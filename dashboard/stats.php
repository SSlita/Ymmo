<?php
// ============================================================
// dashboard/stats.php — Statistiques & Analyses
// ============================================================
$pageTitle = 'Statistiques';
require_once __DIR__ . '/../includes/header.php';
Auth::requireAgent();

$agentId = (int)Auth::get('id');
$isAdmin = Auth::isAdmin();
$aid     = [':aid' => $agentId];

// ============================================================
// KPIs principaux
// ============================================================
$caTotal = $isAdmin
    ? Database::fetchOne('SELECT COALESCE(SUM(prix_final),0) AS c FROM transactions')['c']
    : Database::fetchOne('SELECT COALESCE(SUM(prix_final),0) AS c FROM transactions WHERE agent_id=:aid', $aid)['c'];

$commissions = $isAdmin
    ? Database::fetchOne('SELECT COALESCE(SUM(commission),0) AS c FROM transactions')['c']
    : Database::fetchOne('SELECT COALESCE(SUM(commission),0) AS c FROM transactions WHERE agent_id=:aid', $aid)['c'];

$nbTransactions = $isAdmin
    ? Database::fetchOne('SELECT COUNT(*) AS c FROM transactions')['c']
    : Database::fetchOne('SELECT COUNT(*) AS c FROM transactions WHERE agent_id=:aid', $aid)['c'];

// Biens actifs
$nbBiensActifs = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM biens WHERE statut='disponible'")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM biens WHERE agent_id=:aid AND statut='disponible'", $aid)['c'];

// Offres en attente
$nbOffresAttente = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM offres WHERE statut='en_attente'")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid AND o.statut='en_attente'", $aid)['c'];

// Taux de conversion offres → transactions
$totalOffres = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM offres WHERE statut!='annulee'")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid AND o.statut!='annulee'", $aid)['c'];
$offresAcceptees = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM offres WHERE statut='acceptee'")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid AND o.statut='acceptee'", $aid)['c'];
$tauxConversion = $totalOffres > 0 ? round($offresAcceptees / $totalOffres * 100, 1) : 0;

// Nouvelles demandes non traitées
$nbDemandes = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM demandes WHERE statut='nouvelle'")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:aid AND d.statut='nouvelle'", $aid)['c'];

// Vues totales sur les biens
$nbVues = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM vues_biens WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM vues_biens v JOIN biens b ON b.id=v.bien_id WHERE b.agent_id=:aid AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", $aid)['c'];

// ============================================================
// Transactions par mois (12 derniers mois)
// ============================================================
$moisSql = $isAdmin
    ? "SELECT DATE_FORMAT(date_transaction,'%b %Y') AS mois, DATE_FORMAT(date_transaction,'%Y-%m') AS mois_key,
              COUNT(*) AS nb, COALESCE(SUM(prix_final),0) AS ca
       FROM transactions WHERE date_transaction >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       GROUP BY mois_key, mois ORDER BY mois_key ASC"
    : "SELECT DATE_FORMAT(date_transaction,'%b %Y') AS mois, DATE_FORMAT(date_transaction,'%Y-%m') AS mois_key,
              COUNT(*) AS nb, COALESCE(SUM(prix_final),0) AS ca
       FROM transactions WHERE agent_id=:aid AND date_transaction >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       GROUP BY mois_key, mois ORDER BY mois_key ASC";
$parMois = $isAdmin ? Database::fetchAll($moisSql) : Database::fetchAll($moisSql, $aid);

// Vues par mois (30 derniers jours, par semaine)
$vuesSemaineSql = $isAdmin
    ? "SELECT YEARWEEK(created_at,1) AS semaine_key,
              CONCAT('S', WEEK(created_at,1)) AS semaine_label,
              COUNT(*) AS nb
       FROM vues_biens WHERE created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
       GROUP BY semaine_key, semaine_label ORDER BY semaine_key ASC"
    : "SELECT YEARWEEK(v.created_at,1) AS semaine_key,
              CONCAT('S', WEEK(v.created_at,1)) AS semaine_label,
              COUNT(*) AS nb
       FROM vues_biens v JOIN biens b ON b.id=v.bien_id
       WHERE b.agent_id=:aid AND v.created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
       GROUP BY semaine_key, semaine_label ORDER BY semaine_key ASC";
$vuesSemaine = $isAdmin ? Database::fetchAll($vuesSemaineSql) : Database::fetchAll($vuesSemaineSql, $aid);

