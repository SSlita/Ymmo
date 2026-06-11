<?php
// ============================================================
// faire-offre.php — Soumettre une offre d'achat ou de location
// ============================================================
$pageTitle = 'Faire une offre';
require_once __DIR__ . '/includes/header.php';
Auth::requireLogin();

// Seuls les clients peuvent faire des offres
if (Auth::get('role') !== 'client') {
    flash('danger', 'Seuls les clients peuvent soumettre une offre.');
    header('Location: ' . APP_URL . '/biens.php'); exit;
}

$bienId = (int)($_GET['id'] ?? 0);
$bien   = getBienById($bienId);

if (!$bien || $bien['statut'] !== 'disponible') {
    flash('danger', 'Ce bien n\'est plus disponible.');
    header('Location: ' . APP_URL . '/biens.php'); exit;
}

$userId    = (int)Auth::get('id');
$typeOffre = $bien['operation']; // 'vente' → 'achat', 'location' → 'location'
$typeLabel = $typeOffre === 'vente' ? 'achat' : 'location';

// Vérifier qu'il n'y a pas déjà une offre en attente sur ce bien
$offreExistante = Database::fetchOne(
    'SELECT id FROM offres WHERE bien_id=:bid AND client_id=:uid AND statut="en_attente"',
    [':bid'=>$bienId, ':uid'=>$userId]
);

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $prixPropose = (float)str_replace([' ','€',','], ['','',''], clean($_POST['prix_propose'] ?? '0'));
    $message     = clean($_POST['message'] ?? '');

    if ($prixPropose <= 0)
        $errors[] = 'Le prix proposé doit être supérieur à 0.';
    if ($prixPropose > $bien['prix'] * 2)
        $errors[] = 'Le prix proposé semble anormalement élevé.';

    if (empty($errors)) {
        Database::query(
            'INSERT INTO offres (bien_id, client_id, agent_id, type_offre, prix_propose, message)
             VALUES (:bid, :cid, :aid, :type, :prix, :msg)',
            [
                ':bid'  => $bienId,
                ':cid'  => $userId,
                ':aid'  => $bien['agent_id'],
                ':type' => $typeLabel,
                ':prix' => $prixPropose,
                ':msg'  => $message,
            ]
        );
        $success = true;
    }
}
?>

