<?php
// ============================================================
// dashboard/mes-biens.php — Liste et gestion des biens
// ============================================================
$pageTitle = 'Mes biens';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$agentId = Auth::get('id');
$isAdmin = Auth::isAdmin();

// ---- Action : supprimer / archiver ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action  = clean($_POST['action']  ?? '');
    $bienId  = (int)($_POST['bien_id'] ?? 0);

    // Vérifie que l'agent est propriétaire du bien (sauf admin)
    $ownerCheck = $isAdmin ? true :
        (bool) Database::fetchOne(
            'SELECT id FROM biens WHERE id=:id AND agent_id=:ag',
            [':id'=>$bienId, ':ag'=>$agentId]
        );

    if ($ownerCheck && $bienId) {
        if ($action === 'archiver') {
            Database::query('UPDATE biens SET statut="archive" WHERE id=:id', [':id'=>$bienId]);
            flash('success', 'Bien archivé avec succès.');
        } elseif ($action === 'disponible') {
            Database::query('UPDATE biens SET statut="disponible" WHERE id=:id', [':id'=>$bienId]);
            flash('success', 'Bien remis en ligne.');
        } elseif ($action === 'marquer_vendu') {
            Database::query('UPDATE biens SET statut="vendu" WHERE id=:id', [':id'=>$bienId]);
            flash('success', 'Bien marqué comme vendu.');
        }
    }
    header('Location: mes-biens.php');
    exit;
}

// ---- Filtres ----
$filterStatut = clean($_GET['statut'] ?? '');
$filterType   = clean($_GET['type']   ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$filters = ['sort' => 'created_at', 'order' => 'DESC'];
if (!$isAdmin) $filters['agent_id'] = $agentId;
if ($filterStatut) $filters['statut'] = $filterStatut;
if ($filterType)   $filters['type']   = $filterType;

$result  = getBiens($filters, $page, 10);
$baseUrl = 'mes-biens.php?statut=' . urlencode($filterStatut) . '&type=' . urlencode($filterType);
?>

<div class="dashboard-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.8rem;">Mes biens</h1>
        <p style="color:var(--gray-400);"><?= $result['total'] ?> bien<?= $result['total']>1?'s':'' ?> trouvé<?= $result['total']>1?'s':'' ?></p>
      </div>
      <a href="<?= APP_URL ?>/dashboard/ajouter-bien.php" class="btn btn-primary">+ Ajouter un bien</a>
    </div>

    <!-- Filtres rapides -->
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;">
      <select name="statut" onchange="this.form.submit()" style="max-width:160px;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
        <option value="">Tous les statuts</option>
        <option value="disponible" <?= $filterStatut==='disponible'?'selected':'' ?>>Disponible</option>
        <option value="vendu"      <?= $filterStatut==='vendu'     ?'selected':'' ?>>Vendu</option>
        <option value="loue"       <?= $filterStatut==='loue'      ?'selected':'' ?>>Loué</option>
        <option value="archive"    <?= $filterStatut==='archive'   ?'selected':'' ?>>Archivé</option>
      </select>
      <select name="type" onchange="this.form.submit()" style="max-width:160px;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
        <option value="">Tous les types</option>
        <option value="appartement" <?= $filterType==='appartement'?'selected':'' ?>>Appartement</option>
        <option value="maison"      <?= $filterType==='maison'     ?'selected':'' ?>>Maison</option>
        <option value="bureau"      <?= $filterType==='bureau'     ?'selected':'' ?>>Bureau</option>
        <option value="terrain"     <?= $filterType==='terrain'    ?'selected':'' ?>>Terrain</option>
      </select>
      <?php if ($filterStatut || $filterType): ?>
        <a href="mes-biens.php" class="btn btn-outline btn-sm" style="align-self:center;">Réinitialiser</a>
      <?php endif; ?>
    </form>

    <!-- Tableau des biens -->
    <div class="table-wrapper">
      <?php if (empty($result['items'])): ?>
        <div class="empty-state">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
          <p>Aucun bien ne correspond à vos critères.</p>
          <a href="ajouter-bien.php" class="btn btn-primary btn-sm mt-2">Ajouter mon premier bien</a>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Bien</th>
            <th>Type</th>
            <th>Prix</th>
            <th>Surface</th>
            <th>Statut</th>
            <?php if ($isAdmin): ?><th>Agent</th><?php endif; ?>
            <th>Ajouté le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['items'] as $b): ?>
          <tr>
            <td style="color:var(--gray-400);font-size:.8rem;">#<?= $b['id'] ?></td>
            <td>
              <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $b['id'] ?>"
                 style="font-weight:500;color:var(--navy);font-size:.9rem;">
                <?= e(mb_substr($b['titre'],0,35)) ?><?= mb_strlen($b['titre'])>35?'…':'' ?>
              </a><br>
              <small style="color:var(--gray-400);">📍 <?= e($b['ville']) ?> — <?= $b['operation']==='vente'?'Vente':'Location' ?></small>
            </td>
            <td style="font-size:.85rem;"><?= labelType($b['type']) ?></td>
            <td style="font-weight:600;white-space:nowrap;"><?= formatPrix($b['prix']) ?></td>
            <td><?= formatSurface($b['surface']) ?></td>
            <td><?= badgeStatut($b['statut']) ?></td>
            <?php if ($isAdmin): ?>
            <td style="font-size:.82rem;"><?= e($b['agent_nom'] ?? '—') ?></td>
            <?php endif; ?>
            <td style="font-size:.78rem;color:var(--gray-400);"><?= formatDate($b['created_at']) ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= APP_URL ?>/dashboard/modifier-bien.php?id=<?= $b['id'] ?>"
                   class="btn btn-sm btn-outline" title="Modifier">✏️</a>
                <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $b['id'] ?>"
                   class="btn btn-sm btn-outline" title="Voir" target="_blank">👁</a>

                <form method="POST" style="display:inline;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="bien_id" value="<?= $b['id'] ?>">
                  <?php if ($b['statut'] === 'disponible'): ?>
                    <input type="hidden" name="action" value="marquer_vendu">
                    <button type="submit" class="btn btn-sm btn-outline" title="Marquer vendu"
                            data-confirm="Marquer ce bien comme vendu ?"
                            style="color:var(--success);">✔</button>
                  <?php elseif ($b['statut'] === 'archive'): ?>
                    <input type="hidden" name="action" value="disponible">
                    <button type="submit" class="btn btn-sm btn-outline" title="Remettre en ligne">♻</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="archiver">
                    <button type="submit" class="btn btn-sm btn-outline" title="Archiver"
                            data-confirm="Archiver ce bien ?"
                            style="color:var(--danger);">🗄</button>
                  <?php endif; ?>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?= renderPagination($result, $baseUrl) ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
