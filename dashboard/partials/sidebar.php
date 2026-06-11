<?php
// dashboard/partials/sidebar.php — Sidebar commune
$currentFile = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="dashboard-sidebar">
  <div class="sidebar-logo">
    <a href="<?= APP_URL ?>" class="logo logo-light" style="font-size:1.5rem;"><span class="logo-y">Y</span>mmo</a>
  </div>

  <p class="sidebar-section">Navigation</p>
  <a href="<?= APP_URL ?>/dashboard/" class="sidebar-link <?= $currentFile==='index'?'active':'' ?>">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Tableau de bord
  </a>
  <a href="<?= APP_URL ?>/dashboard/mes-biens.php" class="sidebar-link <?= in_array($currentFile,['mes-biens','modifier-bien'])?'active':'' ?>">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
    Mes biens
  </a>
  <a href="<?= APP_URL ?>/dashboard/ajouter-bien.php" class="sidebar-link <?= $currentFile==='ajouter-bien'?'active':'' ?>">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
    Ajouter un bien
  </a>
  <a href="<?= APP_URL ?>/dashboard/stats.php" class="sidebar-link <?= $currentFile==='stats'?'active':'' ?>">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Statistiques
  </a>
  <a href="<?= APP_URL ?>/dashboard/messages.php" class="sidebar-link <?= $currentFile==='messages'?'active':'' ?>" style="position:relative;">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    Messages
    <?php
      $nbNouveaux = Auth::isAdmin()
        ? (Database::fetchOne('SELECT COUNT(*) AS c FROM demandes WHERE statut="nouvelle"')['c'] ?? 0)
        : (Database::fetchOne('SELECT COUNT(*) AS c FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:id AND d.statut="nouvelle"', [':id' => Auth::get('id')])['c'] ?? 0);
      if ($nbNouveaux > 0):
    ?>
    <span style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:#e74c3c;color:#fff;border-radius:10px;padding:.1rem .45rem;font-size:.68rem;font-weight:700;"><?= $nbNouveaux ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= APP_URL ?>/dashboard/offres.php" class="sidebar-link <?= $currentFile==='offres'?'active':'' ?>" style="position:relative;">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    Offres
    <?php
      $nbOffresAttente = Auth::isAdmin()
        ? (Database::fetchOne('SELECT COUNT(*) AS c FROM offres WHERE statut="en_attente"')['c'] ?? 0)
        : (Database::fetchOne('SELECT COUNT(*) AS c FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:id AND o.statut="en_attente"', [':id' => Auth::get('id')])['c'] ?? 0);
      if ($nbOffresAttente > 0):
    ?>
    <span style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:#e74c3c;color:#fff;border-radius:10px;padding:.1rem .45rem;font-size:.68rem;font-weight:700;"><?= $nbOffresAttente ?></span>
    <?php endif; ?>
  </a>

  <?php if (Auth::isAdmin()): ?>
  <p class="sidebar-section">Administration</p>
  <a href="<?= APP_URL ?>/admin/" class="sidebar-link <?= $currentFile==='index' && strpos($_SERVER['PHP_SELF'],'admin')!==false?'active':'' ?>">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
    Utilisateurs
  </a>
  <a href="<?= APP_URL ?>/admin/agences.php" class="sidebar-link">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
    Agences
  </a>
  <?php endif; ?>

  <p class="sidebar-section">Compte</p>
  <div class="sidebar-link" style="cursor:default;">
    <span class="user-avatar" style="width:26px;height:26px;background:rgba(201,168,76,.2);color:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;">
      <?= strtoupper(substr(Auth::get('prenom'),0,1).substr(Auth::get('nom'),0,1)) ?>
    </span>
    <span style="font-size:.85rem;">
      <?= e(Auth::get('prenom').' '.Auth::get('nom')) ?><br>
      <small style="color:rgba(255,255,255,.3);font-size:.72rem;"><?= e(ucfirst(Auth::get('role'))) ?></small>
    </span>
  </div>
  <a href="<?= APP_URL ?>/logout.php" class="sidebar-link" style="color:rgba(255,100,100,.7);">
    <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Déconnexion
  </a>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
