<?php

declare(strict_types=1);

namespace OCA\HugoPreview\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Config\IUserConfig;
use Psr\Log\LoggerInterface;
use OCA\HugoPreview\AppInfo\Application;

class HugoApiController extends Controller {
	private $userConfig;
	private $logger;
	private $userId;
	private $rootFolder;

	public function __construct($AppName, IRequest $request, IUserConfig $userConfig, LoggerInterface $logger, ?string $UserId, \OCP\Files\IRootFolder $rootFolder) {
		parent::__construct($AppName, $request);
		$this->userConfig = $userConfig;
		$this->logger = $logger;
		$this->userId = $UserId;
		$this->rootFolder = $rootFolder;
	}

	#[NoAdminRequired]
	public function projects(): DataResponse {
		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);

		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		foreach ($projects as &$project) {
			if (!isset($project['created_at'])) {
				try {
					$virtualPath = ltrim($project['folder'], '/');
					if ($userFolder->nodeExists($virtualPath)) {
						$node = $userFolder->get($virtualPath);
						$project['created_at'] = $node->getMTime();
					}
				} catch (\Exception $e) {
					// Fallback to 0 if the folder doesn't exist
					$project['created_at'] = 0;
				}
			}
		}

		return new DataResponse($projects);
	}

	#[NoAdminRequired]
	public function getProject(string $id): DataResponse {
		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);
		foreach ($projects as $project) {
			if ($project['id'] === $id) {
				return new DataResponse($project);
			}
		}
		return new DataResponse(['error' => 'Project not found'], 404);
	}

	#[NoAdminRequired]
	public function saveProject(string $name, string $folder, string $id = ''): DataResponse|JSONResponse {
		if (empty(trim($name)) || empty(trim($folder))) {
			return new JSONResponse(['success' => false, 'error' => 'Project name and folder path are required'], 400);
		}

		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);

		if (empty($id)) {
			// New project
			$id = uniqid('hugo_');
			$projects[] = [
				'id' => $id,
				'name' => $name,
				'folder' => $folder,
				'status' => 'idle',
				'created_at' => time()
			];
		} else {
			// Update
			foreach ($projects as &$project) {
				if ($project['id'] === $id) {
					$project['name'] = $name;
					$project['folder'] = $folder;
					if (!isset($project['created_at'])) {
						$project['created_at'] = time();
					}
					break;
				}
			}
		}

		$this->userConfig->setValueString($this->userId, Application::APP_ID, 'projects', json_encode($projects));
		return new DataResponse(['success' => true, 'id' => $id]);
	}

	#[NoAdminRequired]
	public function deleteProject(string $id): DataResponse {
		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);

		$found = false;
		$newProjects = [];
		foreach ($projects as $project) {
			if ($project['id'] === $id) {
				$found = true;
			} else {
				$newProjects[] = $project;
			}
		}

		if ($found) {
			$this->userConfig->setValueString($this->userId, Application::APP_ID, 'projects', json_encode($newProjects));

			// Also clean up the preview build if it exists
			$appPath = \OC::$server->getAppManager()->getAppPath('hugopreview_nc_app');
			$dest = $appPath . '/img/preview_' . $id;
			if (file_exists($dest)) {
				$this->recursiveDelete($dest);
			}

			return new DataResponse(['success' => true]);
		}

		return new DataResponse(['error' => 'Project not found'], 404);
	}

	#[NoAdminRequired]
	public function buildAllProjects(): JSONResponse {
		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);

		$success = true;
		$firstError = '';
		foreach ($projects as $project) {
			$res = $this->buildProject($project['id']);
			if ($res->getStatus() !== 200) {
				$success = false;
				$data = $res->getData();
				if (isset($data['error']) && empty($firstError)) {
					$firstError = $data['error'];
				}
			}
		}

		return new JSONResponse([
			'status' => $success ? 'success' : 'error',
			'error' => $firstError
		]);
	}

	#[NoAdminRequired]
	public function buildProject(string $id): JSONResponse {
		$projectsJson = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'projects', '[]');
		$projects = json_decode($projectsJson, true);

		$folderPath = '';
		foreach ($projects as $project) {
			if ($project['id'] === $id) {
				$folderPath = $project['folder'];
				break;
			}
		}

		if (empty($folderPath)) {
			return new JSONResponse(['success' => false, 'error' => 'Project not found'], 404);
		}

		$virtualPath = ltrim($folderPath, '/');

		// Security: Prevent directory traversal
		if (strpos($virtualPath, '..') !== false) {
			return new JSONResponse(['success' => false, 'error' => 'Invalid folder path'], 403);
		}

		$dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
		$fullPath = rtrim($dataDir, '/') . '/' . $this->userId . '/files/' . $virtualPath;

		if (!is_dir($fullPath)) {
			return new JSONResponse(['success' => false, 'error' => "Directory not found in Nextcloud filesystem."], 404);
		}

		$appPath = \OC::$server->getAppManager()->getAppPath('hugopreview_nc_app');
		$dest = $appPath . '/img/preview_' . $id;
		if (file_exists($dest)) {
			$this->recursiveDelete($dest);
		}

		$urlGenerator = \OC::$server->getURLGenerator();
		$appSvgUrl = $urlGenerator->imagePath('hugopreview_nc_app', 'app.svg');
		$previewUrl = str_replace('app.svg', 'preview_' . $id . '/', $appSvgUrl);
		$baseUrl = $urlGenerator->getAbsoluteURL($previewUrl);

		// Force relative URLs and build to the static directory
		// Create a temporary directory for pre-processing markdown files
		$tempSrc = sys_get_temp_dir() . '/hugo_src_' . uniqid();
		$this->recursiveCopy($fullPath, $tempSrc);

		try {
			// Rename all .attachments* directories to attachments* so Hugo doesn't ignore them
			$directories = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempSrc, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
			$dirsToRename = [];
			foreach ($directories as $dir) {
				if ($dir->isDir() && strpos($dir->getFilename(), '.attachments') === 0) {
					$dirsToRename[] = $dir->getPathname();
				}
			}
			foreach ($dirsToRename as $oldPath) {
				$newName = substr(basename($oldPath), 1); // remove the dot
				$newPath = dirname($oldPath) . '/' . $newName;
				rename($oldPath, $newPath);
			}

			$mdFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempSrc));
			foreach ($mdFiles as $file) {
				if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
					$content = file_get_contents($file->getPathname());

					// Fix attachment paths
					$content = str_replace('](.attachments', '](attachments', $content);

					// Intelligently handle file attachments (Nextcloud uses ![alt](url) for ALL files)
					$content = preg_replace_callback('/!\[([^\]]*)\]\((attachments[^\)]+)\)/i', function($matches) {
						$alt = $matches[1];
						$url = $matches[2];
						$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

						$imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'];
						if (in_array($ext, $imageExts)) {
							return '![' . $alt . '](' . $url . ')'; // Keep as image
						} elseif ($ext === 'mp4' || $ext === 'webm' || $ext === 'ogg') {
							return '<video controls src="' . $url . '" title="' . $alt . '" style="max-width:100%;"></video>'; // Video tag
						} else {
							// Generic file attachment
							return '<a href="' . $url . '" target="_blank" download style="display:inline-flex; align-items:center; padding:8px 12px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#333; background:#f9f9f9; margin:8px 0;"><span style="margin-right:8px;font-size:20px;">📄</span> <strong>' . htmlspecialchars($alt) . '</strong></a>';
						}
					}, $content);

					// Convert callouts
					$content = preg_replace('/^:::\s*(info|success|warn|warning|error|danger)\s*$/im', '<div class="nc-callout nc-callout-$1">', $content);
					// Add double newline after div to ensure Markdown parses subsequent blocks (like code blocks) correctly
					$content = preg_replace('/^:::\s*$/im', "</div>\n\n", $content);

					// Convert task lists (- [ ] and - [x]) to HTML checkboxes
					// We consume the list marker (- or * or +) to prevent it from rendering as a dash if not in a list
					$content = preg_replace('/^(\s*)[-*+]\s+\[ \]\s+(.*)$/m', '$1<span class="nc-task-item" style="display:flex;align-items:center;"><input type="checkbox" style="margin-right:8px;cursor:pointer;"> <span class="nc-task-text">$2</span></span>', $content);
					$content = preg_replace('/^(\s*)[-*+]\s+\[[xX]\]\s+(.*)$/m', '$1<span class="nc-task-item" style="display:flex;align-items:center;"><input type="checkbox" checked style="margin-right:8px;cursor:pointer;"> <span class="nc-task-text">$2</span></span>', $content);

					// Split by backticks to avoid replacing inside inline code or fenced code blocks
					$parts = preg_split('/(`+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
					$processed = '';
					$inCode = false;
					$codeFenceLength = 0;

					foreach ($parts as $part) {
						if (preg_match('/^`+$/', $part)) {
							$len = strlen($part);
							if (!$inCode) {
								$inCode = true;
								$codeFenceLength = $len;
							} elseif ($len === $codeFenceLength) {
								$inCode = false;
							}
							$processed .= $part;
						} else {
							if (!$inCode) {
								// we are in normal markdown text
								// Replace __text__ with <u>text</u>
								$part = preg_replace('/(?<!_)__([^_]+)__(?!_)/', '<u>$1</u>', $part);
								// Replace ~~text~~ with <del>text</del>
								$part = preg_replace('/(?<!~)~~([^~]+)~~(?!~)/', '<del>$1</del>', $part);
							}
							$processed .= $part;
						}
					}

					file_put_contents($file->getPathname(), $processed);
				}
			}

			// Hide .html and .md files in attachments from Hugo's parser by renaming them
			$allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempSrc));
			foreach ($allFiles as $file) {
				if ($file->isFile()) {
					$path = $file->getPathname();
					// Check if it is inside an attachments directory
					if (preg_match('/\/attachments[^\/]*\//', str_replace('\\', '/', $path))) {
						$ext = strtolower($file->getExtension());
						if ($ext === 'html' || $ext === 'htm' || $ext === 'md') {
							rename($path, $path . '.hugo_safe');
						}
					}
				}
			}
		} catch (\Exception $e) {
			$this->logger->error("Error pre-processing markdown: " . $e->getMessage());
		}

		// Enable unsafe Goldmark renderer so our raw <u> and <del> tags aren't stripped
		$command = "env HUGO_RELATIVEURLS=true HUGO_MARKUP_GOLDMARK_RENDERER_UNSAFE=true hugo --source " . escapeshellarg($tempSrc) . " --destination " . escapeshellarg($dest) . " --baseURL '/' 2>&1";

		$output = [];
		$return_var = 0;
		exec($command, $output, $return_var);

		// Clean up the temporary source directory
		$this->recursiveDelete($tempSrc);

		if ($return_var !== 0) {
			$this->logger->error("Hugo build failed for $folderPath: " . implode("\n", $output));
			return new JSONResponse([
				'success' => false,
				'error' => "Build failed. Code: $return_var.\n" . implode("\n", $output)
			], 500);
		}

		// Restore .hugo_safe files
		try {
			$safeFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dest));
			foreach ($safeFiles as $file) {
				if ($file->isFile() && substr($file->getFilename(), -10) === '.hugo_safe') {
					$oldPath = $file->getPathname();
					$newPath = substr($oldPath, 0, -10);
					rename($oldPath, $newPath);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error("Error restoring safe files: " . $e->getMessage());
		}

		// Fix relative links and inject UI enhancements in HTML files
		try {
			$htmlFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dest));

			$uiEnhancements = <<<'EOD'
