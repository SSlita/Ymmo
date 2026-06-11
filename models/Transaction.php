<?php
// ============================================================
// models/Transaction.php — Entité Transaction (POO avancée)
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/interfaces/Persistable.php';

class Transaction implements Persistable
{
    private int    $id              = 0;
    private int    $bienId          = 0;
    private int    $acheteurId      = 0;
    private int    $agentId         = 0;
    private float  $prixFinal       = 0.0;
    private float  $commission      = 0.0;
    private string $typeTransaction = 'vente';
    private string $dateTransaction = '';
    private string $notes           = '';
    private ?string $createdAt      = null;

    public const TYPES = ['vente', 'location'];

    private function __construct() {}

    public static function fromArray(array $data): self
    {
        $t = new self();
        $t->id              = (int)($data['id'] ?? 0);
        $t->bienId          = (int)($data['bien_id'] ?? 0);
        $t->acheteurId      = (int)($data['acheteur_id'] ?? 0);
        $t->agentId         = (int)($data['agent_id'] ?? 0);
        $t->prixFinal       = (float)($data['prix_final'] ?? 0);
        $t->commission      = (float)($data['commission'] ?? 0);
        $t->typeTransaction = $data['type_transaction'] ?? 'vente';
        $t->dateTransaction = $data['date_transaction'] ?? '';
        $t->notes           = $data['notes'] ?? '';
        $t->createdAt       = $data['created_at'] ?? null;
        return $t;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM transactions WHERE id = :id', [':id' => $id]);
        return $row ? self::fromArray($row) : null;
    }

    /** CA total pour un agent donné (ou global si null) */
    public static function chiffreAffaires(?int $agentId = null): float
    {
        $sql    = 'SELECT COALESCE(SUM(prix_final), 0) AS ca FROM transactions';
        $params = [];
        if ($agentId !== null) {
            $sql .= ' WHERE agent_id = :aid';
            $params[':aid'] = $agentId;
        }
        return (float)(Database::fetchOne($sql, $params)['ca'] ?? 0);
    }

    /** Nombre de transactions par mois (12 derniers mois) */
    public static function parMois(?int $agentId = null): array
    {
        $where  = $agentId ? 'WHERE agent_id=:aid AND ' : 'WHERE ';
        $where .= 'date_transaction >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)';
        $params = $agentId ? [':aid' => $agentId] : [];

        return Database::fetchAll(
            "SELECT DATE_FORMAT(date_transaction,'%b %Y') AS mois,
                    DATE_FORMAT(date_transaction,'%Y-%m') AS mois_key,
                    COUNT(*) AS nb,
                    COALESCE(SUM(prix_final),0) AS ca
             FROM transactions $where
             GROUP BY mois_key, mois ORDER BY mois_key ASC",
            $params
        );
    }

    public function save(): bool
    {
        if (!$this->validate()) return false;

        if ($this->id === 0) {
            Database::query(
                'INSERT INTO transactions (bien_id, acheteur_id, agent_id, type_transaction, prix_final, commission, date_transaction, notes)
                 VALUES (:bienId,:acheteurId,:agentId,:type,:prix,:com,:date,:notes)',
                $this->toParamArray()
            );
            $this->id = (int)Database::lastInsertId();
            return true;
        }

        Database::query(
            'UPDATE transactions SET notes=:notes WHERE id=:id',
            [':notes' => $this->notes, ':id' => $this->id]
        );
        return true;
    }

    public function delete(): bool
    {
        if ($this->id === 0) return false;
        Database::query('DELETE FROM transactions WHERE id = :id', [':id' => $this->id]);
        return true;
    }

    public function validate(): bool
    {
        return $this->bienId > 0
            && $this->acheteurId > 0
            && $this->prixFinal > 0
            && in_array($this->typeTransaction, self::TYPES, true);
    }

    public function getErrors(): array
    {
        $errors = [];
        if ($this->prixFinal <= 0) $errors[] = 'Prix final invalide.';
        if ($this->bienId <= 0)    $errors[] = 'Bien manquant.';
        return $errors;
    }

    // Getters
    public function getId(): int             { return $this->id; }
    public function getBienId(): int         { return $this->bienId; }
    public function getAcheteurId(): int     { return $this->acheteurId; }
    public function getAgentId(): int        { return $this->agentId; }
    public function getPrixFinal(): float    { return $this->prixFinal; }
    public function getCommission(): float   { return $this->commission; }
    public function getType(): string        { return $this->typeTransaction; }
    public function getDate(): string        { return $this->dateTransaction; }
    public function getNotes(): string       { return $this->notes; }
    public function getCreatedAt(): ?string  { return $this->createdAt; }

    // Setters
    public function setBienId(int $v): self      { $this->bienId = $v; return $this; }
    public function setAcheteurId(int $v): self  { $this->acheteurId = $v; return $this; }
    public function setAgentId(int $v): self     { $this->agentId = $v; return $this; }
    public function setPrixFinal(float $v): self { $this->prixFinal = $v; return $this; }
    public function setNotes(string $v): self    { $this->notes = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'bien_id'          => $this->bienId,
            'acheteur_id'      => $this->acheteurId,
            'agent_id'         => $this->agentId,
            'prix_final'       => $this->prixFinal,
            'commission'       => $this->commission,
            'type_transaction' => $this->typeTransaction,
            'date_transaction' => $this->dateTransaction,
            'notes'            => $this->notes,
        ];
    }

    private function toParamArray(): array
    {
        return [
            ':bienId'     => $this->bienId,
            ':acheteurId' => $this->acheteurId,
            ':agentId'    => $this->agentId,
            ':type'       => $this->typeTransaction,
            ':prix'       => $this->prixFinal,
            ':com'        => $this->commission,
            ':date'       => $this->dateTransaction ?: date('Y-m-d'),
            ':notes'      => $this->notes,
        ];
    }
}
