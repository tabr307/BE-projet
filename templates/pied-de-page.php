</main> <!-- Fermeture de #app -->

<footer class="pied-page">
    <p>&copy; 2026 - Simulateur IP - Expertise WBS 3.0</p>
</footer>

<!-- Scripts globaux -->
<script src="js/application-client.js"></script>
<script src="js/theme-manager.js"></script>

<?php if ($page === 'editeur'): ?>
    <!-- Chargement de la librairie vis.js pour le WBS 3.0 -->
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script src="js/moteur-visuel.js"></script>
    <!-- Moteur d'animation WBS 5.0 -->
    <script src="js/animation.js"></script>
<?php endif; ?>

</body>
</html>