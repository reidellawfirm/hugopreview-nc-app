<?php

declare(strict_types=1);

namespace OCA\HugoPreview\Listener;

use OCA\HugoPreview\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;

/** @template-implements IEventListener<NodeDeletedEvent> */
class HugoNodeDeletedListener implements IEventListener {
    public function __construct(private IUserConfig $userConfig) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof NodeDeletedEvent) {
            return;
        }

        try {
            $internalPath = $event->getNode()->getPath();
            if (!preg_match('#^/([^/]+)/files(?=/|$)(.*)$#', $internalPath, $matches)) {
                return;
            }

            $userId = $matches[1];
            $virtualPath = $matches[2] !== '' ? $matches[2] : '/';
            $projectsJson = $this->userConfig->getValueString($userId, Application::APP_ID, 'projects', '[]');
            $projects = json_decode($projectsJson, true);
            if (!is_array($projects) || $projects === []) {
                return;
            }

            $newProjects = [];
            $found = false;
            foreach ($projects as $project) {
                $folder = $project['folder'] ?? '';
                $matchesProject = $virtualPath !== '/' && rtrim($virtualPath, '/') === rtrim($folder, '/');
                if ($matchesProject) {
                    $found = true;
                    $appPath = \OC::$server->getAppManager()->getAppPath(Application::APP_ID);
                    $dest = $appPath . '/img/preview_' . $project['id'];
                    if (file_exists($dest)) {
                        $this->recursiveDelete($dest);
                    }
                } else {
                    $newProjects[] = $project;
                }
            }

            if ($found) {
                $this->userConfig->setValueString($userId, Application::APP_ID, 'projects', json_encode($newProjects));
            }
        } catch (\Throwable) {
            // File deletion must continue even if preview cleanup fails.
        }
    }

    private function recursiveDelete(string $dir): void {
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                unlink($dir);
            }
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            (is_dir($path) && !is_link($path)) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
