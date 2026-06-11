<?php
// ============================================================
// bien-detail.php — Fiche détaillée d'un bien
// ============================================================
require_once __DIR__ . '/includes/header.php';

$id   = (int)($_GET['id'] ?? 0);
$bien = getBienById($id);

if (!$bien) {
    flash('danger', 'Ce bien n\'existe pas ou a été supprimé.');
    header('Location: biens.php');
    exit;
}

$pageTitle = $bien['titre'];
$images    = getImagesBien($id);
$options   = json_decode($bien['options'] ?? '{}', true) ?: [];

// ---- Traitement formulaire de contact ----
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact') {
    if (!Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de sécurité invalide.';
    } elseif (Auth::isLoggedIn() && (int)Auth::get('id') === (int)$bien['agent_id']) {
        $errors[] = 'Vous ne pouvez pas vous envoyer un message sur votre propre bien.';
    } else {
        $nom       = clean($_POST['nom']       ?? '');
        $email     = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $telephone = clean($_POST['telephone'] ?? '');
        $message   = clean($_POST['message']   ?? '');

        if (!$nom)     $errors[] = 'Votre nom est requis.';
        if (!$email)   $errors[] = 'Adresse email invalide.';
        if (!$message) $errors[] = 'Le message ne peut pas être vide.';

        if (empty($errors)) {
            Database::query(
                'INSERT INTO demandes (bien_id, client_id, nom, email, telephone, message)
                 VALUES (:bien_id, :client_id, :nom, :email, :telephone, :message)',
                [
                    ':bien_id'   => $id,
                    ':client_id' => Auth::get('id'),
                    ':nom'       => $nom,
                    ':email'     => $email,
                    ':telephone' => $telephone,
                    ':message'   => $message,
                ]
            );
            $success = true;
        }
    }
}

// Biens similaires (même type, même ville, différent)
// Enregistrer la vue (une fois par session par bien)
$vueKey = 'vue_bien_' . $id;
if (empty($_SESSION[$vueKey])) {
    Database::query(
        'INSERT INTO vues_biens (bien_id, user_id, ip_hash) VALUES (:bid, :uid, :ip)',
        [':bid'=>$id, ':uid'=>Auth::get('id'), ':ip'=>hash('sha256', $_SERVER['REMOTE_ADDR']??'')]
    );
    $_SESSION[$vueKey] = true;
}

$similaires = Database::fetchAll(
    "SELECT b.*, img.filename AS image_principale
     FROM biens b
     LEFT JOIN images_biens img ON img.bien_id = b.id AND img.principale = 1
     WHERE b.type = :type AND b.ville = :ville AND b.id != :id AND b.statut = 'disponible'
     LIMIT 3",
    [':type' => $bien['type'], ':ville' => $bien['ville'], ':id' => $id]
);
?>

