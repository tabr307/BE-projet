<?php
// core/BaseDeDonnees.php
// Correction du chemin vers la racine du projet
require_once __DIR__ . '/../configuration.php';

class BaseDeDonnees {
    private static ?PDO $instance = null;

    // Verrouillage de l'instanciation externe (Singleton)
    private function __construct() {}
    private function __clone() {}

    public static function obtenirInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Interception stricte pour éviter la fuite d'identifiants
                error_log("Échec de connexion SGBD : " . $e->getMessage());
                throw new RuntimeException("Service de données indisponible.");
            }
        }
        return self::$instance;
    }
}