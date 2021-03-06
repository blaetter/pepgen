<?php

namespace Pepgen\Tests\Helper;

use Pepgen\Tests\BaseTest;
use Pepgen\Helper\Config;

class ConfigTest extends BaseTest
{
    private $config;

    public function setUp(): void
    {
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
}