<div class="container" style="padding-top:2rem;padding-bottom:4rem;">

  <!-- Breadcrumb -->
  <nav style="font-size:.82rem;color:var(--gray-400);margin-bottom:1.5rem;">
    <a href="index.php">Accueil</a> /
    <a href="biens.php">Biens</a> /
    <a href="biens.php?type=<?= e($bien['type']) ?>"><?= labelType($bien['type']) ?></a> /
    <span><?= e($bien['titre']) ?></span>
  </nav>

  <div class="bien-detail-layout">
    <!-- ---- COLONNE GAUCHE ---- -->
    <div>
      <!-- Galerie -->
      <div class="bien-gallery">
        <div class="gallery-main" role="img" aria-label="Photo principale du bien">
          <img loading="lazy" src="<?= imageSrc($images[0]['filename'] ?? null) ?>"
               alt="<?= e($bien['titre']) ?>" id="main-img">
        </div>
        <?php if (count($images) > 1): ?>
        <div style="display:flex;gap:.5rem;margin-top:.5rem;overflow-x:auto;padding-bottom:.25rem;">
          <?php foreach ($images as $i => $img): ?>
          <img loading="lazy" class="gallery-thumb <?= $i===0?'active':'' ?>"
               src="<?= imageSrc($img['filename']) ?>"
               data-full="<?= imageSrc($img['filename']) ?>"
               alt="Photo <?= $i+1 ?>"
               style="width:80px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid <?= $i===0?'var(--gold)':'var(--gray-200)' ?>;flex-shrink:0;">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Infos principales -->
      <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
          <div>
            <p class="bien-card-type" style="margin-bottom:.3rem;">
              <?= labelType($bien['type']) ?>
              <?php if ($bien['operation']==='location'): ?>
                <span class="badge badge-gold" style="margin-left:.5rem;">Location</span>
              <?php else: ?>
                <span class="badge badge-gold" style="margin-left:.5rem;">Vente</span>
              <?php endif; ?>
              <?= badgeStatut($bien['statut']) ?>
            </p>
            <h1 style="font-size:1.8rem;"><?= e($bien['titre']) ?></h1>
            <p style="color:var(--gray-400);margin-top:.4rem;">
              📍 <?= e($bien['adresse']) ?>, <?= e($bien['cp']) ?> <?= e($bien['ville']) ?>
            </p>
          </div>
          <p style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);white-space:nowrap;">
            <?= formatPrix($bien['prix']) ?>
            <?php if ($bien['operation']==='location'): ?><small style="font-size:1rem;font-weight:400;color:var(--gray-400);">/mois</small><?php endif; ?>
          </p>
        </div>

        <!-- Caractéristiques -->
        <div class="bien-features-grid">
          <div class="bien-feature">
            <p class="bien-feature-label">Surface</p>
            <p class="bien-feature-value"><?= formatSurface($bien['surface']) ?></p>
          </div>
          <?php if ($bien['pieces']): ?>
          <div class="bien-feature">
            <p class="bien-feature-label">Pièces</p>
            <p class="bien-feature-value"><?= $bien['pieces'] ?></p>
          </div>
          <?php endif; ?>
          <?php if ($bien['chambres']): ?>
          <div class="bien-feature">
            <p class="bien-feature-label">Chambres</p>
            <p class="bien-feature-value"><?= $bien['chambres'] ?></p>
          </div>
          <?php endif; ?>
          <?php if ($bien['etage'] !== null): ?>
          <div class="bien-feature">
            <p class="bien-feature-label">Étage</p>
            <p class="bien-feature-value"><?= $bien['etage'] === 0 ? 'RDC' : $bien['etage'] ?></p>
          </div>
          <?php endif; ?>
        </div>

        <!-- Options -->
        <?php if (!empty($options)): ?>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;">
          <?php if (!empty($options['parking'])): ?><span class="badge badge-info">🚗 Parking</span><?php endif; ?>
          <?php if (!empty($options['cave'])): ?><span class="badge badge-info">🪟 Cave</span><?php endif; ?>
          <?php if (!empty($options['terrasse'])): ?><span class="badge badge-info">🌿 Terrasse</span><?php endif; ?>
          <?php if (!empty($options['ascenseur'])): ?><span class="badge badge-info">🛗 Ascenseur</span><?php endif; ?>
          <?php if (!empty($options['gardien'])): ?><span class="badge badge-info">👮 Gardien</span><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Description -->
      <div class="card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Description</h2>
        <p style="line-height:1.8;"><?= nl2br(e($bien['description'])) ?></p>
      </div>
    </div>

    <!-- ---- COLONNE DROITE (sticky) ---- -->
    <div class="bien-card-sticky">
      <!-- Agence info -->
      <div class="bien-contact-card" style="margin-bottom:1.5rem;">
        <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gold);margin-bottom:.5rem;">Agence</p>
        <h3 style="font-size:1rem;margin-bottom:.3rem;"><?= e($bien['agence_nom']) ?></h3>
        <p style="font-size:.85rem;color:var(--gray-400);margin-bottom:.2rem;">
          Agent : <strong><?= e($bien['agent_nom']) ?></strong>
        </p>
        <?php if ($bien['agent_tel']): ?>
        <a href="tel:<?= preg_replace('/\s/','',$bien['agent_tel']) ?>" class="btn btn-dark btn-sm mt-2" style="width:100%;justify-content:center;">
          📞 <?= e(formatTel($bien['agent_tel'])) ?>
        </a>
        <?php endif; ?>
      </div>

      <!-- Bouton offre -->
      <?php if ($bien['statut'] === 'disponible' && Auth::isLoggedIn() && !Auth::isAgent()): ?>
      <div style="margin-bottom:1rem;">
        <a href="<?= APP_URL ?>/faire-offre.php?id=<?= $bien['id'] ?>" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1rem;padding:.75rem;">
          <?= $bien['operation']==='vente' ? '🤝 Faire une offre d\'achat' : '🏠 Faire une offre de location' ?>
        </a>
        <p style="font-size:.72rem;color:var(--gray-400);text-align:center;margin-top:.4rem;">
          Soumettez votre offre directement à l'agent
        </p>
      </div>
      <?php endif; ?>

      <!-- Formulaire de contact -->
      <div class="bien-contact-card">
        <h3 style="font-size:1.1rem;margin-bottom:1.2rem;">Contacter l'agence</h3>

        <?php if (Auth::isLoggedIn() && (int)Auth::get('id') === (int)$bien['agent_id']): ?>
          <p style="font-size:.85rem;color:var(--gray-400);text-align:center;padding:1rem 0;">
            Vous êtes l'agent responsable de ce bien.
          </p>
        <?php elseif ($success): ?>
          <div class="alert alert-success">✅ Votre message a bien été envoyé ! Nous vous contacterons rapidement.</div>
        <?php else: ?>
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="bien-detail.php?id=<?= $id ?>">
            <input type="hidden" name="action" value="contact">
            <?= Auth::csrfField() ?>

            <div class="form-group">
              <label>Votre nom *</label>
              <input type="text" name="nom" required
                     value="<?= $bien ? e(Auth::get('prenom').' '.Auth::get('nom')) : '' ?>"
                     placeholder="Prénom Nom">
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input type="email" name="email" required
                     value="<?= e(Auth::get('email') ?? '') ?>"
                     placeholder="vous@exemple.fr">
            </div>
            <div class="form-group">
              <label>Téléphone</label>
              <input type="tel" name="telephone" placeholder="06 00 00 00 00">
            </div>
            <div class="form-group">
              <label>Message *</label>
              <textarea name="message" required placeholder="Je souhaite obtenir plus d'informations sur ce bien et organiser une visite..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Envoyer le message</button>
            <p style="font-size:.72rem;color:var(--gray-400);margin-top:.6rem;text-align:center;">Réponse garantie sous 24h</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Biens similaires -->
  <?php if (!empty($similaires)): ?>
  <div style="margin-top:4rem;">
    <h2 style="margin-bottom:1.5rem;">Biens similaires</h2>
    <div class="biens-grid">
      <?php foreach ($similaires as $s): ?>
      <a href="bien-detail.php?id=<?= $s['id'] ?>" class="bien-card">
        <div class="bien-card-img">
          <img loading="lazy" src="<?= imageSrc($s['image_principale']) ?>" alt="<?= e($s['titre']) ?>" loading="lazy">
        </div>
        <div class="bien-card-body">
          <p class="bien-card-type"><?= labelType($s['type']) ?></p>
          <h3 class="bien-card-title"><?= e($s['titre']) ?></h3>
          <div class="bien-card-footer">
            <p class="bien-card-price"><?= formatPrix($s['prix']) ?></p>
            <span class="btn btn-sm btn-outline">Voir →</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
