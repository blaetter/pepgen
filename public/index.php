<?php
/*
 * This file is the main - and only - entrance to the application
 */
require_once('../vendor/autoload.php');

// Be sure only the following three parameters are accessable within the app
$epub_id = $token = $watermark = false;
$request = $_REQUEST;

if (isset($request['id'])) {
    $epub_id = htmlspecialchars($request['id']);
}

if (isset($request['token'])) {
    $token = htmlspecialchars($request['token']);
}

if (isset($request['watermark'])) {
    $watermark = urldecode(htmlspecialchars($request['watermark']));
}

$epub = new \Pepgen\epub\Epub($epub_id, $token, $watermark);
try {
    $epub->run();
} catch (ErrorException $e) {
    // set bad request header
    header("HTTP/1.0 400 Bad Request");
}
// send json header
header('Content-Type: application/json');
// display message from epub generation
echo json_encode($epub->message);
