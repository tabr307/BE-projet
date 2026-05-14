<?php
/**
 * core/MoteurRoutage.php
 * Moteur algorithmique WBS 4.0
 */

class MoteurRoutage {

    /**
     * Exécute l'algorithme Longest Prefix Match (LPM).
     */
    public static function executerLPM(string $ipDestination, array $tableRoutage): ?array {
        $ipDestLong = ip2long($ipDestination);
        
        if ($ipDestLong === false) {
            throw new InvalidArgumentException("Adresse IP de destination invalide.");
        }

        $meilleureRoute = null;
        $masqueMax = -1;

        foreach ($tableRoutage as $route) {
            $reseauLong = ip2long($route['reseau_dest']);
            $masqueCidr = (int)$route['masque_dest'];

            $masqueBinaire = -1 << (32 - $masqueCidr);

            if (($ipDestLong & $masqueBinaire) === ($reseauLong & $masqueBinaire)) {
                if ($masqueCidr > $masqueMax) {
                    $masqueMax = $masqueCidr;
                    $meilleureRoute = $route;
                }
            }
        }

        return $meilleureRoute;
    }

    /**
     * Valide l'appartenance d'une adresse IP à un sous-réseau.
     */
    public static function verifierAppartenanceReseau(string $ip, string $reseau, int $masque): bool {
        $ipLong = ip2long($ip);
        $reseauLong = ip2long($reseau);
        
        if ($ipLong === false || $reseauLong === false || $masque < 0 || $masque > 32) {
            return false;
        }

        $masqueBinaire = -1 << (32 - $masque);
        
        return ($ipLong & $masqueBinaire) === ($reseauLong & $masqueBinaire);
    }

