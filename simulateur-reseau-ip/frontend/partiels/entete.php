<?php
// =============================================================================
// PARTIEL : entete.php
// Auteur : Étudiant
// Description : En-tête commun à toutes les pages protégées.
//               Contient la navigation et l'identité visuelle.
// =============================================================================

require_once __DIR__ . '/../../backend/noyau/GestionnaireAuth.php';

$identifiant = GestionnaireAuth::getIdentifiant();
$role        = GestionnaireAuth::getRole();
$estAdmin    = GestionnaireAuth::estAdmin();

// Détermine la vue active pour la navigation
$vueActive   = $_GET['vue'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        $titres = [
            'tableau-de-bord' => 'Tableau de bord',
            'editeur'         => 'Éditeur de topologie',
        ];
        echo htmlspecialchars($titres[$vueActive] ?? 'Application') . ' — Simulateur Réseau IP';
        ?>
    </title>
    <link rel="stylesheet" href="/simulateur-reseau-ip/frontend/css/styles.css">
    <?php if (isset($cssSupplementaires)): foreach($cssSupplementaires as $css): ?>
    <link rel="stylesheet" href="<?= $css ?>">
    <?php endforeach; endif; ?>
    <?php if (isset($scriptsHead)): foreach($scriptsHead as $src): ?>
    <script src="<?= $src ?>"></script>
    <?php endforeach; endif; ?>
</head>
<body>

<header class="entete-app" role="banner">
    <div class="entete-gauche">
        <a href="/simulateur-reseau-ip/?vue=tableau-de-bord" class="logo-lien" aria-label="Accueil">
            <svg class="logo-svg" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <circle cx="20" cy="7" r="5" fill="var(--couleur-accent)"/>
                <circle cx="7" cy="30" r="5" fill="var(--couleur-accent-2)"/>
                <circle cx="33" cy="30" r="5" fill="var(--couleur-accent-2)"/>
                <line x1="20" y1="12" x2="7" y2="25" stroke="var(--couleur-lien)" stroke-width="1.5"/>
                <line x1="20" y1="12" x2="33" y2="25" stroke="var(--couleur-lien)" stroke-width="1.5"/>
                <line x1="7" y1="30" x2="33" y2="30" stroke="var(--couleur-lien)" stroke-width="1.5" stroke-dasharray="3 2"/>
            </svg>
            <span class="logo-texte">Simulateur <strong>IP</strong></span>
        </a>

        <nav class="nav-principale" aria-label="Navigation principale">
            <a
                href="/simulateur-reseau-ip/?vue=tableau-de-bord"
                class="nav-lien <?= $vueActive === 'tableau-de-bord' ? 'actif' : '' ?>"
            >
                Scénarios
            </a>
            <?php if ($vueActive === 'editeur' && isset($_GET['id_scenario'])): ?>
                <span class="nav-separateur">›</span>
                <span class="nav-lien actif">Éditeur</span>
            <?php endif; ?>
        </nav>
    </div>

    <div class="entete-droite">
        <!-- Aide -->
        <button class="bouton-aide" id="btn-aide" aria-label="Aide" title="Aide">?</button>

        <!-- Menu utilisateur -->
        <div class="menu-utilisateur" id="menu-utilisateur">
            <button class="menu-utilisateur-bouton" aria-expanded="false" aria-haspopup="true">
                <span class="utilisateur-avatar">
                    <?= strtoupper(substr($identifiant, 0, 1)) ?>
                </span>
                <span class="utilisateur-nom"><?= htmlspecialchars($identifiant) ?></span>
                <span class="badge-role badge-<?= $role ?>"><?= $role ?></span>
                <span class="fleche-menu">▾</span>
            </button>
            <div class="menu-utilisateur-dropdown" role="menu">
                <?php if ($estAdmin): ?>
                    <a href="/simulateur-reseau-ip/?vue=tableau-de-bord&panel=admin" class="menu-item" role="menuitem">
                        ⚙ Administration
                    </a>
                    <div class="menu-separateur"></div>
                <?php endif; ?>
                <a href="/simulateur-reseau-ip/?action=deconnecter" class="menu-item menu-item-danger" role="menuitem">
                    ⎋ Se déconnecter
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Modal d'aide -->
<div class="modal-fond" id="modal-aide" aria-hidden="true">
    <div class="modal modal-large" role="dialog" aria-labelledby="aide-titre">
        <div class="modal-entete">
            <h2 class="modal-titre" id="aide-titre">Aide — Guide d'utilisation</h2>
            <button class="modal-fermer" data-modal="modal-aide">&times;</button>
        </div>
        <div class="modal-corps aide-corps">
            <div class="aide-section">
                <h3>🗺 Gestion des scénarios</h3>
                <p>Créez des scénarios réseau depuis le tableau de bord. Chaque scénario est isolé et ne peut être vu que par vous.</p>
            </div>
            <div class="aide-section">
                <h3>🔲 Ajouter des équipements</h3>
                <p>Utilisez la barre d'outils gauche pour ajouter routeurs, switchs, hôtes et réseaux. Cliquez sur un équipement pour voir ses propriétés.</p>
            </div>
            <div class="aide-section">
                <h3>🔗 Câblage</h3>
                <p>Reliez les équipements entre eux : Hôte→Switch, Interface de routeur→Switch, ou Interface→Interface (liaison point-à-point).</p>
            </div>
            <div class="aide-section">
                <h3>▶ Simulation de paquet</h3>
                <p>Entrez les IPs source et destination dans la barre gauche, puis lancez la simulation. Le paquet est animé sur le graphe avec l'en-tête IP détaillé à chaque saut.</p>
            </div>
            <div class="aide-section">
                <h3>⚠ Hôte désactivé</h3>
                <p>Un hôte non rattaché à un réseau est automatiquement désactivé et ne peut pas émettre de paquets.</p>
            </div>
            <div class="aide-section">
                <h3>🔴 Erreurs de routage</h3>
                <p>Si le TTL expire ou qu'aucune route n'est trouvée, le paquet s'affiche en rouge avec le message d'erreur correspondant.</p>
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-principal" data-fermer="modal-aide">Compris</button>
        </div>
    </div>
</div>

<?php if ($estAdmin): ?>
    <?php require_once __DIR__ . '/outils-admin.php'; ?>
<?php endif; ?>

<script>
// --- Gestion du menu utilisateur ---
const menuUtilisateur = document.getElementById('menu-utilisateur');
const boutonMenu = menuUtilisateur?.querySelector('.menu-utilisateur-bouton');

boutonMenu?.addEventListener('click', function() {
    const estOuvert = this.getAttribute('aria-expanded') === 'true';
    this.setAttribute('aria-expanded', !estOuvert);
    menuUtilisateur.classList.toggle('ouvert');
});

// Fermeture du menu au clic extérieur
document.addEventListener('click', function(e) {
    if (!menuUtilisateur?.contains(e.target)) {
        boutonMenu?.setAttribute('aria-expanded', 'false');
        menuUtilisateur?.classList.remove('ouvert');
    }
});

// --- Gestion des modals (fermeture générique) ---
document.querySelectorAll('[data-modal], [data-fermer]').forEach(btn => {
    btn.addEventListener('click', function() {
        const idModal = this.dataset.modal || this.dataset.fermer;
        const modal = document.getElementById(idModal);
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('actif');
        }
    });
});

// Ouverture de l'aide
document.getElementById('btn-aide')?.addEventListener('click', function() {
    const modal = document.getElementById('modal-aide');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('actif');
});

// Fermeture des modals au clic sur le fond
document.querySelectorAll('.modal-fond').forEach(fond => {
    fond.addEventListener('click', function(e) {
        if (e.target === this) {
            this.setAttribute('aria-hidden', 'true');
            this.classList.remove('actif');
        }
    });
});
</script>
