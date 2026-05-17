<?php
// core/BaseDeDonnees.php

require_once __DIR__ . '/../config/configuration.php'; // Chargement des constantes de connexion (DB_HOST, DB_PORT, etc.)

class BaseDeDonnees {

    // Par défaut, pas de connexion active (pattern Singleton : une seule instance possible)
    private static ?PDO $instance = null;

    // Interdit la création d'une instance depuis l'extérieur avec "new BaseDeDonnees()"
    private function __construct() {}

    // Interdit la duplication de l'instance avec "clone"
    private function __clone() {}

    /**
     * Point d'accès unique à la connexion PDO.
     * Crée la connexion au premier appel, la réutilise ensuite.
     */
    public static function obtenirInstance(): PDO {

        // Si aucune connexion n'existe encore, on en crée une
        if (self::$instance === null) {

            // Construction de la chaîne de connexion PostgreSQL (DSN)
            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s",
                DB_HOST,  // Adresse du serveur PostgreSQL
                DB_PORT,  // Port d'écoute (5432 par défaut)
                DB_NAME   // Nom de la base de données
            );

            // Configuration du comportement de la connexion PDO
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Les erreurs SQL lèvent des exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Les résultats sont retournés en tableaux associatifs
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Requêtes préparées natives (plus sécurisé)
                PDO::ATTR_PERSISTENT         => true                    // Réutilise la connexion entre les requêtes (performances)
            ];

            // Tentative de connexion à la base de données
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

            } catch (PDOException $e) {
                // On journalise l'erreur technique sans l'exposer à l'utilisateur
                // pour éviter toute fuite d'identifiants ou d'infos sur l'infrastructure
                error_log("Échec de connexion SGBD : " . $e->getMessage());

                // Message générique retourné à l'application
                throw new RuntimeException("Service de données indisponible.");
            }
        }

        // Retourne la connexion existante ou nouvellement créée
        return self::$instance;
    }
}