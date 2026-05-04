<?php
// =============================================================================
// MODÈLE : Utilisateur
// Auteur : Étudiant
// Description : Opérations CRUD sur la table UTILISATEUR.
//               Gestion des comptes depuis le panel administrateur.
// =============================================================================

require_once __DIR__ . '/../noyau/BaseDeDonnees.php';
require_once __DIR__ . '/../noyau/GestionnaireAuth.php';

class Utilisateur {

    /** @var PDO Instance de connexion */
    private PDO $pdo;

    public function __construct() {
        $this->pdo = BaseDeDonnees::getInstance();
    }

    // =========================================================================
    // LECTURE
    // =========================================================================

    /**
     * Retourne la liste de tous les utilisateurs (sans les mots de passe).
     *
     * @return array Tableau de tous les utilisateurs
     */
    public function listerTous(): array {
        $requete = $this->pdo->query(
            'SELECT id_user, identifiant, role
             FROM UTILISATEUR
             ORDER BY identifiant ASC'
        );
        return $requete->fetchAll();
    }

    /**
     * Recherche un utilisateur par son identifiant (ID numérique).
     *
     * @param int $idUser Identifiant de l'utilisateur
     * @return array|null Les données de l'utilisateur ou null
     */
    public function trouverParId(int $idUser): ?array {
        $requete = $this->pdo->prepare(
            'SELECT id_user, identifiant, role
             FROM UTILISATEUR
             WHERE id_user = :id'
        );
        $requete->execute([':id' => $idUser]);
        $resultat = $requete->fetch();
        return $resultat ?: null;
    }

    // =========================================================================
    // CRÉATION
    // =========================================================================

    /**
     * Crée un nouvel utilisateur après validation des données.
     *
     * @param string $identifiant  Nom d'utilisateur unique
     * @param string $motDePasse   Mot de passe en clair (sera haché)
     * @param string $role         Rôle ('admin' ou 'membre')
     * @return array ['succes' => bool, 'message' => string]
     */
    public function creer(string $identifiant, string $motDePasse, string $role): array {
        // Validation du rôle
        if (!in_array($role, ['admin', 'membre'])) {
            return ['succes' => false, 'message' => "Rôle invalide : utilisez 'admin' ou 'membre'."];
        }

        // Validation de la longueur de l'identifiant
        if (strlen($identifiant) < 3 || strlen($identifiant) > 50) {
            return ['succes' => false, 'message' => "L'identifiant doit contenir entre 3 et 50 caractères."];
        }

        // Validation du mot de passe
        if (strlen($motDePasse) < 6) {
            return ['succes' => false, 'message' => "Le mot de passe doit contenir au moins 6 caractères."];
        }

        try {
            $requete = $this->pdo->prepare(
                'INSERT INTO UTILISATEUR (identifiant, mot_de_passe_hash, role)
                 VALUES (:identifiant, :hash, :role)'
            );
            $requete->execute([
                ':identifiant' => $identifiant,
                ':hash'        => GestionnaireAuth::hacherMotDePasse($motDePasse),
                ':role'        => $role,
            ]);

            return ['succes' => true, 'message' => "Utilisateur '$identifiant' créé avec succès."];

        } catch (PDOException $e) {
            // Code 23505 = violation de contrainte UNIQUE (identifiant déjà pris)
            if (str_contains($e->getMessage(), '23505')) {
                return ['succes' => false, 'message' => "Cet identifiant est déjà utilisé."];
            }
            error_log('[ERREUR Utilisateur::creer] ' . $e->getMessage());
            return ['succes' => false, 'message' => "Erreur lors de la création de l'utilisateur."];
        }
    }

    // =========================================================================
    // MODIFICATION
    // =========================================================================

    /**
     * Modifie le rôle d'un utilisateur.
     *
     * @param int    $idUser Identifiant de l'utilisateur
     * @param string $role   Nouveau rôle
     * @return array ['succes' => bool, 'message' => string]
     */
    public function modifierRole(int $idUser, string $role): array {
        if (!in_array($role, ['admin', 'membre'])) {
            return ['succes' => false, 'message' => "Rôle invalide."];
        }

        try {
            $requete = $this->pdo->prepare(
                'UPDATE UTILISATEUR SET role = :role WHERE id_user = :id'
            );
            $requete->execute([':role' => $role, ':id' => $idUser]);
            return ['succes' => true, 'message' => "Rôle mis à jour."];
        } catch (PDOException $e) {
            error_log('[ERREUR Utilisateur::modifierRole] ' . $e->getMessage());
            return ['succes' => false, 'message' => "Erreur lors de la modification du rôle."];
        }
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur.
     *
     * @param int    $idUser        Identifiant de l'utilisateur
     * @param string $nouveauMotDePasse Nouveau mot de passe en clair
     * @return array ['succes' => bool, 'message' => string]
     */
    public function reinitialiserMotDePasse(int $idUser, string $nouveauMotDePasse): array {
        if (strlen($nouveauMotDePasse) < 6) {
            return ['succes' => false, 'message' => "Le mot de passe doit contenir au moins 6 caractères."];
        }

        try {
            $requete = $this->pdo->prepare(
                'UPDATE UTILISATEUR SET mot_de_passe_hash = :hash WHERE id_user = :id'
            );
            $requete->execute([
                ':hash' => GestionnaireAuth::hacherMotDePasse($nouveauMotDePasse),
                ':id'   => $idUser,
            ]);
            return ['succes' => true, 'message' => "Mot de passe réinitialisé."];
        } catch (PDOException $e) {
            error_log('[ERREUR Utilisateur::reinitialiserMotDePasse] ' . $e->getMessage());
            return ['succes' => false, 'message' => "Erreur lors de la réinitialisation."];
        }
    }

    // =========================================================================
    // SUPPRESSION
    // =========================================================================

    /**
     * Supprime un utilisateur et toutes ses données (cascade).
     * Protection : impossible de supprimer le dernier administrateur.
     *
     * @param int $idUser Identifiant de l'utilisateur à supprimer
     * @return array ['succes' => bool, 'message' => string]
     */
    public function supprimer(int $idUser): array {
        // Vérification : est-ce le dernier admin ?
        $utilisateur = $this->trouverParId($idUser);
        if ($utilisateur && $utilisateur['role'] === 'admin') {
            $compteAdmins = $this->pdo->query(
                "SELECT COUNT(*) FROM UTILISATEUR WHERE role = 'admin'"
            )->fetchColumn();

            if ($compteAdmins <= 1) {
                return ['succes' => false, 'message' => "Impossible de supprimer le dernier administrateur."];
            }
        }

        try {
            $requete = $this->pdo->prepare(
                'DELETE FROM UTILISATEUR WHERE id_user = :id'
            );
            $requete->execute([':id' => $idUser]);
            return ['succes' => true, 'message' => "Utilisateur supprimé."];
        } catch (PDOException $e) {
            error_log('[ERREUR Utilisateur::supprimer] ' . $e->getMessage());
            return ['succes' => false, 'message' => "Erreur lors de la suppression."];
        }
    }
}
?>
