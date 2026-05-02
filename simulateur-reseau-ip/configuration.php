<?php
// configuration.php
$cheminEnv = __DIR__ . '/.env';

if (!file_exists($cheminEnv)) {
    throw new RuntimeException("Erreur critique : Fichier d'environnement introuvable.");
}

$variablesEnv = parse_ini_file($cheminEnv);

if ($variablesEnv === false) {
    throw new RuntimeException("Erreur critique : Échec du parsing. Format INI invalide.");
}

// Projection en constantes immuables
define('DB_HOST', $variablesEnv['DB_HOST']);
define('DB_PORT', (int)$variablesEnv['DB_PORT']);
define('DB_NAME', $variablesEnv['DB_NAME']);
define('DB_USER', $variablesEnv['DB_USER']);
define('DB_PASS', $variablesEnv['DB_PASS']);