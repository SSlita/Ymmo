<?php
// ============================================================
// dashboard/index.php — Tableau de bord agent / admin
// ============================================================
$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$agentId  = Auth::get('id');
$isAdmin  = Auth::isAdmin();

// ---- KPIs ----
if ($isAdmin) {
    $nbBiens      = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE statut != "archive"')['c'];
    $nbDispo      = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE statut = "disponible"')['c'];
    $nbVendus     = Database::fetchOne('SELECT COUNT(*) AS c FROM transactions WHERE type_transaction="vente"')['c'];
    $ca           = Database::fetchOne('SELECT COALESCE(SUM(prix_final),0) AS c FROM transactions')['c'];
    $nbDemandes   = Database::fetchOne('SELECT COUNT(*) AS c FROM demandes WHERE statut="nouvelle"')['c'];
    $nbClients    = Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role="client"')['c'];
} else {
    $nbBiens      = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE agent_id=:id AND statut!="archive"', [':id'=>$agentId])['c'];
    $nbDispo      = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE agent_id=:id AND statut="disponible"', [':id'=>$agentId])['c'];
    $nbVendus     = Database::fetchOne('SELECT COUNT(*) AS c FROM transactions WHERE agent_id=:id', [':id'=>$agentId])['c'];
    $ca           = Database::fetchOne('SELECT COALESCE(SUM(prix_final),0) AS c FROM transactions WHERE agent_id=:id', [':id'=>$agentId])['c'];
    $nbDemandes   = Database::fetchOne(
        'SELECT COUNT(*) AS c FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:id AND d.statut="nouvelle"',
        [':id'=>$agentId]
    )['c'];
    $nbClients    = $nbDemandes; // Contacts non traités
}

// Dernières demandes
$demandesSql = $isAdmin
    ? 'SELECT d.*, b.titre AS bien_titre FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE d.statut="nouvelle" ORDER BY d.created_at DESC LIMIT 6'
    : 'SELECT d.*, b.titre AS bien_titre FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:id AND d.statut="nouvelle" ORDER BY d.created_at DESC LIMIT 6';
$demandes = $isAdmin
    ? Database::fetchAll($demandesSql)
    : Database::fetchAll($demandesSql, [':id'=>$agentId]);

// Biens récents de l'agent/admin
$biensSql = $isAdmin
    ? 'SELECT b.*, CONCAT(u.prenom," ",u.nom) AS agent_nom FROM biens b LEFT JOIN users u ON u.id=b.agent_id WHERE b.statut!="archive" ORDER BY b.created_at DESC LIMIT 5'
    : 'SELECT b.* FROM biens b WHERE b.agent_id=:id AND b.statut!="archive" ORDER BY b.created_at DESC LIMIT 5';
$derniersBiens = $isAdmin
    ? Database::fetchAll($biensSql)
    : Database::fetchAll($biensSql, [':id'=>$agentId]);
?>

<div class="dashboard-layout">
  <!-- ============ SIDEBAR ============ -->
  <?php require_once __DIR__ . '/partials/sidebar.php'; ?>

  <!-- ============ CONTENU PRINCIPAL ============ -->
  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header">
      <h1 style="font-size:1.8rem;">Bonjour, <?= e(Auth::get('prenom')) ?> 👋</h1>
      <p style="color:var(--gray-400);">
        <?= $isAdmin ? 'Vue globale — Administration' : 'Agence : ' . e(Auth::get('agence_nom') ?? 'Non assignée') ?>
        — <?= date('l d F Y') ?>
      </p>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-navy">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        </div>
        <p class="kpi-label">Biens actifs</p>
        <p class="kpi-value"><?= $nbBiens ?></p>
        <p style="font-size:.78rem;color:var(--success);"><?= $nbDispo ?> disponibles</p>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-green">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="kpi-label">Transactions</p>
        <p class="kpi-value"><?= $nbVendus ?></p>
        <p style="font-size:.78rem;color:var(--gray-400);">Ventes réalisées</p>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-gold">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <p class="kpi-label">Chiffre d'affaires</p>
        <p class="kpi-value" style="font-size:1.4rem;"><?= formatPrix($ca) ?></p>
        <p style="font-size:.78rem;color:var(--gray-400);">Transactions clôturées</p>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-red">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <p class="kpi-label">Demandes non traitées</p>
        <p class="kpi-value"><?= $nbDemandes ?></p>
        <p style="font-size:.78rem;color:var(--danger);">À traiter</p>
      </div>
    </div>

    <!-- Deux colonnes : demandes + biens -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

      <!-- Dernières demandes -->
      <div class="table-wrapper">
        <div class="table-header">
          <h3 style="font-size:1rem;">Demandes récentes</h3>
          <span class="badge badge-danger"><?= $nbDemandes ?> nouvelles</span>
        </div>
        <?php if (empty($demandes)): ?>
          <div class="empty-state" style="padding:2rem;">
            <p>Aucune nouvelle demande</p>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Contact</th><th>Bien</th><th>Date</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($demandes as $d): ?>
            <tr>
              <td>
                <strong><?= e($d['nom']) ?></strong><br>
                <small style="color:var(--gray-400);"><?= e($d['email']) ?></small>
              </td>
              <td style="font-size:.82rem;"><?= e(substr($d['bien_titre'],0,30)).'...' ?></td>
              <td style="font-size:.78rem;color:var(--gray-400);"><?= formatDate($d['created_at']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $d['bien_id'] ?>" class="btn btn-sm btn-outline">Voir</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Derniers biens -->
      <div class="table-wrapper">
        <div class="table-header">
          <h3 style="font-size:1rem;">Mes derniers biens</h3>
          <a href="<?= APP_URL ?>/dashboard/ajouter-bien.php" class="btn btn-primary btn-sm">+ Ajouter</a>
        </div>
        <table>
          <thead>
            <tr><th>Bien</th><th>Prix</th><th>Statut</th></tr>
          </thead>
          <tbody>
            <?php foreach ($derniersBiens as $b): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $b['id'] ?>" style="font-weight:500;color:var(--navy);">
                  <?= e(substr($b['titre'],0,28)) ?>...
                </a><br>
                <small style="color:var(--gray-400);"><?= e($b['ville']) ?> — <?= labelType($b['type']) ?></small>
              </td>
              <td style="font-size:.88rem;font-weight:600;"><?= formatPrix($b['prix']) ?></td>
              <td><?= badgeStatut($b['statut']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Lien vers statistiques -->
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h3 style="font-size:1.1rem;">Analyses &amp; Prévisions du marché</h3>
        <p>Consultez les tendances, performances par ville et prédictions de vente.</p>
      </div>
      <a href="<?= APP_URL ?>/dashboard/stats.php" class="btn btn-dark">Voir les statistiques →</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
