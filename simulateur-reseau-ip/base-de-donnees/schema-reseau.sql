-- =============================================================================
-- SCHÉMA DE LA BASE DE DONNÉES : Simulateur de Réseau IP
-- Auteur : Étudiant
-- Description : Création des tables, contraintes et index pour le simulateur
-- =============================================================================

-- Suppression des tables existantes (ordre inverse des dépendances)
DROP TABLE IF EXISTS CABLER_INTERFACE_INTERFACE CASCADE;
DROP TABLE IF EXISTS CABLER_INTERFACE_SWITCH CASCADE;
DROP TABLE IF EXISTS CABLER_HOTE_SWITCH CASCADE;
DROP TABLE IF EXISTS ROUTE_STATIQUE CASCADE;
DROP TABLE IF EXISTS INTERFACE CASCADE;
DROP TABLE IF EXISTS HOTE CASCADE;
DROP TABLE IF EXISTS RESEAU CASCADE;
DROP TABLE IF EXISTS SWITCH CASCADE;
DROP TABLE IF EXISTS Routeur CASCADE;
DROP TABLE IF EXISTS SCENARIO CASCADE;
DROP TABLE IF EXISTS UTILISATEUR CASCADE;

-- =============================================================================
-- TABLE : UTILISATEUR
-- Stocke les comptes utilisateurs avec leur rôle (admin/membre)
-- =============================================================================
CREATE TABLE UTILISATEUR (
    id_user         SERIAL PRIMARY KEY,
    identifiant     VARCHAR(50) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(64) NOT NULL,  -- SHA-256 = 64 caractères hex
    role            VARCHAR(20) NOT NULL DEFAULT 'membre'
                    CHECK (role IN ('admin', 'membre'))
);

