<?php

/*
 * This file is the main - and only - entrance to the application
 *
 *
 */
require_once('../vendor/autoload.php');

$epub = new \Pepgen\epub\Epub();
$epub->run();
