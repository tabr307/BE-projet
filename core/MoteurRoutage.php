<?php
/**
 * core/MoteurRoutage.php
 * Moteur algorithmique WBS 4.0
 */

class MoteurRoutage {

    /**
     * Algorithme Longest Prefix Match (LPM) :
     * Parmi toutes les routes disponibles, retourne celle dont le masque
     * est le plus long (le plus précis) et qui correspond à l'IP de destination.
     */
    public static function executerLPM(string $ipDestination, array $tableRoutage): ?array {
        $ipDestLong = ip2long($ipDestination); // Convertit l'IP en entier pour les opérations binaires
        
        if ($ipDestLong === false) {
            throw new InvalidArgumentException("Adresse IP de destination invalide.");
        }

        $meilleureRoute = null;
        $masqueMax = -1; // Initialisé à -1 pour accepter même un masque /0 (route par défaut)

        foreach ($tableRoutage as $route) {
            $reseauLong  = ip2long($route['reseau_dest']); // IP réseau en entier
            $masqueCidr  = (int)$route['masque_dest']; // Longueur du masque en notation CIDR

            // Construit le masque binaire sur 32 bits à partir du CIDR (ex: /24 -> 0xFFFFFF00)
            $masqueBinaire = -1 << (32 - $masqueCidr);

            // Applique le masque sur les deux IPs : si les parties réseau sont identiques, la route correspond
            if (($ipDestLong & $masqueBinaire) === ($reseauLong & $masqueBinaire)) {
                // On garde la route avec le masque le plus long 
                if ($masqueCidr > $masqueMax) {
                    $masqueMax      = $masqueCidr;
                    $meilleureRoute = $route;
                }
            }
        }

        return $meilleureRoute; // null si aucune route ne correspond
    }

    /**
     * Vérifie si une IP appartient à un sous-réseau donné.
     * Utilisé pour les décisions de livraison locale et la recherche d'interface de sortie.
     */
    public static function verifierAppartenanceReseau(string $ip, string $reseau, int $masque): bool {
        $ipLong     = ip2long($ip);
        $reseauLong = ip2long($reseau);
        
        // Validation des paramètres avant tout calcul
        if ($ipLong === false || $reseauLong === false || $masque < 0 || $masque > 32) {
            return false;
        }

        $masqueBinaire = -1 << (32 - $masque);
        
        // Même logique que LPM : compare les parties réseau des deux adresses
        return ($ipLong & $masqueBinaire) === ($reseauLong & $masqueBinaire);
    }

    /**
     * Construit en mémoire les tables de routage de tous les routeurs d'un scénario.
     * Fusionne routes statiques (configurées manuellement) et routes directes (interfaces locales).
     */
    public static function genererTablesVirtuelles(array $topologie): array {
        $tablesVirtuelles = [];

        // Initialise une entrée vide pour chaque routeur
        foreach ($topologie['routeurs'] as $routeur) {
            $tablesVirtuelles[$routeur['id_routeur']] = [
                'nom'    => $routeur['nom'],
                'routes' => []
            ];
        }

        // Injecte les routes statiques (next_hop explicite, configuré par l'utilisateur)
        foreach ($topologie['routes'] as $route) {
            $tablesVirtuelles[$route['id_routeur']]['routes'][] = [
                'reseau_dest' => $route['reseau_dest'],
                'masque_dest' => (int)$route['masque_dest'],
                'next_hop'    => $route['next_hop'],
                'type'        => 'statique'
            ];
        }

        // Injecte les routes directes (réseaux directement connectés aux interfaces)
        // Le next_hop 0.0.0.0 signifie "livraison directe, pas de saut intermédiaire"
        foreach ($topologie['interfaces'] as $interface) {
            $tablesVirtuelles[$interface['id_routeur']]['routes'][] = [
                'reseau_dest' => $interface['adresse_ip'],
                'masque_dest' => (int)$interface['masque'],
                'next_hop'    => '0.0.0.0', 
                'type'        => 'directe'
            ];
        }

        return $tablesVirtuelles;
    }

