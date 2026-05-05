<?php
/**
 * backend/modeles/Scenario.php
 * Modèle parfaitement aligné sur le nouveau schéma SQL (MLD propre).
 */
class Scenario {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // --- GESTION DU TABLEAU DE BORD ---
    public function lireScenariosParUtilisateur(int $utilisateurId): array {
        // SCENARIO (id_scenario, id_user, nom_scenario, description)
        $stmt = $this->pdo->prepare("SELECT id_scenario AS id, nom_scenario AS nom FROM SCENARIO WHERE id_user = :uid ORDER BY id_scenario DESC");
        $stmt->execute([':uid' => $utilisateurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenirScenario(int $id, int $utilisateurId): ?array {
        $stmt = $this->pdo->prepare("SELECT id_scenario AS id, nom_scenario AS nom FROM SCENARIO WHERE id_scenario = :id AND id_user = :uid");
        $stmt->execute([':id' => $id, ':uid' => $utilisateurId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerScenario(int $utilisateurId, string $nom): int {
        $stmt = $this->pdo->prepare("INSERT INTO SCENARIO (id_user, nom_scenario) VALUES (:uid, :nom) RETURNING id_scenario");
        $stmt->execute([':uid' => $utilisateurId, ':nom' => trim($nom)]);
        return (int) $stmt->fetchColumn();
    }

    public function supprimerScenario(int $id, int $utilisateurId): bool {
        return $this->pdo->prepare("DELETE FROM SCENARIO WHERE id_scenario = :id AND id_user = :uid")->execute([':id' => $id, ':uid' => $utilisateurId]);
    }

    // --- GESTION DES SOUS-RÉSEAUX (Table RESEAU) ---
    public function lireSousReseaux(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id_reseau AS id, label AS nom, pos_x, pos_y FROM RESEAU WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerSousReseau(int $sid, string $nom, string $cidr): int {
        $parts = explode('/', $cidr);
        $ip = $parts[0] ?? '10.0.0.0';
        $masque = isset($parts[1]) ? (int)$parts[1] : 24;

        $x = rand(100, 350); $y = rand(100, 350); // <-- NOUVEAU
        $stmt = $this->pdo->prepare("INSERT INTO RESEAU (id_scenario, adresse_reseau, masque, label, pos_x, pos_y) VALUES (:sid, :ip, :masque, :nom, :x, :y) RETURNING id_reseau");
        $stmt->execute([':sid' => $sid, ':ip' => $ip, ':masque' => $masque, ':nom' => $nom, ':x' => $x, ':y' => $y]);
        return (int)$stmt->fetchColumn();
    }

    // --- LECTURE ET CRÉATION DES ÉQUIPEMENTS ---
    public function lireRouteurs(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id_routeur AS id, nom, pos_x, pos_y FROM Routeur WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lireSwitchsParScenario(int $sid): array {
        // Le switch est maintenant lié directement au scénario
        $stmt = $this->pdo->prepare("SELECT id_switch AS id, nom, pos_x, pos_y FROM SWITCH WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lireHotesParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id_hote AS id, nom, pos_x, pos_y FROM HOTE WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerRouteur(int $sid, string $nom): int {
        $x = rand(50, 200); $y = rand(50, 200); // <-- NOUVEAU : Position aléatoire
        $stmt = $this->pdo->prepare("INSERT INTO Routeur (id_scenario, nom, pos_x, pos_y) VALUES (:sid, :nom, :x, :y) RETURNING id_routeur");
        $stmt->execute([':sid' => $sid, ':nom' => $nom, ':x' => $x, ':y' => $y]);
        return (int)$stmt->fetchColumn();
    }

    public function creerSwitch(int $srid, string $nom): int {
        $sid = $this->pdo->query("SELECT id_scenario FROM RESEAU WHERE id_reseau = " . (int)$srid)->fetchColumn();
        if (!$sid) return 0;

        $x = rand(150, 300); $y = rand(150, 300); // <-- NOUVEAU
        $stmt = $this->pdo->prepare("INSERT INTO SWITCH (id_scenario, nom, pos_x, pos_y) VALUES (:sid, :nom, :x, :y) RETURNING id_switch");
        $stmt->execute([':sid' => $sid, ':nom' => $nom, ':x' => $x, ':y' => $y]);
        return (int)$stmt->fetchColumn();
    }

    public function creerHote(int $srid, string $nom, string $ip): int {
        $sid = $this->pdo->query("SELECT id_scenario FROM RESEAU WHERE id_reseau = " . (int)$srid)->fetchColumn();
        if (!$sid) return 0;

        $x = rand(250, 400); $y = rand(250, 400); // <-- NOUVEAU
        $stmt = $this->pdo->prepare("INSERT INTO HOTE (id_scenario, id_reseau, nom, adresse_ip, pos_x, pos_y) VALUES (:sid, :srid, :nom, :ip, :x, :y) RETURNING id_hote");
        $stmt->execute([':sid' => $sid, ':srid' => $srid, ':nom' => $nom, ':ip' => $ip, ':x' => $x, ':y' => $y]);
        return (int)$stmt->fetchColumn();
    }

    // --- POSITIONS, RENOMMAGE ET SUPPRESSION MULTI-TYPES ---
    
    public function renommerEquipement(string $type, int $id, string $nouveauNom): bool {
        // Routage dynamique vers les bonnes tables et bonnes colonnes
        $tables = [
            'routeurs' => ['table' => 'Routeur', 'id' => 'id_routeur', 'col' => 'nom'],
            'switchs'  => ['table' => 'SWITCH', 'id' => 'id_switch', 'col' => 'nom'],
            'hotes'    => ['table' => 'HOTE', 'id' => 'id_hote', 'col' => 'nom'],
            'reseaux'  => ['table' => 'RESEAU', 'id' => 'id_reseau', 'col' => 'label']
        ];
        if (!isset($tables[$type])) return false;
        
        $t = $tables[$type];
        $sql = "UPDATE {$t['table']} SET {$t['col']} = :nom WHERE {$t['id']} = :id";
        return $this->pdo->prepare($sql)->execute([':nom' => trim($nouveauNom), ':id' => $id]);
    }

    public function mettreAJourPositions(string $type, int $id, int $x, int $y): bool {
        $tables = [
            'routeurs'    => ['table' => 'Routeur', 'id' => 'id_routeur'],
            'switchs'     => ['table' => 'SWITCH', 'id' => 'id_switch'],
            'hotes'       => ['table' => 'HOTE', 'id' => 'id_hote'],
            'sous_reseau' => ['table' => 'RESEAU', 'id' => 'id_reseau']
        ];
        if (!isset($tables[$type])) return false;
        
        $t = $tables[$type];
        return $this->pdo->prepare("UPDATE {$t['table']} SET pos_x = :x, pos_y = :y WHERE {$t['id']} = :id")->execute([':x' => $x, ':y' => $y, ':id' => $id]);
    }

    public function supprimerEquipement(string $tableKey, int $id): bool {
        $tables = [
            'routeur'     => ['table' => 'Routeur', 'id' => 'id_routeur'],
            'switch'      => ['table' => 'SWITCH', 'id' => 'id_switch'],
            'hote'        => ['table' => 'HOTE', 'id' => 'id_hote'],
            'sous_reseau' => ['table' => 'RESEAU', 'id' => 'id_reseau']
        ];
        if (!isset($tables[$tableKey])) return false;
        
        $t = $tables[$tableKey];
        return $this->pdo->prepare("DELETE FROM {$t['table']} WHERE {$t['id']} = ?")->execute([$id]);
    }

    // --- CÂBLAGE ET INTERFACES ---
    public function lireLiaisonsHoteSwitch(int $sid): array {
        $stmt = $this->pdo->prepare("
            SELECT chs.id_hote AS hote_id, chs.id_switch AS switch_id 
            FROM CABLER_HOTE_SWITCH chs 
            JOIN HOTE h ON chs.id_hote = h.id_hote 
            WHERE h.id_scenario = :sid
        ");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lireLiaisonsInterfaceSwitch(int $sid): array {
        $sql = "
            SELECT cis.id_interface AS interface_id, cis.id_switch AS switch_id, ir.id_routeur AS routeur_id 
            FROM CABLER_INTERFACE_SWITCH cis 
            JOIN INTERFACE ir ON cis.id_interface = ir.id_interface 
            JOIN Routeur r ON ir.id_routeur = r.id_routeur 
            WHERE r.id_scenario = :sid
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerLiaisonHoteSwitch(int $hId, int $sId): bool {
        $sql = "INSERT INTO CABLER_HOTE_SWITCH (id_hote, id_switch) VALUES (?, ?) ON CONFLICT (id_switch, id_hote) DO NOTHING";
        return $this->pdo->prepare($sql)->execute([$hId, $sId]);
    }

    public function supprimerLiaisonHoteSwitch(int $hId, int $sId): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_HOTE_SWITCH WHERE id_hote = ? AND id_switch = ?")->execute([$hId, $sId]);
    }

    public function obtenirInterfaceLibre(int $rid): int {
        $stmt = $this->pdo->prepare("SELECT id_interface FROM INTERFACE WHERE id_routeur = :rid AND id_interface NOT IN (SELECT id_interface FROM CABLER_INTERFACE_SWITCH) LIMIT 1");
        $stmt->execute([':rid' => $rid]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $sql = "INSERT INTO INTERFACE (id_routeur, adresse_ip, masque, nom) VALUES (:rid, :ip, 24, :nom) RETURNING id_interface";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':rid' => $rid, ':ip' => "10.0." . rand(1, 250) . ".1", ':nom' => "eth" . rand(0, 99)]);
            $id = $stmt->fetchColumn();
        }
        return (int)$id;
    }

    public function creerLiaisonInterfaceSwitch(int $iId, int $sId): bool {
        $sql = "INSERT INTO CABLER_INTERFACE_SWITCH (id_interface, id_switch) VALUES (?, ?) ON CONFLICT (id_interface, id_switch) DO NOTHING";
        return $this->pdo->prepare($sql)->execute([$iId, $sId]);
    }

    public function supprimerLiaisonInterfaceSwitch(int $iId, int $sId): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_INTERFACE_SWITCH WHERE id_interface = ? AND id_switch = ?")->execute([$iId, $sId]);
    }
    // --- GESTION DES INTERFACES DE ROUTEURS ---
    
    public function lireInterfacesRouteur(int $rid): array {
        $stmt = $this->pdo->prepare("SELECT id_interface AS id, nom, adresse_ip, masque FROM INTERFACE WHERE id_routeur = :rid ORDER BY nom ASC");
        $stmt->execute([':rid' => $rid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerInterfaceRouteur(int $rid, string $ip, int $masque, string $nom): int {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO INTERFACE (id_routeur, adresse_ip, masque, nom) VALUES (:rid, :ip, :masque, :nom) RETURNING id_interface");
            $stmt->execute([':rid' => $rid, ':ip' => $ip, ':masque' => $masque, ':nom' => $nom]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            // Renvoie 0 si l'IP ou le nom provoque une erreur (ex: violation de la contrainte UNIQUE)
            return 0; 
        }
    }

    public function supprimerInterfaceRouteur(int $idInterface): bool {
        return $this->pdo->prepare("DELETE FROM INTERFACE WHERE id_interface = ?")->execute([$idInterface]);
    }
}