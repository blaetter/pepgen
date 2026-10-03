<?php

namespace Pepgen\Tests\Helper;

use Pepgen\Tests\BaseTest;
use Pepgen\Helper\Config;

class ConfigTest extends BaseTest
{
    private $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
    }

    public function testGetConfigPositive()
    {
        $base_path = $this->config->get('base_path');
        // base_path has a / in it.
        $this->assertStringContainsString('/', $base_path);
    }

    public function testGetConfigNegative()
    {
        $missing_config = $this->config->get('foo');
        $this->assertNotTrue($missing_config);
    }

    /**
     * The config file can be set via an environment variable, the tests use their own installation this way.
     */
    public function testConfigFileFromEnvironment()
    {
        $this->assertSame($this->base_path . '/config/config.yml', Config::getConfigFile());
        $this->assertSame($this->base_path, $this->config->get('base_path'));
        $this->assertSame('Europe/Berlin', $this->config->get('timezone'));
    }

    /**
     * Without an environment variable the config of the installation is used.
     */
    public function testDefaultConfigFile()
    {
        putenv(Config::ENV_CONFIG_FILE);

        $this->assertSame(
            realpath(__DIR__ . '/../../app/config') . '/config.yml',
            realpath(dirname(Config::getConfigFile())) . '/' . basename(Config::getConfigFile())
        );
    }

    /**
     * A missing config file leads to an empty config.
     */
    public function testMissingConfigFile()
    {
        putenv(Config::ENV_CONFIG_FILE . '=' . $this->base_path . '/config/missing.yml');

        $this->assertFalse((new Config())->get('base_path'));
    }
}
