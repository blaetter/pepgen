<?php

namespace Pepgen\Tests;

use Pepgen\Helper\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Runs every test within its own directory with its own config.
 *
 * The tests create and delete epubs and log files, so they must never touch the directories of a real installation.
 */
abstract class BaseTest extends TestCase
{
    /**
     * The base path of the test installation.
     *
     * @var string
     */
    protected $base_path;

    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $this->base_path = sys_get_temp_dir() . '/pepgen-test-' . bin2hex(random_bytes(8));
        $filesystem->mkdir([
            $this->base_path . '/config',
            $this->base_path . '/epub',
            $this->base_path . '/tmp',
            $this->base_path . '/public/download',
            $this->base_path . '/logs',
        ]);

        // the sample config with the paths of the test installation
        $config = Yaml::parseFile(__DIR__ . '/../app/config/sample.config.yml');
        $config['base_path'] = $this->base_path;
        $config['secret'] = 'test-secret';
        $config['loglevel'] = 100;
        $filesystem->dumpFile($this->base_path . '/config/config.yml', Yaml::dump($config));
        putenv(Config::ENV_CONFIG_FILE . '=' . $this->base_path . '/config/config.yml');
    }

    protected function tearDown(): void
    {
        putenv(Config::ENV_CONFIG_FILE);
        (new Filesystem())->remove($this->base_path);
    }

    /**
     * Returns the path of a directory or file within the test installation.
     */
    protected function path($path)
    {
        return $this->base_path . '/' . $path;
    }
}
