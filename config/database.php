<?php
// ============================================================
// config/database.php — Connexion PDO (Singleton)
// ============================================================
// Principe SOLID : Single Responsibility + Open/Closed
// DRY : une seule instance PDO dans toute l'application

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    // Empêche l'instanciation directe
    private function __construct() {}
    private function __clone() {}

    /**
     * Retourne l'instance PDO unique (Singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    DB_HOST, DB_NAME, DB_CHARSET
                );

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // En production, ne jamais afficher les détails de connexion
                if (DEBUG_MODE) {
                    die('Erreur de connexion BDD : ' . $e->getMessage());
                } else {
                    die('Erreur de connexion. Veuillez réessayer plus tard.');
                }
            }
        }

        return self::$instance;
    }

    /**
     * Raccourci : exécute une requête préparée et retourne le Statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne toutes les lignes
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Retourne une seule ligne
     */
    public static function fetchOne(string $sql, array $params = []): array|false
    {
        return self::query($sql, $params)->fetch();
    }

    /**
     * Retourne l'ID du dernier insert
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
}
