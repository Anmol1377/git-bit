<?php
/* ---------------------------------------------------------------------------
   -prvite.php — standalone admin panel for the quiz.

   Self-contained on purpose: its own credentials, its own connection, nothing
   shared with quiz.php or config.php. Upload it next to quiz.php, open it in a
   browser, done.

   What it does: start a new session, delete a player, empty or delete a
   session, wipe everything, drop the tables.

   ---- BEFORE YOU UPLOAD -----------------------------------------------------
   Fill in the six constants below. They are placeholders right now, so the page
   will just say "cannot reach MySQL" until you do.

   This page can delete every answer in the room, so pick a real $ADMIN_PASS.
   Once you put the live password in here, keep that copy off GitHub.
--------------------------------------------------------------------------- */

// ---- everything it needs, right here ---------------------------------------
$DB_HOST = 'YOUR_MYSQL_HOST';        // e.g. sqlNNN.infinityfree.com
$DB_PORT = 3306;
$DB_USER = 'YOUR_MYSQL_USER';        // e.g. if0_00000000
$DB_PASS = 'YOUR_MYSQL_PASSWORD';
$DB_NAME = 'YOUR_DATABASE_NAME';     // e.g. if0_00000000_something

// Change this. Anyone who reaches this URL and knows it can delete everything.
$ADMIN_PASS = 'CHANGE_ME';

// Database names get mistyped constantly. Add any spellings you are unsure of
// here — each is tried in turn and the panel reports which one worked.
$NAME_GUESSES = [$DB_NAME];
// ---------------------------------------------------------------------------

session_start();
header('X-Robots-Tag: noindex, nofollow');

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

if (($_POST['pass'] ?? '') !== '' && hash_equals($ADMIN_PASS, $_POST['pass']))
  $_SESSION['ok'] = true;

$authed = !empty($_SESSION['ok']);

/* ------------------------------------------------------------- connection */
$pdo = null; $connErr = ''; $usedName = '';
foreach (array_unique($NAME_GUESSES) as $name) {
  if ($name === '') continue;
  try {
    $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$name;charset=utf8mb4",
      $DB_USER, $DB_PASS,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $usedName = $name;
    break;
  } catch (PDOException $e) { $connErr = $e->getMessage(); }
}

