<?php
session_start();

define('ADMIN_CFG',  __DIR__ . '/config/admin.json');
define('GUESTS_FILE', __DIR__ . '/config/guests.json');
define('EVENT_FILE',  __DIR__ . '/config/event.json');

// ─── helpers ────────────────────────────────────────────────────────────────

function loadJson(string $path): array {
    if (!file_exists($path)) return [];
    $d = json_decode(file_get_contents($path), true);
    return is_array($d) ? $d : [];
}

function saveJson(string $path, array $data): void {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadGuests(): array {
    $d = loadJson(GUESTS_FILE);
    return $d['guests'] ?? [];
}

function saveGuests(array $guests): void {
    saveJson(GUESTS_FILE, ['guests' => array_values($guests)]);
}

function isAdmin(): bool { return !empty($_SESSION['admin']); }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function checkCsrf(): bool {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '');
}

function makeToken(array $existing = []): string {
    $pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
    do {
        $t = '';
        for ($i = 0; $i < 8; $i++) $t .= $pool[random_int(0, 35)];
    } while (in_array($t, $existing, true));
    return $t;
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── bootstrap admin credentials ────────────────────────────────────────────

if (!file_exists(ADMIN_CFG)) {
    saveJson(ADMIN_CFG, [
        'username'      => 'admin',
        'password_hash' => password_hash('changeme123', PASSWORD_BCRYPT),
    ]);
}

// ─── request handling ───────────────────────────────────────────────────────

$notice = null; // ['ok'|'err', 'message html']

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['action'] ?? '');

    // login — no CSRF needed
    if ($act === 'login') {
        $cfg = loadJson(ADMIN_CFG);
        if (
            hash_equals($cfg['username'] ?? '', trim($_POST['username'] ?? '')) &&
            password_verify($_POST['password'] ?? '', $cfg['password_hash'] ?? '')
        ) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: admin.php');
            exit;
        }
        $notice = ['err', 'Invalid username or password.'];
    }

    // all other actions require auth + CSRF
    elseif (isAdmin() && checkCsrf()) {
        $guests = loadGuests();

        switch ($act) {

            case 'add_guest':
                $meals = array_values(array_filter(array_map('trim', explode(',', $_POST['meal_options'] ?? ''))));
                $tok   = makeToken(array_column($guests, 'token'));
                $guests[] = [
                    'token'            => $tok,
                    'name'             => strip_tags(trim($_POST['name'] ?? '')),
                    'salutation'       => strip_tags(trim($_POST['salutation'] ?? 'Dear')),
                    'plus_one_allowed' => !empty($_POST['plus_one_allowed']),
                    'plus_one_name'    => '',
                    'personal_message' => strip_tags(trim($_POST['personal_message'] ?? '')),
                    'table'            => strip_tags(trim($_POST['table'] ?? '')),
                    'meal_options'     => $meals,
                    'rsvp_status'      => null, 'rsvp_attending'  => null,
                    'rsvp_guests'      => null, 'rsvp_meal'       => null,
                    'rsvp_dietary'     => null, 'rsvp_notes'      => null,
                    'rsvp_timestamp'   => null,
                ];
                saveGuests($guests);
                $notice = ['ok', "Guest added — invite token: <code>{$tok}</code>"];
                break;

            case 'edit_guest':
                $tok   = $_POST['token'] ?? '';
                $meals = array_values(array_filter(array_map('trim', explode(',', $_POST['meal_options'] ?? ''))));
                foreach ($guests as &$g) {
                    if ($g['token'] !== $tok) continue;
                    $g['name']             = strip_tags(trim($_POST['name'] ?? $g['name']));
                    $g['salutation']       = strip_tags(trim($_POST['salutation'] ?? $g['salutation']));
                    $g['plus_one_allowed'] = !empty($_POST['plus_one_allowed']);
                    $g['personal_message'] = strip_tags(trim($_POST['personal_message'] ?? $g['personal_message']));
                    $g['table']            = strip_tags(trim($_POST['table'] ?? $g['table']));
                    $g['meal_options']     = $meals;
                    break;
                }
                unset($g);
                saveGuests($guests);
                $notice = ['ok', 'Guest updated.'];
                break;

            case 'delete_guest':
                $tok    = $_POST['token'] ?? '';
                $guests = array_filter($guests, fn($g) => $g['token'] !== $tok);
                saveGuests($guests);
                $notice = ['ok', 'Guest deleted.'];
                break;

            case 'reset_rsvp':
                $tok = $_POST['token'] ?? '';
                foreach ($guests as &$g) {
                    if ($g['token'] !== $tok) continue;
                    foreach (['rsvp_status','rsvp_attending','rsvp_guests','rsvp_meal','rsvp_dietary','rsvp_notes','rsvp_timestamp'] as $k)
                        $g[$k] = null;
                    break;
                }
                unset($g);
                saveGuests($guests);
                $notice = ['ok', 'RSVP cleared.'];
                break;

            case 'change_password':
                $cfg = loadJson(ADMIN_CFG);
                $cur = $_POST['current_password'] ?? '';
                $new = $_POST['new_password']     ?? '';
                $con = $_POST['confirm_password'] ?? '';
                if (!password_verify($cur, $cfg['password_hash'] ?? ''))
                    $notice = ['err', 'Current password is incorrect.'];
                elseif (strlen($new) < 8)
                    $notice = ['err', 'New password must be at least 8 characters.'];
                elseif ($new !== $con)
                    $notice = ['err', 'New passwords do not match.'];
                else {
                    $cfg['password_hash'] = password_hash($new, PASSWORD_BCRYPT);
                    saveJson(ADMIN_CFG, $cfg);
                    $notice = ['ok', 'Password updated.'];
                }
                break;

            case 'export_csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="rsvp_export_' . date('Ymd') . '.csv"');
                $fp = fopen('php://output', 'w');
                fputs($fp, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
                fputcsv($fp, ['Name','Token','Table','Status','Attending','Guests','Meal','Dietary','Notes','Timestamp']);
                foreach ($guests as $g) {
                    fputcsv($fp, [
                        $g['name']    ?? '',
                        $g['token']   ?? '',
                        $g['table']   ?? '',
                        $g['rsvp_status'] ?? 'pending',
                        $g['rsvp_attending'] === true ? 'Yes' : ($g['rsvp_attending'] === false ? 'No' : '-'),
                        $g['rsvp_guests']  ?? '-',
                        $g['rsvp_meal']    ?? '-',
                        $g['rsvp_dietary'] ?? '-',
                        $g['rsvp_notes']   ?? '-',
                        $g['rsvp_timestamp'] ?? '-',
                    ]);
                }
                fclose($fp);
                exit;
        }

        // re-load after modifications
        $guests = loadGuests();
    }
}