// Offres par statut (pour le donut)
$offresByStatut = $isAdmin
    ? Database::fetchAll("SELECT statut, COUNT(*) AS nb FROM offres GROUP BY statut")
    : Database::fetchAll("SELECT o.statut, COUNT(*) AS nb FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid GROUP BY o.statut", $aid);
$offresCounts = ['en_attente'=>0,'acceptee'=>0,'refusee'=>0,'annulee'=>0];
foreach ($offresByStatut as $r) $offresCounts[$r['statut']] = (int)$r['nb'];

// Funnel demandes → offres → transactions
$nbDemandesTotal = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM demandes")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM demandes d JOIN biens b ON b.id=d.bien_id WHERE b.agent_id=:aid", $aid)['c'];
$nbOffresTotal   = $isAdmin
    ? Database::fetchOne("SELECT COUNT(*) AS c FROM offres")['c']
    : Database::fetchOne("SELECT COUNT(*) AS c FROM offres o JOIN biens b ON b.id=o.bien_id WHERE b.agent_id=:aid", $aid)['c'];

// ============================================================
// Répartition / Top
// ============================================================
$repartType = $isAdmin
    ? Database::fetchAll("SELECT type, COUNT(*) AS nb FROM biens WHERE statut!='archive' GROUP BY type ORDER BY nb DESC")
    : Database::fetchAll("SELECT type, COUNT(*) AS nb FROM biens WHERE agent_id=:aid AND statut!='archive' GROUP BY type ORDER BY nb DESC", $aid);
$totalBiensByType = array_sum(array_column($repartType, 'nb')) ?: 1;

$topVilles = Database::fetchAll("SELECT ville, COUNT(*) AS nb_biens, AVG(prix) AS prix_moyen FROM biens WHERE statut!='archive' GROUP BY ville ORDER BY nb_biens DESC LIMIT 6");

$prixMoyenType = Database::fetchAll("SELECT type, AVG(prix) AS prix_moyen, MIN(prix) AS prix_min, MAX(prix) AS prix_max FROM biens WHERE statut!='archive' GROUP BY type");

