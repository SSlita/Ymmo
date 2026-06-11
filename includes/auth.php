<?php
// ============================================================
// includes/auth.php — Authentification & Contrôle d'accès
// ============================================================

require_once __DIR__ . '/../config/database.php';

class Auth
{
    // ---- Connexion ----

    /**
     * Connecte un utilisateur et initialise la session
     */
    public static function login(string $email, string $password): bool
    {
        $user = Database::fetchOne(
            'SELECT u.*, a.nom AS agence_nom FROM users u
             LEFT JOIN agences a ON u.agence_id = a.id
             WHERE u.email = :email AND u.actif = 1',
            [':email' => $email]
        );

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'         => $user['id'],
                'nom'        => $user['nom'],
                'prenom'     => $user['prenom'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'agence_id'  => $user['agence_id'],
                'agence_nom' => $user['agence_nom'],
            ];
            $_SESSION['login_time'] = time();
            return true;
        }
        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path']);
        }
        session_destroy();
    }

    // ---- Vérifications ----

    /** L'utilisateur est-il connecté ? */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    /** Récupère les données de l'utilisateur connecté */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Récupère un champ de l'utilisateur connecté */
    public static function get(string $key): mixed
    {
        return $_SESSION['user'][$key] ?? null;
    }

    /** L'utilisateur a-t-il ce rôle ? */
    public static function hasRole(string $role): bool
    {
        return self::get('role') === $role;
    }

    /** L'utilisateur est-il admin ? */
    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    /** L'utilisateur est-il agent ou admin ? */
    public static function isAgent(): bool
    {
        return in_array(self::get('role'), ['agent', 'admin']);
    }

    // ---- Redirections de protection ----

    /** Redirige vers login si non connecté */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    /** Redirige si pas agent/admin */
    public static function requireAgent(): void
    {
        self::requireLogin();
        if (!self::isAgent()) {
            header('Location: ' . APP_URL . '/?error=access_denied');
            exit;
        }
    }

    /** Redirige si pas admin */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . APP_URL . '/?error=access_denied');
            exit;
        }
    }

    // ---- CSRF ----

    /** Génère (ou récupère) le token CSRF */
    public static function csrfToken(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /** Valide le token CSRF soumis */
    public static function verifyCsrf(string $token): bool
    {
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /** Champ HTML caché CSRF */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::csrfToken() . '">';
    }
}
