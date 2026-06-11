<?php
// ============================================================
// includes/autoload.php — Chargement automatique des classes
// Principe SOLID — D : Dependency Inversion
// Les contrôleurs dépendent des abstractions (interfaces),
// pas des implémentations concrètes.
// ============================================================

spl_autoload_register(function (string $className): void {
    // Interfaces
    $interfacePath = __DIR__ . '/../models/interfaces/' . $className . '.php';
    if (file_exists($interfacePath)) {
        require_once $interfacePath;
        return;
    }

    // Modèles
    $modelPath = __DIR__ . '/../models/' . $className . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
});
