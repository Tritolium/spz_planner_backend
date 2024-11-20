<?php

$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/api/auth', '', $request);
$request = explode('?', $request)[0];
$request = explode('/', $request)[1];

switch ($request) {
    case 'challenge':
        require __DIR__ . '/challenge.php';
        break;
    case 'verify':
        require __DIR__ . '/verify.php';
        break;
    case 'logout':
        require __DIR__ . '/logout.php';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/404.php';
        break;
}