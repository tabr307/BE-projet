<?php
/**
 * src/Model/SousReseau.php
 * Implémentation stricte WBS 1.1 - Table "sous_reseau"
 * * Cette classe gère la définition des segments logiques (sous-réseaux) 
 * au sein d'un scénario réseau.
 */
class SousReseau {
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
     * Récupère la liste des sous-réseaux configurés pour un scénario
     * @param int $sid ID du scénario parent
     * @return array Liste des sous-réseaux (id, nom, bloc_cidr)
     */
    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, bloc_cidr 
            FROM sous_reseau 
            WHERE scenario_id = :sid
        ");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistre un nouveau sous-réseau
     * @param int $sid ID du scénario
     * @param string $nom Label du sous-réseau (ex: "LAN Compta")
     * @param string $bloc_cidr Adresse réseau au format CIDR (ex: "10.0.0.0/24")
     * @return int L'identifiant unique généré
     */
    public function ajouter(int $sid, string $nom, string $bloc_cidr = '192.168.1.0/24'): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO sous_reseau (scenario_id, nom, bloc_cidr) 
            VALUES (:sid, :nom, :bloc_cidr) 
            RETURNING id
        ");
        $stmt->execute([
            ':sid' => $sid,
            ':nom' => trim($nom),
            ':bloc_cidr' => $bloc_cidr
        ]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Modifie le nom descriptif d'un sous-réseau
     * @param int $id ID du sous-réseau
     * @param string $nom Nouveau nom
     * @return bool Succès de l'opération
     */
    public function renommer(int $id, string $nom): bool {
        $stmt = $this->pdo->prepare("UPDATE sous_reseau SET nom = :nom WHERE id = :id");
        return $stmt->execute([':nom' => trim($nom), ':id' => $id]);
    }

    /**
     * Met à jour la plage d'adressage IP du sous-réseau
     * @param int $id ID du sous-réseau
     * @param string $bloc_cidr Nouveau bloc CIDR
     * @return bool Succès de l'opération
     */
    public function modifierCidr(int $id, string $bloc_cidr): bool {
        $stmt = $this->pdo->prepare("UPDATE sous_reseau SET bloc_cidr = :bloc_cidr WHERE id = :id");
        return $stmt->execute([':bloc_cidr' => $bloc_cidr, ':id' => $id]);
    }

    /**
     * Supprime un sous-réseau de la base de données
     * @param int $id ID du sous-réseau à supprimer
     * @return bool Succès de l'opération
     */
    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sous_reseau WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}