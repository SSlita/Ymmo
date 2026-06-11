<?php
// ============================================================
// includes/functions.php — Fonctions utilitaires (principe DRY)
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ---- Sécurité & Nettoyage ----

/** Échappe une valeur pour l'affichage HTML (accepte null) */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Nettoie une entrée utilisateur (accepte null) */
function clean(?string $value): string
{
    return trim(strip_tags($value ?? ''));
}

// ---- Formatage ----

/** Formate un prix en euros */
function formatPrix(float $prix): string
{
    return number_format($prix, 0, ',', ' ') . ' €';
}

/** Formate une surface */
function formatSurface(float $surface): string
{
    return number_format($surface, 0, ',', ' ') . ' m²';
}

/** Formate une date au format français */
function formatDate(string $date): string
{
    return (new DateTime($date))->format('d/m/Y');
}


/**
 * Formate un numéro de téléphone français en XX XX XX XX XX
 * Accepte : 0612345678 / 06-12-34-56-78 / 06.12.34.56.78 / +33612345678
 */
function formatTel(?string $tel): string
{
    if (!$tel) return '';
    $digits = preg_replace('/\D/', '', $tel);
    // +33XXXXXXXXX -> 0XXXXXXXXX
    if (strlen($digits) === 11 && str_starts_with($digits, '33')) {
        $digits = '0' . substr($digits, 2);
    }
    if (strlen($digits) === 10) {
        return implode(' ', str_split($digits, 2));
    }
    return $tel;
}

/** Retourne le libellé du type de bien */
function labelType(string $type): string
{
    return match ($type) {
        'appartement' => 'Appartement',
        'maison'      => 'Maison',
        'bureau'      => 'Bureau',
        'local'       => 'Local commercial',
        'terrain'     => 'Terrain',
        default       => 'Autre',
    };
}

/** Retourne le libellé du statut */
function labelStatut(string $statut): string
{
    return match ($statut) {
        'disponible' => 'Disponible',
        'vendu'      => 'Vendu',
        'loue'       => 'Loué',
        'archive'    => 'Archivé',
        default      => $statut,
    };
}

/** Badge HTML coloré selon le statut */
function badgeStatut(string $statut): string
{
    $class = match ($statut) {
        'disponible' => 'badge-success',
        'vendu'      => 'badge-danger',
        'loue'       => 'badge-warning',
        'archive'    => 'badge-secondary',
        default      => 'badge-info',
    };
    return '<span class="badge ' . $class . '">' . labelStatut($statut) . '</span>';
}

// ---- Biens ----

