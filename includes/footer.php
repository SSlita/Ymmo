<?php // includes/footer.php ?>
</main><!-- /main-content -->

<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= APP_URL ?>" class="logo logo-light">
          <span class="logo-y">Y</span>mmo
        </a>
        <p>Votre partenaire immobilier de confiance.<br>Siège : Aix-en-Provence • 12 agences en France</p>
      </div>
      <div class="footer-col">
        <h4>Acheter &amp; Louer</h4>
        <ul>
          <li><a href="<?= APP_URL ?>/biens.php?operation=vente&type=appartement">Appartements à vendre</a></li>
          <li><a href="<?= APP_URL ?>/biens.php?operation=vente&type=maison">Maisons à vendre</a></li>
          <li><a href="<?= APP_URL ?>/biens.php?operation=location">Biens à louer</a></li>
          <li><a href="<?= APP_URL ?>/biens.php?type=bureau">Bureaux &amp; Locaux</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Ymmo</h4>
        <ul>
          <li><a href="<?= APP_URL ?>">Accueil</a></li>
          <li><a href="<?= APP_URL ?>/biens.php">Tous les biens</a></li>
          <?php if (Auth::isAgent()): ?>
          <li><a href="<?= APP_URL ?>/dashboard/">Mon espace</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <address>
          12 Cours Mirabeau<br>
          13100 Aix-en-Provence<br>
          <a href="tel:+33442000000">04 42 00 00 00</a><br>
          <a href="mailto:contact@ymmo.fr">contact@ymmo.fr</a>
        </address>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> Ymmo. Tous droits réservés.</p>
      <p class="footer-version">v<?= APP_VERSION ?></p>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/main.js" defer></script>
</body>
</html>
