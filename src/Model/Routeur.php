<?php
/**
 * Classe Routeur
 * Gère le cycle de vie des routeurs au sein d'un scénario réseau 
 * (CRUD et positionnement graphique).
 */
class Routeur {
    // Instance de connexion à la base de données
    private PDO $pdo;

    /**
     * Constructeur : Initialise la connexion PDO
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste des routeurs associés à un scénario
     * @param int $sid ID du scénario
     * @return array Liste des routeurs avec leurs coordonnées
     */
    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id, nom, pos_x, pos_y FROM routeur WHERE scenario_id = ?");
        $stmt->execute([$sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouveau routeur dans la base de données
     * @param int $sid ID du scénario parent
     * @param string $nom Nom du routeur (ex: "R1")
     * @param int $x Position X sur le canevas
     * @param int $y Position Y sur le canevas
     * @return int L'identifiant généré pour ce routeur
     */
    public function ajouter(int $sid, string $nom, $x = 100, $y = 100): int {
        // Utilisation de RETURNING id pour récupérer l'ID immédiatement (PostgreSQL)
        $stmt = $this->pdo->prepare("INSERT INTO routeur (scenario_id, nom, pos_x, pos_y) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$sid, $nom, $x, $y]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Modifie le nom d'un routeur
     * @param int $id ID du routeur
     * @param string $nom Nouveau nom
     * @return bool Succès ou échec
     */
    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE routeur SET nom = ? WHERE id = ?")->execute([trim($nom), $id]);
    }

    /**
     * Met à jour les coordonnées de l'icône du routeur sur l'interface
     * @param int $id ID du routeur
     * @param int $x Nouvelle position horizontale
     * @param int $y Nouvelle position verticale
     * @return bool Succès ou échec
     */
    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE routeur SET pos_x = ?, pos_y = ? WHERE id = ?")->execute([$x, $y, $id]);
    }

    /**
     * Supprime un routeur de la base de données
     * Note : Les contraintes SQL (ON DELETE CASCADE) doivent gérer la suppression des interfaces liées.
     * @param int $id ID du routeur à supprimer
     * @return bool Succès ou échec
     */
    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM routeur WHERE id = ?")->execute([$id]);
    }
}