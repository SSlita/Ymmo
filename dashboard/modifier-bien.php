<?php
// ============================================================
// dashboard/modifier-bien.php — Modifier un bien existant
// ============================================================
$pageTitle = 'Modifier un bien';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$id   = (int)($_GET['id'] ?? 0);
$bien = getBienById($id);

// Sécurité : l'agent ne peut modifier que ses propres biens
if (!$bien || (!Auth::isAdmin() && $bien['agent_id'] != Auth::get('id'))) {
    flash('danger', 'Bien introuvable ou accès non autorisé.');
    header('Location: mes-biens.php'); exit;
}

$errors  = [];
$options = json_decode($bien['options'] ?? '{}', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $titre       = clean($_POST['titre']       ?? '');
        $description = clean($_POST['description'] ?? '');
        $type        = clean($_POST['type']        ?? '');
        $operation   = clean($_POST['operation']   ?? '');
        $statut      = clean($_POST['statut']      ?? '');
        $prix        = (float)str_replace(',','.', $_POST['prix'] ?? 0);
        $surface     = (float)str_replace(',','.', $_POST['surface'] ?? 0);
        $pieces      = (int)($_POST['pieces']   ?? 1);
        $chambres    = (int)($_POST['chambres'] ?? 0);
        $etage       = $_POST['etage'] !== '' ? (int)$_POST['etage'] : null;
        $adresse     = clean($_POST['adresse']  ?? '');
        $ville       = clean($_POST['ville']    ?? '');
        $cp          = clean($_POST['cp']       ?? '');

        $newOptions = json_encode([
            'parking'   => isset($_POST['parking']),
            'cave'      => isset($_POST['cave']),
            'terrasse'  => isset($_POST['terrasse']),
            'ascenseur' => isset($_POST['ascenseur']),
            'gardien'   => isset($_POST['gardien']),
        ]);

        if (!$titre)   $errors[] = 'Le titre est requis.';
        if ($prix <= 0) $errors[] = 'Prix invalide.';
        if ($surface <= 0) $errors[] = 'Surface invalide.';

        if ($cp && !preg_match('/^[0-9]{5}$/', $cp)) $errors[] = 'Le code postal doit contenir exactement 5 chiffres.';
        if (empty($errors)) {
            Database::query(
                'UPDATE biens SET titre=:titre, description=:desc, type=:type, statut=:statut,
                 operation=:op, prix=:prix, surface=:surface, pieces=:pieces, chambres=:chambres,
                 etage=:etage, adresse=:adresse, ville=:ville, cp=:cp, options=:options
                 WHERE id=:id',
                [
                    ':titre'=>$titre, ':desc'=>$description, ':type'=>$type,
                    ':statut'=>$statut, ':op'=>$operation, ':prix'=>$prix,
                    ':surface'=>$surface, ':pieces'=>$pieces, ':chambres'=>$chambres,
                    ':etage'=>$etage, ':adresse'=>$adresse, ':ville'=>$ville,
                    ':cp'=>$cp, ':options'=>$newOptions, ':id'=>$id,
                ]
            );

            // Nouvelles images
            if (!empty($_FILES['images']['name'][0])) {
                $allowedMime = ['image/jpeg','image/png','image/webp'];
                $hasExisting = Database::fetchOne('SELECT COUNT(*) AS c FROM images_biens WHERE bien_id=:id', [':id'=>$id])['c'] > 0;
                foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                    if (!is_uploaded_file($tmp)) continue;
                    $mime = mime_content_type($tmp);
                    if (!in_array($mime, $allowedMime)) continue;
                    $ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $filename = 'bien_' . $id . '_' . uniqid() . '.' . $ext;
                    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
                    if (move_uploaded_file($tmp, UPLOAD_PATH . $filename)) {
                        Database::query(
                            'INSERT INTO images_biens (bien_id, filename, principale, ordre) VALUES (:b,:f,:p,:o)',
                            [':b'=>$id, ':f'=>$filename, ':p'=>(!$hasExisting && $idx===0)?1:0, ':o'=>$idx]
                        );
                        $hasExisting = true;
                    }
                }
            }

            flash('success', 'Bien mis à jour avec succès !');
            header('Location: mes-biens.php'); exit;
        }
    }
}