<style>
blockquote {
    border-left: 4px solid #0082c9; padding-left: 16px; margin-left: 0; color: #444; background-color: #f9f9f9; padding-top: 8px; padding-bottom: 8px;
}
table { border-collapse: collapse; width: 100%; margin: 16px 0; font-family: sans-serif; }
th, td { border: 1px solid #ddd; padding: 12px 16px; text-align: left; }
th { background-color: #f2f2f2; font-weight: bold; }
pre { background-color: #f1f1f1; border-radius: 4px; padding: 12px 16px; overflow-x: auto; font-family: monospace; border: 1px solid #ddd; margin: 16px 0; }
code { font-family: monospace; background-color: #f1f1f1; padding: 2px 4px; border-radius: 4px; }
pre code { padding: 0; background-color: transparent; border: 0; }
.nc-callout {
    padding: 12px 12px 12px 40px; margin: 16px 0; border-left: 4px solid; border-radius: 4px; font-family: sans-serif;
    background-repeat: no-repeat; background-position: 12px center; background-size: 20px 20px;
}
.nc-callout p { margin-top: 0; margin-bottom: 0; }
.nc-callout-info {
    background-color: #e6f3fb; border-color: #0082c9;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230082c9' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='12' y1='16' x2='12' y2='12'%3E%3C/line%3E%3Cline x1='12' y1='8' x2='12.01' y2='8'%3E%3C/line%3E%3C/svg%3E");
}
.nc-callout-success {
    background-color: #e6f6eb; border-color: #019a5a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23019a5a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'%3E%3C/path%3E%3Cpolyline points='22 4 12 14.01 9 11.01'%3E%3C/polyline%3E%3C/svg%3E");
}
.nc-callout-warn, .nc-callout-warning {
    background-color: #fdf5e6; border-color: #e59900;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23e59900' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'%3E%3C/path%3E%3Cline x1='12' y1='9' x2='12' y2='13'%3E%3C/line%3E%3Cline x1='12' y1='17' x2='12.01' y2='17'%3E%3C/line%3E%3C/svg%3E");
}
.nc-callout-error, .nc-callout-danger {
    background-color: #fae9e9; border-color: #d2322d;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d2322d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='15' y1='9' x2='9' y2='15'%3E%3C/line%3E%3Cline x1='9' y1='9' x2='15' y2='15'%3E%3C/line%3E%3C/svg%3E");
}
li.task-list-item, li:has(> input[type="checkbox"]) {
    list-style-type: none !important; margin-left: -1em;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nc-task-item input[type="checkbox"]').forEach(function(cb) {
        cb.removeAttribute('disabled');
        cb.style.cursor = 'pointer';

        var targetSpan = cb.nextElementSibling;
        if (targetSpan && targetSpan.classList.contains('nc-task-text')) {
            var updateStrike = function() {
                targetSpan.style.textDecoration = cb.checked ? 'line-through' : 'none';
                targetSpan.style.opacity = cb.checked ? '0.6' : '1';
                targetSpan.style.transition = 'all 0.2s';
            };
            cb.addEventListener('change', updateStrike);
            updateStrike();
        }
    });
});
</script>
EOD;

			foreach ($htmlFiles as $file) {
				if ($file->isFile() && $file->getExtension() === 'html') {
					$content = file_get_contents($file->getPathname());
					$content = preg_replace_callback('/<a([^>]*)href="(\.[^"]*)"/', function($matches) {
						$before = $matches[1];
						$href = $matches[2];
						// If the link points to a file with an extension, keep it as is.
						if (preg_match('/\.[a-zA-Z0-9]+$/', $href)) {
							return '<a' . $before . 'href="' . $href . '"';
						}
						// Otherwise, it is a directory. Append /index.html.
						$newHref = rtrim($href, '/') . '/index.html';
						return '<a' . $before . 'href="' . $newHref . '"';
					}, $content);
					$content = preg_replace('/<a([^>]*)href="(https?:\/\/[^"]+)"/', '<a$1target="_blank" href="$2"', $content);

					// Inject UI enhancements right before closing </body> or </html>
					if (strpos($content, '</body>') !== false) {
						$content = str_replace('</body>', "\n" . $uiEnhancements . "\n</body>", $content);
					} else {
						$content .= "\n" . $uiEnhancements;
					}

					file_put_contents($file->getPathname(), $content);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error("Error fixing HTML links: " . $e->getMessage());
		}

		return new JSONResponse([
			'success' => true,
			'message' => 'Build successful',
			'preview_url' => $baseUrl . 'index.html'
		]);
	}

	private function recursiveDelete(string $dir): void {
		if (!is_dir($dir)) {
			if (file_exists($dir)) {
				unlink($dir);
			}
			return;
		}
		$files = array_diff(scandir($dir), ['.', '..']);
		foreach ($files as $file) {
			$path = "$dir/$file";
			(is_dir($path) && !is_link($path)) ? $this->recursiveDelete($path) : unlink($path);
		}
		rmdir($dir);
	}

	private function recursiveCopy(string $src, string $dst): void {
		if (!is_dir($src)) {
			return;
		}
		@mkdir($dst, 0777, true);
		$dir = opendir($src);
		while (false !== ($file = readdir($dir))) {
			if (($file !== '.') && ($file !== '..')) {
				$srcPath = $src . '/' . $file;
				$dstPath = $dst . '/' . $file;
				if (is_dir($srcPath)) {
					$this->recursiveCopy($srcPath, $dstPath);
				} else {
					copy($srcPath, $dstPath);
				}
			}
		}
		closedir($dir);
	}
}
