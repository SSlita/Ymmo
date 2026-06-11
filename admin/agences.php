<?php
// ============================================================
// admin/agences.php — Gestion des agences (admin only)
// ============================================================
$pageTitle = 'Administration — Agences';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAdmin();

$errors  = [];
$success = '';

// ---- Actions POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action    = clean($_POST['action'] ?? '');
    $agenceId  = (int)($_POST['agence_id'] ?? 0);

    // Ajouter ou modifier
    if (in_array($action, ['ajouter', 'modifier'])) {
        $nom       = clean($_POST['nom']       ?? '');
        $adresse   = clean($_POST['adresse']   ?? '');
        $ville     = clean($_POST['ville']     ?? '');
        $cp        = clean($_POST['cp']        ?? '');
        $telephone = clean($_POST['telephone'] ?? '');
        $email     = clean($_POST['email']     ?? '');

        if (!$nom)       $errors[] = 'Le nom est requis.';
        if (!$adresse)   $errors[] = 'L\'adresse est requise.';
        if (!$ville)     $errors[] = 'La ville est requise.';
        if (!$cp)        $errors[] = 'Le code postal est requis.';
        if ($cp && !preg_match('/^[0-9]{5}$/', $cp)) $errors[] = 'Le code postal doit contenir exactement 5 chiffres.';
        if (!$telephone) $errors[] = 'Le téléphone est requis.';
        if ($telephone && !preg_match('/^[0-9]{10}$/', $telephone)) $errors[] = 'Le téléphone doit contenir exactement 10 chiffres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

        if (empty($errors)) {
            $params = [':nom'=>$nom,':adresse'=>$adresse,':ville'=>$ville,':cp'=>$cp,':telephone'=>$telephone,':email'=>$email];
            if ($action === 'ajouter') {
                Database::query('INSERT INTO agences (nom,adresse,ville,cp,telephone,email) VALUES (:nom,:adresse,:ville,:cp,:telephone,:email)', $params);
                $success = 'Agence ajoutée avec succès.';
            } else {
                $params[':id'] = $agenceId;
                Database::query('UPDATE agences SET nom=:nom,adresse=:adresse,ville=:ville,cp=:cp,telephone=:telephone,email=:email WHERE id=:id', $params);
                $success = 'Agence mise à jour.';
            }
            header('Location: agences.php?success=' . urlencode($success)); exit;
        }
    }

    // Supprimer
    if ($action === 'supprimer' && $agenceId) {
        $nbBiens = Database::fetchOne('SELECT COUNT(*) AS c FROM biens WHERE agence_id=:id', [':id'=>$agenceId])['c'];
        $nbUsers = Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE agence_id=:id', [':id'=>$agenceId])['c'];
        if ($nbBiens > 0 || $nbUsers > 0) {
            $success = ''; // on passe par flash
            header('Location: agences.php?error=' . urlencode("Impossible de supprimer : cette agence possède $nbBiens bien(s) et $nbUsers agent(s).")); exit;
        }
        Database::query('DELETE FROM agences WHERE id=:id', [':id'=>$agenceId]);
        header('Location: agences.php?success=' . urlencode('Agence supprimée.')); exit;
    }
}

// Messages flash via GET
$flashSuccess = clean($_GET['success'] ?? '');
$flashError   = clean($_GET['error']   ?? '');

// Agence à éditer (si ?edit=id)
$editId     = (int)($_GET['edit'] ?? 0);
$editAgence = $editId ? Database::fetchOne('SELECT * FROM agences WHERE id=:id', [':id'=>$editId]) : null;

// Liste des agences avec stats
$agences = Database::fetchAll(
    'SELECT a.*,
            COUNT(DISTINCT b.id)  AS nb_biens,
            COUNT(DISTINCT u.id)  AS nb_agents
     FROM agences a
     LEFT JOIN biens b ON b.agence_id = a.id AND b.statut != "archive"
     LEFT JOIN users u ON u.agence_id = a.id AND u.role = "agent"
     GROUP BY a.id
     ORDER BY a.nom ASC'
);
?>

