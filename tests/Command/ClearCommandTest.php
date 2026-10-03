<?php

namespace Pepgen\Tests\Command;

use Pepgen\Tests\BaseTest;
use Pepgen\Command\ClearCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class ClearCommandTest extends BaseTest
{
    private $config;
    private $filesystem;
    private $temp_dir;
    private $public_dir;
    private $log_dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new \Pepgen\Helper\Config();
        $this->filesystem = new Filesystem();
        $old_timestamp = strtotime('8 days ago');
        $really_old_timestamp = strtotime('10 days ago');

        // set some temp files for the temp dir
        $this->temp_dir = $this->config->get('base_path') . $this->config->get('epub_temp_dir');
        $this->filesystem->touch($this->temp_dir . '/test_new.epub');
        $this->filesystem->touch($this->temp_dir . '/test_old.epub', $old_timestamp);
        $this->filesystem->touch($this->temp_dir . '/test_really_old.epub', $really_old_timestamp);

        // set some temp files for the public dir
        $this->public_dir = $this->config->get('base_path') . $this->config->get('epub_public_dir');
        $this->filesystem->touch($this->public_dir . '/test_new.epub');
        $this->filesystem->touch($this->public_dir . '/test_old.epub', $old_timestamp);

        // set some temp files for the log dir
        $this->log_dir = $this->config->get('base_path') . $this->config->get('epub_log_dir');
        $this->filesystem->touch($this->log_dir . '/test_new.log');
        $this->filesystem->touch($this->log_dir . '/test_old.log', $old_timestamp);
    }

    /**
     * Test the dry-run option - no files should be deleted.
     *
     * @return void
     */
    public function testExecuteDeleteTempDryRun()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'temp',
            '--dry-run'   => true,
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->temp_dir . '/test_new.epub') &&
            $this->filesystem->exists($this->temp_dir . '/test_old.epub') &&
            $this->filesystem->exists($this->temp_dir . '/test_really_old.epub')
        );
    }

    /**
     * Test if the target dir fallback to null works
     *
     * @return void
     */
    public function testExecuteDeleteEmptyTargetDir()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $result = $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'invalid_target_name',
        ));
        $this->assertEquals(1, $result);
    }

    /**
     * Test the --days option. As specified, only the file older then 12 days
     * which is none, so all files should stay.
     *
     * @return void
     */
    public function testExecuteDeleteTempDaysOlder()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'temp',
            '--days'      => 12,
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->temp_dir . '/test_new.epub') &&
            $this->filesystem->exists($this->temp_dir . '/test_old.epub') &&
            $this->filesystem->exists($this->temp_dir . '/test_really_old.epub')
        );
    }

    /**
     * Test the --days option. As specified, only the file older then 8 days
     * which is the test_really_old-epub should be deleted
     *
     * @return void
     */
    public function testExecuteDeleteTempDaysNewer()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'temp',
            '--days'      => 9,
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->temp_dir . '/test_new.epub') &&
            $this->filesystem->exists($this->temp_dir . '/test_old.epub') &&
            !$this->filesystem->exists($this->temp_dir . '/test_really_old.epub')
        );
    }

    /**
     * Test the deletion of the standard case temp dir. Old file should be deleted
     * new ones not.
     *
     * @return void
     */
    public function testExecuteDeleteTemp()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'temp',
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->temp_dir . '/test_new.epub') &&
            !$this->filesystem->exists($this->temp_dir . '/test_old.epub')
        );
    }

    /**
     * Test the deletion of the standard case temp dir. All files should be removed.
     *
     * @return void
     */
    public function testExecuteDeleteAllTemp()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'temp',
            '--all'     => true,
        ));
        $this->assertFalse($this->filesystem->exists($this->temp_dir . '/test_new.epub'));
    }

    /**
     * Test the deletion of the standard case public dir. Old file should be deleted
     * new ones not.
     *
     * @return void
     */
    public function testExecuteDeletePublic()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'public',
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->public_dir . '/test_new.epub') &&
            !$this->filesystem->exists($this->public_dir . '/test_old.epub')
        );
    }

    /**
     * Test the deletion of the standard case public dir. All files should be removed.
     *
     * @return void
     */
    public function testExecuteDeleteAllPublic()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'public',
            '--all'     => true,
        ));
        $this->assertFalse($this->filesystem->exists($this->public_dir . '/test_new.epub'));
    }

    /**
     * Test the deletion of the standard case logs dir. Old file should be deleted
     * new ones not.
     *
     * @return void
     */
    public function testExecuteDeleteLogs()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'logs',
        ));
        $this->assertTrue(
            $this->filesystem->exists($this->log_dir . '/test_new.log') &&
            !$this->filesystem->exists($this->log_dir . '/test_old.log')
        );
    }

    /**
     * Test the deletion of the standard case logs dir. All files should be removed.
     *
     * @return void
     */
    public function testExecuteDeleteAllLogs()
    {
        $application = new Application();
        $application->add(new ClearCommand());

        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            'target'    => 'logs',
            '--all'     => true,
        ));
        $this->assertFalse($this->filesystem->exists($this->log_dir . '/test_new.log'));
    }

    /**
     * The dry run deletes no files and is successful.
     */
    public function testDryRunExitCode()
    {
        $this->assertSame(0, $this->runCommand(['target' => 'temp', '--all' => true, '--dry-run' => true]));
        $this->assertFileExists($this->temp_dir . '/test_new.epub');
        $this->assertStringContainsString('test_new.epub', $this->output);
    }

    /**
     * The deletion is successful.
     */
    public function testExitCode()
    {
        $this->assertSame(0, $this->runCommand(['target' => 'temp']));
    }

    /**
     * Invalid values for --days are reported and no file is deleted.
     */
    public function testInvalidDays()
    {
        foreach (['0', '-3', 'abc', '5 days'] as $days) {
            $this->assertSame(1, $this->runCommand(['target' => 'temp', '--days' => $days]), $days);
            $this->assertStringContainsString('--days', $this->output);
        }
        $this->assertFileExists($this->temp_dir . '/test_really_old.epub');
    }

    /**
     * --days is ignored with --all, as described in the help.
     */
    public function testAllIgnoresDays()
    {
        $this->runCommand(['target' => 'temp', '--all' => true, '--days' => 9]);

        $this->assertFileDoesNotExist($this->temp_dir . '/test_new.epub');
        $this->assertFileDoesNotExist($this->temp_dir . '/test_really_old.epub');
    }

    /**
     * Temporary files of an interrupted zip within the public dir are removed, if they are older than a day.
     */
    public function testZipLeftoversAreRemovedFromPublicDir()
    {
        $two_days_ago = strtotime('2 days ago');
        $this->filesystem->touch($this->public_dir . '/zi0lqLBV', $two_days_ago);
        $this->filesystem->touch($this->public_dir . '/ziLVzDPL');
        $this->filesystem->touch($this->public_dir . '/zipped.txt', $two_days_ago);
        $this->filesystem->touch($this->temp_dir . '/ziTEMP01', $two_days_ago);

        // the dry run only lists the old leftover
        $this->assertSame(0, $this->runCommand(['target' => 'public', '--dry-run' => true]));
        $this->assertStringContainsString('zi0lqLBV', $this->output);
        $this->assertStringNotContainsString('ziLVzDPL', $this->output);
        $this->assertFileExists($this->public_dir . '/zi0lqLBV');

        // even with --all a leftover of a running zip (less than a day old) is kept
        $this->assertSame(0, $this->runCommand(['target' => 'public', '--all' => true]));
        $this->assertFileDoesNotExist($this->public_dir . '/zi0lqLBV');
        $this->assertFileExists($this->public_dir . '/ziLVzDPL');
        $this->assertFileExists($this->public_dir . '/zipped.txt');

        // only the public dir is affected
        $this->runCommand(['target' => 'temp', '--all' => true]);
        $this->assertFileExists($this->temp_dir . '/ziTEMP01');
    }

    /**
     * The output of the last command.
     *
     * @var string
     */
    private $output = '';

    /**
     * Runs the clear command with the given arguments and returns the exit code.
     */
    private function runCommand(array $arguments)
    {
        $application = new Application();
        $application->add(new ClearCommand());
        $command = $application->find('clear');
        $commandTester = new CommandTester($command);
        $result = $commandTester->execute(['command' => $command->getName()] + $arguments);
        $this->output = $commandTester->getDisplay();
        return $result;
    }

    /**
     * The test installation is a copy within the temp directory of the system.
     */
    public function testUsesTestInstallation()
    {
        $this->assertStringStartsWith($this->base_path, $this->temp_dir);
        $this->assertStringStartsWith($this->base_path, $this->public_dir);
        $this->assertStringStartsWith($this->base_path, $this->log_dir);
    }
}
