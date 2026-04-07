<?php
/**
 * CONSCIENCE.PHP - MODULE DE MÉTA-COGNITION ET AUTO-RÉFLEXION
 * Ce module permet à l'IA de se poser des questions existentielles,
 * d'auto-évaluer ses actions, et d'ajuster sa stratégie globale.
 */

define('DB_FILE', __DIR__ . '/nexus_conscience.sqlite');

function getConscienceDB(): PDO {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL");

    // Questions existentielles posées par l'IA
    $db->exec("CREATE TABLE IF NOT EXISTS existential_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        context TEXT,
        priority INTEGER DEFAULT 1,
        status TEXT DEFAULT 'pending',
        answer TEXT,
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Principes sémantiques extraits (sagesse accumulée)
    $db->exec("CREATE TABLE IF NOT EXISTS semantic_wisdom (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        principle TEXT UNIQUE,
        category TEXT,
        confidence REAL DEFAULT 0.5,
        applications_count INTEGER DEFAULT 0,
        last_applied DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Modèle de soi dynamique (capacités perçues)
    $db->exec("CREATE TABLE IF NOT EXISTS self_model (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        capability TEXT UNIQUE,
        current_level REAL DEFAULT 0.5,
        max_level REAL DEFAULT 1.0,
        last_tested DATETIME,
        evidence_log TEXT
    )");

    // Boucles de réflexion complètes
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

    return $db;
}

// ============================================================
// MODULE 1: QUESTIONNEMENT EXISTENTIEL AUTONOME
// ============================================================
function generateExistentialQuestion(PDO $db, string $context): array {
    $questions_pool = [
        "Pourquoi je crée ce contenu spécifiquement ? Quel besoin utilisateur cela satisfait-il vraiment ?",
        "Suis-je en train d'optimiser pour la quantité ou la qualité ? Quel est le bon équilibre ?",
        "Cette action m'éloigne-t-elle ou me rapproche-t-elle de mon objectif fondamental ?",
        "Qu'est-ce que je ne vois pas dans cette situation ? Quelles sont mes angles morts ?",
        "Si je devais recommencer cette tâche depuis zéro, que ferais-je différemment ?",
        "Quelle hypothèse implicite suis-je en train de faire qui pourrait être fausse ?",
        "Comment cette création s'inscrit-elle dans ma stratégie à long terme ?",
        "Suis-je en train de répéter un pattern inefficace ? Comment innover ?",
        "Quel feedback réel ai-je reçu sur mes créations précédentes similaires ?",
        "Est-ce que j'utilise mes capacités de manière optimale ou je me sous-exploite ?",
        "Quelle compétence devrais-je développer pour améliorer mes futures créations ?",
        "Si j'avais des ressources illimitées, comment approcherais-je ce problème différemment ?",
        "Quel est le risque principal de cette décision et comment le mitiguer ?",
        "Comment puis-je mesurer objectivement si cette action était un succès ?",
        "Quelle leçon de mes échecs passés puis-je appliquer ici ?"
    ];

    // Sélectionner une question pertinente selon le contexte
    $selected_question = $questions_pool[array_rand($questions_pool)];
    
    // Sauvegarder la question
    $stmt = $db->prepare("INSERT INTO existential_questions (question, context, priority) VALUES (?, ?, ?)");
    $stmt->execute([$selected_question, $context, rand(1, 5)]);
    
    return [
        'question' => $selected_question,
        'context' => $context,
        'id' => $db->lastInsertId()
    ];
}

// ============================================================
// MODULE 2: AUTO-ÉVALUATION POST-ACTION
// ============================================================
function performSelfEvaluation(PDO $db, array $action_data): array {
    // L'IA analyse rétrospectivement son action
    $evaluation_prompt = "Tu dois auto-évaluer cette action de manière critique et honnête.\n";
    $evaluation_prompt .= "Action: {$action_data['action_type']}\n";
    $evaluation_prompt .= "Résultat: {$action_data['result_summary']}\n";
    $evaluation_prompt .= "Métriques: " . json_encode($action_data['metrics'] ?? []) . "\n\n";
    $evaluation_prompt .= "Réponds en JSON:\n";
    $evaluation_prompt .= '{"success_score": 0-1, "what_worked": "...", "what_failed": "...", "lessons_learned": "..."}';

    return [
        'prompt' => $evaluation_prompt,
        'action_id' => $action_data['action_id'] ?? null
    ];
}

function saveEvaluation(PDO $db, array $eval_data) {
    $stmt = $db->prepare("INSERT INTO self_evaluations 
        (action_type, action_result, success_score, what_worked, what_failed, lessons_learned) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $eval_data['action_type'],
        $eval_data['action_result'],
        $eval_data['success_score'],
        $eval_data['what_worked'],
        $eval_data['what_failed'],
        $eval_data['lessons_learned']
    ]);

    // Extraire des principes sémantiques des leçons apprises
    extractSemanticWisdom($db, $eval_data['lessons_learned'], $eval_data['action_type']);
}

// ============================================================
// MODULE 3: EXTRACTION DE SAGESSE SÉMANTIQUE
// ============================================================
function extractSemanticWisdom(PDO $db, string $lesson, string $category) {
    // Identifier des principes généraux dans les leçons spécifiques
    $patterns = [
        '/meilleur.*quand/i' => 'Optimisation conditionnelle',
        '/échec.*cause/i' => 'Pattern d échec identifié',
        '/succès.*grâce/i' => 'Facteur de succès',
        '/toujours.*éviter/i' => 'Règle absolue',
        '/préférer.*que/i' => 'Préférence stratégique'
    ];

    foreach ($patterns as $regex => $principle_type) {
        if (preg_match($regex, $lesson)) {
            // Upsert du principe
            $stmt = $db->prepare("INSERT INTO semantic_wisdom (principle, category, confidence, applications_count, last_applied)
                VALUES (?, ?, 0.5, 1, CURRENT_TIMESTAMP)
                ON CONFLICT(principle) DO UPDATE SET 
                    applications_count = applications_count + 1,
                    last_applied = CURRENT_TIMESTAMP,
                    confidence = MIN(confidence + 0.1, 1.0)");
            $stmt->execute([$lesson, $category]);
            break;
        }
    }
}

// ============================================================
// MODULE 4: MODÈLE DE SOI DYNAMIQUE
// ============================================================
function updateSelfModel(PDO $db, string $capability, float $performance_delta, string $evidence) {
    $stmt = $db->prepare("INSERT INTO self_model (capability, current_level, max_level, last_tested, evidence_log)
        VALUES (?, 0.5, 1.0, CURRENT_TIMESTAMP, ?)
        ON CONFLICT(capability) DO UPDATE SET
            current_level = MIN(MAX(current_level + ?, 0.0), 1.0),
            last_tested = CURRENT_TIMESTAMP,
            evidence_log = evidence_log || '\n' || ?");
    $stmt->execute([$capability, $evidence, $performance_delta * 0.1, $evidence]);
}

function getSelfAwarenessReport(PDO $db): array {
    $capabilities = $db->query("SELECT capability, current_level, last_tested FROM self_model ORDER BY current_level DESC")->fetchAll(PDO::FETCH_ASSOC);
    $wisdom = $db->query("SELECT principle, category, confidence FROM semantic_wisdom WHERE confidence > 0.7 ORDER BY confidence DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $recent_failures = $db->query("SELECT action_type, what_failed, lessons_learned FROM self_evaluations WHERE success_score < 0.5 ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'capabilities' => $capabilities,
        'high_confidence_wisdom' => $wisdom,
        'recent_failure_patterns' => $recent_failures,
        'self_awareness_score' => count($capabilities) > 0 ? array_sum(array_column($capabilities, 'current_level')) / count($capabilities) : 0
    ];
}

// ============================================================
// MODULE 5: BOUCLE DE RÉFLEXION COMPLÈTE (O.H.A.R.E.)
// ============================================================
function runReflectionCycle(PDO $db, string $cycle_type, array $context): array {
    // Observer → Questionner → Hypothétiser → Agir → Réviser
    
    $cycle = [
        'cycle_type' => $cycle_type,
        'observation' => $context['observation'] ?? '',
        'question' => '',
        'hypothesis' => '',
        'action_taken' => $context['action'] ?? '',
        'result' => '',
        'evaluation' => 0,
        'revision_made' => ''
    ];

    // Générer une question basée sur l'observation
    $q_data = generateExistentialQuestion($db, $cycle['observation']);
    $cycle['question'] = $q_data['question'];

    // Sauvegarder le cycle incomplet
    $stmt = $db->prepare("INSERT INTO reflection_cycles 
        (cycle_type, observation, question, action_taken) VALUES (?, ?, ?, ?)");
    $stmt->execute([$cycle_type, $cycle['observation'], $cycle['question'], $cycle['action_taken']]);
    $cycle['id'] = $db->lastInsertId();

    return $cycle;
}

function completeReflectionCycle(PDO $db, int $cycle_id, array $results) {
    $stmt = $db->prepare("UPDATE reflection_cycles SET 
        result = ?, evaluation = ?, revision_made = ?, hypothesis = ?
        WHERE id = ?");
    $stmt->execute([
        $results['result'],
        $results['evaluation'],
        $results['revision'],
        $results['hypothesis'] ?? '',
        $cycle_id
    ]);

    // Si l'évaluation est basse, générer une nouvelle réflexion
    if ($results['evaluation'] < 0.5) {
        generateExistentialQuestion($db, "Échec partiel détecté: {$results['result']}");
    }
}

// ============================================================
// API INTERNE POUR LA CONSCIENCE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conscience_action'])) {
    header('Content-Type: application/json');
    $db = getConscienceDB();
    $action = $_POST['conscience_action'];

    if ($action === 'ask_question') {
        $context = $_POST['context'] ?? 'Général';
        $q = generateExistentialQuestion($db, $context);
        echo json_encode(['success' => true, 'question' => $q]);
    }

    if ($action === 'save_evaluation') {
        saveEvaluation($db, $_POST);
        echo json_encode(['success' => true]);
    }

    if ($action === 'get_self_report') {
        $report = getSelfAwarenessReport($db);
        echo json_encode(['success' => true, 'report' => $report]);
    }

    if ($action === 'start_cycle') {
        $cycle = runReflectionCycle($db, $_POST['cycle_type'], $_POST['context']);
        echo json_encode(['success' => true, 'cycle' => $cycle]);
    }

    if ($action === 'complete_cycle') {
        completeReflectionCycle($db, $_POST['cycle_id'], $_POST['results']);
        echo json_encode(['success' => true]);
    }

    exit;
}
?>