<div class="dashboard-layout">
  <?php include __DIR__ . '/../dashboard/partials/sidebar.php'; ?>

  <div class="dashboard-main">
    <div class="dashboard-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <h1 style="font-size:1.8rem;">🏢 Agences</h1>
        <p style="color:var(--gray-400);"><?= count($agences) ?> agence<?= count($agences)>1?'s':'' ?> au total</p>
      </div>
      <a href="agences.php?add=1" class="btn btn-primary">+ Nouvelle agence</a>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <!-- Formulaire ajout / édition -->
    <?php if (isset($_GET['add']) || $editAgence): ?>
    <div class="card" style="margin-bottom:2rem;">
      <h3 style="font-size:1.05rem;margin-bottom:1.25rem;">
        <?= $editAgence ? 'Modifier l\'agence' : 'Nouvelle agence' ?>
      </h3>
      <form method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action"    value="<?= $editAgence ? 'modifier' : 'ajouter' ?>">
        <input type="hidden" name="agence_id" value="<?= $editAgence ? $editAgence['id'] : '' ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Nom *</label>
            <input type="text" name="nom" value="<?= e($editAgence['nom'] ?? ($_POST['nom'] ?? '')) ?>"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Email *</label>
            <input type="email" name="email" value="<?= e($editAgence['email'] ?? ($_POST['email'] ?? '')) ?>"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Adresse *</label>
            <input type="text" name="adresse" value="<?= e($editAgence['adresse'] ?? ($_POST['adresse'] ?? '')) ?>"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Téléphone *</label>
            <input type="tel" name="telephone" value="<?= e($editAgence['telephone'] ?? ($_POST['telephone'] ?? '')) ?>"
                   pattern="[0-9]{10}" inputmode="numeric" maxlength="10" title="10 chiffres sans espace"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Ville *</label>
            <input type="text" name="ville" value="<?= e($editAgence['ville'] ?? ($_POST['ville'] ?? '')) ?>"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.35rem;">Code postal *</label>
            <input type="text" name="cp" value="<?= e($editAgence['cp'] ?? ($_POST['cp'] ?? '')) ?>"
                   pattern="[0-9]{5}" inputmode="numeric" maxlength="5" title="5 chiffres exactement"
                   required style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:var(--radius);font-size:.88rem;">
          </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
          <button type="submit" class="btn btn-primary">
            <?= $editAgence ? 'Enregistrer les modifications' : 'Créer l\'agence' ?>
          </button>
          <a href="agences.php" class="btn btn-outline">Annuler</a>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Tableau des agences -->
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Agence</th>
            <th>Ville</th>
            <th>Téléphone</th>
            <th>Email</th>
            <th>Agents</th>
            <th>Biens actifs</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agences as $a): ?>
          <tr>
            <td style="color:var(--gray-400);font-size:.78rem;">#<?= $a['id'] ?></td>
            <td>
              <strong><?= e($a['nom']) ?></strong><br>
              <small style="color:var(--gray-400);"><?= e($a['adresse']) ?></small>
            </td>
            <td style="font-size:.85rem;"><?= e($a['ville']) ?> <span style="color:var(--gray-400);"><?= e($a['cp']) ?></span></td>
            <td style="font-size:.82rem;"><?= e(formatTel($a['telephone'])) ?></td>
            <td style="font-size:.82rem;"><a href="mailto:<?= e($a['email']) ?>" style="color:var(--gold);"><?= e($a['email']) ?></a></td>
            <td style="text-align:center;">
              <span class="badge badge-info"><?= $a['nb_agents'] ?></span>
            </td>
            <td style="text-align:center;">
              <span class="badge badge-success"><?= $a['nb_biens'] ?></span>
            </td>
            <td>
              <div class="table-actions">
                <a href="agences.php?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">✏️</a>
                <form method="POST" style="display:inline;">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"    value="supprimer">
                  <input type="hidden" name="agence_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);"
                          data-confirm="Supprimer l'agence « <?= e($a['nom']) ?> » ? Cette action est irréversible."
                          title="Supprimer">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
