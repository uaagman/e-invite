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
  <link rel="stylesheet" href="assets/css/admin.css">
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

<script>const GUESTS = <?= $guestsJson ?>;</script>
<script src="assets/js/admin.js"></script>

<?php endif; ?>
</body>
</html>