    /**
     * Prépare les tables de routage pour tous les routeurs d'un scénario.
     */
    public static function genererTablesVirtuelles(array $topologie): array {
        $tablesVirtuelles = [];

        foreach ($topologie['routeurs'] as $routeur) {
            $tablesVirtuelles[$routeur['id_routeur']] = [
                'nom' => $routeur['nom'],
                'routes' => []
            ];
        }

        foreach ($topologie['routes'] as $route) {
            $tablesVirtuelles[$route['id_routeur']]['routes'][] = [
                'reseau_dest' => $route['reseau_dest'],
                'masque_dest' => (int)$route['masque_dest'],
                'next_hop'    => $route['next_hop'],
                'type'        => 'statique'
            ];
        }

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
     * WBS 4.0 : Simule l'acheminement complet d'un datagramme IP.
     */
    public static function simulerAcheminement(PDO $pdo, int $scenarioId, int $hoteSourceId, string $ipDest): array {
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
        
        $source['masque'] = !empty($source['bloc_cidr']) && strpos($source['bloc_cidr'], '/') !== false 
            ? (int)explode('/', $source['bloc_cidr'])[1] 
            : 24;

        $topologie = [];
        $stmtRouteurs = $pdo->prepare("SELECT id as id_routeur, nom FROM routeur WHERE scenario_id = ?");
        $stmtRouteurs->execute([$scenarioId]);
        $topologie['routeurs'] = $stmtRouteurs->fetchAll(PDO::FETCH_ASSOC);

        $stmtInterfaces = $pdo->prepare("
            SELECT ir.id as interface_id, ir.routeur_id as id_routeur, ir.adresse_ip, ir.masque 
            FROM interface_routeur ir JOIN routeur r ON ir.routeur_id = r.id WHERE r.scenario_id = ?
        ");
        $stmtInterfaces->execute([$scenarioId]);
        $topologie['interfaces'] = $stmtInterfaces->fetchAll(PDO::FETCH_ASSOC);

        $stmtRoutes = $pdo->prepare("
            SELECT rs.routeur_id as id_routeur, rs.reseau_dest, rs.masque_dest, rs.next_hop 
            FROM route_statique rs JOIN routeur r ON rs.routeur_id = r.id WHERE r.scenario_id = ?
        ");
        $stmtRoutes->execute([$scenarioId]);
        $topologie['routes'] = $stmtRoutes->fetchAll(PDO::FETCH_ASSOC);

        $stmtAllHotes = $pdo->prepare("
            SELECT h.id, h.nom, h.adresse_ip, h.passerelle_ip as passerelle, sr.bloc_cidr 
            FROM hote h 
            LEFT JOIN sous_reseau sr ON h.sous_reseau_id = sr.id 
            WHERE h.scenario_id = ? AND h.adresse_ip IS NOT NULL
        ");
        $stmtAllHotes->execute([$scenarioId]);
        $hotes = $stmtAllHotes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($hotes as &$h) {
            $h['masque'] = !empty($h['bloc_cidr']) && strpos($h['bloc_cidr'], '/') !== false 
                ? (int)explode('/', $h['bloc_cidr'])[1] 
                : 24;
        }
        unset($h);

        $datagramme = [
            'id' => rand(1000, 9999),
            'df' => 1,
            'ttl' => 64,
            'checksum' => CalculateurReseau::calculerChecksumHex(64, $source['adresse_ip'], $ipDest),
            'src' => $source['adresse_ip'],
            'dest' => $ipDest
        ];

        $trace = [];
        $hopIndex = 0;
        
        $trace[] = [
            'hop_index' => $hopIndex++,
            'type_noeud' => 'hote',
            'id_noeud' => $source['id'],
            'nom' => $source['nom'],
            'ip_entree' => $source['adresse_ip'],
            'ip_sortie' => $source['adresse_ip'],
            'etat_datagramme' => $datagramme
        ];

        $masqueSourceCidr = (int)$source['masque'];
        
        if (CalculateurReseau::estDansMemeReseau($source['adresse_ip'], $ipDest, $masqueSourceCidr)) {
            $hoteDest = null;
            foreach ($hotes as $h) {
                if ($h['adresse_ip'] === $ipDest) {
                    $hoteDest = $h; break;
                }
            }
            if ($hoteDest) {
                $trace[] = [
                    'hop_index' => $hopIndex++,
                    'type_noeud' => 'hote',
                    'id_noeud' => $hoteDest['id'],
                    'nom' => $hoteDest['nom'],
                    'ip_entree' => $hoteDest['adresse_ip'],
                    'ip_sortie' => $hoteDest['adresse_ip'],
                    'etat_datagramme' => $datagramme,
                    'message' => 'Livraison locale réussie'
                ];
                return ['statut' => 'succes', 'trace' => $trace];
            } else {
                return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucun hôte trouvé pour l'IP locale " . $ipDest, 'trace' => $trace];
            }
        }

        $passerelle = $source['passerelle'];
        if (empty($passerelle)) {
            return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucune passerelle définie pour l'hôte source.", 'trace' => $trace];
        }

        $routeurActuelId = null;
        $ipEntree = $passerelle;
        foreach ($topologie['interfaces'] as $intf) {
            if ($intf['adresse_ip'] === $passerelle) {
                $routeurActuelId = $intf['id_routeur'];
                break;
            }
        }

        if (!$routeurActuelId) {
            return ['statut' => 'erreur', 'message' => "Network Unreachable : La passerelle " . $passerelle . " n'existe sur aucun routeur.", 'trace' => $trace];
        }

        $tablesVirtuelles = self::genererTablesVirtuelles($topologie);
        $sauts = 0;

        while (true) {
            if ($sauts >= 30) {
                return ['statut' => 'erreur', 'message' => "Boucle de routage détectée (plus de 30 sauts).", 'trace' => $trace];
            }

            $datagramme['ttl'] -= 1;
            $datagramme['checksum'] = CalculateurReseau::calculerChecksumHex($datagramme['ttl'], $datagramme['src'], $datagramme['dest']);

            $trace[] = [
                'hop_index' => $hopIndex++,
                'type_noeud' => 'routeur',
                'id_noeud' => $routeurActuelId,
                'nom' => $tablesVirtuelles[$routeurActuelId]['nom'] ?? 'Routeur',
                'ip_entree' => $ipEntree,
                'ip_sortie' => null,
                'etat_datagramme' => $datagramme
            ];

            if ($datagramme['ttl'] <= 0) {
                return ['statut' => 'erreur', 'message' => "Time Exceeded : Le TTL du datagramme a atteint 0.", 'trace' => $trace];
            }

            $table = $tablesVirtuelles[$routeurActuelId]['routes'];
            $route = self::executerLPM($ipDest, $table);

            if (!$route) {
                return ['statut' => 'erreur', 'message' => "Network Unreachable : Aucune route trouvée vers " . $ipDest . " sur le routeur " . ($tablesVirtuelles[$routeurActuelId]['nom'] ?? ''), 'trace' => $trace];
            }

            $nextHop = $route['next_hop'];

            if ($nextHop === '0.0.0.0') {
                $ipSortieDirecte = null;
                foreach ($topologie['interfaces'] as $intf) {
                    if ($intf['id_routeur'] === $routeurActuelId && CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $ipDest, (int)$intf['masque'])) {
                        $ipSortieDirecte = $intf['adresse_ip'];
                        break;
                    }
                }
                
                $trace[count($trace)-1]['ip_sortie'] = $ipSortieDirecte;

                $hoteDest = null;
                foreach ($hotes as $h) {
                    if ($h['adresse_ip'] === $ipDest) {
                        $hoteDest = $h; break;
                    }
                }

                if ($hoteDest) {
                    $trace[] = [
                        'hop_index' => $hopIndex++,
                        'type_noeud' => 'hote',
                        'id_noeud' => $hoteDest['id'],
                        'nom' => $hoteDest['nom'],
                        'ip_entree' => $hoteDest['adresse_ip'],
                        'ip_sortie' => $hoteDest['adresse_ip'],
                        'etat_datagramme' => $datagramme,
                        'message' => 'Livraison réussie'
                    ];
                    return ['statut' => 'succes', 'trace' => $trace];
                } else {
                    $routeurDestIntf = null;
                    foreach ($topologie['interfaces'] as $intf) {
                        if ($intf['adresse_ip'] === $ipDest) {
                            $routeurDestIntf = $intf; break;
                        }
                    }
                    if ($routeurDestIntf) {
                        $trace[] = [
                            'hop_index' => $hopIndex++,
                            'type_noeud' => 'routeur',
                            'id_noeud' => $routeurDestIntf['id_routeur'],
                            'nom' => $tablesVirtuelles[$routeurDestIntf['id_routeur']]['nom'] ?? 'Routeur',
                            'ip_entree' => $routeurDestIntf['adresse_ip'],
                            'ip_sortie' => $routeurDestIntf['adresse_ip'],
                            'etat_datagramme' => $datagramme,
                            'message' => 'Livraison à l\'interface de routeur réussie'
                        ];
                        return ['statut' => 'succes', 'trace' => $trace];
                    }

                    return ['statut' => 'erreur', 'message' => "Network Unreachable : Hôte final " . $ipDest . " introuvable sur le réseau connecté.", 'trace' => $trace];
                }
            } else {
                $ipSortieNextHop = null;
                foreach ($topologie['interfaces'] as $intf) {
                    if ($intf['id_routeur'] === $routeurActuelId && CalculateurReseau::estDansMemeReseau($intf['adresse_ip'], $nextHop, (int)$intf['masque'])) {
                        $ipSortieNextHop = $intf['adresse_ip'];
                        break;
                    }
                }
                $trace[count($trace)-1]['ip_sortie'] = $ipSortieNextHop;

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

                $routeurActuelId = $prochainRouteurId;
                $ipEntree = $nextHop;
                $sauts++;
            }
        }
    }
}
?>