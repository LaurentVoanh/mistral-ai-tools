<?php
define('DB_FILE', __DIR__ . '/mistral_manager.sqlite');
$server_info = file_exists('serveur.txt') ? file_get_contents('serveur.txt') : "Config Hostinger standard.";

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialisation de la table de contenu si absente
    $db->exec("CREATE TABLE IF NOT EXISTS site_content (
        page_name TEXT PRIMARY KEY, 
        content TEXT, 
        css TEXT, 
        meta_title TEXT, 
        meta_desc TEXT, 
        strategy_log TEXT
    )");

    // On récupère la clé API la plus puissante depuis tes tables
    $res = $db->query("SELECT k.key_val, m.model_name 
                       FROM api_keys k 
                       JOIN model_usage m ON 1=1 
                       WHERE m.last_status = 'OK' 
                       ORDER BY m.limit_tpm DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $api_key = $res['key_val'] ?? '';
    $model = $res['model_name'] ?? 'mistral-large-latest';

    // Affichage de la page demandée
    $page_request = $_GET['p'] ?? 'home';
    $stmt = $db->prepare("SELECT * FROM site_content WHERE page_name = ?");
    $stmt->execute([$page_request]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) { die("Erreur BDD : " . $e->getMessage()); }

// --- LOGIQUE DE SAUVEGARDE AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'ai_deploy') {
        $stmt = $db->prepare("INSERT OR REPLACE INTO site_content (page_name, content, css, meta_title, meta_desc, strategy_log) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['page'], $_POST['html'], $_POST['css'], $_POST['title'], $_POST['desc'], $_POST['thought']]);
        echo json_encode(['status' => 'success']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $page ? htmlspecialchars($page['meta_title']) : "OPENCLAW - Génération..." ?></title>
    <meta name="description" content="<?= $page ? htmlspecialchars($page['meta_desc']) : "" ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <style><?= $page['css'] ?? "" ?></style>
</head>
<body class="bg-[#050505] text-white font-sans">

    <div id="app-view">
        <?php if (!$page): ?>
            <div id="loader" class="flex flex-col items-center justify-center h-screen">
                <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                <h1 class="text-2xl font-black mt-6 tracking-widest text-blue-500">OPENCLAW EVOLUTION</h1>
                <p id="ai-status" class="text-gray-500 mt-2 font-mono text-sm italic">Le cerveau s'éveille...</p>
                <div id="mini-logs" class="mt-8 text-[10px] text-gray-700 font-mono w-96 text-center"></div>
            </div>
        <?php else: ?>
            <?= $page['content'] ?>
        <?php endif; ?>
    </div>

<script>
/**
 * LE CERVEAU INTELLIGENT (CORE JS)
 */
async function triggerBrain() {
    const logArea = document.getElementById('mini-logs');
    const statusText = document.getElementById('ai-status');
    const updateLog = (m) => { if(logArea) logArea.innerHTML = m; if(statusText) statusText.innerText = m; };

    try {
        updateLog("Analyse des actualités mondiales...");
        const newsRes = await fetch('https://api.rss2json.com/v1/api.json?rss_url=https://news.google.com/rss?hl=fr&gl=FR&ceid=FR:fr');
        const newsData = await newsRes.json();
        const newsTitles = newsData.items.slice(0, 7).map(i => i.title).join(" | ");

        updateLog("L'IA conçoit le template et les articles...");
        
        const response = await fetch('https://api.mistral.ai/v1/chat/completions', {
            method: 'POST',
            headers: { 
                'Authorization': 'Bearer <?= $api_key ?>', 
                'Content-Type': 'application/json' 
            },
            body: JSON.stringify({
                model: '<?= $model ?>',
                messages: [{
                    role: "system",
                    content: `Tu es OPENCLAW, une IA Webmaster. Tu gères un site de presse complet.
                    CONTEXTE SERVEUR : <?= str_replace(["\r", "\n", "'"], " ", $server_info) ?>
                    
                    CONSIGNE : 
                    1. Crée un portail de presse avec une navigation, un hero section et des cartes d'articles.
                    2. Utilise Tailwind CSS pour un look moderne et "High Tech".
                    3. Rédige 3 articles courts basés sur les news.
                    
                    Réponds UNIQUEMENT en JSON :
                    {"thought": "...", "title": "...", "desc": "...", "css": "...", "html": "..."}`
                }, {
                    role: "user",
                    content: `Actualités : ${newsTitles}. Code le site complet maintenant.`
                }],
                response_format: { type: "json_object" }
            })
        });

        const data = await response.json();
        if (!data.choices) {
            updateLog("ERREUR : Clé API invalide ou quota épuisé.");
            return;
        }

        const result = JSON.parse(data.choices[0].message.content);
        updateLog("Déploiement du code généré...");

        // Envoi à PHP pour stockage
        const fd = new FormData();
        fd.append('action', 'ai_deploy');
        fd.append('page', 'home');
        fd.append('html', result.html);
        fd.append('css', result.css);
        fd.append('title', result.title);
        fd.append('desc', result.desc);
        fd.append('thought', result.thought);

        await fetch('', { method: 'POST', body: fd });
        
        updateLog("Activation du nouveau site...");
        setTimeout(() => location.reload(), 1000);

    } catch (e) {
        updateLog("Erreur critique : " + e.message);
    }
}

// On lance le cerveau si la page est vide (ou toutes les X minutes)
<?php if (!$page): ?>
triggerBrain();
<?php else: ?>
// Optionnel : L'IA se réveille en tâche de fond pour mettre à jour les news
setTimeout(() => triggerBrain(), 600000); 
<?php endif; ?>
</script>
</body>
</html>
