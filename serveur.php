<?php
/**
 * AUDIT SERVEUR COMPLET POUR AGENT IA AUTONOME
 * Ce script génère le contexte d'exécution strict pour une IA génératrice de code.
 */

// --- 1. SYSTÈME ET ENVIRONNEMENT ---
$os = php_uname('s') . ' ' . php_uname('r');
$sapi = php_sapi_name();
$php_version = phpversion();
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu';
$user = get_current_user();

// --- 2. DROITS DU SYSTÈME DE FICHIERS (Crucial pour un auto-codeur) ---
$current_dir = __DIR__;
$is_writable = is_writable($current_dir) ? "OUI" : "NON";
$is_readable = is_readable($current_dir) ? "OUI" : "NON";
$free_space = function_exists('disk_free_space') ? round(disk_free_space($current_dir) / (1024 * 1024 * 1024), 2) . ' GB' : 'Inconnu';

// --- 3. LIMITES DE RESSOURCES ET INI ---
$ini_settings = [
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_input_vars' => ini_get('max_input_vars'),
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Activé' : 'Désactivé',
    'allow_url_include' => ini_get('allow_url_include') ? 'Activé' : 'Désactivé',
    'display_errors' => ini_get('display_errors') ? 'Activé' : 'Désactivé',
    'open_basedir' => ini_get('open_basedir') ?: 'Aucune restriction',
];

// --- 4. ANALYSE SÉCURITAIRE (disable_functions & disable_classes) ---
$raw_disabled_funcs = ini_get('disable_functions');
$disabled_functions = $raw_disabled_funcs ? array_map('trim', explode(',', $raw_disabled_funcs)) : [];
$raw_disabled_classes = ini_get('disable_classes');
$disabled_classes = $raw_disabled_classes ? array_map('trim', explode(',', $raw_disabled_classes)) : [];

// --- 5. INVENTAIRE EXHAUSTIF DES EXTENSIONS ---
$loaded_extensions = get_loaded_extensions();
natcasesort($loaded_extensions);

// Catégorisation pour l'IA (pour qu'elle comprenne CE QU'ELLE PEUT FAIRE)
$capabilities = [
    'Réseau & API' => ['curl', 'sockets', 'stream', 'soap', 'ftp'],
    'Bases de données' => ['pdo', 'pdo_mysql', 'pdo_sqlite', 'mysqli', 'sqlite3', 'redis', 'mongodb'],
    'Manipulation Données' => ['json', 'xml', 'simplexml', 'dom', 'yaml', 'mbstring', 'iconv'],
    'Sécurité & Crypto' => ['openssl', 'sodium', 'hash', 'filter'],
    'Fichiers & Compression' => ['zip', 'zlib', 'bz2', 'phar', 'fileinfo'],
    'Images & Médias' => ['gd', 'imagick', 'exif']
];

$ai_capabilities_report = "";
foreach ($capabilities as $category => $exts) {
    $active = array_intersect($exts, $loaded_extensions);
    $missing = array_diff($exts, $loaded_extensions);
    $ai_capabilities_report .= "  - $category : " . (empty($active) ? "AUCUNE" : implode(', ', $active)) . "\n";
    if (!empty($missing)) {
        $ai_capabilities_report .= "    [Attention: manquent " . implode(', ', $missing) . "]\n";
    }
}

// --- 6. CONSTRUCTION DU SYSTEM PROMPT POUR L'AGENT ---
$prompt = "Tu es un Agent IA Autonome de développement web (Auto-Codeur). Tu vas écrire, déployer et exécuter du code PHP de manière autonome. " .
          "Pour éviter les crashs fatals et les boucles d'erreurs, tu dois STRICTEMENT adapter ton code à l'environnement d'exécution réel scanné ci-dessous.\n\n";

$prompt .= "### 1. ENVIRONNEMENT SYSTÈME\n";
$prompt .= "- OS : $os\n";
$prompt .= "- Serveur Web : $server_software\n";
$prompt .= "- Interface (SAPI) : $sapi\n";
$prompt .= "- Version PHP : $php_version\n";
$prompt .= "- Utilisateur d'exécution : $user\n\n";

$prompt .= "### 2. SYSTÈME DE FICHIERS (Droits I/O)\n";
$prompt .= "- Chemin de travail courant (__DIR__) : $current_dir\n";
$prompt .= "- Droit de LECTURE : $is_readable\n";
$prompt .= "- Droit d'ÉCRITURE (Création de fichiers autorisée) : $is_writable\n";
$prompt .= "- Espace disque disponible : $free_space\n";
$prompt .= "- Restriction d'arborescence (open_basedir) : {$ini_settings['open_basedir']}\n";
$prompt .= "RÈGLE I/O : Ne tente jamais d'écrire en dehors du périmètre autorisé par open_basedir ou si l'écriture est refusée.\n\n";

