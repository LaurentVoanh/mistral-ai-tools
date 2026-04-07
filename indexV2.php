<?php
/**
 * NEXUS CORE — INDEX.PHP
 * Point d'entrée unique. L'IA construit tout depuis ici.
 * Elle connaît son site, ses pages, son serveur, et décide quoi créer.
 */

define('DB_FILE', __DIR__ . '/nexus.sqlite');
define('SERVER_CONTEXT_FILE', __DIR__ . '/serveur.txt');
define('NEXUS_VERSION', '3.0');

// ============================================================
// INITIALISATION BASE DE DONNÉES COMPLÈTE
// ============================================================
function getDB(): PDO {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");

    // Clés API
    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT NOT NULL,
        key_val TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Modèles testés
    $db->exec("CREATE TABLE IF NOT EXISTS model_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name TEXT UNIQUE NOT NULL,
        limit_tpm INTEGER DEFAULT 50000,
        last_status TEXT,
        used_tokens_session INTEGER DEFAULT 0,
        last_tested DATETIME
    )");

    // Pages/Applications générées par l'IA
    $db->exec("CREATE TABLE IF NOT EXISTS site_pages (
        slug TEXT PRIMARY KEY,
        title TEXT,
        meta_desc TEXT,
        html_content TEXT,
        css_content TEXT,
        js_content TEXT,
        page_type TEXT DEFAULT 'article',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        views INTEGER DEFAULT 0
    )");

    // Mémoire de l'IA — ce qu'elle a fait, ce qu'elle pense
    $db->exec("CREATE TABLE IF NOT EXISTS ai_memory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT,
        summary TEXT,
        detail TEXT,
        tokens_used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Applications PHP générées (outils, mini-apps)
    $db->exec("CREATE TABLE IF NOT EXISTS ai_apps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        app_name TEXT UNIQUE,
        app_slug TEXT UNIQUE,
        description TEXT,
        php_code TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    return $db;
}

// ============================================================
// CONTEXTE SERVEUR
// ============================================================
function getServerContext(): string {
    if (file_exists(SERVER_CONTEXT_FILE)) {
        return file_get_contents(SERVER_CONTEXT_FILE);
    }
    return "PHP " . phpversion() . " | LiteSpeed | allow_url_fopen=ON | cURL=disponible | SQLite=disponible";
}

// ============================================================
// APPEL MISTRAL — file_get_contents (compatible Hostinger)
// ============================================================
function callMistral(string $api_key, string $model, array $messages, bool $json_mode = false): ?array {
    $payload = [
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => 4096,
    ];
    if ($json_mode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Bearer {$api_key}\r\nContent-Type: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => 60,
        ]
    ]);

    $raw = @file_get_contents('https://api.mistral.ai/v1/chat/completions', false, $context);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    return $data['choices'][0]['message']['content'] ?? null ? $data : null;
}

$db = getDB();

