<?php
// =============================================================================
// CLASSE : BaseDeDonnees
// Auteur : Étudiant
// Description : Connexion PDO à PostgreSQL via le pattern Singleton.
//               Garantit une seule instance de connexion par requête HTTP.
// =============================================================================

require_once __DIR__ . '/../../configuration.php';

class BaseDeDonnees {

    /** @var PDO|null Instance unique de la connexion */
    private static ?PDO $instance = null;

    /**
     * Constructeur privé : empêche l'instanciation directe (pattern Singleton)
     */
    private function __construct() {}

    /**
     * Retourne l'instance unique de connexion PDO.
     * La connexion est créée lors du premier appel, puis réutilisée.
     *
     * @return PDO Instance de connexion à la base de données
     * @throws PDOException En cas d'échec de connexion
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Construction du DSN (Data Source Name) pour PostgreSQL
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            // Options PDO pour la sécurité et la robustesse
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lance des exceptions sur erreur
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retourne des tableaux associatifs
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Requêtes préparées natives (sécurité SQL)
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            } catch (PDOException $e) {
                // En production, on logue l'erreur sans l'exposer à l'utilisateur
                error_log('[ERREUR BDD] ' . $e->getMessage());
                throw new PDOException('Impossible de se connecter à la base de données.', (int)$e->getCode());
            }
        }

        return self::$instance;
    }

    /**
     * Empêche la copie de l'instance (pattern Singleton strict)
     */
    private function __clone() {}
}
?>
