<?php
/**
 * backend/noyau/GestionnaireAuth.php
 * Logique métier pour la sécurité, l'authentification et les sessions.
 */
require_once __DIR__ . '/../modeles/Utilisateur.php';

class GestionnaireAuth {
    /**
     * Valide les identifiants et initialise une session sécurisée.
     * 
     * @param string $username Le pseudo saisi par l'utilisateur.
     * @param string $password Le mot de passe en clair.
     * @param PDO $pdo L'instance de connexion à la base de données.
     * @return bool True si la connexion est établie, sinon False.
     */
    public static function login(string $username, string $password, PDO $pdo): bool {
        $modele = new Utilisateur($pdo);
        $user = $modele->trouverParNom($username);

        // 1. Vérification de l'existence de l'utilisateur et du hash SHA-256
        // FIX : On utilise 'mot_de_passe_hash' selon le nouveau MLD
        if ($user && hash('sha256', $password) === $user['mot_de_passe_hash']) {
            
            // 2. Sécurité : Régénération de l'ID de session pour éviter la fixation de session
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // 3. Stockage des informations essentielles en session
            // FIX : Alignement sur 'id_user' et 'identifiant' de la BDD
            $_SESSION['utilisateur_id'] = $user['id_user'];
            $_SESSION['utilisateur_nom'] = $user['identifiant']; // Utilisé dans editeur.php
            $_SESSION['role'] = $user['role'];
            
            return true;
        }

        // Échec de l'authentification
        return false;
    }

    /**
     * Termine proprement la session utilisateur.
     */
    public static function logout(): void {
        $_SESSION = []; // Vide les variables de session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}