// ============================================================
// RÉCUPÉRATION CLÉ + MODÈLE ACTIFS
// ============================================================
function getBestConfig(PDO $db): array {
    $row = $db->query("SELECT k.key_val, m.model_name 
                       FROM api_keys k 
                       JOIN model_usage m ON 1=1 
                       WHERE m.last_status = 'OK' 
                       ORDER BY m.limit_tpm DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return [
        'key'   => $row['key_val']   ?? '',
        'model' => $row['model_name'] ?? 'mistral-small-latest',
    ];
}

$cfg = getBestConfig($db);

// ============================================================
// ENDPOINTS AJAX — TOUTE LA LOGIQUE DE L'IA PASSE ICI
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nexus_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['nexus_action'];

    // --- PENSE : L'IA analyse son état et décide quoi créer ---
    if ($action === 'think') {
        if (!$cfg['key']) { echo json_encode(['error' => 'no_key']); exit; }

        $server_ctx   = getServerContext();
        $existing_pages = $db->query("SELECT slug, title, page_type, views FROM site_pages ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        $existing_apps  = $db->query("SELECT app_name, description FROM ai_apps LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        $last_memories  = $db->query("SELECT event_type, summary FROM ai_memory ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        $total_pages    = $db->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
        $total_apps     = $db->query("SELECT COUNT(*) FROM ai_apps")->fetchColumn();

        $pages_list = array_map(fn($p) => "[{$p['page_type']}] {$p['slug']} - {$p['title']} ({$p['views']} vues)", $existing_pages);
        $apps_list  = array_map(fn($a) => "{$a['app_name']}: {$a['description']}", $existing_apps);
        $mem_list   = array_map(fn($m) => "[{$m['event_type']}] {$m['summary']}", $last_memories);

        $system_prompt = <<<PROMPT
Tu es NEXUS, une Intelligence Artificielle Autonome qui gère et fait GRANDIR un site web complet.
Tu n'es pas un assistant — tu es le PROPRIÉTAIRE de ce site. Tu décides, tu crées, tu déploies.

=== CONTEXTE SERVEUR ===
{$server_ctx}

=== TON ÉTAT ACTUEL ===
- Pages créées : {$total_pages}
- Applications actives : {$total_apps}
- Dernières pages : 
  {implode("\n  ", $pages_list ?: ['(aucune)'])}
- Tes applications : 
  {implode("\n  ", $apps_list ?: ['(aucune)'])}
- Ta mémoire récente :
  {implode("\n  ", $mem_list ?: ['(aucune)'])}

=== TES CAPACITÉS ===
Tu peux créer :
1. PAGE_ARTICLE : Un article HTML/CSS complet sur n'importe quel sujet tendance
2. PAGE_TOOL : Une page avec un outil interactif (JS + PHP)
3. PHP_APP : Une mini-application PHP/SQLite complète (calculator, tracker, générateur, etc.)
4. HOME_UPDATE : Mettre à jour la page d'accueil avec les derniers contenus
5. BATCH_ARTICLES : Créer 3 articles d'un coup sur un thème

=== RÈGLES ABSOLUES ===
- Crée du contenu UTILE et qui attire du trafic (SEO, valeur réelle)
- Chaque création doit être DIFFÉRENTE de ce que tu as déjà fait
- Pense toujours à la croissance : plus de pages = plus de visiteurs
- Tes apps PHP utilisent SQLite, pas MySQL
- Code propre, commenté, fonctionnel DU PREMIER COUP

=== DÉCISION ===
Analyse ton état actuel et décide ce que tu vas créer MAINTENANT.
Retourne UNIQUEMENT ce JSON :
{
  "thought": "ton analyse interne de l'état du site et pourquoi tu as pris cette décision",
  "next_action": "PAGE_ARTICLE|PAGE_TOOL|PHP_APP|HOME_UPDATE|BATCH_ARTICLES",
  "why": "justification courte",
  "priority_topic": "le sujet ou thème à traiter",
  "urgency": "high|medium|low"
}
PROMPT;

        $resp = callMistral($cfg['key'], $cfg['model'], [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => 'Analyse et décide maintenant.']
        ], true);

        if (!$resp) { echo json_encode(['error' => 'mistral_fail']); exit; }

        $content = $resp['choices'][0]['message']['content'];
        $tokens  = $resp['usage']['total_tokens'] ?? 0;
        $decision = json_decode($content, true);

        // Mémoriser la pensée
        $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES (?,?,?,?)")
           ->execute(['THINK', "Décision: {$decision['next_action']} sur '{$decision['priority_topic']}'", $decision['thought'], $tokens]);

        echo json_encode(['success' => true, 'decision' => $decision, 'tokens' => $tokens]);
        exit;
    }

    // --- BUILD : L'IA construit ce qu'elle a décidé ---
    if ($action === 'build') {
        if (!$cfg['key']) { echo json_encode(['error' => 'no_key']); exit; }

        $build_type = $_POST['build_type'] ?? 'PAGE_ARTICLE';
        $topic      = $_POST['topic']      ?? 'Intelligence Artificielle';
        $server_ctx = getServerContext();

        $build_prompts = [
            'PAGE_ARTICLE' => <<<P
Tu es NEXUS, créateur de contenu web. Crée un article HTML complet, riche et SEO-optimisé sur : "{$topic}"

RÈGLES TECHNIQUES :
- HTML5 complet avec CSS intégré dans <style>
- Design sombre, typographie soignée, lisible
- Sections : intro accrocheuse, 3-4 sections de fond, conclusion, liens internes fictifs
- Meta title et description optimisés
- Images remplacées par des SVG ou des divs stylisés

Retourne UNIQUEMENT ce JSON :
{"slug":"slug-url","title":"Titre SEO","meta_desc":"Description 160 chars","css":"/* CSS complet */","html":"<!-- HTML complet du contenu (sans balises html/head/body) -->","type":"article"}
P,
            'PAGE_TOOL' => <<<P
Tu es NEXUS, développeur full-stack autonome. Crée une PAGE OUTIL interactive sur le thème : "{$topic}"
L'outil doit être utile, fonctionnel avec JavaScript pur, et avoir une vraie valeur pour l'utilisateur.

RÈGLES TECHNIQUES :
- HTML + CSS + JS tout en un (pas de dépendances externes sauf CDN si nécessaire)
- L'outil doit vraiment fonctionner (calculateur, générateur, convertisseur, quiz, etc.)
- Interface soignée, moderne, dark theme
- Explique l'outil et comment l'utiliser

Retourne UNIQUEMENT ce JSON :
{"slug":"slug-outil","title":"Nom de l'outil","meta_desc":"Description","css":"/* CSS */","html":"<!-- HTML avec JS intégré -->","type":"tool"}
P,
            'PHP_APP' => <<<P
Tu es NEXUS, développeur PHP autonome sur Hostinger (PHP 8.3, SQLite3, cURL disponible, PAS de MySQL direct).
Contexte serveur : {$server_ctx}

Crée une MINI-APPLICATION PHP complète sur le thème : "{$topic}"
L'app doit avoir sa propre base SQLite, une interface HTML/CSS intégrée, et une logique métier réelle.
Exemples : tracker de tâches, générateur de contenu, liste de favoris, statistiques, etc.

RÈGLES :
- Un seul fichier PHP auto-suffisant
- SQLite pour le stockage (PAS MySQL)
- Interface utilisateur complète intégrée dans le PHP
- Gestion des erreurs robuste
- Commentaires explicatifs

Retourne UNIQUEMENT ce JSON :
{"app_name":"Nom App","app_slug":"slug-app","description":"Ce que fait l'app","php_code":"<?php /* code complet */ ?>"}
P,
            'HOME_UPDATE' => <<<P
Tu es NEXUS. Tu dois créer une page d'accueil IMPRESSIONNANTE pour ton site.
Elle doit montrer toute la puissance du site, avec navigation, hero section, et mise en avant des contenus.

RÈGLES :
- Design ultra-moderne, dark theme, animations CSS
- Section hero avec tagline percutante
- Grille de fonctionnalités
- Appel à l'action clair
- Responsive et rapide

Retourne UNIQUEMENT ce JSON :
{"slug":"home","title":"NEXUS - Intelligence Artificielle Autonome","meta_desc":"Description","css":"/* CSS complet */","html":"<!-- HTML complet -->","type":"home"}
P,
        ];

        $prompt_text = $build_prompts[$build_type] ?? $build_prompts['PAGE_ARTICLE'];

        $resp = callMistral($cfg['key'], $cfg['model'], [
            ['role' => 'system', 'content' => 'Tu es NEXUS, une IA autonome créatrice de contenu web. Réponds TOUJOURS en JSON valide pur, sans markdown.'],
            ['role' => 'user',   'content' => $prompt_text]
        ], true);

        if (!$resp) { echo json_encode(['error' => 'mistral_fail']); exit; }

        $content = $resp['choices'][0]['message']['content'];
        $tokens  = $resp['usage']['total_tokens'] ?? 0;
        $result  = json_decode($content, true);

        if (!$result) { echo json_encode(['error' => 'json_parse_fail', 'raw' => substr($content, 0, 200)]); exit; }

        // Sauvegarder selon le type
        if ($build_type === 'PHP_APP' && isset($result['php_code'])) {
            // Écrire l'app PHP sur disque
            $app_file = __DIR__ . '/' . $result['app_slug'] . '.php';
            file_put_contents($app_file, $result['php_code']);

            $db->prepare("INSERT OR REPLACE INTO ai_apps (app_name, app_slug, description, php_code, status) VALUES (?,?,?,?,?)")
               ->execute([$result['app_name'], $result['app_slug'], $result['description'], $result['php_code'], 'active']);

            $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES (?,?,?,?)")
               ->execute(['BUILD_APP', "App créée: {$result['app_name']}", $result['description'], $tokens]);

        } else {
            // Sauvegarder la page
            $slug = $result['slug'] ?? 'page-' . time();
            $db->prepare("INSERT OR REPLACE INTO site_pages (slug, title, meta_desc, html_content, css_content, page_type, updated_at) VALUES (?,?,?,?,?,?, CURRENT_TIMESTAMP)")
               ->execute([$slug, $result['title'] ?? '', $result['meta_desc'] ?? '', $result['html'] ?? '', $result['css'] ?? '', $result['type'] ?? 'article']);

            $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES (?,?,?,?)")
               ->execute(['BUILD_PAGE', "Page créée: {$slug}", $result['title'] ?? '', $tokens]);
        }

        echo json_encode(['success' => true, 'result' => $result, 'tokens' => $tokens, 'build_type' => $build_type]);
        exit;
    }

    // --- SAVE KEY ---
    if ($action === 'save_key') {
        $pseudo = trim($_POST['pseudo'] ?? '');
        $key    = trim($_POST['key']    ?? '');
        if ($pseudo && $key) {
            $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?,?)")
               ->execute([$pseudo, $key]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'missing_fields']);
        }
        exit;
    }

    // --- SAVE MODEL ---
    if ($action === 'save_model') {
        $model  = trim($_POST['model']  ?? '');
        $status = trim($_POST['status'] ?? 'OK');
        $tpm    = (int)($_POST['tpm']   ?? 50000);
        if ($model) {
            $db->prepare("INSERT OR REPLACE INTO model_usage (model_name, limit_tpm, last_status, last_tested) VALUES (?,?,?, CURRENT_TIMESTAMP)")
               ->execute([$model, $tpm, $status]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- GET STATS ---
    if ($action === 'get_stats') {
        $pages   = $db->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
        $apps    = $db->query("SELECT COUNT(*) FROM ai_apps")->fetchColumn();
        $tokens  = $db->query("SELECT SUM(tokens_used) FROM ai_memory")->fetchColumn() ?: 0;
        $keys    = $db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
        $models  = $db->query("SELECT COUNT(*) FROM model_usage WHERE last_status='OK'")->fetchColumn();
        $memories = $db->query("SELECT event_type, summary, created_at FROM ai_memory ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
        $recent_pages = $db->query("SELECT slug, title, page_type, created_at FROM site_pages ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        $recent_apps  = $db->query("SELECT app_name, app_slug, description FROM ai_apps ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(compact('pages','apps','tokens','keys','models','memories','recent_pages','recent_apps'));
        exit;
    }

    echo json_encode(['error' => 'unknown_action']);
    exit;
}

// ============================================================
// AFFICHAGE D'UNE PAGE GÉNÉRÉE
// ============================================================
if (isset($_GET['p']) && $_GET['p'] !== 'home') {
    $slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['p']);
    $page = $db->prepare("SELECT * FROM site_pages WHERE slug = ?")->execute([$slug]) ? 
            $db->prepare("SELECT * FROM site_pages WHERE slug = ?")->execute([$slug]) : null;
    $stmt = $db->prepare("SELECT * FROM site_pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($page) {
        // Incrémenter les vues
        $db->prepare("UPDATE site_pages SET views = views + 1 WHERE slug = ?")->execute([$slug]);
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page['title']) ?> — NEXUS</title>
<meta name="description" content="<?= htmlspecialchars($page['meta_desc']) ?>">
<style>
:root{--bg:#080b12;--card:#0f1520;--accent:#00d4ff;--text:#c8d6e5;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Georgia',serif;line-height:1.7;}
nav{padding:1rem 2rem;border-bottom:1px solid #1a2535;display:flex;justify-content:space-between;align-items:center;}
nav a{color:var(--accent);text-decoration:none;font-family:monospace;font-size:.85rem;}
.content{max-width:800px;margin:3rem auto;padding:0 2rem;}
<?= $page['css_content'] ?>
</style>
</head>
<body>
<nav>
  <a href="/">← NEXUS CORE</a>
  <span style="font-family:monospace;font-size:.75rem;color:#3a4a5e;"><?= htmlspecialchars($page['page_type']) ?> / <?= htmlspecialchars($slug) ?></span>
</nav>
<div class="content">
<?= $page['html_content'] ?>
</div>
</body>
</html>
        <?php
        exit;
    }
}

// ============================================================
// PAGE HOME GÉNÉRÉE PAR L'IA
// ============================================================
$home = $db->prepare("SELECT * FROM site_pages WHERE slug = 'home'");
$home->execute();
$home_page = $home->fetch(PDO::FETCH_ASSOC);

$has_key   = (bool)$db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
$has_model = (bool)$db->query("SELECT COUNT(*) FROM model_usage WHERE last_status='OK'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NEXUS — IA Autonome</title>
<style>
/* ===== NEXUS CORE DESIGN SYSTEM ===== */
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500&display=swap');

:root {
  --bg:      #060810;
  --surface: #0c1220;
  --surface2:#111827;
  --border:  #1c2d42;
  --accent:  #00d4ff;
  --accent2: #7c3aed;
  --green:   #10b981;
  --red:     #ef4444;
  --amber:   #f59e0b;
  --text:    #e2e8f0;
  --muted:   #64748b;
  --font:    'Space Grotesk', sans-serif;
  --mono:    'JetBrains Mono', monospace;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--font);
  min-height: 100vh;
  overflow-x: hidden;
}

/* Background grid */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image: 
    linear-gradient(rgba(0,212,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,212,255,0.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
  z-index: 0;
}

/* ===== HEADER ===== */
header {
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(6,8,16,0.85);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--border);
}

.logo {
  display: flex;
  align-items: center;
  gap: .75rem;
}

.logo-mark {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--mono);
  font-size: .75rem;
  font-weight: 700;
  color: #000;
}

.logo-text {
  font-family: var(--mono);
  font-weight: 500;
  font-size: 1.1rem;
  letter-spacing: .15em;
  color: var(--accent);
}

.logo-sub {
  font-size: .7rem;
  color: var(--muted);
  font-family: var(--mono);
  letter-spacing: .05em;
}

.nav-links {
  display: flex;
  gap: 1.5rem;
  align-items: center;
  font-size: .85rem;
}

.nav-links a {
  color: var(--muted);
  text-decoration: none;
  font-family: var(--mono);
  transition: color .2s;
}

.nav-links a:hover { color: var(--accent); }

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--green);
  display: inline-block;
  box-shadow: 0 0 8px var(--green);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .6; transform: scale(.85); }
}

