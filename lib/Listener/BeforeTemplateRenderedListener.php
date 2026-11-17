<?php

declare(strict_types=1);

namespace OCA\HugoPreview\Listener;

use OCA\HugoPreview\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
    public function handle(Event $event): void {
        if (!$event instanceof BeforeTemplateRenderedEvent) {
            return;
        }

        try {
            $uri = \OC::$server->getRequest()->getRequestUri();
            if (str_contains($uri, '/apps/files')) {
                Util::addScript(Application::APP_ID, Application::APP_ID . '-files-integration');
            }
        } catch (\Throwable) {
            // Files integration is optional on non-Files pages.
        }
    }
}
