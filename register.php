<?php
// ============================================================
// register.php — Inscription client
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (Auth::isLoggedIn()) { header('Location: ' . APP_URL); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $nom       = clean($_POST['nom']       ?? '');
        $prenom    = clean($_POST['prenom']    ?? '');
        $email     = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $telephone = clean($_POST['telephone'] ?? '');
        $password  = $_POST['password']  ?? '';
        $confirm   = $_POST['password2'] ?? '';

        if ($telephone && !preg_match('/^[0-9]{10}$/', $telephone)) $errors[] = 'Le téléphone doit contenir exactement 10 chiffres.';
        if (!$nom)       $errors[] = 'Le nom est requis.';
        if (!$prenom)    $errors[] = 'Le prénom est requis.';
        if (!$email)     $errors[] = 'Email invalide.';
        if (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if ($password !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            // Vérifier unicité email
            $exist = Database::fetchOne('SELECT id FROM users WHERE email = :e', [':e' => $email]);
            if ($exist) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                Database::query(
                    'INSERT INTO users (nom, prenom, email, password, telephone, role) VALUES (:n,:p,:e,:h,:t,"client")',
                    [':n'=>$nom, ':p'=>$prenom, ':e'=>$email, ':h'=>$hash, ':t'=>$telephone]
                );
                Auth::login($email, $password);
                header('Location: ' . APP_URL . '/?welcome=1');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription — Ymmo</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-panel">
    <a href="<?= APP_URL ?>" class="logo logo-light" style="margin-bottom:3rem;font-size:2rem;"><span class="logo-y">Y</span>mmo</a>
    <h2>Rejoignez la communauté<br><em>Ymmo</em></h2>
    <p>Accédez à des milliers de biens immobiliers, sauvegardez vos favoris et contactez nos agents facilement.</p>
  </div>

  <div class="auth-form-side">
    <div class="auth-form-box">
      <h1>Créer un compte</h1>
      <p>Déjà inscrit ? <a href="login.php" style="color:var(--gold);font-weight:500;">Se connecter</a></p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e): ?><?= e($e) ?><br><?php endforeach; ?></div>
      <?php endif; ?>

      <form method="POST" style="margin-top:1.5rem;">
        <?= Auth::csrfField() ?>
        <div class="form-row">
          <div class="form-group">
            <label>Prénom *</label>
            <input type="text" name="prenom" required value="<?= e($_POST['prenom']??'') ?>" placeholder="Jean">
          </div>
          <div class="form-group">
            <label>Nom *</label>
            <input type="text" name="nom" required value="<?= e($_POST['nom']??'') ?>" placeholder="Dupont">
          </div>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required value="<?= e($_POST['email']??'') ?>" placeholder="vous@exemple.fr">
        </div>
        <div class="form-group">
          <label>Téléphone</label>
          <input type="tel" name="telephone" value="<?= e($_POST['telephone']??'') ?>" placeholder="0600000000"
                 maxlength="10" pattern="[0-9]{10}" inputmode="numeric" title="10 chiffres sans espace">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Mot de passe * <small>(min. 8 car.)</small></label>
            <input type="password" name="password" required placeholder="••••••••" minlength="8">
          </div>
          <div class="form-group">
            <label>Confirmer *</label>
            <input type="password" name="password2" required placeholder="••••••••">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:.5rem;">
          Créer mon compte
        </button>
        <p style="font-size:.76rem;color:var(--gray-400);text-align:center;margin-top:1rem;">
          En créant un compte, vous acceptez nos conditions d'utilisation.
        </p>
      </form>
      <p style="margin-top:1.5rem;font-size:.82rem;text-align:center;">
        <a href="<?= APP_URL ?>" style="color:var(--gray-400);">← Retour au site</a>
      </p>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
