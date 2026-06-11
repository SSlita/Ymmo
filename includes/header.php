<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Ymmo</title>
  <meta name="description" content="Ymmo – Votre expert immobilier en France">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <!-- Performance : preload CSS critique -->
  <link rel="preload" href="<?= APP_URL ?>/assets/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css"></noscript>
  <!-- Fallback immédiat pour éviter FOUC -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <!-- SEO & accessibilité -->
  <meta name="theme-color" content="#0A1628">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= APP_URL . $_SERVER['REQUEST_URI'] ?>">
</head>
<body class="<?= $currentPage ?>-page">

<a href="#main-content" class="skip-link">Aller au contenu principal</a>

<header class="site-header" role="banner">
  <div class="header-inner container">
    <!-- Logo -->
    <a href="<?= APP_URL ?>" class="logo">
      <span class="logo-y">Y</span>mmo
    </a>

    <!-- Navigation principale -->
    <nav class="main-nav" id="main-nav" aria-label="Navigation principale">
      <a href="<?= APP_URL ?>/biens.php?operation=vente" class="nav-link <?= strpos($_SERVER['QUERY_STRING'],'vente')!==false?'active':''?>">Acheter</a>
      <a href="<?= APP_URL ?>/biens.php?operation=location" class="nav-link <?= strpos($_SERVER['QUERY_STRING'],'location')!==false?'active':''?>">Louer</a>
      <a href="<?= APP_URL ?>/biens.php" class="nav-link">Tous les biens</a>
    </nav>

    <!-- Actions utilisateur -->
    <div class="header-actions">
      <?php if ($user): ?>
        <?php if (Auth::isAgent()): ?>
          <a href="<?= APP_URL ?>/dashboard/" class="btn btn-outline btn-sm">
            <svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
          </a>
        <?php endif; ?>
        <div class="user-menu" id="user-menu">
          <button class="user-btn" id="user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="user-dropdown">
            <span class="user-avatar"><?= strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1)) ?></span>
            <span><?= e($user['prenom']) ?></span>
            <svg aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="user-dropdown" id="user-dropdown" role="menu" aria-labelledby="user-btn">
            <div class="dropdown-header">
              <strong><?= e($user['prenom'].' '.$user['nom']) ?></strong>
              <small><?= e(ucfirst($user['role'])) ?></small>
            </div>
            <?php if (Auth::isAgent()): ?>
              <a href="<?= APP_URL ?>/dashboard/" class="dropdown-item" role="menuitem">Tableau de bord</a>
              <a href="<?= APP_URL ?>/dashboard/messages.php" class="dropdown-item" role="menuitem">Messages</a>
              <a href="<?= APP_URL ?>/dashboard/offres.php" class="dropdown-item" role="menuitem">Offres reçues</a>
            <?php else: ?>
              <a href="<?= APP_URL ?>/mes-messages.php" class="dropdown-item" role="menuitem">Mes messages</a>
              <a href="<?= APP_URL ?>/mes-offres.php" class="dropdown-item" role="menuitem">Mes offres</a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/logout.php" class="dropdown-item dropdown-item-danger">Déconnexion</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-outline btn-sm">Connexion</a>
        <a href="<?= APP_URL ?>/register.php" class="btn btn-primary btn-sm">S'inscrire</a>
      <?php endif; ?>
    </div>

    <!-- Burger menu mobile -->
    <button class="burger" id="burger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main class="main-content" id="main-content" tabindex="-1">
<?php if ($flash = getFlash()): ?>
  <div class="container" style="margin-top:1rem">
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  </div>
<?php endif; ?>
