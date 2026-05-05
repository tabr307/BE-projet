<header class="barre-nav">
    <div class="logo">Simulateur IP</div>
    <div class="user-actions">
        <!-- On utilise la nouvelle clé utilisateur_nom et on ajoute htmlspecialchars par sécurité -->
        <span class="username">
            <?php echo htmlspecialchars($_SESSION['utilisateur_nom'] ?? 'Invité'); ?>
        </span>
        <a href="backend/api.php?action=logout" class="btn-logout">Déconnexion</a>
    </div>
</header>