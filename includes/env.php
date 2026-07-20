<?php
// includes/env.php

function loadEnv($path)
{
    // If the .env file does not exist (like on Render), we do NOT crash.
    // We just return gracefully and let Render's system environment variables work!
    if (!file_exists($path)) {
        return; 
    }

    // If the file exists (like on your localhost), we load it
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