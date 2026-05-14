<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/configuration.php';
require_once __DIR__ . '/core/BaseDeDonnees.php';

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    $pdo->exec("ALTER TABLE hote ADD COLUMN IF NOT EXISTS nom_interface VARCHAR(50) DEFAULT 'eth0'");
    echo "SUCCESS: Colonne ajoutée";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
