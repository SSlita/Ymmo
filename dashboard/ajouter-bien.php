<?php
// ============================================================
// dashboard/ajouter-bien.php — Formulaire création d'un bien
// ============================================================
$pageTitle = 'Ajouter un bien';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$errors  = [];
$success = false;

// Liste des agences (admin voit tout, agent uniquement la sienne)
if (Auth::isAdmin()) {
    $agences = Database::fetchAll('SELECT id, nom FROM agences ORDER BY nom');
} else {
    $agences = Database::fetchAll(
        'SELECT id, nom FROM agences WHERE id = :id',
        [':id' => Auth::get('agence_id')]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        // Récupération et nettoyage des champs
        $titre       = clean($_POST['titre']       ?? '');
        $description = clean($_POST['description'] ?? '');
        $type        = clean($_POST['type']        ?? '');
        $operation   = clean($_POST['operation']   ?? '');
        $statut      = 'disponible';
        $prix        = (float)str_replace(',','.', $_POST['prix'] ?? 0);
        $surface     = (float)str_replace(',','.', $_POST['surface'] ?? 0);
        $pieces      = (int)($_POST['pieces']   ?? 1);
        $chambres    = (int)($_POST['chambres'] ?? 0);
        $etage       = $_POST['etage'] !== '' ? (int)$_POST['etage'] : null;
        $adresse     = clean($_POST['adresse']  ?? '');
        $ville       = clean($_POST['ville']    ?? '');
        $cp          = clean($_POST['cp']       ?? '');
        $agenceId    = Auth::isAdmin() ? (int)$_POST['agence_id'] : (int)Auth::get('agence_id');
        $agentId     = Auth::get('id');

        $options = json_encode([
            'parking'   => isset($_POST['parking']),
            'cave'      => isset($_POST['cave']),
            'terrasse'  => isset($_POST['terrasse']),
            'ascenseur' => isset($_POST['ascenseur']),
            'gardien'   => isset($_POST['gardien']),
        ]);

        // Validations
        if (!$titre)                                    $errors[] = 'Le titre est requis.';
        if (!$description)                              $errors[] = 'La description est requise.';
        if (!in_array($type, ['appartement','maison','bureau','local','terrain','autre'])) $errors[] = 'Type invalide.';
        if (!in_array($operation, ['vente','location'])) $errors[] = 'Opération invalide.';
        if ($prix <= 0)                                 $errors[] = 'Prix invalide.';
        if ($surface <= 0)                              $errors[] = 'Surface invalide.';
        if (!$adresse || !$ville || !$cp)              $errors[] = 'Adresse incomplète.';
        if ($cp && !preg_match('/^[0-9]{5}$/', $cp)) $errors[] = 'Le code postal doit contenir exactement 5 chiffres.';

        if (empty($errors)) {
            Database::query(
                'INSERT INTO biens
                 (titre, description, type, statut, operation, prix, surface, pieces, chambres,
                  etage, adresse, ville, cp, agence_id, agent_id, options)
                 VALUES (:titre, :desc, :type, :statut, :op, :prix, :surface, :pieces, :chambres,
                  :etage, :adresse, :ville, :cp, :agence, :agent, :options)',
                [
                    ':titre'   => $titre,   ':desc'    => $description,
                    ':type'    => $type,    ':statut'  => $statut,
                    ':op'      => $operation, ':prix'  => $prix,
                    ':surface' => $surface, ':pieces'  => $pieces,
                    ':chambres'=> $chambres, ':etage'  => $etage,
                    ':adresse' => $adresse, ':ville'   => $ville,
                    ':cp'      => $cp,      ':agence'  => $agenceId,
                    ':agent'   => $agentId, ':options' => $options,
                ]
            );
            $newId = Database::lastInsertId();

            // Upload d'images
            if (!empty($_FILES['images']['name'][0])) {
                $allowedMime = ['image/jpeg','image/png','image/webp'];
                $premiere    = true;
                foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
                    if (!is_uploaded_file($tmp)) continue;
                    $mime = mime_content_type($tmp);
                    if (!in_array($mime, $allowedMime)) continue;
                    $ext      = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $filename = 'bien_' . $newId . '_' . uniqid() . '.' . $ext;
                    $dest     = UPLOAD_PATH . $filename;
                    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
                    if (move_uploaded_file($tmp, $dest)) {
                        Database::query(
                            'INSERT INTO images_biens (bien_id, filename, principale, ordre) VALUES (:b,:f,:p,:o)',
                            [':b'=>$newId, ':f'=>$filename, ':p'=>(int)$premiere, ':o'=>$idx]
                        );
                        $premiere = false;
                    }
                }
            }

            flash('success', 'Bien ajouté avec succès !');
            header('Location: ' . APP_URL . '/dashboard/mes-biens.php');
            exit;
        }
    }
}

