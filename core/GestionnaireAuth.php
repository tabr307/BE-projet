<?php
/**
 * core/GestionnaireAuth.php
 * Logique métier pour la sécurité, l'authentification et les sessions.
 * Conforme à la nouvelle arborescence et au SGBD WBS 1.1.
 */
require_once __DIR__ . '/../src/Model/Utilisateur.php';

class GestionnaireAuth {

    /**
     * Initialise la session avec des paramètres de sécurité stricts.
     */
    public static function initialiserSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 3600,
                'path'     => '/',
                'domain'   => '', 
                'secure'   => isset($_SERVER['HTTPS']), // True en production HTTPS
                'httponly' => true,                     // Bloque l'accès au cookie via JS
                'samesite' => 'Strict'                  // Bloque l'envoi cross-origin
            ]);
            session_start();
        }
    }

    /**
     * Valide les identifiants et initialise une session sécurisée.
     */
    public static function login(string $username, string $password, PDO $pdo): bool {
        self::initialiserSession();
        
        $modele = new Utilisateur($pdo);
        $user = $modele->trouverParNom($username);

        // Vérification d'existence et validation du hachage cryptographique
        if ($user && hash('sha256', $password) === $user['mot_de_passe_hash']) {
            
            // Prévention stricte des attaques de fixation de session
            session_regenerate_id(true);

            // Alignement sur le nouveau MLD (colonne 'id')
            $_SESSION['utilisateur_id']  = $user['id'];
            $_SESSION['utilisateur_nom'] = $user['identifiant'];
            $_SESSION['role']            = $user['role'];
            
            return true;
        }

        return false;
    }

    /**
     * Termine la session utilisateur avec destruction totale.
     */
    public static function logout(): void {
        self::initialiserSession();
        
        $_SESSION = []; 
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }
}
?>