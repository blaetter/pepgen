<?php

/*
 * This file is the main - and only - entrance to the application
 *
 *
 */
require_once('../vendor/autoload.php');

require_once('../app/modules/epub/epub.class.php');

$epub = new epub();
$epub->run();

?>