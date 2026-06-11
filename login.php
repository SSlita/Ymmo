<?php
// ============================================================
// login.php — Page de connexion
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Déjà connecté → redirection
if (Auth::isLoggedIn()) {
    header('Location: ' . APP_URL . (Auth::isAgent() ? '/dashboard/' : '/'));
    exit;
}

$errors   = [];
$redirect = clean($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email)    $errors[] = 'Adresse email invalide.';
        if (!$password) $errors[] = 'Mot de passe requis.';

        if (empty($errors)) {
            if (Auth::login($email, $password)) {
                $dest = ($redirect && strpos($redirect, APP_URL) === 0)
                      ? $redirect
                      : (Auth::isAgent() ? APP_URL . '/dashboard/' : APP_URL . '/');
                header('Location: ' . $dest);
                exit;
            } else {
                $errors[] = 'Email ou mot de passe incorrect.';
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
  <title>Connexion — Ymmo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <!-- Panneau gauche décoratif -->
  <div class="auth-panel">
    <a href="<?= APP_URL ?>" class="logo logo-light" style="margin-bottom:3rem;font-size:2rem;">
      <span class="logo-y">Y</span>mmo
    </a>
    <h2>Bienvenue sur<br>votre espace Ymmo</h2>
    <p>Accédez à votre tableau de bord, gérez vos biens et suivez vos performances en temps réel.</p>
    <div class="testimonial">
      <p style="color:rgba(255,255,255,.8);font-style:italic;margin-bottom:.7rem;">
        "Grâce à Ymmo, j'ai vendu 3 biens en moins d'un mois. La plateforme est intuitive et les analyses de marché sont précieuses."
      </p>
      <p style="font-size:.82rem;color:var(--gold);">— Lucas Martin, Agent commercial</p>
    </div>
  </div>

  <!-- Panneau droit formulaire -->
  <div class="auth-form-side">
    <div class="auth-form-box">
      <h1>Connexion</h1>
      <p>Pas encore de compte ? <a href="register.php" style="color:var(--gold);font-weight:500;">S'inscrire</a></p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>" style="margin-top:1.5rem;">
        <?= Auth::csrfField() ?>

        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="vous@exemple.fr">
        </div>

        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required autocomplete="current-password"
                 placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:.5rem;">
          Se connecter
        </button>
      </form>

      <!-- Comptes de démonstration -->
      <div style="margin-top:2rem;padding:1.2rem;background:var(--gray-100);border-radius:var(--radius);font-size:.82rem;">
        <p style="font-weight:600;color:var(--navy);margin-bottom:.6rem;">🔑 Comptes de démonstration</p>
        <p><strong>Admin :</strong> admin@ymmo.fr</p>
        <p><strong>Agent :</strong> agent1@ymmo.fr</p>
        <p><strong>Client :</strong> client1@ymmo.fr</p>
        <p style="color:var(--gray-400);margin-top:.5rem;">Mot de passe : <code>Ymmo2024!</code></p>
      </div>

      <p style="margin-top:2rem;font-size:.82rem;text-align:center;">
        <a href="<?= APP_URL ?>" style="color:var(--gray-400);">← Retour au site</a>
      </p>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
