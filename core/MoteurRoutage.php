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
}
?>