-- ==============================================================================
-- RÉINITIALISATION DU SCHÉMA (IDEMPOTENCE)
-- ==============================================================================
DROP TABLE IF EXISTS hote CASCADE;
DROP TABLE IF EXISTS switch CASCADE;
DROP TABLE IF EXISTS sous_reseau CASCADE;
DROP TABLE IF EXISTS route_statique CASCADE;
DROP TABLE IF EXISTS interface_routeur CASCADE;
DROP TABLE IF EXISTS routeur CASCADE;
DROP TABLE IF EXISTS scenario CASCADE;

-- ==============================================================================
-- CRÉATION DES ENTITÉS ET CONTRAINTES D'INTÉGRITÉ
-- ==============================================================================

-- Entité racine : Scénario
CREATE TABLE scenario (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    cree_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Entité : Routeur
CREATE TABLE routeur (
    id SERIAL PRIMARY KEY,
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    UNIQUE(scenario_id, nom)
);

-- Entité : Interface Routeur (WBS 1.3 : Cascade depuis Routeur)
CREATE TABLE interface_routeur (
    id SERIAL PRIMARY KEY,
    routeur_id INT NOT NULL REFERENCES routeur(id) ON DELETE CASCADE,
    adresse_ip INET NOT NULL, -- WBS 1.2 : Type INET
    masque CIDR NOT NULL,     -- WBS 1.2 : Type CIDR (valide nativement 0-32)
    UNIQUE(routeur_id, adresse_ip)
);

-- Entité : Route Statique (WBS 1.3 : Cascade depuis Routeur)
CREATE TABLE route_statique (
    id SERIAL PRIMARY KEY,
    routeur_id INT NOT NULL REFERENCES routeur(id) ON DELETE CASCADE,
    reseau_cible CIDR NOT NULL,
    prochain_saut INET NOT NULL
);

-- Entité : Sous-réseau
CREATE TABLE sous_reseau (
    id SERIAL PRIMARY KEY,
    scenario_id INT NOT NULL REFERENCES scenario(id) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    bloc_cidr CIDR NOT NULL,
    UNIQUE(scenario_id, bloc_cidr)
);

-- Entité : Switch
CREATE TABLE switch (
    id SERIAL PRIMARY KEY,
    sous_reseau_id INT NOT NULL REFERENCES sous_reseau(id) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL
);

-- Entité : Hôte
CREATE TABLE hote (
    id SERIAL PRIMARY KEY,
    sous_reseau_id INT NOT NULL REFERENCES sous_reseau(id) ON DELETE CASCADE,
    nom VARCHAR(50) NOT NULL,
    adresse_ip INET NOT NULL,
    passerelle_defaut INET,
    UNIQUE(sous_reseau_id, adresse_ip)
);


-- ==============================================================================
-- WBS 1.2 : CONTRAINTES STRICTES INET/CIDR (RESTRICTION IPV4 ET MASQUE 0-32)
-- ==============================================================================

-- Entité : Interface Routeur
ALTER TABLE interface_routeur 
    ADD CONSTRAINT chk_interface_ipv4 CHECK (family(adresse_ip) = 4),
    ADD CONSTRAINT chk_masque_ipv4 CHECK (family(masque) = 4),
    ADD CONSTRAINT chk_masque_limite CHECK (masklen(masque) >= 0 AND masklen(masque) <= 32);

-- Entité : Route Statique
ALTER TABLE route_statique 
    ADD CONSTRAINT chk_reseau_ipv4 CHECK (family(reseau_cible) = 4),
    ADD CONSTRAINT chk_reseau_limite CHECK (masklen(reseau_cible) >= 0 AND masklen(reseau_cible) <= 32),
    ADD CONSTRAINT chk_saut_ipv4 CHECK (family(prochain_saut) = 4);

-- Entité : Sous-réseau
ALTER TABLE sous_reseau 
    ADD CONSTRAINT chk_bloc_ipv4 CHECK (family(bloc_cidr) = 4),
    ADD CONSTRAINT chk_bloc_limite CHECK (masklen(bloc_cidr) >= 0 AND masklen(bloc_cidr) <= 32);

-- Entité : Hôte
ALTER TABLE hote 
    ADD CONSTRAINT chk_hote_ipv4 CHECK (family(adresse_ip) = 4),
    ADD CONSTRAINT chk_passerelle_ipv4 CHECK (passerelle_defaut IS NULL OR family(passerelle_defaut) = 4);