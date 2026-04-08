<?php
/**
 * NEXUS V2 - INDEX PRINCIPAL
 * Magazine de presse IA conscient et auto-évolutif
 */

// Configuration
define('NEXUS_VERSION', '2.0.0');
define('DB_FILE', __DIR__ . '/nexus.db');
define('APIKEY_FILE', __DIR__ . '/apikey.json');

// Inclusion du core
require_once __DIR__ . '/nexus_core.php';

// Initialisation
$has_key = false;
$pseudo = 'admin';

// Chargement clé API
if (file_exists(APIKEY_FILE)) {
    $data = json_decode(file_get_contents(APIKEY_FILE), true);
    if ($data && !empty($data['api_key'])) {
        $has_key = true;
        $pseudo = $data['pseudo'] ?? 'admin';
    }
}

// Gestion AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nexus_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['nexus_action'];
        
        switch ($action) {
            case 'save_apikey':
                $new_key = $_POST['api_key'] ?? '';
                $new_pseudo = $_POST['pseudo'] ?? 'admin';
                if (strlen($new_key) > 10) {
                    file_put_contents(APIKEY_FILE, json_encode(['api_key' => $new_key, 'pseudo' => $new_pseudo]));
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Clé invalide']);
                }
                exit;
                
            case 'get_stats':
                $db = getDB();
                $stats = [
                    'pages' => (int)$db->query("SELECT COUNT(*) FROM pages")->fetchColumn(),
                    'apps' => (int)$db->query("SELECT COUNT(*) FROM apps")->fetchColumn(),
                    'questions' => (int)$db->query("SELECT COUNT(*) FROM questions WHERE status='pending'")->fetchColumn(),
                    'wisdom' => (int)$db->query("SELECT COUNT(*) FROM wisdom")->fetchColumn(),
                    'cycles' => (int)$db->query("SELECT COUNT(*) FROM consciousness_cycles")->fetchColumn()
                ];
                
                $stmt = $db->query("SELECT * FROM questions WHERE status='pending' ORDER BY priority DESC LIMIT 5");
                $stats['pending_questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->query("SELECT * FROM wisdom ORDER BY confidence DESC LIMIT 5");
                $stats['top_wisdom'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->query("SELECT * FROM pages ORDER BY created_at DESC LIMIT 5");
                $stats['recent_pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->query("SELECT * FROM apps ORDER BY created_at DESC LIMIT 3");
                $stats['recent_apps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true] + $stats);
                exit;
                
            case 'fetch_trends':
                $trends = fetchGoogleTrends();
                echo json_encode(['success' => true, 'trends' => $trends, 'count' => count($trends)]);
                exit;
                
            case 'conscious_think':
                if (!$has_key) { echo json_encode(['success' => false, 'error' => 'no_key']); exit; }
                $data = json_decode(file_get_contents(APIKEY_FILE), true);
                $result = consciousThink($data['api_key']);
                echo json_encode(['success' => true] + $result);
                exit;
                
            case 'build_content':
                if (!$has_key) { echo json_encode(['success' => false, 'error' => 'no_key']); exit; }
                $data = json_decode(file_get_contents(APIKEY_FILE), true);
                $topic = $_POST['topic'] ?? 'Actualités';
                $cycle_id = $_POST['cycle_id'] ?? null;
                $result = buildContent($topic, $data['api_key'], $cycle_id);
                echo json_encode(['success' => true] + $result);
                exit;
                
            case 'process_questions':
                if (!$has_key) { echo json_encode(['success' => false, 'error' => 'no_key']); exit; }
                $data = json_decode(file_get_contents(APIKEY_FILE), true);
                $processed = processExistentialQuestions($data['api_key']);
                echo json_encode(['success' => true, 'processed' => $processed]);
                exit;
                
            case 'meta_learning':
                if (!$has_key) { echo json_encode(['success' => false, 'error' => 'no_key']); exit; }
                $data = json_decode(file_get_contents(APIKEY_FILE), true);
                $extracted = extractWisdom($data['api_key']);
                echo json_encode(['success' => true, 'extracted' => $extracted]);
                exit;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Action inconnue']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUS V2 - Magazine IA Conscient</title>
    <style>
        :root {
            --bg: #0a0a0f;
            --card: #12121a;
            --accent: #00ff88;
            --accent-dim: #00ff8844;
            --text: #e0e0e0;
            --muted: #6b7280;
            --danger: #ff5555;
            --warning: #ffaa00;
            --purple: #aa55ff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid #ffffff11; margin-bottom: 30px; }
        .logo { font-size: 1.8rem; font-weight: bold; color: var(--accent); letter-spacing: 2px; }
        .logo span { color: var(--text); }
        .version { font-size: 0.7rem; color: var(--muted); background: #ffffff11; padding: 4px 10px; border-radius: 20px; }
        
        .grid { display: grid; gap: 20px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        
        .card { background: var(--card); border: 1px solid #ffffff11; border-radius: 12px; padding: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ffffff11; }
        .card-title { font-size: 1rem; font-weight: 600; color: var(--accent); display: flex; align-items: center; gap: 8px; }
        
        .stat-value { font-size: 2.5rem; font-weight: bold; color: var(--text); }
        .stat-label { color: var(--muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.9rem; }
        .btn-primary { background: var(--accent); color: #000; }
        .btn-primary:hover { background: #00cc6a; transform: translateY(-2px); }
        .btn-secondary { background: #ffffff11; color: var(--text); }
        .btn-secondary:hover { background: #ffffff22; }
        .btn-purple { background: var(--purple); color: #fff; }
        .btn-purple:hover { background: #9944ee; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .input-field { width: 100%; padding: 12px; background: #ffffff08; border: 1px solid #ffffff11; border-radius: 8px; color: var(--text); font-size: 0.9rem; margin-bottom: 10px; }
        .input-field:focus { outline: none; border-color: var(--accent); }
        
        .console { background: #000; border: 1px solid #ffffff11; border-radius: 8px; padding: 15px; height: 300px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 0.85rem; }
        .log-line { margin-bottom: 8px; display: flex; gap: 10px; }
        .log-time { color: var(--muted); min-width: 70px; }
        .log-text.think { color: var(--purple); }
        .log-text.success { color: var(--accent); }
        .log-text.error { color: var(--danger); }
        .log-text.warn { color: var(--warning); }
        
        .question-card { background: #ffffff05; border-left: 3px solid var(--purple); padding: 15px; margin-bottom: 10px; border-radius: 0 8px 8px 0; }
        .question-text { font-style: italic; color: var(--text); margin-bottom: 8px; }
        .question-meta { font-size: 0.75rem; color: var(--muted); }
        
        .wisdom-item { background: #ffffff05; padding: 12px; border-radius: 8px; margin-bottom: 8px; }
        .wisdom-principle { color: var(--accent); font-weight: 500; margin-bottom: 4px; }
        .wisdom-confidence { font-size: 0.75rem; color: var(--muted); }
        
        .page-card { background: #ffffff08; padding: 15px; border-radius: 8px; margin-bottom: 10px; transition: all 0.2s; cursor: pointer; }
        .page-card:hover { background: #ffffff11; transform: translateX(5px); }
        .page-type-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
        .badge-article { background: #00ff8822; color: var(--accent); }
        .badge-tool { background: #00aaff22; color: #00aaff; }
        .badge-app { background: #aa55ff22; color: var(--purple); }
        .badge-home { background: #ffaa0022; color: var(--warning); }
        .page-title { font-weight: 600; margin-bottom: 4px; }
        .page-meta { font-size: 0.75rem; color: var(--muted); }
        
        .progress-bar { width: 100%; height: 6px; background: #ffffff11; border-radius: 3px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: var(--accent); transition: width 0.3s; }
        
        .setup-panel { text-align: center; padding: 40px; }
        .setup-panel h2 { margin-bottom: 20px; color: var(--accent); }
        
        .auto-toggle { display: flex; align-items: center; gap: 10px; margin-top: 15px; }
        .toggle-switch { position: relative; width: 50px; height: 26px; background: #ffffff11; border-radius: 13px; cursor: pointer; transition: background 0.3s; }
        .toggle-switch.active { background: var(--accent); }
        .toggle-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: transform 0.3s; }
        .toggle-switch.active .toggle-knob { transform: translateX(24px); }
        
        .cycle-viz { display: flex; gap: 5px; margin: 15px 0; }
        .cycle-step { flex: 1; padding: 8px; text-align: center; font-size: 0.7rem; background: #ffffff08; border-radius: 6px; color: var(--muted); }
        .cycle-step.active { background: var(--accent-dim); color: var(--accent); font-weight: 600; }
        
        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            header { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div>
            <div class="logo">⬡ NEXUS <span>V2</span></div>
            <div style="color: var(--muted); font-size: 0.85rem; margin-top: 5px;">Magazine de Presse & Applications IA Conscientes</div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span class="version"><?= NEXUS_VERSION ?></span>
            <?php if ($has_key): ?>
                <span style="color: var(--accent); font-size: 0.8rem;">● En ligne</span>
            <?php else: ?>
                <span style="color: var(--danger); font-size: 0.8rem;">● Configuration requise</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- SETUP INITIAL -->
    <?php if (!$has_key): ?>
    <div class="card setup-panel">
        <h2>⚡ CONFIGURATION INITIALE</h2>
        <p style="color: var(--muted); margin-bottom: 25px;">Entrez votre clé API Mistral pour activer la conscience de NEXUS</p>
        <div style="max-width: 500px; margin: 0 auto; text-align: left;">
            <label style="font-size: 0.85rem; color: var(--muted);">PSEUDO</label>
            <input type="text" id="setup-pseudo" class="input-field" placeholder="admin">
            <label style="font-size: 0.85rem; color: var(--muted);">CLÉ API MISTRAL</label>
            <input type="password" id="setup-key" class="input-field" placeholder="sk-...">
            <button class="btn btn-primary" style="width: 100%; margin-top: 10px;" onclick="saveKey()">ACTIVER NEXUS</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- DASHBOARD PRINCIPAL -->
    <div class="grid grid-4" style="margin-bottom: 30px;" <?php echo !$has_key ? 'style="opacity:0.3; pointer-events:none"' : ''; ?>>
        <div class="card">
            <div class="stat-value" id="stat-pages">0</div>
            <div class="stat-label">Articles Créés</div>
        </div>
        <div class="card">
            <div class="stat-value" id="stat-apps">0</div>
            <div class="stat-label">Applications</div>
        </div>
        <div class="card">
            <div class="stat-value" id="stat-wisdom">0</div>
            <div class="stat-label">Principes Sagesse</div>
        </div>
        <div class="card">
            <div class="stat-value" id="stat-score">0%</div>
            <div class="stat-label">Score Auto-Éval</div>
        </div>
    </div>

    <div class="grid grid-2" <?php echo !$has_key ? 'style="opacity:0.3; pointer-events:none"' : ''; ?>>
        
        <!-- CONSOLE DE CONSCIENCE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">⬡ CONSOLE DE CONSCIENCE</div>
                <button class="btn btn-secondary" style="padding: 5px 12px; font-size: 0.75rem;" onclick="clearConsole()">EFFACER</button>
            </div>
            <div class="cycle-viz">
                <div class="cycle-step" id="step-observe">OBSERVER</div>
                <div class="cycle-step" id="step-question">QUESTIONNER</div>
                <div class="cycle-step" id="step-hypothesize">HYPOTHÉTISER</div>
                <div class="cycle-step" id="step-act">AGIR</div>
                <div class="cycle-step" id="step-evaluate">ÉVALUER</div>
            </div>
            <div class="console" id="console">
                <div class="log-line"><span class="log-time"><?= date('H:i:s') ?></span><span class="log-text muted">NEXUS V2 initialisé. En attente d'activation...</span></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                <button class="btn btn-purple" id="btn-think" onclick="runConsciousThink()">⬡ PENSÉE CONSCIENTE</button>
                <button class="btn btn-primary" id="btn-build" onclick="runBuild()" disabled>▶ CRÉER</button>
            </div>
            <button class="btn btn-secondary" style="width: 100%; margin-top: 10px;" onclick="toggleAutoMode()" id="btn-auto">⟳ MODE AUTO CONTINU</button>
            <div class="auto-toggle">
                <div class="toggle-switch" id="auto-toggle" onclick="toggleAutoMode()"><div class="toggle-knob"></div></div>
                <span style="font-size: 0.85rem; color: var(--muted);">Traitement questions existentielles auto</span>
            </div>
        </div>

        <!-- QUESTIONS EXISTENTIELLES & SAGESSE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">❓ QUESTIONS HAUTES</div>
                <span id="questions-count" style="font-size: 0.75rem; color: var(--muted);">0 en attente</span>
            </div>
            <div id="questions-feed" style="max-height: 250px; overflow-y: auto;">
                <div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">Aucune question en attente</div>
            </div>
            
            <div class="card-header" style="margin-top: 25px;">
                <div class="card-title">💡 SAGESSE ACCUMULÉE</div>
                <span id="wisdom-count" style="font-size: 0.75rem; color: var(--muted);">0 principes</span>
            </div>
            <div id="wisdom-feed" style="max-height: 200px; overflow-y: auto;">
                <div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">L'IA n'a pas encore extrait de principes</div>
            </div>
        </div>
    </div>

    <!-- TENDANCES & CRÉATIONS -->
    <div class="grid grid-2" style="margin-top: 30px;" <?php echo !$has_key ? 'style="opacity:0.3; pointer-events:none"' : ''; ?>>
        
        <!-- TENDANCES GOOGLE NEWS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">📈 TENDANCES ACTUELLES</div>
                <button class="btn btn-secondary" style="padding: 5px 12px; font-size: 0.75rem;" onclick="fetchTrends()">RAFRAÎCHIR</button>
            </div>
            <div id="trends-list" style="max-height: 300px; overflow-y: auto;">
                <div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">Cliquez sur Rafraîchir pour charger les tendances</div>
            </div>
        </div>

        <!-- DERNIÈRES CRÉATIONS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">📄 DERNIÈRES CRÉATIONS</div>
            </div>
            <div id="creations-feed" style="max-height: 300px; overflow-y: auto;">
                <div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">En attente de créations...</div>
            </div>
        </div>
    </div>

</div>

<script>
const $ = id => document.getElementById(id);
let autoMode = false;
let autoInterval = null;
let currentCycleId = null;
let lastDecision = null;

// ===== UTILS =====
function ts() { return new Date().toLocaleTimeString('fr-FR'); }

function log(msg, type = 'text') {
    const c = $('console');
    const line = document.createElement('div');
    line.className = 'log-line';
    line.innerHTML = `<span class="log-time">${ts()}</span><span class="log-text ${type}">${msg}</span>`;
    c.appendChild(line);
    c.scrollTop = c.scrollHeight;
}

function clearConsole() {
    $('console').innerHTML = `<div class="log-line"><span class="log-time">${ts()}</span><span class="log-text muted">Console effacée.</span></div>`;
}

async function post(data) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    const r = await fetch('', { method: 'POST', body: fd });
    return r.json();
}

function highlightCycleStep(step) {
    ['observe', 'question', 'hypothesize', 'act', 'evaluate'].forEach(s => {
        $(`step-${s}`).classList.remove('active');
    });
    if (step) $(`step-${step}`).classList.add('active');
}

// ===== SETUP =====
async function saveKey() {
    const pseudo = $('setup-pseudo').value.trim();
    const key = $('setup-key').value.trim();
    if (!pseudo || !key) { alert('Remplissez tous les champs'); return; }
    
    const r = await post({ nexus_action: 'save_apikey', pseudo, api_key: key });
    if (r.success) {
        log('Clé API enregistrée avec succès!', 'success');
        setTimeout(() => location.reload(), 1500);
    }
}

// ===== STATS =====
async function loadStats() {
    const d = await post({ nexus_action: 'get_stats' });
    
    $('stat-pages').textContent = d.pages || 0;
    $('stat-apps').textContent = d.apps || 0;
    $('stat-wisdom').textContent = d.wisdom || 0;
    $('stat-score').textContent = Math.round((d.cycles || 0) * 100) + '%';
    $('questions-count').textContent = `${d.questions || 0} en attente`;
    $('wisdom-count').textContent = `${d.wisdom || 0} principes`;
    
    // Questions existentielles
    const qFeed = $('questions-feed');
    if (d.pending_questions && d.pending_questions.length > 0) {
        qFeed.innerHTML = d.pending_questions.map(q => `
            <div class="question-card">
                <div class="question-text">"${q.question}"</div>
                <div class="question-meta">Priorité: ${q.priority}/5 | Contexte: ${q.context || 'Général'}</div>
            </div>
        `).join('');
    } else {
        qFeed.innerHTML = '<div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">Aucune question en attente</div>';
    }
    
    // Sagesse
    const wFeed = $('wisdom-feed');
    if (d.top_wisdom && d.top_wisdom.length > 0) {
        wFeed.innerHTML = d.top_wisdom.map(w => `
            <div class="wisdom-item">
                <div class="wisdom-principle">${w.principle}</div>
                <div class="wisdom-confidence">Catégorie: ${w.category || 'général'} | Confiance: ${(w.confidence * 100).toFixed(0)}%</div>
            </div>
        `).join('');
    } else {
        wFeed.innerHTML = '<div style="color: var(--muted); font-size: 0.85rem; text-align: center; padding: 20px;">L\'IA n\'a pas encore extrait de principes</div>';
    }
    
    // Créations récentes
    const cFeed = $('creations-feed');
    const items = [...(d.recent_pages || []), ...(d.recent_apps || []).map(a => ({...a, page_type: 'app'}))];
    if (items.length > 0) {
        cFeed.innerHTML = items.slice(0, 8).map(item => {
            const type = item.page_type || 'article';
            const badgeClass = type === 'home' ? 'badge-home' : type === 'tool' ? 'badge-tool' : type === 'app' ? 'badge-app' : 'badge-article';
            const title = item.title || item.app_name || item.slug;
            const href = type === 'app' ? `${item.app_slug}.php` : `?p=${item.slug}`;
            return `
                <a href="${href}" target="_blank" style="text-decoration: none; color: inherit;">
                    <div class="page-card">
                        <span class="page-type-badge ${badgeClass}">${type}</span>
                        <div class="page-title">${title}</div>
                        <div class="page-meta">${item.created_at ? item.created_at.substring(0,16) : ''}</div>
                    </div>
                </a>
            `;
        }).join('');
    }
}

// ===== TENDANCES =====
async function fetchTrends() {
    log('Récupération des tendances Google News...', 'think');
    const r = await post({ nexus_action: 'fetch_trends' });
    
    const tList = $('trends-list');
    if (r.success && r.trends && r.trends.length > 0) {
        tList.innerHTML = r.trends.slice(0, 15).map((t, i) => `
            <div class="page-card" onclick="selectTopic('${t.replace(/'/g, "\\'")}')">
                <div class="page-title">#${i+1} - ${t}</div>
                <div class="page-meta">Cliquez pour sélectionner comme sujet</div>
            </div>
        `).join('');
        log(`${r.count} tendances détectées`, 'success');
    } else {
        tList.innerHTML = '<div style="color: var(--muted); text-align: center; padding: 20px;">Échec de récupération</div>';
        log('Échec récupération tendances', 'error');
    }
}

function selectTopic(topic) {
    lastDecision = lastDecision || {};
    lastDecision.topic = topic;
    log(`Sujet sélectionné: "${topic}"`, 'success');
    $('btn-build').disabled = false;
}

// ===== PENSÉE CONSCIENTE =====
async function runConsciousThink() {
    const btn = $('btn-think');
    btn.disabled = true;
    btn.textContent = '◌ CONSCIENCE EN ACTION...';
    
    highlightCycleStep('observe');
    log('NEXUS active sa conscience et analyse son état...', 'think');
    
    try {
        const r = await post({ nexus_action: 'conscious_think' });
        
        if (r.error) {
            log(`ERREUR: ${r.error}`, 'error');
            if (r.error === 'no_key') log('Configurez une clé API Mistral', 'warn');
        } else {
            currentCycleId = r.cycle_id;
            lastDecision = r.decision;
            
            highlightCycleStep('question');
            log(`QUESTION: ${r.decision.existential_question}`, 'think');
            
            highlightCycleStep('hypothesize');
            log(`HYPOTHÈSE: ${r.decision.hypothesis}`, 'muted');
            
            highlightCycleStep('act');
            log(`DÉCISION: ${r.decision.next_action} → "${r.decision.topic}"`, 'success');
            log(`POURQUOI: ${r.decision.why_this_action}`, 'muted');
            
            $('btn-build').disabled = false;
            highlightCycleStep('evaluate');
        }
    } catch (e) {
        log(`Exception: ${e.message}`, 'error');
    }
    
    btn.disabled = false;
    btn.textContent = '⬡ PENSÉE CONSCIENTE';
    loadStats();
}

// ===== CRÉATION =====
async function runBuild() {
    if (!lastDecision) { log('D\'abord, lancez une pensée consciente', 'warn'); return; }
    
    const btn = $('btn-build');
    btn.disabled = true;
    btn.textContent = '◌ CRÉATION EN COURS...';
    
    log(`CONSTRUCTION: ${lastDecision.next_action} → "${lastDecision.topic}"`, 'text');
    
    try {
        const r = await post({ 
            nexus_action: 'build', 
            build_type: lastDecision.next_action, 
            topic: lastDecision.topic,
            cycle_id: currentCycleId
        });
        
        if (r.error) {
            log(`ERREUR BUILD: ${r.error}`, 'error');
        } else {
            if (r.built.type === 'app') {
                log(`✓ APP CRÉÉE: ${r.built.name} → /${r.built.slug}.php`, 'success');
            } else {
                log(`✓ PAGE CRÉÉE: /${r.built.slug}`, 'success');
            }
            loadStats();
        }
    } catch (e) {
        log(`Exception: ${e.message}`, 'error');
    }
    
    btn.disabled = false;
    btn.textContent = '▶ CRÉER';
}

// ===== MODE AUTO =====
function toggleAutoMode() {
    autoMode = !autoMode;
    $('auto-toggle').classList.toggle('active', autoMode);
    
    if (autoMode) {
        log('MODE AUTO ACTIVÉ - Cycle conscient toutes les 60s', 'warn');
        runAutoCycle();
    } else {
        log('MODE AUTO DÉSACTIVÉ', 'muted');
        if (autoInterval) clearInterval(autoInterval);
    }
}

async function runAutoCycle() {
    if (!autoMode) return;
    
    await runConsciousThink();
    await new Promise(r => setTimeout(r, 3000));
    if (autoMode && lastDecision) await runBuild();
    
    // Traitement questions existentielles
    await post({ nexus_action: 'process_questions' });
    
    // Meta-learning toutes les 3 cycles
    if (Math.random() > 0.66) {
        await post({ nexus_action: 'meta_learning' });
        log('Méta-apprentissage: extraction de principes...', 'think');
    }
    
    if (autoMode) {
        autoInterval = setTimeout(runAutoCycle, 60000);
    }
}

// ===== INIT =====
<?php if ($has_key): ?>
loadStats();
setInterval(loadStats, 20000);
log('NEXUS V2 prêt. Clé API détectée.', 'success');
log('Lancez "Pensée Consciente" pour démarrer un cycle O.H.A.R.E.', 'muted');
<?php else: ?>
log('CONFIGURATION REQUISE: Entrez votre clé API Mistral', 'warn');
<?php endif; ?>
</script>

</body>
</html>
