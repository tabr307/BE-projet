<?php

/**
 * Classe Hote
 * Gère les opérations CRUD et la configuration réseau des hôtes (ordinateurs/terminaux)
 * dans la base de données.
 */
class Hote {
    // Instance de connexion PDO
    private PDO $pdo;

    /**
     * Constructeur : Initialise la connexion à la base de données
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les hôtes appartenant à un scénario
     * @param int $sid ID du scénario
     * @return array Liste des hôtes avec leurs détails techniques et positions
     */
    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id, nom, nom_interface, adresse_ip, passerelle_ip, pos_x, pos_y, sous_reseau_id FROM hote WHERE scenario_id = ?");
        $stmt->execute([$sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouvel hôte avec des coordonnées par défaut
     * @param int $sid ID du scénario parent
     * @param string $nom Nom de l'hôte
     * @param int $x Position horizontale initiale
     * @param int $y Position verticale initiale
     * @return int Identifiant de l'hôte créé
     */
    public function ajouter(int $sid, string $nom, $x = 200, $y = 200): int {
        // Note : Les paramètres réseau sont laissés à NULL lors de la création initiale
        $stmt = $this->pdo->prepare("INSERT INTO hote (scenario_id, nom, pos_x, pos_y) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$sid, $nom, $x, $y]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Modifie le nom d'affichage de l'hôte
     * @param int $id ID de l'hôte
     * @param string $nom Nouveau nom
     * @return bool Succès de l'opération
     */
    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE hote SET nom = ? WHERE id = ?")->execute([trim($nom), $id]);
    }

    /**
     * Met à jour l'emplacement de l'hôte sur l'interface graphique
     * @param int $id ID de l'hôte
     * @param int $x Nouvelle coordonnée X
     * @param int $y Nouvelle coordonnée Y
     * @return bool Succès de l'opération
     */
    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE hote SET pos_x = ?, pos_y = ? WHERE id = ?")->execute([$x, $y, $id]);
    }

    /**
     * Configure les paramètres réseaux avancés de l'hôte
     * @param int $id ID de l'hôte
     * @param string $ip Adresse IP (ex: 192.168.1.10)
     * @param string $passerelle IP de la passerelle par défaut
     * @param string $nom_interface Nom de la carte (ex: eth0)
     * @param int|null $sous_reseau_id ID du sous-réseau associé
     * @return bool Succès de l'opération
     */
    public function configurerReseau(int $id, string $ip, string $passerelle, string $nom_interface, ?int $sous_reseau_id): bool {
        $sql = "UPDATE hote SET adresse_ip = ?, passerelle_ip = ?, nom_interface = ?, sous_reseau_id = ? WHERE id = ?";
        return $this->pdo->prepare($sql)->execute([$ip, $passerelle, $nom_interface, $sous_reseau_id, $id]);
    }

    /**
     * Supprime un hôte de la base de données
     * @param int $id ID de l'hôte à supprimer
     * @return bool Succès de l'opération
     */
    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM hote WHERE id = ?")->execute([$id]);
    }
}