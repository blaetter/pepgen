<?php

namespace Pepgen\helper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Config
{

    private $config;

    public function __construct()
    {
        $this->config = $this->getConfig();
    }

    private function getConfig()
    {
        $configFinder = new Finder();

        $configFinder->files()->name('config.yml')->in(__DIR__ . '/../../../app/config/');

        $configs = array();

        foreach ($configFinder as $config) {
            $configs[] = $config->getContents();
        }

        return YAML::parse(implode('\r\n', $configs));
    }

    public function get($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        return false;
    }
}
