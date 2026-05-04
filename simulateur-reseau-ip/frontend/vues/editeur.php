<?php
// =============================================================================
// VUE : editeur.php
// Auteur : Étudiant
// Description : Éditeur de topologie et simulateur de paquets IP.
//               Interface principale avec vis.js et panneau de configuration.
// =============================================================================

require_once __DIR__ . '/../../backend/noyau/GestionnaireAuth.php';
require_once __DIR__ . '/../../backend/modeles/Scenario.php';

GestionnaireAuth::exigerConnexion();

// CSS supplémentaires injectés dans le <head> via entete.php
$cssSupplementaires = [
    'https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css',
    '/simulateur-reseau-ip/frontend/css/editeur.css',
];

// vis.js chargé dans le <head> pour être disponible avant les scripts applicatifs
$scriptsHead = [
    'https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js',
];

$idUser     = GestionnaireAuth::getIdUtilisateur();
$idScenario = (int)($_GET['id_scenario'] ?? 0);

// Vérification d'appartenance du scénario
$modele = new Scenario();
if (!$idScenario || !$modele->appartientA($idScenario, $idUser)) {
    header('Location: /simulateur-reseau-ip/?vue=tableau-de-bord');
    exit;
}

// Chargement de la topologie initiale
$topologie = $modele->chargerTopologie($idScenario);

require_once __DIR__ . '/../partiels/entete.php';
?>

<!-- Données PHP injectées en JS pour initialiser vis.js sans requête AJAX initiale -->
<script>
    const SCENARIO_ID  = <?= $idScenario ?>;
    const TOPOLOGIE    = <?= json_encode($topologie, JSON_UNESCAPED_UNICODE) ?>;
    const API_BASE_URL = '/simulateur-reseau-ip/backend/api.php';
</script>

<div class="editeur-layout">

    <!-- ===================================================================
         BARRE D'OUTILS LATÉRALE GAUCHE
         =================================================================== -->
    <aside class="barre-outils" id="barre-outils">
        <div class="barre-outils-section">
            <h3 class="barre-outils-titre">Équipements</h3>
            <button class="outil-btn" id="outil-routeur" title="Ajouter un routeur (L3)">
                <span class="outil-icone">🔲</span>
                <span>Routeur</span>
                <span class="outil-plus">+</span>
            </button>
            <button class="outil-btn" id="outil-switch" title="Ajouter un switch (L2)">
                <span class="outil-icone">⬛</span>
                <span>Switch</span>
                <span class="outil-plus">+</span>
            </button>
            <button class="outil-btn" id="outil-hote" title="Ajouter un hôte terminal">
                <span class="outil-icone">💻</span>
                <span>Hôte</span>
                <span class="outil-plus">+</span>
            </button>
            <button class="outil-btn outil-btn-cable" id="outil-cable" title="Câbler deux équipements">
                <span class="outil-icone">🔗</span>
                <span>Câbler</span>
            </button>
        </div>

        <!-- Panneau réseaux créés -->
        <div class="barre-outils-section" id="section-reseaux">
            <h3 class="barre-outils-titre">Réseaux
                <button class="btn-ajouter-reseau-rapide" onclick="ouvrirModal('modal-reseau')"
                        title="Ajouter un réseau">+</button>
            </h3>
            <div id="liste-reseaux-barre">
                <p class="barre-reseaux-vide">Aucun réseau</p>
            </div>
        </div>

        <div class="barre-outils-section">
            <h3 class="barre-outils-titre">Simulation</h3>
            <div class="champ-groupe">
                <label class="champ-label">Source</label>
                <select id="sim-select-source" class="champ-input champ-sm"
                        onchange="syncSimIP('source', this.value)">
                    <option value="">-- Choisir un hôte --</option>
                </select>
                <input type="text" id="sim-ip-source" class="champ-input champ-sm sim-ip-manuel"
                       placeholder="ou saisir une IP" style="margin-top:0.3rem">
            </div>
            <div class="champ-groupe">
                <label class="champ-label">Destination</label>
                <select id="sim-select-dest" class="champ-input champ-sm"
                        onchange="syncSimIP('dest', this.value)">
                    <option value="">-- Choisir un hôte --</option>
                </select>
                <input type="text" id="sim-ip-dest" class="champ-input champ-sm sim-ip-manuel"
                       placeholder="ou saisir une IP" style="margin-top:0.3rem">
            </div>
            <button class="bouton bouton-principal bouton-plein" id="btn-simuler">
                ▶ Lancer
            </button>
            <button class="bouton bouton-secondaire bouton-plein" id="btn-reinit-sim" style="display:none">
                ↺ Réinitialiser
            </button>
        </div>

        <div class="barre-outils-section">
            <a href="/simulateur-reseau-ip/?vue=tableau-de-bord" class="bouton bouton-secondaire bouton-plein">
                ← Retour
            </a>
        </div>
    </aside>

    <!-- ===================================================================
         CANEVAS VIS.JS (topologie réseau)
         =================================================================== -->
    <div class="editeur-centre">
        <div id="canevas-topologie"></div>

        <!-- Légende (sans Réseau — non représenté visuellement sur le canvas) -->
        <div class="legende">
            <span class="legende-item"><span class="legende-couleur" style="background:#1E40AF"></span>Routeur</span>
            <span class="legende-item"><span class="legende-couleur" style="background:#5B21B6"></span>Switch</span>
            <span class="legende-item"><span class="legende-couleur" style="background:#14532D"></span>Hôte actif</span>
            <span class="legende-item"><span class="legende-couleur" style="background:#9CA3AF"></span>Hôte désactivé</span>
        </div>
    </div>

    <!-- ===================================================================
         PANNEAU LATÉRAL DROIT : Propriétés et simulation
         =================================================================== -->
    <aside class="panneau-proprietes" id="panneau-proprietes">
        <div class="panneau-entete">
            <h3 class="panneau-titre" id="panneau-titre">Propriétés</h3>
            <button class="panneau-fermer" id="btn-fermer-panneau" aria-label="Fermer">&times;</button>
        </div>
        <div class="panneau-corps" id="panneau-corps">
            <p class="panneau-vide">Sélectionnez un équipement dans le graphe pour voir ses propriétés.</p>
        </div>
    </aside>

