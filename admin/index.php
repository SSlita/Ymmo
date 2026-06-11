<?php
// ============================================================
// admin/index.php — Gestion des utilisateurs (admin only)
// ============================================================
$pageTitle = 'Administration — Utilisateurs';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAdmin();

// ---- Action : toggle actif / supprimer ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = clean($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId && $userId !== (int)Auth::get('id')) { // ne peut pas s'auto-désactiver
        if ($action === 'toggle_actif') {
            $current = Database::fetchOne('SELECT actif FROM users WHERE id=:id', [':id'=>$userId])['actif'] ?? 1;
            Database::query('UPDATE users SET actif=:a WHERE id=:id', [':a'=>$current?0:1, ':id'=>$userId]);
            flash('success', 'Statut utilisateur mis à jour.');
        } elseif ($action === 'changer_role') {
            $role = clean($_POST['new_role'] ?? '');
            if (in_array($role, ['admin','agent','client'])) {
                Database::query('UPDATE users SET role=:r WHERE id=:id', [':r'=>$role, ':id'=>$userId]);
                flash('success', 'Rôle mis à jour.');
            }
        } elseif ($action === 'assigner_agence') {
            $newAgenceId = (int)($_POST['agence_id'] ?? 0);
            Database::query('UPDATE users SET agence_id=:a WHERE id=:id',
                [':a' => $newAgenceId ?: null, ':id' => $userId]);
            flash('success', 'Agence mise à jour.');
        }
    }
    header('Location: index.php'); exit;
}

// Agences pour le sélecteur
$agences = Database::fetchAll('SELECT id, nom FROM agences ORDER BY nom ASC');

// Filtres
$filterRole = clean($_GET['role'] ?? '');
$search     = clean($_GET['q']    ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($filterRole) { $where[] = 'u.role=:role'; $params[':role'] = $filterRole; }
if ($search)     { $where[] = '(u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q)'; $params[':q'] = "%$search%"; }

$whereStr = implode(' AND ', $where);
$total    = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM users u WHERE $whereStr", $params)['c'];
$perPage  = 15;
$offset   = ($page-1)*$perPage;

$stmt = Database::getInstance()->prepare(
    "SELECT u.*, a.nom AS agence_nom FROM users u
     LEFT JOIN agences a ON a.id=u.agence_id
     WHERE $whereStr ORDER BY u.created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();
?>

<div class="dashboard-layout">
  <?php include __DIR__ . '/../dashboard/partials/sidebar.php'; ?>

  <div class="dashboard-main">
    <div class="dashboard-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.8rem;">👥 Utilisateurs</h1>
        <p style="color:var(--gray-400);"><?= $total ?> utilisateur<?= $total>1?'s':'' ?> au total</p>
      </div>
    </div>

    <!-- Recherche + filtre -->
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher (nom, email)…"
             style="max-width:280px;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
      <select name="role" onchange="this.form.submit()"
              style="max-width:160px;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
        <option value="">Tous les rôles</option>
        <option value="admin"  <?= $filterRole==='admin' ?'selected':'' ?>>Admin</option>
        <option value="agent"  <?= $filterRole==='agent' ?'selected':'' ?>>Agent</option>
        <option value="client" <?= $filterRole==='client'?'selected':'' ?>>Client</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Filtrer</button>
      <?php if ($filterRole || $search): ?>
        <a href="index.php" class="btn btn-outline btn-sm">Réinitialiser</a>
      <?php endif; ?>
    </form>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Utilisateur</th>
            <th>Rôle</th>
            <th>Agence</th>
            <th>Téléphone</th>
            <th>Inscrit le</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--gray-400);font-size:.78rem;">#<?= $u['id'] ?></td>
            <td>
              <strong><?= e($u['prenom'].' '.$u['nom']) ?></strong><br>
              <small style="color:var(--gray-400);"><?= e($u['email']) ?></small>
            </td>
            <td>
              <span class="badge <?= $u['role']==='admin'?'badge-danger':($u['role']==='agent'?'badge-info':'badge-secondary') ?>">
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td style="font-size:.82rem;"><?= e($u['agence_nom'] ?? '—') ?></td>
            <td style="font-size:.82rem;"><?= e(formatTel($u['telephone']) ?: '—') ?></td>
            <td style="font-size:.78rem;color:var(--gray-400);"><?= formatDate($u['created_at']) ?></td>
            <td>
              <?php if ($u['actif']): ?>
                <span class="badge badge-success">Actif</span>
              <?php else: ?>
                <span class="badge badge-secondary">Inactif</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['id'] != Auth::get('id')): ?>
              <div class="table-actions">
                <!-- Toggle actif -->
                <form method="POST" style="display:inline;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"  value="toggle_actif">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline"
                          data-confirm="Changer le statut de cet utilisateur ?"
                          title="<?= $u['actif']?'Désactiver':'Activer' ?>">
                    <?= $u['actif'] ? '🔒' : '🔓' ?>
                  </button>
                </form>

                <!-- Changer rôle -->
                <form method="POST" style="display:inline;display:flex;align-items:center;gap:.25rem;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"  value="changer_role">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="new_role" onchange="this.form.submit()"
                          style="font-size:.75rem;padding:.25rem .5rem;border:1px solid var(--gray-200);border-radius:4px;">
                    <option value="">Rôle…</option>
                    <?php foreach (['admin','agent','client'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>

                <!-- Assigner agence (uniquement pour les agents) -->
                <?php if ($u['role'] === 'agent'): ?>
                <form method="POST" style="display:inline;display:flex;align-items:center;gap:.25rem;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"  value="assigner_agence">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="agence_id" onchange="this.form.submit()"
                          style="font-size:.75rem;padding:.25rem .5rem;border:1px solid var(--gray-200);border-radius:4px;max-width:160px;">
                    <option value="0">— Agence —</option>
                    <?php foreach ($agences as $ag): ?>
                    <option value="<?= $ag['id'] ?>" <?= (int)($u['agence_id'] ?? 0)===(int)$ag['id']?'selected':'' ?>>
                      <?= e($ag['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </form>
                <?php endif; ?>
              </div>
              <?php else: ?>
                <span style="font-size:.78rem;color:var(--gray-400);">Vous</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php
    $totalPages = (int)ceil($total/$perPage);
    if ($totalPages > 1):
    ?>
    <nav class="pagination"><ul>
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?role=<?= urlencode($filterRole) ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
