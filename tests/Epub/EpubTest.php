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
        $this->token = $this->tokenize($this->epub_id, $this->watermark);
        // the original epub
        mkdir($this->path('epub/' . $this->epub_id . '.epub'));
        file_put_contents($this->path('epub/' . $this->epub_id . '.epub/file_1.xhtml'), '<p><!--WATERMARK--></p>');
        file_put_contents($this->path('epub/' . $this->epub_id . '.epub/file_2.xhtml'), 'test');
        file_put_contents($this->path('epub/' . $this->epub_id . '.epub/mimetype'), 'application/epub+zip');
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

    /**
     * Builds the token like the website or shop does.
     */
    private function tokenize($epub_id, $watermark)
    {
        return \Pepgen\Helper\Tokenizer::tokenize(
            $epub_id,
            $this->secret,
            $watermark,
            $this->config->get('timezone')
        );
    }

    /**
     * A previously generated epub is delivered without generating it again.
     */
    public function testRunSkipsGenerationOfExistingEpub()
    {
        (new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark))->run();
        $temp_path = $this->path('tmp/' . $this->token . '.' . $this->epub_id . '.epub');
        $public_path = $this->path('public/download/' . $this->token . '.' . $this->epub_id . '.epub');
        $this->assertFileExists($public_path);
        (new Filesystem())->remove($temp_path);
        $modified = filemtime($public_path);

        $epub = new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->run();

        $this->assertTrue($epub->success);
        $this->assertSame('Success', $epub->message);
        $this->assertDirectoryDoesNotExist($temp_path, 'The epub must not be generated again.');
        clearstatcache();
        $this->assertSame($modified, filemtime($public_path));
    }

    /**
     * The generated epub starts with the uncompressed mimetype and contains all files of the original.
     */
    public function testRunCreatesValidEpub()
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not available.');
        }
        (new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark))->run();

        $zip = new \ZipArchive();
        $this->assertTrue(
            $zip->open($this->path('public/download/' . $this->token . '.' . $this->epub_id . '.epub'))
        );
        $first = $zip->statIndex(0);
        $this->assertSame('mimetype', $first['name']);
        $this->assertSame(\ZipArchive::CM_STORE, $first['comp_method']);
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        sort($names);
        $this->assertSame(['file_1.xhtml', 'file_2.xhtml', 'mimetype'], $names);
        $this->assertSame(
            '<p>' . sprintf($this->config->get('template'), $this->watermark) . '</p>',
            $zip->getFromName('file_1.xhtml')
        );
        $zip->close();

        // the temporary copy is removed after the epub was created
        $this->assertDirectoryDoesNotExist($this->path('tmp/' . $this->token . '.' . $this->epub_id . '.epub'));
    }

    /**
     * Ids that could be used for paths outside the epub directory or for shell commands are denied, even with a
     * valid token.
     */
    public function testInvalidEpubIdIsDenied()
    {
        foreach (['../epub/test', 'test;touch pepgen_injected', 'test$(id)', 'te st', 'test.epub'] as $epub_id) {
            $epub = new \Pepgen\Epub\Epub($epub_id, $this->tokenize($epub_id, $this->watermark), $this->watermark);
            try {
                $epub->run();
                $this->fail('The epub id ' . $epub_id . ' should be denied.');
            } catch (\ErrorException $exception) {
                $this->assertSame('Something went wrong: Not enough arguments or wrong arguments.', $epub->message);
            }
        }
        $this->assertFileDoesNotExist($this->path('tmp/pepgen_injected'));
        $this->assertFileDoesNotExist(getcwd() . '/pepgen_injected');
    }

    /**
     * The message for the client does not contain any paths, they are only logged.
     */
    public function testDenyMessageContainsNoPath()
    {
        $epub = new \Pepgen\Epub\Epub('missing', $this->tokenize('missing', $this->watermark), $this->watermark);
        try {
            $epub->run();
            $this->fail('A missing epub should be denied.');
        } catch (\ErrorException $exception) {
            $this->assertSame('Something went wrong: Requested ePub does not exist.', $epub->message);
            $this->assertStringNotContainsString('/', $exception->getMessage());
        }
        // the details are only logged, separated by a colon
        $this->assertStringContainsString(
            'Denied: Requested ePub does not exist: ' . $this->path('epub/missing.epub'),
            file_get_contents($this->path('logs/application-' . date('Y-m-d') . '.log'))
        );
    }

    /**
     * The watermark is inserted literally, "$1" or "\1" are no back references.
     */
    public function testWatermarkIsInsertedLiterally()
    {
        $watermark = 'Erika $1 \\1 ${0} Muster';
        $token = $this->tokenize($this->epub_id, $watermark);

        $epub = new \Pepgen\Epub\Epub($this->epub_id, $token, $watermark);
        $epub->verify();
        $epub->copy();
        $epub->modify();

        $this->assertSame(
            '<p>' . sprintf($this->config->get('template'), $watermark) . '</p>',
            file_get_contents($this->path('tmp/' . $token . '.' . $this->epub_id . '.epub/file_1.xhtml'))
        );
    }

    /**
     * The watermark contains personal data of the customer, so it must not be logged.
     */
    public function testWatermarkIsNotLogged()
    {
        $watermark = 'Erika Muster, E-Mail: ' . uniqid('pepgen-test-') . '@example.com';
        $token = $this->tokenize($this->epub_id, $watermark);
        $log_file = $this->path('logs/application-' . date('Y-m-d') . '.log');

        try {
            (new \Pepgen\Epub\Epub($this->epub_id, $token, $watermark))->run();
            (new \Pepgen\Epub\Epub($this->epub_id, 'wrong-token', $watermark))->run();
        } catch (\ErrorException $exception) {
            // the second request is denied
        }

        $this->assertFileExists($log_file);
        $this->assertStringNotContainsString($watermark, file_get_contents($log_file));
        $this->assertStringNotContainsString('token_check', file_get_contents($log_file));
    }

    /**
     * Creates an epub, whose second zip command is replaced by the given command.
     */
    private function createEpubWithFailingZip(array $command)
    {
        return new class ($this->epub_id, $this->token, $this->watermark, $command) extends \Pepgen\Epub\Epub {
            private $call = 0;

            public function __construct($epub_id, $token, $watermark, private array $failing_command)
            {
                parent::__construct($epub_id, $token, $watermark);
            }

            protected function createProcess(array $command, $working_directory)
            {
                $this->call++;
                if (2 === $this->call) {
                    return new \Symfony\Component\Process\Process($this->failing_command, $working_directory, null, null, 0.5);
                }
                return parent::createProcess($command, $working_directory);
            }
        };
    }

    /**
     * If zipping fails or times out, no incomplete epub is left, so the next request does not deliver it.
     */
    public function testFailedZipLeavesNoIncompleteEpub()
    {
        $public_path = $this->path('public/download/' . $this->token . '.' . $this->epub_id . '.epub');
        $temp_path = $this->path('tmp/' . $this->token . '.' . $this->epub_id . '.epub');

        foreach (['error' => ['false'], 'timeout' => ['sleep', '5']] as $case => $command) {
            $epub = $this->createEpubWithFailingZip($command);
            $started = microtime(true);
            try {
                $epub->run();
                $this->fail('The ' . $case . ' should be reported.');
            } catch (\ErrorException $exception) {
                $this->assertSame(
                    'Something went wrong: Zipping went wrong. Could not create personalised ePub.',
                    $epub->message,
                    $case
                );
            }
            $this->assertLessThan(4, microtime(true) - $started, $case);
            $this->assertFileDoesNotExist($public_path, $case);
            $this->assertDirectoryDoesNotExist($temp_path, $case);
        }

        // the next request creates a complete epub
        $epub = new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark);
        $epub->run();
        $this->assertTrue($epub->success);
        $this->assertFileExists($public_path);
    }

    /**
     * zip does not leave temporary files in the public directory.
     */
    public function testNoTemporaryFilesInPublicDir()
    {
        (new \Pepgen\Epub\Epub($this->epub_id, $this->token, $this->watermark))->run();

        $this->assertSame(
            [$this->token . '.' . $this->epub_id . '.epub'],
            array_values(array_diff(scandir($this->path('public/download')), ['.', '..']))
        );
    }
}
