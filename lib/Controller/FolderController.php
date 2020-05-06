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

namespace OCA\TemplateRepo\Controller;

use OC\AppFramework\OCS\V1Response;
use OCA\TemplateRepo\Folder\FolderManager;
use OCA\TemplateRepo\Mount\MountProvider;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OC\Files\Filesystem;
use OC\Files\Node\Node;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUser;

class FolderController extends OCSController {
	private FolderManager $manager;
	private MountProvider $mountProvider;
	private IRootFolder $rootFolder;
	private ?IUser $user = null;

	public function __construct(
		string $AppName,
		IRequest $request,
		FolderManager $manager,
		MountProvider $mountProvider,
		IRootFolder $rootFolder,
		IUserSession $userSession
	) {
		parent::__construct($AppName, $request);
		$this->manager = $manager;
		$this->mountProvider = $mountProvider;
		$this->rootFolder = $rootFolder;
		$this->user = $userSession->getUser();

		$this->registerResponder('xml', function ($data): V1Response {
			return $this->buildOCSResponseXML('xml', $data);
		});
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function getFolders(): DataResponse {
		return new DataResponse($this->manager->getAllFoldersWithSize($this->getRootFolderStorageId()));
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 * @param int $id
	 * @return DataResponse
	 */
	public function getFolder(int $id): DataResponse {
		return new DataResponse($this->manager->getFolder($id, $this->getRootFolderStorageId()));
	}

	private function getRootFolderStorageId(): int {
		return $this->rootFolder->getMountPoint()->getNumericStorageId();
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function addFolder(string $mountpoint): DataResponse {
		$id = $this->manager->createFolder($mountpoint);
		return new DataResponse(['id' => $id]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function removeFolder(int $id): DataResponse {
		$folder = $this->mountProvider->getFolder($id);
		if ($folder) {
			$folder->delete();
		}
		$this->manager->removeFolder($id);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->setMountPoint($id, $mountPoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function addGroup(int $id, string $group): DataResponse {
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function removeGroup(int $id, string $group): DataResponse {
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

 	/**
 	 * @param int $id
	 * @param string $user
	 * @return DataResponse
	 */
	public function addUser($id, $user) {
		$this->manager->addApplicableUser($id, $user);
		return new DataResponse(true);
	}

	/**
	 * @param int $id
	 * @param string $user
	 * @return DataResponse
	 */
	public function removeUser($id, $user) {
		$this->manager->removeApplicableUser($id, $user);
		return new DataResponse(true);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @param int $id
	 * @param string $group
	 * @param string $permissions
	 * @return DataResponse
	 */
	public function setPermissionsForUser($id, $user, $permissions) {
		$this->manager->setUserPermissions($id, $user, $permissions);
		return new DataResponse(true);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 * @throws \OCP\DB\Exception
	 */
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function setQuota(int $id, int $quota): DataResponse {
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function setACL(int $id, bool $acl): DataResponse {
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\TemplateRepo\Settings\Admin)
	 */
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$this->manager->renameFolder($id, $mountpoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Overwrite response builder to customize xml handling to deal with spaces in folder names
	 *
	 * @param string $format json or xml
	 * @param DataResponse $data the data which should be transformed
	 * @since 8.1.0
	 * @return \OC\AppFramework\OCS\V1Response
	 */
	private function buildOCSResponseXML(string $format, DataResponse $data): V1Response {
		/** @var array $folderData */
		$folderData = $data->getData();
		if (isset($folderData['id'])) {
			// single folder response
			$folderData = $this->folderDataForXML($folderData);
		} elseif (is_array($folderData) && count($folderData) && isset(current($folderData)['id'])) {
			// folder list
			$folderData = array_map([$this, 'folderDataForXML'], $folderData);
		}
		$data->setData($folderData);
		return new V1Response($data, $format);
	}

	private function folderDataForXML(array $data): array {
		$groups = $data['groups'];
		$data['groups'] = [];
		foreach ($groups as $id => $permissions) {
			$data['groups'][] = ['@group_id' => $id, '@permissions' => $permissions];
		}
		return $data;
	}

	/**
	 * @NoAdminRequired
	 */
	public function aclMappingSearch(int $id, ?int $fileId, string $search = ''): DataResponse {
		$users = [];
		$groups = [];

		if ($this->manager->canManageACL($id, $this->user) === true) {
			$groups = $this->manager->searchGroups($id, $search);
			$users = $this->manager->searchUsers($id, $search);
		}
		return new DataResponse([
			'users' => $users,
			'groups' => $groups,
		]);
	}

	/**
	 * @param \OCP\Files\Node[] $nodes
	 * @return array
	 */
	private function formatNodes(array $nodes) {
		return array_values(array_map(function (Node $node) {
			/** @var \OC\Files\Node\Node $shareTypes */
			$shareTypes = [0];
			$file = \OCA\Files\Helper::formatFileInfo($node->getFileInfo());
			$parts = explode('/', $node->getPath());
			if (isset($parts[4])) {
				$file['path'] = '/' . $parts[4];
			} else {
				$file['path'] = '/';
			}
			if (!empty($shareTypes)) {
				$file['shareTypes'] = $shareTypes;
			}
			$templateFormatFile = array(
				"id" => strval($file['id']),
				"parentId" => strval($file['parentId']),
				"permissions" => $file['permissions'],
				"mimetype" => $file['mimetype'],
				"name" => $parts[3],
				"size" => $file['size'],
				"type" => "dir",
				"etag" => $file['etag'],
				"path" => $file['path'],
				"mtime" => $file['mtime'],
				"mountType" => "group"

			);
			return $templateFormatFile;
		}, $nodes));
	}


	public function getFolderList()
	{
		$x=1;
		$mounts  = $this->rootFolder->getMountsIn("");
		$mounts = array_filter($mounts, function($mount){
			if($mount->getMountType() == "group")
				return True;
			else
				return False;
		});

		$nodes = array_map(function($mount){
			$path = $mount->getMountPoint();
			$info = Filesystem::getView()->getFileInfo($path);
			$node =  $this->rootFolder->get($path);
			return $node;
		}, $mounts);

		$files = $this->formatNodes($nodes);
		return new JSONResponse(['files' => $files]);
	}

	/* @param int $id
	 * @param string $apiserver
	 * @return DataResponse
	 */
	public function setAPIServer($id, $apiserver) {
		$this->manager->setAPIServer($id, $apiserver);
		return new DataResponse(true);
 	}
}
