<?php

/*
 * This file is the main - and only - entrance to the application
 *
 *
 */
require_once('../vendor/autoload.php');

// Be sure only the following three parameters are accessable within the app

$epub_id = $token = $watermark = false;

if (isset($_REQUEST['id'])) {
    $epub_id = htmlspecialchars($_REQUEST['id']);
}

if (isset($_REQUEST['token'])) {
    $token = htmlspecialchars($_REQUEST['token']);
}

if (isset($_REQUEST['watermark'])) {
    $watermark = urldecode(htmlspecialchars($_REQUEST['watermark']));
}

$epub = new \Pepgen\epub\Epub($epub_id, $token, $watermark);
$epub->run();
