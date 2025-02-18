<?php
$request = $_SERVER['REQUEST_URI'];

if (isset($_GET['api_token'])) {
    $api_token = $_GET['api_token'];
} else if ($request == '/api/v0/error') {
    // do nothing
} else {
    http_response_code(403);
    exit();
}

// remove /api/v0 from the request
$request = str_replace('/api/v0', '', $request);
// remove query string from the request
$request = explode('?', $request)[0];
$request = explode('/', $request)[1];

switch ($request) {
    case 'analytics':
        require __DIR__ . '/analytics.php';
        break;
    case 'association':
        require __DIR__ . '/association.php';
        break;
    case 'attendence':
        require __DIR__ . '/attendence.php';
        break;
    case 'attendenceeval':
        require __DIR__ . '/attendenceeval.php';
        break;
    case 'calendar':
        require __DIR__ . '/calendar.php';
        break;
    case 'error':
        require __DIR__ . '/error.php';
        break;
    case 'events':
        require __DIR__ . '/events.php';
        break;
    case 'member':
        require __DIR__ . '/member.php';
        break;
    case 'p_evaluation':
        require __DIR__ . '/p_evaluation.php';
        break;
    case 'permissions':
        require __DIR__ . '/permissions.php';
        break;
    case 'pushsubscription':
        require __DIR__ . '/pushsubscription.php';
        break;
    case 'roleassign':
        require __DIR__ . '/roleassign.php';
        break;
    case 'roles':
        require __DIR__ . '/roles.php';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/404.php';
        break;
}

?>