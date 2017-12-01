<?php

namespace Pepgen\Tests\helper;

use Pepgen\Tests\BaseTest;
use Pepgen\helper\Config;

class ConfigTest extends BaseTest
{
    private $config;

    public function setUp()
    {
        $this->config = new Config();
    }
    public function testGetConfigPositive()
    {
        $base_path = $this->config->get('base_path');
        // base_path has a / in it.
        $this->assertContains('/', $base_path);
    }

    public function testGetConfigNegative()
    {
        $missing_config = $this->config->get('foo');
        $this->assertNotTrue($missing_config);
    }
}
