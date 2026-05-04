<?php
// =============================================================================
// CLASSE : GestionnaireAuth
// Auteur : Étudiant
// Description : Gestion des sessions utilisateur et de l'authentification.
//               Utilise SHA-256 pour le hachage des mots de passe.
// =============================================================================

require_once __DIR__ . '/BaseDeDonnees.php';
require_once __DIR__ . '/../../configuration.php';

class GestionnaireAuth {

    /**
     * Tente d'authentifier un utilisateur avec ses identifiants.
     *
     * @param string $identifiant Nom d'utilisateur saisi
     * @param string $motDePasse  Mot de passe en clair saisi
     * @return bool True si l'authentification réussit, false sinon
     */
    public static function connecter(string $identifiant, string $motDePasse): bool {
        $pdo = BaseDeDonnees::getInstance();

        // Hachage du mot de passe saisi pour comparaison avec la BDD
        $hashSaisi = hash('sha256', $motDePasse);

        // Requête préparée : protection contre les injections SQL
        $requete = $pdo->prepare(
            'SELECT id_user, identifiant, role
             FROM UTILISATEUR
             WHERE identifiant = :identifiant
               AND mot_de_passe_hash = :hash'
        );

        $requete->execute([
            ':identifiant' => $identifiant,
            ':hash'        => $hashSaisi,
        ]);

        $utilisateur = $requete->fetch();

        // Si l'utilisateur est trouvé, on ouvre la session
        if ($utilisateur) {
            self::demarrerSession();
            $_SESSION['id_user']      = $utilisateur['id_user'];
            $_SESSION['identifiant']  = $utilisateur['identifiant'];
            $_SESSION['role']         = $utilisateur['role'];
            $_SESSION['connecte_le']  = time();
            return true;
        }

        return false;
    }

    /**
     * Déconnecte l'utilisateur en détruisant sa session.
     */
    public static function deconnecter(): void {
        self::demarrerSession();
        $_SESSION = [];

        // Supprime le cookie de session côté navigateur
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est connecté et si la session n'a pas expiré.
     *
     * @return bool True si la session est valide
     */
    public static function estConnecte(): bool {
        self::demarrerSession();

        if (empty($_SESSION['id_user'])) {
            return false;
        }

        // Vérification de l'expiration de la session
        if (isset($_SESSION['connecte_le'])) {
            if ((time() - $_SESSION['connecte_le']) > SESSION_DUREE) {
                self::deconnecter();
                return false;
            }
            // Renouvellement de l'horodatage pour prolonger la session active
            $_SESSION['connecte_le'] = time();
        }

        return true;
    }

    /**
     * Redirige vers la page de connexion si l'utilisateur n'est pas authentifié.
     * À appeler en haut de chaque page protégée.
     */
    public static function exigerConnexion(): void {
        if (!self::estConnecte()) {
            header('Location: /simulateur-reseau-ip/index.php?vue=connexion');
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur connecté possède le rôle administrateur.
     *
     * @return bool True si l'utilisateur est admin
     */
    public static function estAdmin(): bool {
        self::demarrerSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Retourne l'identifiant de l'utilisateur connecté.
     *
     * @return int|null L'id_user ou null si non connecté
     */
    public static function getIdUtilisateur(): ?int {
        self::demarrerSession();
        return $_SESSION['id_user'] ?? null;
    }

    /**
     * Retourne l'identifiant textuel de l'utilisateur connecté.
     *
     * @return string L'identifiant ou une chaîne vide
     */
    public static function getIdentifiant(): string {
        self::demarrerSession();
        return $_SESSION['identifiant'] ?? '';
    }

    /**
     * Retourne le rôle de l'utilisateur connecté.
     *
     * @return string Le rôle ou une chaîne vide
     */
    public static function getRole(): string {
        self::demarrerSession();
        return $_SESSION['role'] ?? '';
    }

    /**
     * Démarre la session PHP si elle n'est pas encore active.
     * Évite les appels multiples à session_start().
     */
    private static function demarrerSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Hache un mot de passe en SHA-256.
     * Utile pour la création de comptes depuis l'interface admin.
     *
     * @param string $motDePasse Mot de passe en clair
     * @return string Hash SHA-256 hexadécimal (64 caractères)
     */
    public static function hacherMotDePasse(string $motDePasse): string {
        return hash('sha256', $motDePasse);
    }
}
?>
