<?php
// ============================================================
// models/Bien.php — Entité Bien (POO avancée, principes SOLID)
// ============================================================
// S : responsabilité unique — modélise un bien immobilier
// O : extensible sans modifier (Appartement extends Bien)
// L : substitution possible avec BienInterface
// I : interfaces séparées (Persistable, Searchable)
// D : dépend de l'abstraction Database, pas d'une implémentation
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/interfaces/Persistable.php';
require_once __DIR__ . '/../models/interfaces/Searchable.php';

class Bien implements Persistable, Searchable
{
    // ---- Propriétés typées (PHP 8) ----
    private int    $id        = 0;
    private string $titre     = '';
    private string $description = '';
    private string $type      = 'appartement';
    private string $statut    = 'disponible';
    private string $operation = 'vente';
    private float  $prix      = 0.0;
    private float  $surface   = 0.0;
    private int    $pieces    = 1;
    private int    $chambres  = 0;
    private string $adresse   = '';
    private string $ville     = '';
    private string $cp        = '';
    private int    $agenceId  = 0;
    private int    $agentId   = 0;
    private ?string $createdAt = null;

    // Types valides (principe de validation)
    public const TYPES      = ['appartement', 'maison', 'bureau', 'local', 'terrain', 'autre'];
    public const STATUTS    = ['disponible', 'vendu', 'loue', 'archive'];
    public const OPERATIONS = ['vente', 'location'];

    // ---- Constructeur privé — utiliser les factory methods ----
    private function __construct() {}

    // ---- Factory : depuis un tableau (résultat BDD) ----
    public static function fromArray(array $data): self
    {
        $bien = new self();
        $bien->id          = (int)($data['id'] ?? 0);
        $bien->titre       = $data['titre'] ?? '';
        $bien->description = $data['description'] ?? '';
        $bien->type        = $data['type'] ?? 'appartement';
        $bien->statut      = $data['statut'] ?? 'disponible';
        $bien->operation   = $data['operation'] ?? 'vente';
        $bien->prix        = (float)($data['prix'] ?? 0);
        $bien->surface     = (float)($data['surface'] ?? 0);
        $bien->pieces      = (int)($data['pieces'] ?? 1);
        $bien->chambres    = (int)($data['chambres'] ?? 0);
        $bien->adresse     = $data['adresse'] ?? '';
        $bien->ville       = $data['ville'] ?? '';
        $bien->cp          = $data['cp'] ?? '';
        $bien->agenceId    = (int)($data['agence_id'] ?? 0);
        $bien->agentId     = (int)($data['agent_id'] ?? 0);
        $bien->createdAt   = $data['created_at'] ?? null;
        return $bien;
    }