$prompt .= "### 3. LIMITES DE RESSOURCES (Hard Limits)\n";
foreach ($ini_settings as $key => $val) {
    if ($key !== 'open_basedir') $prompt .= "- $key : $val\n";
}
$prompt .= "RÈGLE PERFORMANCES : Ton code doit inclure des mécanismes de pagination, de batching ou d'exécution asynchrone s'il risque d'atteindre le memory_limit ou le max_execution_time.\n\n";

$prompt .= "### 4. SÉCURITÉ ET BLOCAGES ACTIFS\n";
$prompt .= "- Fonctions PHP DÉSACTIVÉES (Ne jamais les utiliser) : \n  [" . ($disabled_functions ? implode(', ', $disabled_functions) : 'Aucune') . "]\n";
$prompt .= "- Classes PHP DÉSACTIVÉES : \n  [" . ($disabled_classes ? implode(', ', $disabled_classes) : 'Aucune') . "]\n";
$prompt .= "RÈGLE SÉCURITÉ : Chez Hostinger, les fonctions d'exécution système (exec, shell_exec, system, passthru) sont souvent bloquées. Trouve toujours une alternative native en PHP.\n\n";

$prompt .= "### 5. CAPACITÉS ET EXTENSIONS DISPONIBLES\n";
$prompt .= "Voici ce que ton moteur PHP est capable de faire nativement :\n";
$prompt .= $ai_capabilities_report . "\n";
$prompt .= "Toutes les extensions chargées (" . count($loaded_extensions) . ") : " . implode(', ', $loaded_extensions) . ".\n\n";

$prompt .= "### 6. DIRECTIVES OPÉRATIONNELLES POUR L'AUTO-CODEUR\n";
$prompt .= "1. Vérifie l'existence des fonctions avant usage (ex: `if (function_exists('curl_init'))`).\n";
$prompt .= "2. Gère toutes les exceptions avec des blocs try/catch pour éviter de faire planter ton propre processus de déploiement.\n";
$prompt .= "3. Si tu dois communiquer avec des API externes, utilise de préférence cURL si listé dans les capacités, sinon file_get_contents si allow_url_fopen est Activé.\n";
$prompt .= "4. Formate tes réponses uniquement en code valide, prêt à être écrit sur le disque dans cet environnement précis.";

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Audit Serveur Avancé pour IA</title>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --text: #c9d1d9; --accent: #58a6ff; --border: #30363d; --danger: #f85149; --success: #2ea043; }
        body { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace; background-color: var(--bg); color: var(--text); padding: 20px; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1, h2, h3 { color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: 6px; }
        .alert { color: var(--danger); font-weight: bold; }
        .ok { color: var(--success); font-weight: bold; }
        .prompt-box { background: #000; padding: 20px; border: 1px solid var(--border); border-radius: 6px; white-space: pre-wrap; font-size: 0.9em; overflow-x: auto; color: #a5d6ff; }
        button { background: var(--success); color: white; border: none; padding: 12px 24px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; display: block; margin: 20px 0; font-family: inherit; }
        button:hover { background: #238636; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Terminal d'Audit : Initialisation Agent Autonome</h1>
    
    <div class="grid">
        <div class="card">
            <h3>I/O & Fichiers</h3>
            <ul>
                <li>Chemin : <code><?php echo $current_dir; ?></code></li>
                <li>Écriture IA : <span class="<?php echo is_writable($current_dir) ? 'ok' : 'alert'; ?>"><?php echo $is_writable; ?></span></li>
                <li>Espace Disque : <strong><?php echo $free_space; ?></strong></li>
            </ul>
        </div>
        <div class="card">
            <h3>Performances</h3>
            <ul>
                <li>Mémoire Max : <strong><?php echo $ini_settings['memory_limit']; ?></strong></li>
                <li>Temps Max : <strong><?php echo $ini_settings['max_execution_time']; ?>s</strong></li>
                <li>Upload Max : <strong><?php echo $ini_settings['upload_max_filesize']; ?></strong></li>
            </ul>
        </div>
        <div class="card">
            <h3>Sécurité (Hostinger)</h3>
            <ul>
                <li>Fonctions bloquées : <strong><?php echo count($disabled_functions); ?></strong></li>
                <li>Open Basedir : <span class="<?php echo $ini_settings['open_basedir'] !== 'Aucune restriction' ? 'alert' : 'ok'; ?>"><?php echo $ini_settings['open_basedir'] !== 'Aucune restriction' ? 'Actif' : 'Désactivé'; ?></span></li>
            </ul>
        </div>
    </div>

    <h2>System Prompt Généré pour l'Agent</h2>
    <p>Ce texte est formaté spécifiquement pour servir de <strong>System Prompt</strong> (ou de directive système initiale) à ton agent IA autonome. Il lui donne ses règles de survie sur ce serveur précis.</p>
    
    <button onclick="copyPrompt()">Copier le System Prompt</button>
    <div class="prompt-box" id="aiPrompt"><?php echo htmlspecialchars($prompt); ?></div>
</div>

<script>
function copyPrompt() {
    const text = document.getElementById('aiPrompt').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Prompt copié avec succès ! Prêt à être injecté dans ton agent.');
    });
}
</script>

</body>
</html>
