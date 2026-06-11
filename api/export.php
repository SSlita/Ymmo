<?php
// ============================================================
// api/export.php — Export CSV des données
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAgent();

$type = clean($_GET['type'] ?? 'transactions');

switch ($type) {
    case 'transactions':
        $rows = Database::fetchAll(
            "SELECT t.id, t.date_transaction, b.titre AS bien, b.type, b.ville,
                    t.prix_final, t.commission, t.type_transaction,
                    CONCAT(u.prenom,' ',u.nom) AS acheteur,
                    CONCAT(ag.prenom,' ',ag.nom) AS agent
             FROM transactions t
             LEFT JOIN biens b ON b.id=t.bien_id
             LEFT JOIN users u  ON u.id=t.acheteur_id
             LEFT JOIN users ag ON ag.id=t.agent_id
             ORDER BY t.date_transaction DESC"
        );
        $headers = ['ID','Date','Bien','Type','Ville','Prix final','Commission','Opération','Acheteur','Agent'];
        $filename = 'ymmo_transactions_' . date('Ymd') . '.csv';
        break;

    case 'biens':
        $rows = Database::fetchAll(
            "SELECT b.id, b.titre, b.type, b.operation, b.statut, b.prix, b.surface,
                    b.pieces, b.ville, b.cp, b.created_at,
                    CONCAT(u.prenom,' ',u.nom) AS agent, a.nom AS agence
             FROM biens b
             LEFT JOIN users u ON u.id=b.agent_id
             LEFT JOIN agences a ON a.id=b.agence_id
             WHERE b.statut != 'archive'
             ORDER BY b.created_at DESC"
        );
        $headers = ['ID','Titre','Type','Opération','Statut','Prix','Surface','Pièces','Ville','CP','Créé le','Agent','Agence'];
        $filename = 'ymmo_biens_' . date('Ymd') . '.csv';
        break;

    default:
        http_response_code(400);
        die('Type d\'export invalide.');
}

// En-têtes HTTP pour téléchargement
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, $headers, ';');

foreach ($rows as $row) {
    fputcsv($out, array_values($row), ';');
}
fclose($out);
exit;
