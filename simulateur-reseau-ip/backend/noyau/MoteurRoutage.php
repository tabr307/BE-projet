<?php
// =============================================================================
// CLASSE : MoteurRoutage
// Auteur : Étudiant
// Description : Cœur du simulateur. Implémente l'algorithme de routage IP
//               hop-by-hop avec LPM, gestion du TTL et calcul du checksum.
//               S'exécute côté Backend (serveur) conformément aux specs.
// =============================================================================

require_once __DIR__ . '/BaseDeDonnees.php';
require_once __DIR__ . '/../../configuration.php';

class MoteurRoutage {

    /** @var PDO Instance de connexion à la base de données */
    private PDO $pdo;

    /** @var int Identifiant du scénario simulé */
    private int $idScenario;

    /**
     * Constructeur : initialise la connexion et le contexte du scénario.
     *
     * @param int $idScenario L'identifiant du scénario à simuler
     */
    public function __construct(int $idScenario) {
        $this->pdo        = BaseDeDonnees::getInstance();
        $this->idScenario = $idScenario;
    }

    // =========================================================================
    // MÉTHODE PRINCIPALE : Simulation complète d'un paquet
    // =========================================================================

    /**
     * Simule l'acheminement d'un paquet IP d'une source vers une destination.
     * Retourne un tableau décrivant chaque saut (hop) du parcours.
     *
     * @param string $ipSource      Adresse IP de l'hôte source
     * @param string $ipDestination Adresse IP de l'hôte destination
     * @return array Tableau contenant les étapes de simulation et le résultat
     */
    public function simuler(string $ipSource, string $ipDestination): array {
        // --- Initialisation de l'en-tête IP du paquet ---
        $paquet = [
            'version'        => 4,
            'ihl'            => 5,                          // Internet Header Length (en mots de 32 bits)
            'identification' => rand(1000, 65535),           // ID aléatoire comme spécifié
            'flags_df'       => true,                       // Don't Fragment activé
            'ttl'            => SIMULATION_TTL_INIT,        // TTL initial = 64
            'source'         => $ipSource,
            'destination'    => $ipDestination,
            'checksum'       => null,                       // Calculé à chaque saut
        ];
        $paquet['checksum'] = $this->calculerChecksum($paquet);

        // Tableau de résultat qui sera retourné au frontend
        $resultat = [
            'succes'  => false,
            'message' => '',
            'sauts'   => [],     // Liste des étapes hop-by-hop
            'paquet'  => $paquet,
        ];

        // --- Récupération des infos de l'hôte source ---
        $hoteSource = $this->trouverHote($ipSource);
        if (!$hoteSource) {
            $resultat['message'] = "Hôte source introuvable avec l'IP $ipSource.";
            return $resultat;
        }

        // Vérification que l'hôte source est bien rattaché à un réseau (non désactivé)
        if (empty($hoteSource['id_reseau'])) {
            $resultat['message'] = "L'hôte source est désactivé (non rattaché à un sous-réseau).";
            return $resultat;
        }

        // --- Récupération des infos de l'hôte destination ---
        $hoteDestination = $this->trouverHote($ipDestination);
        if (!$hoteDestination) {
            $resultat['message'] = "Hôte destination introuvable avec l'IP $ipDestination.";
            return $resultat;
        }

        // Enregistrement du premier saut (l'hôte source lui-même)
        $resultat['sauts'][] = $this->creerEntreeSaut(
            'hote', $hoteSource['nom'], $ipSource, null, $paquet, 'Émission du paquet'
        );

        // --- Phase d'émission : livraison locale ou via passerelle ? ---
        $reseauSource = $this->trouverReseau($hoteSource['id_reseau']);
        if (!$reseauSource) {
            $resultat['message'] = "Réseau source introuvable.";
            return $resultat;
        }

        // Calcul du réseau de la destination pour comparaison
        $memeReseau = $this->memeReseau(
            $ipSource, $ipDestination, $reseauSource['masque']
        );

        if ($memeReseau) {
            // --- Livraison locale directe (abstraction d'ARP) ---
            $resultat['succes']  = true;
            $resultat['message'] = "Livraison locale : la destination est dans le même sous-réseau.";
            $resultat['sauts'][] = $this->creerEntreeSaut(
                'hote', $hoteDestination['nom'], $ipDestination, null, $paquet, 'Livraison locale directe'
            );
            return $resultat;
        }

        // --- La destination est dans un autre réseau : envoi à la passerelle ---
        $passerelleIp = $hoteSource['passerelle_ip'];
        if (!$passerelleIp) {
            $resultat['message'] = "Network Unreachable : aucune passerelle configurée sur l'hôte source.";
            return $resultat;
        }

        // La passerelle doit être dans le même réseau que l'hôte source
        if (!$this->memeReseau($ipSource, $passerelleIp, $reseauSource['masque'])) {
            $resultat['message'] = "Network Unreachable : la passerelle est injoignable (hors sous-réseau).";
            return $resultat;
        }

        // --- Routage hop-by-hop ---
        $ipCourante  = $passerelleIp; // On commence par la passerelle de l'hôte
        $nbSauts     = 0;

        while ($nbSauts < SIMULATION_MAX_HOPS) {
            $nbSauts++;

            // Décrémentation du TTL à chaque routeur traversé
            $paquet['ttl']--;

            // Vérification du TTL : si = 0, le paquet est abandonné
            if ($paquet['ttl'] <= 0) {
                $paquet['checksum']  = $this->calculerChecksum($paquet);
                $resultat['message'] = "TTL expiré ! Datagramme d'erreur ICMP Time Exceeded généré.";
                $resultat['sauts'][] = $this->creerEntreeSaut(
                    'erreur', 'TTL Expiré', $ipCourante, null, $paquet,
                    'ICMP Time Exceeded - paquet abandonné', 'erreur_ttl'
                );
                return $resultat;
            }

            // Recalcul du checksum après modification du TTL
            $paquet['checksum'] = $this->calculerChecksum($paquet);

            // Recherche du routeur possédant l'IP courante (passerelle ou next-hop)
            $routeur = $this->trouverRouteurParIp($ipCourante);
            if (!$routeur) {
                $resultat['message'] = "Next-hop $ipCourante introuvable dans la topologie.";
                return $resultat;
            }

            // Enregistrement du saut sur ce routeur
            $resultat['sauts'][] = $this->creerEntreeSaut(
                'routeur', $routeur['nom'], $ipCourante, $routeur['id_routeur'],
                $paquet, "Routeur en transit (TTL={$paquet['ttl']})"
            );

            // Vérification si la destination est directement connectée à ce routeur
            $interfaceDirecte = $this->trouverInterfaceVersDestination(
                $routeur['id_routeur'], $ipDestination
            );

            if ($interfaceDirecte) {
                // La destination est sur un réseau directement connecté : livraison finale
                $resultat['succes']  = true;
                $resultat['message'] = "Paquet livré avec succès après $nbSauts saut(s).";
                $resultat['sauts'][] = $this->creerEntreeSaut(
                    'hote', $hoteDestination['nom'], $ipDestination, null,
                    $paquet, 'Livraison finale'
                );
                return $resultat;
            }

            // Recherche de la meilleure route (LPM : Longest Prefix Match)
            $meilleureRoute = $this->lpm($routeur['id_routeur'], $ipDestination);

            if (!$meilleureRoute) {
                $resultat['message'] = "Réseau inaccessible : aucune route vers $ipDestination sur le routeur '{$routeur['nom']}'.";
                return $resultat;
            }

            // Le prochain saut est le next-hop de la route sélectionnée
            $ipCourante = $meilleureRoute['next_hop'];
        }

        // Sécurité : arrêt après MAX_HOPS (boucle de routage détectée)
        $resultat['message'] = "Boucle de routage détectée : simulation arrêtée après " . SIMULATION_MAX_HOPS . " sauts.";
        return $resultat;
    }

