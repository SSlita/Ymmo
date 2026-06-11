<?php
// ============================================================
// models/Offre.php — Entité Offre (POO avancée)
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/interfaces/Persistable.php';

class Offre implements Persistable
{
    private int    $id           = 0;
    private int    $bienId       = 0;
    private int    $clientId     = 0;
    private int    $agentId      = 0;
    private string $typeOffre    = 'achat';
    private float  $prixPropose  = 0.0;
    private string $message      = '';
    private string $statut       = 'en_attente';
    private string $noteAgent    = '';
    private ?string $createdAt   = null;

    public const TYPES   = ['achat', 'location'];
    public const STATUTS = ['en_attente', 'acceptee', 'refusee', 'annulee'];

    private function __construct() {}

    public static function fromArray(array $data): self
    {
        $o = new self();
        $o->id          = (int)($data['id'] ?? 0);
        $o->bienId      = (int)($data['bien_id'] ?? 0);
        $o->clientId    = (int)($data['client_id'] ?? 0);
        $o->agentId     = (int)($data['agent_id'] ?? 0);
        $o->typeOffre   = $data['type_offre'] ?? 'achat';
        $o->prixPropose = (float)($data['prix_propose'] ?? 0);
        $o->message     = $data['message'] ?? '';
        $o->statut      = $data['statut'] ?? 'en_attente';
        $o->noteAgent   = $data['note_agent'] ?? '';
        $o->createdAt   = $data['created_at'] ?? null;
        return $o;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM offres WHERE id = :id', [':id' => $id]);
        return $row ? self::fromArray($row) : null;
    }

    /** Retourne toutes les offres en attente pour un agent */
    public static function pendingForAgent(int $agentId): array
    {
        return array_map(
            fn($r) => self::fromArray($r),
            Database::fetchAll(
                'SELECT o.* FROM offres o
                 JOIN biens b ON b.id = o.bien_id
                 WHERE b.agent_id = :aid AND o.statut = "en_attente"
                 ORDER BY o.created_at ASC',
                [':aid' => $agentId]
            )
        );
    }

    /** Retourne toutes les offres d'un client */
    public static function forClient(int $clientId): array
    {
        return array_map(
            fn($r) => self::fromArray($r),
            Database::fetchAll(
                'SELECT * FROM offres WHERE client_id = :cid ORDER BY created_at DESC',
                [':cid' => $clientId]
            )
        );
    }

    public function save(): bool
    {
        if (!$this->validate()) return false;

        if ($this->id === 0) {
            Database::query(
                'INSERT INTO offres (bien_id, client_id, agent_id, type_offre, prix_propose, message, statut)
                 VALUES (:bienId,:clientId,:agentId,:typeOffre,:prixPropose,:message,:statut)',
                $this->toParamArray()
            );
            $this->id = (int)Database::lastInsertId();
            return true;
        }

        Database::query(
            'UPDATE offres SET statut=:statut, note_agent=:noteAgent WHERE id=:id',
            [':statut' => $this->statut, ':noteAgent' => $this->noteAgent, ':id' => $this->id]
        );
        return true;
    }

    public function delete(): bool
    {
        if ($this->id === 0) return false;
        Database::query('DELETE FROM offres WHERE id = :id', [':id' => $this->id]);
        return true;
    }

    public function validate(): bool
    {
        return $this->bienId > 0
            && $this->clientId > 0
            && $this->prixPropose > 0
            && in_array($this->typeOffre, self::TYPES, true)
            && in_array($this->statut, self::STATUTS, true);
    }

    public function getErrors(): array
    {
        $errors = [];
        if ($this->prixPropose <= 0) $errors[] = 'Le prix proposé est invalide.';
        if ($this->bienId <= 0)      $errors[] = 'Bien manquant.';
        return $errors;
    }

    /** Accepter l'offre : crée la transaction et met à jour le bien */
    public function accepter(string $note = ''): bool
    {
        if ($this->statut !== 'en_attente') return false;

        $this->statut    = 'acceptee';
        $this->noteAgent = $note;
        $this->save();

        // Marquer le bien comme vendu/loué
        $newStatut = $this->typeOffre === 'achat' ? 'vendu' : 'loue';
        Database::query('UPDATE biens SET statut=:s WHERE id=:id', [':s' => $newStatut, ':id' => $this->bienId]);

        // Annuler les autres offres en attente sur ce bien
        Database::query(
            'UPDATE offres SET statut="annulee", note_agent="Bien non disponible suite à l\'acceptation d\'une autre offre."
             WHERE bien_id=:bid AND id!=:oid AND statut="en_attente"',
            [':bid' => $this->bienId, ':oid' => $this->id]
        );

        // Créer la transaction
        $commission = round($this->prixPropose * COMMISSION_RATE / 100, 2);
        Database::query(
            'INSERT INTO transactions (bien_id, acheteur_id, agent_id, type_transaction, prix_final, commission, date_transaction)
             VALUES (:bid, :cid, :aid, :type, :prix, :com, NOW())',
            [
                ':bid'  => $this->bienId,
                ':cid'  => $this->clientId,
                ':aid'  => $this->agentId,
                ':type' => $this->typeOffre === 'achat' ? 'vente' : 'location',
                ':prix' => $this->prixPropose,
                ':com'  => $commission,
            ]
        );
        return true;
    }

    /** Refuser l'offre */
    public function refuser(string $note = ''): bool
    {
        if ($this->statut !== 'en_attente') return false;
        $this->statut    = 'refusee';
        $this->noteAgent = $note;
        return $this->save();
    }

    // ---- Getters ----
    public function getId(): int         { return $this->id; }
    public function getBienId(): int     { return $this->bienId; }
    public function getClientId(): int   { return $this->clientId; }
    public function getAgentId(): int    { return $this->agentId; }
    public function getTypeOffre(): string { return $this->typeOffre; }
    public function getPrixPropose(): float { return $this->prixPropose; }
    public function getMessage(): string { return $this->message; }
    public function getStatut(): string  { return $this->statut; }
    public function getNoteAgent(): string { return $this->noteAgent; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function isEnAttente(): bool  { return $this->statut === 'en_attente'; }

    // ---- Setters ----
    public function setBienId(int $v): self     { $this->bienId = $v; return $this; }
    public function setClientId(int $v): self   { $this->clientId = $v; return $this; }
    public function setAgentId(int $v): self    { $this->agentId = $v; return $this; }
    public function setPrixPropose(float $v): self
    {
        if ($v <= 0) throw new InvalidArgumentException('Prix invalide.');
        $this->prixPropose = $v; return $this;
    }
    public function setTypeOffre(string $v): self
    {
        if (!in_array($v, self::TYPES, true)) throw new InvalidArgumentException("Type '$v' invalide.");
        $this->typeOffre = $v; return $this;
    }
    public function setMessage(string $v): self { $this->message = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'bien_id'      => $this->bienId,
            'client_id'    => $this->clientId,
            'agent_id'     => $this->agentId,
            'type_offre'   => $this->typeOffre,
            'prix_propose' => $this->prixPropose,
            'message'      => $this->message,
            'statut'       => $this->statut,
            'note_agent'   => $this->noteAgent,
            'created_at'   => $this->createdAt,
        ];
    }

    private function toParamArray(): array
    {
        return [
            ':bienId'      => $this->bienId,
            ':clientId'    => $this->clientId,
            ':agentId'     => $this->agentId,
            ':typeOffre'   => $this->typeOffre,
            ':prixPropose' => $this->prixPropose,
            ':message'     => $this->message,
            ':statut'      => $this->statut,
        ];
    }
}
