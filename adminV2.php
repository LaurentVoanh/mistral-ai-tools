<?php
/**
 * NEXUS ADMIN — admin.php
 * Cockpit de surveillance : clés, modèles, pages, apps, mémoire.
 */

define('DB_FILE', __DIR__ . '/nexus.sqlite');

if (!file_exists(DB_FILE)) {
    header("Location: index.php");
    exit;
}

$db = new PDO('sqlite:' . DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Actions admin
if (isset($_GET['del_key']) && is_numeric($_GET['del_key'])) {
    $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$_GET['del_key']]);
    header("Location: admin.php"); exit;
}
if (isset($_GET['del_page']) && $_GET['del_page']) {
    $db->prepare("DELETE FROM site_pages WHERE slug = ?")->execute([$_GET['del_page']]);
    header("Location: admin.php"); exit;
}
if (isset($_GET['del_app']) && is_numeric($_GET['del_app'])) {
    // Supprimer aussi le fichier PHP
    $app = $db->prepare("SELECT app_slug FROM ai_apps WHERE id = ?");
    $app->execute([$_GET['del_app']]);
    $row = $app->fetch(PDO::FETCH_ASSOC);
    if ($row && file_exists(__DIR__ . '/' . $row['app_slug'] . '.php')) {
        @unlink(__DIR__ . '/' . $row['app_slug'] . '.php');
    }
    $db->prepare("DELETE FROM ai_apps WHERE id = ?")->execute([$_GET['del_app']]);
    header("Location: admin.php"); exit;
}
if (isset($_GET['clear_memory'])) {
    $db->exec("DELETE FROM ai_memory");
    header("Location: admin.php"); exit;
}

// Data
$keys      = $db->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$models    = $db->query("SELECT * FROM model_usage ORDER BY last_tested DESC")->fetchAll(PDO::FETCH_ASSOC);
$pages     = $db->query("SELECT slug, title, page_type, views, created_at, updated_at FROM site_pages ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$apps      = $db->query("SELECT id, app_name, app_slug, description, status, created_at FROM ai_apps ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$memories  = $db->query("SELECT * FROM ai_memory ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$total_tokens = $db->query("SELECT SUM(tokens_used) FROM ai_memory")->fetchColumn() ?: 0;
$total_views  = $db->query("SELECT SUM(views) FROM site_pages")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NEXUS ADMIN — Cockpit</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  --bg:#060810; --surface:#0c1220; --surface2:#111827;
  --border:#1c2d42; --accent:#00d4ff; --accent2:#7c3aed;
  --green:#10b981; --red:#ef4444; --amber:#f59e0b;
  --text:#e2e8f0; --muted:#64748b;
  --font:'Space Grotesk',sans-serif;
  --mono:'JetBrains Mono',monospace;
}

* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); color:var(--text); font-family:var(--font); padding:2rem; }
body::before {
  content:''; position:fixed; inset:0;
  background-image:linear-gradient(rgba(0,212,255,.02)1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,.02)1px,transparent 1px);
  background-size:40px 40px; pointer-events:none; z-index:0;
}

.container { max-width:1200px; margin:0 auto; position:relative; z-index:1; }

/* Header */
.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:2.5rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border); }
.title-group h1 { font-family:var(--mono); font-size:1.4rem; color:var(--accent); letter-spacing:.1em; }
.title-group p { font-size:.8rem; color:var(--muted); font-family:var(--mono); margin-top:.25rem; }
.back-link { font-family:var(--mono); font-size:.8rem; color:var(--muted); text-decoration:none; padding:.5rem 1rem; border:1px solid var(--border); border-radius:8px; transition:all .2s; }
.back-link:hover { color:var(--accent); border-color:var(--accent); }

/* KPI Grid */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:2rem; }
.kpi { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; }
.kpi-label { font-family:var(--mono); font-size:.68rem; color:var(--muted); letter-spacing:.12em; text-transform:uppercase; margin-bottom:.5rem; }
.kpi-value { font-family:var(--mono); font-size:2rem; font-weight:700; color:var(--accent); line-height:1; }
.kpi-value.green { color:var(--green); }
.kpi-value.amber { color:var(--amber); font-size:1.4rem; }

/* Section */
.section { margin-bottom:2.5rem; }
.section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.section-title { font-family:var(--mono); font-size:.78rem; color:var(--muted); letter-spacing:.15em; text-transform:uppercase; }
.section-action { font-size:.72rem; color:var(--red); font-family:var(--mono); text-decoration:none; padding:.3rem .75rem; border:1px solid rgba(239,68,68,.2); border-radius:6px; transition:all .2s; }
.section-action:hover { background:rgba(239,68,68,.1); }

