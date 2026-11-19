<?php

namespace App\Core;

use App\Models\Utilisateur;

/**
 * Authentification par session + contrôle des droits par rôle (Règle 7).
 */
class Auth
{
    /** Rôles autorisés à agir en tant qu'encadrant (Fonctionnalité transverse). */
    public const ROLE_ADMIN      = 'admin';
    public const ROLE_ENCADRANT  = 'encadrant';
    public const ROLE_STAGIAIRE  = 'stagiaire';
    public const ROLE_DIRECTION  = 'direction';

    private const REMEMBER_COOKIE = 'remember_stage';
    private const REMEMBER_DAYS = 30;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $model = new Utilisateur();
        $user = $model->findByEmail($email);
        if (!$user || (int)$user['actif'] !== 1) {
            return false;
        }
        if (!password_verify($password, $user['mot_de_passe'])) {
            return false;
        }
        self::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'nom'   => $user['nom'],
            'prenom'=> $user['prenom'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        return true;
    }

    public static function logout(): void
    {
        self::start();
        self::forgetRememberToken();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * "Se souvenir de moi" : émet un cookie persistant (sélecteur + validateur, RFC-style)
     * qui permet une reconnexion automatique sans stocker le mot de passe ni un jeton en clair.
     */
    public static function remember(int $utilisateurId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * 86400);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO remember_token (utilisateur_id, selector, validator_hash, expires_at) VALUES (?,?,?,?)"
        );
        $stmt->execute([$utilisateurId, $selector, hash('sha256', $validator), $expiresAt]);

        self::setRememberCookie($selector . ':' . $validator, time() + self::REMEMBER_DAYS * 86400);
    }

    /**
     * Si aucune session active mais qu'un cookie "se souvenir de moi" valide est présent,
     * reconnecte automatiquement l'utilisateur et fait tourner le jeton (sécurité).
     */
    public static function tryRememberedLogin(): void
    {
        self::start();
        if (self::check() || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        $parts = explode(':', (string)$_COOKIE[self::REMEMBER_COOKIE], 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            self::clearRememberCookie();
            return;
        }
        [$selector, $validator] = $parts;

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM remember_token WHERE selector = ?");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row || strtotime($row['expires_at']) < time() || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            if ($row) {
                $db->prepare("DELETE FROM remember_token WHERE id = ?")->execute([$row['id']]);
            }
            self::clearRememberCookie();
            return;
        }

        $user = (new Utilisateur())->find((int)$row['utilisateur_id']);
        $db->prepare("DELETE FROM remember_token WHERE id = ?")->execute([$row['id']]);
        if (!$user || (int)$user['actif'] !== 1) {
            self::clearRememberCookie();
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'nom'   => $user['nom'],
            'prenom'=> $user['prenom'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        // Rotation du jeton à chaque utilisation (limite le risque en cas de vol du cookie).
        self::remember((int)$user['id']);
    }

    /** Supprime le jeton "se souvenir de moi" en base et le cookie associé (déconnexion). */
    public static function forgetRememberToken(): void
    {
        if (!empty($_COOKIE[self::REMEMBER_COOKIE])) {
            $parts = explode(':', (string)$_COOKIE[self::REMEMBER_COOKIE], 2);
            if (count($parts) === 2 && $parts[0] !== '') {
                Database::getInstance()->prepare("DELETE FROM remember_token WHERE selector = ?")->execute([$parts[0]]);
            }
        }
        self::clearRememberCookie();
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::REMEMBER_COOKIE] = $value;
    }

    private static function clearRememberCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u ? $u['role'] : null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /** Vrai si la requête vient d'un appel AJAX (fetch du module DataTable). */
    private static function estRequeteAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Redirige vers le login si non connecté (ou renvoie une erreur JSON pour une requête AJAX,
     * afin d'éviter qu'un fetch() ne reste bloqué sur "Chargement..." en cas de session expirée).
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (self::estRequeteAjax()) {
                Response::json(['error' => 'Session expirée. Merci de vous reconnecter.'], 401);
            }
            Response::redirect('index.php?action=login');
        }
    }

    /**
     * N'autorise que les rôles listés. Sinon -> page 403 (ou JSON 403 pour une requête AJAX).
     */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!self::is(...$roles)) {
            if (self::estRequeteAjax()) {
                Response::json(['error' => 'Accès refusé pour votre rôle.'], 403);
            }
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            exit;
        }
    }
}
