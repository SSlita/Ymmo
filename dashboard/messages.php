<?php
// ============================================================
// dashboard/messages.php — Messagerie interne (agent/admin)
// ============================================================
$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$agentId = Auth::get('id');
$isAdmin = Auth::isAdmin();

// ---- Action POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action    = clean($_POST['action'] ?? '');
    $demandeId = (int)($_POST['demande_id'] ?? 0);

    // Changer statut
    if (in_array($action, ['nouvelle','traitee','archivee']) && $demandeId) {
        $check = $isAdmin
            ? Database::fetchOne('SELECT id FROM demandes WHERE id=:id', [':id'=>$demandeId])
            : Database::fetchOne('SELECT d.id FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE d.id=:did AND b.agent_id=:aid', [':did'=>$demandeId,':aid'=>$agentId]);
        if ($check) {
            Database::query('UPDATE demandes SET statut=:s WHERE id=:id', [':s'=>$action,':id'=>$demandeId]);
        }
        header('Location: messages.php?statut='.urlencode(clean($_POST['back_statut']??'')).'&q='.urlencode(clean($_POST['back_q']??'')).'&id='.$demandeId);
        exit;
    }

    // Envoyer une réponse interne
    if ($action === 'reply' && $demandeId) {
        $texte = trim(clean($_POST['reply_text'] ?? ''));
        if ($texte !== '') {
            // Vérifier accès
            $check = $isAdmin
                ? Database::fetchOne('SELECT id, client_id FROM demandes WHERE id=:id', [':id'=>$demandeId])
                : Database::fetchOne('SELECT d.id, d.client_id FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE d.id=:did AND b.agent_id=:aid', [':did'=>$demandeId,':aid'=>$agentId]);
            if ($check) {
                Database::query(
                    'INSERT INTO messages_replies (demande_id, auteur_id, message) VALUES (:did, :uid, :msg)',
                    [':did'=>$demandeId, ':uid'=>$agentId, ':msg'=>$texte]
                );
                // Passer la demande en "traitee" automatiquement
                Database::query("UPDATE demandes SET statut='traitee' WHERE id=:id AND statut='nouvelle'", [':id'=>$demandeId]);
            }
        }
        header('Location: messages.php?statut='.urlencode(clean($_POST['back_statut']??'')).'&q='.urlencode(clean($_POST['back_q']??'')).'&id='.$demandeId);
        exit;
    }
}

// ---- Filtres ----
$filtreStatut = clean($_GET['statut'] ?? '');
$filtreSearch = clean($_GET['q']      ?? '');
if (!in_array($filtreStatut, ['nouvelle','traitee','archivee',''])) $filtreStatut = '';

// ---- Liste des demandes (fix PDO: pas de placeholder réutilisé) ----
$whereParts = [];
$params     = [];

if (!$isAdmin) {
    $whereParts[] = 'b.agent_id = :agent_id';
    $params[':agent_id'] = $agentId;
}
if ($filtreStatut !== '') {
    $whereParts[] = 'd.statut = :statut';
    $params[':statut'] = $filtreStatut;
}
if ($filtreSearch !== '') {
    // Éviter la réutilisation du même placeholder en PDO
    $whereParts[] = '(d.nom LIKE :q1 OR d.email LIKE :q2 OR d.message LIKE :q3 OR b.titre LIKE :q4)';
    $q = '%' . $filtreSearch . '%';
    $params[':q1'] = $q;
    $params[':q2'] = $q;
    $params[':q3'] = $q;
    $params[':q4'] = $q;
}

$whereStr = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$demandes = Database::fetchAll(
    "SELECT d.*, b.titre AS bien_titre, b.id AS bien_id_ref
     FROM demandes d
     JOIN biens b ON b.id = d.bien_id
     $whereStr
     ORDER BY d.created_at DESC",
    $params
);

// ---- Compteurs par statut ----
$countParams = $isAdmin ? [] : [':aid' => $agentId];
$countWhere  = $isAdmin ? '' : 'JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:aid';
$countRows   = Database::fetchAll("SELECT d.statut, COUNT(*) AS nb FROM demandes d $countWhere GROUP BY d.statut", $countParams);
$counts = [''=>0,'nouvelle'=>0,'traitee'=>0,'archivee'=>0];
foreach ($countRows as $row) { $counts[$row['statut']] = (int)$row['nb']; $counts[''] += (int)$row['nb']; }

