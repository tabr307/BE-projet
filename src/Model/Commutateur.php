<?php

/**
 * Classe Commutateur
 * Gère les opérations CRUD (Création, Lecture, Mise à jour, Suppression) 
 * pour les commutateurs (switchs) dans la base de données.
 */
class Commutateur {
    // Instance de connexion à la base de données
    private PDO $pdo;

    /**
     * Constructeur : Injecte la connexion PDO
     * @param PDO $pdo Connexion active à la base de données
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les commutateurs associés à un scénario spécifique
     * @param int $sid L'identifiant du scénario
     * @return array Liste des commutateurs trouvés
     */
    public function listerParScenario(int $sid): array {
        // Préparation de la requête pour éviter les injections SQL
        $stmt = $this->pdo->prepare("SELECT id, nom, pos_x, pos_y FROM switch WHERE scenario_id = ?");
        $stmt->execute([$sid]);
        // Retourne les résultats sous forme de tableau associatif
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un nouveau commutateur en base de données
     * @param int $sid ID du scénario parent
     * @param string $nom Nom du commutateur
     * @param int $x Position horizontale (par défaut 150)
     * @param int $y Position verticale (par défaut 150)
     * @return int L'ID du nouvel enregistrement créé
     */
    public function ajouter(int $sid, string $nom, $x = 150, $y = 150): int {
        // Utilisation de RETURNING id (spécifique à PostgreSQL) pour récupérer l'ID immédiatement
        $stmt = $this->pdo->prepare("INSERT INTO switch (scenario_id, nom, pos_x, pos_y) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$sid, $nom, $x, $y]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Modifie le nom d'un commutateur existant
     * @param int $id ID du commutateur
     * @param string $nom Nouveau nom
     * @return bool True en cas de succès, False en cas d'échec
     */
    public function renommer(int $id, string $nom): bool {
        // On nettoie le nom avec trim() pour enlever les espaces inutiles
        return $this->pdo->prepare("UPDATE switch SET nom = ? WHERE id = ?")
                         ->execute([trim($nom), $id]);
    }

    /**
     * Met à jour les coordonnées graphiques d'un commutateur
     * @param int $id ID du commutateur
     * @param int $x Nouvelle position X
     * @param int $y Nouvelle position Y
     * @return bool Succès de l'opération
     */
    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE switch SET pos_x = ?, pos_y = ? WHERE id = ?")
                         ->execute([$x, $y, $id]);
    }

    /**
     * Supprime un commutateur de la base de données
     * @param int $id ID du commutateur à supprimer
     * @return bool Succès de l'opération
     */
    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM switch WHERE id = ?")
                         ->execute([$id]);
    }
}