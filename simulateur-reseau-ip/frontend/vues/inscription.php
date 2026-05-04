<?php
// =============================================================================
// VUE : inscription.php
// Auteur : Étudiant
// Description : Page d'inscription pour créer un compte membre.
//               Les comptes créés ont le rôle 'membre' par défaut.
// =============================================================================

require_once __DIR__ . '/../../backend/noyau/GestionnaireAuth.php';
require_once __DIR__ . '/../../backend/modeles/Utilisateur.php';

// Redirection si déjà connecté
if (GestionnaireAuth::estConnecte()) {
    header('Location: /simulateur-reseau-ip/?vue=tableau-de-bord');
    exit;
}

$erreur  = '';
$succes  = false;
$donnees = ['identifiant' => ''];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant       = trim($_POST['identifiant'] ?? '');
    $motDePasse        = $_POST['mot_de_passe'] ?? '';
    $confirmMotDePasse = $_POST['confirmer_mot_de_passe'] ?? '';

    // Validation
    if (strlen($identifiant) < 3 || strlen($identifiant) > 50) {
        $erreur = "L'identifiant doit contenir entre 3 et 50 caractères.";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $identifiant)) {
        $erreur = "L'identifiant ne peut contenir que des lettres, chiffres, tirets et underscores.";
    } elseif (strlen($motDePasse) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($motDePasse !== $confirmMotDePasse) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        $modele    = new Utilisateur();
        $resultat  = $modele->creer($identifiant, $motDePasse, 'membre');

        if ($resultat['succes']) {
            $succes = true;
        } else {
            $erreur = $resultat['message'];
        }
    }

    $donnees['identifiant'] = htmlspecialchars($identifiant);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Simulateur Réseau IP</title>
    <link rel="stylesheet" href="/simulateur-reseau-ip/frontend/css/styles.css">
    <link rel="stylesheet" href="/simulateur-reseau-ip/frontend/css/connexion.css">
