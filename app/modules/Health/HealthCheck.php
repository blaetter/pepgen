<?php

namespace Pepgen\Health;

use Pepgen\Helper\Config;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Checks whether pepgen can generate ePubs, for the monitoring (GET /health).
 *
 * Only cheap checks of what a generation needs: the config, the original
 * ePubs, the writable directories and log file and the zip program. Nothing
 * is generated or written. The result names only the checks and "ok" or
 * "error", the reasons go to the error log of PHP.
 */
class HealthCheck
{
    /**
     * Config values a generation needs.
     */
    public const REQUIRED_CONFIG = [
        'base_path',
        'secret',
        'textpattern',
        'template',
        'files_to_replace',
        'epub_original_dir',
        'epub_temp_dir',
        'epub_public_dir',
    ];

    /**
     * Directories searched for zip besides PATH: PHP-FPM clears the
     * environment by default, while the generation (proc_open without a
     * shell) still finds zip in the standard directories.
     */
    public const ZIP_DIRS = ['/usr/bin', '/usr/local/bin', '/bin'];

    /**
     * Reasons of the failed checks, for the log.
     *
     * @var string[]
     */
    private $reasons = [];

    public function __construct(
        private Config $config = new Config(),
        private ExecutableFinder $finder = new ExecutableFinder()
    ) {
    }

    /**
     * Whether a request is the health check: GET or HEAD on "/health" next
     * to the front controller (also if pepgen runs in a subdirectory).
     *
     * @param string $uri The request URI, with or without query string
     * @param string $script_name The path of the front controller, e.g. "/index.php"
     * @param string $method The request method
     * @return bool
     */
    public static function isHealthRequest($uri, $script_name, $method)
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }
        $base = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
        return $base . '/health' === parse_url($uri, PHP_URL_PATH);
    }

    /**
     * Runs the checks.
     *
     * @return array
     *   "status" ("ok" or "error") and "checks" (name => "ok" or "error").
     */
    public function run()
    {
        $this->reasons = [];
        $config = $this->checkConfig();
        $checks = [
            'config' => $config,
            'epubs' => $config && $this->checkEpubs(),
            'storage' => $config && $this->checkStorage(),
            'logs' => $this->checkLogs(),
            'zip' => $this->checkZip(),
        ];
        foreach ($this->reasons as $reason) {
            error_log('pepgen health: ' . $reason);
        }
        return [
            'status' => in_array(false, $checks, true) ? 'error' : 'ok',
            'checks' => array_map(fn ($ok) => $ok ? 'ok' : 'error', $checks),
        ];
    }

    /**
     * Returns the reasons of the failed checks of the last run.
     *
     * @return string[]
     */
    public function getReasons()
    {
        return $this->reasons;
    }

    private function checkConfig()
    {
        $missing = array_filter(self::REQUIRED_CONFIG, fn ($key) => !$this->config->get($key));
        if ($missing) {
            $this->reasons[] = 'config: missing ' . implode(', ', $missing);
            return false;
        }
        return true;
    }

    /**
     * At least one unzipped original ePub (a directory) is readable.
     */
    private function checkEpubs()
    {
        $dir = $this->config->getPath('epub_original_dir');
        if (!is_dir($dir) || !is_readable($dir)) {
            $this->reasons[] = 'epubs: directory not readable';
            return false;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' !== $entry[0] && is_dir($dir . '/' . $entry)) {
                return true;
            }
        }
        $this->reasons[] = 'epubs: no original ePub';
        return false;
    }

    /**
     * The temp and the download directory are writable.
     */
    private function checkStorage()
    {
        $ok = true;
        foreach (['epub_temp_dir' => 'temp', 'epub_public_dir' => 'download'] as $key => $name) {
            $dir = $this->config->getPath($key);
            if (!is_dir($dir) || !is_writable($dir)) {
                $this->reasons[] = 'storage: ' . $name . ' directory not writable';
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * The log directory is writable, and so is the log file of today if it
     * exists (e.g. created by a command run as another user, the logger then
     * fails on every request).
     */
    private function checkLogs()
    {
        $file = $this->config->getLogFile();
        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->reasons[] = 'logs: directory not writable';
            return false;
        }
        // The name the logger rotates to (Monolog RotatingFileHandler: name-Y-m-d.extension).
        $today = $dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . date('Y-m-d') . '.'
            . pathinfo($file, PATHINFO_EXTENSION);
        if (file_exists($today) && !is_writable($today)) {
            $this->reasons[] = 'logs: log file of today not writable';
            return false;
        }
        return true;
    }

    /**
     * The zip program is found (without running it).
     */
    private function checkZip()
    {
        $zip = $this->finder->find('zip', null, self::ZIP_DIRS);
        if (null === $zip || !is_executable($zip)) {
            $this->reasons[] = 'zip: program not found';
            return false;
        }
        return true;
    }
}
