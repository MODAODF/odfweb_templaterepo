<?php

namespace OCA\TemplateRepo\Notifier;

class Notifier implements \OCP\Notification\INotifier {
    protected $factory;
    protected $url;

    public function __construct() {

    }

    public function prepare(\OCP\Notification\INotification $notification, $languageCode)
    {
        if ($notification->getApp() !== 'files_sharing') {
            // Not my app => throw
            throw new \InvalidArgumentException();
        }
        // Read the language from the notification
        $l = $this->factory->get('files_sharing', $languageCode);
        switch ($notification->getSubject()) {
            case 'remote_share':
                $x = 1;
            default:
                return $notification;
        }
    }
}