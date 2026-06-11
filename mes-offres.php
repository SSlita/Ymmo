<?php
// ============================================================
// mes-offres.php — Espace client : suivi des offres
// ============================================================
$pageTitle = 'Mes offres';
require_once __DIR__ . '/includes/header.php';
Auth::requireLogin();

if (Auth::get('role') !== 'client') {
    header('Location: ' . APP_URL . '/dashboard/offres.php'); exit;
}

$userId = (int)Auth::get('id');

// Action : annuler une offre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $offreId = (int)($_POST['offre_id'] ?? 0);
    if ($offreId) {
        Database::query(
            'UPDATE offres SET statut="annulee" WHERE id=:id AND client_id=:uid AND statut="en_attente"',
            [':id'=>$offreId, ':uid'=>$userId]
        );
    }
    header('Location: mes-offres.php'); exit;
}

$offres = Database::fetchAll(
    'SELECT o.*, b.titre AS bien_titre, b.ville, b.type, b.operation, b.prix AS prix_affiche,
            b.statut AS bien_statut, b.id AS bien_id_ref,
            CONCAT(u.prenom," ",u.nom) AS agent_nom, u.telephone AS agent_tel, u.email AS agent_email
     FROM offres o
     JOIN biens b ON b.id = o.bien_id
     JOIN users u ON u.id = o.agent_id
     WHERE o.client_id = :uid
     ORDER BY o.created_at DESC',
    [':uid' => $userId]
);

$statutInfo = [
    'en_attente' => ['label'=>'En attente',  'color'=>'var(--gold)',    'bg'=>'rgba(201,168,76,.12)', 'icon'=>'⏳'],
    'acceptee'   => ['label'=>'Acceptée',    'color'=>'var(--success)', 'bg'=>'rgba(39,174,96,.1)',   'icon'=>'✅'],
    'refusee'    => ['label'=>'Refusée',     'color'=>'var(--danger)',  'bg'=>'rgba(231,76,60,.1)',   'icon'=>'❌'],
    'annulee'    => ['label'=>'Annulée',     'color'=>'var(--gray-400)','bg'=>'rgba(0,0,0,.05)',      'icon'=>'🚫'],
];
?>

<div class="container" style="max-width:900px;margin:2.5rem auto;padding:0 1rem 4rem;">

  <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
    <div>
      <h1 style="font-size:1.9rem;">Mes offres</h1>
      <p style="color:var(--gray-400);">Suivi de vos offres d'achat et de location</p>
    </div>
    <a href="biens.php" class="btn btn-outline btn-sm">← Rechercher des biens</a>
  </div>

  <?php if (empty($offres)): ?>
    <div class="card" style="text-align:center;padding:4rem 2rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">🏠</div>
      <p style="color:var(--gray-400);margin-bottom:1.25rem;">Vous n'avez pas encore fait d'offre.</p>
      <a href="biens.php" class="btn btn-primary">Parcourir les annonces</a>
    </div>
  <?php else: ?>
    <!-- Résumé rapide -->
    <?php
      $counts = ['en_attente'=>0,'acceptee'=>0,'refusee'=>0,'annulee'=>0];
      foreach ($offres as $o) $counts[$o['statut']]++;
    ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.75rem;">
      <?php foreach ($statutInfo as $key => $info): if (!$counts[$key]) continue; ?>
      <div style="background:<?= $info['bg'] ?>;border-radius:10px;padding:.5rem 1rem;display:flex;align-items:center;gap:.4rem;">
        <span style="font-size:.9rem;"><?= $info['icon'] ?></span>
        <span style="font-size:.82rem;font-weight:600;color:<?= $info['color'] ?>;"><?= $counts[$key] ?> <?= $info['label'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
      <?php foreach ($offres as $o):
        $si = $statutInfo[$o['statut']];
        $diff = (float)$o['prix_propose'] - (float)$o['prix_affiche'];
        $diffPct = $o['prix_affiche'] > 0 ? round($diff / $o['prix_affiche'] * 100, 1) : 0;
      ?>
      <div class="card" style="border-left:4px solid <?= $si['color'] ?>;padding:1.25rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
          <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem;">
              <span style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;border-radius:20px;padding:.2rem .75rem;font-size:.78rem;font-weight:700;">
                <?= $si['icon'] ?> <?= $si['label'] ?>
              </span>
              <span style="font-size:.75rem;color:var(--gray-400);"><?= (new DateTime($o['created_at']))->format('d/m/Y à H:i') ?></span>
            </div>
            <a href="bien-detail.php?id=<?= $o['bien_id_ref'] ?>"
               style="font-weight:700;font-size:1.05rem;color:var(--navy);text-decoration:none;">
              <?= e($o['bien_titre']) ?> ↗
            </a>
            <p style="font-size:.82rem;color:var(--gray-400);margin-top:.2rem;">
              📍 <?= e($o['ville']) ?> · <?= labelType($o['type']) ?> ·
              <?= $o['type_offre'] === 'achat' ? 'Vente' : 'Location' ?>
            </p>
          </div>
          <div style="text-align:right;">
            <p style="font-size:.72rem;color:var(--gray-400);">Votre offre</p>
            <p style="font-size:1.4rem;font-weight:700;font-family:var(--font-display);color:var(--navy);"><?= formatPrix($o['prix_propose']) ?></p>
            <p style="font-size:.75rem;color:var(--gray-400);">
              Affiché : <?= formatPrix($o['prix_affiche']) ?>
              <span style="color:<?= $diff <= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                (<?= $diff >= 0 ? '+' : '' ?><?= $diffPct ?>%)
              </span>
            </p>
          </div>
        </div>

        <?php if ($o['message']): ?>
        <div style="background:var(--gray-50);border-radius:6px;padding:.65rem .85rem;margin-bottom:.85rem;font-size:.85rem;color:var(--gray-600);font-style:italic;">
          "<?= e($o['message']) ?>"
        </div>
        <?php endif; ?>

        <?php if ($o['note_agent']): ?>
        <div style="background:<?= $si['bg'] ?>;border-radius:6px;padding:.65rem .85rem;margin-bottom:.85rem;border-left:3px solid <?= $si['color'] ?>;">
          <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:<?= $si['color'] ?>;margin-bottom:.2rem;">
            Réponse de l'agent
          </p>
          <p style="font-size:.85rem;color:var(--gray-700);"><?= e($o['note_agent']) ?></p>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
          <div style="font-size:.8rem;color:var(--gray-400);">
            Agent : <strong><?= e($o['agent_nom']) ?></strong>
            <?php if ($o['agent_tel']): ?>
              · <a href="tel:<?= preg_replace('/\s/','',$o['agent_tel']) ?>" style="color:var(--gold);"><?= e(formatTel($o['agent_tel'])) ?></a>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:.5rem;">
            <?php if ($o['statut'] === 'en_attente'): ?>
            <form method="POST" style="display:inline;">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);"
                      data-confirm="Annuler cette offre ?">Retirer l'offre</button>
            </form>
            <?php endif; ?>
            <?php if ($o['statut'] === 'refusee'): ?>
            <a href="faire-offre.php?id=<?= $o['bien_id_ref'] ?>" class="btn btn-sm btn-primary">Faire une nouvelle offre</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
