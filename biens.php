<?php
// ============================================================
// biens.php — Liste des biens avec filtres
// ============================================================
$pageTitle = 'Nos biens immobiliers';
require_once __DIR__ . '/includes/header.php';

// Récupération des filtres depuis GET (nettoyés)
$filters = [
  'operation'  => clean($_GET['operation']  ?? ''),
  'type'       => clean($_GET['type']       ?? ''),
  'ville'      => clean($_GET['ville']      ?? ''),
  'prix_min'   => isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float)$_GET['prix_min'] : null,
  'prix_max'   => isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float)$_GET['prix_max'] : null,
  'surface_min' => (int)($_GET['surface_min'] ?? 0),
  'pieces_min' => (int)($_GET['pieces_min'] ?? 0),
  'sort'       => clean($_GET['sort']       ?? 'created_at'),
  'order'      => clean($_GET['order']      ?? 'DESC'),
  'statut'     => 'disponible', // N'affiche que les biens disponibles
];

$erreurPrix = null;
if ($filters['prix_min'] !== null && $filters['prix_max'] !== null && $filters['prix_min'] > $filters['prix_max']) {
  $erreurPrix = 'Le prix minimum ne peut pas être supérieur au prix maximum.';
  $filters['prix_min'] = null;
  $filters['prix_max'] = null;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$result = getBiens($filters, $page);

// Construction de l'URL de base pour la pagination
$queryParams = array_filter($filters);
$baseUrl     = 'biens.php?' . http_build_query($queryParams);

// Villes distinctes pour le filtre
$villes = Database::fetchAll(
  'SELECT DISTINCT ville FROM biens WHERE statut = "disponible" ORDER BY ville ASC'
);
?>

<div class="container" style="padding-top:2rem;padding-bottom:4rem;">

  <!-- Titre de page -->
  <div class="section-header">
    <div>
      <p class="section-label">
        <?php if ($filters['operation'] === 'vente'): ?>Acheter
        <?php elseif ($filters['operation'] === 'location'): ?>Louer
        <?php else: ?>Tous les biens<?php endif; ?>
      </p>
      <h1 style="font-size:2rem;">
        <?= $result['total'] ?> bien<?= $result['total'] > 1 ? 's' : '' ?> trouvé<?= $result['total'] > 1 ? 's' : '' ?>
      </h1>
      <?php if ($filters['operation']): ?>
        <p style="font-size:.82rem;color:var(--gray-400);margin-top:.3rem;">
          Filtré par : <strong><?= $filters['operation'] === 'vente' ? 'Vente uniquement' : 'Location uniquement' ?></strong>
          — <a href="biens.php" style="color:var(--gold);">Voir tous les biens</a>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---- FILTRES ---- -->
  <button type="button" class="filters-toggle-btn" id="filters-toggle" aria-expanded="false" aria-controls="filters-form">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="4" y1="6" x2="20" y2="6" />
      <line x1="8" y1="12" x2="20" y2="12" />
      <line x1="12" y1="18" x2="20" y2="18" />
    </svg>
    Filtres &amp; tri
    <svg class="filters-toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <polyline points="6 9 12 15 18 9" />
    </svg>
  </button>
  <form class="filters-bar" method="GET" action="biens.php" id="filters-form">
    <div class="filter-group">
      <label>Opération</label>
      <select name="operation" onchange="this.form.submit()">
        <option value="">Toutes</option>
        <option value="vente" <?= $filters['operation'] === 'vente'    ? 'selected' : '' ?>>Vente</option>
        <option value="location" <?= $filters['operation'] === 'location' ? 'selected' : '' ?>>Location</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Type</label>
      <select name="type" onchange="this.form.submit()">
        <option value="">Tous</option>
        <option value="appartement" <?= $filters['type'] === 'appartement' ? 'selected' : '' ?>>Appartement</option>
        <option value="maison" <?= $filters['type'] === 'maison'     ? 'selected' : '' ?>>Maison</option>
        <option value="bureau" <?= $filters['type'] === 'bureau'     ? 'selected' : '' ?>>Bureau</option>
        <option value="local" <?= $filters['type'] === 'local'      ? 'selected' : '' ?>>Local</option>
        <option value="terrain" <?= $filters['type'] === 'terrain'    ? 'selected' : '' ?>>Terrain</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Ville</label>
      <input type="text" name="ville" value="<?= e($filters['ville']) ?>" placeholder="Ex: Lyon">
    </div>
    <div class="filter-group">
      <label>Prix min (€)</label>
      <input type="number" name="prix_min" value="<?= $filters['prix_min'] ?: '' ?>" placeholder="0" min="0">
    </div>
    <div class="filter-group">
      <label>Prix max (€)</label>
      <input type="number" name="prix_max" value="<?= $filters['prix_max'] ?: '' ?>" placeholder="Max" min="0">
    </div>
    <div class="filter-group">
      <label>Surface min (m²)</label>
      <input type="number" name="surface_min" value="<?= $filters['surface_min'] ?: '' ?>" placeholder="0" min="0">
    </div>
    <div class="filter-group">
      <label>Pièces min</label>
      <select name="pieces_min">
        <option value="">Toutes</option>
        <?php for ($p = 1; $p <= 6; $p++): ?>
          <option value="<?= $p ?>" <?= $filters['pieces_min'] == $p ? 'selected' : '' ?>><?= $p ?>+</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Trier par</label>
      <select name="sort" onchange="this.form.submit()">
        <option value="created_at" <?= $filters['sort'] === 'created_at' ? 'selected' : '' ?>>Date</option>
        <option value="prix" <?= $filters['sort'] === 'prix'      ? 'selected' : '' ?>>Prix</option>
        <option value="surface" <?= $filters['sort'] === 'surface'   ? 'selected' : '' ?>>Surface</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filtrer</button>
    <a href="biens.php" class="btn btn-outline">Réinitialiser</a>
  </form>

  <?php if ($erreurPrix): ?>
    <div class="alert alert-danger" style="margin-top:1rem;">
      ⚠️ <?= e($erreurPrix) ?>
    </div>
  <?php endif; ?>

  <!-- ---- GRILLE BIENS ---- -->
  <?php if (empty($result['items'])): ?>
    <div class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
      </svg>
      <h3>Aucun bien ne correspond à vos critères</h3>
      <p>Essayez d'élargir vos filtres.</p>
      <a href="biens.php" class="btn btn-primary mt-2">Voir tous les biens</a>
    </div>
  <?php else: ?>
    <div class="biens-grid">
      <?php foreach ($result['items'] as $b): ?>
        <a href="bien-detail.php?id=<?= $b['id'] ?>" class="bien-card">
          <div class="bien-card-img">
            <img loading="lazy" src="<?= imageSrc($b['image_principale']) ?>" alt="<?= e($b['titre']) ?>" loading="lazy">
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
              <svg class="icon-pin" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
              <?= e($b['ville']) ?> — <?= e($b['agence_nom']) ?>
            </p>
            <div class="bien-card-features">
              <span class="bien-card-feature">📐 <?= formatSurface($b['surface']) ?></span>
              <?php if ($b['pieces']): ?>
                <span class="bien-card-feature">🏠 <?= $b['pieces'] ?> p.</span>
              <?php endif; ?>
              <?php if ($b['chambres']): ?>
                <span class="bien-card-feature">🛏 <?= $b['chambres'] ?> ch.</span>
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

    <!-- Pagination -->
    <?= renderPagination($result, $baseUrl) ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>