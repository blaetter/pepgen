<?php

/*
 * This file is the main - and only - entrance to the application
 *
 *
 */
require_once('../vendor/autoload.php');

// Be sure only the following three parameters are accessable within the app

$epub_id = $token = $watermark = false;

if (isset($_POST['id'])) {
    $epub_id = htmlspecialchars($_POST['id']);
}

if (isset($_POST['token'])) {
    $token = htmlspecialchars($_POST['token']);
}

if (isset($_POST['watermark'])) {
    $watermark = urldecode(htmlspecialchars($_POST['watermark']));
}

$epub = new \Pepgen\epub\Epub($epub_id, $token, $watermark);
$epub->run();
