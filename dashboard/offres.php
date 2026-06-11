<?php
// ============================================================
// dashboard/offres.php — Gestion des offres reçues (agent/admin)
// ============================================================
$pageTitle = 'Offres reçues';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$agentId = (int)Auth::get('id');
$isAdmin = Auth::isAdmin();

// ---- Action POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action  = clean($_POST['action']   ?? '');
    $offreId = (int)($_POST['offre_id'] ?? 0);
    $note    = clean($_POST['note']     ?? '');

    if ($offreId && in_array($action, ['accepter','refuser'])) {
        // Vérifier que l'agent est bien responsable de cette offre
        $offre = $isAdmin
            ? Database::fetchOne('SELECT o.*, b.agent_id FROM offres o JOIN biens b ON b.id=o.bien_id WHERE o.id=:id AND o.statut="en_attente"', [':id'=>$offreId])
            : Database::fetchOne('SELECT o.*, b.agent_id FROM offres o JOIN biens b ON b.id=o.bien_id WHERE o.id=:id AND o.statut="en_attente" AND b.agent_id=:aid', [':id'=>$offreId,':aid'=>$agentId]);

        if ($offre) {
            if ($action === 'accepter') {
                // 1. Mettre à jour le statut de l'offre
                Database::query(
                    'UPDATE offres SET statut="acceptee", note_agent=:note WHERE id=:id',
                    [':note'=>$note, ':id'=>$offreId]
                );
                // 2. Marquer le bien comme vendu/loué
                $newStatut = $offre['type_offre'] === 'achat' ? 'vendu' : 'loue';
                Database::query('UPDATE biens SET statut=:s WHERE id=:id', [':s'=>$newStatut, ':id'=>$offre['bien_id']]);
                // 3. Annuler toutes les autres offres en attente sur ce bien
                Database::query(
                    'UPDATE offres SET statut="annulee", note_agent="Bien non disponible suite à l\'acceptation d\'une autre offre." WHERE bien_id=:bid AND id!=:oid AND statut="en_attente"',
                    [':bid'=>$offre['bien_id'], ':oid'=>$offreId]
                );
                // 4. Créer la transaction
                $commission = round($offre['prix_propose'] * COMMISSION_RATE / 100, 2);
                Database::query(
                    'INSERT INTO transactions (bien_id, acheteur_id, agent_id, type_transaction, prix_final, commission, date_transaction)
                     VALUES (:bid, :cid, :aid, :type, :prix, :com, NOW())',
                    [
                        ':bid'  => $offre['bien_id'],
                        ':cid'  => $offre['client_id'], // acheteur_id in transactions
                        ':aid'  => $offre['agent_id'],
                        ':type' => ($offre['type_offre'] === 'achat' ? 'vente' : 'location'),
                        ':prix' => $offre['prix_propose'],
                        ':com'  => $commission,
                    ]
                );
                flash('success', 'Offre acceptée — transaction enregistrée et bien marqué comme ' . ($newStatut === 'vendu' ? 'vendu' : 'loué') . '.');
            } else {
                Database::query(
                    'UPDATE offres SET statut="refusee", note_agent=:note WHERE id=:id',
                    [':note'=>$note, ':id'=>$offreId]
                );
                flash('success', 'Offre refusée.');
            }
        }
    }
    header('Location: offres.php?statut=' . urlencode(clean($_POST['back_statut'] ?? ''))); exit;
}

// ---- Filtres ----
$filtreStatut = clean($_GET['statut'] ?? 'en_attente');
if (!in_array($filtreStatut, ['en_attente','acceptee','refusee','annulee',''])) $filtreStatut = 'en_attente';