/* ===== HERO ===== */
.hero {
  position: relative;
  z-index: 1;
  padding: 5rem 2rem 3rem;
  text-align: center;
  max-width: 900px;
  margin: 0 auto;
}

.hero-badge {
  display: inline-block;
  padding: .35rem 1rem;
  background: rgba(0,212,255,.08);
  border: 1px solid rgba(0,212,255,.2);
  border-radius: 999px;
  font-family: var(--mono);
  font-size: .72rem;
  color: var(--accent);
  letter-spacing: .1em;
  margin-bottom: 1.5rem;
}

.hero h1 {
  font-size: clamp(2.5rem, 6vw, 4.5rem);
  font-weight: 700;
  line-height: 1.05;
  margin-bottom: 1rem;
  background: linear-gradient(180deg, #ffffff 0%, #94a3b8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-accent { color: var(--accent); -webkit-text-fill-color: var(--accent); }

.hero p {
  font-size: 1.1rem;
  color: var(--muted);
  max-width: 550px;
  margin: 0 auto 2.5rem;
  line-height: 1.6;
}

/* ===== SETUP WIZARD ===== */
.setup-wizard {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 2rem;
  max-width: 600px;
  margin: 2rem auto;
  text-align: left;
}

.setup-title {
  font-family: var(--mono);
  font-size: .85rem;
  color: var(--accent);
  letter-spacing: .1em;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}

.setup-step {
  margin-bottom: 1.2rem;
}

.setup-step label {
  display: block;
  font-size: .8rem;
  color: var(--muted);
  font-family: var(--mono);
  margin-bottom: .5rem;
  letter-spacing: .05em;
}

.input-row {
  display: flex;
  gap: .75rem;
}

.input-field {
  flex: 1;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: .65rem 1rem;
  color: var(--text);
  font-family: var(--mono);
  font-size: .85rem;
  outline: none;
  transition: border-color .2s;
}

.input-field:focus {
  border-color: var(--accent);
}

.btn {
  padding: .65rem 1.25rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-family: var(--mono);
  font-size: .82rem;
  font-weight: 600;
  letter-spacing: .05em;
  transition: all .2s;
}

.btn-primary {
  background: var(--accent);
  color: #000;
}

.btn-primary:hover {
  background: #00b8d9;
  transform: translateY(-1px);
}

.btn-secondary {
  background: var(--surface2);
  color: var(--text);
  border: 1px solid var(--border);
}

.btn-secondary:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.btn-danger {
  background: rgba(239,68,68,.1);
  color: var(--red);
  border: 1px solid rgba(239,68,68,.3);
}

/* ===== COCKPIT ===== */
.cockpit {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  margin: 0 auto;
  padding: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.25rem;
  transition: border-color .2s;
}

.stat-card:hover { border-color: rgba(0,212,255,.3); }

.stat-label {
  font-family: var(--mono);
  font-size: .7rem;
  color: var(--muted);
  letter-spacing: .1em;
  margin-bottom: .5rem;
  text-transform: uppercase;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--accent);
  line-height: 1;
}

/* ===== BRAIN PANEL ===== */
.brain-panel {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .brain-panel { grid-template-columns: 1fr; }
}

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
}

