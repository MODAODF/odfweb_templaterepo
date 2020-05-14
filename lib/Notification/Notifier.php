<?php

namespace OCA\TemplateRepo\Notification;

use OCP\Notification\INotifier;

class Notifier implements INotifier {
    protected $factory;
    protected $url;

    public function __construct() {

    }

    public function prepare(\OCP\Notification\INotification $notification, $languageCode)
    {
        if ($notification->getApp() !== 'templaterepo') {
            // Not my app => throw
            throw new \InvalidArgumentException();
        }
        // Read the language from the notification
        $parameters = $notification->getSubjectParameters();
        switch ($notification->getSubject()) {
            case 'upload-success':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端同步成功";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'upload-fail':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端同步失敗";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'delete-success':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端刪除成功";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'delete-fail':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端刪除失敗";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'update-success':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端更新成功";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'update-fail':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = $parameters['filename'] . " 遠端更新失敗";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'sync-success':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = "[SYSTEM] ".$parameters['filename'] . " 同步成功";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            case 'sync-fail':
                $subject = "TemplateRepo";
                $subjectParameters = [];
                $message = "[SYSTEM] ".$parameters['filename'] . " 同步失敗";
                $messageParameters = [];
                $notification->setRichSubject($subject, $subjectParameters)
                    ->setParsedSubject($subject)
                    ->setRichMessage($message, $messageParameters)
                    ->setParsedMessage($message);
                return $notification;
            default:
                return $notification;
        }
    }
}