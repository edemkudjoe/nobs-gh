<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/** Keeps PHP sessions in the sessions table, because Vercel containers have no durable disk. */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $data = db_val('SELECT data FROM sessions WHERE id = ? AND expires_at > now()', [$id]);
        return $data === null ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $life = (int)ini_get('session.gc_maxlifetime');
        db_exec(
            "INSERT INTO sessions (id, data, expires_at)
             VALUES (?, ?, now() + make_interval(secs => ?))
             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at",
            [$id, $data, $life]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        db_exec('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return db_exec('DELETE FROM sessions WHERE expires_at < now()');
    }
}

/** Call once at the top of every page (the layout will do this). */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.gc_maxlifetime', '7200');          // 2 hours
    ini_set('session.use_strict_mode', '1');
    // Vercel ends HTTPS before the container, so look at the forwarded header too.
    $https = (($_SERVER['HTTPS'] ?? '') === 'on')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_set_save_handler(new DbSessionHandler(), true);
    session_name('nobb_session');
    session_start();
}