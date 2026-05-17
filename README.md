# Simulateur de Réseau IP (TCP/IP)

Bienvenue sur le projet **Simulateur de Réseau IP**, un outil pédagogique et technique conçu pour visualiser et comprendre les mécanismes fondamentaux du routage de niveau 3 et de l'acheminement des datagrammes IP.

---

## Contexte du Projet

Ce projet a été réalisé dans le cadre du module **Bureau d'Études (BE)** du cursus de **L3 IRT** (Informatique, Réseaux et Télécoms) à l'Université de Toulouse. 

L'objectif principal était de concrétiser les concepts théoriques des protocoles TCP/IP en développant une application capable de modéliser une infrastructure réseau complète. Le projet repose sur des standards modernes de développement : **Clean Code**, architecture modulaire et respect strict des spécifications de la **RFC 791**.

## Fonctionnalités Clés

- **Gestion d'Infrastructure :** Création et configuration d'hôtes, de routeurs et de sous-réseaux.
- **Routage Statique :** Implémentation d'algorithmes d'acheminement basés sur des tables de routage configurables.
- **Visualisation Dynamique :** Utilisation de la bibliothèque `Vis.js` pour une interface interactive affichant la topologie du réseau.
- **Moteur de Simulation :** Simulation pas à pas de l'envoi d'un paquet IP, avec affichage détaillé de l'en-tête (TTL, Identifiant, Flags DF, etc.).

---

## Installation

Le projet utilise une stack **LAMP/WAMP** classique (Apache, PostgreSQL, PHP).

### Prérequis
- Un serveur local (WAMP, XAMPP, ou MAMP).
- PHP 7.4 ou supérieur.
- PostgreSQL.

### Étapes d'installation

1.  **Clonage du dépôt :**
    ```bash
    git clone https://github.com/tabr307/BE-projet.git
    cd BE-projet
    ```

2.  **Configuration de la Base de Données :**
    - Installez PostgreSQL,
    - Créez une nouvelle base de données nommée `simulateur_reseau`.
    - Executez le script `database/schema-reseau.sql` fourni dans le dépôt.

3.  **Configuration du Backend :**
    - Modifiez le fichier de configuration (ex: `config/configuration.php`) avec vos identifiants locaux :
    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'simulateur_reseau');
    define('DB_USER', 'simu_user (par exemple)');
    define('DB_PASS', 'mdp');
    ```

4.  **Lancement :**
    - Placez le dossier du projet dans votre répertoire `www/` ou `htdocs/`.
    - Accédez à l'application via `http://localhost:3000/public`.

---

## Guide de démarrage rapide

### 1. Créer votre premier compte
<img width="2152" height="1306" alt="image" src="https://github.com/user-attachments/assets/b0e3f406-2055-4fce-8f5d-ce9d19328614" />
- Sur la page d'accueil, cliquez sur **Inscription**.
- Remplissez vos informations (Nom et Mot de passe).
- Une fois inscrit, connectez-vous pour accéder au tableau de bord.

### 2. Créer votre premier réseau
<img width="2152" height="1306" alt="image" src="https://github.com/user-attachments/assets/20a5f7fe-8876-4ec3-bd55-87140d8232b8" />
- **Étape 1 : Créer un scénario.** Cliquez sur "Nouveau Scénario" pour initialiser un espace de travail vierge.
- **Étape 2 : Ajouter des nœuds.** Utilisez le panneau latéral pour ajouter des **Hôtes**, des **Switchs** et des **Routeurs** sur la topologie.
- **Étape 3 : Configurer les interfaces.** Cliquez sur un équipement pour lui attribuer une adresse IP et un masque de sous-réseau, une interface ou une route.
- **Étape 4 : Établir les liaisons.** Connectez les équipements entre eux pour définir les segments réseaux.
<img width="2152" height="1306" alt="image" src="https://github.com/user-attachments/assets/bfc39b86-8005-4d0a-b723-5ec935288647" />

### 3. Lancer une simulation
- Cliquez sur le bouton **"Simuler"**.
- Sélectionnez un **Hôte Source** et un **Hôte Destination**.
- Observez le paquet se déplacer sur le graphe. À chaque saut (hop), les modifications de l'en-tête IP (décrémentation du TTL, vérification du checksum) sont re-calculées et affichées.
<img width="2152" height="1306" alt="image" src="https://github.com/user-attachments/assets/8ab629e0-1183-4db1-95ab-b51df18d66c1" />

---
*Projet développé dans le cadre de la formation STRI L3IRT - Université de Toulouse.*
