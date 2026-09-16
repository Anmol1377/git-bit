<?php
/* Quiz API — one file, plain PHP + MySQL. Upload this, config.php and
   questions.json to your host; the front end on GitHub Pages talks to it.

   Endpoints (all under ?action=):
     questions  GET   the 15 questions, correct answers stripped
     state      GET   session, leaderboard, per-question stats, live feed
     join       POST  {name, device}      -> player
     answer     POST  {playerId, qid, choice, ms} -> {correct, points, why}
     reset      POST  {key}               -> starts a new session            */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');            // GitHub Pages is a different origin
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function fail(int $code, string $msg) {
  http_response_code($code);
  echo json_encode(['error' => $msg]);
  exit;
}

$cfg = @include __DIR__ . '/config.php';
if (!is_array($cfg)) fail(500, 'config.php missing on the server');

try {
  $pdo = new PDO(
    "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4",
    $cfg['user'], $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (PDOException $e) {
  // The real reason matters more than hiding it — no credentials appear in
  // these messages, just things like "Unknown database" or "Access denied".
  fail(500, 'database connection failed: ' . $e->getMessage());
}

/* ------------------------------------------------------------------ schema */
$pdo->exec("CREATE TABLE IF NOT EXISTS quiz_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(40) NOT NULL,
  started_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS quiz_players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  name VARCHAR(24) NOT NULL,
  device VARCHAR(32) NOT NULL,
  joined_at BIGINT NOT NULL,
  UNIQUE KEY uq_device (session_id, device),
  KEY ix_session (session_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  player_id INT NOT NULL,
  qid INT NOT NULL,
  choice INT NOT NULL,
  correct TINYINT NOT NULL,
  ms INT NOT NULL,
  points INT NOT NULL,
  at BIGINT NOT NULL,
  UNIQUE KEY uq_answer (player_id, qid),
  KEY ix_session (session_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* --------------------------------------------------------------- helpers */
$QUESTIONS = json_decode((string)@file_get_contents(__DIR__ . '/questions.json'), true);
if (!is_array($QUESTIONS)) fail(500, 'questions.json missing on the server');

function question(array $qs, int $id): ?array {
  foreach ($qs as $q) if ((int)$q['id'] === $id) return $q;
  return null;
}
function body(): array {
  $b = json_decode((string)file_get_contents('php://input'), true);
  return is_array($b) ? $b : [];
}
function currentSession(PDO $pdo): array {
  $s = $pdo->query("SELECT * FROM quiz_sessions ORDER BY id DESC LIMIT 1")->fetch();
  if ($s) return $s;
  $pdo->prepare("INSERT INTO quiz_sessions (label, started_at) VALUES ('Session 1', ?)")
      ->execute([(int)(microtime(true) * 1000)]);
  return $pdo->query("SELECT * FROM quiz_sessions ORDER BY id DESC LIMIT 1")->fetch();
}
// 10 base + difficulty bonus + speed bonus (5 -> 0 across the first 15 seconds)
function points(string $d, int $ms): int {
  $bonus = ['easy' => 0, 'medium' => 5, 'hard' => 10][$d] ?? 0;
  return 10 + $bonus + (int)max(0, round(5 * (1 - min($ms, 15000) / 15000)));
}

$action = $_GET['action'] ?? '';
$session = currentSession($pdo);
$sid = (int)$session['id'];

/* --------------------------------------------------------------- actions */
if ($action === 'questions') {
  echo json_encode(array_map(fn($q) => ['id' => $q['id'], 'd' => $q['d'], 'q' => $q['q'], 'a' => $q['a']], $QUESTIONS));
  exit;
}

if ($action === 'state') {
  $board = $pdo->prepare("
    SELECT p.id, p.name,
           COALESCE(SUM(a.points), 0)  AS score,
           COALESCE(SUM(a.correct), 0) AS correct,
           COUNT(a.id)                 AS answered,
           CAST(COALESCE(AVG(a.ms), 0) AS UNSIGNED) AS avg_ms
    FROM quiz_players p LEFT JOIN quiz_answers a ON a.player_id = p.id
    WHERE p.session_id = ?
    GROUP BY p.id, p.name
    ORDER BY score DESC, avg_ms ASC");
  $board->execute([$sid]);

  $perQ = $pdo->prepare("SELECT qid, COUNT(*) n, SUM(correct) ok FROM quiz_answers WHERE session_id = ? GROUP BY qid");
  $perQ->execute([$sid]);

  $sp = $pdo->prepare("SELECT qid, choice, COUNT(*) n FROM quiz_answers WHERE session_id = ? GROUP BY qid, choice");
  $sp->execute([$sid]);
  $spread = [];
  foreach ($sp->fetchAll() as $r) {
    $spread[(int)$r['qid']] ??= [0, 0, 0, 0];
    $spread[(int)$r['qid']][(int)$r['choice']] = (int)$r['n'];
  }

  $feed = $pdo->prepare("
    SELECT a.id, p.name, a.qid, a.correct, a.points, a.ms
    FROM quiz_answers a JOIN quiz_players p ON p.id = a.player_id
    WHERE a.session_id = ? ORDER BY a.id DESC LIMIT 12");
  $feed->execute([$sid]);

  echo json_encode([
    'session' => $session,
    'total'   => count($QUESTIONS),
    'board'   => $board->fetchAll(),
    'perQ'    => $perQ->fetchAll(),
    'spread'  => $spread,
    'feed'    => $feed->fetchAll(),
    'correct' => array_map(fn($q) => ['id' => $q['id'], 'c' => $q['c']], $QUESTIONS),
  ]);
  exit;
}

if ($action === 'join') {
  $b = body();
  $name = trim((string)($b['name'] ?? ''));
  $device = preg_replace('/[^a-z0-9]/i', '', (string)($b['device'] ?? ''));
  if ($name === '' || $device === '') fail(400, 'name and device required');
  $name = mb_substr($name, 0, 24);

  // one player per device per session — rejoining resumes, it does not restart
  $st = $pdo->prepare("SELECT * FROM quiz_players WHERE session_id = ? AND device = ?");
  $st->execute([$sid, $device]);
  $player = $st->fetch();

  if (!$player) {
    $pdo->prepare("INSERT INTO quiz_players (session_id, name, device, joined_at) VALUES (?, ?, ?, ?)")
        ->execute([$sid, $name, $device, (int)(microtime(true) * 1000)]);
    $st->execute([$sid, $device]);
    $player = $st->fetch();
  }

  $done = $pdo->prepare("SELECT qid, choice, correct, ms, points FROM quiz_answers WHERE player_id = ? ORDER BY qid");
  $done->execute([(int)$player['id']]);

  echo json_encode(['player' => $player, 'answered' => $done->fetchAll(), 'sessionId' => $sid]);
  exit;
}

if ($action === 'answer') {
  $b = body();
  $pid = (int)($b['playerId'] ?? 0);
  $q = question($QUESTIONS, (int)($b['qid'] ?? 0));
  $choice = (int)($b['choice'] ?? -1);
  $ms = max(0, (int)($b['ms'] ?? 0));
  if (!$q) fail(400, 'unknown question');

  $st = $pdo->prepare("SELECT * FROM quiz_players WHERE id = ?");
  $st->execute([$pid]);
  $player = $st->fetch();
  if (!$player) fail(400, 'unknown player');
  if ((int)$player['session_id'] !== $sid) fail(409, 'session restarted — rejoin');

  $ok = $choice === (int)$q['c'];
  $pts = $ok ? points((string)$q['d'], $ms) : 0;

  // IGNORE => the first answer stands; re-submitting cannot change it
  $pdo->prepare("INSERT IGNORE INTO quiz_answers
                 (session_id, player_id, qid, choice, correct, ms, points, at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
      ->execute([$sid, $pid, (int)$q['id'], $choice, $ok ? 1 : 0, $ms, $pts, (int)(microtime(true) * 1000)]);

  $st = $pdo->prepare("SELECT correct, points FROM quiz_answers WHERE player_id = ? AND qid = ?");
  $st->execute([$pid, (int)$q['id']]);
  $stored = $st->fetch() ?: ['correct' => $ok ? 1 : 0, 'points' => $pts];

  echo json_encode([
    'correct'      => (bool)$stored['correct'],
    'points'       => (int)$stored['points'],
    'correctIndex' => (int)$q['c'],
    'why'          => $q['why'],
  ]);
  exit;
}

if ($action === 'reset') {
  $b = body();
  if (($b['key'] ?? '') !== ($cfg['host_key'] ?? '')) fail(403, 'wrong host key');
  $n = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM quiz_sessions")->fetchColumn() + 1;
  $pdo->prepare("INSERT INTO quiz_sessions (label, started_at) VALUES (?, ?)")
      ->execute(["Session $n", (int)(microtime(true) * 1000)]);
  echo json_encode(['ok' => true]);
  exit;
}

fail(404, 'unknown action');
