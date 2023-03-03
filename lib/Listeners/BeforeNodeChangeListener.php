<?php

declare(strict_types=1);

namespace OCA\TemplateRepo\Listeners;

use Exception;
use \OCP\HintException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeWrittenEvent;
// use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
// use OCP\Files\Events\Node\BeforeNodeReadEvent;
// use OCP\Files\Events\Node\BeforeNodeTouchedEvent;

class BeforeNodeChangeListener implements IEventListener {

	private const SUPPORT_MIMETYPE = [
		'application/vnd.oasis.opendocument.text-template',         // ott
		'application/vnd.oasis.opendocument.spreadsheet-template',  // ots
		'application/vnd.oasis.opendocument.presentation-template', // otp
	];

	public function handle(Event $event): void {
		if (!($event instanceof BeforeNodeCopiedEvent)  &&
			!($event instanceof BeforeNodeRenamedEvent) &&
			!($event instanceof BeforeNodeCreatedEvent) &&
			// !($event instanceof BeforeNodeDeletedEvent) &&
			// !($event instanceof BeforeNodeReadEvent) &&
			// !($event instanceof BeforeNodeTouchedEvent) &&
			!($event instanceof BeforeNodeWrittenEvent)
		) {
			return;
		}

		/**
		 * BeforeNodeCopiedEvent | BeforeNodeRenamedEvent
		 * $event 有 target 和 source
		 */
		if ($event instanceof BeforeNodeCopiedEvent || $event instanceof BeforeNodeRenamedEvent) {
			// 檢查 target (預期 Copied 或 Renamed 後的 Node)
			$target = $event->getTarget();
			$targetFolder = $target->getParent();
			if (!$this->checkMount($target) && !$this->checkMount($targetFolder)) {
				return;
			}
			// 檢查 source
			$source = $event->getSource();
			$mimeSource = $this->getMimeByFile($source);
			if (!$this->isSupportMime($mimeSource)) {
				throw new HintException($this->getThrowMsg($source));
			}
			return;
		}

		/**
		 * BeforeNodeCreatedEvent | BeforeNodeDeletedEvent | BeforeNodeTouchedEvent | BeforeNodeWrittenEvent
		 */
		$node = $event->getNode();
		if ($node instanceof \OC\Files\Node\NonExistingFile || $node instanceof \OC\Files\Node\NonExistingFolder) {
			$folder = $node->getParent();
			$isMountOnThis = $this->checkMount($folder);
		} else {
			$isMountOnThis = $this->checkMount($node);
		}

		if ($isMountOnThis) {
			$method = \OC::$server->getRequest()->getMethod();
			if ($method === 'MKCOL') {
				throw new HintException('TemplateRepo: Unsupported method(MKCOL).');
			}
			$mime = $this->getMimeByFile($node) ?? $this->getMimeByRequest($method) ?? '';
			if (!$this->isSupportMime($mime)) {
				throw new HintException($this->getThrowMsg($node));
			}
		}
	}

	private function getThrowMsg($node = null) {
		$method = \OC::$server->getRequest()->getMethod();
		$msg = 'TemplateRepo (' . $method . '): Unsupported file type.';
		if ($node) {
			try {
				$fileInfo = ($node instanceof \OC\Files\FileInfo) ? $node : $node->getFileInfo();
				$name = $fileInfo->getName();
				$msg .= '('.$name.')';
			} catch (\Throwable $th) {
			}
		}
		return $msg;
	}

	private function checkMount($file): bool {
		try {
			$fileInfo = ($file instanceof \OC\Files\FileInfo) ? $file : $file->getFileInfo();
			$mountType = $fileInfo->getMountPoint()->getMountType();
			if ($mountType !== "templaterepo") throw new Exception();
		} catch (\Throwable $th) {
			return false;
		}
		return true;
	}

	private function isSupportMime(string $mime): bool {
		return in_array($mime, self::SUPPORT_MIMETYPE);
	}

	private function getMimeByFile($node) {
		try {
			$mime = $node->getMimetype() ?? null;
		} catch (\Throwable $th) {
			$mime = null;
		}
		return $mime;
	}

	private function getMimeByRequest($method): string {
		if ($method === 'PUT') { // Upload
			$content = fopen('php://input', 'rb');
			$mime = mime_content_type($content);
		} else if ($method === 'COPY') { // NC 內部複製
			$baseUri = \OC::$WEBROOT . "/remote.php/dav/";
			$request = \OC::$server->getRequest();
			try {
				$tmpServer = new \OCA\DAV\Server($request, $baseUri);
				$path = '/' . $tmpServer->server->httpRequest->getPath();
				$path = str_replace($baseUri, "", $path);
				$node = $tmpServer->server->tree->getNodeForPath($path);
				$mime = $this->getMimeByFile($node->getFileInfo());
			} catch (\Throwable $th) {
			}
		} // else if ($method === 'MOVE') {}

		return $mime ?? '';
	}
}
