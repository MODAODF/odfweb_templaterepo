<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TemplateRepo\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Trashbin\Expiration;
use OCA\TemplateRepo\ACL\ACLManagerFactory;
use OCA\TemplateRepo\ACL\RuleManager;
use OCA\TemplateRepo\ACL\UserMapping\IUserMappingManager;
use OCA\TemplateRepo\ACL\UserMapping\UserMappingManager;
use OCA\TemplateRepo\BackgroundJob\ExpireGroupPlaceholder;
use OCA\TemplateRepo\BackgroundJob\ExpireGroupTrash as ExpireGroupTrashJob;
use OCA\TemplateRepo\BackgroundJob\ExpireGroupVersions as ExpireGroupVersionsJob;
use OCA\TemplateRepo\CacheListener;
use OCA\TemplateRepo\Command\ExpireGroup\ExpireGroupBase;
use OCA\TemplateRepo\Command\ExpireGroup\ExpireGroupVersionsTrash;
use OCA\TemplateRepo\Command\ExpireGroup\ExpireGroupVersions;
use OCA\TemplateRepo\Command\ExpireGroup\ExpireGroupTrash;
use OCA\TemplateRepo\Folder\FolderManager;
use OCA\TemplateRepo\Helper\LazyFolder;
use OCA\TemplateRepo\Listeners\LoadAdditionalScriptsListener;
use OCA\TemplateRepo\Mount\MountProvider;
use OCA\TemplateRepo\Trash\TrashBackend;
use OCA\TemplateRepo\Trash\TrashManager;
use OCA\TemplateRepo\Versions\GroupVersionsExpireManager;
use OCA\TemplateRepo\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public function __construct(array $urlParams = []) {
		parent::__construct('templaterepo', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LoadAdditionalScriptsListener::class);

		$context->registerServiceAlias('GroupAppFolder', LazyFolder::class);

		$context->registerService(MountProvider::class, function (IAppContainer $c): MountProvider {
			$rootProvider = function () use ($c): LazyFolder {
				return $c->get('GroupAppFolder');
			};
			$config = $c->get(IConfig::class);
			$allowRootShare = $config->getAppValue('templaterepo', 'allow_root_share', 'true') === 'true';

			return new MountProvider(
				$c->getServer()->getGroupManager(),
				$c->get(FolderManager::class),
				$rootProvider,
				$c->get(ACLManagerFactory::class),
				$c->get(IUserSession::class),
				$c->get(IRequest::class),
				$c->get(ISession::class),
				$c->get(IMountProviderCollection::class),
				$c->get(IDBConnection::class),
				$c->get(ICacheFactory::class)->createLocal("templaterepo"),
				$allowRootShare
			);
		});

		$context->registerService(TrashBackend::class, function (IAppContainer $c): TrashBackend {
			$trashBackend = new TrashBackend(
				$c->get(FolderManager::class),
				$c->get(TrashManager::class),
				$c->get('GroupAppFolder'),
				$c->get(MountProvider::class),
				$c->get(ACLManagerFactory::class),
				$c->getServer()->getRootFolder()
			);
			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			if ($hasVersionApp) {
				$trashBackend->setVersionsBackend($c->get(VersionsBackend::class));
			}
			return $trashBackend;
		});

		$context->registerService(VersionsBackend::class, function (IAppContainer $c): VersionsBackend {
			return new VersionsBackend(
				$c->get('GroupAppFolder'),
				$c->get(MountProvider::class),
				$c->get(ITimeFactory::class),
				$c->get(LoggerInterface::class)
			);
		});

		$context->registerService(ExpireGroupBase::class, function (IAppContainer $c): ExpireGroupBase {
			// Multiple implementation of this class exists depending on if the trash and versions
			// backends are enabled.

			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			$hasTrashApp = interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class);

			if ($hasVersionApp && $hasTrashApp) {
				return new ExpireGroupVersionsTrash(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			if ($hasVersionApp) {
				return new ExpireGroupVersions(
					$c->get(GroupVersionsExpireManager::class),
				);
			}

			if ($hasTrashApp) {
				return new ExpireGroupTrash(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			return new ExpireGroupBase();
		});

		$context->registerService(\OCA\TemplateRepo\BackgroundJob\ExpireGroupVersions::class, function (IAppContainer $c) {
			if (interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class)) {
				return new ExpireGroupVersionsJob(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(ITimeFactory::class)
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerService(\OCA\TemplateRepo\BackgroundJob\ExpireGroupTrash::class, function (IAppContainer $c) {
			if (interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class)) {
				return new ExpireGroupTrashJob(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class),
					$c->get(IConfig::class),
					$c->get(ITimeFactory::class)
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerService(ACLManagerFactory::class, function (IAppContainer $c): ACLManagerFactory {
			$rootFolderProvider = function () use ($c): \OCP\Files\IRootFolder {
				return $c->getServer()->getRootFolder();
			};
			return new ACLManagerFactory(
				$c->get(RuleManager::class),
				$rootFolderProvider
			);
		});

		$context->registerServiceAlias(IUserMappingManager::class, UserMappingManager::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMountProviderCollection $mountProviderCollection, CacheListener $cacheListener, IGroupManager $groupManager): void {
			$mountProviderCollection->registerProvider($this->getMountProvider());

			$groupManager->listen('\OC\Group', 'postDelete', function (IGroup $group) {
				$this->getFolderManager()->deleteGroup($group->getGID());
			});
			$cacheListener->listen();
		});

		\OCA\Files\App::getNavigationManager()->add([
			'id' => 'templaterepolist',
			'appname' => 'templaterepo',
			'script' => 'list.php',
			'order' => 25,
			'name' => "群組資料夾",
			'icon' => "templaterepo"
		]);
	}

	public function getMountProvider(): MountProvider {
		return $this->getContainer()->get(MountProvider::class);
	}

	public function getFolderManager(): FolderManager {
		return $this->getContainer()->get(FolderManager::class);
	}
}
