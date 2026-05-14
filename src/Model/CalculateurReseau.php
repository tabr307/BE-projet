<?php
// src/Model/CalculateurReseau.php

class CalculateurReseau {

    // Vérifie si une adresse IPv4 est syntaxiquement correcte
    public static function validerIP(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    // Convertit un préfixe CIDR en masque de sous-réseau 
    public static function cidrVersMasque(int $cidr): string {
        if ($cidr < 0 || $cidr > 32) return "0.0.0.0";
        $masqueLong = 0xFFFFFFFF << (32 - $cidr);
        return long2ip($masqueLong);
    }

    // Vérifie si deux adresses IP appartiennent au même sous-réseau
    public static function estDansMemeReseau(string $ip1, string $ip2, int $cidr): bool {
        $masqueBin = ip2long(self::cidrVersMasque($cidr));
        $ip1Bin = ip2long($ip1);
        $ip2Bin = ip2long($ip2);

        if ($ip1Bin === false || $ip2Bin === false) return false;

        return ($ip1Bin & $masqueBin) === ($ip2Bin & $masqueBin);
    }

    // WBS 4.0 : Calcule un pseudo-checksum hexadécimal basé sur l'en-tête IP
    public static function calculerChecksumHex(int $ttl, string $src, string $dest): string {
        $ipSrcParts = explode('.', $src);
        $ipDestParts = explode('.', $dest);
        
        $sum = $ttl;
        foreach ($ipSrcParts as $part) $sum += (int)$part;
        foreach ($ipDestParts as $part) $sum += (int)$part;
        
        // Complément à un classique simulé : on masque sur 16 bits
        $checksum = (~$sum) & 0xFFFF;
        return '0x' . strtoupper(str_pad(dechex($checksum), 4, '0', STR_PAD_LEFT));
    }
}