    // =========================================================================
    // ALGORITHME LPM (Longest Prefix Match)
    // =========================================================================

    /**
     * Sélectionne la meilleure route pour une destination donnée.
     * Critère 1 : masque le plus long (route la plus spécifique)
     * Critère 2 : en cas d'égalité, l'ID de route le plus petit (ORDER BY id ASC)
     *
     * @param int    $idRouteur     Identifiant du routeur courant
     * @param string $ipDestination Adresse IP de destination
     * @return array|null La route sélectionnée ou null si aucune route trouvée
     */
    private function lpm(int $idRouteur, string $ipDestination): ?array {
        // Récupération de toutes les routes du routeur, triées par masque DESC puis id ASC
        $requete = $this->pdo->prepare(
            'SELECT id_route, reseau_dest, masque_dest, next_hop
             FROM ROUTE_STATIQUE
             WHERE id_routeur = :id_routeur
             ORDER BY masque_dest DESC, id_route ASC'
        );
        $requete->execute([':id_routeur' => $idRouteur]);
        $routes = $requete->fetchAll();

        // Parcours des routes pour trouver la première qui correspond (LPM natif)
        foreach ($routes as $route) {
            if ($this->appartientAuReseau($ipDestination, $route['reseau_dest'], $route['masque_dest'])) {
                return $route;
            }
        }

        return null; // Aucune route correspondante
    }

