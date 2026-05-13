<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulateur IP - <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $page ?? ''))) ?></title>
    
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/mise-en-page.css">
    <link rel="stylesheet" href="css/composants.css">
    <link rel="stylesheet" href="css/styles.css">

    <?php if ($page === 'connexion'): ?>
        <link rel="stylesheet" href="css/connexion.css">
    <?php endif; ?>
    
    <?php if ($page === 'editeur'): ?>
        <link rel="stylesheet" href="css/editeur.css">
    <?php endif; ?>

    <!-- WBS 3.0: Prévention du FOUC (Flash of Unstyled Content) -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<header class="barre-nav">
    <div class="logo">Simulateur IP</div>
    
    <div class="user-actions" style="display: flex; align-items: center;">
        <button id="btn-theme-toggle" class="btn-outline" style="padding: 4px 8px; margin-right: 10px; font-size: 16px; border-radius: 4px; background: transparent; border: 1px solid var(--bordure); cursor: pointer; color: var(--texte-principal);" title="Basculer le thème">
            <span class="theme-icon">🌙</span>
        </button>
        <?php if (isset($_SESSION['utilisateur_id'])): ?>
            <span class="username" style="margin-right: 15px;">
                <?php echo htmlspecialchars($_SESSION['utilisateur_nom']); ?>
            </span>
            <a href="api.php?action=logout" class="btn-logout">Déconnexion</a>
        <?php endif; ?>
    </div>
</header>

<main id="app">