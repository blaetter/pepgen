<?php
/*
 * This file is the main - and only - entrance to the application
 */
require_once('../vendor/autoload.php');

// send json header
header('Content-Type: application/json');

// Health check for the monitoring: GET (or HEAD) /health next to this file, without parameters. 200 if pepgen can generate ePubs,
// 503 otherwise; the answer names only the checks, the reasons go to the error log. Never cached.
if (\Pepgen\Health\HealthCheck::isHealthRequest(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['SCRIPT_NAME'] ?? '/index.php',
    $_SERVER['REQUEST_METHOD'] ?? ''
)) {
    header('Cache-Control: no-store');
    try {
        $health = (new \Pepgen\Health\HealthCheck())->run();
    } catch (\Throwable $e) {
        error_log('pepgen health: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $health = ['status' => 'error', 'checks' => []];
    }
    http_response_code('ok' === $health['status'] ? 200 : 503);
    echo json_encode($health);
    return;
}

// Only POST requests are accepted, so the token and the watermark do not end up in URLs and access logs.
if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    header('HTTP/1.0 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode('Something went wrong: Only POST requests are allowed.');
    return;
}

// Be sure only the following three parameters are accessable within the app
// Only strings are accepted, e.g. id[]=1 is ignored.
$epub_id = $token = $watermark = false;
$request = $_POST;

if (isset($request['id']) && is_string($request['id'])) {
    $epub_id = htmlspecialchars($request['id']);
}

if (isset($request['token']) && is_string($request['token'])) {
    $token = htmlspecialchars($request['token']);
}

if (isset($request['watermark']) && is_string($request['watermark'])) {
    $watermark = urldecode(htmlspecialchars($request['watermark']));
}

$message = 'Something went wrong.';
try {
    $epub = new \Pepgen\Epub\Epub($epub_id, $token, $watermark);
    $epub->run();
    $message = $epub->message;
} catch (ErrorException $e) {
    // the request was denied, the message does not contain any internal details
    header("HTTP/1.0 400 Bad Request");
    $message = isset($epub) ? $epub->message : $message;
} catch (\Throwable $e) {
    // Unexpected errors, e.g. a broken configuration. Never show the details (paths, stack traces) to the client.
    error_log('pepgen: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    header("HTTP/1.0 500 Internal Server Error");
}
// display message from epub generation
echo json_encode($message);
