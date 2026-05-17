<?php
// core/GestionnaireAuth.php

require_once __DIR__ . '/../src/Model/Utilisateur.php'; // Chargement du modèle Utilisateur pour les requêtes BDD

class GestionnaireAuth {

    private ?PDO $pdo; // Connexion PDO, nullable (peut être null si non fournie)

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo; // Injection de dépendance : la connexion BDD est passée depuis l'extérieur
    }

    /**
     * Vérifie si un utilisateur est actuellement connecté.
     * Se base sur la présence d'un ID en session.
     */
    public function estConnecte(): bool {
        // Démarre la session si elle n'est pas encore active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Retourne true si la clé 'utilisateur_id' existe et n'est pas vide
        return isset($_SESSION['utilisateur_id']) && !empty($_SESSION['utilisateur_id']);
    }

    /**
     * Configure et démarre une session avec des paramètres de sécurité stricts.
     * Doit être appelée avant toute écriture en session.
     */
    public static function initialiserSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 3600,                      // Cookie expiré après 1 heure d'inactivité
                'path'     => '/',                       // Cookie valable sur tout le site
                'domain'   => '',                        // Domaine courant par défaut
                'secure'   => isset($_SERVER['HTTPS']),  // Cookie transmis uniquement en HTTPS
                'httponly' => true,                      // Cookie inaccessible via JavaScript 
                'samesite' => 'Strict'                   // Cookie non envoyé depuis un autre domaine 
            ]);
            session_start(); // Démarre la session avec les options ci-dessus
        }
    }

    // Authentifie un utilisateur et crée sa session.
    public static function login(string $username, string $password, PDO $pdo): bool {
        self::initialiserSession(); // Prépare la session sécurisée avant toute écriture

        $modele = new Utilisateur($pdo);
        $user   = $modele->trouverParNom($username); // Recherche l'utilisateur en BDD par son identifiant

        // Vérifie que l'utilisateur existe ET que le hash du mot de passe fourni correspond au hash stocké
        if ($user && hash('sha256', $password) === $user['mot_de_passe_hash']) {

            // Régénère l'ID de session pour prévenir les attaques de fixation de session
            session_regenerate_id(true);

            // Stocke les informations essentielles de l'utilisateur en session
            $_SESSION['utilisateur_id']  = $user['id'];         // Identifiant unique (clé primaire)
            $_SESSION['utilisateur_nom'] = $user['identifiant']; // Nom affiché
            $_SESSION['role']            = $user['role'];        // Rôle pour la gestion des permissions

            return true; // Connexion réussie
        }

        return false; // Identifiants invalides
    }

    /**
     * Déconnecte l'utilisateur et détruit complètement sa session.
     */
    public static function logout(): void {
        self::initialiserSession(); // S'assure que la session est active avant de la manipuler

        $_SESSION = []; // Vide toutes les variables de session en mémoire

        // Si les cookies de session sont activés, supprime le cookie côté client
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params(); // Récupère les paramètres actuels du cookie
            setcookie(
                session_name(), // Nom du cookie de session (ex: PHPSESSID)
                '',             // Valeur vide
                time() - 42000, // Date d'expiration dans le passé = suppression immédiate
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy(); // Détruit le fichier de session côté serveur
    }
}
?>