<?php

require __DIR__ . '/vendor/autoload.php';

set_error_handler('ErrorHandler::handleError');
set_exception_handler('ErrorHandler::handleException');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$required_env = ['DB_HOST', 'DB_NAME', 'DB_USER', 'SECRET_KEY'];
foreach ($required_env as $var) {
    if (empty($_ENV[$var])) {
        throw new RuntimeException("Missing required environment variable: {$var}");
    }
}

if (strlen($_ENV['SECRET_KEY']) < 32) {
    throw new RuntimeException("SECRET_KEY must be at least 32 characters for security");
}

header("Content-type: application/json; charset=UTF-8");

$database = new Database(
    $_ENV["DB_HOST"],
    $_ENV["DB_NAME"],
    $_ENV["DB_USER"],
    $_ENV["DB_PASS"]
);
