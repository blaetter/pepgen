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

    protected function tearDown(): void
    {
        // In case there was something wrong with the tests, remove the files by hand.
        $this->filesystem->remove($this->temp_dir . '/test_new.epub');
        $this->filesystem->remove($this->temp_dir . '/test_old.epub');
        $this->filesystem->remove($this->public_dir . '/test_new.epub');
        $this->filesystem->remove($this->public_dir . '/test_old.epub');
        $this->filesystem->remove($this->log_dir . '/test_new.log');
        $this->filesystem->remove($this->log_dir . '/test_old.log');
    }
}
