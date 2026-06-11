<?php
// ============================================================
// mes-messages.php — Messagerie client (voir ses demandes + réponses)
// ============================================================
$pageTitle = 'Mes messages';
require_once __DIR__ . '/includes/header.php';
Auth::requireLogin();

$userId  = (int)Auth::get('id');
$isAgent = Auth::isAgent();

// Rediriger les agents vers leur propre interface
if ($isAgent) {
    header('Location: ' . APP_URL . '/dashboard/messages.php'); exit;
}

// ---- Action : envoyer une réponse ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $demandeId = (int)($_POST['demande_id'] ?? 0);
    $texte     = trim(clean($_POST['reply_text'] ?? ''));
    if ($demandeId && $texte !== '') {
        // Vérifier que la demande appartient bien à ce client
        $check = Database::fetchOne('SELECT id FROM demandes WHERE id=:id AND client_id=:uid', [':id'=>$demandeId,':uid'=>$userId]);
        if ($check) {
            Database::query(
                'INSERT INTO messages_replies (demande_id, auteur_id, message) VALUES (:did, :uid, :msg)',
                [':did'=>$demandeId, ':uid'=>$userId, ':msg'=>$texte]
            );
        }
    }
    header('Location: mes-messages.php?id=' . $demandeId); exit;
}

// ---- Demandes du client ----
$demandes = Database::fetchAll(
    'SELECT d.*, b.titre AS bien_titre, b.id AS bien_id_ref, b.ville, b.prix,
            CONCAT(u.prenom," ",u.nom) AS agent_nom
     FROM demandes d
     JOIN biens b ON b.id = d.bien_id
     LEFT JOIN users u ON u.id = b.agent_id
     WHERE d.client_id = :uid
     ORDER BY d.created_at DESC',
    [':uid' => $userId]
);

// ---- Détail sélectionné ----
$selectedId = (int)($_GET['id'] ?? 0);
$selected   = null;
$replies    = [];
if ($selectedId) {
    $sel = Database::fetchOne(
        'SELECT d.*, b.titre AS bien_titre, b.id AS bien_id_ref, b.ville, b.prix,
                CONCAT(u.prenom," ",u.nom) AS agent_nom, u.telephone AS agent_tel
         FROM demandes d
         JOIN biens b ON b.id = d.bien_id
         LEFT JOIN users u ON u.id = b.agent_id
         WHERE d.id=:id AND d.client_id=:uid',
        [':id'=>$selectedId, ':uid'=>$userId]
    );
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
        // Marquer les réponses de l'agent comme lues
        Database::query('UPDATE messages_replies SET lu=1 WHERE demande_id=:did AND auteur_id!=:uid AND lu=0', [':did'=>$selectedId,':uid'=>$userId]);
    }
}

// Compter les non-lus total
$totalUnread = (int)(Database::fetchOne(
    'SELECT COUNT(*) AS c FROM messages_replies r JOIN demandes d ON d.id=r.demande_id WHERE d.client_id=:uid AND r.auteur_id!=:uid2 AND r.lu=0',
    [':uid'=>$userId,':uid2'=>$userId]
)['c'] ?? 0);
?>