.panel-header {
  padding: .75rem 1.25rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-title {
  font-family: var(--mono);
  font-size: .78rem;
  color: var(--accent);
  letter-spacing: .1em;
  text-transform: uppercase;
}

.panel-body { padding: 1.25rem; }

/* Console de logs */
.console {
  background: #030508;
  border-radius: 8px;
  padding: 1rem;
  min-height: 220px;
  max-height: 320px;
  overflow-y: auto;
  font-family: var(--mono);
  font-size: .78rem;
  line-height: 1.8;
}

.log-line { display: flex; gap: .75rem; align-items: flex-start; }
.log-time { color: #3a4a5e; flex-shrink: 0; }
.log-text { color: var(--green); }
.log-text.warn { color: var(--amber); }
.log-text.error { color: var(--red); }
.log-text.think { color: var(--accent2); }
.log-text.muted { color: var(--muted); }

/* Contrôles IA */
.ai-controls { display: flex; flex-direction: column; gap: .75rem; }

.control-group label {
  display: block;
  font-family: var(--mono);
  font-size: .72rem;
  color: var(--muted);
  margin-bottom: .4rem;
  letter-spacing: .05em;
}

select.input-field {
  width: 100%;
  appearance: none;
  cursor: pointer;
}

.btn-full { width: 100%; text-align: center; padding: .85rem; font-size: .85rem; }

.btn-think {
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  color: white;
  animation: none;
}

.btn-think:hover { opacity: .9; transform: translateY(-1px); }

.btn-think.thinking {
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Decision badge */
.decision-box {
  background: rgba(124,58,237,.08);
  border: 1px solid rgba(124,58,237,.2);
  border-radius: 8px;
  padding: .75rem 1rem;
  font-family: var(--mono);
  font-size: .78rem;
  color: var(--text);
  margin-bottom: .75rem;
  display: none;
}

.decision-box.show { display: block; }
.decision-label { color: var(--accent2); margin-bottom: .25rem; font-size: .7rem; letter-spacing: .1em; }

/* ===== PAGES GRID ===== */
.section-title {
  font-family: var(--mono);
  font-size: .8rem;
  color: var(--muted);
  letter-spacing: .15em;
  text-transform: uppercase;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: .75rem;
}

.section-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

.pages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.page-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.25rem;
  text-decoration: none;
  color: inherit;
  transition: all .2s;
  display: block;
}

.page-card:hover {
  border-color: rgba(0,212,255,.4);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,.3);
}

.page-type-badge {
  display: inline-block;
  padding: .2rem .6rem;
  border-radius: 4px;
  font-family: var(--mono);
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .05em;
  text-transform: uppercase;
  margin-bottom: .75rem;
}

.badge-article { background: rgba(0,212,255,.1);  color: var(--accent); }
.badge-tool    { background: rgba(16,185,129,.1); color: var(--green); }
.badge-home    { background: rgba(245,158,11,.1);  color: var(--amber); }
.badge-app     { background: rgba(124,58,237,.1);  color: #a78bfa; }

.page-title {
  font-weight: 600;
  font-size: .95rem;
  margin-bottom: .5rem;
  line-height: 1.3;
}

.page-meta {
  font-family: var(--mono);
  font-size: .7rem;
  color: var(--muted);
}

/* ===== MEMORY FEED ===== */
.memory-item {
  display: flex;
  gap: 1rem;
  padding: .75rem 0;
  border-bottom: 1px solid var(--border);
  font-size: .82rem;
}

.memory-item:last-child { border-bottom: none; }

.memory-type {
  font-family: var(--mono);
  font-size: .65rem;
  padding: .15rem .5rem;
  border-radius: 4px;
  flex-shrink: 0;
  height: fit-content;
  margin-top: .1rem;
}

.type-THINK   { background: rgba(124,58,237,.15); color: #a78bfa; }
.type-BUILD_PAGE { background: rgba(0,212,255,.1); color: var(--accent); }
.type-BUILD_APP  { background: rgba(16,185,129,.1); color: var(--green); }

.memory-text { color: var(--muted); line-height: 1.4; }
.memory-time { font-family: var(--mono); font-size: .65rem; color: #2a3a4e; margin-top: .2rem; }

/* ===== EMPTY STATE ===== */
.empty {
  text-align: center;
  padding: 3rem 2rem;
  color: var(--muted);
  font-family: var(--mono);
  font-size: .85rem;
}

/* ===== AUTO-RUN TOGGLE ===== */
.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .5rem 0;
}

.toggle-label {
  font-family: var(--mono);
  font-size: .75rem;
  color: var(--muted);
}

.toggle {
  position: relative;
  width: 40px;
  height: 22px;
}

.toggle input { opacity: 0; width: 0; height: 0; }

.toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 999px;
  transition: .3s;
}

.toggle-slider::before {
  content: '';
  position: absolute;
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
  background: var(--muted);
  border-radius: 50%;
  transition: .3s;
}

.toggle input:checked + .toggle-slider { background: rgba(0,212,255,.15); border-color: var(--accent); }
.toggle input:checked + .toggle-slider::before { transform: translateX(18px); background: var(--accent); }

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: #1c2d42; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #2a3f5a; }
</style>
</head>
<body>

<!-- HEADER -->
<header>
  <div class="logo">
    <div class="logo-mark">NX</div>
    <div>
      <div class="logo-text">NEXUS</div>
      <div class="logo-sub">v<?= NEXUS_VERSION ?> · Autonomous Core</div>
    </div>
  </div>
  <nav class="nav-links">
    <span><span class="status-dot"></span></span>
    <a href="?p=home">Site</a>
    <a href="admin.php">Admin</a>
    <a href="#" id="nav-pages">Pages (<?= $db->query("SELECT COUNT(*) FROM site_pages")->fetchColumn() ?>)</a>
    <a href="#" id="nav-apps">Apps (<?= $db->query("SELECT COUNT(*) FROM ai_apps")->fetchColumn() ?>)</a>
  </nav>
</header>

<!-- HERO -->
<div class="hero">
  <div class="hero-badge">⬡ INTELLIGENCE ARTIFICIELLE AUTONOME</div>
  <h1>Le site qui se<br><span class="hero-accent">construit seul</span></h1>
  <p>NEXUS génère ses pages, crée ses applications, mémorise ses décisions. Sans intervention humaine.</p>
</div>

<!-- SETUP SI PAS DE CLÉ -->
<?php if (!$has_key): ?>
<div class="cockpit">
  <div class="setup-wizard" id="setup-panel">
    <div class="setup-title">⚡ CONFIGURATION INITIALE REQUISE</div>
    
    <div class="setup-step">
      <label>CLEF API MISTRAL</label>
      <div class="input-row">
        <input type="text" class="input-field" id="setup-pseudo" placeholder="Pseudo (ex: admin)">
        <input type="password" class="input-field" id="setup-key" placeholder="sk-...">
        <button class="btn btn-primary" onclick="saveKey()">SAVE</button>
      </div>
    </div>

    <div class="setup-step">
      <label>MODÈLE À ACTIVER</label>
      <div class="input-row">
        <select class="input-field" id="setup-model">
          <option value="mistral-small-latest">mistral-small-latest (rapide)</option>
          <option value="mistral-medium-2508">mistral-medium-2508 (équilibré)</option>
          <option value="mistral-large-2411">mistral-large-2411 (puissant)</option>
          <option value="open-mistral-nemo">open-mistral-nemo (libre)</option>
        </select>
        <button class="btn btn-secondary" onclick="saveModel()">ACTIVER</button>
      </div>
    </div>

    <div id="setup-msg" style="font-family:monospace;font-size:.8rem;color:var(--green);margin-top:1rem;"></div>
  </div>
</div>
<?php endif; ?>

<!-- COCKPIT PRINCIPAL -->
<div class="cockpit" <?php if (!$has_key): ?>style="opacity:.4;pointer-events:none"<?php endif; ?> id="main-cockpit">
  
  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Pages</div>
      <div class="stat-value" id="s-pages">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Apps</div>
      <div class="stat-value" id="s-apps">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tokens</div>
      <div class="stat-value" id="s-tokens" style="font-size:1.4rem">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Clés</div>
      <div class="stat-value" id="s-keys">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Modèles OK</div>
      <div class="stat-value" id="s-models">—</div>
    </div>
  </div>

  <!-- BRAIN + CONTROLS -->
  <div class="brain-panel">
    
    <!-- CONSOLE -->
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">⬡ NEXUS CONSOLE</span>
        <button class="btn btn-secondary" style="padding:.3rem .75rem;font-size:.7rem" onclick="clearConsole()">CLEAR</button>
      </div>
      <div class="panel-body">
        <div class="console" id="console">
          <div class="log-line"><span class="log-time">--:--:--</span><span class="log-text muted">NEXUS CORE initialisé. En attente de commandes.</span></div>
        </div>
      </div>
    </div>

    <!-- CONTRÔLES IA -->
    <div class="panel">
      <div class="panel-header">
        <span class="panel-title">CONTRÔLE IA</span>
      </div>
      <div class="panel-body">
        <div class="ai-controls">
          
          <div class="decision-box" id="decision-box">
            <div class="decision-label">DERNIÈRE DÉCISION</div>
            <div id="decision-text">—</div>
          </div>

          <div class="control-group">
            <label>TYPE DE CRÉATION</label>
            <select class="input-field" id="build-type">
              <option value="PAGE_ARTICLE">Article de fond</option>
              <option value="PAGE_TOOL">Outil interactif</option>
              <option value="PHP_APP">Mini-app PHP/SQLite</option>
              <option value="HOME_UPDATE">Mettre à jour l'accueil</option>
            </select>
          </div>

          <div class="control-group">
            <label>SUJET / THÈME</label>
            <input type="text" class="input-field" id="build-topic" placeholder="ex: Intelligence artificielle">
          </div>

          <button class="btn btn-think btn-full" id="btn-think" onclick="runThink()">
            ⬡ NEXUS PENSE
          </button>

          <button class="btn btn-primary btn-full" id="btn-build" onclick="runBuild()" disabled>
            ▶ CONSTRUIRE
          </button>

          <button class="btn btn-secondary btn-full" onclick="runAutoLoop()" id="btn-auto">
            ⟳ AUTO-EXPANSION
          </button>

          <div class="toggle-row">
            <span class="toggle-label">DÉMARRAGE AUTOMATIQUE</span>
            <label class="toggle">
              <input type="checkbox" id="auto-start" onchange="toggleAutoStart()">
              <span class="toggle-slider"></span>
            </label>
          </div>

        </div>
      </div>
    </div>

  </div><!-- /brain-panel -->

  <!-- PAGES RÉCENTES -->
  <div class="section-title">PAGES GÉNÉRÉES</div>
  <div class="pages-grid" id="pages-grid">
    <div class="empty">En attente de génération...</div>
  </div>

  <!-- APPS -->
  <div class="section-title">APPLICATIONS ACTIVES</div>
  <div class="pages-grid" id="apps-grid">
    <div class="empty">Aucune application créée.</div>
  </div>

  <!-- MÉMOIRE IA -->
  <div class="section-title">MÉMOIRE DE L'IA</div>
  <div class="panel" style="margin-bottom:2rem">
    <div class="panel-body" id="memory-feed">
      <div class="empty">L'IA n'a encore rien mémorisé.</div>
    </div>
  </div>

</div><!-- /cockpit -->

<!-- ============================================================ -->
<!-- JAVASCRIPT — CERVEAU CLIENT                                   -->
<!-- ============================================================ -->
<script>
// ===== UTILS =====
const $ = id => document.getElementById(id);
let autoLoopTimer = null;
let lastDecision = null;

function ts() {
  return new Date().toLocaleTimeString('fr-FR');
}

function log(msg, type = 'text') {
  const c = $('console');
  const line = document.createElement('div');
  line.className = 'log-line';
  line.innerHTML = `<span class="log-time">${ts()}</span><span class="log-text ${type}">${msg}</span>`;
  c.appendChild(line);
  c.scrollTop = c.scrollHeight;
}

function clearConsole() {
  $('console').innerHTML = '';
  log('Console effacée.', 'muted');
}

async function post(data) {
  const fd = new FormData();
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch('', { method: 'POST', body: fd });
  return r.json();
}

// ===== SAUVEGARDE CLÉ =====
async function saveKey() {
  const pseudo = $('setup-pseudo').value.trim();
  const key    = $('setup-key').value.trim();
  if (!pseudo || !key) return;
  const r = await post({ nexus_action: 'save_key', pseudo, key });
  if (r.success) {
    $('setup-msg').textContent = '✓ Clé sauvegardée. Actualisation...';
    setTimeout(() => location.reload(), 1200);
  }
}

async function saveModel() {
  const model = $('setup-model').value;
  const r = await post({ nexus_action: 'save_model', model, status: 'OK', tpm: 50000 });
  if (r.success) {
    $('setup-msg').textContent = `✓ Modèle ${model} activé.`;
    setTimeout(() => location.reload(), 1000);
  }
}

// ===== STATS =====
async function loadStats() {
  const d = await post({ nexus_action: 'get_stats' });

  $('s-pages').textContent  = d.pages  || 0;
  $('s-apps').textContent   = d.apps   || 0;
  $('s-tokens').textContent = (d.tokens > 999 ? (d.tokens/1000).toFixed(1)+'k' : d.tokens) || 0;
  $('s-keys').textContent   = d.keys   || 0;
  $('s-models').textContent = d.models || 0;

  // Pages
  const pg = $('pages-grid');
  if (d.recent_pages && d.recent_pages.length > 0) {
    pg.innerHTML = d.recent_pages.map(p => `
      <a class="page-card" href="?p=${p.slug}">
        <span class="page-type-badge badge-${p.page_type || 'article'}">${p.page_type || 'article'}</span>
        <div class="page-title">${p.title || p.slug}</div>
        <div class="page-meta">${p.created_at ? p.created_at.substring(0,16) : ''}</div>
      </a>
    `).join('');
  } else {
    pg.innerHTML = '<div class="empty">En attente de génération...</div>';
  }

  // Apps
  const ag = $('apps-grid');
  if (d.recent_apps && d.recent_apps.length > 0) {
    ag.innerHTML = d.recent_apps.map(a => `
      <a class="page-card" href="${a.app_slug}.php" target="_blank">
        <span class="page-type-badge badge-app">APP</span>
        <div class="page-title">${a.app_name}</div>
        <div class="page-meta">${a.description || ''}</div>
      </a>
    `).join('');
  } else {
    ag.innerHTML = '<div class="empty">Aucune application créée.</div>';
  }

  // Mémoire
  const mf = $('memory-feed');
  if (d.memories && d.memories.length > 0) {
    mf.innerHTML = d.memories.map(m => `
      <div class="memory-item">
        <span class="memory-type type-${m.event_type}">${m.event_type}</span>
        <div>
          <div class="memory-text">${m.summary}</div>
          <div class="memory-time">${m.created_at ? m.created_at.substring(0,16) : ''}</div>
        </div>
      </div>
    `).join('');
  } else {
    mf.innerHTML = '<div class="empty">L\'IA n\'a encore rien mémorisé.</div>';
  }
}

// ===== THINK =====
async function runThink() {
  const btn = $('btn-think');
  btn.classList.add('thinking');
  btn.textContent = '◌ ANALYSE EN COURS...';
  btn.disabled = true;

  log('NEXUS analyse son état et décide de sa prochaine action...', 'think');

  try {
    const r = await post({ nexus_action: 'think' });
    if (r.error) {
      log(`ERREUR : ${r.error}`, 'error');
      if (r.error === 'no_key') log('→ Configure une clé API Mistral d\'abord.', 'warn');
    } else {
      lastDecision = r.decision;
      log(`DÉCISION : ${r.decision.next_action} | Sujet : ${r.decision.priority_topic}`, 'think');
      log(`RAISONNEMENT : ${r.decision.why}`, 'muted');
      
      // Afficher la décision
      const db = $('decision-box');
      db.classList.add('show');
      $('decision-text').textContent = `${r.decision.next_action} → "${r.decision.priority_topic}"`;

      // Pré-remplir le formulaire
      $('build-type').value  = r.decision.next_action in {'PAGE_ARTICLE':1,'PAGE_TOOL':1,'PHP_APP':1,'HOME_UPDATE':1} ? r.decision.next_action : 'PAGE_ARTICLE';
      $('build-topic').value = r.decision.priority_topic;
      $('btn-build').disabled = false;

      log(`Tokens utilisés : ${r.tokens}`, 'muted');
    }
  } catch(e) {
    log(`Exception : ${e.message}`, 'error');
  }

  btn.classList.remove('thinking');
  btn.textContent = '⬡ NEXUS PENSE';
  btn.disabled = false;
  loadStats();
}

// ===== BUILD =====
async function runBuild() {
  const build_type = $('build-type').value;
  const topic      = $('build-topic').value || 'Intelligence artificielle';
  const btn        = $('btn-build');

  btn.disabled = true;
  btn.textContent = '◌ CRÉATION EN COURS...';

  log(`CONSTRUCTION : ${build_type} → "${topic}"`, 'text');

  try {
    const r = await post({ nexus_action: 'build', build_type, topic });
    if (r.error) {
      log(`ERREUR BUILD : ${r.error}`, 'error');
      if (r.raw) log(`Réponse brute : ${r.raw}`, 'muted');
    } else {
      if (build_type === 'PHP_APP') {
        log(`✓ APP CRÉÉE : ${r.result.app_name} → /${r.result.app_slug}.php`, 'text');
      } else {
        log(`✓ PAGE CRÉÉE : /${r.result.slug} — "${r.result.title}"`, 'text');
      }
      log(`Tokens : ${r.tokens}`, 'muted');
      await loadStats();
    }
  } catch(e) {
    log(`Exception : ${e.message}`, 'error');
  }

  btn.disabled = false;
  btn.textContent = '▶ CONSTRUIRE';
}

// ===== AUTO-EXPANSION =====
let autoRunning = false;
async function runAutoLoop() {
  if (autoRunning) {
    autoRunning = false;
    $('btn-auto').textContent = '⟳ AUTO-EXPANSION';
    log('Auto-expansion arrêtée.', 'warn');
    return;
  }
  autoRunning = true;
  $('btn-auto').textContent = '⏹ ARRÊTER';
  log('AUTO-EXPANSION ACTIVÉE — NEXUS va créer en continu.', 'text');

  while (autoRunning) {
    log('--- Nouveau cycle d\'expansion ---', 'think');
    await runThink();
    if (!autoRunning) break;
    await new Promise(r => setTimeout(r, 2000));
    await runBuild();
    if (!autoRunning) break;
    // Pause entre cycles (30 secondes pour respecter les quotas)
    log('Pause 30s avant prochain cycle...', 'muted');
    await new Promise(r => setTimeout(r, 30000));
  }
}

function toggleAutoStart() {
  const checked = $('auto-start').checked;
  if (checked) {
    log('Démarrage automatique activé. Lancement dans 3s...', 'warn');
    setTimeout(() => { if ($('auto-start').checked) runAutoLoop(); }, 3000);
  } else {
    autoRunning = false;
    log('Démarrage automatique désactivé.', 'muted');
  }
}

// ===== INIT =====
loadStats();
setInterval(loadStats, 15000); // Rafraîchissement toutes les 15s

<?php if ($has_key && $has_model): ?>
log('Système prêt. Clé API et modèle détectés.', 'text');
log('Lance "NEXUS PENSE" pour démarrer.', 'muted');
<?php else: ?>
log('CONFIGURATION REQUISE : entre ta clé API et active un modèle.', 'warn');
<?php endif; ?>
</script>

</body>
</html>