    /**
     * WBS 4.0 : Simule le cheminement complet d'un datagramme IP à travers la topologie.
     * Gère : livraison locale, routage multi-sauts, TTL/ICMP Time Exceeded, boucles de routage.
     *
     * @param PDO    $pdo : Connexion base de données
     * @param int    $scenarioId : Identifiant du scénario réseau à simuler
     * @param int    $hoteSourceId : ID de l'hôte émetteur
     * @param string $ipDest : Adresse IP de destination
     * @return array ['statut' => 'succes'|'erreur', 'trace' => [...], 'message' => '...']
     */
    public static function simulerAcheminement(PDO $pdo, int $scenarioId, int $hoteSourceId, string $ipDest): array {

        // 1. CHARGEMENT DE L'HÔTE SOURCE 

        $stmtHote = $pdo->prepare("
            SELECT h.*, h.passerelle_ip as passerelle, sr.bloc_cidr 
            FROM hote h 
            LEFT JOIN sous_reseau sr ON h.sous_reseau_id = sr.id 
            WHERE h.id = ? AND h.scenario_id = ?
        ");
        $stmtHote->execute([$hoteSourceId, $scenarioId]);
        $source = $stmtHote->fetch(PDO::FETCH_ASSOC);

        if (!$source || empty($source['adresse_ip'])) {
            throw new Exception("Hôte source non configuré ou introuvable.");
        }
        
        // Extrait le masque depuis le bloc CIDR (ex: "192.168.1.0/24" → 24), défaut à /24
        $source['masque'] = !empty($source['bloc_cidr']) && strpos($source['bloc_cidr'], '/') !== false 
            ? (int)explode('/', $source['bloc_cidr'])[1] 
            : 24;

        // 2. CHARGEMENT DE LA TOPOLOGIE COMPLÈTE 

        $topologie = [];

        // Récupère tous les routeurs du scénario
        $stmtRouteurs = $pdo->prepare("SELECT id as id_routeur, nom FROM routeur WHERE scenario_id = ?");
        $stmtRouteurs->execute([$scenarioId]);
        $topologie['routeurs'] = $stmtRouteurs->fetchAll(PDO::FETCH_ASSOC);

        // Récupère toutes les interfaces des routeurs (adresse IP + masque par interface)
        $stmtInterfaces = $pdo->prepare("
            SELECT ir.id as interface_id, ir.routeur_id as id_routeur, ir.adresse_ip, ir.masque 
            FROM interface_routeur ir JOIN routeur r ON ir.routeur_id = r.id WHERE r.scenario_id = ?
        ");
        $stmtInterfaces->execute([$scenarioId]);
        $topologie['interfaces'] = $stmtInterfaces->fetchAll(PDO::FETCH_ASSOC);

        // Récupère toutes les routes statiques configurées
        $stmtRoutes = $pdo->prepare("
            SELECT rs.routeur_id as id_routeur, rs.reseau_dest, rs.masque_dest, rs.next_hop 
            FROM route_statique rs JOIN routeur r ON rs.routeur_id = r.id WHERE r.scenario_id = ?
        ");
        $stmtRoutes->execute([$scenarioId]);
        $topologie['routes'] = $stmtRoutes->fetchAll(PDO::FETCH_ASSOC);

        // Récupère tous les hôtes du scénario ayant une IP configurée
        $stmtAllHotes = $pdo->prepare("
            SELECT h.id, h.nom, h.adresse_ip, h.passerelle_ip as passerelle, sr.bloc_cidr 
            FROM hote h 
            LEFT JOIN sous_reseau sr ON h.sous_reseau_id = sr.id 
            WHERE h.scenario_id = ? AND h.adresse_ip IS NOT NULL
        ");
        $stmtAllHotes->execute([$scenarioId]);
        $hotes = $stmtAllHotes->fetchAll(PDO::FETCH_ASSOC);

        // Enrichit chaque hôte avec son masque extrait du bloc CIDR
        foreach ($hotes as &$h) {
            $h['masque'] = !empty($h['bloc_cidr']) && strpos($h['bloc_cidr'], '/') !== false 
                ? (int)explode('/', $h['bloc_cidr'])[1] 
                : 24;
        }
        unset($h); // Libère la référence pour éviter les effets de bord

        // 3. CRÉATION DU DATAGRAMME IP 

        $datagramme = [
            'id'       => rand(1000, 9999),                        // Identifiant aléatoire du paquet
            'df'       => 1,                                       // Don't Fragment activé
            'ttl'      => 64,                                      // Time To Live initial 
            'checksum' => CalculateurReseau::calculerChecksumHex(64, $source['adresse_ip'], $ipDest),
            'src'      => $source['adresse_ip'],
            'dest'     => $ipDest
        ];

        // 4. INITIALISATION DE LA TRACE 

        $trace    = [];
        $hopIndex = 0;
        
        // Premier nœud : l'hôte source lui-même
        $trace[] = [
            'hop_index'       => $hopIndex++,
            'type_noeud'      => 'hote',
            'id_noeud'        => $source['id'],
            'nom'             => $source['nom'],
            'ip_entree'       => $source['adresse_ip'],
            'ip_sortie'       => $source['adresse_ip'],
            'etat_datagramme' => $datagramme
        ];

        // 5. CAS DE LIVRAISON LOCALE (même sous-réseau) 

        $masqueSourceCidr = (int)$source['masque'];
        
        // Si la destination est dans le même réseau que la source, pas besoin de routeur
        if (CalculateurReseau::estDansMemeReseau($source['adresse_ip'], $ipDest, $masqueSourceCidr)) {
            $hoteDest = null;
            foreach ($hotes as $h) {
                if ($h['adresse_ip'] === $ipDest) {
                    $hoteDest = $h; break;
                }
            }
            if ($hoteDest) {
                // L'hôte destination est trouvé, livraison directe
                $trace[] = [
                    'hop_index'       => $hopIndex++,
                    'type_noeud'      => 'hote',
                    'id_noeud'        => $hoteDest['id'],
                    'nom'             => $hoteDest['nom'],
                    'ip_entree'       => $hoteDest['adresse_ip'],
                    'ip_sortie'       => $hoteDest['adresse_ip'],
                    'etat_datagramme' => $datagramme,
                    'message'         => 'Livraison locale réussie'
                ];
                return ['statut' => 'succes', 'trace' => $trace];
            } else {
                // IP dans le bon réseau mais aucun hôte ne l'a
                return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucun hôte trouvé pour l'IP locale " . $ipDest, 'trace' => $trace];
            }
        }

        // 6. IDENTIFICATION DU PREMIER ROUTEUR (via la passerelle) 

        $passerelle = $source['passerelle'];
        if (empty($passerelle)) {
            return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucune passerelle définie pour l'hôte source.", 'trace' => $trace];
        }

        // Cherche quel routeur possède l'IP de la passerelle sur l'une de ses interfaces
        $routeurActuelId = null;
        $ipEntree        = $passerelle;
        foreach ($topologie['interfaces'] as $intf) {
            if ($intf['adresse_ip'] === $passerelle) {
                $routeurActuelId = $intf['id_routeur'];
                break;
            }
        }

        if (!$routeurActuelId) {
            return ['statut' => 'erreur', 'message' => "Network Unreachable : La passerelle " . $passerelle . " n'existe sur aucun routeur.", 'trace' => $trace];
        }

        // 7. GÉNÉRATION DES TABLES VIRTUELLES ET BOUCLE DE ROUTAGE 

        $tablesVirtuelles = self::genererTablesVirtuelles($topologie);
        $sauts = 0;

        while (true) {
            // Protection contre les boucles de routage infinies
            if ($sauts >= 30) {
                return ['statut' => 'erreur', 'message' => "Boucle de routage détectée (plus de 30 sauts).", 'trace' => $trace];
            }

            // Décrémente le TTL et recalcule le checksum à chaque saut (comportement IP réel)
            $datagramme['ttl'] -= 1;
            $datagramme['checksum'] = CalculateurReseau::calculerChecksumHex($datagramme['ttl'], $datagramme['src'], $datagramme['dest']);

            // Enregistre le passage sur ce routeur dans la trace
            $trace[] = [
                'hop_index'       => $hopIndex++,
                'type_noeud'      => 'routeur',
                'id_noeud'        => $routeurActuelId,
                'nom'             => $tablesVirtuelles[$routeurActuelId]['nom'] ?? 'Routeur',
                'ip_entree'       => $ipEntree,
                'ip_sortie'       => null, // Sera complété plus bas après décision de routage
                'etat_datagramme' => $datagramme
            ];

            // 7a. TTL EXPIRÉ -> ICMP Time Exceeded

            if ($datagramme['ttl'] <= 0) {
                // Marquage du saut où le TTL expire
                $trace[count($trace)-1]['message'] = 'TTL expiré — Génération ICMP Time Exceeded';

                // Construction du datagramme ICMP d'erreur
                $datagrammeErreur = [
                    'id'          => rand(1000, 9999),
                    'df'          => 1,
                    'ttl'         => 64,
                    'checksum'    => CalculateurReseau::calculerChecksumHex(64, $ipEntree, $datagramme['src']),
                    'src'         => $ipEntree,              // Émis depuis l'interface du routeur qui a détecté l'expiration
                    'dest'        => $datagramme['src'],     // Destiné à l'hôte source original
                    'type_paquet' => 'icmp_time_exceeded'
                ];

                // Enregistre l'émission du paquet ICMP sur le routeur courant
                $trace[] = [
                    'hop_index'       => $hopIndex++,
                    'type_noeud'      => 'routeur',
                    'id_noeud'        => $routeurActuelId,
                    'nom'             => $tablesVirtuelles[$routeurActuelId]['nom'] ?? 'Routeur',
                    'ip_entree'       => $ipEntree,
                    'ip_sortie'       => null,
                    'etat_datagramme' => $datagrammeErreur,
                    'message'         => 'Émission ICMP Time Exceeded vers ' . $datagramme['src'],
                    'type_paquet'     => 'icmp_time_exceeded'
                ];

                // Routage retour du paquet ICMP vers la source 

                $routeurRetourId = $routeurActuelId;
                $ipDestRetour    = $datagramme['src'];
                $sautsRetour     = 0;

                while (true) {
                    // Protection boucle sur le chemin retour également
                    if ($sautsRetour >= 30) {
                        $trace[count($trace)-1]['message'] = 'Boucle de routage détectée sur le retour ICMP';
                        return ['statut' => 'erreur', 'message' => "Time Exceeded + Boucle ICMP retour.", 'trace' => $trace];
                    }

                    // Décrémente TTL du paquet ICMP retour à chaque saut
                    $datagrammeErreur['ttl'] -= 1;
                    $datagrammeErreur['checksum'] = CalculateurReseau::calculerChecksumHex(
                        $datagrammeErreur['ttl'], $datagrammeErreur['src'], $datagrammeErreur['dest']
                    );

                    // LPM pour trouver la route vers la source originale
                    $tableRetour = $tablesVirtuelles[$routeurRetourId]['routes'];
                    $routeRetour = self::executerLPM($ipDestRetour, $tableRetour);

                    if (!$routeRetour) {
                        $trace[count($trace)-1]['message'] = 'ICMP retour impossible — Aucune route vers la source';
                        return ['statut' => 'erreur', 'message' => "Time Exceeded : ICMP retour impossible — Network Unreachable.", 'trace' => $trace];
                    }

                    $nextHopRetour = $routeRetour['next_hop'];

                    if ($nextHopRetour === '0.0.0.0') {
                        // Réseau directement connecté : livraison finale du paquet ICMP à la source

                        // Trouve l'interface de sortie du routeur vers la source
                        $ipSortieRetour = null;
                        foreach ($topologie['interfaces'] as $intf) {
                            if ($intf['id_routeur'] == $routeurRetourId &&
                                CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $ipDestRetour, (int)$intf['masque'])) {
                                $ipSortieRetour = $intf['adresse_ip'];
                                break;
                            }
                        }
                        $trace[count($trace)-1]['ip_sortie'] = $ipSortieRetour;

                        // Recherche de l'hôte source pour l'enregistrer comme destinataire final
                        $hoteSource = null;
                        foreach ($hotes as $h) {
                            if ($h['adresse_ip'] === $ipDestRetour) {
                                $hoteSource = $h;
                                break;
                            }
                        }

                        if ($hoteSource) {
                            $trace[] = [
                                'hop_index'       => $hopIndex++,
                                'type_noeud'      => 'hote',
                                'id_noeud'        => $hoteSource['id'],
                                'nom'             => $hoteSource['nom'],
                                'ip_entree'       => $hoteSource['adresse_ip'],
                                'ip_sortie'       => $hoteSource['adresse_ip'],
                                'etat_datagramme' => $datagrammeErreur,
                                'message'         => 'ICMP Time Exceeded reçu par la source',
                                'type_paquet'     => 'icmp_time_exceeded'
                            ];
                        }

                        return ['statut' => 'erreur', 'message' => "Time Exceeded : Le TTL a atteint 0. Erreur ICMP renvoyée à la source.", 'trace' => $trace];

                    } else {
                        // Saut intermédiaire sur le chemin retour ICMP

                        // Trouve l'IP de sortie du routeur courant vers le prochain saut
                        $ipSortieRetour = null;
                        foreach ($topologie['interfaces'] as $intf) {
                            if ($intf['id_routeur'] == $routeurRetourId &&
                                CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $nextHopRetour, (int)$intf['masque'])) {
                                $ipSortieRetour = $intf['adresse_ip'];
                                break;
                            }
                        }
                        $trace[count($trace)-1]['ip_sortie'] = $ipSortieRetour;

                        // Identifie le routeur suivant à partir du next_hop
                        $prochainRouteurRetourId = null;
                        foreach ($topologie['interfaces'] as $intf) {
                            if ($intf['adresse_ip'] === $nextHopRetour) {
                                $prochainRouteurRetourId = $intf['id_routeur'];
                                break;
                            }
                        }

                        if (!$prochainRouteurRetourId) {
                            return ['statut' => 'erreur', 'message' => "Time Exceeded : ICMP retour impossible — Next Hop introuvable.", 'trace' => $trace];
                        }

                        // Enregistre le passage sur ce routeur intermédiaire du chemin retour
                        $trace[] = [
                            'hop_index'       => $hopIndex++,
                            'type_noeud'      => 'routeur',
                            'id_noeud'        => $prochainRouteurRetourId,
                            'nom'             => $tablesVirtuelles[$prochainRouteurRetourId]['nom'] ?? 'Routeur',
                            'ip_entree'       => $nextHopRetour,
                            'ip_sortie'       => null,
                            'etat_datagramme' => $datagrammeErreur,
                            'type_paquet'     => 'icmp_time_exceeded'
                        ];

                        $routeurRetourId = $prochainRouteurRetourId;
                        $sautsRetour++;
                    }
                }
            }

            // 7b. ROUTAGE NORMAL : LPM sur la table du routeur courant 

            $table = $tablesVirtuelles[$routeurActuelId]['routes'];
            $route = self::executerLPM($ipDest, $table);

            if (!$route) {
                // Aucune route correspondante : destination injoignable
                return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucune route trouvée vers " . $ipDest . " sur le routeur " . ($tablesVirtuelles[$routeurActuelId]['nom'] ?? ''), 'trace' => $trace];
            }

            $nextHop = $route['next_hop'];

            if ($nextHop === '0.0.0.0') {
                // 7c. LIVRAISON DIRECTE (réseau connecté au routeur courant)

                // Trouve l'interface du routeur sur laquelle l'IP dest est joignable
                $ipSortieDirecte = null;
                foreach ($topologie['interfaces'] as $intf) {
                    if ($intf['id_routeur'] == $routeurActuelId && CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $ipDest, (int)$intf['masque'])) {
                        $ipSortieDirecte = $intf['adresse_ip'];
                        break;
                    }
                }
                
                $trace[count($trace)-1]['ip_sortie'] = $ipSortieDirecte;

                // Cherche un hôte ayant exactement l'IP de destination
                $hoteDest = null;
                foreach ($hotes as $h) {
                    if ($h['adresse_ip'] === $ipDest) {
                        $hoteDest = $h; break;
                    }
                }

                if ($hoteDest) {
                    // Hôte trouvé, livraison réussie
                    $trace[] = [
                        'hop_index'       => $hopIndex++,
                        'type_noeud'      => 'hote',
                        'id_noeud'        => $hoteDest['id'],
                        'nom'             => $hoteDest['nom'],
                        'ip_entree'       => $hoteDest['adresse_ip'],
                        'ip_sortie'       => $hoteDest['adresse_ip'],
                        'etat_datagramme' => $datagramme,
                        'message'         => 'Livraison réussie'
                    ];
                    return ['statut' => 'succes', 'trace' => $trace];
                } else {
                    // Cas particulier : la destination est peut-être une interface de routeur (ex: ping d'une interface)
                    $routeurDestIntf = null;
                    foreach ($topologie['interfaces'] as $intf) {
                        if ($intf['adresse_ip'] === $ipDest) {
                            $routeurDestIntf = $intf; break;
                        }
                    }
                    if ($routeurDestIntf) {
                        $trace[] = [
                            'hop_index'       => $hopIndex++,
                            'type_noeud'      => 'routeur',
                            'id_noeud'        => $routeurDestIntf['id_routeur'],
                            'nom'             => $tablesVirtuelles[$routeurDestIntf['id_routeur']]['nom'] ?? 'Routeur',
                            'ip_entree'       => $routeurDestIntf['adresse_ip'],
                            'ip_sortie'       => $routeurDestIntf['adresse_ip'],
                            'etat_datagramme' => $datagramme,
                            'message'         => 'Livraison à l\'interface de routeur réussie'
                        ];
                        return ['statut' => 'succes', 'trace' => $trace];
                    }

                    // Réseau connecté mais aucun hôte ni interface ne possède cette IP
                    return ['statut' => 'erreur', 'message' => "Network Unreachable : Hôte final " . $ipDest . " introuvable sur le réseau connecté.", 'trace' => $trace];
                }
            } else {
                // 7d. SAUT INTERMÉDIAIRE vers un autre routeur (next_hop ≠ 0.0.0.0)

                // Trouve l'interface de sortie du routeur courant vers le next_hop
                $ipSortieNextHop = null;
                foreach ($topologie['interfaces'] as $intf) {
                    if ($intf['id_routeur'] == $routeurActuelId && CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $nextHop, (int)$intf['masque'])) {
                        $ipSortieNextHop = $intf['adresse_ip'];
                        break;
                    }
                }
                $trace[count($trace)-1]['ip_sortie'] = $ipSortieNextHop;

                // Identifie le prochain routeur via son interface qui porte l'IP du next_hop
                $prochainRouteurId = null;
                foreach ($topologie['interfaces'] as $intf) {
                    if ($intf['adresse_ip'] === $nextHop) {
                        $prochainRouteurId = $intf['id_routeur'];
                        break;
                    }
                }

                if (!$prochainRouteurId) {
                    return ['statut' => 'erreur', 'message' => "Network Unreachable : Le Next Hop " . $nextHop . " ne correspond à aucune interface.", 'trace' => $trace];
                }

                // Passe au routeur suivant et continue la boucle
                $routeurActuelId = $prochainRouteurId;
                $ipEntree        = $nextHop;
                $sauts++;
            }
        }
    }
}
?>