    // =========================================================================
    // MÉTHODES UTILITAIRES : Calculs IP
    // =========================================================================

    /**
     * Vérifie si deux adresses IP appartiennent au même réseau.
     *
     * @param string $ip1    Première adresse IP
     * @param string $ip2    Deuxième adresse IP
     * @param int    $masque Longueur du préfixe (CIDR)
     * @return bool True si les deux IP sont dans le même sous-réseau
     */
    private function memeReseau(string $ip1, string $ip2, int $masque): bool {
        return $this->appartientAuReseau($ip1, $ip2, $masque) || 
               ($this->adresseReseau($ip1, $masque) === $this->adresseReseau($ip2, $masque));
    }

    /**
     * Vérifie si une IP appartient à un réseau donné (notation CIDR).
     *
     * @param string $ip          Adresse IP à tester
     * @param string $adresseNet  Adresse réseau de référence
     * @param int    $masque      Longueur du préfixe
     * @return bool True si l'IP est dans le réseau
     */
    private function appartientAuReseau(string $ip, string $adresseNet, int $masque): bool {
        // Nettoyage : PostgreSQL peut stocker l'IP avec le masque (notation CIDR)
        $ip         = strtok($ip, '/');
        $adresseNet = strtok($adresseNet, '/');

        $ipLong  = ip2long($ip);
        $netLong = ip2long($adresseNet);

        if ($ipLong === false || $netLong === false) {
            return false;
        }

        // Masque binaire : 32 bits dont les $masque premiers sont à 1
        $masqueLong = $masque === 0 ? 0 : (0xFFFFFFFF << (32 - $masque)) & 0xFFFFFFFF;

        return ($ipLong & $masqueLong) === ($netLong & $masqueLong);
    }

    /**
     * Calcule l'adresse réseau d'une IP avec un masque donné.
     *
     * @param string $ip     Adresse IP
     * @param int    $masque Longueur du préfixe CIDR
     * @return string Adresse réseau en notation pointée
     */
    private function adresseReseau(string $ip, int $masque): string {
        $ip         = strtok($ip, '/');
        $ipLong     = ip2long($ip);
        $masqueLong = $masque === 0 ? 0 : (0xFFFFFFFF << (32 - $masque)) & 0xFFFFFFFF;
        return long2ip($ipLong & $masqueLong);
    }

    /**
     * Calcule le checksum de l'en-tête IP (RFC 791).
     * Le checksum est présenté en hexadécimal sur l'interface (couleur orange).
     *
     * @param array $paquet Données de l'en-tête IP
     * @return string Checksum hexadécimal (ex: "0x1A2B")
     */
    private function calculerChecksum(array $paquet): string {
        // Simulation simplifiée du checksum IP (CRC des champs de l'en-tête)
        // En environnement réel, on calcule le complément à 1 de la somme des mots 16 bits
        $donnees = sprintf(
            '%d%d%d%d%d%s%s',
            $paquet['version'],
            $paquet['ihl'],
            $paquet['identification'],
            $paquet['flags_df'] ? 1 : 0,
            $paquet['ttl'],
            $paquet['source'],
            $paquet['destination']
        );

        // CRC32 comme approximation pédagogique du checksum IP
        $crc = crc32($donnees) & 0xFFFF;
        return '0x' . strtoupper(sprintf('%04X', $crc));
    }

    // =========================================================================
    // MÉTHODES DE REQUÊTES EN BASE DE DONNÉES
    // =========================================================================