    // ---- Factory : depuis la BDD par ID ----
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM biens WHERE id = :id', [':id' => $id]);
        return $row ? self::fromArray($row) : null;
    }

    // ---- Persistable : sauvegarde (INSERT ou UPDATE) ----
    public function save(): bool
    {
        if (!$this->validate()) return false;

        if ($this->id === 0) {
            // INSERT
            Database::query(
                'INSERT INTO biens (titre, description, type, statut, operation, prix, surface,
                                   pieces, chambres, adresse, ville, cp, agence_id, agent_id)
                 VALUES (:titre,:desc,:type,:statut,:operation,:prix,:surface,
                         :pieces,:chambres,:adresse,:ville,:cp,:agenceId,:agentId)',
                $this->toParamArray()
            );
            $this->id = (int)Database::lastInsertId();
            return true;
        }

        // UPDATE
        Database::query(
            'UPDATE biens SET titre=:titre, description=:desc, type=:type, statut=:statut,
                              operation=:operation, prix=:prix, surface=:surface,
                              pieces=:pieces, chambres=:chambres, adresse=:adresse,
                              ville=:ville, cp=:cp, agence_id=:agenceId, agent_id=:agentId
             WHERE id=:id',
            array_merge($this->toParamArray(), [':id' => $this->id])
        );
        return true;
    }

    // ---- Persistable : suppression ----
    public function delete(): bool
    {
        if ($this->id === 0) return false;
        Database::query('DELETE FROM biens WHERE id = :id', [':id' => $this->id]);
        $this->id = 0;
        return true;
    }

    // ---- Searchable : recherche avec filtres ----
    public static function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = ["b.statut != 'archive'"];
        $params = [];

        if (!empty($filters['operation'])) {
            $where[] = 'b.operation = :operation';
            $params[':operation'] = $filters['operation'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'b.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['ville'])) {
            $where[] = 'b.ville LIKE :ville';
            $params[':ville'] = '%' . $filters['ville'] . '%';
        }
        if (!empty($filters['prix_max'])) {
            $where[] = 'b.prix <= :prix_max';
            $params[':prix_max'] = (float)$filters['prix_max'];
        }
        if (!empty($filters['prix_min'])) {
            $where[] = 'b.prix >= :prix_min';
            $params[':prix_min'] = (float)$filters['prix_min'];
        }
        if (!empty($filters['surface_min'])) {
            $where[] = 'b.surface >= :surface_min';
            $params[':surface_min'] = (float)$filters['surface_min'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'b.agent_id = :agent_id';
            $params[':agent_id'] = (int)$filters['agent_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(b.titre LIKE :q1 OR b.description LIKE :q2 OR b.ville LIKE :q3)';
            $q = '%' . $filters['q'] . '%';
            $params[':q1'] = $q; $params[':q2'] = $q; $params[':q3'] = $q;
        }

        $sql = 'SELECT b.* FROM biens b WHERE ' . implode(' AND ', $where)
             . ' ORDER BY b.created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        return array_map(
            fn($row) => self::fromArray($row),
            Database::fetchAll($sql, $params)
        );
    }

    public static function count(array $filters = []): int
    {
        $where  = ["b.statut != 'archive'"];
        $params = [];
        if (!empty($filters['agent_id'])) {
            $where[] = 'b.agent_id = :agent_id';
            $params[':agent_id'] = (int)$filters['agent_id'];
        }
        $row = Database::fetchOne('SELECT COUNT(*) AS c FROM biens b WHERE ' . implode(' AND ', $where), $params);
        return (int)($row['c'] ?? 0);
    }

    // ---- Validation métier ----
    public function validate(): bool
    {
        return $this->titre !== ''
            && $this->prix > 0
            && $this->surface > 0
            && in_array($this->type, self::TYPES, true)
            && in_array($this->statut, self::STATUTS, true)
            && in_array($this->operation, self::OPERATIONS, true)
            && $this->agentId > 0;
    }

    public function getErrors(): array
    {
        $errors = [];
        if ($this->titre === '')    $errors[] = 'Le titre est requis.';
        if ($this->prix <= 0)       $errors[] = 'Le prix doit être supérieur à 0.';
        if ($this->surface <= 0)    $errors[] = 'La surface doit être supérieure à 0.';
        if (!in_array($this->type, self::TYPES))       $errors[] = 'Type de bien invalide.';
        if (!in_array($this->operation, self::OPERATIONS)) $errors[] = 'Opération invalide.';
        return $errors;
    }

    // ---- Accesseurs (getters) ----
    public function getId(): int      { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    public function getDescription(): string { return $this->description; }
    public function getType(): string  { return $this->type; }
    public function getStatut(): string { return $this->statut; }
    public function getOperation(): string { return $this->operation; }
    public function getPrix(): float   { return $this->prix; }
    public function getSurface(): float { return $this->surface; }
    public function getPieces(): int   { return $this->pieces; }
    public function getChambres(): int { return $this->chambres; }
    public function getAdresse(): string { return $this->adresse; }
    public function getVille(): string { return $this->ville; }
    public function getCp(): string    { return $this->cp; }
    public function getAgenceId(): int { return $this->agenceId; }
    public function getAgentId(): int  { return $this->agentId; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function isDisponible(): bool { return $this->statut === 'disponible'; }
    public function isVente(): bool    { return $this->operation === 'vente'; }
    public function isLocation(): bool { return $this->operation === 'location'; }

    // ---- Mutateurs (setters avec validation) ----
    public function setTitre(string $v): self
    {
        if (strlen($v) > 200) throw new InvalidArgumentException('Titre trop long (200 max).');
        $this->titre = $v; return $this;
    }
    public function setPrix(float $v): self
    {
        if ($v < 0) throw new InvalidArgumentException('Le prix ne peut pas être négatif.');
        $this->prix = $v; return $this;
    }
    public function setSurface(float $v): self
    {
        if ($v <= 0) throw new InvalidArgumentException('Surface invalide.');
        $this->surface = $v; return $this;
    }
    public function setType(string $v): self
    {
        if (!in_array($v, self::TYPES, true)) throw new InvalidArgumentException("Type '$v' invalide.");
        $this->type = $v; return $this;
    }
    public function setStatut(string $v): self
    {
        if (!in_array($v, self::STATUTS, true)) throw new InvalidArgumentException("Statut '$v' invalide.");
        $this->statut = $v; return $this;
    }
    public function setOperation(string $v): self
    {
        if (!in_array($v, self::OPERATIONS, true)) throw new InvalidArgumentException("Opération '$v' invalide.");
        $this->operation = $v; return $this;
    }
    public function setDescription(string $v): self { $this->description = $v; return $this; }
    public function setAdresse(string $v): self     { $this->adresse = $v; return $this; }
    public function setVille(string $v): self       { $this->ville = $v; return $this; }
    public function setCp(string $v): self          { $this->cp = $v; return $this; }
    public function setPieces(int $v): self         { $this->pieces = max(1, $v); return $this; }
    public function setChambres(int $v): self       { $this->chambres = max(0, $v); return $this; }
    public function setAgenceId(int $v): self       { $this->agenceId = $v; return $this; }
    public function setAgentId(int $v): self        { $this->agentId = $v; return $this; }

    // ---- Sérialisation (DRY : un seul endroit pour mapper les colonnes) ----
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'titre'       => $this->titre,
            'description' => $this->description,
            'type'        => $this->type,
            'statut'      => $this->statut,
            'operation'   => $this->operation,
            'prix'        => $this->prix,
            'surface'     => $this->surface,
            'pieces'      => $this->pieces,
            'chambres'    => $this->chambres,
            'adresse'     => $this->adresse,
            'ville'       => $this->ville,
            'cp'          => $this->cp,
            'agence_id'   => $this->agenceId,
            'agent_id'    => $this->agentId,
            'created_at'  => $this->createdAt,
        ];
    }

    private function toParamArray(): array
    {
        return [
            ':titre'     => $this->titre,
            ':desc'      => $this->description,
            ':type'      => $this->type,
            ':statut'    => $this->statut,
            ':operation' => $this->operation,
            ':prix'      => $this->prix,
            ':surface'   => $this->surface,
            ':pieces'    => $this->pieces,
            ':chambres'  => $this->chambres,
            ':adresse'   => $this->adresse,
            ':ville'     => $this->ville,
            ':cp'        => $this->cp,
            ':agenceId'  => $this->agenceId,
            ':agentId'   => $this->agentId,
        ];
    }
}