// Top biens par vues (30 derniers jours)
$topBiensVues = $isAdmin
    ? Database::fetchAll("SELECT b.titre, b.ville, b.type, b.prix, COUNT(v.id) AS nb_vues
       FROM biens b LEFT JOIN vues_biens v ON v.bien_id=b.id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       WHERE b.statut='disponible' GROUP BY b.id ORDER BY nb_vues DESC LIMIT 5")
    : Database::fetchAll("SELECT b.titre, b.ville, b.type, b.prix, COUNT(v.id) AS nb_vues
       FROM biens b LEFT JOIN vues_biens v ON v.bien_id=b.id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       WHERE b.statut='disponible' AND b.agent_id=:aid GROUP BY b.id ORDER BY nb_vues DESC LIMIT 5", $aid);

// Biens en tendance (demandes)
$biensTendance = $isAdmin
    ? Database::fetchAll("SELECT b.titre, b.ville, b.type, b.prix, COUNT(d.id) AS nb_demandes FROM biens b LEFT JOIN demandes d ON d.bien_id=b.id WHERE b.statut='disponible' GROUP BY b.id ORDER BY nb_demandes DESC LIMIT 5")
    : Database::fetchAll("SELECT b.titre, b.ville, b.type, b.prix, COUNT(d.id) AS nb_demandes FROM biens b LEFT JOIN demandes d ON d.bien_id=b.id WHERE b.statut='disponible' AND b.agent_id=:aid GROUP BY b.id ORDER BY nb_demandes DESC LIMIT 5", $aid);

$typeColors = ['appartement'=>'#0A1628','maison'=>'#C9A84C','bureau'=>'#162240','local'=>'#E5C97A','terrain'=>'#5A5752','autre'=>'#AEAAA4'];

$moisLabels = json_encode(array_column($parMois, 'mois'));
$moisNb     = json_encode(array_column($parMois, 'nb'));
$moisCA     = json_encode(array_map(fn($r)=> round($r['ca']/1000,1), $parMois));
$vueLabels  = json_encode(array_column($vuesSemaine, 'semaine_label'));
$vueNb      = json_encode(array_column($vuesSemaine, 'nb'));
?>

<div class="dashboard-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="dashboard-main">
      <!-- Toggle sidebar mobile -->
  <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="dashboard-sidebar">
    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>
<div class="dashboard-header">
      <h1 style="font-size:1.8rem;">📊 Statistiques & Analyses</h1>
      <p style="color:var(--gray-400);">Données du marché et performances <?= $isAdmin ? 'globales' : 'de votre portefeuille' ?></p>
    </div>

    <!-- ======================================================
         LIGNE 1 : KPIs principaux
    ====================================================== -->
    <div class="kpi-grid" style="margin-bottom:1.5rem;">
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-gold">💰</div>
        <p class="kpi-label">CA Total</p>
        <p class="kpi-value" style="font-size:1.3rem;"><?= formatPrix($caTotal) ?></p>
        <p style="font-size:.75rem;color:var(--gray-400);"><?= $nbTransactions ?> transaction<?= $nbTransactions>1?'s':'' ?></p>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-green">📈</div>
        <p class="kpi-label">Commissions</p>
        <p class="kpi-value" style="font-size:1.3rem;"><?= formatPrix($commissions) ?></p>
        <p style="font-size:.75rem;color:var(--gray-400);"><?= COMMISSION_RATE ?>% du CA</p>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-navy">🏘</div>
        <p class="kpi-label">Biens disponibles</p>
        <p class="kpi-value"><?= $nbBiensActifs ?></p>
        <p style="font-size:.75rem;color:var(--gray-400);">en portefeuille</p>
      </div>
      <div class="kpi-card" style="<?= $nbOffresAttente>0 ? 'border-top:3px solid var(--gold);' : '' ?>">
        <div class="kpi-icon kpi-icon-red">📋</div>
        <p class="kpi-label">Offres en attente</p>
        <p class="kpi-value" style="<?= $nbOffresAttente>0 ? 'color:var(--gold)' : '' ?>"><?= $nbOffresAttente ?></p>
        <a href="offres.php" style="font-size:.75rem;color:var(--gold);text-decoration:none;">Gérer →</a>
      </div>
    </div>

    <!-- ======================================================
         LIGNE 2 : Funnel conversion + Vues
    ====================================================== -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

      <!-- Funnel -->
      <div class="chart-card">
        <h3 style="margin-bottom:1.25rem;">🎯 Funnel de conversion</h3>
        <?php
          $steps = [
            ['label'=>'Consultations (30j)', 'val'=>(int)$nbVues,         'color'=>'var(--navy)'],
            ['label'=>'Demandes de contact', 'val'=>(int)$nbDemandesTotal, 'color'=>'#C9A84C'],
            ['label'=>'Offres soumises',      'val'=>(int)$nbOffresTotal,  'color'=>'#162240'],
            ['label'=>'Transactions',         'val'=>(int)$nbTransactions, 'color'=>'var(--success)'],
          ];
          $maxVal = max(array_column($steps, 'val')) ?: 1;
        ?>
        <?php foreach ($steps as $i => $step): ?>
        <div style="margin-bottom:.8rem;">
          <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.25rem;">
            <span style="color:var(--gray-600);"><?= $step['label'] ?></span>
            <strong><?= $step['val'] ?><?php if($i>0&&$steps[$i-1]['val']>0): ?> <span style="font-weight:400;color:var(--gray-400);">(<?= round($step['val']/$steps[$i-1]['val']*100,1) ?>%)</span><?php endif; ?></strong>
          </div>
          <div style="background:var(--gray-100);border-radius:4px;height:10px;overflow:hidden;">
            <div style="width:<?= $maxVal>0?round($step['val']/$maxVal*100):0 ?>%;background:<?= $step['color'] ?>;height:100%;border-radius:4px;transition:width .4s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;padding:.75rem;background:rgba(201,168,76,.08);border-radius:8px;border-left:3px solid var(--gold);font-size:.82rem;">
          Taux de conversion offres : <strong style="color:var(--gold);"><?= $tauxConversion ?>%</strong>
          (<?= $offresAcceptees ?> / <?= max($totalOffres,1) ?> offres acceptées)
        </div>
      </div>

      <!-- Vues par semaine -->
      <div class="chart-card">
        <h3 style="margin-bottom:1rem;">👁 Vues des annonces (8 dernières semaines)</h3>
        <?php if (empty($vuesSemaine)): ?>
          <p style="color:var(--gray-400);text-align:center;padding:2rem;">Aucune donnée disponible — installez la migration SQL.</p>
        <?php else: ?>
          <div class="bar-chart js-bar-chart" style="height:140px;"
               data-values='<?= $vueNb ?>'
               data-labels='<?= $vueLabels ?>'
               data-colors='<?= json_encode(array_fill(0, count($vuesSemaine), "#0A1628")) ?>'>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:.75rem;">
            <span style="font-size:.78rem;color:var(--gray-400);">Total 30j : <strong><?= $nbVues ?> vues</strong></span>
            <?php $avgVues = count($vuesSemaine)>0 ? round(array_sum(array_column($vuesSemaine,'nb'))/count($vuesSemaine)) : 0; ?>
            <span style="font-size:.78rem;color:var(--gray-400);">Moy./semaine : <strong><?= $avgVues ?></strong></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ======================================================
         LIGNE 3 : Offres + Transactions par mois
    ====================================================== -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:1.5rem;">

      <!-- Répartition offres -->
      <div class="chart-card">
        <h3 style="margin-bottom:1rem;">📊 Offres reçues</h3>
        <?php
          $offreTotal = array_sum($offresCounts) ?: 1;
          $oc = [
            'en_attente' => ['label'=>'En attente', 'color'=>'#C9A84C'],
            'acceptee'   => ['label'=>'Acceptées',  'color'=>'var(--success)'],
            'refusee'    => ['label'=>'Refusées',    'color'=>'var(--danger)'],
            'annulee'    => ['label'=>'Annulées',    'color'=>'var(--gray-300)'],
          ];
        ?>
        <?php foreach ($oc as $key => $info): $nb = $offresCounts[$key]; ?>
        <div style="margin-bottom:.7rem;">
          <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.2rem;">
            <span style="color:var(--gray-600);"><?= $info['label'] ?></span>
            <strong><?= $nb ?> <span style="font-weight:400;color:var(--gray-400);">(<?= round($nb/$offreTotal*100) ?>%)</span></strong>
          </div>
          <div style="background:var(--gray-100);border-radius:4px;height:8px;">
            <div style="width:<?= round($nb/$offreTotal*100) ?>%;background:<?= $info['color'] ?>;height:100%;border-radius:4px;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;text-align:center;font-size:.85rem;color:var(--gray-400);">
          <?= array_sum($offresCounts) ?> offre<?= array_sum($offresCounts)>1?'s':'' ?> au total
        </div>
      </div>

      <!-- Transactions par mois -->
      <div class="chart-card">
        <h3 style="margin-bottom:1rem;">Évolution des transactions (12 derniers mois)</h3>
        <?php if (empty($parMois)): ?>
          <p style="color:var(--gray-400);text-align:center;padding:2rem;">Aucune transaction enregistrée.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:flex-end;">
            <div>
              <p style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gray-400);margin-bottom:.6rem;">Nb transactions</p>
              <div class="bar-chart js-bar-chart" style="height:130px;"
                   data-values='<?= $moisNb ?>'
                   data-labels='<?= $moisLabels ?>'
                   data-colors='<?= json_encode(array_fill(0,count($parMois),"#0A1628")) ?>'>
              </div>
            </div>
            <div>
              <p style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gray-400);margin-bottom:.6rem;">CA (k€)</p>
              <div class="bar-chart js-bar-chart" style="height:130px;"
                   data-values='<?= $moisCA ?>'
                   data-labels='<?= $moisLabels ?>'
                   data-colors='<?= json_encode(array_fill(0,count($parMois),"#C9A84C")) ?>'>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ======================================================
         LIGNE 4 : Top biens + Répartition type + Prix
    ====================================================== -->
    <div class="chart-grid">

      <!-- Top biens par vues -->
      <div class="chart-card">
        <h3>👁 Top biens consultés (30j)</h3>
        <p style="font-size:.78rem;color:var(--gray-400);margin-bottom:.9rem;">Classés par nombre de vues uniques</p>
        <?php if (empty($topBiensVues)): ?>
          <p style="color:var(--gray-400);">Aucune donnée — installez la migration SQL.</p>
        <?php else: ?>
          <?php $maxV = max(array_column($topBiensVues,'nb_vues')) ?: 1; ?>
          <?php foreach ($topBiensVues as $i => $b): ?>
          <div style="margin-bottom:.85rem;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;margin-bottom:.2rem;">
              <span style="color:var(--gray-700);font-weight:500;"><?= e(mb_substr($b['titre'],0,28)) ?>…</span>
              <span style="font-weight:700;"><?= $b['nb_vues'] ?> <span style="font-weight:400;color:var(--gray-400);">vues</span></span>
            </div>
            <div style="background:var(--gray-100);border-radius:4px;height:6px;">
              <div style="width:<?= round($b['nb_vues']/$maxV*100) ?>%;background:<?= $i===0?'var(--gold)':'var(--navy)' ?>;height:100%;border-radius:4px;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Répartition par type -->
      <div class="chart-card">
        <h3>Répartition par type de bien</h3>
        <?php if (empty($repartType)): ?>
          <p style="color:var(--gray-400);">Aucune donnée.</p>
        <?php else: ?>
          <div class="bar-chart js-bar-chart" style="height:140px;margin-bottom:1rem;"
               data-values='<?= json_encode(array_column($repartType,'nb')) ?>'
               data-labels='<?= json_encode(array_map(fn($r)=>labelType($r["type"]), $repartType)) ?>'
               data-colors='<?= json_encode(array_map(fn($r)=>$typeColors[$r["type"]]??'#0A1628', $repartType)) ?>'>
          </div>
          <?php foreach ($repartType as $r):
            $pct = round($r['nb'] / $totalBiensByType * 100);
            $color = $typeColors[$r['type']] ?? '#0A1628';
          ?>
          <div class="stat-row">
            <span class="stat-dot" style="background:<?= $color ?>;"></span>
            <span class="stat-name"><?= labelType($r['type']) ?></span>
            <span style="font-size:.8rem;color:var(--gray-400);margin-right:.75rem;"><?= $r['nb'] ?> biens</span>
            <span class="stat-pct"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Top villes -->
      <div class="chart-card">
        <h3>Top villes</h3>
        <?php foreach ($topVilles as $i => $v): ?>
        <div class="stat-row">
          <span style="font-size:.72rem;font-weight:700;width:18px;color:<?= $i<3?'var(--gold)':'var(--gray-400)' ?>">#<?= $i+1 ?></span>
          <span class="stat-name"><?= e($v['ville']) ?></span>
          <span style="font-size:.78rem;color:var(--gray-400);margin-right:.75rem;"><?= $v['nb_biens'] ?> bien<?= $v['nb_biens']>1?'s':'' ?></span>
          <span class="stat-pct" style="font-size:.8rem;"><?= formatPrix($v['prix_moyen']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Biens en tendance -->
      <div class="chart-card">
        <h3>🔥 Biens populaires</h3>
        <p style="font-size:.78rem;color:var(--gray-400);margin-bottom:.9rem;">Classés par demandes de contact reçues</p>
        <?php if (empty($biensTendance)): ?>
          <p style="color:var(--gray-400);">Aucune demande pour le moment.</p>
        <?php else: ?>
          <?php foreach ($biensTendance as $i => $b): ?>
          <div class="stat-row">
            <span style="font-size:.72rem;font-weight:700;width:18px;color:<?= $b['nb_demandes']>0?'var(--gold)':'var(--gray-400)' ?>">
              <?= $b['nb_demandes'] > 0 ? '🔥' : '—' ?>
            </span>
            <div style="flex:1;margin-left:.4rem;">
              <span style="font-weight:500;font-size:.87rem;"><?= e(mb_substr($b['titre'],0,26)) ?>…</span><br>
              <span style="font-size:.72rem;color:var(--gray-400);"><?= e($b['ville']) ?> — <?= formatPrix($b['prix']) ?></span>
            </div>
            <span class="badge badge-gold"><?= $b['nb_demandes'] ?> dem.</span>
          </div>
          <?php endforeach; ?>

          <?php if (!empty($biensTendance) && $biensTendance[0]['nb_demandes'] > 0): ?>
          <div style="margin-top:1rem;padding:.75rem;background:rgba(201,168,76,.08);border-radius:8px;border-left:3px solid var(--gold);font-size:.8rem;">
            📈 <strong><?= e(mb_substr($biensTendance[0]['titre'],0,22)) ?>…</strong> à <?= e($biensTendance[0]['ville']) ?> —
            probabilité de vente estimée à <strong style="color:var(--gold);"><?= min(95, 60 + (int)$biensTendance[0]['nb_demandes'] * 15) ?>%</strong> sous 30j.
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Export -->
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:1rem;">
      <div>
        <h3 style="font-size:1rem;">Exporter les données</h3>
        <p style="font-size:.85rem;color:var(--gray-400);">Téléchargez vos statistiques au format CSV pour Excel ou un outil BI.</p>
      </div>
      <a href="<?= APP_URL ?>/api/export.php?type=transactions" class="btn btn-dark btn-sm">📥 Export CSV transactions</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
