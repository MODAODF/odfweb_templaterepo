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

	public function getFolderList()	{
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

	/**
	 * @param \OCP\Files\Node[] $nodes
	 * @return array
	 */
	private function formatNodes(array $nodes)
	{
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
				"mountType" => "templaterepo"

			);
			return $templateFormatFile;
		}, $nodes));
	}

	public function getFolderList()	{
		$x = 1;
		$mounts  = $this->rootFolder->getMountsIn("");
		$mounts = array_filter($mounts, function($mount){
			if($mount->getMountType() == "templaterepo")
				return True;
			else
				return False;
		});

		$nodes = array_map(function ($mount) {
			$path = $mount->getMountPoint();
			$info = Filesystem::getView()->getFileInfo($path);
			$node =  $this->rootFolder->get($path);
			return $node;
		}, $mounts);

		$files = $this->formatNodes($nodes);
		return new JSONResponse(['files' => $files]);
	}

	public function syncFolder($id)
	{
		$api_server = $this->manager->getAPIServer($id);
		$url = $api_server . "/lool/templaterepo/list";

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		$res = curl_exec($curl);
		$serverList = json_decode($res);
		$folder = $this->mountProvider->getFolder($id, false);
		$dirList = $folder->getDirectoryListing();

		$mount_point = $this->manager->getMountPointById($id);

		$sync_result = [];
		foreach ($dirList as $file) {
			$update = false;
			$exist  = false;
			if ($file->getType() == "dir") {
				continue;
			}
			foreach ($serverList->$mount_point as $data) {
				if ($data->endpt == md5($file->getInternalPath())) {
					$exist = true;
					if ($file->getMTime() > intval(strtotime($data->uptime))) {
						$update = true;
					}
					break;
				}
			}
			if ($exist == false) {
				// Upload
				$result = $this->upload($file, $api_server, $mount_point);
				$sync_result[$file->getName()] = $result ? "成功" : "失敗";
			} else if ($exist == true && $update == true) {
				// Update
				$result = $this->update($file, $api_server, $mount_point);
				$sync_result[$file->getName()] = $result ? "成功" : "失敗";
			} else {
				$sync_result[$file->getName()] = '略過';
			}
		}
		if ($sync_result != []) {
			$this->notify("sync-result", $mount_point, $api_server, $sync_result);
		} else {
			$this->notify("sync-empty", $mount_point, $api_server, $sync_result);
		}

		return new DataResponse(true);
	}

	private function upload($file, $api_server, $mount_point)
	{
		$filePath = $file->getInternalPath();
		$fileType = $file->getMimetype();
		$fileName = $file->getName();
		$fileExt = $file->getExtension();
		$file_content = $file->getStorage()->file_get_contents($filePath);
		$path_hash  = md5($filePath);
		$mtime  = $file->getMTime();
		$baseName = str_replace("." . $fileExt, "", $fileName);

		$url = $api_server . "/lool/templaterepo/upload";
		$tmph = tmpfile();
		fwrite($tmph, $file_content);
		$tmpf = stream_get_meta_data($tmph)['uri'];
		$fields = array(
			'endpt' => $path_hash,
			'filename' => curl_file_create($tmpf, $fileType, $fileName),
			'extname' => $fileExt,
			'cname' => $mount_point,
			'docname' => $baseName,
			'uptime' => date("Y-m-d H:i:s", $mtime)
		);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($httpCode != 200) {
			return false;
		} else {
			return true;
		}
	}

	private function update($file, $api_server, $mount_point)
	{
		$filePath = $file->getInternalPath();
		$fileType = $file->getMimetype();
		$fileName = $file->getName();
		$fileExt = $file->getExtension();
		$file_content = $file->getStorage()->file_get_contents($filePath);
		$path_hash  = md5($filePath);
		$mtime  = $file->getMTime();
		$baseName = str_replace("." . $fileExt, "", $fileName);

		$url = $api_server . "/lool/templaterepo/update";
		$tmph = tmpfile();
		fwrite($tmph, $file_content);
		$tmpf = stream_get_meta_data($tmph)['uri'];
		$fields = array(
			'endpt' => $path_hash,
			'filename' => curl_file_create($tmpf, $fileType, $fileName),
			'extname' => $fileExt,
			'cname' => $mount_point,
			'docname' => $baseName,
			'uptime' => date("Y-m-d H:i:s", $mtime)
		);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($httpCode != 200) {
			return false;
		} else {
			return true;
		}
	}

	private function notify(string $type, string $mount_point, string $api_server, array $sync_result)
	{
		$manager = \OC::$server->getNotificationManager();
		$notification = $manager->createNotification();
		$notification->setApp('templaterepo')
			->setUser($this->userId)
			->setDateTime(new \DateTime())
			->setObject('templaterepo', '1') // $type and $id
			->setSubject($type, [
				'sync_result' => $sync_result,
				'user' => $this->userId,
				'mount_point' => $mount_point,
				'api_server' => $api_server
			]);
		$manager->notify($notification);
	}

}
