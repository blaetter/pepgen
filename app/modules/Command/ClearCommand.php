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
    /**
     * The file suffix or extension to look for in the file system
     *
     * @var string
     */
    private $file_identifier;

    /**
     * The representation of the symfony file system object
     *
     * @var Symfony\Component\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * The representation of the symfony finder object
     *
     * @var Symfony\Component\Finder\Finder
     */
    private $finder;

    /**
     * The input interface object
     *
     * @var Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * The output interface object
     *
     * @var Symfony\Component\Console\Output\OutInterface
     */
    private $output;

    /**
     * The target dir to look for
     *
     * @var string
     */
    private $target_dir;

    /**
     * The config object
     *
     * @var Pepgen\Helper\Config
     */
    public $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
        $this->filesystem = new Filesystem();
        $this->finder = new Finder();
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
            ->setHelp(
                "This command clears either the temp, public epub or log folder." . PHP_EOL .
                "Per default it does not delete files that are less than 7 days old, but you might force deleting " .
                "all files with --all or specify a number of days that needs to be kept with --days = 5." . PHP_EOL .
                "This is mainly because we want to keep public ebooks for a litte bit to give users the chance " .
                "to download it." . PHP_EOL .
                "The temp folder should be empty as the temp files are deleted after successfully creating" .
                "a personalized epub."
            )

            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Which files should be deleted (temp|public|logs)?'
            )

            // add an option to delete all
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Should all files be deleted or only older ones?',
                null
            )
            // add an option to set the day parameter
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Override the number of days where files will kept. (Usually 7 days). ' .
                'Option is ignored if --all is used. Option should be greater than 0.'
            )
            // add an option for dry run
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry run, so no actual file is deleted. Use this to check if you have old files.'
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // set input class member
        $this->input = $input;
        // set output class member
        $this->output = $output;
        // get the target dir based on given target
        $this->setTargetDir($this->input->getArgument('target'));

        // check if target dir is set properly, return otherwise
        if (true !== $this->isRequestValid()) {
            return 1;
        }

        // apply file identifier to finder
        $this->finder->files()->name($this->file_identifier);

        // set the finder date parameter if needed
        $this->setFinderDate();

        // set the finder to the actually wanted files
        $this->finder->in($this->target_dir);

        // check for dry-run
        if ($input->getOption('dry-run')) {
            return $this->executeDryRun();
        }

        // delete the files, if no dry-run is specified.
        try {
            $this->filesystem->remove($this->finder);
        } catch (IOException $exception) {
            $this->output->writeln(
                'Something went wrong: ' . $exception->getMessage(),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
        return 0;
    }

    protected function setTargetDir($target)
    {
        // set default file identifier to epub file extension.
        $this->file_identifier = '*.epub';
        $this->target_dir = '';
        // set the directory depending on the given target
        if ('temp' == $target) {
            $this->target_dir = $this->config->get('base_path') . $this->config->get('epub_temp_dir');
        } elseif ('public' == $target) {
            $this->target_dir = $this->config->get('base_path') . $this->config->get('epub_public_dir');
        } elseif ('logs' == $target) {
            $this->file_identifier = '*.log';
            $this->target_dir = $this->config->get('base_path') . $this->config->get('epub_log_dir');
        }
    }

    private function executeDryRun()
    {
        // in dry-run, only display the files
        $this->output->writeln('dry-run, printing files that matches given criteria');
        foreach ($this->finder as $file) {
            $this->output->writeln($file);
        }
        return 1;
    }

    private function setFinderDate()
    {
        // check if we need to limit the files beeing selected, this should either be with the --all flag or via --days
        if ($this->input->getOption('days') && 0 < $this->input->getOption('days')) {
            // in this case we need to set the number of days where files will be kept accoring to the given number
            $this->finder->date('< ' . $this->input->getOption('days') . ' days ago');
        } elseif (false === $this->input->getOption('all')) {
            // in this case we use a standard of 7 days as long as --all is not set.
            $this->finder->date('< 7 days ago');
        }
    }

    private function isRequestValid()
    {
        if (null === $this->target_dir || !$this->filesystem->exists($this->target_dir)) {
            $this->output->writeln(
                'No valid argument provided or target dir not existing'
            );
            return false;
        }
        return true;
    }
}
