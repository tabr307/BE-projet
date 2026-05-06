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
</head>
<body>

<header class="barre-nav">
    <div class="logo">Simulateur IP</div>
    
    <?php if (isset($_SESSION['utilisateur_id'])): ?>
        <div class="user-actions">
            <span class="username">
                <?php echo htmlspecialchars($_SESSION['utilisateur_nom']); ?>
            </span>
            <a href="api.php?action=logout" class="btn-logout">Déconnexion</a>
        </div>
    <?php endif; ?>
</header>

<main id="app">