$msg = '';
if ($pdo && $authed && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $do = $_POST['do'] ?? '';
  try {
    if ($do === 'new_session') {
      $n = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM quiz_sessions")->fetchColumn() + 1;
      $pdo->prepare("INSERT INTO quiz_sessions (label, started_at) VALUES (?, ?)")
          ->execute(["Session $n", (int)(microtime(true) * 1000)]);
      $msg = "Started Session $n. The dashboard picks it up within a couple of seconds.";
    }
    if ($do === 'del_player') {
      $id = (int)$_POST['id'];
      $nm = $pdo->prepare("SELECT name FROM quiz_players WHERE id = ?");
      $nm->execute([$id]);
      $who = $nm->fetchColumn() ?: "#$id";
      $pdo->prepare("DELETE FROM quiz_answers WHERE player_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM quiz_players WHERE id = ?")->execute([$id]);
      $msg = "Deleted player \"$who\" and every answer they gave.";
    }
    if ($do === 'clear_session') {
      $sid = (int)$_POST['id'];
      $pdo->prepare("DELETE FROM quiz_answers WHERE session_id = ?")->execute([$sid]);
      $pdo->prepare("DELETE FROM quiz_players WHERE session_id = ?")->execute([$sid]);
      $msg = "Emptied session #$sid — players and answers gone, the session itself kept.";
    }
    if ($do === 'del_session') {
      $sid = (int)$_POST['id'];
      $pdo->prepare("DELETE FROM quiz_answers WHERE session_id = ?")->execute([$sid]);
      $pdo->prepare("DELETE FROM quiz_players WHERE session_id = ?")->execute([$sid]);
      $pdo->prepare("DELETE FROM quiz_sessions WHERE id = ?")->execute([$sid]);
      $msg = "Deleted session #$sid entirely.";
    }
    if ($do === 'nuke' && ($_POST['confirm'] ?? '') === 'DELETE') {
      $pdo->exec("DELETE FROM quiz_answers");
      $pdo->exec("DELETE FROM quiz_players");
      $pdo->exec("DELETE FROM quiz_sessions");
      $pdo->exec("ALTER TABLE quiz_answers AUTO_INCREMENT = 1");
      $pdo->exec("ALTER TABLE quiz_players AUTO_INCREMENT = 1");
      $pdo->exec("ALTER TABLE quiz_sessions AUTO_INCREMENT = 1");
      $msg = "Everything wiped. A fresh session is created on the next visit to the quiz.";
    }
    if ($do === 'drop' && ($_POST['confirm'] ?? '') === 'DROP') {
      $pdo->exec("DROP TABLE IF EXISTS quiz_answers");
      $pdo->exec("DROP TABLE IF EXISTS quiz_players");
      $pdo->exec("DROP TABLE IF EXISTS quiz_sessions");
      $msg = "Tables dropped. quiz.php recreates them on its next request.";
    }
  } catch (PDOException $e) { $msg = 'Error: ' . $e->getMessage(); }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tablesExist = false; $sessions = []; $players = []; $counts = ['s' => 0, 'p' => 0, 'a' => 0];
if ($pdo && $authed) {
  try {
    $pdo->query("SELECT 1 FROM quiz_sessions LIMIT 1");
    $tablesExist = true;
    $sessions = $pdo->query("
      SELECT s.id, s.label, s.started_at,
             (SELECT COUNT(*) FROM quiz_players p WHERE p.session_id = s.id) players,
             (SELECT COUNT(*) FROM quiz_answers a WHERE a.session_id = s.id) answers
      FROM quiz_sessions s ORDER BY s.id DESC")->fetchAll();
    $players = $pdo->query("
      SELECT p.id, p.name, p.session_id, p.device,
             COALESCE(SUM(a.points),0) score, COUNT(a.id) answered
      FROM quiz_players p LEFT JOIN quiz_answers a ON a.player_id = p.id
      GROUP BY p.id, p.name, p.session_id, p.device
      ORDER BY p.session_id DESC, score DESC")->fetchAll();
    $counts = [
      's' => (int)$pdo->query("SELECT COUNT(*) FROM quiz_sessions")->fetchColumn(),
      'p' => (int)$pdo->query("SELECT COUNT(*) FROM quiz_players")->fetchColumn(),
      'a' => (int)$pdo->query("SELECT COUNT(*) FROM quiz_answers")->fetchColumn(),
    ];
  } catch (PDOException $e) { $tablesExist = false; }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Quiz admin</title>
<style>
:root{--bg:#05070d;--panel:#0d1117;--panel2:#161b22;--line:#25303d;--tx:#e6edf3;--dim:#8b98a5;
  --blue:#58a6ff;--green:#3fb950;--red:#f85149;
  --mono:ui-monospace,SFMono-Regular,Menlo,monospace}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.5 Inter,system-ui,sans-serif;padding:28px 18px 70px}
.wrap{max-width:1080px;margin:0 auto}
h1{font-size:26px;letter-spacing:-.02em;margin:0 0 4px}
h2{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);font-family:var(--mono);margin:30px 0 10px}
.sub{color:var(--dim);margin:0 0 20px;font-size:13px}
code,.mono{font-family:var(--mono)}
.card{border:1px solid var(--line);border-radius:12px;background:var(--panel);padding:16px 18px;margin-bottom:12px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:6px}
.kpi b{display:block;font-size:28px;font-weight:800;color:var(--blue)}
.kpi span{font-size:12px;color:var(--dim)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-family:var(--mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;
  color:var(--dim);padding:8px 10px;border-bottom:1px solid var(--line)}
td{padding:9px 10px;border-bottom:1px solid #1b2430}
tr:last-child td{border-bottom:0}
.btn{font:inherit;font-size:13px;cursor:pointer;border:1px solid var(--line);background:var(--panel2);
  color:var(--tx);border-radius:8px;padding:8px 14px}
.btn:hover{border-color:var(--blue);color:var(--blue)}
.btn.red{border-color:rgba(248,81,73,.5);color:var(--red)}
.btn.red:hover{background:var(--red);color:#2a0705}
.btn.green{border-color:rgba(63,185,80,.5);color:var(--green)}
.btn.sm{padding:5px 10px;font-size:12px}
input[type=text],input[type=password]{font:inherit;background:var(--panel2);color:var(--tx);
  border:1px solid var(--line);border-radius:8px;padding:10px 13px;outline:0;min-width:210px}
input:focus{border-color:var(--blue)}
.note{border-left:3px solid var(--blue);background:rgba(88,166,255,.08);padding:11px 15px;
  border-radius:0 10px 10px 0;margin-bottom:16px;font-size:13px}
.note.bad{border-color:var(--red);background:rgba(248,81,73,.1)}
.note.good{border-color:var(--green);background:rgba(63,185,80,.1)}
.danger{border-color:rgba(248,81,73,.4)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
a{color:var(--blue)}
@media(max-width:700px){.kpis{grid-template-columns:1fr}table{font-size:12px}}
</style>
</head>
<body>
<div class="wrap">
<h1>Quiz admin</h1>
<p class="sub">Standalone panel — <span class="mono"><?= h($DB_USER) ?>@<?= h($DB_HOST) ?></span><?php
  if ($usedName) echo ' · db <span class="mono">' . h($usedName) . '</span>'; ?><?php
  if ($authed) echo ' · <a href="?logout=1">log out</a>'; ?></p>

<?php if ($msg): ?><div class="note <?= str_starts_with($msg, 'Error') ? 'bad' : 'good' ?>"><?= h($msg) ?></div><?php endif; ?>

<?php if (!$pdo): ?>
  <div class="note bad">
    <b>Cannot reach MySQL.</b><br>
    Tried: <span class="mono"><?= h(implode(', ', array_filter(array_unique($NAME_GUESSES)))) ?></span><br>
    Last error: <span class="mono"><?= h($connErr) ?></span>
    <p style="margin:8px 0 0">
      <b>Still on the placeholders</b> → fill in the constants at the top of this file.<br>
      <b>Unknown database</b> → the name is wrong, check your hosting panel.<br>
      <b>Access denied</b> → wrong username or password.<br>
      <b>getaddrinfo / refused</b> → wrong host, or this file is not running on the hosting network.
    </p>
  </div>

<?php elseif (!$authed): ?>
  <div class="card">
    <h2 style="margin-top:0">Password</h2>
    <form method="post" class="row">
      <input type="password" name="pass" placeholder="Admin password" autofocus>
      <button class="btn">Unlock</button>
    </form>
    <p class="sub" style="margin:12px 0 0">Connected to <span class="mono"><?= h($usedName) ?></span>. Set <code>$ADMIN_PASS</code> at the top of this file.</p>
  </div>

<?php else: ?>
  <?php if (!$tablesExist): ?>
    <div class="note">No quiz tables yet. Open <code>quiz.php?action=state</code> once and they create themselves.</div>
  <?php endif; ?>

  <div class="kpis">
    <div class="card kpi"><b><?= $counts['s'] ?></b><span>sessions</span></div>
    <div class="card kpi"><b><?= $counts['p'] ?></b><span>players</span></div>
    <div class="card kpi"><b><?= $counts['a'] ?></b><span>answers</span></div>
  </div>

  <h2>Sessions</h2>
  <div class="card">
    <form method="post" style="margin-bottom:14px">
      <input type="hidden" name="do" value="new_session">
      <button class="btn green">Start a new session</button>
      <span class="sub" style="margin-left:8px">Old data is kept; the dashboard switches to the new one.</span>
    </form>
    <?php if ($sessions): ?>
    <table>
      <tr><th>#</th><th>Label</th><th>Started</th><th>Players</th><th>Answers</th><th></th></tr>
      <?php foreach ($sessions as $s): ?>
      <tr>
        <td class="mono"><?= (int)$s['id'] ?></td>
        <td><?= h($s['label']) ?></td>
        <td class="mono"><?= h(date('Y-m-d H:i', (int)($s['started_at'] / 1000))) ?></td>
        <td class="mono"><?= (int)$s['players'] ?></td>
        <td class="mono"><?= (int)$s['answers'] ?></td>
        <td class="row">
          <form method="post" onsubmit="return confirm('Empty this session? Players and answers are deleted.')">
            <input type="hidden" name="do" value="clear_session"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn sm">Empty</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete this session and everything in it?')">
            <input type="hidden" name="do" value="del_session"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn sm red">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p class="sub" style="margin:0">No sessions yet.</p><?php endif; ?>
  </div>

  <h2>Players</h2>
  <div class="card">
    <?php if ($players): ?>
    <table>
      <tr><th>#</th><th>Name</th><th>Session</th><th>Device</th><th>Answered</th><th>Score</th><th></th></tr>
      <?php foreach ($players as $p): ?>
      <tr>
        <td class="mono"><?= (int)$p['id'] ?></td>
        <td><?= h($p['name']) ?></td>
        <td class="mono"><?= (int)$p['session_id'] ?></td>
        <td class="mono" style="color:var(--dim)"><?= h($p['device']) ?></td>
        <td class="mono"><?= (int)$p['answered'] ?></td>
        <td class="mono" style="color:var(--green)"><?= (int)$p['score'] ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this player and all their answers?')">
            <input type="hidden" name="do" value="del_player"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn sm red">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p class="sub" style="margin:0">No players yet.</p><?php endif; ?>
  </div>

  <h2 style="color:var(--red)">Danger zone</h2>
  <div class="card danger">
    <p class="sub" style="margin-top:0"><b>Wipe everything</b> — every session, player and answer. Counters restart at 1.</p>
    <form method="post" class="row" onsubmit="return confirm('Delete ALL quiz data? This cannot be undone.')">
      <input type="hidden" name="do" value="nuke">
      <input type="text" name="confirm" placeholder="type DELETE" autocomplete="off">
      <button class="btn red">Wipe everything</button>
    </form>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <p class="sub" style="margin-top:0"><b>Drop the tables</b> — use this if the schema is ever wrong. <code>quiz.php</code> rebuilds them on its next request.</p>
    <form method="post" class="row" onsubmit="return confirm('Drop the quiz tables?')">
      <input type="hidden" name="do" value="drop">
      <input type="text" name="confirm" placeholder="type DROP" autocomplete="off">
      <button class="btn red">Drop tables</button>
    </form>
  </div>
<?php endif; ?>
</div>
</body>
</html>