// ─── data for view ──────────────────────────────────────────────────────────

$guests = loadGuests();
$event  = loadJson(EVENT_FILE);
$csrf   = csrf();

$total = count($guests);
$attending = $declined = $pending = $seats = 0;
foreach ($guests as $g) {
    if ($g['rsvp_attending'] === true)       { $attending++; $seats += (int)($g['rsvp_guests'] ?? 1); }
    elseif ($g['rsvp_attending'] === false)    $declined++;
    elseif ($g['rsvp_status'] === null)        $pending++;
}

$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Guest data keyed by token for JS modal population
$guestsJson = json_encode(
    array_column($guests, null, 'token'),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — <?= e($event['title'] ?? 'Event') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  <style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #0f0f1a;
    --surface: #1a1a2e;
    --surface2: #20203a;
    --accent:  #c9a84c;
    --accent2: #e5c87a;
    --text:    #f0e6d3;
    --muted:   #7a7a9a;
    --border:  #2a2a4a;
    --ok:      #4aca84;
    --err:     #ca4a4a;
    --clr-yes:  #4aca84;
    --clr-no:   #ca5050;
    --clr-pend: #c9a84c;
  }

  html { font-size: 15px; }
  body {
    font-family: 'Lato', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    line-height: 1.6;
  }

  /* ─── Login ─── */
  .login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(ellipse at 50% 0%, #1a1a3e 0%, var(--bg) 70%);
    padding: 1.5rem;
  }
  .login-card {
    width: 100%;
    max-width: 380px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 2.5rem;
    text-align: center;
  }
  .login-tag {
    display: inline-block;
    background: var(--accent);
    color: #1a1a2e;
    font-family: 'Playfair Display', serif;
    font-weight: 600;
    font-size: 0.7rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    padding: 0.2rem 0.8rem;
    border-radius: 4px;
    margin-bottom: 1.1rem;
  }
  .login-card h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 400;
    margin-bottom: 1.75rem;
  }
  .login-card .field { text-align: left; margin-bottom: 1rem; }

  /* ─── Nav ─── */
  .topnav {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 1.75rem;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .nav-brand { display: flex; align-items: center; gap: 0.8rem; }
  .nav-tag {
    background: var(--accent);
    color: #1a1a2e;
    font-weight: 700;
    font-size: 0.65rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    padding: 0.18rem 0.6rem;
    border-radius: 4px;
  }
  .nav-event {
    font-family: 'Playfair Display', serif;
    font-size: 1rem;
    opacity: 0.75;
  }
  .nav-actions { display: flex; gap: 0.65rem; }

  /* ─── Container ─── */
  .container {
    max-width: 1320px;
    margin: 0 auto;
    padding: 2rem 1.5rem 5rem;
  }

  /* ─── Notice ─── */
  .notice {
    padding: 0.8rem 1.2rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
  }
  .notice.ok  { background: rgba(74,202,132,0.1); border: 1px solid rgba(74,202,132,0.25); color: var(--ok); }
  .notice.err { background: rgba(202,74,74,0.1);  border: 1px solid rgba(202,74,74,0.25);  color: var(--err); }
  .notice code { background: rgba(201,168,76,0.2); padding: 0.1rem 0.35rem; border-radius: 3px; font-size: 0.9em; }

  /* ─── Stats ─── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
  }
  @media (max-width: 700px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.25rem;
    text-align: center;
  }
  .stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 2.1rem;
    font-weight: 600;
    color: var(--accent);
    line-height: 1;
    margin-bottom: 0.3rem;
  }
  .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
  .stat-card.s-attending .stat-value { color: var(--clr-yes); }
  .stat-card.s-declined  .stat-value { color: var(--clr-no); }
  .stat-card.s-pending   .stat-value { color: var(--clr-pend); }

  /* ─── Section header ─── */
  .section-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
  }
  .section-hdr h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 400;
  }
  .section-hdr-actions { display: flex; gap: 0.65rem; align-items: center; }

  /* ─── Table ─── */
  .table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    overflow-x: auto;
    margin-bottom: 2.5rem;
  }
  .guests-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
    white-space: nowrap;
  }
  .guests-table th {
    background: var(--surface2);
    color: var(--muted);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.75rem 1rem;
    text-align: left;
  }
  .guests-table td {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border);
    vertical-align: middle;
  }
  .guests-table tbody tr:hover td { background: rgba(255,255,255,0.018); }
  .td-wrap { white-space: normal; max-width: 160px; font-size: 0.82rem; color: var(--muted); }
  .empty-row { text-align: center; color: var(--muted); padding: 2.5rem !important; white-space: normal; }

  code.tok {
    background: var(--surface2);
    color: var(--accent);
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.05em;
  }

  .copy-btn {
    background: none; border: none; cursor: pointer;
    color: var(--muted); font-size: 0.95rem; padding: 0 0.25rem;
    vertical-align: middle; transition: color 0.15s;
    line-height: 1;
  }
  .copy-btn:hover { color: var(--accent); }

  /* ─── Badges ─── */
  .badge {
    display: inline-block;
    padding: 0.18rem 0.6rem;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }
  .b-yes  { background: rgba(74,202,132,0.15); color: var(--clr-yes); }
  .b-no   { background: rgba(202,74,74,0.15);  color: var(--clr-no); }
  .b-pend { background: rgba(201,168,76,0.15); color: var(--clr-pend); }

  /* ─── Buttons ─── */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
    padding: 0.5rem 1.1rem;
    border: 1px solid transparent;
    border-radius: 6px;
    font-family: 'Lato', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.18s;
    white-space: nowrap;
  }
  .btn-primary { background: var(--accent); color: #1a1a2e; border-color: var(--accent); }
  .btn-primary:hover { background: var(--accent2); border-color: var(--accent2); }
  .btn-outline { background: transparent; color: var(--text); border-color: var(--border); }
  .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
  .btn-ghost { background: transparent; color: var(--muted); border-color: transparent; }
  .btn-ghost:hover { color: var(--text); }
  .btn-sm { padding: 0.35rem 0.8rem; font-size: 0.78rem; }
  .btn-full { width: 100%; }

  .act-btn {
    background: transparent; border: 1px solid transparent;
    border-radius: 4px; font-size: 0.72rem; font-weight: 600;
    cursor: pointer; padding: 0.18rem 0.48rem;
    transition: all 0.15s; font-family: 'Lato', sans-serif;
    white-space: nowrap;
  }
  .act-edit   { color: var(--accent);  border-color: rgba(201,168,76,0.3); }
  .act-edit:hover   { background: rgba(201,168,76,0.12); }
  .act-reset  { color: var(--muted);   border-color: rgba(122,122,154,0.3); }
  .act-reset:hover  { background: rgba(122,122,154,0.1); color: var(--text); }
  .act-delete { color: var(--err);     border-color: rgba(202,80,80,0.3); }
  .act-delete:hover { background: rgba(202,80,80,0.12); }

  .inline-form { display: inline; }
  .acts { display: flex; gap: 0.3rem; align-items: center; }

  /* ─── Forms / Fields ─── */
  .field { margin-bottom: 1rem; }
  .field label {
    display: block; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--muted); margin-bottom: 0.4rem;
  }
  .field label small { font-size: 0.78rem; text-transform: none; letter-spacing: 0; opacity: 0.75; }
  .field input[type="text"],
  .field input[type="password"],
  .field textarea {
    width: 100%; background: var(--bg);
    border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-family: 'Lato', sans-serif;
    font-size: 0.9rem; padding: 0.6rem 0.875rem;
    transition: border-color 0.2s; outline: none;
  }
  .field input:focus, .field textarea:focus { border-color: var(--accent); }
  .field textarea { resize: vertical; }
  .chk-label {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.875rem; cursor: pointer; color: var(--text);
    margin-bottom: 1rem;
  }
  .chk-label input[type="checkbox"] { accent-color: var(--accent); width: 15px; height: 15px; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 520px) { .field-row { grid-template-columns: 1fr; } }

  /* ─── Settings ─── */
  .settings-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-top: 1rem;
  }
  .settings-panel > summary {
    padding: 1rem 1.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--muted);
    list-style: none;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .settings-panel > summary::-webkit-details-marker { display: none; }
  .settings-panel > summary::before { content: '▶'; font-size: 0.6rem; transition: transform 0.2s; }
  .settings-panel[open] > summary::before { transform: rotate(90deg); }
  .settings-panel > summary:hover { color: var(--text); }
  .settings-panel[open] > summary { border-bottom: 1px solid var(--border); }
  .settings-body { padding: 1.5rem; }
  .settings-body h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1rem; font-weight: 400; margin-bottom: 1.25rem;
  }
  .settings-form { max-width: 400px; }

  /* ─── Modals ─── */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.72); z-index: 1000;
    align-items: center; justify-content: center; padding: 1rem;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; width: 100%; max-width: 560px;
    max-height: 90vh; overflow-y: auto;
    animation: slideUp 0.2s ease;
  }
  @keyframes slideUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
  .modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border);
    position: sticky; top: 0; background: var(--surface); z-index: 1;
  }
  .modal-hdr h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 400; }
  .modal-x {
    background: none; border: none; cursor: pointer;
    color: var(--muted); font-size: 1.5rem; line-height: 1; padding: 0 0.2rem;
    transition: color 0.15s;
  }
  .modal-x:hover { color: var(--text); }
  .modal-body { padding: 1.5rem; }
  .modal-foot { display: flex; justify-content: flex-end; gap: 0.65rem; padding-top: 0.5rem; }
  </style>
