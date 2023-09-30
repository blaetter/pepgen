<?php
/**
 * Class Epub
 *
 * handles epub stuff
 */

namespace Pepgen\Epub;

// vendor libraries to use
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Pepgen\Helper\Config;
use Pepgen\Helper\Tokenizer;

class Epub
{
    public $success;

    public $message;

    private $config;

    private $watermark;

    private $epub_id;

    private $token;

    private $token_check;

    private $epub;

    private $epub_personal;

    private $textpattern;

    private $template;

    private $files_to_replace;

    private $finder;

    private $filesystem;

    private $logger;

    private $debugging_info;

    public function __construct($epub_id, $token, $watermark)
    {
        // success is false by default, switches to true on success
        $this->success = false;

        $this->config = new Config();

        // the textpattern that is searched for in the epubs
        $this->textpattern = $this->config->get('textpattern');

        // the template the textpattern is replaced with
        $this->template = $this->config->get('template');

        // the regexp for the files we have to replace text in
        $this->files_to_replace = $this->config->get('files_to_replace');

        // the id of the requested ePub
        $this->epub_id = $epub_id;

        // the token given by the request
        $this->token = $token;

        // the watermark that will be printed right into the epub
        $this->watermark = $watermark;

        // the name of the epub - the name should be the node-id with an .epub suffix
        $this->epub = $this->epub_id . '.epub';

        // the name of the personal epub - the name should be the ordinary epub name plus the token
        $this->epub_personal = $this->token . '.' . $this->epub;

        // prepare the token check to make sure request is coming from a trusted site
        $this->token_check = Tokenizer::tokenize($this->epub_id, $this->config->get('secret'), $this->watermark);

        // create a debugging information array
        $this->debugging_info = [
            'epub_id' => $this->epub_id,
            'token' => $this->token,
            'token_check' => $this->token_check,
            'watermark' => $this->watermark,
            'files' => $this->files_to_replace,
            'pattern' => $this->textpattern
        ];

        // creating instance of filesystem
        $this->filesystem = new Filesystem();

        // get logging instance
        $this->logger = new Logger('pepgen');
        // Now add some handlers
        $this->logger->pushHandler(
            new RotatingFileHandler(
                __DIR__.'/../../../logs/application.log',
                $this->config->get('keepfiles'),
                $this->config->get('loglevel')
            )
        );
    }

    /**
     * Main Application Function
     *
     */
    public function run()
    {
        // verify the request
        $this->verify();

        // check if epub is already generated and deliver it the short way
        $this->fastrun();

        // look for and copy the epub blueprint
        $this->copy();

        // modify the epub
        $this->modify();

        // process the epub - the real generation
        $this->process();

        // everything is fine
        $this->success();
    }

    /**
     * This function returns plain text information back to the requestor
     *
     */
    public function success()
    {
        // set success to true cause everything is fine
        $this->success = true;
        // log success into info stream
        $this->logger->info(
            'Success: epub ready for download.',
            $this->debugging_info
        );
        $this->message = 'Success';
        return true;
    }

    /**
     * This means there's noting to do, so deny the request
     *
     * @param $msg - the messages that is displayed
     */
    public function deny($msg = '')
    {
        // log bad request
        $this->logger->info('Denied: ' . $msg, $this->debugging_info);
        // send error message to end user
        $this->message = 'Something went wrong: ' . $msg;
        // because of previous errors, we need to end the run() here
        throw new \ErrorException($this->message);
    }

    /**
     * This function provides the download of the given file via a crypted url - if already there
     *
     */
    public function fastrun()
    {
        // check for needed files
        if ($this->filesystem->exists($this->getPublicEpubPath())) {
            $this->logger->info(
                'Fastrun: Found previously generated file.',
                $this->debugging_info
            );
            $this->success();
        }
    }

    /**
     * This function verifies the request
     *
     */
    public function verify()
    {
        $this->logger->debug(
            'Verify: ',
            $this->debugging_info
        );
        // If no information is provided or the information is invalid, cancel request at this point.
        if (empty($this->watermark) ||
            empty($this->epub_id) ||
            empty($this->token) ||
            $this->token !== $this->token_check
        ) {
            $this->deny('Not enough arguments or wrong arguments.');
        }
    }

    /**
     * This function handles the file system operations.
     * First it looks for the requested epub mirrors it to the tmp directory
     *
     */
    public function copy()
    {
        // check for needed files
        // if its not there the requests is per se not allowed
        if (!$this->filesystem->exists(
            $this->getOriginalEpubPath()
        )) {
            $this->deny(
                'Requested ePub does not exist: ' .
                $this->getOriginalEpubPath()
            );
        }

        // copy the original file to the target directory and rename it
        try {
            $this->filesystem->mirror(
                $this->getOriginalEpubPath(),
                $this->getTempEpubPath(),
                null,
                array('override' => true)
            );
        } catch (IOException $e) {
            $this->deny('Could not copy ePub: '.$e);
        }
    }

    /**
     * This function modifies the given epub and puts the watermark in it.
     *
     */
    public function modify()
    {
        // now we need to try finding the requested files for modifications
        $this->finder = new Finder();
        $this->finder->files()->name($this->files_to_replace)->in(
            $this->getTempEpubPath()
        );

        // log into debug stream
        $this->logger->debug('Modify: Checking for files.', $this->debugging_info);

        foreach ($this->finder as $file) {
            // log into debug stream
            $this->logger->debug('Modify: Found file: ' . $file, $this->debugging_info);

            // get the files content
            $original_content = $file->getContents();

            // replace the given textpattern with the personal user watermark
            $modified_content = preg_replace(
                $this->textpattern,
                sprintf($this->template, $this->watermark),
                $original_content
            );

            // if replace was successfull we can dump the content back into the file
            if (null !== $modified_content && $original_content !== $modified_content) {
                try {
                    $this->filesystem->dumpFile($file->getRealpath(), $modified_content);
                } catch (IOException $exception) {
                    $this->deny('Could not write watermark to file.');
                }
            }
        }
    }

    /**
     * This function processes the epub and puts it in the download directory
     *
     */
    public function process()
    {
        // lets start the process that handles the zipping
        $process = Process::fromShellCommandline(
            'cd '.
            $this->getTempEpubPath() .
            ' && zip -0Xq ' .
            $this->getPublicEpubPath() .
            ' mimetype && zip -Xr9Dq ' .
            $this->getPublicEpubPath() . ' *'
        );

        $process->run();

        if (!$process->isSuccessful() ||
            !$this->filesystem->exists(
                $this->config->get('base_path') . $this->config->get('epub_public_dir') . '/' . $this->epub_personal
            )
        ) {
            $this->deny('Zipping went wrong. Could not create personalised ePub: ' . $process->getErrorOutput());
        }
    }

    private function getOriginalEpubPath()
    {
        return $this->config->get('base_path') . $this->config->get('epub_original_dir') . '/' . $this->epub;
    }

    private function getPublicEpubPath()
    {
        return $this->config->get('base_path')  . $this->config->get('epub_public_dir') . '/' . $this->epub_personal;
    }

    private function getTempEpubPath()
    {
        return $this->config->get('base_path') . $this->config->get('epub_temp_dir') . '/' . $this->epub_personal;
    }
}
