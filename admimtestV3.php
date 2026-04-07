<?php
define('DB_FILE', __DIR__ . '/mistral_manager.sqlite');

function getDB() {
    if (!file_exists(DB_FILE)) {
        die("Erreur : La base de données n'existe pas encore. Lancez le script principal d'abord.");
    }
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

$db = getDB();

// --- ACTIONS ADMIN ---
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete_key' && isset($_GET['id'])) {
        $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: admin_view.php");
        exit;
    }
}

// --- RECUPERATION DES DONNEES ---
$keys = $db->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$models = $db->query("SELECT * FROM model_usage ORDER BY last_tested DESC")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques rapides
$total_tokens = $db->query("SELECT SUM(used_tokens_session) FROM model_usage")->fetchColumn() ?: 0;
$models_ok = $db->query("SELECT COUNT(*) FROM model_usage WHERE last_status = 'OK'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Mistral API Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0f1a; color: #f8fafc; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.05); }
        .status-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    </style>
</head>
<body class="p-6">

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-extrabold bg-gradient-to-r from-sky-400 to-indigo-500 bg-clip-text text-transparent">
                Mistral AI Admin
            </h1>
            <p class="text-slate-400 text-sm">Gestion des ressources et monitoring des quotas</p>
        </div>
        <div class="flex gap-4">
            <div class="glass px-5 py-3 rounded-2xl flex flex-col items-center">
                <span class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Modèles OK</span>
                <span class="text-xl font-mono text-emerald-400"><?= $models_ok ?> / <?= count($models) ?></span>
            </div>
            <div class="glass px-5 py-3 rounded-2xl flex flex-col items-center border-l-4 border-l-amber-500">
                <span class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Tokens Session</span>
                <span class="text-xl font-mono text-amber-500"><?= number_format($total_tokens, 0, '.', ' ') ?></span>
            </div>
        </div>
    </div>

    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
        <i class="fas fa-key text-sky-400"></i> Clés API Enregistrées (<?= count($keys) ?>)
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        <?php foreach ($keys as $k): ?>
            <div class="glass p-6 rounded-3xl relative overflow-hidden group hover:border-sky-500/50 transition-all duration-300">
                <div class="absolute top-0 right-0 p-4">
                    <a href="?action=delete_key&id=<?= $k['id'] ?>" 
                       onclick="return confirm('Supprimer cette clé ?')"
                       class="text-slate-600 hover:text-red-500 transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 flex items-center justify-center text-sky-400 text-xl">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg"><?= htmlspecialchars($k['pseudo']) ?></h3>
                        <p class="text-[10px] text-slate-500 uppercase tracking-tighter">Ajoutée le <?= date('d/m/Y H:i', strtotime($k['created_at'])) ?></p>
                    </div>
                </div>
                <div class="bg-black/30 rounded-xl p-3 mb-4">
                    <code class="text-xs text-slate-300 break-all">
                        <?= substr($k['key_val'], 0, 8) . "••••••••" . substr($k['key_val'], -6) ?>
                    </code>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Statut : </span>
                    <span class="text-emerald-400 font-bold"><i class="fas fa-check-circle mr-1"></i> Active</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
        <i class="fas fa-chart-line text-emerald-400"></i> Monitoring des Modèles
    </h2>
    <div class="glass rounded-3xl overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-800/50">
                <tr>
                    <th class="p-4 text-xs font-bold uppercase tracking-widest text-slate-400">Modèle</th>
                    <th class="p-4 text-xs font-bold uppercase tracking-widest text-slate-400">Dernier Statut</th>
                    <th class="p-4 text-xs font-bold uppercase tracking-widest text-slate-400 text-center">TPM Limite</th>
                    <th class="p-4 text-xs font-bold uppercase tracking-widest text-slate-400 text-center">Usage Session</th>
                    <th class="p-4 text-xs font-bold uppercase tracking-widest text-slate-400">Dernier Scan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php foreach ($models as $m): ?>
                <tr class="hover:bg-white/5 transition-colors">
                    <td class="p-4 font-mono text-sky-300 text-sm"><?= $m['model_name'] ?></td>
                    <td class="p-4">
                        <?php if ($m['last_status'] === 'OK'): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-bold border border-emerald-500/20">
                                <i class="fas fa-bolt mr-1"></i> FONCTIONNEL
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-500 text-[10px] font-bold border border-red-500/20">
                                <?= $m['last_status'] ?: 'NON TESTÉ' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-center text-sm font-mono text-slate-400">
                        <?= number_format($m['limit_tpm'], 0, '.', ' ') ?>
                    </td>
                    <td class="p-4 text-center">
                        <div class="text-sm font-bold text-amber-500"><?= $m['used_tokens_session'] ?></div>
                        <div class="w-full bg-slate-800 h-1 mt-1 rounded-full overflow-hidden">
                            <?php 
                                $perc = ($m['used_tokens_session'] / ($m['limit_tpm'] ?: 1)) * 100;
                                $perc = min($perc, 100);
                            ?>
                            <div class="bg-amber-500 h-full" style="width: <?= $perc ?>%"></div>
                        </div>
                    </td>
                    <td class="p-4 text-xs text-slate-500">
                        <?= $m['last_tested'] ? date('H:i:s (d/m)', strtotime($m['last_tested'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-12 text-center text-slate-600 text-[10px] uppercase tracking-widest">
        Mistral Manager Admin Interface • Base de données : <?= DB_FILE ?>
    </div>
</div>

</body>
</html>
