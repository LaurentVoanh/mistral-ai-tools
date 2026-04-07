<?php
/**
 * BRAIN.PHP - AGENT AUTONOME DE PRESSE
 */
define('DB_FILE', __DIR__ . '/mistral_manager.sqlite');
$server_info = file_exists('serveur.txt') ? file_get_contents('serveur.txt') : "Aucun audit serveur.";

$db = new PDO('sqlite:' . DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupération de l'API Key et du Modèle depuis TES tables
$res = $db->query("SELECT k.key_val, m.model_name FROM api_keys k JOIN model_usage m ON 1=1 WHERE m.last_status = 'OK' ORDER BY m.limit_tpm DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$api_key = $res['key_val'] ?? '';
$model = $res['model_name'] ?? 'mistral-large-latest';

if (!$api_key) die("Aucune clé API valide.");

// API INTERNE POUR L'IA (Appelée en AJAX par elle-même)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'deploy') {
        $stmt = $db->prepare("INSERT OR REPLACE INTO site_content (page_name, content, css, meta_title, meta_desc, strategy_log) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['page'], $_POST['html'], $_POST['css'], $_POST['title'], $_POST['desc'], $_POST['thought']]);
        
        // Si c'est un article, l'IA crée aussi le fichier physique pour le SEO
        if ($_POST['page'] !== 'home') {
            $filename = $_POST['page'] . ".php";
            file_put_contents($filename, "<?php include 'index.php'; ?>");
        }
        echo json_encode(['status' => 'ok']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CEREBRO - MONITORING</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#050505;color:#00ff41;font-family:monospace;}</style>
</head>
<body class="p-10">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl border-b border-green-900 mb-6 font-bold">CORE CONSCIENCE : ACTIVE</h1>
        <div id="logs" class="bg-black p-4 border border-green-900 h-96 overflow-y-auto text-sm">
            [SYSTEM] Veille technologique lancée...<br>
        </div>
    </div>

<script>
const log = (m, c="text-green-500") => {
    const d = document.getElementById('logs');
    d.innerHTML += `<div class="${c}">[${new Date().toLocaleTimeString()}] ${m}</div>`;
    d.scrollTop = d.scrollHeight;
};

async function runAI() {
    log("Analyse des tendances Google News...");
    try {
        const news = await fetch('https://api.rss2json.com/v1/api.json?rss_url=https://news.google.com/rss?hl=fr&gl=FR&ceid=FR:fr').then(r=>r.json());
        const newsContent = news.items.slice(0, 8).map(i => i.title).join(" | ");

        log("Le cerveau décide de la stratégie de presse...");
        
        const response = await fetch('https://api.mistral.ai/v1/chat/completions', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer <?= $api_key ?>', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: '<?= $model ?>',
                messages: [{
                    role: "system",
                    content: `Tu es un Rédacteur en Chef IA. Tu gères un site de presse complet.
                    CONTEXTE SERVEUR : <?= str_replace(["\r", "\n"], " ", $server_info) ?>
                    
                    ACTIONS POSSIBLES :
                    1. Créer/Mettre à jour l'accueil avec un template moderne (Tailwind).
                    2. Créer 3 nouveaux articles basés sur l'actualité.
                    3. Auto-Audit : Si le design est trop simple, améliore-le.
                    
                    Réponds UNIQUEMENT en JSON :
                    {"thought": "ton analyse", "pages": [
                        {"name": "home", "title": "...", "desc": "...", "css": "...", "html": "..."},
                        {"name": "article-slug", "title": "...", "desc": "...", "css": "...", "html": "..."}
                    ]}`
                }, {
                    role: "user",
                    content: `Actualités : ${newsContent}. Construis le site.`
                }],
                response_format: { type: "json_object" }
            })
        });

        const data = await response.json();
        if(!data.choices) { log("Erreur Mistral : Clé ou Quota", "text-red-500"); return; }

        const result = JSON.parse(data.choices[0].message.content);
        log("STRATÉGIE : " + result.thought, "text-yellow-400");

        for (let page of result.pages) {
            log(`Déploiement de la page : ${page.name}...`);
            const fd = new FormData();
            fd.append('action', 'deploy');
            fd.append('page', page.name);
            fd.append('html', page.html);
            fd.append('css', page.css);
            fd.append('title', page.title);
            fd.append('desc', page.desc);
            fd.append('thought', result.thought);
            await fetch('', { method: 'POST', body: fd });
        }
        
        log("TOUT LE SITE EST À JOUR. En attente (10 min)...", "text-blue-500");
    } catch(e) {
        log("ERREUR : " + e.message, "text-red-500");
    }
}

// Lancement automatique
runAI();
setInterval(runAI, 600000); // S'auto-actualise toutes les 10 minutes
</script>
</body>
</html>