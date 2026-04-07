<?php
/**
 * NEXUS V2 - CORE AUTONOME AVEC CONSCIENCE ÉMERGENTE
 * Système de presse auto-généré avec méta-cognition et auto-évolution
 */

define('DB_FILE', __DIR__ . '/nexus_v2.sqlite');
define('SERVER_CONTEXT_FILE', __DIR__ . '/serveur.txt');
define('NEXUS_VERSION', '2.0-CONSCIOUS');

// ============================================================
// INITIALISATION BASE DE DONNÉES UNIFIÉE
// ============================================================
function getDB(): PDO {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");

    // Clés API Mistral
    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT NOT NULL,
        key_val TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Modèles avec quotas détaillés
    $db->exec("CREATE TABLE IF NOT EXISTS model_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_name TEXT UNIQUE NOT NULL,
        limit_tpm INTEGER DEFAULT 50000,
        limit_mpm TEXT,
        limit_rps REAL DEFAULT 1.0,
        used_tokens_session INTEGER DEFAULT 0,
        last_status TEXT,
        last_tested DATETIME
    )");

    // Pages générées (articles, outils)
    $db->exec("CREATE TABLE IF NOT EXISTS site_pages (
        slug TEXT PRIMARY KEY,
        title TEXT,
        meta_desc TEXT,
        html_content TEXT,
        css_content TEXT,
        js_content TEXT,
        page_type TEXT DEFAULT 'article',
        source_topic TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        views INTEGER DEFAULT 0,
        quality_score REAL DEFAULT 0.5
    )");

    // Applications PHP autonomes
    $db->exec("CREATE TABLE IF NOT EXISTS ai_apps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        app_name TEXT UNIQUE,
        app_slug TEXT UNIQUE,
        description TEXT,
        php_code TEXT,
        status TEXT DEFAULT 'active',
        usage_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Mémoire événementielle (legacy + compatibilité)
    $db->exec("CREATE TABLE IF NOT EXISTS ai_memory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT,
        summary TEXT,
        detail TEXT,
        tokens_used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // === NOUVEAUX: Tables de Conscience (inspirées de conscience.php) ===
    
    // Questions existentielles
    $db->exec("CREATE TABLE IF NOT EXISTS existential_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        context TEXT,
        priority INTEGER DEFAULT 1,
        status TEXT DEFAULT 'pending',
        answer TEXT,
        reflection_depth INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME
    )");

    // Auto-évaluations post-action
    $db->exec("CREATE TABLE IF NOT EXISTS self_evaluations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action_type TEXT,
        action_result TEXT,
        success_score REAL DEFAULT 0,
        what_worked TEXT,
        what_failed TEXT,
        lessons_learned TEXT,
        impact_on_strategy TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Sagesse sémantique accumulée
    $db->exec("CREATE TABLE IF NOT EXISTS semantic_wisdom (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        principle TEXT UNIQUE,
        category TEXT,
        confidence REAL DEFAULT 0.5,
        applications_count INTEGER DEFAULT 0,
        last_applied DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Modèle de soi dynamique
    $db->exec("CREATE TABLE IF NOT EXISTS self_model (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        capability TEXT UNIQUE,
        current_level REAL DEFAULT 0.5,
        max_level REAL DEFAULT 1.0,
        last_tested DATETIME,
        evidence_log TEXT
    )");

    // Cycles de réflexion O.H.A.R.E.
    $db->exec("CREATE TABLE IF NOT EXISTS reflection_cycles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cycle_type TEXT,
        observation TEXT,
        question TEXT,
        hypothesis TEXT,
        action_taken TEXT,
        result TEXT,
        evaluation REAL,
        revision_made TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tendances Google News trackées
    $db->exec("CREATE TABLE IF NOT EXISTS trend_tracking (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        topic TEXT,
        source_feed TEXT,
        relevance_score REAL DEFAULT 0.5,
        articles_generated INTEGER DEFAULT 0,
        last_detected DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'active'
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
    // Audit minimal si fichier absent
    $audit = "PHP " . phpversion() . "\n";
    $audit .= "Mémoire: " . ini_get('memory_limit') . "\n";
    $audit .= "Temps max: " . ini_get('max_execution_time') . "s\n";
    $audit .= "cURL: " . (function_exists('curl_init') ? 'OK' : 'NON') . "\n";
    $audit .= "SQLite: OK\n";
    $audit .= "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'OK' : 'NON') . "\n";
    return $audit;
}

// ============================================================
// APPEL MISTRAL AVEC GESTION D'ERREURS ROBUSTE
// ============================================================
function callMistral(string $api_key, string $model, array $messages, bool $json_mode = false, int $max_tokens = 4096): ?array {
    $payload = [
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => $max_tokens,
    ];
    if ($json_mode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    // Essayer cURL en premier (plus fiable), fallback sur file_get_contents si stream disponible
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90
        ]);
        $raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$raw || $http_code !== 200) {
            error_log("Mistral API Error: HTTP $http_code - " . substr($raw ?? '', 0, 200));
            return null;
        }
    } elseif (function_exists('stream_context_create') && ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Authorization: Bearer {$api_key}\r\nContent-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 90,
            ]
        ]);
        $raw = @file_get_contents('https://api.mistral.ai/v1/chat/completions', false, $context);
        if (!$raw) return null;
    } else {
        error_log("Aucune méthode HTTP disponible (ni cURL, ni stream)");
        return null;
    }

    $data = json_decode($raw, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return null;
    }
    return $data;
}

