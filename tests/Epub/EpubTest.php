<?php

namespace Pepgen\Tests\Epub;

use Pepgen\Tests\BaseTest;
use Symfony\Component\Filesystem\Filesystem;

class EpubTest extends BaseTest
{
    protected $config;
    protected $epub_id;
    protected $secret;
    protected $token;
    protected $watermark;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new \Pepgen\Helper\Config();
        // create testing epub id
        $this->epub_id = 'test';
        $this->secret = $this->config->get('secret');
        $this->watermark = 'test';
        $this->token = \Pepgen\Helper\Tokenizer::tokenize($this->epub_id, $this->secret, $this->watermark);
        if (!file_exists(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub')) {
            mkdir(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub');
            file_put_contents(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub/file_1.xhtml', 'test');
            file_put_contents(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub/file_2.xhtml', 'test');
            file_put_contents(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub/mimetype', 'test');
        }
    }

    /**
     *
     */
    public function testCopyNegative()
    {
        $epub = new \Pepgen\Epub\Epub('', '', '');
        $this->expectException(\ErrorException::class);
        $epub->copy();
    }

    /**
     *
     */
    public function testEpubEmty()
    {
        $epub = new \Pepgen\Epub\Epub('', '', '');
        $this->expectException(\ErrorException::class);
        $epub->run();
    }

    public function testFastrunNegative()
    {
        $epub = new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->fastrun();
        $this->assertNotTrue($epub->success);
    }

    public function testFastrunPositive()
    {
        $epub = new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->verify();
        $epub->copy();
        $epub->modify();
        $epub->process();
        $epub->fastrun();
        $this->assertTrue($epub->success);
    }

    public function testEpubNegative()
    {
        $epub = new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->run();
        $this->assertTrue($epub->success);
    }

    /**
     *
     */
    public function testProcessNegatve()
    {
        $epub = new \Pepgen\Epub\Epub('', '', '');
        $this->expectException(\ErrorException::class);
        $epub->process();
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        if (file_exists(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub')) {
            $filesystem->remove(dirname(__FILE__) . '/../../epub/' . $this->epub_id . '.epub');
        }
        if (file_exists(dirname(__FILE__) . '/../../tmp/' .  $this->token . '.' . $this->epub_id . '.epub')) {
            $filesystem->remove(dirname(__FILE__) . '/../../tmp/' .  $this->token . '.' . $this->epub_id . '.epub');
        }
        if (file_exists(dirname(__FILE__) . '/../../public/download/' .  $this->token . '.' . $this->epub_id . '.epub')) {
            $filesystem->remove(dirname(__FILE__) . '/../../public/download/' .  $this->token . '.' . $this->epub_id . '.epub');
        }
    }
}
