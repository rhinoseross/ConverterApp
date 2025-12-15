<?php

/**
 * db.php
 * Central place for DB configuration + connection helpers.
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
 * Returns a small HTML string to render in the UI.
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

/**
 * Fetch exchange rates from DB.
 * Expects a table:
 *   fx_rates(currency_code CHAR(3) PRIMARY KEY, rate_to_usd DECIMAL(...))
 *
 * Returns: ['USD' => 1.0, 'EUR' => 0.92, ...]
 */
function db_get_rates(): array {
    $pdo = db_pdo();

    $stmt = $pdo->query("SELECT currency_code, rate_to_usd FROM fx_rates");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rates = [];
    foreach ($rows as $r) {
        $code = strtoupper((string)$r['currency_code']);
        $rates[$code] = (float)$r['rate_to_usd'];
    }

    // Ensure base exists
    if (!isset($rates['USD'])) {
        $rates['USD'] = 1.0;
    }

    return $rates;
}

/**
 * Simple in-request cache to avoid repeated DB reads if called multiple times.
 * Note: This cache is per PHP request (not shared across requests).
 */
function db_get_rates_cached(int $ttlSeconds = 60): array {
    static $cache = null;
    static $cachedAt = 0;

    $now = time();
    if ($cache !== null && ($now - $cachedAt) < $ttlSeconds) {
        return $cache;
    }

    $cache = db_get_rates();
    $cachedAt = $now;
    return $cache;
}
