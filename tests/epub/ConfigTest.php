<?php
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{

    public function testGetConfig()
    {
        $base_path = \Pepgen\helper\Config::get('base_path');
        //$this->assertEmpty($base_path);
    }
}
