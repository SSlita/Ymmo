<?php
// ============================================================
// models/interfaces/Searchable.php
// Principe SOLID — I : Interface Segregation
// Contrat pour toute entité interrogeable (recherche + comptage)
// ============================================================

interface Searchable
{
    /** Recherche des entités selon des filtres */
    public static function search(array $filters = [], int $limit = 20, int $offset = 0): array;

    /** Compte les entités correspondant aux filtres */
    public static function count(array $filters = []): int;
}
