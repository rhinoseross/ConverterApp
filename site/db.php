<?php

/**
 * db.php
 * Central place for DB configuration + connection helpers.
 * (We’ll later swap this to pull from AWS Secrets Manager or whatever you decide.)
 */

function db_env(string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val === false || $val === '') return $default;
    return $val;
}

function db_pdo(): PDO {
    $host = db_env('DB_HOST');
    $name = db_env('DB_NAME');
    $user = db_env('DB_USER');
    $pass = db_env('DB_PASS');
    $port = db_env('DB_PORT', '3306');

    if (!$host || !$name || !$user) {
        throw new RuntimeException("Missing DB env vars (DB_HOST/DB_NAME/DB_USER[/DB_PASS]).");
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Returns a small HTML string you can render in the UI.
 * Checks basic connectivity and whether a given table exists.
 */
function db_status_message(string $table = 'conversions'): string {
    try {
        $pdo = db_pdo();

        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);

        if ($stmt->rowCount() > 0) {
            return "<span class='ok'>Database connected successfully. Table '" . htmlspecialchars($table) . "' found.</span>";
        }
        return "<span class='warn'>Connected to database, but table '" . htmlspecialchars($table) . "' does not exist.</span>";

    } catch (Throwable $e) {
        return "<span class='bad'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}
