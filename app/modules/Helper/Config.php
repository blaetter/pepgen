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
}
