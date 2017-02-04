<?php

namespace Pepgen\Tests\epub;

use Pepgen\Tests\BaseTest;

class EpubTest extends BaseTest
{

    protected $epub_id;
    protected $secret;
    protected $token;
    protected $watermark;

    protected function setUp()
    {
        // create testing epub id
        $this->epub_id = 'test';
        $this->secret = \Pepgen\helper\Config::get('secret');
        $this->watermark = 'test';
        $this->token = \Pepgen\helper\Tokenizer::tokenize($this->epub_id, $this->secret, $this->watermark);
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

    public function testFastrunNegative()
    {
        $epub = new \Pepgen\epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->fastrun();
        $this->assertNotTrue($epub->success);
    }

    /**
     * @expectedException ErrorException
     */
    public function testEpub()
    {
        $epub = new \Pepgen\epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->run();
        $this->assertTrue($epub->success);
    }

    public function testFastrunPositive()
    {
        $epub = new \Pepgen\epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->fastrun();
        $this->assertTrue($epub->success);
    }

    protected function tearDown()
    {
        if (file_exists(dirname(__FILE__) . '/../../epub/test.epub')) {
            rmdir(dirname(__FILE__) . '/../../epub/test.epub');
        }
        if (file_exists(dirname(__FILE__) . '/../../tmp/' .  $this->token . '.test.epub')) {
            rmdir(dirname(__FILE__) . '/../../tmp/' .  $this->token . '.test.epub');
        }
        if (file_exists(dirname(__FILE__) . '/../../public/download/' .  $this->token . '.test.epub')) {
            rmdir(dirname(__FILE__) . '/../../public/download/' .  $this->token . '.test.epub');
        }
    }
}