    /**
     * Recherche un hôte dans le scénario courant par son adresse IP.
     *
     * @param string $ip Adresse IP à rechercher
     * @return array|null Les données de l'hôte ou null
     */
    private function trouverHote(string $ip): ?array {
        $requete = $this->pdo->prepare(
            'SELECT id_hote, nom, host(adresse_ip) AS adresse_ip, host(passerelle_ip) AS passerelle_ip, id_reseau
             FROM HOTE
             WHERE adresse_ip = :ip AND id_scenario = :id_scenario'
        );
        $requete->execute([
            ':ip'          => $ip,
            ':id_scenario' => $this->idScenario,
        ]);

        $resultat = $requete->fetch();
        return $resultat ?: null;
    }

    /**
     * Recherche les informations d'un réseau par son identifiant.
     *
     * @param int $idReseau Identifiant du réseau
     * @return array|null Les données du réseau ou null
     */
    private function trouverReseau(int $idReseau): ?array {
        $requete = $this->pdo->prepare(
            'SELECT id_reseau, host(adresse_reseau) AS adresse_reseau, masque, label
             FROM RESEAU
             WHERE id_reseau = :id_reseau'
        );
        $requete->execute([':id_reseau' => $idReseau]);

        $resultat = $requete->fetch();
        return $resultat ?: null;
    }

    /**
     * Recherche un routeur possédant une interface avec l'adresse IP donnée.
     *
     * @param string $ip Adresse IP de l'interface recherchée
     * @return array|null Les données du routeur ou null
     */
    private function trouverRouteurParIp(string $ip): ?array {
        $requete = $this->pdo->prepare(
            'SELECT r.id_routeur, r.nom
             FROM Routeur r
             INNER JOIN INTERFACE i ON i.id_routeur = r.id_routeur
             WHERE i.adresse_ip = :ip AND r.id_scenario = :id_scenario'
        );
        $requete->execute([
            ':ip'          => $ip,
            ':id_scenario' => $this->idScenario,
        ]);

        $resultat = $requete->fetch();
        return $resultat ?: null;
    }

    /**
     * Cherche si une IP de destination est dans un réseau directement connecté
     * à un routeur (via une de ses interfaces).
     *
     * @param int    $idRouteur     Identifiant du routeur
     * @param string $ipDestination Adresse IP de destination
     * @return array|null L'interface correspondante ou null
     */
    private function trouverInterfaceVersDestination(int $idRouteur, string $ipDestination): ?array {
        $requete = $this->pdo->prepare(
            'SELECT id_interface, host(adresse_ip) AS adresse_ip, masque
             FROM INTERFACE
             WHERE id_routeur = :id_routeur'
        );
        $requete->execute([':id_routeur' => $idRouteur]);
        $interfaces = $requete->fetchAll();

        // Vérification pour chaque interface si la destination est dans son sous-réseau
        foreach ($interfaces as $interface) {
            if ($this->appartientAuReseau($ipDestination, $interface['adresse_ip'], $interface['masque'])) {
                return $interface;
            }
        }

        return null;
    }

    /**
     * Crée un tableau structuré représentant une étape de la simulation.
     * Ce format est consommé par le frontend pour l'animation vis.js.
     *
     * @param string     $type      Type de nœud ('hote', 'routeur', 'erreur')
     * @param string     $nom       Nom affiché de l'équipement
     * @param string     $ip        Adresse IP courante
     * @param int|null   $idNoeud   Identifiant du nœud dans la BDD
     * @param array      $paquet    État actuel de l'en-tête IP
     * @param string     $message   Message descriptif du saut
     * @param string     $statut    Statut visuel ('normal', 'erreur_ttl', etc.)
     * @return array Entrée de saut structurée
     */
    private function creerEntreeSaut(
        string $type,
        string $nom,
        string $ip,
        ?int   $idNoeud,
        array  $paquet,
        string $message,
        string $statut = 'normal'
    ): array {
        return [
            'type'    => $type,
            'nom'     => $nom,
            'ip'      => $ip,
            'id_noeud' => $idNoeud,
            'message' => $message,
            'statut'  => $statut,
            'paquet'  => [
                'version'        => $paquet['version'],
                'ihl'            => $paquet['ihl'],
                'identification' => $paquet['identification'],
                'flags_df'       => $paquet['flags_df'],
                'ttl'            => $paquet['ttl'],
                'checksum'       => $paquet['checksum'],
                'source'         => $paquet['source'],
                'destination'    => $paquet['destination'],
            ],
        ];
    }
}
?>
