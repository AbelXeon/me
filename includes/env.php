<?php
// includes/env.php

function loadEnv($path)
{
    // If we are on a cloud host like Render, the environment variables 
    // are already loaded in the system, so we do not need the physical .env file!
    if (getenv('DB_DRIVER') !== false || isset($_ENV['DB_DRIVER'])) {
        return; 
    }

    // Otherwise, if we are local and there is no .env file, show the error
    if (!file_exists($path)) {
        die(".env file not found. Copy .env.example to .env and fill in your values.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        list($key, $value) = array_map('trim', explode('=', $line, 2));
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/../.env');