</head>
<body>

<?php if (!isAdmin()): ?>
<!-- ═══════════════════ LOGIN ═══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-tag">Admin</div>
    <h1><?= e($event['title'] ?? 'Event') ?></h1>

    <?php if ($notice): ?>
      <div class="notice <?= $notice[0] ?>" style="margin-bottom:1.25rem;"><?= $notice[1] ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label for="u">Username</label>
        <input id="u" type="text" name="username" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label for="p">Password</label>
        <input id="p" type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">Sign In</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════ DASHBOARD ═══════════════════ -->

<nav class="topnav">
  <div class="nav-brand">
    <span class="nav-tag">Admin</span>
    <span class="nav-event"><?= e($event['title'] ?? '') ?></span>
  </div>
  <div class="nav-actions">
    <a href="index.php" class="btn btn-sm btn-ghost" target="_blank" rel="noopener">View Site</a>
    <a href="admin.php?action=logout" class="btn btn-sm btn-outline">Sign Out</a>
  </div>
</nav>

<main class="container">

  <?php if ($notice): ?>
    <div class="notice <?= $notice[0] ?>"><?= $notice[1] ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-label">Invited</div>
    </div>
    <div class="stat-card s-attending">
      <div class="stat-value"><?= $attending ?></div>
      <div class="stat-label">Attending</div>
    </div>
    <div class="stat-card s-declined">
      <div class="stat-value"><?= $declined ?></div>
      <div class="stat-label">Declined</div>
    </div>
    <div class="stat-card s-pending">
      <div class="stat-value"><?= $pending ?></div>
      <div class="stat-label">Awaiting</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $seats ?></div>
      <div class="stat-label">Total Seats</div>
    </div>
  </div>

  <!-- Section header -->
  <div class="section-hdr">
    <h2>Guest List</h2>
    <div class="section-hdr-actions">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit" class="btn btn-sm btn-outline">↓ Export CSV</button>
      </form>
      <button class="btn btn-sm btn-primary" onclick="openModal('modal-add')">+ Add Guest</button>
    </div>
  </div>

  <!-- Guest table -->
  <div class="table-wrap">
    <table class="guests-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Token / Link</th>
          <th>Table</th>
          <th>Status</th>
          <th>Guests</th>
          <th>Meal</th>
          <th>Dietary</th>
          <th>Notes</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($guests as $g): ?>
        <?php
          $statusClass = match(true) {
            $g['rsvp_attending'] === true  => 'b-yes',
            $g['rsvp_attending'] === false => 'b-no',
            default                        => 'b-pend',
          };
          $statusText = match(true) {
            $g['rsvp_attending'] === true  => 'Attending',
            $g['rsvp_attending'] === false => 'Declined',
            default                        => 'Pending',
          };
          $inviteUrl = $baseUrl . '/invite.php?token=' . urlencode($g['token']);
        ?>
        <tr>
          <td><strong><?= e($g['name']) ?></strong></td>
          <td>
            <code class="tok"><?= e($g['token']) ?></code>
            <button class="copy-btn" title="Copy invite link"
              onclick="copyLink(this, '<?= e(addslashes($inviteUrl)) ?>')">⎘</button>
          </td>
          <td><?= e($g['table'] ?? '—') ?></td>
          <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
          <td><?= $g['rsvp_guests'] !== null ? (int)$g['rsvp_guests'] : '—' ?></td>
          <td><?= e($g['rsvp_meal'] ?? '—') ?></td>
          <td class="td-wrap"><?= e($g['rsvp_dietary'] ?? '—') ?></td>
          <td class="td-wrap"><?= e($g['rsvp_notes'] ?? '—') ?></td>
          <td>
            <?= $g['rsvp_timestamp']
              ? e((new DateTime($g['rsvp_timestamp']))->format('M j, Y g:i A') . ' UTC')
              : '—' ?>
          </td>
          <td>
            <div class="acts">
              <button class="act-btn act-edit" onclick="openEdit('<?= e($g['token']) ?>')">Edit</button>
              <?php if ($g['rsvp_status'] !== null): ?>
              <form method="POST" class="inline-form"
                onsubmit="return confirm('Reset RSVP for <?= e(addslashes($g['name'])) ?>?')">
                <input type="hidden" name="action" value="reset_rsvp">
                <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
                <input type="hidden" name="token"  value="<?= e($g['token']) ?>">
                <button type="submit" class="act-btn act-reset">Reset</button>
              </form>
              <?php endif; ?>
              <form method="POST" class="inline-form"
                onsubmit="return confirm('Delete <?= e(addslashes($g['name'])) ?>? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_guest">
                <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
                <input type="hidden" name="token"  value="<?= e($g['token']) ?>">
                <button type="submit" class="act-btn act-delete">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($guests)): ?>
        <tr><td colspan="10" class="empty-row">No guests yet — click <strong>+ Add Guest</strong> to begin.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Settings -->
  <details class="settings-panel">
    <summary>Settings</summary>
    <div class="settings-body">
      <h3>Change Password</h3>
      <form method="POST" class="settings-form">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
        <div class="field">
          <label>Current Password</label>
          <input type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label>New Password</label>
          <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Update Password</button>
      </form>
    </div>
  </details>

