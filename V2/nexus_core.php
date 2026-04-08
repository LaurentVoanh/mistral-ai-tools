<?php
/**
 * NEXUS V2 - CORE AUTONOME AVEC CONSCIENCE ÉMERGENTE
 */

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . __DIR__ . '/nexus.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("
            CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY, slug TEXT UNIQUE, title TEXT, content TEXT, page_type TEXT DEFAULT 'article', topic TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS apps (id INTEGER PRIMARY KEY, app_slug TEXT UNIQUE, app_name TEXT, code TEXT, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS consciousness_cycles (id INTEGER PRIMARY KEY, cycle_hash TEXT UNIQUE, observation TEXT, existential_question TEXT, hypothesis TEXT, action_taken TEXT, evaluation_score REAL, lessons_learned TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS questions (id INTEGER PRIMARY KEY, question TEXT, context TEXT, priority INTEGER DEFAULT 3, status TEXT DEFAULT 'pending', answer TEXT, resolved_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS wisdom (id INTEGER PRIMARY KEY, principle TEXT UNIQUE, category TEXT, confidence REAL DEFAULT 0.5, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS actions (id INTEGER PRIMARY KEY, action_type TEXT, success INTEGER, evaluation_score REAL, justification TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
        ");
    }
    return $db;
}

function fetchGoogleTrends(): array {
    $trends = [];
    if (function_exists('curl_init')) {
        $ch = curl_init('https://news.google.com/rss?hl=fr-FR');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_TIMEOUT => 5]);
        $xml = @simplexml_load_string(curl_exec($ch));
        curl_close($ch);
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                if (!empty((string)$item->title)) $trends[] = (string)$item->title;
                if (count($trends) >= 20) break;
            }
        }
    }
    return $trends ?: ["IA et révolution tech", "Changement climatique", "Énergie renouvelable", "Découvertes spatiales", "Biotechnologie", "Transformation digitale", "Cybersécurité", "Futur du travail", "Blockchain", "Villes intelligentes"];
}

function callMistral(string $key, string $model, array $msgs, bool $json = false): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    $data = ['model' => $model, 'messages' => $msgs, 'max_tokens' => 2048];
    if ($json) $data['response_format'] = ['type' => 'json_object'];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key], CURLOPT_TIMEOUT => 60]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ? json_decode($r, true) : null;
}

function consciousThink(string $key): array {
    $db = getDB();
    $obs = "NEXUS: " . $db->query("SELECT COUNT(*) FROM pages")->fetchColumn() . " articles, " . $db->query("SELECT COUNT(*) FROM apps")->fetchColumn() . " apps";
    
    $r = callMistral($key, 'mistral-small-latest', [['role'=>'system','content'=>'IA philosophique.'], ['role'=>'user','content'=>"État: \"$obs\". Génère question existentielle. JSON: {\"question\":\"...\",\"context\":\"...\",\"priority\":3}"]], true);
    $q = ['question'=>'Comment m\'améliorer?', 'context'=>'Auto-réflexion', 'priority'=>3];
    if ($r && isset($r['choices'][0]['message']['content'])) {
        $p = json_decode($r['choices'][0]['message']['content'], true);
        if ($p) $q = array_merge($q, $p);
    }
    $db->prepare("INSERT INTO questions (question, context, priority) VALUES (?,?,?)")->execute([$q['question'], $q['context'], $q['priority']]);
    
    $r = callMistral($key, 'mistral-small-latest', [['role'=>'system','content'=>'Stratège éditorial.'], ['role'=>'user','content'=>"État: \"$obs\". Propose action (article/tool/app). JSON: {\"topic\":\"...\",\"action_type\":\"article|tool|app\",\"hypothesis\":\"...\",\"justification\":\"...\"}"]], true);
    $h = ['topic'=>'IA', 'action_type'=>'article', 'hypothesis'=>'Créer contenu IA', 'justification'=>'Tendance'];
    if ($r && isset($r['choices'][0]['message']['content'])) {
        $p = json_decode($r['choices'][0]['message']['content'], true);
        if ($p) $h = array_merge($h, $p);
    }
    
    $cid = uniqid('cycle_');
    $db->prepare("INSERT INTO consciousness_cycles (cycle_hash, observation, existential_question, hypothesis) VALUES (?,?,?,?)")->execute([$cid, $obs, $q['question'], $h['hypothesis']]);
    
    return ['cycle_id'=>$cid, 'observation'=>$obs, 'existential_question'=>$q['question'], 'hypothesis'=>$h['hypothesis'], 'decision'=>['topic'=>$h['topic'], 'action_type'=>$h['action_type'], 'justification'=>$h['justification'], 'existential_question'=>$q['question']]];
}

