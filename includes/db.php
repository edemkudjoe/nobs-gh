<?php
declare(strict_types=1);

/** Read a setting from the environment (.env locally, Vercel settings online). */
function env(string $key, ?string $default = null): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        if ($default === null) {
            throw new RuntimeException("Missing environment variable: $key");
        }
        return $default;
    }
    return $v;
}

/** One shared PDO connection per request. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        env('DB_HOST'), env('DB_PORT', '5432'), env('DB_NAME'), env('DB_SSLMODE', 'require')
    );
    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // The Supabase pooler can reject server-side prepared statements.
        // Emulated prepares still escape every value safely.
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
    return $pdo;
}

/** Every row: db_all('SELECT * FROM regions WHERE id > ?', [3]) */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** First row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** First column of the first row, or null. */
function db_val(string $sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row === null ? null : array_values($row)[0];
}

/** INSERT / UPDATE / DELETE. Returns the number of rows changed. */
function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Run several statements as one unit: all succeed or none do. */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}