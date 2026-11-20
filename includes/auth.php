<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';

/** Escape text before printing it into HTML. */
function e(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Where each role lands after signing in. */
function home_for(string $role): string
{
    return match ($role) {
        'admin'    => '/admin/dashboard.php',
        'center'   => '/center/dashboard.php',
        'hospital' => '/hospital/dashboard.php',
        default    => '/donor/dashboard.php',
    };
}

/** The signed-in user (with facility rights), or null. Loaded once per request. */
function current_user(): ?array
{
    static $loaded = false, $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $user = db_one(
        "SELECT u.id, u.title, u.full_name, u.email, u.role, u.facility_id, u.is_facility_admin,
                u.must_change_password,
                f.name AS facility_name, f.type AS facility_type,
                COALESCE(f.can_request_blood, FALSE) AS can_request_blood,
                COALESCE(f.can_report_donors, FALSE) AS can_report_donors,
                COALESCE(f.can_transplant,    FALSE) AS can_transplant,
                (f.id IS NULL OR (f.active AND f.verification_status = 'approved')) AS facility_ok
           FROM users u LEFT JOIN facilities f ON f.id = u.facility_id
          WHERE u.id = ? AND u.status = 'active'",
        [$_SESSION['user_id']]
    );
    return $user;
}

/** Check an email and password. Returns the user row, or null if wrong. */
function attempt_login(string $email, string $password): ?array
{
    start_session();
    $row = db_one("SELECT id, password_hash FROM users WHERE lower(email) = lower(?) AND status = 'active'", [trim($email)]);
    // Same work whether or not the email exists, so response time does not reveal accounts.
    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    if (!password_verify($password, $hash) || $row === null) {
        return null;
    }
    session_regenerate_id(true);                       // stops session fixation
    $_SESSION['user_id'] = (int)$row['id'];
    unset($_SESSION['csrf']);
    db_exec('UPDATE users SET last_login_at = now() WHERE id = ?', [$row['id']]);
    db_exec('UPDATE sessions SET user_id = ? WHERE id = ?', [$row['id'], session_id()]);
    return db_one('SELECT id, role, must_change_password FROM users WHERE id = ?', [$row['id']]);
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

/** Page needs a signed-in user. Also forces a password change when required. */
function require_login(bool $allow_password_change_page = false): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/login.php');
    }
    if ($user['must_change_password'] && !$allow_password_change_page) {
        redirect('/set-password.php');
    }
    return $user;
}

/**
 * The single permission check. Every protected page starts with it.
 * Allowed names: admin, center, hospital, donor, facility_admin,
 * can_request_blood, can_report_donors, can_transplant.
 */
function require_capability(string $capability): array
{
    $user = require_login();
    $ok = match ($capability) {
        'admin', 'center', 'hospital', 'donor' => $user['role'] === $capability,
        'facility_admin'    => (bool)$user['is_facility_admin'] && in_array($user['role'], ['center', 'hospital'], true),
        'can_request_blood' => $user['role'] === 'hospital' && $user['can_request_blood'],
        'can_report_donors' => $user['role'] === 'hospital' && $user['can_report_donors'],
        'can_transplant'    => $user['role'] === 'hospital' && $user['can_transplant'],
        default             => false,
    };
    // Facility staff of a deactivated or unapproved facility are blocked everywhere.
    if (!$user['facility_ok']) {
        $ok = false;
    }
    if (!$ok) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title>'
           . '<h1>You do not have access to this page.</h1><p><a href="' . e(home_for($user['role'])) . '">Back to your dashboard</a></p>';
        exit;
    }
    return $user;
}

/** Hidden form field value that proves a form came from our own page. */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every page that handles a POST form. */
function csrf_check(): void
{
    start_session();
    $sent = (string)($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(400);
        exit('The form expired. Go back, refresh the page and try again.');
    }
}

/** More than 4 wrong passwords for one email in 15 minutes locks that email for the rest of the window. */
function login_blocked(string $email): bool
{
    return (int)db_val(
        "SELECT COUNT(*) FROM audit_log
          WHERE action = 'login_failed' AND detail = ? AND created_at > now() - INTERVAL '15 minutes'",
        [mb_strtolower(trim($email))]
    ) >= 5;
}

function login_record_failure(string $email): void
{
    db_exec("INSERT INTO audit_log (action, entity, detail) VALUES ('login_failed', 'user', ?)",
            [substr(mb_strtolower(trim($email)), 0, 160)]);
}

/** The password rule in one place. Returns the problem as a sentence, or null if the password is fine. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'The password must be at least 8 characters and include a letter and a number.';
    }
    return null;
}

/** A random one-time password for new or reset accounts (no look-alike characters). */
function temp_password(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    do {
        $pw = '';
        for ($i = 0; $i < 10; $i++) {
            $pw .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (password_problem($pw) !== null);
    return $pw;
}