</div>

<!-- ===================================================================
     PANNEAU DE RÉSULTATS DE SIMULATION (bas de page)
     =================================================================== -->
<div class="panneau-simulation" id="panneau-simulation" style="display:none">
    <div class="simulation-entete">
        <h3>Résultats de simulation</h3>
        <button class="panneau-fermer" id="btn-fermer-simulation">&times;</button>
    </div>
    <div class="simulation-corps" id="simulation-corps">
        <!-- Rempli dynamiquement par JS -->
    </div>
</div>

<!-- ===================================================================
     MODALS (formulaires d'ajout/modification)
     =================================================================== -->

<!-- Modal Routeur -->
<div class="modal-fond" id="modal-routeur" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre" id="modal-routeur-titre">Ajouter un routeur</h2>
            <button class="modal-fermer" data-modal="modal-routeur">&times;</button>
        </div>
        <div class="modal-corps">
            <input type="hidden" id="routeur-id">
            <div class="champ-groupe">
                <label for="routeur-nom" class="champ-label">Nom du routeur *</label>
                <input type="text" id="routeur-nom" class="champ-input" placeholder="ex: R1">
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-routeur">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-routeur">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Switch -->
<div class="modal-fond" id="modal-switch" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre" id="modal-switch-titre">Ajouter un switch</h2>
            <button class="modal-fermer" data-modal="modal-switch">&times;</button>
        </div>
        <div class="modal-corps">
            <input type="hidden" id="switch-id">
            <div class="champ-groupe">
                <label for="switch-nom" class="champ-label">Nom du switch *</label>
                <input type="text" id="switch-nom" class="champ-input" placeholder="ex: SW1">
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-switch">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-switch">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Hôte -->
<div class="modal-fond" id="modal-hote" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre" id="modal-hote-titre">Ajouter un hôte</h2>
            <button class="modal-fermer" data-modal="modal-hote">&times;</button>
        </div>
        <div class="modal-corps">
            <input type="hidden" id="hote-id">
            <div class="champ-groupe">
                <label for="hote-nom" class="champ-label">Nom *</label>
                <input type="text" id="hote-nom" class="champ-input" placeholder="ex: PC1">
            </div>
            <div class="grille-3">
                <div class="champ-groupe">
                    <label for="hote-ip" class="champ-label">Adresse IP *</label>
                    <input type="text" id="hote-ip" class="champ-input" placeholder="192.168.1.10">
                </div>
                <div class="champ-groupe">
                    <label for="hote-cidr" class="champ-label">Masque CIDR *</label>
                    <input type="number" id="hote-cidr" class="champ-input" placeholder="24" min="0" max="32">
                </div>
                <div class="champ-groupe">
                    <label for="hote-gw" class="champ-label">Passerelle *</label>
                    <input type="text" id="hote-gw" class="champ-input" placeholder="192.168.1.1">
                </div>
            </div>
            <div class="champ-groupe">
                <label for="hote-reseau" class="champ-label">Réseau (optionnel)</label>
                <select id="hote-reseau" class="champ-input" onchange="remplirCidrDepuisReseau(this.value)">
                    <option value="">-- Aucun réseau (hôte désactivé) --</option>
                    <!-- Rempli dynamiquement -->
                </select>
                <span class="champ-aide">Sélectionner un réseau remplit automatiquement le CIDR</span>
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-hote">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-hote">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Réseau -->
<div class="modal-fond" id="modal-reseau" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre">Ajouter un réseau</h2>
            <button class="modal-fermer" data-modal="modal-reseau">&times;</button>
        </div>
        <div class="modal-corps">
            <div class="champ-groupe">
                <label for="reseau-label" class="champ-label">Label *</label>
                <input type="text" id="reseau-label" class="champ-input" placeholder="ex: LAN Marketing">
            </div>
            <div class="grille-2">
                <div class="champ-groupe">
                    <label for="reseau-adresse" class="champ-label">Adresse réseau *</label>
                    <input type="text" id="reseau-adresse" class="champ-input" placeholder="192.168.1.0">
                </div>
                <div class="champ-groupe">
                    <label for="reseau-masque" class="champ-label">Masque CIDR *</label>
                    <input type="number" id="reseau-masque" class="champ-input" placeholder="24" min="0" max="32">
                </div>
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-reseau">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-reseau">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Interface -->
<div class="modal-fond" id="modal-interface" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre" id="modal-interface-titre">Ajouter une interface</h2>
            <button class="modal-fermer" data-modal="modal-interface">&times;</button>
        </div>
        <div class="modal-corps">
            <input type="hidden" id="interface-id">
            <input type="hidden" id="interface-routeur-id">
            <div class="champ-groupe">
                <label for="interface-nom" class="champ-label">Nom *</label>
                <input type="text" id="interface-nom" class="champ-input" placeholder="ex: GigabitEthernet0/0">
            </div>
            <div class="grille-2">
                <div class="champ-groupe">
                    <label for="interface-ip" class="champ-label">Adresse IP *</label>
                    <input type="text" id="interface-ip" class="champ-input" placeholder="10.0.0.1">
                </div>
                <div class="champ-groupe">
                    <label for="interface-masque" class="champ-label">Masque CIDR *</label>
                    <input type="number" id="interface-masque" class="champ-input" placeholder="30" min="0" max="32">
                </div>
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-interface">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-interface">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Route Statique -->
<div class="modal-fond" id="modal-route" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre">Ajouter une route statique</h2>
            <button class="modal-fermer" data-modal="modal-route">&times;</button>
        </div>
        <div class="modal-corps">
            <input type="hidden" id="route-routeur-id">
            <div class="grille-2">
                <div class="champ-groupe">
                    <label for="route-dest" class="champ-label">Réseau destination *</label>
                    <input type="text" id="route-dest" class="champ-input" placeholder="10.0.1.0">
                </div>
                <div class="champ-groupe">
                    <label for="route-masque" class="champ-label">Masque CIDR *</label>
                    <input type="number" id="route-masque" class="champ-input" placeholder="24" min="0" max="32">
                </div>
            </div>
            <div class="champ-groupe">
                <label for="route-nexthop" class="champ-label">Next-hop *</label>
                <input type="text" id="route-nexthop" class="champ-input" placeholder="10.0.0.2">
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-route">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-route">Valider</button>
        </div>
    </div>
</div>

<!-- Modal Câblage -->
<div class="modal-fond" id="modal-cable" aria-hidden="true">
    <div class="modal" role="dialog">
        <div class="modal-entete">
            <h2 class="modal-titre">Créer un câblage</h2>
            <button class="modal-fermer" data-modal="modal-cable">&times;</button>
        </div>
        <div class="modal-corps">
            <p class="info-texte">Sélectionnez le type de câblage à créer :</p>
            <div class="champ-groupe">
                <label for="cable-type" class="champ-label">Type *</label>
                <select id="cable-type" class="champ-input">
                    <option value="hote-switch">Hôte → Switch</option>
                    <option value="interface-switch">Interface Routeur → Switch</option>
                    <option value="interface-interface">Interface → Interface (liaison P2P)</option>
                </select>
            </div>
            <div id="cable-champs">
                <!-- Champs dynamiques selon le type -->
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-cable">Annuler</button>
            <button class="bouton bouton-principal" id="btn-valider-cable">Valider</button>
        </div>
    </div>
</div>

<!-- Scripts placés juste avant la fermeture body (dans pied-de-page) -->
<script src="/simulateur-reseau-ip/frontend/js/moteur-visuel.js"></script>
<script src="/simulateur-reseau-ip/frontend/js/application-client.js"></script>

<?php require_once __DIR__ . '/../partiels/pied-de-page.php'; ?>
