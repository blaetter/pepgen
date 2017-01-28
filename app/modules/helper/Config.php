<?php

namespace Pepgen\helper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Config
{
  /**
   * Handles config object
   *
   */
    private static function getConfig()
    {
        $configFinder = new Finder();

        $configFinder->files()->name('config.yml')->in(__DIR__ . '/../../../app/config/');

        $configs = array();

        foreach ($configFinder as $config) {
            $configs[] = $config->getContents();
        }

        return YAML::parse(implode('\r\n', $configs));
    }

    public static function get($key)
    {
        $config = self::getConfig();
        if (isset($config[$key])) {
            return $config[$key];
        }
        return false;
    }
}
