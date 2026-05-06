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
        <!-- Navigation par onglets -->
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
            <p class="texte-muet">Chargement...</p>
        </div>
    </aside>

    <!-- Canevas de topologie -->
    <section class="zone-dessin">
        <div id="network-canvas"></div>
        
        <!-- Légende flottante -->
        <div class="legende-canvas">
            <span class="item"><span class="symbole rect"></span> Routeur</span>
            <span class="item"><span class="symbole rect-arrondi" style="background:#f0f9ff; border-color:#bae6fd;"></span> Switch</span>
            <span class="item"><span class="symbole cercle"></span> Réseau</span>
            <span class="item"><span class="symbole rect-arrondi"></span> Hôte</span>
        </div>
    </section>
</div>

<!-- =========================================================
     COMPOSANTS UI : FENÊTRES MODALES (CONTEXTE ÉDITEUR)
     ========================================================= -->

<!-- 1. MODALE D'ÉDITION GÉNÉRIQUE DES ÉQUIPEMENTS (modal-edition) -->
<div id="modal-edition" class="modal-overlay hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-titre">Édition Équipement</h3>
            <button class="btn-close" onclick="fermerModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Champ Nom -->
            <div class="form-group">
                <label class="texte-muet text-sm mb-1 block">Nom</label>
                <input type="text" id="modal-input-nom" class="dark-input">
            </div>
            
            <hr class="modal-divider">
            
            <!-- Section Interfaces (Visible uniquement pour les routeurs) -->
            <div id="section-interfaces" style="display:none;">
                <h4 class="texte-muet text-sm mb-2">Interfaces actives (Routage)</h4>
                
                <!-- Conteneurs de rendu dynamique -->
                <div id="liste-interfaces-actives" class="mb-3"></div>
                <p id="liste-interfaces-vides" class="texte-muet text-sm mb-3" style="display:none; font-style:italic;">Aucune interface configurée.</p>
                
                <button id="btn-toggle-interface" class="btn-text-blue" onclick="toggleInterfaceForm()">
                    <span id="icon-toggle-interface">▶</span> + Ajouter une interface
                </button>
                
                <!-- Formulaire rétractable -->
                <div id="form-ajout-interface" class="hidden form-sous-section" style="margin-top:10px;">
                    <input type="text" id="nouvelle-int-nom" placeholder="Nom (ex: eth0)" class="dark-input mb-2" style="width:100%;">
                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <input type="text" id="nouvelle-int-ip" placeholder="IP (ex: 192.168.1.1)" class="dark-input" style="flex:2;">
                        <input type="number" id="nouvelle-int-masque" placeholder="CIDR (ex: 24)" class="dark-input" style="flex:1;" min="0" max="32">
                    </div>
                    <button class="btn-outline full-width" onclick="ajouterInterfaceRouteur()">Sauvegarder l'interface</button>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-text" onclick="fermerModal()">Fermer</button>
            <button class="btn-primary" onclick="sauvegarderEdition()">Appliquer les modifications</button>
        </div>
    </div>
</div>

<!-- 2. MODALE DE RÉSOLUTION D'AMBIGUÏTÉ DE PORT (modal-choix-interface) -->
<div id="modal-choix-interface" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; display:flex; justify-content:center; align-items:center;">
    <div style="background:#ffffff; padding:24px; border-radius:8px; width:450px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color:#1f2937; font-size:1.1rem; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">Résolution de port physique</h3>
        <p style="font-size:14px; color:#4b5563; margin:15px 0;">Ambiguïté matérielle détectée. Sélectionner l'interface cible pour l'établissement de la liaison de niveau 2/3 :</p>
        <select id="select-interface-cible" style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #d1d5db; border-radius:4px; font-family:monospace; font-size:13px;"></select>
        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button id="btn-annuler-interface" type="button" style="padding:8px 16px; border:1px solid #d1d5db; background:#f9fafb; color:#374151; border-radius:4px; cursor:pointer; font-weight:bold;">Annuler</button>
            <button id="btn-confirmer-interface" type="button" style="padding:8px 16px; border:none; background:#3b82f6; color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">Connecter</button>
        </div>
    </div>
</div>
<!-- 3. MODALE D'ALERTE SYSTÈME -->
<div id="modal-alerte" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; display:flex; justify-content:center; align-items:center;">
    <div style="background:#ffffff; padding:24px; border-radius:8px; width:400px; box-shadow:0 4px 10px rgba(0,0,0,0.2); text-align:center;">
        <h3 style="margin-top:0; color:#ef4444; font-size:1.1rem; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">Notification Système</h3>
        <p id="texte-alerte" style="font-size:14px; color:#4b5563; margin:20px 0; line-height:1.4;"></p>
        <button id="btn-fermer-alerte" type="button" style="padding:8px 24px; border:none; background:#ef4444; color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">OK</button>
    </div>
</div>

<!-- 4. MODALE DE CONFIRMATION D'ACTION -->
<div id="modal-confirmation" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; display:flex; justify-content:center; align-items:center;">
    <div style="background:#ffffff; padding:24px; border-radius:8px; width:400px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color:#f59e0b; font-size:1.1rem; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">Confirmation requise</h3>
        <p id="texte-confirmation" style="font-size:14px; color:#4b5563; margin:20px 0;"></p>
        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button id="btn-annuler-confirmation" type="button" style="padding:8px 16px; border:1px solid #d1d5db; background:#f9fafb; color:#374151; border-radius:4px; cursor:pointer; font-weight:bold;">Annuler</button>
            <button id="btn-valider-confirmation" type="button" style="padding:8px 16px; border:none; background:#f59e0b; color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">Confirmer</button>
        </div>
    </div>
</div>
<!-- 5. MODALE DE SAISIE DE TEXTE (Remplacement de prompt) -->
<div id="modal-saisie" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; display:flex; justify-content:center; align-items:center;">
    <div style="background:#ffffff; padding:24px; border-radius:8px; width:400px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color:#3b82f6; font-size:1.1rem; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">Saisie requise</h3>
        <label id="texte-saisie" style="display:block; font-size:14px; color:#4b5563; margin:15px 0 10px; font-weight:bold;"></label>
        <input type="text" id="input-saisie" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:4px; font-size:14px; margin-bottom:20px;">
        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button id="btn-annuler-saisie" type="button" style="padding:8px 16px; border:1px solid #d1d5db; background:#f9fafb; color:#374151; border-radius:4px; cursor:pointer; font-weight:bold;">Annuler</button>
            <button id="btn-valider-saisie" type="button" style="padding:8px 16px; border:none; background:#3b82f6; color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">Valider</button>
        </div>
    </div>
</div>
<script>
    const CURRENT_SCENARIO_ID = <?php echo $idScenario; ?>;
</script>