<div class="container" style="max-width:700px;margin:2.5rem auto;padding:0 1rem 4rem;">

  <nav style="font-size:.82rem;color:var(--gray-400);margin-bottom:1.5rem;">
    <a href="index.php">Accueil</a> /
    <a href="biens.php">Biens</a> /
    <a href="bien-detail.php?id=<?= $bienId ?>">Fiche</a> /
    <span>Faire une offre</span>
  </nav>

  <?php if ($success): ?>
    <div class="card" style="text-align:center;padding:3rem 2rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">🎉</div>
      <h1 style="font-size:1.6rem;margin-bottom:.75rem;">Offre envoyée !</h1>
      <p style="color:var(--gray-400);margin-bottom:1.5rem;">
        Votre offre de <strong><?= formatPrix($prixPropose) ?></strong>
        pour <strong><?= e($bien['titre']) ?></strong> a été transmise à l'agent.
        Vous serez notifié dès qu'elle sera traitée.
      </p>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <a href="mes-offres.php" class="btn btn-primary">Voir mes offres</a>
        <a href="biens.php" class="btn btn-outline">Continuer la recherche</a>
      </div>
    </div>

  <?php elseif ($offreExistante): ?>
    <div class="card" style="text-align:center;padding:3rem 2rem;">
      <div style="font-size:2.5rem;margin-bottom:1rem;">⏳</div>
      <h2 style="font-size:1.3rem;margin-bottom:.75rem;">Offre déjà soumise</h2>
      <p style="color:var(--gray-400);margin-bottom:1.5rem;">
        Vous avez déjà une offre en attente sur ce bien. Vous pouvez la consulter dans votre espace.
      </p>
      <div style="display:flex;gap:.75rem;justify-content:center;">
        <a href="mes-offres.php" class="btn btn-primary">Mes offres</a>
        <a href="bien-detail.php?id=<?= $bienId ?>" class="btn btn-outline">Retour à l'annonce</a>
      </div>
    </div>

  <?php else: ?>
    <!-- Récap du bien -->
    <div class="card" style="display:flex;gap:1rem;align-items:center;margin-bottom:1.5rem;padding:.9rem 1.1rem;">
      <div style="flex:1;">
        <p style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gold);font-weight:700;margin-bottom:.2rem;"><?= labelType($bien['type']) ?> — <?= $typeOffre === 'vente' ? 'Vente' : 'Location' ?></p>
        <h2 style="font-size:1.05rem;margin-bottom:.2rem;"><?= e($bien['titre']) ?></h2>
        <p style="font-size:.83rem;color:var(--gray-400);">📍 <?= e($bien['ville']) ?> (<?= e($bien['cp']) ?>)</p>
      </div>
      <div style="text-align:right;">
        <p style="font-size:1.3rem;font-weight:700;font-family:var(--font-display);color:var(--navy);"><?= formatPrix($bien['prix']) ?></p>
        <p style="font-size:.75rem;color:var(--gray-400);"><?= $typeOffre === 'location' ? '/mois' : 'Prix affiché' ?></p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><?= e($e) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h1 style="font-size:1.4rem;margin-bottom:.3rem;">Faire une offre d'<?= $typeLabel ?></h1>
      <p style="color:var(--gray-400);font-size:.87rem;margin-bottom:1.75rem;">
        Votre offre sera transmise directement à l'agent en charge de ce bien.
        Il pourra l'accepter, la refuser ou vous contacter pour négocier.
      </p>

      <form method="POST">
        <?= Auth::csrfField() ?>

        <div class="form-group" style="margin-bottom:1.25rem;">
          <label style="font-weight:600;font-size:.88rem;display:block;margin-bottom:.4rem;">
            Votre prix proposé * <span style="font-weight:400;color:var(--gray-400);">(prix affiché : <?= formatPrix($bien['prix']) ?>)</span>
          </label>
          <div style="position:relative;">
            <input type="number" name="prix_propose"
                   value="<?= $_POST['prix_propose'] ?? $bien['prix'] ?>"
                   min="1" step="1" required
                   style="width:100%;padding:.6rem 2.5rem .6rem .9rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:1rem;font-weight:600;">
            <span style="position:absolute;right:.85rem;top:50%;transform:translateY(-50%);color:var(--gray-400);">€</span>
          </div>
          <p style="font-size:.75rem;color:var(--gray-400);margin-top:.3rem;">
            Vous pouvez proposer un prix différent du prix affiché.
          </p>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label style="font-weight:600;font-size:.88rem;display:block;margin-bottom:.4rem;">Message à l'agent <span style="font-weight:400;color:var(--gray-400);">(optionnel)</span></label>
          <textarea name="message" rows="4"
                    placeholder="Précisez vos conditions, disponibilités pour une visite, financement prévu…"
                    style="width:100%;padding:.6rem .9rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;resize:vertical;font-family:inherit;"><?= e($_POST['message'] ?? '') ?></textarea>
        </div>

        <div style="background:rgba(201,168,76,.08);border-radius:var(--radius);padding:.9rem 1rem;margin-bottom:1.5rem;border-left:3px solid var(--gold);">
          <p style="font-size:.82rem;color:var(--gray-700);line-height:1.6;">
            <strong>ℹ️ Comment ça fonctionne ?</strong><br>
            1. Vous soumettez votre offre — l'agent la reçoit immédiatement.<br>
            2. L'agent peut <strong>accepter</strong> (la vente/location est enregistrée) ou <strong>refuser</strong> avec une note.<br>
            3. Vous pouvez suivre l'état de vos offres dans <a href="mes-offres.php" style="color:var(--gold);">Mes offres</a>.
          </p>
        </div>

        <div style="display:flex;gap:.75rem;">
          <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
            ✓ Soumettre mon offre
          </button>
          <a href="bien-detail.php?id=<?= $bienId ?>" class="btn btn-outline">Annuler</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
