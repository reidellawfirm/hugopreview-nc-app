<?php

declare(strict_types=1);

namespace OCA\HugoPreview\AppInfo;

use OCA\HugoPreview\Listener\BeforeTemplateRenderedListener;
use OCA\HugoPreview\Listener\HugoNodeDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
    public const APP_ID = 'hugopreview_nc_app';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, HugoNodeDeletedListener::class);
    }

    public function boot(IBootContext $context): void {
        $server = $context->getServerContainer();
        $loadFilesIntegration = static function (): void {
            $uri = \OC::$server->getRequest()->getRequestUri();
            if (str_contains($uri, '/apps/files')) {
                Util::addScript(self::APP_ID, self::APP_ID . '-files-integration');
            }
        };

        try {
            $loadFilesIntegration();
            $dispatcher = $server->get(\OCP\EventDispatcher\IEventDispatcher::class);
            $dispatcher->addListener(
                BeforeTemplateRenderedEvent::class,
                static function ($event) use ($loadFilesIntegration): void {
                    try {
                        $loadFilesIntegration();
                    } catch (\Throwable) {
                        // Files integration is optional on non-Files pages.
                    }
                },
            );
        } catch (\Throwable) {
            // The standalone Hugo page does not depend on Files integration.
        }
    }
}
