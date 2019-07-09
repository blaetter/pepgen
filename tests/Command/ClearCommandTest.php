<?php

namespace Pepgen\Tests\Epub;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new \Pepgen\Helper\Config();
        $this->filesystem = new Filesystem();
        $old_timestamp = strtotime('8 days ago');
        $this->temp_dir = $this->config->get('base_path') . $this->config->get('epub_temp_dir');
        $this->filesystem->touch($this->temp_dir . '/test_new.epub');
        $this->filesystem->touch($this->temp_dir . '/test_old.epub', $old_timestamp);
        $this->public_dir = $this->config->get('base_path') . $this->config->get('epub_public_dir');
        $this->filesystem->touch($this->public_dir . '/test_new.epub');
        $this->filesystem->touch($this->public_dir . '/test_old.epub', $old_timestamp);
    }

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

    protected function tearDown(): void
    {
        // In case there was something wrong with the tests, remove the files by hand.
        $this->filesystem->remove($this->temp_dir . '/test_new.epub');
        $this->filesystem->remove($this->temp_dir . '/test_old.epub');
        $this->filesystem->remove($this->public_dir . '/test_new.epub');
        $this->filesystem->remove($this->public_dir . '/test_old.epub');

    }
}