// Pré-remplissage depuis POST ou BDD
$v = $_SERVER['REQUEST_METHOD']==='POST' ? $_POST : $bien;
?>

<div class="dashboard-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header">
      <h1 style="font-size:1.8rem;">Modifier : <?= e(mb_substr($bien['titre'],0,40)) ?>…</h1>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><?php foreach ($errors as $e): ?><?= e($e) ?><br><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">📋 Informations générales</h3>
        <div class="form-group">
          <label>Titre *</label>
          <input type="text" name="titre" required value="<?= e($v['titre']??'') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Type *</label>
            <select name="type" required>
              <?php foreach (['appartement'=>'Appartement','maison'=>'Maison','bureau'=>'Bureau','local'=>'Local','terrain'=>'Terrain','autre'=>'Autre'] as $k=>$l): ?>
              <option value="<?= $k ?>" <?= ($v['type']??'')===$k?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Opération *</label>
            <select name="operation" required>
              <option value="vente"    <?= ($v['operation']??'')==='vente'?'selected':'' ?>>Vente</option>
              <option value="location" <?= ($v['operation']??'')==='location'?'selected':'' ?>>Location</option>
            </select>
          </div>
          <div class="form-group">
            <label>Statut</label>
            <select name="statut">
              <?php foreach (['disponible'=>'Disponible','vendu'=>'Vendu','loue'=>'Loué','archive'=>'Archivé'] as $k=>$l): ?>
              <option value="<?= $k ?>" <?= ($v['statut']??'')===$k?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Description *</label>
          <textarea name="description" required rows="5"><?= e($v['description']??'') ?></textarea>
        </div>
      </div>

      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">📐 Caractéristiques</h3>
        <div class="form-row" style="grid-template-columns:repeat(3,1fr);">
          <div class="form-group"><label>Prix (€) *</label><input type="number" name="prix" min="1" step="0.01" required value="<?= e($v['prix']??'') ?>"></div>
          <div class="form-group"><label>Surface (m²) *</label><input type="number" name="surface" min="1" step="0.01" required value="<?= e($v['surface']??'') ?>"></div>
          <div class="form-group"><label>Pièces</label><input type="number" name="pieces" min="0" value="<?= e($v['pieces']??'1') ?>"></div>
          <div class="form-group"><label>Chambres</label><input type="number" name="chambres" min="0" value="<?= e($v['chambres']??'0') ?>"></div>
          <div class="form-group"><label>Étage</label><input type="number" name="etage" min="0" value="<?= e($v['etage']??'') ?>"></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:1.2rem;margin-top:.5rem;">
          <?php foreach (['parking'=>'🚗 Parking','cave'=>'🪟 Cave','terrasse'=>'🌿 Terrasse','ascenseur'=>'🛗 Ascenseur','gardien'=>'👮 Gardien'] as $k=>$l): ?>
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.9rem;font-weight:400;">
            <input type="checkbox" name="<?= $k ?>"
                   <?= (isset($_POST[$k]) || (!isset($_POST['titre']) && !empty($options[$k])))?'checked':'' ?>
                   style="width:auto;accent-color:var(--gold);">
            <?= $l ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">📍 Localisation</h3>
        <div class="form-group"><label>Adresse *</label><input type="text" name="adresse" required value="<?= e($v['adresse']??'') ?>"></div>
        <div class="form-row">
          <div class="form-group"><label>Ville *</label><input type="text" name="ville" required value="<?= e($v['ville']??'') ?>"></div>
          <div class="form-group"><label>Code postal *</label><input type="text" name="cp" required maxlength="5" pattern="[0-9]{5}" title="5 chiffres exactement" inputmode="numeric" value="<?= e($v['cp']??'') ?>"></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">🖼 Ajouter des photos</h3>
        <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
        <div id="img-preview" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;"></div>
      </div>

      <div style="display:flex;gap:1rem;justify-content:flex-end;">
        <a href="mes-biens.php" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg">Enregistrer les modifications</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
