-- ==============================================================================
-- RÉINITIALISATION DU SCHÉMA (IDEMPOTENCE)
-- ==============================================================================
DROP TABLE IF EXISTS CABLER_INTERFACE_INTERFACE CASCADE;
DROP TABLE IF EXISTS CABLER_INTERFACE_SWITCH CASCADE;
DROP TABLE IF EXISTS CABLER_HOTE_SWITCH CASCADE;
DROP TABLE IF EXISTS HOTE CASCADE;
DROP TABLE IF EXISTS SWITCH CASCADE;
DROP TABLE IF EXISTS RESEAU CASCADE;
DROP TABLE IF EXISTS ROUTE_STATIQUE CASCADE;
DROP TABLE IF EXISTS INTERFACE CASCADE;
DROP TABLE IF EXISTS Routeur CASCADE;
DROP TABLE IF EXISTS SCENARIO CASCADE;
DROP TABLE IF EXISTS UTILISATEUR CASCADE;

-- ==============================================================================
-- CRÉATION DES ENTITÉS DE BASE
-- ==============================================================================

CREATE TABLE UTILISATEUR (
    id_user SERIAL PRIMARY KEY,
    identifiant VARCHAR(50) UNIQUE NOT NULL,
    mot_de_passe_hash VARCHAR(64) NOT NULL, 
    role VARCHAR(50) DEFAULT 'classique' CHECK (role IN ('classique', 'admin'))
);

CREATE TABLE SCENARIO (
    id_scenario SERIAL PRIMARY KEY,
    id_user INT NOT NULL REFERENCES UTILISATEUR(id_user) ON DELETE CASCADE,
    nom_scenario VARCHAR(50) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE Routeur (
    id_routeur SERIAL PRIMARY KEY,
    id_scenario INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    pos_x DOUBLE PRECISION DEFAULT 0, 
    pos_y DOUBLE PRECISION DEFAULT 0,
    UNIQUE(id_scenario, nom)
);

CREATE TABLE INTERFACE (
    id_interface SERIAL PRIMARY KEY,
    id_routeur INT NOT NULL REFERENCES Routeur(id_routeur) ON DELETE CASCADE,
    adresse_ip INET NOT NULL, 
    masque INT NOT NULL,    
    nom VARCHAR(50) DEFAULT 'eth0',
    UNIQUE(id_routeur, adresse_ip)
);

CREATE TABLE ROUTE_STATIQUE (
    id_route SERIAL PRIMARY KEY,
    id_routeur INT NOT NULL REFERENCES Routeur(id_routeur) ON DELETE CASCADE,
    reseau_dest INET NOT NULL,
    masque_dest INT NOT NULL,
    next_hop INET NOT NULL
);

CREATE TABLE RESEAU (
    id_reseau SERIAL PRIMARY KEY,
    id_scenario INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE,
    adresse_reseau INET NOT NULL,
    masque INT NOT NULL,
    label VARCHAR(50) NOT NULL,
    UNIQUE(id_scenario, adresse_reseau)
);

CREATE TABLE SWITCH (
    id_switch SERIAL PRIMARY KEY,
    id_scenario INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    pos_x DOUBLE PRECISION DEFAULT 0,
    pos_y DOUBLE PRECISION DEFAULT 0
);

CREATE TABLE HOTE (
    id_hote SERIAL PRIMARY KEY,
    id_reseau INT REFERENCES RESEAU(id_reseau) ON DELETE SET NULL,
    id_scenario INT NOT NULL REFERENCES SCENARIO(id_scenario) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    adresse_ip INET NOT NULL,
    passerelle_ip INET,
    pos_x DOUBLE PRECISION DEFAULT 0,
    pos_y DOUBLE PRECISION DEFAULT 0,
    UNIQUE(id_scenario, adresse_ip)
);

-- ==============================================================================
-- TABLES DE CÂBLAGE (LIAISONS PHYSIQUES)
-- ==============================================================================

-- Liaison entre un Hôte et un Switch
CREATE TABLE CABLER_HOTE_SWITCH (
    id_switch INT REFERENCES SWITCH(id_switch) ON DELETE CASCADE,
    id_hote INT REFERENCES HOTE(id_hote) ON DELETE CASCADE,
    PRIMARY KEY (id_switch, id_hote)
);

-- Liaison entre une Interface de Routeur et un Switch
CREATE TABLE CABLER_INTERFACE_SWITCH (
    id_interface INT REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    id_switch INT REFERENCES SWITCH(id_switch) ON DELETE CASCADE,
    PRIMARY KEY (id_interface, id_switch)
);

-- Liaison directe entre deux Interfaces de Routeurs
CREATE TABLE CABLER_INTERFACE_INTERFACE (
    id_interface INT REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    id_interface_1 INT REFERENCES INTERFACE(id_interface) ON DELETE CASCADE,
    PRIMARY KEY (id_interface, id_interface_1),
    CONSTRAINT no_self_connect CHECK (id_interface <> id_interface_1)
);

-- ==============================================================================
-- CONTRAINTES STRICTES IPV4 ET MASQUES
-- ==============================================================================

ALTER TABLE INTERFACE 
    ADD CONSTRAINT chk_interface_ipv4 CHECK (family(adresse_ip) = 4),
    ADD CONSTRAINT chk_masque_limite CHECK (masque >= 0 AND masque <= 32);

ALTER TABLE ROUTE_STATIQUE 
    ADD CONSTRAINT chk_reseau_dest_ipv4 CHECK (family(reseau_dest) = 4),
    ADD CONSTRAINT chk_masque_dest_limite CHECK (masque_dest >= 0 AND masque_dest <= 32),
    ADD CONSTRAINT chk_saut_ipv4 CHECK (family(next_hop) = 4);

ALTER TABLE RESEAU 
    ADD CONSTRAINT chk_adresse_reseau_ipv4 CHECK (family(adresse_reseau) = 4),
    ADD CONSTRAINT chk_masque_reseau_limite CHECK (masque >= 0 AND masque <= 32);

ALTER TABLE HOTE 
    ADD CONSTRAINT chk_hote_ipv4 CHECK (family(adresse_ip) = 4),
    ADD CONSTRAINT chk_passerelle_ipv4 CHECK (passerelle_ip IS NULL OR family(passerelle_ip) = 4);

-- ==============================================================================
-- INITIALISATION
-- ==============================================================================
INSERT INTO UTILISATEUR (identifiant, mot_de_passe_hash, role) 
VALUES ('admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'admin');


ALTER TABLE RESEAU ADD COLUMN pos_x DOUBLE PRECISION DEFAULT 0;
ALTER TABLE RESEAU ADD COLUMN pos_y DOUBLE PRECISION DEFAULT 0;