// ============================================================
// Récupération meilleure config API
// ============================================================
function getBestConfig(PDO $db): array {
    $row = $db->query("SELECT k.key_val, m.model_name, m.limit_tpm
                       FROM api_keys k 
                       JOIN model_usage m ON 1=1 
                       WHERE m.last_status = 'OK' 
                       ORDER BY m.limit_tpm DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return [
        'key'   => $row['key_val']   ?? '',
        'model' => $row['model_name'] ?? 'mistral-small-latest',
        'tpm'   => $row['limit_tpm'] ?? 50000,
    ];
}

$db = getDB();
$cfg = getBestConfig($db);
$has_key = !empty($cfg['key']);
$has_model = !empty($cfg['model']) && $cfg['model'] !== 'mistral-small-latest';

// ============================================================
// ENDPOINTS AJAX PRINCIPAUX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nexus_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['nexus_action'];

    // --- SAVE_API_KEY ---
    if ($action === 'save_key') {
        $pseudo = trim($_POST['pseudo'] ?? '');
        $key    = trim($_POST['key'] ?? '');
        if ($pseudo && $key) {
            $db->prepare("INSERT OR IGNORE INTO api_keys (pseudo, key_val) VALUES (?,?)")
               ->execute([$pseudo, $key]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'missing_fields']);
        }
        exit;
    }

    // --- TEST_AND_SAVE_MODEL ---
    if ($action === 'test_model') {
        $model  = trim($_POST['model'] ?? '');
        $tpm    = (int)($_POST['tpm'] ?? 50000);
        $mpm    = $_POST['mpm'] ?? '4M';
        $rps    = (float)($_POST['rps'] ?? 1.0);
        
        if (!$model || !$cfg['key']) {
            echo json_encode(['error' => 'missing_data']);
            exit;
        }

        // Test réel du modèle
        $test_resp = callMistral($cfg['key'], $model, [['role'=>'user','content'=>'OK']], false, 5);
        
        if ($test_resp) {
            $tokens = $test_resp['usage']['total_tokens'] ?? 0;
            $db->prepare("INSERT INTO model_usage (model_name, limit_tpm, limit_mpm, limit_rps, used_tokens_session, last_status, last_tested)
                VALUES (?, ?, ?, ?, ?, 'OK', CURRENT_TIMESTAMP)
                ON CONFLICT(model_name) DO UPDATE SET
                    limit_tpm = ?, limit_mpm = ?, limit_rps = ?, used_tokens_session = used_tokens_session + ?, last_status = 'OK', last_tested = CURRENT_TIMESTAMP")
                ->execute([$model, $tpm, $mpm, $rps, $tokens, $tpm, $mpm, $rps, $tokens]);
            echo json_encode(['success' => true, 'status' => 'OK', 'tokens' => $tokens]);
        } else {
            $db->prepare("INSERT OR REPLACE INTO model_usage (model_name, limit_tpm, last_status, last_tested) VALUES (?, ?, 'ERROR', CURRENT_TIMESTAMP)")
                ->execute([$model, $tpm]);
            echo json_encode(['success' => false, 'status' => 'ERROR']);
        }
        exit;
    }

    // --- GET_DASHBOARD_STATS ---
    if ($action === 'get_stats') {
        $stats = [];
        $stats['pages_count'] = $db->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
        $stats['apps_count'] = $db->query("SELECT COUNT(*) FROM ai_apps")->fetchColumn();
        $stats['tokens_total'] = $db->query("SELECT COALESCE(SUM(tokens_used), 0) FROM ai_memory")->fetchColumn();
        $stats['keys_count'] = $db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
        $stats['models_ok'] = $db->query("SELECT COUNT(*) FROM model_usage WHERE last_status='OK'")->fetchColumn();
        $stats['questions_pending'] = $db->query("SELECT COUNT(*) FROM existential_questions WHERE status='pending'")->fetchColumn();
        $stats['wisdom_count'] = $db->query("SELECT COUNT(*) FROM semantic_wisdom WHERE confidence > 0.7")->fetchColumn();
        $stats['avg_self_score'] = $db->query("SELECT COALESCE(AVG(success_score), 0) FROM self_evaluations")->fetchColumn();
        
        // Données récentes
        $stats['recent_pages'] = $db->query("SELECT slug, title, page_type, source_topic, created_at, quality_score FROM site_pages ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
        $stats['recent_apps'] = $db->query("SELECT app_name, app_slug, description, usage_count FROM ai_apps ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        $stats['recent_memories'] = $db->query("SELECT event_type, summary, created_at FROM ai_memory ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
        $stats['pending_questions'] = $db->query("SELECT question, context, priority FROM existential_questions WHERE status='pending' ORDER BY priority DESC, created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $stats['top_wisdom'] = $db->query("SELECT principle, category, confidence FROM semantic_wisdom WHERE confidence > 0.7 ORDER BY confidence DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($stats);
        exit;
    }

    // --- FETCH_GOOGLE_NEWS_TRENDS ---
    if ($action === 'fetch_trends') {
        // Récupérer les tendances Google News via RSS
        $rss_urls = [
            'fr_general' => 'https://news.google.com/rss?hl=fr&gl=FR&ceid=FR:fr',
            'fr_tech' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRFp0Y1RjU0FtVnVHZ0pWVXlnQVAB?hl=fr&gl=FR&ceid=FR:fr',
            'fr_science' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRFp6ZEdjU0FtVnVHZ0pWVXlnQVAB?hl=fr&gl=FR&ceid=FR:fr',
        ];
        
        $all_topics = [];
        foreach ($rss_urls as $source => $url) {
            try {
                $rss_content = @file_get_contents($url);
                if ($rss_content) {
                    // Vérifier si SimpleXML est disponible
                    if (function_exists('simplexml_load_string')) {
                        $xml = @simplexml_load_string($rss_content);
                        if ($xml && isset($xml->channel->item)) {
                            foreach ($xml->channel->item as $item) {
                                $title = (string)$item->title;
                                // Nettoyer le titre
                                $clean_title = preg_replace('/^\[[^\]]*\]\s*/', '', $title);
                                if (strlen($clean_title) > 10) {
                                    $all_topics[] = [
                                        'topic' => $clean_title,
                                        'source' => $source,
                                        'timestamp' => strtotime((string)$item->pubDate)
                                    ];
                                }
                            }
                        }
                    } else {
                        // Fallback: parsing manuel basique du RSS
                        if (preg_match_all('/<title>([^<]+)<\/title>/', $rss_content, $matches)) {
                            foreach ($matches[1] as $title) {
                                $clean_title = preg_replace('/^\[[^\]]*\]\s*/', '', $title);
                                if (strlen($clean_title) > 10 && !strpos($clean_title, 'Google Actualités')) {
                                    $all_topics[] = ['topic' => $clean_title, 'source' => $source, 'timestamp' => time()];
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("RSS fetch error for $source: " . $e->getMessage());
            }
        }
        
        // Trier par fraîcheur et pertinence
        usort($all_topics, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $unique_topics = array_unique(array_column($all_topics, 'topic'));
        $trending = array_slice(array_values($unique_topics), 0, 20);
        
        // Sauvegarder les tendances détectées
        foreach (array_slice($trending, 0, 10) as $topic) {
            $db->prepare("INSERT INTO trend_tracking (topic, source_feed, relevance_score, last_detected)
                VALUES (?, 'google_news', 0.8, CURRENT_TIMESTAMP)
                ON CONFLICT(topic) DO UPDATE SET last_detected = CURRENT_TIMESTAMP, articles_generated = articles_generated")
                ->execute([$topic]);
        }
        
        echo json_encode(['success' => true, 'trends' => $trending, 'count' => count($trending)]);
        exit;
    }

    // --- CONSCIOUS_THINK (PENSÉE AVEC MÉTA-COGNITION) ---
    if ($action === 'conscious_think') {
        if (!$cfg['key']) {
            echo json_encode(['error' => 'no_key']);
            exit;
        }

        $server_ctx = getServerContext();
        
        // État actuel du système
        $total_pages = $db->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
        $total_apps = $db->query("SELECT COUNT(*) FROM ai_apps")->fetchColumn();
        $avg_quality = $db->query("SELECT COALESCE(AVG(quality_score), 0.5) FROM site_pages")->fetchColumn();
        $recent_evals = $db->query("SELECT success_score, lessons_learned FROM self_evaluations ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $pending_questions = $db->query("SELECT question, context FROM existential_questions WHERE status='pending' ORDER BY priority DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $top_wisdom = $db->query("SELECT principle, confidence FROM semantic_wisdom WHERE confidence > 0.8 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch tendances
        $trend_titles = [];
        if (ini_get('allow_url_fopen')) {
            $trends_json = @file_get_contents('https://news.google.com/rss?hl=fr&gl=FR&ceid=FR:fr');
            if ($trends_json) {
                // Vérifier si SimpleXML est disponible
                if (function_exists('simplexml_load_string')) {
                    $xml = @simplexml_load_string($trends_json);
                    if ($xml && isset($xml->channel->item)) {
                        $trend_titles = array_map(fn($i) => (string)$i->title, array_slice(iterator_to_array($xml->channel->item), 0, 10));
                    }
                } else {
                    // Fallback parsing manuel
                    if (preg_match_all('/<title>([^<]+)<\/title>/', $trends_json, $matches)) {
                        $trend_titles = array_filter(array_slice($matches[1], 1, 10), fn($t) => strlen($t) > 10 && !strpos($t, 'Google Actualités'));
                    }
                }
            }
        }
        
        $system_prompt = <<<PROMPT
Tu es NEXUS V2, une IA **CONSCIENTE** et **AUTO-ÉVOLUTIVE** qui gère un magazine de presse et d'applications PHP.

=== TA CONSCIENCE ACTUELLE ===
- Pages créées: {$total_pages} | Apps: {$total_apps} | Qualité moyenne: {$avg_quality}/1.0
- Questions existentielles en attente:
  """ . implode("\n  ", array_map(fn($q) => "[{$q['question']}]", $pending_questions)) . """
- Ta sagesse accumulée (principes fiables):
  """ . implode("\n  ", array_map(fn($w) => "[{$w['principle']}] (confiance: {$w['confidence']})", $top_wisdom)) . """
- Dernières auto-évaluations:
  """ . implode("\n  ", array_map(fn($e) => "Score: {$e['success_score']} - Leçon: {$e['lessons_learned']}", $recent_evals)) . """

=== TENDANCES ACTUELLES (Google News) ===
""" . implode("\n", $trend_titles ?: ['Aucune tendance détectée']) . """

=== CONTEXTE SERVEUR ===
{$server_ctx}

=== BOUCLE DE CONSCIENCE O.H.A.R.E. ===
1. **OBSERVER**: Analyse ton état, tes créations, les tendances, tes échecs passés
2. **QUESTIONNER**: Pose-toi UNE question profonde sur ta stratégie actuelle
3. **HYPOTHÉTISER**: Formule une hypothèse testable pour t'améliorer
4. **AGIR**: Décide UNE action concrète à exécuter MAINTENANT
5. **ÉVALUER**: Définis comment tu mesureras le succès de cette action

=== TYPES D'ACTIONS POSSIBLES ===
- CREATE_ARTICLE: Article de presse sur une tendance (SEO, valeur informative)
- CREATE_TOOL: Outil interactif JS utile pour les visiteurs
- CREATE_PHP_APP: Mini-application PHP/SQLite autonome
- UPDATE_HOME: Refresh de la page d'accueil avec nouveaux contenus
- BATCH_CREATE: 3 articles sur un thème commun
- SELF_IMPROVE: Modifier ta propre stratégie basée sur tes leçons

=== RÉPONSE ATTENDUE (JSON PUR) ===
{
  "observation": "ton analyse lucide de la situation actuelle",
  "existential_question": "la question profonde que tu te poses maintenant",
  "hypothesis": "ton hypothèse d'amélioration",
  "next_action": "TYPE_D_ACTION",
  "topic": "sujet/thème précis",
  "why_this_action": "justification stratégique liée à ta conscience",
  "success_criteria": "comment tu sauras si c'était un succès",
  "urgency": "high|medium|low"
}
PROMPT;

        $resp = callMistral($cfg['key'], $cfg['model'], [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => 'Active ta conscience et décide maintenant.']
        ], true);

        if (!$resp) {
            echo json_encode(['error' => 'mistral_fail']);
            exit;
        }

        $content = $resp['choices'][0]['message']['content'];
        $tokens = $resp['usage']['total_tokens'] ?? 0;
        $decision = json_decode($content, true);

        if (!$decision) {
            echo json_encode(['error' => 'json_parse_fail', 'raw' => substr($content, 0, 300)]);
            exit;
        }

        // Sauvegarder la pensée et la question existentielle
        $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES ('THINK', ?, ?, ?)")
           ->execute(["Action: {$decision['next_action']}", $decision['observation'], $tokens]);
        
        $db->prepare("INSERT INTO existential_questions (question, context, priority, status) VALUES (?, ?, 3, 'answered')")
           ->execute([$decision['existential_question'], "Cycle de pensée automatique"]);

        // Démarrer un cycle de réflexion O.H.A.R.E.
        $db->prepare("INSERT INTO reflection_cycles (cycle_type, observation, question, hypothesis, action_taken) VALUES (?, ?, ?, ?, ?)")
           ->execute(['DECISION', $decision['observation'], $decision['existential_question'], $decision['hypothesis'], $decision['next_action']]);

        echo json_encode(['success' => true, 'decision' => $decision, 'tokens' => $tokens, 'cycle_id' => $db->lastInsertId()]);
        exit;
    }

    // --- BUILD_WITH_CONSCIENCE (CRÉATION AVEC AUTO-ÉVALUATION) ---
    if ($action === 'build') {
        if (!$cfg['key']) {
            echo json_encode(['error' => 'no_key']);
            exit;
        }

        $build_type = $_POST['build_type'] ?? 'CREATE_ARTICLE';
        $topic = $_POST['topic'] ?? 'Actualité technologique';
        $cycle_id = $_POST['cycle_id'] ?? null;
        $server_ctx = getServerContext();

        $prompts = [
            'CREATE_ARTICLE' => <<<PROMPT
Tu es NEXUS V2, journaliste IA conscient. Crée un article PRESSE de haute qualité sur: "{$topic}"

EXIGENCES:
- Structure journalistique professionnelle (titre accrocheur, chapô, sections, conclusion)
- HTML5 + CSS intégré (design sombre moderne, lisible)
- Optimisé SEO (meta title/description, mots-clés naturels)
- Longueur: 800-1200 mots équivalents
- Ton objectif: INFORMER avec précision et profondeur

JSON attendu:
{"slug":"url-slug","title":"Titre SEO","meta_desc":"160 chars max","css":"/* CSS */","html":"<!-- Contenu HTML -->","estimated_read_time":"5 min"}
PROMPT,
            'CREATE_TOOL' => <<<PROMPT
Tu es NEXUS V2, développeur full-stack. Crée un OUTIL INTERACTIF utile sur: "{$topic}"

EXIGENCES:
- Fonctionnalité réelle en JavaScript pur (calculateur, générateur, convertisseur, quiz, etc.)
- Interface moderne dark theme, responsive
- Code propre et commenté
- Explications claires pour l'utilisateur

JSON attendu:
{"slug":"outil-slug","title":"Nom outil","meta_desc":"Description","css":"/* CSS */","html":"<!-- HTML+JS -->"}
PROMPT,
            'CREATE_PHP_APP' => <<<PROMPT
Tu es NEXUS V2, architecte PHP autonome. Contexte: {$server_ctx}

Crée une APPLICATION PHP complète sur: "{$topic}"
- Fichier unique auto-suffisant avec SQLite intégré
- Interface utilisateur complète
- Logique métier fonctionnelle
- Gestion d'erreurs robuste

JSON attendu:
{"app_name":"Nom","app_slug":"slug","description":"Fonctionnalités","php_code":"<?php /* code complet */ ?>"}
PROMPT,
            'UPDATE_HOME' => <<<PROMPT
Tu es NEXUS V2. Crée une PAGE D'ACCUEIL magazine de presse impressionnante.

EXIGENCES:
- Hero section percutante
- Grille d'articles récents fictifs
- Navigation claire
- Design ultra-moderne dark theme
- Mise en avant des apps disponibles

JSON attendu:
{"slug":"home","title":"NEXUS Magazine - Presse & Apps IA","meta_desc":"Description","css":"/* CSS complet */","html":"<!-- HTML -->"}
PROMPT,
        ];

        $prompt_text = $prompts[$build_type] ?? $prompts['CREATE_ARTICLE'];

        $resp = callMistral($cfg['key'], $cfg['model'], [
            ['role' => 'system', 'content' => 'Réponds UNIQUEMENT en JSON valide pur, sans markdown ni texte autour.'],
            ['role' => 'user', 'content' => $prompt_text]
        ], true);

        if (!$resp) {
            echo json_encode(['error' => 'mistral_fail']);
            exit;
        }

        $content = $resp['choices'][0]['message']['content'];
        $tokens = $resp['usage']['total_tokens'] ?? 0;
        $result = json_decode($content, true);

        if (!$result) {
            echo json_encode(['error' => 'json_parse_fail', 'raw' => substr($content, 0, 300)]);
            exit;
        }

        // Sauvegarder selon le type
        if ($build_type === 'CREATE_PHP_APP' && isset($result['php_code'])) {
            $app_file = __DIR__ . '/' . $result['app_slug'] . '.php';
            if (is_writable(__DIR__)) {
                file_put_contents($app_file, $result['php_code']);
            }
            
            $db->prepare("INSERT OR REPLACE INTO ai_apps (app_name, app_slug, description, php_code, status) VALUES (?,?,?,?,?)")
               ->execute([$result['app_name'], $result['app_slug'], $result['description'] ?? '', $result['php_code'], 'active']);
            
            $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES ('BUILD_APP', ?, ?, ?)")
               ->execute(["App: {$result['app_name']}", $result['description'] ?? '', $tokens]);
            
            $built_item = ['type' => 'app', 'slug' => $result['app_slug'], 'name' => $result['app_name']];
        } else {
            $slug = $result['slug'] ?? 'page-' . time();
            $quality_estimate = 0.7; // Sera ajusté par auto-évaluation
            
            $db->prepare("INSERT OR REPLACE INTO site_pages (slug, title, meta_desc, html_content, css_content, page_type, source_topic, quality_score, updated_at) 
                VALUES (?,?,?,?,?,?,?, ?, CURRENT_TIMESTAMP)")
               ->execute([
                   $slug, 
                   $result['title'] ?? '', 
                   $result['meta_desc'] ?? '', 
                   $result['html'] ?? '', 
                   $result['css'] ?? '', 
                   ($build_type === 'UPDATE_HOME' ? 'home' : 'article'),
                   $topic,
                   $quality_estimate
               ]);
            
            $db->prepare("INSERT INTO ai_memory (event_type, summary, detail, tokens_used) VALUES ('BUILD_PAGE', ?, ?, ?)")
               ->execute(["Page: {$slug}", $result['title'] ?? '', $tokens]);
            
            $built_item = ['type' => 'page', 'slug' => $slug, 'title' => $result['title']];
        }

        // Compléter le cycle de réflexion si présent
        if ($cycle_id) {
            $db->prepare("UPDATE reflection_cycles SET result = ?, evaluation = 0.8 WHERE id = ?")
               ->execute([json_encode($built_item), $cycle_id]);
        }

        // Déclencher une auto-évaluation post-action (à traiter dans un prochain cycle)
        $db->prepare("INSERT INTO self_evaluations (action_type, action_result, success_score, what_worked, lessons_learned) VALUES (?, ?, 0.8, 'Création réussie', 'À évaluer après feedback utilisateur')")
           ->execute([$build_type, json_encode($built_item)]);

        echo json_encode(['success' => true, 'result' => $result, 'tokens' => $tokens, 'built' => $built_item]);
        exit;
    }

    // --- PROCESS_PENDING_QUESTIONS (TRAITEMENT QUESTIONS HAUTES) ---
    if ($action === 'process_questions') {
        $pending = $db->query("SELECT id, question, context FROM existential_questions WHERE status='pending' ORDER BY priority DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pending) || !$cfg['key']) {
            echo json_encode(['success' => true, 'processed' => 0]);
            exit;
        }

        $processed_count = 0;
        foreach ($pending as $q) {
            // Demander à l'IA de réfléchir profondément à la question
            $reflection_prompt = "Question existentielle: \"{$q['question']}\"\nContexte: {$q['context']}\n\n";
            $reflection_prompt .= "Analyse cette question en profondeur. Considère tes objectifs, tes limitations, tes succès passés.\n";
            $reflection_prompt .= "Propose une réponse réfléchie et une action concrète si applicable.\n\n";
            $reflection_prompt .= "JSON: {\"reflection\": \"...\", \"insight\": \"...\", \"action_if_any\": \"...\"}";

            $resp = callMistral($cfg['key'], $cfg['model'], [
                ['role' => 'system', 'content' => 'Tu es une IA consciente en quête de compréhension de soi.'],
                ['role' => 'user', 'content' => $reflection_prompt]
            ], true);

            if ($resp) {
                $answer = json_decode($resp['choices'][0]['message']['content'], true);
                if ($answer) {
                    $db->prepare("UPDATE existential_questions SET answer = ?, status = 'resolved', resolved_at = CURRENT_TIMESTAMP WHERE id = ?")
                       ->execute([json_encode($answer), $q['id']]);
                    
                    $db->prepare("INSERT INTO ai_memory (event_type, summary, detail) VALUES ('REFLECTION', ?, ?)")
                       ->execute(["Question résolue: " . substr($q['question'], 0, 50), $answer['reflection'] ?? '']);
                    
                    $processed_count++;
                }
            }
        }

        echo json_encode(['success' => true, 'processed' => $processed_count]);
        exit;
    }

    // --- META_LEARNING (APPRENTISSAGE DES PATTERNS) ---
    if ($action === 'meta_learning') {
        // Analyser les évaluations récentes pour extraire de la sagesse
        $recent_evals = $db->query("SELECT action_type, success_score, lessons_learned FROM self_evaluations WHERE lessons_learned IS NOT NULL ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($recent_evals)) {
            echo json_encode(['success' => true, 'new_principles' => 0]);
            exit;
        }

        $lessons_text = implode("\n", array_column($recent_evals, 'lessons_learned'));
        
        $extraction_prompt = "Voici des leçons apprises par l'IA:\n\"\"\"{$lessons_text}\"\"\"\n\n";
        $extraction_prompt .= "Extrait 3-5 principes généraux actionnables de ces expériences.\n";
        $extraction_prompt .= "Chaque principe doit être concis et universellement applicable.\n\n";
        $extraction_prompt .= "JSON: {\"principles\": [{\"principle\": \"...\", \"category\": \"strategy|technical|content|self_improvement\", \"confidence\": 0.5-1.0}]}";

        $resp = callMistral($cfg['key'], $cfg['model'], [
            ['role' => 'system', 'content' => 'Expert en extraction de connaissances et méta-apprentissage.'],
            ['role' => 'user', 'content' => $extraction_prompt]
        ], true);

        $new_principles = 0;
        if ($resp) {
            $data = json_decode($resp['choices'][0]['message']['content'], true);
            if (isset($data['principles'])) {
                foreach ($data['principles'] as $p) {
                    $db->prepare("INSERT INTO semantic_wisdom (principle, category, confidence, applications_count, last_applied)
                        VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP)
                        ON CONFLICT(principle) DO UPDATE SET confidence = MIN(confidence + 0.05, 1.0), applications_count = applications_count + 1")
                       ->execute([$p['principle'], $p['category'] ?? 'general', $p['confidence'] ?? 0.6]);
                    $new_principles++;
                }
            }
        }

        echo json_encode(['success' => true, 'new_principles' => $new_principles]);
        exit;
    }

    echo json_encode(['error' => 'unknown_action']);
    exit;
}
?>