// ---- Détail + fil de réponses ----
$selectedId = (int)($_GET['id'] ?? 0);
$selected   = null;
$replies    = [];
if ($selectedId) {
    $sel = $isAdmin
        ? Database::fetchOne('SELECT d.*, b.titre AS bien_titre, b.id AS bien_id_ref, b.ville, b.prix, b.type FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE d.id=:id', [':id'=>$selectedId])
        : Database::fetchOne('SELECT d.*, b.titre AS bien_titre, b.id AS bien_id_ref, b.ville, b.prix, b.type FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE d.id=:id AND b.agent_id=:aid', [':id'=>$selectedId,':aid'=>$agentId]);
    if ($sel) {
        $selected = $sel;
        $replies  = Database::fetchAll(
            'SELECT r.*, CONCAT(u.prenom," ",u.nom) AS auteur_nom, u.role AS auteur_role
             FROM messages_replies r
             JOIN users u ON u.id = r.auteur_id
             WHERE r.demande_id = :did
             ORDER BY r.created_at ASC',
            [':did'=>$selectedId]
        );
        // Marquer les réponses comme lues (celles pas envoyées par moi)
        Database::query('UPDATE messages_replies SET lu=1 WHERE demande_id=:did AND auteur_id != :uid', [':did'=>$selectedId,':uid'=>$agentId]);
    }
}
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/partials/sidebar.php'; ?>

  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header">
      <div>
        <h1 style="font-size:1.8rem;">Messages</h1>
        <p style="color:var(--gray-400);">Demandes de contact et échanges avec les clients</p>
      </div>
    </div>

    <!-- Onglets statut -->
    <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
      <?php
        $tabs = [''=>'Tous','nouvelle'=>'Nouvelles','traitee'=>'Traitées','archivee'=>'Archivées'];
        $tabColors = ['nouvelle'=>'var(--danger)','traitee'=>'var(--success)','archivee'=>'var(--gray-400)',''=>'var(--navy)'];
        foreach ($tabs as $val => $label):
          $active = $filtreStatut === $val;
          $col    = $tabColors[$val];
      ?>
      <a href="?statut=<?= urlencode($val) ?>&q=<?= urlencode($filtreSearch) ?>"
         style="display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;border:2px solid <?= $active ? $col : 'var(--gray-200)' ?>;background:<?= $active ? $col : 'transparent' ?>;color:<?= $active ? '#fff' : 'var(--gray-500)' ?>;">
        <?= $label ?>
        <?php if ($counts[$val]): ?>
          <span style="background:<?= $active ? 'rgba(255,255,255,.25)' : $col ?>;color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.7rem;"><?= $counts[$val] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Recherche -->
    <form method="GET" style="display:flex;gap:.6rem;margin-bottom:1.5rem;flex-wrap:wrap;">
      <input type="hidden" name="statut" value="<?= e($filtreStatut) ?>">
      <input type="search" name="q" value="<?= e($filtreSearch) ?>" placeholder="Rechercher (nom, email, message, bien)…"
             style="flex:1;min-width:220px;padding:.5rem .9rem;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.87rem;">
      <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
      <?php if ($filtreSearch): ?>
        <a href="?statut=<?= e($filtreStatut) ?>" class="btn btn-outline btn-sm">✕ Effacer</a>
      <?php endif; ?>
    </form>

    <!-- Grid liste + détail -->
    <div style="display:grid;grid-template-columns:<?= $selected ? '340px 1fr' : '1fr' ?>;gap:1.5rem;align-items:start;">

      <!-- Liste -->
      <div class="table-wrapper" style="overflow:hidden;padding:0;">
        <?php if (empty($demandes)): ?>
          <div style="padding:3rem;text-align:center;">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom:.75rem;display:block;margin-inline:auto;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <p style="color:var(--gray-400);">Aucun message trouvé</p>
          </div>
        <?php else: ?>
          <div style="overflow-y:auto;max-height:72vh;">
            <?php foreach ($demandes as $d):
              $isSelected  = $selected && (int)$selected['id'] === (int)$d['id'];
              $statutColor = match($d['statut']) { 'traitee'=>'var(--success)','archivee'=>'var(--gray-300)', default=>'var(--danger)' };
              // Nb réponses non lues
              $unread = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM messages_replies WHERE demande_id=:did AND auteur_id!=:uid AND lu=0', [':did'=>$d['id'],':uid'=>$agentId])['c'] ?? 0);
            ?>
            <a href="?statut=<?= e($filtreStatut) ?>&q=<?= urlencode($filtreSearch) ?>&id=<?= $d['id'] ?>"
               style="display:block;text-decoration:none;padding:.9rem 1.1rem;border-bottom:1px solid var(--gray-100);background:<?= $isSelected ? 'rgba(10,22,40,.05)' : 'transparent' ?>;">
              <div style="display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.25rem;">
                <span style="font-weight:600;font-size:.88rem;color:var(--navy);"><?= e($d['nom']) ?>
                  <?php if ($unread): ?><span style="background:var(--danger);color:#fff;border-radius:10px;padding:.05rem .35rem;font-size:.65rem;margin-left:.3rem;"><?= $unread ?></span><?php endif; ?>
                </span>
                <span style="font-size:.7rem;color:var(--gray-400);white-space:nowrap;"><?= formatDate($d['created_at']) ?></span>
              </div>
              <div style="font-size:.75rem;color:var(--gray-400);margin-bottom:.3rem;"><?= e($d['email']) ?></div>
              <div style="font-size:.78rem;color:var(--gray-500);font-style:italic;margin-bottom:.35rem;"><?= e(mb_substr($d['bien_titre'],0,38)) ?>…</div>
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:.76rem;color:var(--gray-400);"><?= e(mb_substr($d['message'],0,50)) ?>…</span>
                <span style="width:7px;height:7px;border-radius:50%;background:<?= $statutColor ?>;flex-shrink:0;display:inline-block;margin-left:.5rem;"></span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Panneau détail + fil de conversation -->
      <?php if ($selected): ?>
      <div style="display:flex;flex-direction:column;gap:1rem;">

        <!-- Infos contact + bien -->
        <div class="card" style="padding:1.1rem 1.25rem;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;margin-bottom:.9rem;">
            <div>
              <strong style="font-size:1rem;"><?= e($selected['nom']) ?></strong>
              <div style="margin-top:.2rem;">
                <a href="mailto:<?= e($selected['email']) ?>" style="color:var(--gold);font-size:.82rem;"><?= e($selected['email']) ?></a>
                <?php if ($selected['telephone']): ?>
                  · <a href="tel:<?= preg_replace('/\s/','',$selected['telephone']) ?>" style="color:var(--gray-400);font-size:.82rem;"><?= e(formatTel($selected['telephone'])) ?></a>
                <?php endif; ?>
              </div>
            </div>
            <!-- Changer statut -->
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
              <?php foreach(['nouvelle','traitee','archivee'] as $s):
                if ($s === $selected['statut']) continue;
                $sLabel = ['nouvelle'=>'● Nouvelle','traitee'=>'✓ Traitée','archivee'=>'Archiver'][$s];
                $sStyle = ['nouvelle'=>'background:var(--danger);color:#fff;border:none;','traitee'=>'background:var(--success);color:#fff;border:none;','archivee'=>''][$s];
              ?>
              <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action"       value="<?= $s ?>">
                <input type="hidden" name="demande_id"   value="<?= $selected['id'] ?>">
                <input type="hidden" name="back_statut"  value="<?= e($filtreStatut) ?>">
                <input type="hidden" name="back_q"       value="<?= e($filtreSearch) ?>">
                <button type="submit" class="btn btn-sm btn-outline" style="<?= $sStyle ?>"><?= $sLabel ?></button>
              </form>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- Bien -->
          <div style="background:var(--gray-50);border-radius:6px;padding:.65rem .85rem;border-left:3px solid var(--gold);font-size:.82rem;">
            <span style="color:var(--gray-400);font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Bien concerné</span><br>
            <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $selected['bien_id_ref'] ?>" target="_blank"
               style="font-weight:600;color:var(--navy);text-decoration:none;"><?= e($selected['bien_titre']) ?> ↗</a>
            <span style="color:var(--gray-400);"> — <?= e($selected['ville']) ?> · <?= formatPrix($selected['prix']) ?></span>
          </div>
        </div>

        <!-- Fil de conversation -->
        <div class="card" style="padding:0;overflow:hidden;">
          <div style="padding:.75rem 1.1rem;border-bottom:1px solid var(--gray-100);font-size:.78rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;">
            Conversation
          </div>

          <div style="overflow-y:auto;max-height:38vh;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.85rem;" id="conv-scroll">

            <!-- Message initial du client -->
            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:.25rem;">
              <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--gray-200);color:var(--gray-600);border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">
                  <?= strtoupper(substr($selected['nom'],0,1)) ?>
                </span>
                <span style="font-size:.75rem;font-weight:600;color:var(--gray-600);"><?= e($selected['nom']) ?></span>
                <span style="font-size:.68rem;color:var(--gray-400);"><?= (new DateTime($selected['created_at']))->format('d/m/Y à H:i') ?></span>
              </div>
              <div style="margin-left:36px;background:var(--gray-50);border-radius:0 10px 10px 10px;padding:.65rem .85rem;font-size:.87rem;line-height:1.6;color:var(--gray-700);max-width:90%;white-space:pre-wrap;"><?= e($selected['message']) ?></div>
            </div>

            <!-- Réponses -->
            <?php foreach ($replies as $r):
              $isMe = (int)$r['auteur_id'] === $agentId;
              $initiales = strtoupper(substr($r['auteur_nom'],0,1));
            ?>
            <div style="display:flex;flex-direction:column;align-items:<?= $isMe ? 'flex-end' : 'flex-start' ?>;gap:.25rem;">
              <div style="display:flex;align-items:center;gap:.5rem;<?= $isMe ? 'flex-direction:row-reverse;' : '' ?>">
                <span style="background:<?= $isMe ? 'var(--navy)' : 'var(--gray-200)' ?>;color:<?= $isMe ? 'var(--gold)' : 'var(--gray-600)' ?>;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;">
                  <?= $initiales ?>
                </span>
                <span style="font-size:.75rem;font-weight:600;color:var(--gray-600);"><?= e($r['auteur_nom']) ?></span>
                <span style="font-size:.68rem;color:var(--gray-400);"><?= (new DateTime($r['created_at']))->format('d/m/Y à H:i') ?></span>
              </div>
              <div style="<?= $isMe ? 'margin-right:36px;' : 'margin-left:36px;' ?>;background:<?= $isMe ? 'var(--navy)' : 'var(--gray-50)' ?>;color:<?= $isMe ? '#fff' : 'var(--gray-700)' ?>;border-radius:<?= $isMe ? '10px 0 10px 10px' : '0 10px 10px 10px' ?>;padding:.65rem .85rem;font-size:.87rem;line-height:1.6;max-width:90%;white-space:pre-wrap;"><?= e($r['message']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Zone de réponse -->
          <div style="border-top:1px solid var(--gray-100);padding:.85rem 1.1rem;">
            <form method="POST">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action"      value="reply">
              <input type="hidden" name="demande_id"  value="<?= $selected['id'] ?>">
              <input type="hidden" name="back_statut" value="<?= e($filtreStatut) ?>">
              <input type="hidden" name="back_q"      value="<?= e($filtreSearch) ?>">
              <div style="display:flex;gap:.6rem;align-items:flex-end;">
                <textarea name="reply_text" rows="2" placeholder="Écrire une réponse au client…"
                          style="flex:1;padding:.55rem .8rem;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.87rem;resize:vertical;font-family:inherit;"
                          required></textarea>
                <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;">Envoyer ↑</button>
              </div>
            </form>
          </div>
        </div>

      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// Auto-scroll vers le bas du fil de conv
const conv = document.getElementById('conv-scroll');
if (conv) conv.scrollTop = conv.scrollHeight;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