$v = $_POST ?? [];
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
      <h1 style="font-size:1.8rem;">Ajouter un bien</h1>
      <p style="color:var(--gray-400);">Remplissez le formulaire pour publier un nouveau bien.</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><?= e($e) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>

      <!-- Section : Informations générales -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">
          📋 Informations générales
        </h3>
        <div class="form-group">
          <label>Titre de l'annonce *</label>
          <input type="text" name="titre" required value="<?= e($v['titre']??'') ?>"
                 placeholder="Ex: Appartement lumineux avec terrasse vue mer">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Type de bien *</label>
            <select name="type" required>
              <option value="">Choisir...</option>
              <?php foreach (['appartement'=>'Appartement','maison'=>'Maison','bureau'=>'Bureau','local'=>'Local commercial','terrain'=>'Terrain','autre'=>'Autre'] as $k=>$l): ?>
              <option value="<?= $k ?>" <?= ($v['type']??'')===$k?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Opération *</label>
            <select name="operation" required>
              <option value="vente"    <?= ($v['operation']??'')==='vente'   ?'selected':'' ?>>Vente</option>
              <option value="location" <?= ($v['operation']??'')==='location'?'selected':'' ?>>Location</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Description *</label>
          <textarea name="description" required rows="5"
                    placeholder="Décrivez le bien : qualité de construction, environnement, points forts..."><?= e($v['description']??'') ?></textarea>
        </div>
        <?php if (Auth::isAdmin()): ?>
        <div class="form-row">
          <div class="form-group">
            <label>Agence *</label>
            <select name="agence_id" required>
              <?php foreach ($agences as $ag): ?>
              <option value="<?= $ag['id'] ?>" <?= ($v['agence_id']??'')==$ag['id']?'selected':'' ?>><?= e($ag['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Section : Caractéristiques -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">
          📐 Caractéristiques
        </h3>
        <div class="form-row" style="grid-template-columns:repeat(3,1fr);">
          <div class="form-group">
            <label>Prix (€) *</label>
            <input type="number" name="prix" min="1" step="0.01" required
                   value="<?= e($v['prix']??'') ?>" placeholder="320000">
          </div>
          <div class="form-group">
            <label>Surface (m²) *</label>
            <input type="number" name="surface" min="1" step="0.01" required
                   value="<?= e($v['surface']??'') ?>" placeholder="68">
          </div>
          <div class="form-group">
            <label>Nombre de pièces</label>
            <input type="number" name="pieces" min="0" max="50"
                   value="<?= e($v['pieces']??'1') ?>">
          </div>
          <div class="form-group">
            <label>Chambres</label>
            <input type="number" name="chambres" min="0" max="20"
                   value="<?= e($v['chambres']??'0') ?>">
          </div>
          <div class="form-group">
            <label>Étage (vide = N/A)</label>
            <input type="number" name="etage" min="0" max="100"
                   value="<?= e($v['etage']??'') ?>" placeholder="0 = RDC">
          </div>
        </div>

        <div style="margin-top:.5rem;">
          <label style="display:block;margin-bottom:.8rem;">Options</label>
          <div style="display:flex;flex-wrap:wrap;gap:1.2rem;">
            <?php foreach (['parking'=>'🚗 Parking','cave'=>'🪟 Cave','terrasse'=>'🌿 Terrasse','ascenseur'=>'🛗 Ascenseur','gardien'=>'👮 Gardien'] as $k=>$l): ?>
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.9rem;font-weight:400;">
              <input type="checkbox" name="<?= $k ?>" <?= isset($v[$k])?'checked':'' ?> style="width:auto;accent-color:var(--gold);">
              <?= $l ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Section : Localisation -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">
          📍 Localisation
        </h3>
        <div class="form-group">
          <label>Adresse *</label>
          <input type="text" name="adresse" required
                 value="<?= e($v['adresse']??'') ?>" placeholder="12 Rue de la Paix">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Ville *</label>
            <input type="text" name="ville" required
                   value="<?= e($v['ville']??'') ?>" placeholder="Paris">
          </div>
          <div class="form-group">
            <label>Code postal *</label>
            <input type="text" name="cp" required maxlength="5" pattern="[0-9]{5}" title="5 chiffres exactement" inputmode="numeric"
                   value="<?= e($v['cp']??'') ?>" placeholder="75001">
          </div>
        </div>
      </div>

      <!-- Section : Photos -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-200);">
          🖼 Photos
        </h3>
        <div class="form-group">
          <label>Images (JPG, PNG, WebP — max 5 Mo chacune)</label>
          <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                 style="padding:.5rem;">
          <p style="font-size:.78rem;color:var(--gray-400);margin-top:.3rem;">La première image sera utilisée comme photo principale.</p>
        </div>
        <div id="img-preview" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;"></div>
      </div>

      <div style="display:flex;gap:1rem;justify-content:flex-end;">
        <a href="mes-biens.php" class="btn btn-outline">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg">Publier le bien</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
