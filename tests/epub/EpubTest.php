<?php

namespace Pepgen\Tests\epub;

use Pepgen\Tests\BaseTest;

class EpubTest extends BaseTest
{
    protected function setUp()
    {
        if (!file_exists(dirname(__FILE__) . '/../../epub/test.epub')) {
            mkdir(dirname(__FILE__) . '/../../epub/test.epub');
        }
    }

    /**
     * @expectedException ErrorException
     */
    public function testEpubEmty()
    {
        $epub = new \Pepgen\epub\Epub('', '', '');
        $epub->run();
    }

    /**
     * @expectedException ErrorException
     */
    public function testEpub()
    {
        // create testing epub id
        $epub_id = 'test';
        $secret = \Pepgen\helper\Config::get('secret');
        $watermark = 'test';
        $token = \Pepgen\helper\Tokenizer::tokenize($epub_id, $secret, $watermark);
        $epub = new \Pepgen\epub\Epub($epub_id, $token, $watermark);
        $epub->run();
        $this->assertTrue($this->success);
    }

    protected function tearDown()
    {
        if (file_exists(dirname(__FILE__) . '/../../epub/test.epub')) {
            rmdir(dirname(__FILE__) . '/../../epub/test.epub');
        }
    }
}
