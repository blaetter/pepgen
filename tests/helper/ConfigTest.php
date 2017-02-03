<?php

namespace Pepgen\Tests\helper;

use Pepgen\Tests\BaseTest;

class ConfigTest extends BaseTest
{
    public function testGetConfig()
    {
        $base_path = \Pepgen\helper\Config::get('base_path');
        // base_path has a / in it.
        $this->assertContains('/', $base_path);
    }
}
