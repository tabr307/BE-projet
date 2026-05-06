<?php
namespace App\Model;

class CalculateurReseau {

    //Vérifie si une adresse IPv4 est syntaxiquement correcte

    public static function validerIP(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    //Convertit un préfixe CIDR en masque de sous-réseau 

    public static function cidrVersMasque(int $cidr): string {
        if ($cidr < 0 || $cidr > 32) return "0.0.0.0";
        // Calcul mathématique : on décale des bits pour créer le masque
        $masqueLong = 0xFFFFFFFF << (32 - $cidr);
        return long2ip($masqueLong);
    }

    //Vérifie si deux adresses IP appartiennent au même sous-réseau
  
    public static function estDansMemeReseau(string $ip1, string $ip2, int $cidr): bool {
        $masqueBin = ip2long(self::cidrVersMasque($cidr));
        $ip1Bin = ip2long($ip1);
        $ip2Bin = ip2long($ip2);

        if ($ip1Bin === false || $ip2Bin === false) return false;

        // On compare les parties "Réseau" des deux IP en utilisant le masque
        return ($ip1Bin & $masqueBin) === ($ip2Bin & $masqueBin);
    }
}