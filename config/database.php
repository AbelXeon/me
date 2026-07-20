<?php
// config/database.php

// 1. Set your local timezone (Important for scheduling!)
date_default_timezone_set('Africa/Addis_Ababa');

// 2. Load environment variables
require_once __DIR__ . '/../includes/env.php';

// 3. Define Database Constants from .env
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
define('DB_PATH',   __DIR__ . '/../' . (getenv('DB_PATH') ?: 'database/social_manager.sqlite'));
define('DB_HOST',   getenv('DB_HOST') ?: 'localhost');
define('DB_PORT',   getenv('DB_PORT') ?: '5432'); // <-- ADDED PORT CONSTANT
define('DB_NAME',   getenv('DB_NAME') ?: 'social_manager');
define('DB_USER',   getenv('DB_USER') ?: 'root');
define('DB_PASS',   getenv('DB_PASS') ?: '');

/**
 * Creates and returns a PDO database connection
 */
function getDBConnection()
{
    try {
        if (DB_DRIVER === 'sqlite') {
            // SQLite Connection
            $conn = new PDO('sqlite:' . DB_PATH);
        } elseif (DB_DRIVER === 'pgsql') {
            // PostgreSQL Connection (Includes custom Port and SSL requirement)
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require"; // <-- ADDED PORT HERE
            $conn = new PDO($dsn, DB_USER, DB_PASS);
        } else {
            // MySQL Connection
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
        }

        // Set Error Mode to Exception so we can catch errors
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Return results as Associative Arrays by default
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable Foreign Keys for SQLite
        if (DB_DRIVER === 'sqlite') {
            $conn->exec('PRAGMA foreign_keys = ON;');
        }

        return $conn;
    } catch (PDOException $e) {
        die("Database Connection failed: " . $e->getMessage());
    }
}