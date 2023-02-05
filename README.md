# PeP-Gen Personalized ePub Generation

[![Coverage Status](https://coveralls.io/repos/github/blaetter/pepgen/badge.svg)](https://coveralls.io/github/blaetter/pepgen)
[![Build Status](https://travis-ci.org/blaetter/pepgen.svg)](https://travis-ci.org/blaetter/pepgen)
[![Code Climate](https://codeclimate.com/github/blaetter/pepgen/badges/gpa.svg)](https://codeclimate.com/github/blaetter/pepgen)

## Introduction

This application generates personalized ePubs. As ePubs are just compressed folders and files, all you have to do is to provide unzipped versions of your ePub. The application itself is not build to be called directly from the enduser (although it is comfortable for testing purposes), it is more an application that can speak to your website or shop.

## Requirements

* php >= 8.0 for Symfony Components
* Unix environment with an AMP stack (should work on windows to, but thats not tested nor supported)
* Shell to run composer

## Installation

1. clone repository: `git clone git@github.com:blaetter/pepgen.git`
2. change directory: `cd pepgen`
3. install composer: `php -r "eval('?>'.file_get_contents('https://getcomposer.org/installer'));"`
4. install dependencies `php composer.phar install`
5. copy config: `cp app/config/sample.config.yml app/config/config.yml`
6. set up your configuration
7. put at least one unzipped ePub into the `epub` folder
8. make sure your webserver only has access to public/ and go for it

## Configuration

You need to configure the following parameters that are located in the `app/config/config.yml` (step 5 of the installation process)

1. `base_path` - The absolute base path to your copy of PeP-Gen. No trailing slash.
2. `http_base` - The baseic URL of your PeP-Gen. No trailing slash.
3. `secret` - The secret token that is shared between your website or shop and PeP-Gen
4. `textpattern` - The textpattern that is located in your original ePubs e.g. `<!-- WATERMARK -->` or `XXX_WATERMARK_XXX`. Should be very unique. Has to contain delemiters because I don't know what characters you want to use.
5. `template` - The template the textpattern is replaced by. It should contain a `%s` pattern so the watermark can be put into it
6. `files_to_replace` a string of files where the watermark can be found in. Maybe you want to put the watermark in more than one file? Should either be a full filename or a regular expression including the delemiters.
7. `epub_original_dir` the source directory where you have to place the untouched epubs, e.g. `/epub`
8. `epub_temp_dir` the temp directory for creating the epubs, e.g. `/tmp`
9. `epub_public_dir` the directory where the personal epubs will be moved to, e.g. `/public/download`
10. `loglevel` a RFC 5424 numeric loglevel.
11. `keepfiles` number of logfiles that needs to be kept after rotation.

## Usage

After setting up the application you can call it via a browser. The application needs the following parameters:

1. `epub_id`- the id of the requested ePub. In the most cases this should be an integer or string that represents the name of your epub without the file extension.
2. `token` - the personalized token of the request. This token changed on every request, and only the two endpoints - e.g. your website or shop and this application should know how to build it. It contains a secret string, some of the request variables and a date string. Only if the token can be validated by the application, the request is handled.
3. `watermark` - this is the watermark that is put into the ePub. What it contains depends on you.

If a request does not provide all theese parameters, it will not be handled. The application itself copies the original unzipped ePub into a temporary folder, adds the watermark and zippes the ePub into `public/download` with a hashed filename.

Your website or shop can now decide how to deliver that to your costumer, there are three ways to do so:

1. Provide a direct download link: you can forward the user to the download location or you can display or mail the hashed URL of the generated ePub to the user. This method implicates, that the URL of this application becomes public. If you're fine with that, this method is the easy one and right for you.
2. Your website or shop does a server request to the generated ePub and streames it to the end user. Using this method, your customer will receive the ePub directly from your website or shop. This version is more secure.
3. If your website or shop and this application are on the same server, things are much easier for you. You can implement this application directly into your website or shop or you can generate the personalized ePubs directly into a public folder of your website or shop.

## Maintenance

Pepgen is shipped with a command line tool at `bin/console` which can help you to clear the generated files. Check out `bin/console list` for a list of available commands and options.

You might want to use theese commands to interact with Pepgen on a regular basis, e.g. via cron. Something like `bin/console clear temp` could be executed every night depending on your traffic.

## Logging

Depending on your configuration there is more or less logging saved into the log folder. Application logs are rotated once per day, but you might want to use the console command `bin/console clear logs` to get rid of old logfiles as well.

## Development

Feel free to change Pepgen to your needs, PRs are welcome. Please test your change and stay close to standards regarding code climate, editor config and code styles.

You might want to use the convinient shortcuts like `make dev`, `make test` oder `make fix`. Take a look into the Makefile for further information.

## Disclaimer

You should know that this watermark is easy to remove for people who know about ePub generation. But its a visible border to put your ePubs free for all into the internet. There is absolutely no warranty that this watermark will prevent your content from beeing published by other people than yourself.