$whereParts = [];
$params     = [];
if (!$isAdmin) { $whereParts[] = 'b.agent_id=:aid'; $params[':aid'] = $agentId; }
if ($filtreStatut !== '') { $whereParts[] = 'o.statut=:statut'; $params[':statut'] = $filtreStatut; }
$whereStr = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$offres = Database::fetchAll(
    "SELECT o.*, b.titre AS bien_titre, b.ville, b.type, b.operation, b.prix AS prix_affiche, b.id AS bien_id_ref,
            CONCAT(c.prenom,' ',c.nom) AS client_nom, c.email AS client_email, c.telephone AS client_tel
     FROM offres o
     JOIN biens b ON b.id = o.bien_id
     JOIN users c ON c.id = o.client_id
     $whereStr
     ORDER BY o.created_at DESC",
    $params
);

// Compteurs
$cntParams = $isAdmin ? [] : [':aid'=>$agentId];
$cntJoin   = $isAdmin ? '' : 'JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid';
$cntRows   = Database::fetchAll("SELECT o.statut, COUNT(*) AS nb FROM offres o $cntJoin GROUP BY o.statut", $cntParams);
$counts    = ['en_attente'=>0,'acceptee'=>0,'refusee'=>0,'annulee'=>0,''=>0];
foreach ($cntRows as $r) { $counts[$r['statut']] = (int)$r['nb']; $counts[''] += (int)$r['nb']; }