<div class="container" style="max-width:1100px;margin:2.5rem auto;padding:0 1rem;">

  <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
    <div>
      <h1 style="font-size:1.9rem;">Mes messages</h1>
      <p style="color:var(--gray-400);">Vos demandes de contact et les réponses des agents</p>
    </div>
    <a href="<?= APP_URL ?>/biens.php" class="btn btn-outline btn-sm">← Retour aux annonces</a>
  </div>

  <?php if (empty($demandes)): ?>
    <div class="card" style="text-align:center;padding:4rem 2rem;">
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.3" style="margin-bottom:1rem;display:block;margin-inline:auto;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      <p style="color:var(--gray-400);margin-bottom:1.25rem;">Vous n'avez pas encore envoyé de demande de contact.</p>
      <a href="<?= APP_URL ?>/biens.php" class="btn btn-primary">Voir les annonces</a>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:<?= $selected ? '300px 1fr' : '1fr' ?>;gap:1.5rem;align-items:start;">

    <!-- Liste des conversations -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:.75rem 1rem;border-bottom:1px solid var(--gray-100);font-size:.78rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;">
        Conversations <?php if ($totalUnread): ?><span style="background:var(--danger);color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.7rem;margin-left:.4rem;"><?= $totalUnread ?></span><?php endif; ?>
      </div>
      <?php foreach ($demandes as $d):
        $isSelected = $selected && (int)$selected['id'] === (int)$d['id'];
        $statutColor = match($d['statut']) { 'traitee'=>'var(--success)','archivee'=>'var(--gray-300)',default=>'var(--danger)' };
        $unread = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM messages_replies WHERE demande_id=:did AND auteur_id!=:uid AND lu=0',[':did'=>$d['id'],':uid'=>$userId])['c']??0);
      ?>
      <a href="mes-messages.php?id=<?= $d['id'] ?>"
         style="display:block;padding:.85rem 1rem;border-bottom:1px solid var(--gray-100);text-decoration:none;background:<?= $isSelected?'rgba(10,22,40,.05)':'transparent' ?>;">
        <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
          <span style="font-weight:600;font-size:.87rem;color:var(--navy);"><?= e(mb_substr($d['bien_titre'],0,30)) ?>…
            <?php if ($unread): ?><span style="background:var(--danger);color:#fff;border-radius:10px;padding:.02rem .32rem;font-size:.65rem;margin-left:.25rem;"><?= $unread ?></span><?php endif; ?>
          </span>
          <span style="font-size:.68rem;color:var(--gray-400);"><?= formatDate($d['created_at']) ?></span>
        </div>
        <div style="font-size:.76rem;color:var(--gray-400);margin-bottom:.25rem;"><?= e($d['ville']) ?></div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:.74rem;color:var(--gray-400);"><?= e(mb_substr($d['message'],0,45)) ?>…</span>
          <span style="width:7px;height:7px;border-radius:50%;background:<?= $statutColor ?>;display:inline-block;flex-shrink:0;margin-left:.4rem;"></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Conversation sélectionnée -->
    <?php if ($selected): ?>
    <div style="display:flex;flex-direction:column;gap:1rem;">

      <!-- Infos bien + agent -->
      <div class="card" style="padding:1rem 1.25rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;">
          <div>
            <a href="<?= APP_URL ?>/bien-detail.php?id=<?= $selected['bien_id_ref'] ?>"
               style="font-weight:700;font-size:1rem;color:var(--navy);text-decoration:none;"><?= e($selected['bien_titre']) ?> ↗</a>
            <div style="font-size:.82rem;color:var(--gray-400);margin-top:.2rem;"><?= e($selected['ville']) ?> · <?= formatPrix($selected['prix']) ?></div>
          </div>
          <?php if ($selected['agent_nom']): ?>
          <div style="text-align:right;font-size:.8rem;color:var(--gray-500);">
            Agent : <strong><?= e($selected['agent_nom']) ?></strong>
            <?php if ($selected['agent_tel']): ?>
              <br><a href="tel:<?= preg_replace('/\s/','',$selected['agent_tel']) ?>" style="color:var(--gold);"><?= e(formatTel($selected['agent_tel'])) ?></a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Fil de conversation -->
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:.7rem 1.1rem;border-bottom:1px solid var(--gray-100);font-size:.78rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;">Échange</div>

        <div style="overflow-y:auto;max-height:40vh;padding:1rem;display:flex;flex-direction:column;gap:.85rem;" id="conv-scroll">

          <!-- Message initial -->
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;">
            <div style="display:flex;align-items:center;gap:.4rem;flex-direction:row-reverse;">
              <span style="background:var(--navy);color:var(--gold);border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;">
                <?= strtoupper(substr(Auth::get('prenom'),0,1)) ?>
              </span>
              <span style="font-size:.73rem;font-weight:600;color:var(--gray-600);">Vous</span>
              <span style="font-size:.67rem;color:var(--gray-400);"><?= (new DateTime($selected['created_at']))->format('d/m/Y à H:i') ?></span>
            </div>
            <div style="margin-right:36px;background:var(--navy);color:#fff;border-radius:10px 0 10px 10px;padding:.6rem .8rem;font-size:.87rem;line-height:1.6;max-width:90%;white-space:pre-wrap;"><?= e($selected['message']) ?></div>
          </div>

          <!-- Réponses -->
          <?php foreach ($replies as $r):
            $isMe = (int)$r['auteur_id'] === $userId;
          ?>
          <div style="display:flex;flex-direction:column;align-items:<?= $isMe?'flex-end':'flex-start' ?>;gap:.25rem;">
            <div style="display:flex;align-items:center;gap:.4rem;<?= $isMe?'flex-direction:row-reverse;':'' ?>">
              <span style="background:<?= $isMe?'var(--navy)':'var(--gray-200)' ?>;color:<?= $isMe?'var(--gold)':'var(--gray-600)' ?>;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;">
                <?= strtoupper(substr($r['auteur_nom'],0,1)) ?>
              </span>
              <span style="font-size:.73rem;font-weight:600;color:var(--gray-600);"><?= $isMe?'Vous':e($r['auteur_nom']) ?></span>
              <span style="font-size:.67rem;color:var(--gray-400);"><?= (new DateTime($r['created_at']))->format('d/m/Y à H:i') ?></span>
            </div>
            <div style="<?= $isMe?'margin-right:36px;':'margin-left:36px;' ?>;background:<?= $isMe?'var(--navy)':'var(--gray-50)' ?>;color:<?= $isMe?'#fff':'var(--gray-700)' ?>;border-radius:<?= $isMe?'10px 0 10px 10px':'0 10px 10px 10px' ?>;padding:.6rem .8rem;font-size:.87rem;line-height:1.6;max-width:90%;white-space:pre-wrap;"><?= e($r['message']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Zone réponse -->
        <div style="border-top:1px solid var(--gray-100);padding:.8rem 1rem;">
          <form method="POST" style="display:flex;gap:.6rem;align-items:flex-end;">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="demande_id" value="<?= $selected['id'] ?>">
            <textarea name="reply_text" rows="2" placeholder="Répondre à l'agent…" required
                      style="flex:1;padding:.5rem .75rem;border:1.5px solid var(--gray-200);border-radius:8px;font-size:.87rem;resize:vertical;font-family:inherit;"></textarea>
            <button type="submit" class="btn btn-primary btn-sm">Envoyer ↑</button>
          </form>
        </div>
      </div>

    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>
</div>

<script>
const conv = document.getElementById('conv-scroll');
if (conv) conv.scrollTop = conv.scrollHeight;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
