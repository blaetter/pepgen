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
use Pepgen\Helper\Config;
use Pepgen\Helper\Tokenizer;

class Epub
{
    /**
     * The allowed characters of an epub id. As the id is part of file paths, it must not contain dots or slashes.
     */
    public const EPUB_ID_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * The log level, if none is configured (INFO, @see RFC 5424).
     */
    public const DEFAULT_LOGLEVEL = 200;

    /**
     * The number of log files kept, if none is configured.
     */
    public const DEFAULT_KEEPFILES = 5;

    /**
     * The maximum number of seconds a zip command may run.
     */
    public const ZIP_TIMEOUT = 120;

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
        $this->token_check = Tokenizer::tokenize(
            $this->epub_id,
            $this->config->get('secret'),
            $this->watermark,
            $this->config->get('timezone')
        );

        // create a debugging information array
        // The watermark contains personal data of the customer and the valid token must not be logged, so both are
        // left out. The requested token is enough to find the generated file.
        $this->debugging_info = [
            'epub_id' => $this->epub_id,
            'token' => $this->token,
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
                $this->getLogPath(),
                (int) ($this->config->get('keepfiles') ?: self::DEFAULT_KEEPFILES),
                (int) ($this->config->get('loglevel') ?: self::DEFAULT_LOGLEVEL)
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
        if ($this->fastrun()) {
            return;
        }

        // look for and copy the epub blueprint
        $this->copy();

        // modify the epub
        $this->modify();

        // process the epub - the real generation
        $this->process();

        // the temporary copy is not needed anymore
        $this->cleanup();

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
     * @param $details - further details, e.g. paths or exception messages, that are only logged
     */
    public function deny($msg = '', $details = '')
    {
        // log bad request, the details follow the message after a colon, e.g. "Could not copy ePub: <reason>"
        $details = trim((string) $details);
        $this->logger->info(
            'Denied: ' . ('' !== $details ? rtrim($msg, '.') . ': ' . $details : $msg),
            $this->debugging_info
        );
        // send error message to end user
        $this->message = 'Something went wrong: ' . $msg;
        // because of previous errors, we need to end the run() here
        throw new \ErrorException($this->message);
    }

    /**
     * This function provides the download of the given file via a crypted url - if already there
     *
     * @return bool true, if the epub was already generated, so there is nothing left to do
     */
    public function fastrun()
    {
        // check for needed files
        if ($this->filesystem->exists($this->getPublicEpubPath())) {
            $this->logger->info(
                'Fastrun: Found previously generated file.',
                $this->debugging_info
            );
            return $this->success();
        }
        return false;
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
            // the id is part of file paths and the zip command, so only allow safe characters
            !preg_match(self::EPUB_ID_PATTERN, (string) $this->epub_id) ||
            !is_string($this->token) ||
            // compare in constant time, so the response time does not reveal parts of the valid token
            !hash_equals($this->token_check, $this->token)
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
                'Requested ePub does not exist.',
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
            $this->deny('Could not copy ePub.', $e->getMessage());
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
            // A callback is used, so "$1" or "\\1" within the watermark are not handled as back references.
            $watermark = sprintf($this->template, $this->watermark);
            $modified_content = preg_replace_callback(
                $this->textpattern,
                fn () => $watermark,
                $original_content
            );

            // if replace was successfull we can dump the content back into the file
            if (null !== $modified_content && $original_content !== $modified_content) {
                try {
                    $this->filesystem->dumpFile($file->getRealpath(), $modified_content);
                } catch (IOException $exception) {
                    $this->deny('Could not write watermark to file.', $exception->getMessage());
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
        $temp_path = $this->getTempEpubPath();
        if (!is_dir($temp_path)) {
            $this->deny('Zipping went wrong. Could not create personalised ePub.', 'Missing ' . $temp_path);
        }

        // The mimetype has to be the first file and uncompressed, afterwards all other (not hidden) files are added.
        // The commands are run without a shell, so no part of the paths is interpreted by a shell.
        // zip writes into a temporary file before renaming it to the epub. With -b this temporary file is created
        // within the temp directory of the request instead of the public directory, so an interrupted zip does not
        // leave any file in the public directory.
        $files = array_values(array_filter(
            scandir($temp_path),
            fn ($file) => '.' !== $file[0]
        ));
        $commands = [
            ['zip', '-0Xq', '-b', $temp_path, $this->getPublicEpubPath(), 'mimetype'],
            array_merge(['zip', '-Xr9Dq', '-b', $temp_path, $this->getPublicEpubPath()], $files),
        ];

        foreach ($commands as $command) {
            $error = null;
            try {
                $process = $this->createProcess($command, $temp_path);
                $process->run();
                if (!$process->isSuccessful()) {
                    $error = $process->getErrorOutput();
                }
            } catch (\RuntimeException $exception) {
                // e.g. the timeout was exceeded
                $error = $exception->getMessage();
            }

            if (null !== $error) {
                // Remove the incomplete epub, otherwise it would be delivered by the next fastrun.
                $this->filesystem->remove($this->getPublicEpubPath());
                $this->cleanup();
                $this->deny('Zipping went wrong. Could not create personalised ePub.', $error);
            }
        }

        if (!$this->filesystem->exists($this->getPublicEpubPath())) {
            $this->deny('Zipping went wrong. Could not create personalised ePub.', 'Missing ' . $this->getPublicEpubPath());
        }
    }

    /**
     * Creates the process for the given zip command.
     *
     * @param array $command The command and its arguments
     * @param string $working_directory The working directory of the command
     * @return Process
     */
    protected function createProcess(array $command, $working_directory)
    {
        return new Process($command, $working_directory, null, null, self::ZIP_TIMEOUT);
    }

    /**
     * Removes the temporary copy of the epub after the personalised epub was created.
     *
     * A failure is only logged, as the personalised epub is ready. Remaining copies are removed by
     * "bin/console clear temp".
     */
    public function cleanup()
    {
        try {
            $this->filesystem->remove($this->getTempEpubPath());
        } catch (IOException $exception) {
            $this->logger->warning(
                'Cleanup: Could not remove temporary copy. ' . $exception->getMessage(),
                $this->debugging_info
            );
        }
    }

    private function getLogPath()
    {
        $base_path = $this->config->get('base_path') ?: __DIR__ . '/../../..';
        return $base_path . ($this->config->get('epub_log_dir') ?: '/logs') . '/application.log';
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