</main>

<!-- ═══ Modal: Add Guest ═══ -->
<div id="modal-add" class="modal-overlay" onclick="closeModal('modal-add')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-hdr">
      <h2>Add Guest</h2>
      <button class="modal-x" onclick="closeModal('modal-add')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_guest">
        <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
        <div class="field-row">
          <div class="field">
            <label>Full Name *</label>
            <input type="text" name="name" required placeholder="e.g. John & Jane Smith">
          </div>
          <div class="field">
            <label>Salutation *</label>
            <input type="text" name="salutation" required placeholder="e.g. Dear" value="Dear">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Table Assignment</label>
            <input type="text" name="table" placeholder="e.g. Table 5">
          </div>
          <div class="field">
            <label>Meal Options <small>(comma-separated)</small></label>
            <input type="text" name="meal_options" placeholder="Chicken, Vegetarian, Fish">
          </div>
        </div>
        <div class="field">
          <label>Personal Message</label>
          <textarea name="personal_message" rows="3" placeholder="A personal note for this guest..."></textarea>
        </div>
        <label class="chk-label">
          <input type="checkbox" name="plus_one_allowed" value="1">
          Allow plus-one (up to 2 guests total)
        </label>
        <div class="modal-foot">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add')">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Add Guest</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══ Modal: Edit Guest ═══ -->
