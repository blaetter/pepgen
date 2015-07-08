<?php
/**
 * Class epub
 *
 * handles epub stuff
 */

namespace Pepgen\epub;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Epub
{
    private $config;

    private $http_base;

    private $base_dir;

    private $watermark;

    private $epub_id;

    private $token;

    private $secret;

    private $epub;

    private $epub_original_directory;

    private $epub_output_directory;

    private $epub_temp_dirctory;

    private $textpattern;

    private $template;

    private $files_to_replace;

    private $finder;

    private $filesystem;

    public function __construct()
    {
        // get the configuration
        $this->getConfig();

        // http base for everything
        $this->http_base = $this->config['http_base'];

        // the absolute base path of the application
        $this->base_dir = $this->config['base_path'];

        // the textpattern that is searched for in the epubs
        $this->textpattern = $this->config['textpattern'];

        // the template the textpattern is replaced with
        $this->template = $this->config['template'];

        // the secret key for generating the token
        $this->secret = $this->config['secret'];

        // the regexp for the files we have to replace text in
        $this->files_to_replace = $this->config['files_to_replace'];

        // the id of the requested ePub
        $this->epub_id = @htmlspecialchars($_REQUEST['id']);

        // the token given by the request
        $this->token = @htmlspecialchars($_REQUEST['token']);

        // the watermark that will be printed right into the epub
        $this->watermark = @urldecode(htmlspecialchars($_REQUEST['watermark']));

        // the name of the epub - the name should be the node-id with an .epub suffix
        $this->epub = $this->epub_id . '.epub';

        // the name of the personal epub - the name should be the ordinary epub name plus the token
        $this->epub_personal = $this->token . '.' . $this->epub;

        // the directory where the original epub-folders are stored - uncompressed and not accessable from the web
        $this->epub_original_directory = $this->base_dir.'/epub/';

        // the directory where the generated epubs will be stored until they're delivered to the end user
        $this->epub_output_directory = $this->base_dir.'/public/download/';

        // the directory where the user specific epubs are copied to and processed
        $this->epub_temp_dirctory = $this->base_dir.'/tmp/';

        // creating instance of filesystem
        $this->filesystem = new Filesystem();
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
     * This function returns plain text information back to drupal
     *
     */
    public function success()
    {
        $this->out('Success');
        exit;
    }

    /**
     * This function provides the download of the given file via a crypted url - if already there
     *
     */
    public function fastrun()
    {
        // check for needed files
        if ($this->filesystem->exists($this->epub_output_directory.$this->epub_personal)) {
            $this->success();
        }
    }

    /**
     * This means there's noting to do, so deny the request
     *
     * @param $msg - the messages that is displayed
     */
    public function deny($msg = '')
    {
        $this->out('Something went wrong: '.$msg);
        header("HTTP/1.0 400 Bad Request");
        exit;
    }

    /**
     * This function verifies the request
     *
     */
    private function verify()
    {
        // If no information is provided or the information is invalid, cancel request at this point.
        if (
            empty($this->watermark) ||
            empty($this->epub_id) ||
            empty($this->token) ||
            $this->token !== $this->tokenize()
        ) {
            $this->deny('Not enough arguments or wrong arguments.');
        }
    }

    /**
     * This function handles the file system operations.
     * First it looks for the requested epub mirrors it to the tmp directory
     *
     */
    private function copy()
    {
        // check for needed files
        // if its not there the requests is per se not allowed
        if (!$this->filesystem->exists($this->epub_original_directory.$this->epub)) {
            $this->deny('Requested ePub does not exist.');
        }

        // copy the original file to the target directory and rename it
        try {
            $this->filesystem->mirror(
                $this->epub_original_directory.$this->epub,
                $this->epub_temp_dirctory.$this->epub_personal,
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
    private function modify()
    {
        // now we need to try finding the requested files for modifications
        $this->finder = new Finder();
        $this->finder->files()->name($this->files_to_replace)->in($this->epub_temp_dirctory.$this->epub_personal);

        foreach ($this->finder as $file) {
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
                } catch (IOException $e) {
                    $this->deny('Could not write watermark to file.');
                }
            }
        }
    }

    /**
     * This function processes the epub and puts it in the download directory
     *
     */
    private function process()
    {
        // lets start the process that handles the zipping
        $process = new Process(
            'cd '.
            $this->epub_temp_dirctory.
            $this->epub_personal.
            ' && zip -0Xq '.
            $this->epub_output_directory.
            $this->epub_personal.
            ' mimetype && zip -Xr9Dq '.
            $this->epub_output_directory.
            $this->epub_personal.
            ' *'
        );

        $process->run();

        if (!$process->isSuccessful()) {
            $this->deny('Zipping went wrong. Could not create personalised ePub.');
        }

        if (!$this->filesystem->exists($this->epub_output_directory.$this->epub_personal)) {
            $this->deny('personalized ePub could not be created. Please try again later.');
        }
    }

    /**
     * This function renders a message in json and gives it back to the user
     *
     *
     */
    private function out($message)
    {
        print(json_encode($message));
    }

    /**
     * tokenize - the unique implementation of how to build the token
     *
     */
    private function tokenize()
    {
        return md5(
            $this->epub_id.
            $this->secret.
            $this->watermark.
            strftime("%d.%m.%Y")
        );
    }

    /**
     * Handles config object
     *
     */
    private function getConfig()
    {
        $configFinder = new Finder();

        $configFinder->files()->name('config.yml')->in('../app/config/');

        foreach ($configFinder as $config) {
            $configs[] = $config->getContents();
        }

        $this->config = YAML::parse(implode('\r\n', $configs));

        if (empty($this->config)) {
            $this->deny('Could not load config - aborting.');
        }

    }
}
