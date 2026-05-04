<?php
// =============================================================================
// VUE : connexion.php
// Auteur : Étudiant
// Description : Page de connexion/inscription unifiée avec onglets.
// =============================================================================

require_once __DIR__ . '/../../configuration.php';
require_once __DIR__ . '/../../backend/noyau/GestionnaireAuth.php';
require_once __DIR__ . '/../../backend/modeles/Utilisateur.php';

if (GestionnaireAuth::estConnecte()) {
    header('Location: /simulateur-reseau-ip/?vue=tableau-de-bord');
    exit;
}

$erreurConnexion  = isset($_GET['erreur'])      && $_GET['erreur']      === '1';
$deconnecteRecent = isset($_GET['deconnecte'])  && $_GET['deconnecte']  === '1';
$ongletActif      = isset($_GET['onglet'])       ? $_GET['onglet']       : 'connexion';

// Traitement inscription depuis cet écran
$erreurInscription = '';
$succesInscription = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inscription') {
    $ongletActif = 'inscription';
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mdp         = $_POST['mot_de_passe'] ?? '';
    $confirm     = $_POST['confirmer_mot_de_passe'] ?? '';

    if (strlen($identifiant) < 3 || strlen($identifiant) > 50) {
        $erreurInscription = "L'identifiant doit contenir entre 3 et 50 caractères.";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $identifiant)) {
        $erreurInscription = "Identifiant : lettres, chiffres, tirets et underscores uniquement.";
    } elseif (strlen($mdp) < 6) {
        $erreurInscription = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($mdp !== $confirm) {
        $erreurInscription = "Les mots de passe ne correspondent pas.";
    } else {
        $modele   = new Utilisateur();
        $resultat = $modele->creer($identifiant, $mdp, 'membre');
        if ($resultat['succes']) {
            $succesInscription = true;
            $ongletActif = 'connexion';
        } else {
            $erreurInscription = $resultat['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulateur Réseau IP</title>
    <link rel="stylesheet" href="/simulateur-reseau-ip/frontend/css/styles.css">
    <link rel="stylesheet" href="/simulateur-reseau-ip/frontend/css/auth.css">
</head>
<body class="page-auth">

<div class="auth-conteneur">

    <!-- Logo -->
    <div class="auth-logo">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="24" cy="8"  r="6" fill="#2563EB"/>
            <circle cx="8"  cy="38" r="6" fill="#1D4ED8"/>
            <circle cx="40" cy="38" r="6" fill="#1D4ED8"/>
            <line x1="24" y1="14" x2="8"  y2="32" stroke="#2563EB" stroke-width="2"/>
            <line x1="24" y1="14" x2="40" y2="32" stroke="#2563EB" stroke-width="2"/>
            <line x1="8"  y1="38" x2="40" y2="38" stroke="#93C5FD" stroke-width="2" stroke-dasharray="5 3"/>
        </svg>
        <div class="auth-logo-texte">
            <span class="auth-app-nom">SimRéseau IP</span>
            <span class="auth-app-desc">Simulateur de topologie et de routage</span>
        </div>
    </div>

    <!-- Carte principale -->
    <div class="auth-carte">

        <!-- Message succès inscription -->
        <?php if ($succesInscription): ?>
        <div class="auth-banner-succes">
            ✓ Compte créé avec succès ! Connectez-vous ci-dessous.
        </div>
        <?php endif; ?>

        <!-- Onglets -->
        <div class="auth-onglets">
            <button class="auth-onglet <?= $ongletActif === 'connexion' ? 'actif' : '' ?>"
                    data-onglet="connexion" type="button">
                Connexion
            </button>
            <button class="auth-onglet <?= $ongletActif === 'inscription' ? 'actif' : '' ?>"
                    data-onglet="inscription" type="button">
                Inscription
            </button>
        </div>

        <!-- ================================================================
             PANNEAU CONNEXION
             ================================================================ -->
        <div class="auth-panneau <?= $ongletActif === 'connexion' ? 'actif' : '' ?>" id="panneau-connexion">

            <?php if ($erreurConnexion): ?>
            <div class="auth-alerte auth-alerte-erreur">
                ⚠ Identifiant ou mot de passe incorrect.
            </div>
            <?php endif; ?>

            <?php if ($deconnecteRecent): ?>
            <div class="auth-alerte auth-alerte-info">
                ✓ Vous avez été déconnecté avec succès.
            </div>
            <?php endif; ?>

            <form method="POST" action="/simulateur-reseau-ip/" novalidate>
                <input type="hidden" name="action" value="connexion">

                <div class="auth-champ">
                    <label for="identifiant-co" class="auth-label">IDENTIFIANT</label>
                    <input type="text" id="identifiant-co" name="identifiant"
                           class="auth-input <?= $erreurConnexion ? 'erreur' : '' ?>"
                           placeholder="votre identifiant"
                           autocomplete="username" required autofocus>
                </div>

                <div class="auth-champ">
                    <label for="mot-de-passe-co" class="auth-label">MOT DE PASSE</label>
                    <input type="password" id="mot-de-passe-co" name="mot_de_passe"
                           class="auth-input <?= $erreurConnexion ? 'erreur' : '' ?>"
                           placeholder="••••••••"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="auth-bouton">Se connecter</button>
            </form>
        </div>

        <!-- ================================================================
             PANNEAU INSCRIPTION
             ================================================================ -->
        <div class="auth-panneau <?= $ongletActif === 'inscription' ? 'actif' : '' ?>" id="panneau-inscription">

            <?php if ($erreurInscription): ?>
            <div class="auth-alerte auth-alerte-erreur">
                ⚠ <?= htmlspecialchars($erreurInscription) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/simulateur-reseau-ip/?vue=connexion&onglet=inscription" novalidate>
                <input type="hidden" name="action" value="inscription">

                <div class="auth-champ">
                    <label for="identifiant-in" class="auth-label">IDENTIFIANT</label>
                    <input type="text" id="identifiant-in" name="identifiant"
                           class="auth-input"
                           placeholder="3 à 50 caractères"
                           autocomplete="username" required>
                </div>

                <div class="auth-champ">
                    <label for="mot-de-passe-in" class="auth-label">MOT DE PASSE</label>
                    <input type="password" id="mot-de-passe-in" name="mot_de_passe"
                           class="auth-input"
                           placeholder="minimum 6 caractères"
                           autocomplete="new-password" required
                           oninput="majForce(this.value)">
                    <!-- Indicateur de force -->
                    <div class="force-conteneur" id="force-conteneur" style="display:none">
                        <div class="force-barre">
                            <div class="force-remplissage" id="force-remplissage"></div>
                        </div>
                        <span class="force-label" id="force-label"></span>
                    </div>
                </div>

                <div class="auth-champ">
                    <label for="confirmer-in" class="auth-label">CONFIRMER LE MOT DE PASSE</label>
                    <input type="password" id="confirmer-in" name="confirmer_mot_de_passe"
                           class="auth-input"
                           placeholder="répétez votre mot de passe"
                           autocomplete="new-password" required
                           oninput="verifierConfirmation(this)">
                </div>

                <button type="submit" class="auth-bouton">Créer mon compte</button>
            </form>
        </div>

    </div><!-- fin auth-carte -->

    <p class="auth-pied">Simulateur Réseau IP v<?= APP_VERSION ?> &mdash; Usage académique</p>
</div>

<script>
// ============================================================================
// SCRIPT : Authentification — onglets et validation
// ============================================================================

/* --- Gestion des onglets --- */
document.querySelectorAll('.auth-onglet').forEach(btn => {
    btn.addEventListener('click', function() {
        const cible = this.dataset.onglet;

        /* Onglets */
        document.querySelectorAll('.auth-onglet').forEach(b => b.classList.remove('actif'));
        this.classList.add('actif');

        /* Panneaux */
        document.querySelectorAll('.auth-panneau').forEach(p => p.classList.remove('actif'));
        document.getElementById(`panneau-${cible}`).classList.add('actif');
    });
});

/* --- Indicateur de force du mot de passe --- */
function majForce(mdp) {
    const conteneur = document.getElementById('force-conteneur');
    const barre     = document.getElementById('force-remplissage');
    const label     = document.getElementById('force-label');
    if (!mdp) { conteneur.style.display = 'none'; return; }
    conteneur.style.display = 'flex';

    let score = 0;
    if (mdp.length >= 6)           score++;
    if (mdp.length >= 10)          score++;
    if (/[A-Z]/.test(mdp))        score++;
    if (/[0-9]/.test(mdp))        score++;
    if (/[^a-zA-Z0-9]/.test(mdp)) score++;

    const niveaux = [
        { pct:'20%', couleur:'#DC2626', texte:'Très faible' },
        { pct:'40%', couleur:'#D97706', texte:'Faible' },
        { pct:'60%', couleur:'#FBBF24', texte:'Moyen' },
        { pct:'80%', couleur:'#16A34A', texte:'Fort' },
        { pct:'100%',couleur:'#15803D', texte:'Très fort' },
    ];
    const n = niveaux[Math.min(score - 1, 4)];
    barre.style.width           = n.pct;
    barre.style.backgroundColor = n.couleur;
    label.textContent           = n.texte;
    label.style.color           = n.couleur;
}

/* --- Vérification concordance confirmation --- */
function verifierConfirmation(el) {
    const mdp = document.getElementById('mot-de-passe-in').value;
    el.classList.toggle('erreur', el.value !== '' && el.value !== mdp);
}
</script>

</body>
</html>
