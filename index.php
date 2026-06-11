<?php
// ============================================================
// index.php — Page d'accueil Ymmo
// ============================================================
$pageTitle = 'Accueil';
require_once __DIR__ . '/includes/header.php';

// Derniers biens disponibles (6)
$lastBiens = getBiens(['statut' => 'disponible'], 1, 6);

// Stats rapides
$totalBiens = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE statut="disponible"')['c'];
$ventes     = Database::fetchOne('SELECT COUNT(*) AS c FROM transactions WHERE type_transaction="vente"')['c'];
$agences    = Database::fetchOne('SELECT COUNT(*) AS c FROM agences')['c'];
?>

<!-- ============ HERO ============ -->
<section class="hero">
  <div class="container">
    <div class="hero-content">
      <p class="hero-eyebrow">Leader de l'immobilier en France</p>
      <h1>Trouvez le bien<br><em>qui vous correspond</em></h1>
      <p>Ymmo accompagne acheteurs, vendeurs et locataires partout en France,<br>avec expertise et transparence.</p>

      <div class="hero-cta">
        <a href="biens.php?operation=vente" class="btn btn-primary btn-lg">Acheter</a>
        <a href="biens.php?operation=location" class="btn btn-outline-white btn-lg">Louer</a>
      </div>

      <!-- Barre de recherche rapide -->
      <form class="hero-search" action="biens.php" method="GET">
        <div class="search-field">
          <label>Je veux</label>
          <select name="operation">
            <option value="">Acheter ou louer</option>
            <option value="vente">Acheter</option>
            <option value="location">Louer</option>
          </select>
        </div>
        <div class="search-field">
          <label>Type de bien</label>
          <select name="type">
            <option value="">Tous les types</option>
            <option value="appartement">Appartement</option>
            <option value="maison">Maison</option>
            <option value="bureau">Bureau / Local</option>
            <option value="terrain">Terrain</option>
          </select>
        </div>
        <div class="search-field">
          <label>Ville</label>
          <input type="text" name="ville" placeholder="Ex: Paris, Lyon...">
        </div>
        <div class="search-field">
          <label>Budget max (€)</label>
          <input type="number" name="prix_max" placeholder="500 000">
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Rechercher</button>
      </form>

      <!-- Stats -->
      <div class="hero-stats">
        <div class="hero-stat"><strong><?= $totalBiens ?></strong><span>Biens disponibles</span></div>
        <div class="hero-stat"><strong><?= $ventes ?>+</strong><span>Ventes réalisées</span></div>
        <div class="hero-stat"><strong><?= $agences ?></strong><span>Agences en France</span></div>
        <div class="hero-stat"><strong>15+</strong><span>Années d'expérience</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ DERNIERS BIENS ============ -->
<section class="section">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="section-label">Nouveautés</p>
        <h2>Biens récemment ajoutés</h2>
      </div>
      <a href="biens.php" class="btn btn-outline">Voir tout</a>
    </div>

    <div class="biens-grid">
      <?php foreach ($lastBiens['items'] as $b): ?>
      <a href="bien-detail.php?id=<?= $b['id'] ?>" class="bien-card" style="cursor:pointer;">
        <div class="bien-card-img">
          <img src="<?= imageSrc($b['image_principale']) ?>" alt="<?= e($b['titre']) ?>" loading="lazy">
          <div class="bien-card-badges">
            <span class="bien-card-operation <?= $b['operation'] === 'location' ? 'location' : '' ?>">
              <?= $b['operation'] === 'vente' ? 'À vendre' : 'À louer' ?>
            </span>
            <?= badgeStatut($b['statut']) ?>
          </div>
        </div>
        <div class="bien-card-body">
          <p class="bien-card-type"><?= labelType($b['type']) ?></p>
          <h3 class="bien-card-title"><?= e($b['titre']) ?></h3>
          <p class="bien-card-location">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= e($b['ville']) ?> (<?= e($b['cp']) ?>)
          </p>
          <div class="bien-card-features">
            <?php if ($b['surface']): ?>
            <span class="bien-card-feature">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
              <?= formatSurface($b['surface']) ?>
            </span>
            <?php endif; ?>
            <?php if ($b['pieces']): ?>
            <span class="bien-card-feature">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
              <?= $b['pieces'] ?> pièce<?= $b['pieces'] > 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
            <?php if ($b['chambres']): ?>
            <span class="bien-card-feature">
              🛏 <?= $b['chambres'] ?> ch.
            </span>
            <?php endif; ?>
          </div>
          <div class="bien-card-footer">
            <p class="bien-card-price">
              <?= formatPrix($b['prix']) ?>
              <?php if ($b['operation'] === 'location'): ?><small>/mois</small><?php endif; ?>
            </p>
            <span class="btn btn-sm btn-outline">Voir →</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ POURQUOI YMMO ============ -->
<section class="section section-alt">
  <div class="container">
    <div class="text-center">
      <p class="section-label">Notre valeur ajoutée</p>
      <h2>Pourquoi choisir Ymmo ?</h2>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <div class="why-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <h3>Réseau national</h3>
        <p>12 agences implantées sur tout le territoire, pour vous accompagner où que vous soyez.</p>
      </div>
      <div class="why-card">
        <div class="why-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <h3>Analyse de données</h3>
        <p>Nos outils d'IA analysent les tendances du marché pour vous orienter vers les meilleures décisions.</p>
      </div>
      <div class="why-card">
        <div class="why-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h3>Sécurité &amp; Transparence</h3>
        <p>Processus clair, tarifs transparents et agents certifiés pour chaque transaction.</p>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
