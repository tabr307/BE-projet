<?php
/**
 * frontend/vues/editeur.php
 * Interface de l'éditeur conforme à la maquette et au MLD (Séparation Switch / Réseau).
 */
$idScenario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$nomUtilisateur = $_SESSION['utilisateur_nom'] ?? 'utilisateur';

if (!$idScenario) {
    echo "<div class='conteneur'><p>Erreur : ID de scénario manquant.</p></div>";
    return;
}
?>

<!-- En-tête de page (Header supérieur) -->
<header class="editeur-top-bar">
    <div class="breadcrumb">
        <a href="index.php?page=tableau-de-bord" class="lien-retour-discret">← Scénarios</a>
        <span class="separateur">›</span>
        <strong id="nom-scenario">Chargement...</strong>
    </div>
    <div class="actions-utilisateur">
        <button class="btn-simuler" onclick="lancerSimulation()">
            <span class="icon">▶</span> Simuler
        </button>
        <span class="username"><?php echo htmlspecialchars($nomUtilisateur); ?></span>
    </div>
</header>

<div class="editeur-layout">
    <!-- Barre latérale gauche -->
    <aside class="sidebar-config">
        <!-- Navigation par onglets (Ajout du Switch) -->
        <nav class="tabs-equipements">
            <button class="tab-btn active" onclick="switchEquipementTab('routeurs')">Routeurs</button>
            <button class="tab-btn" onclick="switchEquipementTab('switchs')">Switchs</button>
            <button class="tab-btn" onclick="switchEquipementTab('reseaux')">Réseaux</button>
            <button class="tab-btn" onclick="switchEquipementTab('hotes')">Hôtes</button>
            <button class="tab-btn" onclick="switchEquipementTab('routes')">Routes</button>
        </nav>

        <!-- Zone d'action : Ajouter un élément -->
        <div class="zone-action-ajout">
            <button class="btn-ajouter-dashed" onclick="ajouterElement()">
                + Ajouter <span id="label-type-ajout">routeur</span>
            </button>
        </div>

        <!-- Inventaire dynamique des équipements -->
        <div class="liste-objets" id="inventaire-objets">
            <!-- Injecté par moteur-visuel.js -->
            <p class="texte-muet">Chargement...</p>
        </div>
    </aside>

    <!-- Canevas de topologie -->
    <section class="zone-dessin">
        <div id="network-canvas"></div>
        
        <!-- Légende flottante (Mise à jour avec le Switch) -->
        <div class="legende-canvas">
            <span class="item"><span class="symbole rect"></span> Routeur</span>
            <span class="item"><span class="symbole rect-arrondi" style="background:#f0f9ff; border-color:#bae6fd;"></span> Switch</span>
            <span class="item"><span class="symbole cercle"></span> Réseau</span>
            <span class="item"><span class="symbole rect-arrondi"></span> Hôte</span>
        </div>
    </section>
</div>

<!-- Fenêtre Modale d'Édition -->
<div id="modal-edition" class="modal-overlay hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-titre">Routeur : R1</h3>
            <button class="btn-close" onclick="fermerModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Champ Nom -->
            <div class="form-group">
                <label class="texte-muet text-sm mb-1 block">Nom</label>
                <input type="text" id="modal-input-nom" class="dark-input">
            </div>
            
            <hr class="modal-divider">
            
            <!-- Section Interfaces (Visible uniquement pour les routeurs pour l'instant) -->
            <div id="section-interfaces">
                <h4 class="texte-muet text-sm mb-2">Interfaces</h4>
                <p class="texte-muet text-sm mb-3" id="liste-interfaces-vides">Aucune interface</p>
                
                <!-- Zone liste des interfaces (pour plus tard) -->
                <div id="liste-interfaces-actives" class="mb-3"></div>
                
                <button id="btn-toggle-interface" class="btn-text-blue" onclick="toggleInterfaceForm()">
                    <span id="icon-toggle-interface">▶</span> + Ajouter une interface
                </button>
                
                <!-- Formulaire rétractable -->
                <div id="form-ajout-interface" class="hidden form-sous-section">
                    <input type="text" id="nouvelle-int-nom" placeholder="eth0" class="dark-input mb-2">
                    <input type="text" id="nouvelle-int-ip" placeholder="192.168.1.1" class="dark-input mb-2">
                    <input type="text" id="nouvelle-int-masque" placeholder="24" class="dark-input mb-2">
                    <button class="btn-outline full-width" onclick="ajouterInterfaceRouteur()">Ajouter</button>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-text" onclick="fermerModal()">Fermer</button>
            <button class="btn-primary" onclick="sauvegarderEdition()">Sauvegarder</button>
        </div>
    </div>
</div>


<script>
    const CURRENT_SCENARIO_ID = <?php echo $idScenario; ?>;
</script>