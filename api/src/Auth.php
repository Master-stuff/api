<?php

/**
 * Authentication Helper (Legacy)
 * 
 * This file is kept for backward compatibility but should be replaced
 * with AuthMiddleware in new code.
 * 
 * @deprecated Use AuthMiddleware instead
 */

require './vendor/autoload.php';

if (empty($_ENV['SECRET_KEY'])) {
    http_response_code(500);
    echo json_encode(["error" => "Server configuration error"]);
    exit;
}

$secretKey = $_ENV['SECRET_KEY'];
$jwtManager = new JwtManager($secretKey);

$headers = getallheaders();
$auth = $headers["Authorization"] ?? "";

if (!preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
    http_response_code(401);
    echo json_encode(["error" => "Missing or invalid token"]);
    exit;
}

$token = $matches[1];

if (!$jwtManager->validateToken($token)) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}

$payload = $jwtManager->decodeToken($token);

if (!$payload) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}