/* Table */
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
table { width:100%; border-collapse:collapse; font-size:.84rem; }
th { background:var(--surface2); padding:.75rem 1rem; text-align:left; font-family:var(--mono); font-size:.68rem; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; border-bottom:1px solid var(--border); }
td { padding:.8rem 1rem; border-bottom:1px solid rgba(28,45,66,.5); vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.02); }

.mono { font-family:var(--mono); }
.muted { color:var(--muted); font-size:.78rem; }
.key-masked { font-family:var(--mono); font-size:.75rem; color:var(--muted); background:rgba(0,0,0,.3); padding:.2rem .5rem; border-radius:4px; }

/* Badges */
.badge { display:inline-block; padding:.2rem .6rem; border-radius:4px; font-family:var(--mono); font-size:.65rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase; }
.badge-ok     { background:rgba(16,185,129,.1); color:var(--green); }
.badge-err    { background:rgba(239,68,68,.1);  color:var(--red); }
.badge-active { background:rgba(0,212,255,.1);  color:var(--accent); }
.badge-article { background:rgba(0,212,255,.1);  color:var(--accent); }
.badge-tool    { background:rgba(16,185,129,.1); color:var(--green); }
.badge-home    { background:rgba(245,158,11,.1);  color:var(--amber); }
.badge-app     { background:rgba(124,58,237,.1);  color:#a78bfa; }

/* Delete link */
.del { color:rgba(239,68,68,.4); font-size:.75rem; font-family:var(--mono); text-decoration:none; transition:color .2s; }
.del:hover { color:var(--red); }

/* Progress bar */
.progress-wrap { width:100px; background:var(--surface2); height:4px; border-radius:2px; overflow:hidden; }
.progress-bar { height:100%; background:var(--amber); border-radius:2px; }

/* Memory event colors */
.type-THINK      { color:#a78bfa; }
.type-BUILD_PAGE { color:var(--accent); }
.type-BUILD_APP  { color:var(--green); }

/* Empty */
.empty-row td { text-align:center; color:var(--muted); font-family:var(--mono); font-size:.8rem; padding:2rem; }

::-webkit-scrollbar { width:6px; }
::-webkit-scrollbar-track { background:var(--bg); }
::-webkit-scrollbar-thumb { background:#1c2d42; border-radius:3px; }
</style>
</head>
<body>
<div class="container">

  <!-- TOP BAR -->
  <div class="top-bar">
    <div class="title-group">
      <h1>NEXUS / ADMIN</h1>
      <p>Cockpit de surveillance — <?= date('d/m/Y H:i') ?></p>
    </div>
    <a href="index.php" class="back-link">← RETOUR CORE</a>
  </div>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi"><div class="kpi-label">Pages créées</div><div class="kpi-value"><?= count($pages) ?></div></div>
    <div class="kpi"><div class="kpi-label">Applications</div><div class="kpi-value green"><?= count($apps) ?></div></div>
    <div class="kpi"><div class="kpi-label">Total tokens</div><div class="kpi-value amber"><?= number_format($total_tokens,0,'.',' ') ?></div></div>
    <div class="kpi"><div class="kpi-label">Vues totales</div><div class="kpi-value"><?= number_format($total_views,0,'.',' ') ?></div></div>
    <div class="kpi"><div class="kpi-label">Clés actives</div><div class="kpi-value"><?= count($keys) ?></div></div>
    <div class="kpi"><div class="kpi-label">Modèles OK</div><div class="kpi-value green"><?= count(array_filter($models, fn($m) => $m['last_status'] === 'OK')) ?></div></div>
  </div>

  <!-- CLÉS API -->
  <div class="section">
    <div class="section-head">
      <span class="section-title">Clés API Mistral</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Pseudo</th><th>Clé</th><th>Créée le</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($keys)): ?>
          <tr class="empty-row"><td colspan="4">Aucune clé enregistrée</td></tr>
        <?php else: foreach ($keys as $k): ?>
          <tr>
            <td><strong><?= htmlspecialchars($k['pseudo']) ?></strong></td>
            <td><span class="key-masked"><?= substr($k['key_val'],0,8) ?>••••<?= substr($k['key_val'],-4) ?></span></td>
            <td class="muted"><?= date('d/m/Y H:i', strtotime($k['created_at'])) ?></td>
            <td><a href="?del_key=<?= $k['id'] ?>" class="del" onclick="return confirm('Supprimer ?')">✕ supprimer</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MODÈLES -->
  <div class="section">
    <div class="section-head"><span class="section-title">Modèles Actifs</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Modèle</th><th>Statut</th><th>TPM limite</th><th>Tokens session</th><th>Dernier test</th></tr></thead>
        <tbody>
        <?php if (empty($models)): ?>
          <tr class="empty-row"><td colspan="5">Aucun modèle configuré</td></tr>
        <?php else: foreach ($models as $m): 
          $perc = $m['limit_tpm'] > 0 ? min(($m['used_tokens_session'] / $m['limit_tpm']) * 100, 100) : 0;
        ?>
          <tr>
            <td class="mono" style="color:var(--accent)"><?= htmlspecialchars($m['model_name']) ?></td>
            <td><span class="badge badge-<?= $m['last_status'] === 'OK' ? 'ok' : 'err' ?>"><?= $m['last_status'] ?: 'NON TESTÉ' ?></span></td>
            <td class="muted mono"><?= number_format($m['limit_tpm'],0,'.',' ') ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.75rem">
                <span class="mono" style="color:var(--amber)"><?= $m['used_tokens_session'] ?></span>
                <div class="progress-wrap"><div class="progress-bar" style="width:<?= $perc ?>%"></div></div>
              </div>
            </td>
            <td class="muted"><?= $m['last_tested'] ? date('H:i d/m', strtotime($m['last_tested'])) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PAGES GÉNÉRÉES -->
  <div class="section">
    <div class="section-head"><span class="section-title">Pages générées (<?= count($pages) ?>)</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Slug</th><th>Titre</th><th>Type</th><th>Vues</th><th>Créée</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($pages)): ?>
          <tr class="empty-row"><td colspan="6">Aucune page générée</td></tr>
        <?php else: foreach ($pages as $p): ?>
          <tr>
            <td><a href="index.php?p=<?= $p['slug'] ?>" style="color:var(--accent);font-family:var(--mono);font-size:.8rem;text-decoration:none"><?= htmlspecialchars($p['slug']) ?></a></td>
            <td style="max-width:300px"><?= htmlspecialchars($p['title'] ?: '—') ?></td>
            <td><span class="badge badge-<?= $p['page_type'] ?>"><?= $p['page_type'] ?></span></td>
            <td class="muted mono"><?= $p['views'] ?></td>
            <td class="muted"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
            <td><a href="?del_page=<?= urlencode($p['slug']) ?>" class="del" onclick="return confirm('Supprimer cette page ?')">✕</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- APPLICATIONS -->
  <div class="section">
    <div class="section-head"><span class="section-title">Applications PHP (<?= count($apps) ?>)</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom</th><th>Slug / Fichier</th><th>Description</th><th>Statut</th><th>Créée</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($apps)): ?>
          <tr class="empty-row"><td colspan="6">Aucune application créée</td></tr>
        <?php else: foreach ($apps as $a): ?>
          <tr>
            <td><strong><?= htmlspecialchars($a['app_name']) ?></strong></td>
            <td><a href="<?= $a['app_slug'] ?>.php" target="_blank" style="color:var(--accent);font-family:var(--mono);font-size:.78rem;text-decoration:none">↗ /<?= htmlspecialchars($a['app_slug']) ?>.php</a></td>
            <td class="muted" style="max-width:250px"><?= htmlspecialchars($a['description'] ?: '—') ?></td>
            <td><span class="badge badge-active"><?= $a['status'] ?></span></td>
            <td class="muted"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
            <td><a href="?del_app=<?= $a['id'] ?>" class="del" onclick="return confirm('Supprimer + fichier PHP ?')">✕</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MÉMOIRE IA -->
  <div class="section">
    <div class="section-head">
      <span class="section-title">Mémoire de l'IA (<?= count($memories) ?> entrées)</span>
      <?php if (!empty($memories)): ?>
      <a href="?clear_memory=1" class="section-action" onclick="return confirm('Effacer toute la mémoire IA ?')">⚠ Effacer la mémoire</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Événement</th><th>Résumé</th><th>Tokens</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($memories)): ?>
          <tr class="empty-row"><td colspan="4">Mémoire vide</td></tr>
        <?php else: foreach ($memories as $m): ?>
          <tr>
            <td><span class="mono type-<?= $m['event_type'] ?>" style="font-size:.72rem"><?= $m['event_type'] ?></span></td>
            <td style="max-width:450px;font-size:.82rem"><?= htmlspecialchars($m['summary']) ?></td>
            <td class="muted mono"><?= $m['tokens_used'] ?></td>
            <td class="muted"><?= date('d/m H:i:s', strtotime($m['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="text-align:center;color:var(--muted);font-family:var(--mono);font-size:.65rem;letter-spacing:.15em;padding:2rem 0">
    NEXUS ADMIN — BASE : <?= DB_FILE ?> — <?= number_format(filesize(DB_FILE)/1024, 1) ?> KB
  </div>

</div>
</body>
</html>
