<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$guests_file = __DIR__ . '/config/guests.json';
$guests_data = json_decode(file_get_contents($guests_file), true);

$token    = trim($_POST['token']    ?? '');
$attending = trim($_POST['attending'] ?? '');

// Validate token
$guest_index = null;
foreach ($guests_data['guests'] as $i => $g) {
    if ($g['token'] === $token) {
        $guest_index = $i;
        break;
    }
}

if ($guest_index === null || $token === '') {
    header('Location: index.php?error=invalid_token');
    exit;
}

// Validate attending field
if (!in_array($attending, ['yes', 'no'], true)) {
    header('Location: invite.php?token=' . urlencode($token) . '&error=missing_field');
    exit;
}

$is_attending = ($attending === 'yes');

// Sanitize inputs
$guest_count = $is_attending ? max(1, min(2, (int)($_POST['guest_count'] ?? 1))) : 0;
$meal        = $is_attending ? trim(strip_tags($_POST['meal']     ?? '')) : '';
$dietary     = trim(strip_tags($_POST['dietary']  ?? ''));
$notes       = trim(strip_tags($_POST['notes']    ?? ''));

// Validate meal for attending guests
$allowed_meals = $guests_data['guests'][$guest_index]['meal_options'] ?? [];
if ($is_attending && !empty($allowed_meals) && !in_array($meal, $allowed_meals, true)) {
    header('Location: invite.php?token=' . urlencode($token) . '&error=invalid_meal');
    exit;
}

// Update guest record
$guests_data['guests'][$guest_index]['rsvp_status']    = 'submitted';
$guests_data['guests'][$guest_index]['rsvp_attending'] = $is_attending;
$guests_data['guests'][$guest_index]['rsvp_guests']    = $guest_count;
$guests_data['guests'][$guest_index]['rsvp_meal']      = $meal;
$guests_data['guests'][$guest_index]['rsvp_dietary']   = $dietary;
$guests_data['guests'][$guest_index]['rsvp_notes']     = $notes;
$guests_data['guests'][$guest_index]['rsvp_timestamp'] = (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);

// Persist to JSON
$written = file_put_contents(
    $guests_file,
    json_encode($guests_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($written === false) {
    header('Location: invite.php?token=' . urlencode($token) . '&error=save_failed');
    exit;
}

// Also write individual response file for easy export/backup
$response_dir = __DIR__ . '/rsvp_responses';
if (is_dir($response_dir)) {
    $response = [
        'token'          => $token,
        'name'           => $guests_data['guests'][$guest_index]['name'],
        'attending'      => $is_attending,
        'guest_count'    => $guest_count,
        'meal'           => $meal,
        'dietary'        => $dietary,
        'notes'          => $notes,
        'submitted_at'   => $guests_data['guests'][$guest_index]['rsvp_timestamp'],
    ];
    file_put_contents(
        $response_dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $token) . '.json',
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

header('Location: invite.php?token=' . urlencode($token) . '&rsvp=success');
exit;
