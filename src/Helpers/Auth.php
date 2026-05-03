<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

/**
 * Thin static facade over $_SESSION for authentication state.
 *
 * Stores only the user id in the session and lazily loads the bean on
 * demand. Caches the loaded bean for the duration of one request so
 * callers can hit Auth::user() repeatedly without extra DB hits.
 */
class Auth
{
    /** Per-request cache of the loaded user bean. */
    private static ?OODBBean $cachedUser = null;

    /** Mark a user as logged in by storing their id in the session. */
    public static function login(int $userId): void
    {
        // Regenerate the session id on login to prevent session fixation
        // — without this, an id that was set before authentication would
        // remain valid after, letting an attacker hijack a fresh session.
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        self::$cachedUser = null; // force reload on next user() call
    }

    /** Wipe all session data and the cookie, ending the login. */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            // Use the options-array form so the samesite attribute survives the
            // expiry write — the legacy positional form silently drops it.
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$cachedUser = null;
    }

    /** Return the current user's id from session, or null when logged out. */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Load and cache the current user bean. Returns null when no user is
     * logged in or when the stored id no longer maps to a real row.
     */
    public static function user(): ?OODBBean
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = self::userId();
        if ($id === null) {
            return null;
        }

        $bean = R::load('users', $id);
        if (!BeanHelper::isValidBean($bean)) {
            return null; // session pointed at a deleted user
        }

        self::$cachedUser = $bean;
        return $bean;
    }

    /** Convenience predicate used by templates and controllers. */
    public static function isLoggedIn(): bool
    {
        return self::user() !== null;
    }

    /** True only if a user is logged in AND has the admin role. */
    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (string) $u->role === 'admin';
    }

    /**
     * Guard helper: returns a 302-to-/login response if not logged in,
     * otherwise null so the caller can continue.
     */
    public static function requireLogin(Response $response, string $basePath): ?Response
    {
        if (self::isLoggedIn()) {
            return null;
        }
        return $response
            ->withHeader('Location', $basePath . '/login')
            ->withStatus(302);
    }

    /**
     * Guard helper: returns a 302 response if not an admin, otherwise null.
     * Logged-out users go to /login; logged-in non-admins go to /.
     */
    public static function requireAdmin(Response $response, string $basePath): ?Response
    {
        if (self::isAdmin()) {
            return null;
        }
        $target = self::isLoggedIn() ? '/' : '/login';
        return $response
            ->withHeader('Location', $basePath . $target)
            ->withStatus(302);
    }
}
