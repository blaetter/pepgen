<?php

namespace Pepgen\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Pepgen\Helper\Config;

class ClearCommand extends Command
{
    public $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('clear')

            // the short description shown while running "php bin/console list"
            ->setDescription('Removes epub temp or public files.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command clears either the temp or the public epub folder.\nPer default it does not delete files that are less than 7 days old, but you might force deleting all files with --all.\nThis is mainly because we want to keep public ebooks for a litte bit to give users the chance to download it.\nThe temp folder should be empty as the temp files are deleted after successfully creating a personalized epub.")

            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Which files should be deleted (temp|public)?'
            )

            // add an option to
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Should all files be deleted or only older ones?',
                null
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filesystem = new Filesystem();
        $finder = new Finder();
        if ('temp' == $input->getArgument('target')) {
            $target_dir = $this->config->get('base_path') . $this->config->get('epub_temp_dir');
        } elseif ('public' == $input->getArgument('target')) {
            $target_dir = $this->config->get('base_path') . $this->config->get('epub_public_dir');
        }
        if ($input->getOption('all')) {
            $finder->files()->name('*.epub');
        } else {
            $finder->files()->name('*.epub')->date('< 7 days ago');
        }
        if ($filesystem->exists($target_dir)) {
            try {
                $filesystem->remove($finder->in($target_dir));
            } catch (IOException $e) {
                $output->writeln(
                    'Something went wrong: ' . $e->getMessage(),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }
    }
}