/** Récupère les biens avec filtres et pagination */
function getBiens(array $filters = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $where  = ['b.statut != "archive"'];
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
    if (isset($filters['prix_min']) && $filters['prix_min'] !== null && $filters['prix_min'] !== '') {
        $where[] = 'b.prix >= :prix_min';
        $params[':prix_min'] = (float)$filters['prix_min'];
    }
    if (isset($filters['prix_max']) && $filters['prix_max'] !== null && $filters['prix_max'] !== '') {
        $where[] = 'b.prix <= :prix_max';
        $params[':prix_max'] = (float)$filters['prix_max'];
    }
    if (!empty($filters['surface_min'])) {
        $where[] = 'b.surface >= :surface_min';
        $params[':surface_min'] = $filters['surface_min'];
    }
    if (!empty($filters['pieces_min'])) {
        $where[] = 'b.pieces >= :pieces_min';
        $params[':pieces_min'] = $filters['pieces_min'];
    }
    if (!empty($filters['statut'])) {
        $where[] = 'b.statut = :statut';
        $params[':statut'] = $filters['statut'];
    }
    if (!empty($filters['agence_id'])) {
        $where[] = 'b.agence_id = :agence_id';
        $params[':agence_id'] = $filters['agence_id'];
    }
    if (!empty($filters['agent_id'])) {
        $where[] = 'b.agent_id = :agent_id';
        $params[':agent_id'] = $filters['agent_id'];
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sort        = in_array($filters['sort'] ?? '', ['prix', 'surface', 'created_at'])
        ? 'b.' . $filters['sort']
        : 'b.created_at';
    $order       = ($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $offset      = ($page - 1) * $perPage;

    // Compte total
    $total = (int) Database::fetchOne(
        "SELECT COUNT(*) AS total FROM biens b $whereClause",
        $params
    )['total'];

    // Requête principale
    $sql = "
        SELECT b.*,
               a.nom AS agence_nom,
               CONCAT(u.prenom, ' ', u.nom) AS agent_nom,
               img.filename AS image_principale
        FROM biens b
        LEFT JOIN agences a ON a.id = b.agence_id
        LEFT JOIN users   u ON u.id = b.agent_id
        LEFT JOIN images_biens img ON img.bien_id = b.id AND img.principale = 1
        $whereClause
        ORDER BY $sort $order
        LIMIT :limit OFFSET :offset
    ";

    $stmt = Database::getInstance()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items'      => $items,
        'total'      => $total,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => (int) ceil($total / $perPage),
    ];
}

/** Récupère un bien par son ID */
function getBienById(int $id): array|false
{
    return Database::fetchOne(
        "SELECT b.*,
                a.nom AS agence_nom, a.telephone AS agence_tel, a.email AS agence_email,
                CONCAT(u.prenom, ' ', u.nom) AS agent_nom,
                u.telephone AS agent_tel, u.email AS agent_email
         FROM biens b
         LEFT JOIN agences a ON a.id = b.agence_id
         LEFT JOIN users   u ON u.id = b.agent_id
         WHERE b.id = :id",
        [':id' => $id]
    );
}

/** Récupère les images d'un bien */
function getImagesBien(int $bienId): array
{
    return Database::fetchAll(
        'SELECT * FROM images_biens WHERE bien_id = :id ORDER BY principale DESC, ordre ASC',
        [':id' => $bienId]
    );
}

/** Retourne le placeholder si pas d'image */
function imageSrc(?string $filename): string
{
    if ($filename && file_exists(UPLOAD_PATH . $filename)) {
        return UPLOAD_URL . e($filename);
    }
    return APP_URL . '/assets/images/placeholder.svg';
}

// ---- Statistiques ----

/** Chiffre d'affaires total */
function getCA(): float
{
    $row = Database::fetchOne('SELECT COALESCE(SUM(prix_final),0) AS ca FROM transactions');
    return (float) $row['ca'];
}

/** Stats par ville */
function getStatsByVille(): array
{
    return Database::fetchAll(
        'SELECT ville, COUNT(*) AS nb_biens, AVG(prix) AS prix_moyen
         FROM biens WHERE statut != "archive"
         GROUP BY ville ORDER BY nb_biens DESC LIMIT 10'
    );
}

/** Évolution des transactions par mois (12 derniers mois) */
function getTransactionsParMois(): array
{
    return Database::fetchAll(
        "SELECT DATE_FORMAT(date_transaction,'%Y-%m') AS mois,
                COUNT(*) AS nb,
                SUM(prix_final) AS ca
         FROM transactions
         WHERE date_transaction >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY mois ORDER BY mois ASC"
    );
}

/** Répartition des biens par type */
function getRepartitionType(): array
{
    return Database::fetchAll(
        'SELECT type, COUNT(*) AS nb FROM biens WHERE statut != "archive" GROUP BY type ORDER BY nb DESC'
    );
}

// ---- Pagination HTML ----

function renderPagination(array $result, string $baseUrl): string
{
    if ($result['totalPages'] <= 1) return '';
    $html  = '<nav class="pagination"><ul>';
    for ($i = 1; $i <= $result['totalPages']; $i++) {
        $active = $i === $result['page'] ? ' active' : '';
        $html  .= "<li class=\"page-item$active\"><a class=\"page-link\" href=\"{$baseUrl}&page={$i}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ---- Flash messages ----

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string
{
    $f = getFlash();
    if (!$f) return '';
    return '<div class="alert alert-' . e($f['type']) . ' alert-dismissible">'
        . e($f['message'])
        . '<button type="button" class="btn-close" data-dismiss="alert">×</button></div>';
}