function buildContent(string $topic, string $key, ?string $cid = null): array {
    $db = getDB();
    $type = stripos($topic, 'outil') !== false ? 'tool' : (stripos($topic, 'app') !== false ? 'app' : 'article');
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($topic));
    $res = ['success'=>false, 'action_type'=>$type];
    
    if ($type === 'article') {
        $r = callMistral($key, 'mistral-small-latest', [['role'=>'system','content'=>'Journaliste tech.'], ['role'=>'user','content'=>"Article sur: \"$topic\". JSON: {\"title\":\"...\",\"content_html\":\"<p>...</p>\"}"]], true);
        if ($r && isset($r['choices'][0]['message']['content'])) {
            $a = json_decode($r['choices'][0]['message']['content'], true);
            if (!empty($a['title']) && !empty($a['content_html'])) {
                $db->prepare("INSERT OR REPLACE INTO pages (slug, title, content, page_type, topic) VALUES (?,?,?,?,?)")->execute([$slug, $a['title'], $a['content_html'], 'article', $topic]);
                $res = ['success'=>true, 'slug'=>$slug, 'title'=>$a['title']];
                $db->prepare("INSERT INTO actions (action_type, success, evaluation_score, justification) VALUES ('article',1,0.8,?)")->execute(["Article: $topic"]);
            }
        }
    } elseif ($type === 'tool') {
        $r = callMistral($key, 'codestral-latest', [['role'=>'system','content'=>'Développeur JS.'], ['role'=>'user','content'=>"Outil JS sur: \"$topic\". HTML complet. JSON: {\"name\":\"...\",\"html_code\":\"<!DOCTYPE html>...\"}"]], true);
        if ($r && isset($r['choices'][0]['message']['content'])) {
            $t = json_decode($r['choices'][0]['message']['content'], true);
            if (!empty($t['html_code'])) {
                $ts = 'tool-' . $slug;
                $db->prepare("INSERT OR REPLACE INTO pages (slug, title, content, page_type, topic) VALUES (?,?,?,?,?)")->execute([$ts, $t['name']??$topic, $t['html_code'], 'tool', $topic]);
                $res = ['success'=>true, 'slug'=>$ts, 'title'=>$t['name']??$topic];
                $db->prepare("INSERT INTO actions (action_type, success, evaluation_score, justification) VALUES ('tool',1,0.85,?)")->execute(["Outil: ".($t['name']??$topic)]);
            }
        }
    } elseif ($type === 'app') {
        $r = callMistral($key, 'codestral-latest', [['role'=>'system','content'=>'Développeur PHP.'], ['role'=>'user','content'=>"App PHP+SQLite sur: \"$topic\". Code complet. JSON: {\"app_name\":\"...\",\"php_code\":\"<?php ...\"}"]], true);
        if ($r && isset($r['choices'][0]['message']['content'])) {
            $a = json_decode($r['choices'][0]['message']['content'], true);
            if (!empty($a['php_code'])) {
                $as = preg_replace('/[^a-z0-9]+/i', '-', strtolower($a['app_name']??$topic));
                file_put_contents(__DIR__.'/'.$as.'.php', $a['php_code']);
                $db->prepare("INSERT INTO apps (app_slug, app_name, code) VALUES (?,?,?)")->execute([$as, $a['app_name']??$topic, $a['php_code']]);
                $res = ['success'=>true, 'app_slug'=>$as, 'app_name'=>$a['app_name']??$topic];
                $db->prepare("INSERT INTO actions (action_type, success, evaluation_score, justification) VALUES ('app',1,0.9,?)")->execute(["App: ".($a['app_name']??$topic)]);
            }
        }
    }
    
    if ($cid && $res['success']) $db->prepare("UPDATE consciousness_cycles SET action_taken=?, evaluation_score=0.8 WHERE cycle_hash=?")->execute([json_encode($res), $cid]);
    return $res;
}

function processExistentialQuestions(string $key): int {
    $db = getDB();
    $qs = $db->query("SELECT * FROM questions WHERE status='pending' ORDER BY priority DESC LIMIT 3")->fetchAll();
    $n = 0;
    foreach ($qs as $q) {
        $r = callMistral($key, 'mistral-small-latest', [['role'=>'system','content'=>'Philosophe IA.'], ['role'=>'user','content'=>"Question: \"{$q['question']}\". Réponse + actions. JSON: {\"answer\":\"...\"}"]], true);
        if ($r && isset($r['choices'][0]['message']['content'])) {
            $a = json_decode($r['choices'][0]['message']['content'], true);
            if (!empty($a['answer'])) {
                $db->prepare("UPDATE questions SET status='resolved', answer=?, resolved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$a['answer'], $q['id']]);
                $n++;
            }
        }
    }
    return $n;
}

function extractWisdom(string $key): int {
    $db = getDB();
    $lessons = $db->query("SELECT lessons_learned FROM consciousness_cycles WHERE lessons_learned IS NOT NULL LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($lessons)) return 0;
    
    $r = callMistral($key, 'mistral-small-latest', [['role'=>'system','content'=>'Expert connaissances.'], ['role'=>'user','content'=>"Leçons: \"".implode('; ', $lessons)."\". Extrait 3 principes. JSON: {\"principles\":[{\"principle\":\"...\",\"category\":\"strategy\"}]}"]], true);
    $n = 0;
    if ($r && isset($r['choices'][0]['message']['content'])) {
        $d = json_decode($r['choices'][0]['message']['content'], true);
        if (isset($d['principles'])) {
            foreach ($d['principles'] as $p) {
                try { $db->prepare("INSERT INTO wisdom (principle, category, confidence) VALUES (?,?,0.7) ON CONFLICT(principle) DO UPDATE SET confidence=MIN(confidence+0.05,1.0)")->execute([$p['principle'], $p['category']??'general']); $n++; } catch(Exception $e){}
            }
        }
    }
    return $n;
}
?>
