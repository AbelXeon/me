<?php
// create_db.php — RUN ONCE to build the database

require_once 'config/database.php';

$driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

if ($driver === 'sqlite') {
    $dbFolder = __DIR__ . '/database';
    $dbPath = $dbFolder . '/social_manager.sqlite';

    // 1. DROP THE SQLITE DATABASE FILE
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "🗑️ Old SQLite database file deleted.<br>";
    }

    if (!is_dir($dbFolder)) {
        mkdir($dbFolder, 0777, true);
        echo "📁 Created database/ folder.<br>";
    }

    try {
        $pdo = getDBConnection();

        $schema = <<<SQL
        CREATE TABLE users (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name      TEXT NOT NULL,
            middle_name     TEXT,
            last_name       TEXT NOT NULL,
            username        TEXT NOT NULL UNIQUE,
            password        TEXT NOT NULL,
            email           TEXT NOT NULL UNIQUE,
            address         TEXT,
            phone_no        TEXT,
            profile_image   TEXT,
            account_status  TEXT NOT NULL DEFAULT 'pending'
                                CHECK (account_status IN ('active','suspended','pending','deleted')),
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE media_files (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            path            TEXT NOT NULL,
            type            TEXT NOT NULL CHECK (type IN ('image','video')),
            size            INTEGER NOT NULL,
            mime_type       TEXT NOT NULL,
            uploaded_by     INTEGER NOT NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE posts (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id         INTEGER NOT NULL,
            caption         TEXT NOT NULL,
            title           TEXT,
            external_link   TEXT,
            media_type      TEXT NOT NULL CHECK (media_type IN ('image','video')),
            media_id        INTEGER NOT NULL,
            status          TEXT NOT NULL DEFAULT 'draft'
                                CHECK (status IN ('draft','scheduled','posted','failed')),
            scheduled_at    TIMESTAMP,
            published_at    TIMESTAMP,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE RESTRICT
        );

        CREATE TABLE post_extra_media (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id         INTEGER NOT NULL,
            media_id        INTEGER NOT NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE CASCADE
        );

        CREATE TABLE email_verification (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id         INTEGER NOT NULL,
            email           TEXT NOT NULL,
            code            TEXT NOT NULL,
            attempt_count   INTEGER NOT NULL DEFAULT 0,
            is_used         INTEGER NOT NULL DEFAULT 0 CHECK (is_used IN (0,1)),
            purpose         TEXT NOT NULL CHECK (purpose IN ('email_verify','password_reset','2fa')),
            expires_at      TIMESTAMP NOT NULL,
            verified_at     TIMESTAMP,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE social_accounts (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id             INTEGER NOT NULL,
            platform            TEXT NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            account_name        TEXT NOT NULL,
            access_token        TEXT NOT NULL,
            refresh_token       TEXT,
            platform_user_id    TEXT,
            token_expires_at    TIMESTAMP,
            status              INTEGER NOT NULL DEFAULT 1 CHECK (status IN (0,1)),
            connected_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, platform)
        );

        CREATE TABLE post_platforms (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id             INTEGER NOT NULL,
            platform            TEXT NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            platform_post_id    TEXT,
            status              TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','posted','failed')),
            error_message       TEXT,
            posted_at           TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        );

        CREATE TABLE login_attempts (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id         INTEGER,
            username        TEXT NOT NULL,
            ip_address      TEXT NOT NULL,
            success         INTEGER NOT NULL CHECK (success IN (0,1)),
            attempted_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE INDEX idx_posts_user_status ON posts(user_id, status);
        CREATE INDEX idx_post_platforms_post ON post_platforms(post_id, platform);
        CREATE INDEX idx_social_accounts_user_platform ON social_accounts(user_id, platform);
        CREATE INDEX idx_login_attempts_lookup ON login_attempts(username, attempted_at);
        CREATE INDEX idx_email_verification_lookup ON email_verification(user_id, purpose, is_used);
        CREATE INDEX idx_media_files_uploader ON media_files(uploaded_by);
SQL;

        $pdo->exec($schema);
        echo "<h2>✅ Fresh SQLite Database created successfully!</h2>";

    } catch (Exception $e) {
        die("<h2>❌ SQLite Setup Failed:</h2> " . $e->getMessage());
    }

} elseif ($driver === 'pgsql') {
    // POSTGRESQL CODE PATH
    try {
        $pdo = getDBConnection();

        $drops = <<<SQL
        DROP TABLE IF EXISTS login_attempts CASCADE;
        DROP TABLE IF EXISTS post_platforms CASCADE;
        DROP TABLE IF EXISTS social_accounts CASCADE;
        DROP TABLE IF EXISTS email_verification CASCADE;
        DROP TABLE IF EXISTS post_extra_media CASCADE;
        DROP TABLE IF EXISTS posts CASCADE;
        DROP TABLE IF EXISTS media_files CASCADE;
        DROP TABLE IF EXISTS users CASCADE;
SQL;
        $pdo->exec($drops);
        echo "🗑️ Old PostgreSQL tables dropped.<br>";

        $schema = <<<SQL
        CREATE TABLE users (
            id              SERIAL PRIMARY KEY,
            first_name      VARCHAR(255) NOT NULL,
            middle_name     VARCHAR(255),
            last_name       VARCHAR(255) NOT NULL,
            username        VARCHAR(255) NOT NULL UNIQUE,
            password        VARCHAR(255) NOT NULL,
            email           VARCHAR(255) NOT NULL UNIQUE,
            address         VARCHAR(255),
            phone_no        VARCHAR(50),
            profile_image   VARCHAR(255),
            account_status  VARCHAR(50) NOT NULL DEFAULT 'pending'
                                CHECK (account_status IN ('active','suspended','pending','deleted')),
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE media_files (
            id              SERIAL PRIMARY KEY,
            path            TEXT NOT NULL,
            type            VARCHAR(50) NOT NULL CHECK (type IN ('image','video')),
            size            BIGINT NOT NULL,
            mime_type       VARCHAR(100) NOT NULL,
            uploaded_by     INTEGER NOT NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE posts (
            id              SERIAL PRIMARY KEY,
            user_id         INTEGER NOT NULL,
            caption         TEXT NOT NULL,
            title           VARCHAR(255),
            external_link   TEXT,
            media_type      VARCHAR(50) NOT NULL CHECK (media_type IN ('image','video')),
            media_id        INTEGER NOT NULL,
            status          VARCHAR(50) NOT NULL DEFAULT 'draft'
                                CHECK (status IN ('draft','scheduled','posted','failed')),
            scheduled_at    TIMESTAMP,
            published_at    TIMESTAMP,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE RESTRICT
        );

        CREATE TABLE post_extra_media (
            id              SERIAL PRIMARY KEY,
            post_id         INTEGER NOT NULL,
            media_id        INTEGER NOT NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE CASCADE
        );

        CREATE TABLE email_verification (
            id              SERIAL PRIMARY KEY,
            user_id         INTEGER NOT NULL,
            email           VARCHAR(255) NOT NULL,
            code            VARCHAR(50) NOT NULL,
            attempt_count   INTEGER NOT NULL DEFAULT 0,
            is_used         INTEGER NOT NULL DEFAULT 0 CHECK (is_used IN (0,1)),
            purpose         VARCHAR(50) NOT NULL CHECK (purpose IN ('email_verify','password_reset','2fa')),
            expires_at      TIMESTAMP NOT NULL,
            verified_at     TIMESTAMP,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE social_accounts (
            id                  SERIAL PRIMARY KEY,
            user_id             INTEGER NOT NULL,
            platform            VARCHAR(50) NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            account_name        VARCHAR(255) NOT NULL,
            access_token        TEXT NOT NULL,
            refresh_token       TEXT,
            platform_user_id    VARCHAR(255),
            token_expires_at    TIMESTAMP,
            status              INTEGER NOT NULL DEFAULT 1 CHECK (status IN (0,1)),
            connected_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, platform)
        );

        CREATE TABLE post_platforms (
            id                  SERIAL PRIMARY KEY,
            post_id             INTEGER NOT NULL,
            platform            VARCHAR(50) NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            platform_post_id    VARCHAR(255),
            status              VARCHAR(50) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','posted','failed')),
            error_message       TEXT,
            posted_at           TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        );

        CREATE TABLE login_attempts (
            id              SERIAL PRIMARY KEY,
            user_id         INTEGER,
            username        VARCHAR(255) NOT NULL,
            ip_address      VARCHAR(50) NOT NULL,
            success         INTEGER NOT NULL CHECK (success IN (0,1)),
            attempted_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE INDEX idx_posts_user_status ON posts(user_id, status);
        CREATE INDEX idx_post_platforms_post ON post_platforms(post_id, platform);
        CREATE INDEX idx_social_accounts_user_platform ON social_accounts(user_id, platform);
        CREATE INDEX idx_login_attempts_lookup ON login_attempts(username, attempted_at);
        CREATE INDEX idx_email_verification_lookup ON email_verification(user_id, purpose, is_used);
        CREATE INDEX idx_media_files_uploader ON media_files(uploaded_by);
SQL;

        $pdo->exec($schema);
        echo "<h2>✅ Fresh PostgreSQL Database created successfully!</h2>";

    } catch (Exception $e) {
        die("<h2>❌ PostgreSQL Setup Failed:</h2> " . $e->getMessage());
    }
} elseif ($driver === 'mysql') {
    // MYSQL CODE PATH (FOR AIVEN)
    try {
        $pdo = getDBConnection();

        // Disable checks temporarily to drop tables without dependency issues
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS login_attempts;");
        $pdo->exec("DROP TABLE IF EXISTS post_platforms;");
        $pdo->exec("DROP TABLE IF EXISTS social_accounts;");
        $pdo->exec("DROP TABLE IF EXISTS email_verification;");
        $pdo->exec("DROP TABLE IF EXISTS post_extra_media;");
        $pdo->exec("DROP TABLE IF EXISTS posts;");
        $pdo->exec("DROP TABLE IF EXISTS media_files;");
        $pdo->exec("DROP TABLE IF EXISTS users;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        echo "🗑️ Old MySQL tables dropped.<br>";

        $schema = <<<SQL
        CREATE TABLE users (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            first_name      VARCHAR(255) NOT NULL,
            middle_name     VARCHAR(255),
            last_name       VARCHAR(255) NOT NULL,
            username        VARCHAR(255) NOT NULL UNIQUE,
            password        VARCHAR(255) NOT NULL,
            email           VARCHAR(255) NOT NULL UNIQUE,
            address         VARCHAR(255),
            phone_no        VARCHAR(50),
            profile_image   VARCHAR(255),
            account_status  VARCHAR(50) NOT NULL DEFAULT 'pending'
                                CHECK (account_status IN ('active','suspended','pending','deleted')),
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE media_files (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            path            TEXT NOT NULL,
            type            VARCHAR(50) NOT NULL CHECK (type IN ('image','video')),
            size            BIGINT NOT NULL,
            mime_type       VARCHAR(100) NOT NULL,
            uploaded_by     INT NOT NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE posts (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NOT NULL,
            caption         TEXT NOT NULL,
            title           VARCHAR(255),
            external_link   TEXT,
            media_type      VARCHAR(50) NOT NULL CHECK (media_type IN ('image','video')),
            media_id        INT NOT NULL,
            status          VARCHAR(50) NOT NULL DEFAULT 'draft'
                                CHECK (status IN ('draft','scheduled','posted','failed')),
            scheduled_at    TIMESTAMP NULL,
            published_at    TIMESTAMP NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE RESTRICT
        );

        CREATE TABLE post_extra_media (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            post_id         INT NOT NULL,
            media_id        INT NOT NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (media_id) REFERENCES media_files(id) ON DELETE CASCADE
        );

        CREATE TABLE email_verification (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NOT NULL,
            email           VARCHAR(255) NOT NULL,
            code            VARCHAR(50) NOT NULL,
            attempt_count   INT NOT NULL DEFAULT 0,
            is_used         INT NOT NULL DEFAULT 0 CHECK (is_used IN (0,1)),
            purpose         VARCHAR(50) NOT NULL CHECK (purpose IN ('email_verify','password_reset','2fa')),
            expires_at      TIMESTAMP NOT NULL,
            verified_at     TIMESTAMP NULL,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE social_accounts (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            user_id             INT NOT NULL,
            platform            VARCHAR(50) NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            account_name        VARCHAR(255) NOT NULL,
            access_token        TEXT NOT NULL,
            refresh_token       TEXT,
            platform_user_id    VARCHAR(255),
            token_expires_at    TIMESTAMP NULL,
            status              INT NOT NULL DEFAULT 1 CHECK (status IN (0,1)),
            connected_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY (user_id, platform)
        );

        CREATE TABLE post_platforms (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            post_id             INT NOT NULL,
            platform            VARCHAR(50) NOT NULL CHECK (platform IN ('facebook','instagram','telegram','linkedin','tiktok')),
            platform_post_id    VARCHAR(255),
            status              VARCHAR(50) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','posted','failed')),
            error_message       TEXT,
            posted_at           TIMESTAMP NULL,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        );

        CREATE TABLE login_attempts (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NULL,
            username        VARCHAR(255) NOT NULL,
            ip_address      VARCHAR(50) NOT NULL,
            success         INT NOT NULL CHECK (success IN (0,1)),
            attempted_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE INDEX idx_posts_user_status ON posts(user_id, status);
        CREATE INDEX idx_post_platforms_post ON post_platforms(post_id, platform);
        CREATE INDEX idx_social_accounts_user_platform ON social_accounts(user_id, platform);
        CREATE INDEX idx_login_attempts_lookup ON login_attempts(username, attempted_at);
        CREATE INDEX idx_email_verification_lookup ON email_verification(user_id, purpose, is_used);
        CREATE INDEX idx_media_files_uploader ON media_files(uploaded_by);
SQL;

        $pdo->exec($schema);
        echo "<h2>✅ Fresh MySQL Database created successfully on Aiven!</h2>";

    } catch (Exception $e) {
        die("<h2>❌ MySQL Setup Failed:</h2> " . $e->getMessage());
    }
}