<div id="modal-edit" class="modal-overlay" onclick="closeModal('modal-edit')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-hdr">
      <h2>Edit Guest</h2>
      <button class="modal-x" onclick="closeModal('modal-edit')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="form-edit">
        <input type="hidden" name="action" value="edit_guest">
        <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
        <input type="hidden" name="token"  id="edit-token">
        <div class="field-row">
          <div class="field">
            <label>Full Name *</label>
            <input type="text" name="name" id="edit-name" required>
          </div>
          <div class="field">
            <label>Salutation *</label>
            <input type="text" name="salutation" id="edit-salutation" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Table Assignment</label>
            <input type="text" name="table" id="edit-table">
          </div>
          <div class="field">
            <label>Meal Options <small>(comma-separated)</small></label>
            <input type="text" name="meal_options" id="edit-meals">
          </div>
        </div>
        <div class="field">
          <label>Personal Message</label>
          <textarea name="personal_message" id="edit-message" rows="3"></textarea>
        </div>
        <label class="chk-label">
          <input type="checkbox" name="plus_one_allowed" id="edit-plusone" value="1">
          Allow plus-one (up to 2 guests total)
        </label>
        <div class="modal-foot">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit')">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const GUESTS = <?= $guestsJson ?>;

function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}

function openEdit(token) {
  const g = GUESTS[token];
  if (!g) return;
  document.getElementById('edit-token').value       = g.token;
  document.getElementById('edit-name').value        = g.name || '';
  document.getElementById('edit-salutation').value  = g.salutation || '';
  document.getElementById('edit-table').value       = g.table || '';
  document.getElementById('edit-meals').value       = (g.meal_options || []).join(', ');
  document.getElementById('edit-message').value     = g.personal_message || '';
  document.getElementById('edit-plusone').checked   = !!g.plus_one_allowed;
  openModal('modal-edit');
}

function copyLink(btn, url) {
  navigator.clipboard.writeText(url).then(() => {
    btn.textContent = '✓';
    setTimeout(() => btn.textContent = '⎘', 1800);
  }).catch(() => {
    prompt('Copy this invite link:', url);
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});
</script>

<?php endif; ?>
</body>
</html>
