<?php
// ============================================================
// models/interfaces/Persistable.php
// Principe SOLID — I : Interface Segregation
// Contrat minimal pour toute entité persistable en BDD
// ============================================================

interface Persistable
{
    /** Sauvegarde l'entité (INSERT si id=0, UPDATE sinon) */
    public function save(): bool;

    /** Supprime l'entité de la base */
    public function delete(): bool;

    /** Valide les données avant persistance */
    public function validate(): bool;

    /** Retourne la liste des erreurs de validation */
    public function getErrors(): array;

    /** Sérialise l'entité en tableau associatif */
    public function toArray(): array;
}