-- =============================================================================
-- TABLE : SCENARIO
-- Un scénario représente un projet réseau complet appartenant à un utilisateur
-- =============================================================================
CREATE TABLE SCENARIO (
    id_scenario     SERIAL PRIMARY KEY,
    nom_scenario    VARCHAR(100) NOT NULL,
    description     VARCHAR(255),
    id_user         INT NOT NULL REFERENCES UTILISATEUR(id_user) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : Routeur
-- Équipement de couche 3, possède des interfaces et des routes statiques
-- =============================================================================
CREATE TABLE Routeur (
    id_routeur      SERIAL PRIMARY KEY,
    nom             VARCHAR(50) NOT NULL,
    pos_x           DOUBLE PRECISION DEFAULT 0,
    pos_y           DOUBLE PRECISION DEFAULT 0,
    id_scenario     INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : INTERFACE
-- Interface réseau d'un routeur (adresse IP + masque)
-- =============================================================================
CREATE TABLE INTERFACE (
    id_interface    SERIAL PRIMARY KEY,
    adresse_ip      INET NOT NULL,
    masque          INT NOT NULL CHECK (masque >= 0 AND masque <= 32),
    nom             VARCHAR(50) NOT NULL,
    id_routeur      INT NOT NULL REFERENCES Routeur(id_routeur) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : ROUTE_STATIQUE
-- Routes de routage configurées manuellement sur un routeur
-- =============================================================================
CREATE TABLE ROUTE_STATIQUE (
    id_route        SERIAL PRIMARY KEY,
    reseau_dest     INET NOT NULL,
    masque_dest     INT NOT NULL CHECK (masque_dest >= 0 AND masque_dest <= 32),
    next_hop        INET NOT NULL,
    id_routeur      INT NOT NULL REFERENCES Routeur(id_routeur) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : SWITCH
-- Commutateur de couche 2 (pas de logique de routage)
-- =============================================================================
CREATE TABLE SWITCH (
    id_switch       SERIAL PRIMARY KEY,
    nom             VARCHAR(50) NOT NULL,
    pos_x           DOUBLE PRECISION DEFAULT 0,
    pos_y           DOUBLE PRECISION DEFAULT 0,
    id_scenario     INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : RESEAU
-- Sous-réseau logique défini par une adresse et un masque
-- =============================================================================
CREATE TABLE RESEAU (
    id_reseau       SERIAL PRIMARY KEY,
    adresse_reseau  INET NOT NULL,
    masque          INT NOT NULL CHECK (masque >= 0 AND masque <= 32),
    label           VARCHAR(50),
    id_scenario     INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : HOTE
-- Équipement terminal (PC, serveur) rattaché à un réseau et un scénario
-- =============================================================================
CREATE TABLE HOTE (
    id_hote         SERIAL PRIMARY KEY,
    nom             VARCHAR(50) NOT NULL,
    adresse_ip      INET,
    passerelle_ip   INET,
    pos_x           DOUBLE PRECISION DEFAULT 0,
    pos_y           DOUBLE PRECISION DEFAULT 0,
    id_reseau       INT REFERENCES RESEAU(id_reseau) ON DELETE SET NULL,
    id_scenario     INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE
);

-- =============================================================================
-- TABLE : CABLER_HOTE_SWITCH
-- Relation de câblage entre un hôte et un switch
-- =============================================================================
CREATE TABLE CABLER_HOTE_SWITCH (
    id_switch       INT NOT NULL REFERENCES SWITCH(id_switch) ON DELETE CASCADE,
    id_hote         INT NOT NULL REFERENCES HOTE(id_hote) ON DELETE CASCADE,
    PRIMARY KEY (id_switch, id_hote)
);

-- =============================================================================
-- TABLE : CABLER_INTERFACE_SWITCH
-- Relation de câblage entre une interface de routeur et un switch
-- =============================================================================
CREATE TABLE CABLER_INTERFACE_SWITCH (
    id_interface    INT NOT NULL REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    id_switch       INT NOT NULL REFERENCES SWITCH(id_switch) ON DELETE CASCADE,
    PRIMARY KEY (id_interface, id_switch)
);

-- =============================================================================
-- TABLE : CABLER_INTERFACE_INTERFACE
-- Liaison point-à-point entre deux interfaces de routeurs différents
-- =============================================================================
CREATE TABLE CABLER_INTERFACE_INTERFACE (
    id_interface    INT NOT NULL REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    id_interface_1  INT NOT NULL REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    PRIMARY KEY (id_interface, id_interface_1),
    -- Empêche une interface de se connecter à elle-même
    CHECK (id_interface <> id_interface_1)
);

-- =============================================================================
-- INDEX pour optimiser les requêtes fréquentes
-- =============================================================================
CREATE INDEX idx_scenario_user ON SCENARIO(id_user);
CREATE INDEX idx_routeur_scenario ON Routeur(id_scenario);
CREATE INDEX idx_interface_routeur ON INTERFACE(id_routeur);
CREATE INDEX idx_route_routeur ON ROUTE_STATIQUE(id_routeur);
CREATE INDEX idx_switch_scenario ON SWITCH(id_scenario);
CREATE INDEX idx_reseau_scenario ON RESEAU(id_scenario);
CREATE INDEX idx_hote_scenario ON HOTE(id_scenario);
CREATE INDEX idx_hote_reseau ON HOTE(id_reseau);

-- =============================================================================
-- DONNÉES INITIALES : Compte administrateur par défaut
-- Mot de passe : "admin123" hashé en SHA-256
-- =============================================================================
INSERT INTO UTILISATEUR (identifiant, mot_de_passe_hash, role)
VALUES (
    'admin',
    -- SHA-256 de "admin123"
    '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9',
    'admin'
);

-- Compte utilisateur de démonstration (identifiant: 'utilisateur', mot de passe: 'tech123')
INSERT INTO UTILISATEUR (identifiant, mot_de_passe_hash, role)
VALUES (
    'utilisateur',
    'a393fa2c34ab1dc5c3b0bef6c7b50f23c63a8ec19e00f72ee4d24a5b63e2e48d',
    'membre'
);
