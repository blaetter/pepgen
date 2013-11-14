# Automated ePub watermark generation for Blätter

## Introduction

This application generates personalized ePubs. As ePubs are just compressed Folders and files, all you have to to is to provied unzipped versions of your ePub. The application itself is not ment to be called directly from the enduser (although its comfortable for testing purposes), its more an application that can speak to your website or shop. 

## Requirements

* php > 5.3 for Symfony Components
* Unix environment with an AMP stack (should work on windows to, but thats not tested nore supported
* Shell to run composer

## Installation

1. clone repository: `git clone git@github.com:ambo/blaetter-epub.git`
2. change directory: `cd blaetter-epub`
3. install composer: `php -r "eval('?>'.file_get_contents('https://getcomposer.org/installer'));"`
4. install dependencies `php composer.phar install`
5. copy config: `cp app/config/sample.config.yml app/config/config.yml`
6. set up your configuration
7. put at least one unzipped ePub into the `epub` folder
8. make sure your webserver only has access to public/ and go for it


## Usage

After setting up the application you can call it via a browser. The application needs the following parameters:

1. `id`- the id of the requested ePub. In the most cases this should be an integer or string that represents the name of your epub without the file extension.
2. `token` - the personalized token of the request. This token changed on every request, and only the two endpoints - e.g. your website or shop and this application should know how to build it. It contains a secret string, some of the request variables and a date string. Only if the token can be validated by the application, the request is handled.
3. `watermark` - this is the watermark that is put into the ePub. What it contains depends on you. 

If a request does not provide all theese parameters, it will not be handled. The application itself copies the original unzipped ePub into a temporary folder, adds the watermark and zippes the ePub into `public/download` with a hashed filename. 

Your Shop or Website can now decide how to deliver that to your costumer, there are three ways to do so:

1. Provide a direct download link: you can forward the user to the download location or you can display or mail the hashed URL of the generated ePub to the user. This method implicates, that the URL of this application becomes public. If you're fine with that, this method is the easy one and right for you.
2. Your Shop or Website does a server request to the generated ePub and streames it to the end user. Using this method, your customer will receive the ePub directly from your Shop or Website. This version is more secure.
3. If your Shop or Website and this application are on the same server, things are much easier for you. You can implement this application diretly into your Shop or Application or you can generate the personalized ePubs directly into a public folder of your Shop or Website.

## Disclaimer

You should know, that this watermark is easy to remove for people who know about ePub generation. But its a visible border to put your ePubs free for all into the internet. There is absolutely no warranty that this watermark will prevent your content from beeing published by other people than yourself.
