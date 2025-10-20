<?php

declare(strict_types=1);

use HttpCapture\Application;
use HttpCapture\Http\Response;

require __DIR__ . '/src/bootstrap.php';

$application = new Application();
$response = $application->handle($_SERVER, file_get_contents('php://input') ?: '');

if (!$response instanceof Response) {
    return;
}

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $value) {
    header($name . ': ' . $value);
}

echo $response->getBody();
