<?php
// =============================================================================
// CONFIGURATION GLOBALE DE L'APPLICATION
// Auteur : Étudiant
// Description : Constantes de connexion à la BDD et paramètres globaux
// IMPORTANT : Ne jamais versionner ce fichier avec des credentials réels
// =============================================================================

// --- Paramètres de connexion PostgreSQL (XAMPP) ---
define('DB_HOST',     'localhost');
define('DB_PORT',     '5432');
define('DB_NAME',     'simulateur_reseau');
define('DB_USER',     'postgres');
define('DB_PASSWORD', 'sofian'); // À modifier selon votre installation

// --- Paramètres de l'application ---
define('APP_NAME',    'Simulateur Réseau IP');
define('APP_VERSION', '1.0.0');

// --- Durée de vie de la session (en secondes) ---
define('SESSION_DUREE', 3600); // 1 heure

// --- Limite de sécurité pour la simulation ---
define('SIMULATION_MAX_HOPS', 30);  // Arrêt forcé pour détecter les boucles
define('SIMULATION_TTL_INIT', 64);  // TTL initial d'un paquet IP standard

// --- Chemin racine de l'application ---
define('CHEMIN_RACINE', __DIR__);

// --- Mode débogage (false en production) ---
define('MODE_DEBUG', true);

// Affichage des erreurs PHP en mode débogage
if (MODE_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>
