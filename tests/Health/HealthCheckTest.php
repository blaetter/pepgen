<?php

namespace Pepgen\Tests\Health;

use Pepgen\Health\HealthCheck;
use Pepgen\Helper\Config;
use Pepgen\Tests\BaseTest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Yaml\Yaml;

class HealthCheckTest extends BaseTest
{
    /**
     * The error log before the test.
     *
     * @var string|false
     */
    private $error_log;

    protected function setUp(): void
    {
        parent::setUp();
        // one unzipped original ePub
        (new Filesystem())->mkdir($this->path('epub/test'));
        $this->error_log = ini_set('error_log', $this->path('logs/php-error.log'));
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->error_log ?: '');
        parent::tearDown();
    }

    /**
     * A finder that finds zip at the given path (null: not found).
     */
    private function finder(?string $zip)
    {
        return new class ($zip) extends ExecutableFinder {
            public array $extraDirs = [];

            public function __construct(private ?string $zip)
            {
            }

            public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
            {
                $this->extraDirs = $extraDirs;
                return $this->zip;
            }
        };
    }

    /**
     * Permissions do not stop root.
     */
    private function skipAsRoot()
    {
        if (function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            $this->markTestSkipped('Runs as root, file permissions do not apply.');
        }
    }

    private function check(?string $zip = '/bin/sh')
    {
        return new HealthCheck(new Config(), $this->finder($zip));
    }

    public function testAllChecksOk()
    {
        $result = $this->check()->run();
        $this->assertSame('ok', $result['status']);
        $this->assertSame(
            ['config' => 'ok', 'epubs' => 'ok', 'storage' => 'ok', 'logs' => 'ok', 'zip' => 'ok'],
            $result['checks']
        );
    }

    public function testNothingIsWritten()
    {
        $this->check()->run();
        $this->assertSame([], array_values(array_diff(scandir($this->path('tmp')), ['.', '..'])));
        $this->assertSame([], array_values(array_diff(scandir($this->path('public/download')), ['.', '..'])));
    }

    public function testMissingConfigValue()
    {
        $config = Yaml::parseFile($this->path('config/config.yml'));
        unset($config['secret']);
        (new Filesystem())->dumpFile($this->path('config/config.yml'), Yaml::dump($config));
        $check = $this->check();
        $result = $check->run();
        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['checks']['config']);
        // the reason names the key, not its value
        $this->assertSame(['config: missing secret'], $check->getReasons());
    }

    public function testNoOriginalEpub()
    {
        (new Filesystem())->remove($this->path('epub/test'));
        $result = $this->check()->run();
        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['checks']['epubs']);
        $this->assertSame('ok', $result['checks']['storage']);
    }

    public function testTempDirectoryMissing()
    {
        (new Filesystem())->remove($this->path('tmp'));
        $result = $this->check()->run();
        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['checks']['storage']);
    }

    public function testDownloadDirectoryNotWritable()
    {
        $this->skipAsRoot();
        chmod($this->path('public/download'), 0555);
        try {
            $result = $this->check()->run();
        } finally {
            chmod($this->path('public/download'), 0755);
        }
        $this->assertSame('error', $result['checks']['storage']);
    }

    public function testLogFileOfTodayNotWritable()
    {
        $this->skipAsRoot();
        $file = $this->path('logs/application-' . date('Y-m-d') . '.log');
        file_put_contents($file, '');
        chmod($file, 0444);
        try {
            $check = $this->check();
            $result = $check->run();
        } finally {
            chmod($file, 0644);
        }
        $this->assertSame('error', $result['checks']['logs']);
        $this->assertContains('logs: log file of today not writable', $check->getReasons());
    }

    public function testZipIsSearchedInTheStandardDirectories()
    {
        // PHP-FPM clears PATH by default
        $finder = $this->finder('/bin/sh');
        (new HealthCheck(new Config(), $finder))->run();
        $this->assertContains('/usr/bin', $finder->extraDirs);
    }

    public function testZipMissing()
    {
        $result = $this->check(null)->run();
        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $result['checks']['zip']);
    }

    public function testResultContainsNoPathsOrValues()
    {
        (new Filesystem())->remove($this->path('tmp'));
        $result = json_encode($this->check()->run());
        $this->assertStringNotContainsString($this->base_path, $result);
        $this->assertStringNotContainsString('test-secret', $result);
    }

    public function testReasonsGoToTheLog()
    {
        (new Filesystem())->remove($this->path('tmp'));
        $this->check()->run();
        $this->assertStringContainsString(
            'pepgen health: storage: temp directory not writable',
            file_get_contents($this->path('logs/php-error.log'))
        );
    }

    public static function healthRequests()
    {
        return [
            'root' => ['/health', '/index.php', 'GET', true],
            'head' => ['/health', '/index.php', 'HEAD', true],
            'query string' => ['/health?x=1', '/index.php', 'GET', true],
            'subdirectory' => ['/pepgen/health', '/pepgen/index.php', 'GET', true],
            'post' => ['/health', '/index.php', 'POST', false],
            'other path' => ['/', '/index.php', 'GET', false],
            'longer path' => ['/health/x', '/index.php', 'GET', false],
            'wrong subdirectory' => ['/health', '/pepgen/index.php', 'GET', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('healthRequests')]
    public function testIsHealthRequest($uri, $script_name, $method, $expected)
    {
        $this->assertSame($expected, HealthCheck::isHealthRequest($uri, $script_name, $method));
    }
}
