<?php
define('DB_FILE', __DIR__ . '/mistral_manager.sqlite');

// --- INITIALISATION BDD ---
function getDB() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Table des clés API (Pseudo + Clé)
    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT,
        key_val TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Table de suivi des modèles (Quotas et Résultats)
    $db->exec("CREATE TABLE IF NOT EXISTS model_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name TEXT UNIQUE,
        limit_tpm INTEGER,
        limit_mpm TEXT,
        limit_rps REAL,
        used_tokens_session INTEGER DEFAULT 0,
        last_status TEXT,
        last_tested DATETIME
    )");
    return $db;
}

// --- TRAITEMENT DES REQUÊTES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $db = getDB();

    if ($_POST['action'] === 'add_key') {
        $stmt = $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?, ?)");
        $stmt->execute([$_POST['pseudo'], $_POST['key']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'get_data') {
        $keys = $db->query("SELECT * FROM api_keys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach($keys as &$k) { 
            $k['key_masked'] = substr($k['key_val'], 0, 6) . '••••' . substr($k['key_val'], -4); 
        }
        $models = $db->query("SELECT * FROM model_usage ORDER BY model_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['keys' => $keys, 'models' => $models]);
        exit;
    }

    if ($_POST['action'] === 'test_model') {
        $model = $_POST['model'];
        $key = $_POST['key'];
        
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'messages' => [['role'=>'user','content'=>'ok']], 'max_tokens'=>2]),
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res_data = json_decode($response, true);
        $tokens = $res_data['usage']['total_tokens'] ?? 0;

        $stmt = $db->prepare("INSERT INTO model_usage (model_name, limit_tpm, limit_mpm, limit_rps, used_tokens_session, last_status, last_tested) 
            VALUES (:name, :tpm, :mpm, :rps, :used, :stat, CURRENT_TIMESTAMP)
            ON CONFLICT(model_name) DO UPDATE SET 
            used_tokens_session = used_tokens_session + :used, 
            last_status = :stat, 
            last_tested = CURRENT_TIMESTAMP");
        
        $stmt->execute([
            ':name' => $model,
            ':tpm'  => (int)$_POST['limit_tpm'],
            ':mpm'  => $_POST['limit_mpm'],
            ':rps'  => (float)$_POST['limit_rps'],
            ':used' => $tokens,
            ':stat' => ($code === 200 ? 'OK' : 'Err '.$code)
        ]);

        echo json_encode(['status' => $code, 'used' => $tokens]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mistral Full Model & Quota Manager</title>
    <style>
        :root { --accent: #00A8E1; --bg: #121212; --card: #1e1e1e; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: #e0e0e0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        .card { background: var(--card); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; }
        th { background: #252525; color: var(--accent); }
        .btn { background: var(--accent); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn:disabled { background: #444; cursor: not-allowed; }
        input { padding: 8px; background: #2d2d2d; border: 1px solid #444; color: white; border-radius: 5px; margin-right: 10px; }
        .status-ok { color: #00ff41; font-weight: bold; }
        .status-err { color: #ff5555; }
        .progress-container { width: 100%; background: #333; height: 10px; border-radius: 5px; margin: 10px 0; display: none; }
        #progress-bar { width: 0%; height: 100%; background: var(--accent); border-radius: 5px; transition: width 0.3s; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>🔑 Ajouter une clé API</h2>
        <input type="text" id="pseudo" placeholder="Pseudo (ex: Admin)">
        <input type="password" id="new_key" placeholder="Clé API Mistral (32 chars)">
        <button class="btn" onclick="addKey()">Enregistrer la clé</button>
    </div>

    <div class="card">
        <h3>Clés enregistrées</h3>
        <table id="keysTable">
            <thead><tr><th>Pseudo</th><th>Clé</th><th>Action</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="card">
        <h3>🚀 État des Modèles (TPM / MPM / RPS)</h3>
        <div id="test-info" style="margin-bottom: 10px; color: var(--accent);"></div>
        <div class="progress-container" id="p-cont"><div id="progress-bar"></div></div>
        <table id="modelsTable">
            <thead>
                <tr>
                    <th>Modèle</th>
                    <th>Statut</th>
                    <th>Limite TPM</th>
                    <th>MPM</th>
                    <th>RPS</th>
                    <th>Tokens Session</th>
                    <th>Dernier Test</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
// --- LISTE COMPLÈTE DES MODÈLES ---
const allModels = [
    {name: "codestral-2508", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "codestral-embed", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "devstral-2512", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "devstral-medium-2507", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "devstral-small-2507", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "labs-leanstral-2603", tpm: 5000000, mpm: "-", rps: 0.63},
    {name: "labs-mistral-small-creative", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "magistral-medium-2509", tpm: 75000, mpm: "1B", rps: 0.08},
    {name: "magistral-small-2509", tpm: 75000, mpm: "1B", rps: 0.08},
    {name: "ministral-14b-2512", tpm: 50000, mpm: "4M", rps: 0.50},
    {name: "ministral-3b-2512", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "ministral-8b-2512", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-embed-2312", tpm: 20000000, mpm: "200B", rps: 1.00},
    {name: "mistral-large-2411", tpm: 600000, mpm: "200B", rps: 1.00},
    {name: "mistral-large-2512", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-medium-2505", tpm: 375000, mpm: "-", rps: 0.42},
    {name: "mistral-medium-2508", tpm: 375000, mpm: "-", rps: 0.42},
    {name: "mistral-moderation-2411", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-moderation-2603", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-ocr-2505", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-ocr-2512", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-small-2506", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "mistral-small-2603", tpm: 375000, mpm: "-", rps: 1.00},
    {name: "open-mistral-nemo", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "pixtral-large-2411", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "pixtral-12b-2409", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-mini-2507", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-mini-2602", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-mini-transcribe-2507", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-mini-transcribe-realtime-2602", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-mini-tts-2603", tpm: 50000, mpm: "4M", rps: 1.00},
    {name: "voxtral-small-2507", tpm: 50000, mpm: "4M", rps: 1.00}
];

async function addKey() {
    const pseudo = document.getElementById('pseudo').value;
    const key = document.getElementById('new_key').value;
    if(!pseudo || !key) return;
    const fd = new FormData();
    fd.append('action', 'add_key');
    fd.append('pseudo', pseudo);
    fd.append('key', key);
    await fetch('', {method: 'POST', body: fd});
    location.reload();
}

async function loadData() {
    const fd = new FormData();
    fd.append('action', 'get_data');
    const res = await fetch('', {method: 'POST', body: fd});
    const data = await res.json();

    const kt = document.querySelector('#keysTable tbody');
    kt.innerHTML = data.keys.map(k => `
        <tr>
            <td>${k.pseudo}</td>
            <td><code>${k.key_masked}</code></td>
            <td><button class="btn" onclick="testAllModels('${k.key_val}')">Lancer Scan Complet</button></td>
        </tr>
    `).join('');

    const mt = document.querySelector('#modelsTable tbody');
    mt.innerHTML = data.models.map(m => `
        <tr>
            <td><strong>${m.model_name}</strong></td>
            <td class="${m.last_status === 'OK' ? 'status-ok' : 'status-err'}">${m.last_status || '-'}</td>
            <td>${m.limit_tpm}</td>
            <td>${m.limit_mpm}</td>
            <td>${m.limit_rps}</td>
            <td>${m.used_tokens_session}</td>
            <td>${m.last_tested || 'Jamais'}</td>
        </tr>
    `).join('');
}

async function testAllModels(key) {
    const btns = document.querySelectorAll('.btn');
    btns.forEach(b => b.disabled = true);
    document.getElementById('p-cont').style.display = 'block';
    
    let current = 0;
    for (const m of allModels) {
        current++;
        let progress = (current / allModels.length) * 100;
        document.getElementById('progress-bar').style.width = progress + '%';
        document.getElementById('test-info').innerText = `Scan : ${m.name} (${current}/${allModels.length})`;

        const fd = new FormData();
        fd.append('action', 'test_model');
        fd.append('key', key);
        fd.append('model', m.name);
        fd.append('limit_tpm', m.tpm);
        fd.append('limit_mpm', m.mpm);
        fd.append('limit_rps', m.rps);

        try {
            await fetch('', {method: 'POST', body: fd});
        } catch(e) {}

        // Pause de 1.5 seconde entre chaque modèle pour préserver le RPS
        await new Promise(r => setTimeout(r, 1500));
        loadData();
    }
    
    document.getElementById('test-info').innerText = "✅ Scan terminé.";
    btns.forEach(b => b.disabled = false);
}

loadData();
</script>
</body>
</html>