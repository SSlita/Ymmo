<?php
// ============================================================
// models/User.php — Entité Utilisateur (POO avancée)
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/interfaces/Persistable.php';

class User implements Persistable
{
    private int    $id        = 0;
    private string $prenom    = '';
    private string $nom       = '';
    private string $email     = '';
    private string $password  = '';
    private string $role      = 'client';
    private string $telephone = '';
    private ?int   $agenceId  = null;
    private bool   $actif     = true;
    private ?string $createdAt = null;

    public const ROLES = ['admin', 'agent', 'client'];

    private function __construct() {}

    public static function fromArray(array $data): self
    {
        $u = new self();
        $u->id        = (int)($data['id'] ?? 0);
        $u->prenom    = $data['prenom'] ?? '';
        $u->nom       = $data['nom'] ?? '';
        $u->email     = $data['email'] ?? '';
        $u->password  = $data['password'] ?? '';
        $u->role      = $data['role'] ?? 'client';
        $u->telephone = $data['telephone'] ?? '';
        $u->agenceId  = isset($data['agence_id']) ? (int)$data['agence_id'] : null;
        $u->actif     = (bool)($data['actif'] ?? true);
        $u->createdAt = $data['created_at'] ?? null;
        return $u;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $id]);
        return $row ? self::fromArray($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Database::fetchOne('SELECT * FROM users WHERE email = :email', [':email' => $email]);
        return $row ? self::fromArray($row) : null;
    }

    /** Vérifie le mot de passe */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password);
    }

    /** Hache et définit le mot de passe */
    public function setPasswordPlain(string $plain): self
    {
        if (strlen($plain) < 8) throw new InvalidArgumentException('Mot de passe trop court (8 min).');
        $this->password = password_hash($plain, PASSWORD_BCRYPT);
        return $this;
    }

    public function save(): bool
    {
        if (!$this->validate()) return false;

        if ($this->id === 0) {
            Database::query(
                'INSERT INTO users (prenom, nom, email, password, role, telephone, agence_id, actif)
                 VALUES (:prenom,:nom,:email,:pwd,:role,:tel,:agenceId,:actif)',
                $this->toParamArray()
            );
            $this->id = (int)Database::lastInsertId();
            return true;
        }

        Database::query(
            'UPDATE users SET prenom=:prenom, nom=:nom, email=:email,
                              role=:role, telephone=:tel, agence_id=:agenceId, actif=:actif
             WHERE id=:id',
            array_merge($this->toParamArray(), [':id' => $this->id])
        );
        return true;
    }

    public function delete(): bool
    {
        if ($this->id === 0) return false;
        Database::query('UPDATE users SET actif=0 WHERE id=:id', [':id' => $this->id]);
        return true;
    }

    public function validate(): bool
    {
        return $this->prenom !== ''
            && $this->nom !== ''
            && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false
            && in_array($this->role, self::ROLES, true);
    }

    public function getErrors(): array
    {
        $errors = [];
        if ($this->prenom === '') $errors[] = 'Prénom requis.';
        if ($this->nom === '')    $errors[] = 'Nom requis.';
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        return $errors;
    }

    // Getters
    public function getId(): int         { return $this->id; }
    public function getPrenom(): string  { return $this->prenom; }
    public function getNom(): string     { return $this->nom; }
    public function getNomComplet(): string { return $this->prenom . ' ' . $this->nom; }
    public function getEmail(): string   { return $this->email; }
    public function getRole(): string    { return $this->role; }
    public function getTelephone(): string { return $this->telephone; }
    public function getAgenceId(): ?int  { return $this->agenceId; }
    public function isActif(): bool      { return $this->actif; }
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isAgent(): bool      { return in_array($this->role, ['agent', 'admin']); }
    public function isClient(): bool     { return $this->role === 'client'; }
    public function getCreatedAt(): ?string { return $this->createdAt; }

    // Setters
    public function setPrenom(string $v): self  { $this->prenom = trim($v); return $this; }
    public function setNom(string $v): self     { $this->nom = trim($v); return $this; }
    public function setTelephone(string $v): self { $this->telephone = $v; return $this; }
    public function setAgenceId(?int $v): self  { $this->agenceId = $v; return $this; }
    public function setActif(bool $v): self     { $this->actif = $v; return $this; }
    public function setEmail(string $v): self
    {
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email invalide.');
        $this->email = $v; return $this;
    }
    public function setRole(string $v): self
    {
        if (!in_array($v, self::ROLES, true)) throw new InvalidArgumentException("Rôle '$v' invalide.");
        $this->role = $v; return $this;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'prenom'    => $this->prenom,
            'nom'       => $this->nom,
            'email'     => $this->email,
            'role'      => $this->role,
            'telephone' => $this->telephone,
            'agence_id' => $this->agenceId,
            'actif'     => $this->actif,
            'created_at'=> $this->createdAt,
        ];
    }

    private function toParamArray(): array
    {
        return [
            ':prenom'   => $this->prenom,
            ':nom'      => $this->nom,
            ':email'    => $this->email,
            ':pwd'      => $this->password,
            ':role'     => $this->role,
            ':tel'      => $this->telephone,
            ':agenceId' => $this->agenceId,
            ':actif'    => (int)$this->actif,
        ];
    }
}