$statutInfo = [
    'en_attente' => ['label'=>'En attente', 'color'=>'var(--gold)',    'icon'=>'⏳'],
    'acceptee'   => ['label'=>'Acceptées',  'color'=>'var(--success)', 'icon'=>'✅'],
    'refusee'    => ['label'=>'Refusées',   'color'=>'var(--danger)',  'icon'=>'❌'],
    'annulee'    => ['label'=>'Annulées',   'color'=>'var(--gray-400)','icon'=>'🚫'],
    ''           => ['label'=>'Toutes',     'color'=>'var(--navy)',    'icon'=>'📋'],
];
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/partials/sidebar.php'; ?>

  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header">
      <div>
        <h1 style="font-size:1.8rem;">Offres reçues</h1>
        <p style="color:var(--gray-400);">Gérez les offres d'achat et de location de vos clients</p>
      </div>
    </div>

    <?= renderFlash() ?>

    <!-- Onglets -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;">
      <?php foreach ($statutInfo as $val => $info): ?>
      <a href="?statut=<?= urlencode($val) ?>"
         style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;border:2px solid <?= $filtreStatut===$val ? $info['color'] : 'var(--gray-200)' ?>;background:<?= $filtreStatut===$val ? $info['color'] : 'transparent' ?>;color:<?= $filtreStatut===$val ? '#fff' : 'var(--gray-500)' ?>;">
        <?= $info['icon'] ?> <?= $info['label'] ?>
        <?php if ($counts[$val]): ?>
          <span style="background:<?= $filtreStatut===$val ? 'rgba(255,255,255,.25)' : $info['color'] ?>;color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.7rem;"><?= $counts[$val] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Liste des offres -->
    <?php if (empty($offres)): ?>
      <div class="card" style="text-align:center;padding:3.5rem;">
        <p style="font-size:2rem;margin-bottom:.75rem;">📭</p>
        <p style="color:var(--gray-400);">Aucune offre <?= $filtreStatut ? $statutInfo[$filtreStatut]['label'] : '' ?> pour le moment.</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($offres as $o):
          $si   = $statutInfo[$o['statut']];
          $diff = (float)$o['prix_propose'] - (float)$o['prix_affiche'];
          $pct  = $o['prix_affiche'] > 0 ? round($diff / $o['prix_affiche'] * 100, 1) : 0;
        ?>
        <div class="card" style="border-left:4px solid <?= $si['color'] ?>;padding:1.2rem 1.4rem;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:.9rem;">

            <!-- Client + bien -->
            <div>
              <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;">
                <span style="background:<?= $si['color'] ?>;color:#fff;border-radius:20px;padding:.18rem .7rem;font-size:.75rem;font-weight:700;"><?= $si['icon'] ?> <?= $si['label'] ?></span>
                <span style="font-size:.73rem;color:var(--gray-400);"><?= (new DateTime($o['created_at']))->format('d/m/Y à H:i') ?></span>
              </div>
              <p style="font-size:.95rem;font-weight:700;color:var(--navy);margin-bottom:.2rem;">
                <?= e($o['client_nom']) ?>
                <span style="font-weight:400;font-size:.82rem;color:var(--gray-400);">&lt;<?= e($o['client_email']) ?>&gt;</span>
              </p>
              <?php if ($o['client_tel']): ?>
                <p style="font-size:.8rem;color:var(--gray-400);"><?= e(formatTel($o['client_tel'])) ?></p>
              <?php endif; ?>
              <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $o['bien_id_ref'] ?>"
                 style="font-size:.85rem;color:var(--gold);text-decoration:none;">
                <?= e($o['bien_titre']) ?>, <?= e($o['ville']) ?> ↗
              </a>
            </div>

            <!-- Prix -->
            <div style="text-align:right;">
              <p style="font-size:.72rem;color:var(--gray-400);">Offre proposée</p>
              <p style="font-size:1.5rem;font-weight:700;font-family:var(--font-display);color:var(--navy);"><?= formatPrix($o['prix_propose']) ?></p>
              <p style="font-size:.77rem;color:var(--gray-400);">
                Affiché : <?= formatPrix($o['prix_affiche']) ?>
                <span style="color:<?= $diff <= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                  (<?= $diff >= 0 ? '+' : '' ?><?= $pct ?>%)
                </span>
              </p>
              <p style="font-size:.72rem;color:var(--gray-400);">Com. : <?= formatPrix($o['prix_propose'] * COMMISSION_RATE / 100) ?></p>
            </div>
          </div>

          <?php if ($o['message']): ?>
          <div style="background:var(--gray-50);border-radius:6px;padding:.65rem .85rem;margin-bottom:.85rem;font-size:.85rem;color:var(--gray-600);font-style:italic;">
            "<?= e($o['message']) ?>"
          </div>
          <?php endif; ?>

          <?php if ($o['note_agent']): ?>
          <div style="background:rgba(0,0,0,.04);border-radius:6px;padding:.6rem .85rem;margin-bottom:.85rem;font-size:.83rem;color:var(--gray-500);">
            <strong>Note :</strong> <?= e($o['note_agent']) ?>
          </div>
          <?php endif; ?>

          <!-- Actions (seulement si en attente) -->
          <?php if ($o['statut'] === 'en_attente'): ?>
          <div style="border-top:1px solid var(--gray-100);padding-top:.9rem;display:flex;gap:1rem;flex-wrap:wrap;">
            <!-- Accepter -->
            <form method="POST" style="flex:1;min-width:180px;">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action"       value="accepter">
              <input type="hidden" name="offre_id"     value="<?= $o['id'] ?>">
              <input type="hidden" name="back_statut"  value="<?= e($filtreStatut) ?>">
              <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <div style="flex:1;">
                  <input type="text" name="note" placeholder="Note d'acceptation (optionnel)…"
                         style="width:100%;padding:.45rem .7rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-sm" style="background:var(--success);color:#fff;border:none;white-space:nowrap;"
                        data-confirm="Accepter cette offre ? Le bien sera marqué comme <?= $o['type_offre']==='achat'?'vendu':'loué' ?> et une transaction sera créée.">
                  ✓ Accepter
                </button>
              </div>
            </form>
            <!-- Refuser -->
            <form method="POST" style="flex:1;min-width:180px;">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action"       value="refuser">
              <input type="hidden" name="offre_id"     value="<?= $o['id'] ?>">
              <input type="hidden" name="back_statut"  value="<?= e($filtreStatut) ?>">
              <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <div style="flex:1;">
                  <input type="text" name="note" placeholder="Motif du refus…"
                         style="width:100%;padding:.45rem .7rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);white-space:nowrap;">
                  ✕ Refuser
                </button>
              </div>
            </form>
          </div>
          <?php endif; ?>

        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
