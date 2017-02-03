<?php

namespace Pepgen\Tests;

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    // This is set to true if we set up a copy of the
    // sample config file for testing purposes.
    // This enables us to test within a testing environment
    // with a fully set up config file but also within an
    // automated testing build where we don't have a set up config.
    protected $test_config_file;

    protected function setUp()
    {
        $this->test_config_file = false;
        if (!file_exists(dirname(__FILE__) . '/../../app/config/config.yml')) {
            $this->test_config_file = true;
            copy(
                dirname(__FILE__) . '/../../app/config/sample.config.yml',
                dirname(__FILE__) . '/../../app/config/config.yml'
            );
        }
    }

    protected function tearDown()
    {
        // if there is a config file and if we set it up fot testing
        // remove it.
        if (file_exists(dirname(__FILE__) . '/../../app/config/config.yml') && true === $this->test_config_file) {
            unlink(dirname(__FILE__) . '/../../app/config/config.yml');
        }
    }
}
