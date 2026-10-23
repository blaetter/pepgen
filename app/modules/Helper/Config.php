<?php

namespace Pepgen\Helper;

use Symfony\Component\Yaml\Yaml;

class Config
{
    /**
     * The environment variable that may contain the path of another config file, e.g. for the tests.
     */
    public const ENV_CONFIG_FILE = 'PEPGEN_CONFIG';

    private $config;

    public function __construct()
    {
        $this->config = $this->getConfig();
    }

    /**
     * Returns the path of the config file.
     *
     * @return string
     */
    public static function getConfigFile()
    {
        return getenv(self::ENV_CONFIG_FILE) ?: __DIR__ . '/../../../app/config/config.yml';
    }

    private function getConfig()
    {
        $config_file = self::getConfigFile();
        if (!is_file($config_file)) {
            return [];
        }
        return Yaml::parseFile($config_file) ?: [];
    }

    public function get($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        return false;
    }

    /**
     * Returns the path of a directory of the config within the installation (base_path + the value of the key).
     *
     * @param string $key The key of the directory, e.g. "epub_temp_dir"
     * @return string
     */
    public function getPath($key)
    {
        return $this->get('base_path') . $this->get($key);
    }

    /**
     * Returns the path of the log file (before rotation: the logger adds the date to its name).
     *
     * Without base_path the installation directory, without epub_log_dir the directory "/logs".
     *
     * @return string
     */
    public function getLogFile()
    {
        $base_path = $this->get('base_path') ?: __DIR__ . '/../../..';
        return $base_path . ($this->get('epub_log_dir') ?: '/logs') . '/application.log';
    }
}