</head>
<body class="page-connexion">

    <div class="connexion-conteneur">

        <!-- Logo et titre -->
        <div class="connexion-entete">
            <div class="logo-icone">
                <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="30" cy="10" r="7" fill="#2563EB"/>
                    <circle cx="10" cy="45" r="7" fill="#1D4ED8"/>
                    <circle cx="50" cy="45" r="7" fill="#1D4ED8"/>
                    <line x1="30" y1="17" x2="10" y2="38" stroke="#2563EB" stroke-width="2"/>
                    <line x1="30" y1="17" x2="50" y2="38" stroke="#2563EB" stroke-width="2"/>
                    <line x1="10" y1="45" x2="50" y2="45" stroke="#93C5FD" stroke-width="2" stroke-dasharray="4 2"/>
                </svg>
            </div>
            <h1 class="connexion-titre">Créer<br><span>un compte</span></h1>
            <p class="connexion-sous-titre">Rejoignez le simulateur réseau IP. Votre compte sera de type membre.</p>
        </div>

        <!-- Carte inscription -->
        <div class="connexion-carte">

            <?php if ($succes): ?>
                <div class="alerte alerte-info" role="status">
                    <span class="alerte-icone">✓</span>
                    Compte créé avec succès ! Vous pouvez maintenant vous connecter.
                </div>
                <a href="/simulateur-reseau-ip/?vue=connexion" class="bouton-connexion" style="text-decoration:none;display:flex;margin-top:0">
                    Se connecter <span class="bouton-fleche">→</span>
                </a>
            <?php else: ?>

                <?php if ($erreur): ?>
                    <div class="alerte alerte-erreur" role="alert">
                        <span class="alerte-icone">⚠</span>
                        <?= htmlspecialchars($erreur) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/simulateur-reseau-ip/?vue=inscription" class="formulaire-connexion" novalidate>

                    <div class="champ-groupe">
                        <label for="identifiant" class="champ-label">Identifiant *</label>
                        <input
                            type="text"
                            id="identifiant"
                            name="identifiant"
                            class="champ-input <?= $erreur && !str_contains($erreur, 'mot de passe') ? 'champ-erreur' : '' ?>"
                            placeholder="3 à 50 caractères (lettres, chiffres, _ -)"
                            value="<?= $donnees['identifiant'] ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    <div class="champ-groupe">
                        <label for="mot_de_passe" class="champ-label">Mot de passe *</label>
                        <div class="champ-wrapper">
                            <input
                                type="password"
                                id="mot_de_passe"
                                name="mot_de_passe"
                                class="champ-input <?= str_contains($erreur, 'mot de passe') || str_contains($erreur, 'correspondent') ? 'champ-erreur' : '' ?>"
                                placeholder="Minimum 6 caractères"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" class="bouton-voir-mdp" id="btn-voir-mdp" aria-label="Afficher le mot de passe">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="champ-groupe">
                        <label for="confirmer_mot_de_passe" class="champ-label">Confirmer le mot de passe *</label>
                        <input
                            type="password"
                            id="confirmer_mot_de_passe"
                            name="confirmer_mot_de_passe"
                            class="champ-input <?= str_contains($erreur, 'correspondent') ? 'champ-erreur' : '' ?>"
                            placeholder="Répétez votre mot de passe"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <!-- Indicateur force du mot de passe -->
                    <div class="force-mdp" id="force-mdp" style="display:none">
                        <div class="force-mdp-barre">
                            <div class="force-mdp-remplissage" id="force-mdp-remplissage"></div>
                        </div>
                        <span class="force-mdp-label" id="force-mdp-label"></span>
                    </div>

                    <button type="submit" class="bouton-connexion">
                        Créer mon compte
                        <span class="bouton-fleche">→</span>
                    </button>
                </form>

            <?php endif; ?>
        </div>

        <p class="connexion-pied">
            Déjà un compte ?
            <a href="/simulateur-reseau-ip/?vue=connexion" style="color:var(--couleur-accent);text-decoration:none;font-weight:600">
                Se connecter
            </a>
        </p>

    </div>

    <style>
    /* Indicateur force du mot de passe */
    .force-mdp { margin-top: -0.5rem; margin-bottom: 0.75rem; }
    .force-mdp-barre {
        height: 4px; background: var(--couleur-bordure);
        border-radius: 2px; overflow: hidden; margin-bottom: 0.25rem;
    }
    .force-mdp-remplissage {
        height: 100%; border-radius: 2px;
        transition: width 0.3s ease, background-color 0.3s ease;
        width: 0;
    }
    .force-mdp-label { font-size: 0.72rem; font-weight: 600; }
    </style>

    <script>
    // Toggle visibilité mot de passe
    document.getElementById('btn-voir-mdp')?.addEventListener('click', function() {
        const input = document.getElementById('mot_de_passe');
        input.type = input.type === 'password' ? 'text' : 'password';
    });

    // Indicateur de force du mot de passe
    document.getElementById('mot_de_passe')?.addEventListener('input', function() {
        const mdp       = this.value;
        const conteneur = document.getElementById('force-mdp');
        const barre     = document.getElementById('force-mdp-remplissage');
        const label     = document.getElementById('force-mdp-label');

        if (!mdp) { conteneur.style.display = 'none'; return; }
        conteneur.style.display = 'block';

        let score = 0;
        if (mdp.length >= 6)  score++;
        if (mdp.length >= 10) score++;
        if (/[A-Z]/.test(mdp)) score++;
        if (/[0-9]/.test(mdp)) score++;
        if (/[^a-zA-Z0-9]/.test(mdp)) score++;

        const niveaux = [
            { seuil: 1, pct: '20%', couleur: '#DC2626', texte: 'Très faible' },
            { seuil: 2, pct: '40%', couleur: '#D97706', texte: 'Faible' },
            { seuil: 3, pct: '60%', couleur: '#FBBF24', texte: 'Moyen' },
            { seuil: 4, pct: '80%', couleur: '#16A34A', texte: 'Fort' },
            { seuil: 5, pct: '100%',couleur: '#15803D', texte: 'Très fort' },
        ];
        const niveau = niveaux[Math.min(score - 1, 4)];
        barre.style.width           = niveau.pct;
        barre.style.backgroundColor = niveau.couleur;
        label.textContent           = niveau.texte;
        label.style.color           = niveau.couleur;
    });

    // Validation côté client : vérification concordance des mots de passe
    document.getElementById('confirmer_mot_de_passe')?.addEventListener('input', function() {
        const mdp     = document.getElementById('mot_de_passe').value;
        const confirm = this.value;
        if (confirm && confirm !== mdp) {
            this.classList.add('champ-erreur');
        } else {
            this.classList.remove('champ-erreur');
        }
    });
    </script>

</body>
</html>
