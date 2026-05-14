-- =============================================================================
-- SCHÉMA DE LA BASE DE DONNÉES : Simulateur de Réseau IP (Standard WBS 1.1)
-- =============================================================================

-- Purge de l'existant
DROP TABLE IF EXISTS liaison_hote_hote CASCADE;
DROP TABLE IF EXISTS liaison_hote_interface CASCADE;
DROP TABLE IF EXISTS liaison_interface_interface CASCADE;
DROP TABLE IF EXISTS liaison_interface_switch CASCADE;
DROP TABLE IF EXISTS liaison_hote_switch CASCADE;
DROP TABLE IF EXISTS route_statique CASCADE;
DROP TABLE IF EXISTS interface_routeur CASCADE;
DROP TABLE IF EXISTS hote CASCADE;
DROP TABLE IF EXISTS sous_reseau CASCADE;
DROP TABLE IF EXISTS switch CASCADE;
DROP TABLE IF EXISTS routeur CASCADE;
DROP TABLE IF EXISTS scenario CASCADE;
DROP TABLE IF EXISTS utilisateur CASCADE;

-- =============================================================================
-- TABLES PRINCIPALES (SANS DÉPENDANCES)
-- =============================================================================

CREATE TABLE utilisateur (
    id SERIAL PRIMARY KEY,
    identifiant VARCHAR(50) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(64) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'membre' CHECK (role IN ('admin', 'membre'))
);

-- =============================================================================
-- TABLES AVEC DÉPENDANCE DE NIVEAU 1
-- =============================================================================

CREATE TABLE scenario (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    utilisateur_id INT NOT NULL REFERENCES utilisateur(id) ON DELETE CASCADE
);

-- =============================================================================
-- TABLES AVEC DÉPENDANCE DE NIVEAU 2 (LIÉES AU SCÉNARIO)
-- =============================================================================

CREATE TABLE routeur (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    pos_x DOUBLE PRECISION DEFAULT 0,
    pos_y DOUBLE PRECISION DEFAULT 0,
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE
);

CREATE TABLE switch (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    pos_x DOUBLE PRECISION DEFAULT 0,
    pos_y DOUBLE PRECISION DEFAULT 0,
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE
);

CREATE TABLE sous_reseau (
    id SERIAL PRIMARY KEY,
    bloc_cidr CIDR NOT NULL,
    nom VARCHAR(50),
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE
);

-- =============================================================================
-- TABLES AVEC DÉPENDANCE DE NIVEAU 3 (COMPOSANTS D'ÉQUIPEMENTS)
-- =============================================================================

CREATE TABLE interface_routeur (
    id SERIAL PRIMARY KEY,
    adresse_ip INET NOT NULL,
    masque INT NOT NULL CHECK (masque >= 0 AND masque <= 32),
    nom VARCHAR(50) NOT NULL,
    routeur_id INT NOT NULL REFERENCES routeur(id) ON DELETE CASCADE
);

CREATE TABLE route_statique (
    id SERIAL PRIMARY KEY,
    reseau_dest INET NOT NULL,
    masque_dest INT NOT NULL CHECK (masque_dest >= 0 AND masque_dest <= 32),
    next_hop INET NOT NULL,
    routeur_id INT NOT NULL REFERENCES routeur(id) ON DELETE CASCADE
);

CREATE TABLE hote (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    nom_interface VARCHAR(50) DEFAULT 'eth0',
    adresse_ip INET,
    passerelle_ip INET,
    pos_x DOUBLE PRECISION DEFAULT 0,
    pos_y DOUBLE PRECISION DEFAULT 0,
    sous_reseau_id INT REFERENCES sous_reseau(id) ON DELETE SET NULL,
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE
);

-- =============================================================================
-- TABLES DE LIAISON (RELATIONS N:M)
-- =============================================================================

CREATE TABLE liaison_hote_switch (
    switch_id INT NOT NULL REFERENCES switch(id) ON DELETE CASCADE,
    hote_id INT NOT NULL REFERENCES hote(id) ON DELETE CASCADE,
    PRIMARY KEY (switch_id, hote_id)
);

CREATE TABLE liaison_interface_switch (
    interface_id INT NOT NULL REFERENCES interface_routeur(id) ON DELETE CASCADE,
    switch_id INT NOT NULL REFERENCES switch(id) ON DELETE CASCADE,
    PRIMARY KEY (interface_id, switch_id)
);

CREATE TABLE liaison_interface_interface (
    interface_id INT NOT NULL REFERENCES interface_routeur(id) ON DELETE CASCADE,
    interface_1_id INT NOT NULL REFERENCES interface_routeur(id) ON DELETE CASCADE,
    PRIMARY KEY (interface_id, interface_1_id),
    CHECK (interface_id <> interface_1_id)
);

CREATE TABLE liaison_hote_interface (
    hote_id INT NOT NULL REFERENCES hote(id) ON DELETE CASCADE,
    interface_id INT NOT NULL REFERENCES interface_routeur(id) ON DELETE CASCADE,
    PRIMARY KEY (hote_id, interface_id)
);

CREATE TABLE liaison_hote_hote (
    hote_1_id INT NOT NULL REFERENCES hote(id) ON DELETE CASCADE,
    hote_2_id INT NOT NULL REFERENCES hote(id) ON DELETE CASCADE,
    PRIMARY KEY (hote_1_id, hote_2_id),
    CHECK (hote_1_id <> hote_2_id)
);

-- =============================================================================
-- INDEXATION D'OPTIMISATION (CLÉS ÉTRANGÈRES)
-- =============================================================================

CREATE INDEX idx_scenario_utilisateur ON scenario(utilisateur_id);
CREATE INDEX idx_routeur_scenario ON routeur(scenario_id);
CREATE INDEX idx_interface_routeur_fk ON interface_routeur(routeur_id);
CREATE INDEX idx_route_statique_routeur ON route_statique(routeur_id);
CREATE INDEX idx_switch_scenario ON switch(scenario_id);
CREATE INDEX idx_sous_reseau_scenario ON sous_reseau(scenario_id);
CREATE INDEX idx_hote_scenario ON hote(scenario_id);
CREATE INDEX idx_hote_sous_reseau ON hote(sous_reseau_id);

-- =============================================================================
-- INJECTIONS D'AMORÇAGE (SEEDING)
-- =============================================================================

INSERT INTO utilisateur (identifiant, mot_de_passe_hash, role)
VALUES 
('admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin'),
('utilisateur', 'a393fa2c34ab1dc5c3b0bef6c7b50f23c63a8ec19e00f72ee4d24a5b63e2e48d', 'membre');