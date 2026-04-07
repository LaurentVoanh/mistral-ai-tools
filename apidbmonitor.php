<?php
define('DB_FILE', __DIR__ . '/mistral_manager.sqlite');

// Connexion à la base de données
function getReadOnlyDB() {
    if (!file_exists(DB_FILE)) {
        die("Erreur : La base de données n'existe pas encore. Lancez la page principale d'abord.");
    }
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

try {
    $db = getReadOnlyDB();
    // Récupération des clés (masquées pour la sécurité)
    $keys = $db->query("SELECT pseudo, key_val, created_at FROM api_keys ORDER BY pseudo ASC")->fetchAll(PDO::FETCH_ASSOC);
    // Récupération des modèles et quotas
    $models = $db->query("SELECT * FROM model_usage ORDER BY last_tested DESC, model_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur de lecture : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mistral - Lecture Seule</title>
    <style>
        :root { --accent: #00ff41; --bg: #0a0a0a; --card: #161616; --text: #d0d0d0; }
        body { font-family: 'Consolas', 'Monaco', monospace; background: var(--bg); color: var(--text); padding: 20px; line-height: 1.4; }
        .container { max-width: 1100px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: var(--card); padding: 15px; border-radius: 8px; border: 1px solid #222; }
        h2 { color: var(--accent); font-size: 1.2em; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #222; }
        th { color: #888; text-transform: uppercase; font-size: 0.8em; }
        .status-ok { color: var(--accent); text-shadow: 0 0 5px rgba(0,255,65,0.3); }
        .status-err { color: #ff4444; }
        .badge { background: #333; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; }
        .refresh-hint { font-size: 0.7em; color: #555; text-align: right; margin-top: 10px; }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>

<div class="container">
    <div class="header">
        <h1>MISTRAL_DB_READER v1.0</h1>
        <div style="text-align: right">
            <span class="badge">SQLite: <?php echo basename(DB_FILE); ?></span><br>
            <small style="color: #666;">Dernière mise à jour : <?php echo date('H:i:s'); ?></small>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Clés API Active</h2>
            <table>
                <thead>
                    <tr><th>Pseudo</th><th>Clé (Masquée)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $k): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($k['pseudo']); ?></strong></td>
                        <td><code><?php echo substr($k['key_val'], 0, 4) . '...' . substr($k['key_val'], -4); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>État Global des Modèles</h2>
            <table>
                <thead>
                    <tr>
                        <th>Modèle</th>
                        <th>Statut</th>
                        <th>TPM</th>
                        <th>Tokens</th>
                        <th>Dernier Test</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($models as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['model_name']); ?></td>
                        <td class="<?php echo ($m['last_status'] === 'OK') ? 'status-ok' : 'status-err'; ?>">
                            <?php echo $m['last_status'] ?: '---'; ?>
                        </td>
                        <td><?php echo number_format($m['limit_tpm'], 0, '.', ' '); ?></td>
                        <td><span class="badge"><?php echo $m['used_tokens_session']; ?></span></td>
                        <td><small><?php echo $m['last_tested'] ? date('d/m H:i', strtotime($m['last_tested'])) : 'Jamais'; ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="refresh-hint">La page se rafraîchit automatiquement toutes les 30 secondes.</p>
        </div>
    </div>
</div>

</body>
</html>