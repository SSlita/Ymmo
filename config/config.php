<?php
// ============================================================
// config/config.php — Configuration globale de l'application
// ============================================================

// --- Base de données (XAMPP par défaut) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ymmo');
define('DB_USER', 'root');
define('DB_PASS', '');          // Vide par défaut sur XAMPP
define('DB_CHARSET', 'utf8mb4');

// --- Application ---
define('APP_NAME',    'Ymmo');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/ymmo');   // Adapter selon votre config XAMPP

// --- Chemins ---
define('ROOT_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/images/uploads/');
define('UPLOAD_URL',  APP_URL . '/assets/images/uploads/');

// --- Sécurité ---
define('SESSION_LIFETIME', 3600);           // 1 heure
define('BCRYPT_COST',      12);
define('CSRF_TOKEN_NAME',  '_csrf_token');

// --- Pagination ---
define('ITEMS_PER_PAGE', 9);

// --- Commission agence (%) ---
define('COMMISSION_RATE', 2.0);

// --- Environnement ---
define('DEBUG_MODE', true);   // Mettre false en production

// --- Affichage des erreurs selon l'environnement ---
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Démarrage de session sécurisé
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // true en HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Autoloader des modèles (POO)
require_once __DIR__ . '/../includes/autoload.php';
