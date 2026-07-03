<?php
$config      = json_decode(file_get_contents(__DIR__ . '/config/event.json'),  true);
$guests_data = json_decode(file_get_contents(__DIR__ . '/config/guests.json'), true);
$event       = $config;

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    header('Location: index.php');
    exit;
}

// Find guest by token
$guest = null;
foreach ($guests_data['guests'] as $g) {
    if ($g['token'] === $token) {
        $guest = $g;
        break;
    }
}

if ($guest === null) {
    header('Location: index.php?error=invalid_token');
    exit;
}

$date_obj      = new DateTime($event['date'] . ' ' . $event['time_start'], new DateTimeZone($event['timezone']));
$end_obj       = new DateTime($event['date'] . ' ' . $event['time_end'],   new DateTimeZone($event['timezone']));
$formatted_date = $date_obj->format('l, F j, Y');
$formatted_time = $date_obj->format('g:i A');
$formatted_end  = $end_obj->format('g:i A');
$rsvp_deadline  = (new DateTime($event['rsvp_deadline']))->format('F j, Y');

$already_rsvp = $guest['rsvp_status'] !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Invitation – <?= htmlspecialchars($event['title']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav>
  <a class="nav-brand" href="index.php"><?= htmlspecialchars($event['title']) ?></a>
  <ul class="nav-links">
    <li><a href="#details">Details</a></li>
    <li><a href="#rsvp">RSVP</a></li>
  </ul>
</nav>

<!-- Hero -->
<section class="hero" style="padding-top:5rem; min-height:70vh;">
  <div class="hero-particles"></div>
  <div class="hero-content">
    <div class="hero-ornament">✦ &nbsp; ✦ &nbsp; ✦</div>
    <p class="hero-tagline">You are cordially invited</p>
    <h1><?= htmlspecialchars($event['title']) ?></h1>
    <div class="hero-divider"><span>◆</span></div>
    <p class="hero-date"><?= $formatted_date ?> &nbsp;·&nbsp; <?= $formatted_time ?> – <?= $formatted_end ?></p>
  </div>
</section>

<!-- Personal Greeting -->
<section>
  <div class="container">
    <p class="section-label">A Personal Note</p>
    <h2 class="guest-name-heading">
      Dear <?= htmlspecialchars($guest['salutation'] ? $guest['salutation'] . ' ' : '') ?><?= htmlspecialchars($guest['name']) ?>,
    </h2>
    <div class="section-divider"></div>
    <div class="personal-message">
      <?= htmlspecialchars($guest['personal_message']) ?>
    </div>

    <!-- RSVP status badge -->
    <?php if ($already_rsvp): ?>
      <?php if ($guest['rsvp_attending']): ?>
        <div class="alert alert-success">
          ✓ You have confirmed your attendance. We look forward to seeing you!
          <?php if ($guest['rsvp_guests'] > 1): ?> (<?= (int)$guest['rsvp_guests'] ?> guests)<?php endif; ?>
        </div>
      <?php else: ?>
        <div class="alert alert-info">
          You have declined this invitation. If your plans change, please reach out to the host.
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Event Details -->
<section id="details" style="background: var(--primary); padding-top: 3rem; padding-bottom: 3rem;">
  <div class="container">
    <p class="section-label">Event Details</p>
    <h2 class="section-title">What to Expect</h2>
    <div class="section-divider"></div>

    <div class="details-grid">
      <div class="detail-item">
        <div class="detail-icon">📅</div>
        <div class="detail-label">Date</div>
        <div class="detail-value"><?= $date_obj->format('F j, Y') ?></div>
        <div class="detail-sub"><?= $date_obj->format('l') ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-icon">🕖</div>
        <div class="detail-label">Time</div>
        <div class="detail-value"><?= $formatted_time ?></div>
        <div class="detail-sub">until <?= $formatted_end ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-icon">📍</div>
        <div class="detail-label">Venue</div>
        <div class="detail-value"><?= htmlspecialchars($event['location']['name']) ?></div>
        <div class="detail-sub">
          <?= htmlspecialchars($event['location']['address']) ?>,
          <?= htmlspecialchars($event['location']['city']) ?>,
          <?= htmlspecialchars($event['location']['state']) ?> <?= htmlspecialchars($event['location']['zip']) ?>
        </div>
        <a class="map-link" href="<?= htmlspecialchars($event['location']['maps_url']) ?>" target="_blank" rel="noopener">
          ↗ Get Directions
        </a>
      </div>
      <?php if (!empty($guest['table'])): ?>
      <div class="detail-item">
        <div class="detail-icon">🪑</div>
        <div class="detail-label">Seating</div>
        <div class="detail-value"><?= htmlspecialchars($guest['table']) ?></div>
        <div class="detail-sub">Your reserved seat</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Dress Code -->
<section id="attire">
  <div class="container">
    <p class="section-label">Attire</p>
    <h2 class="section-title"><?= htmlspecialchars($event['dress_code']['label']) ?></h2>
    <div class="section-divider"></div>
    <div class="card">
      <p><?= htmlspecialchars($event['dress_code']['description']) ?></p>
      <?php if (!empty($event['dress_code']['color_palette'])): ?>
      <div class="dress-swatches">
        <?php foreach ($event['dress_code']['color_palette'] as $color): ?>
          <div class="swatch" style="background:<?= htmlspecialchars($color) ?>;" title="<?= htmlspecialchars($color) ?>"></div>
        <?php endforeach; ?>
        <span style="font-size:0.8rem;color:var(--text-muted);align-self:center;">Suggested palette</span>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Notes -->
<section id="notes" style="background: var(--primary);">
  <div class="container">
    <p class="section-label">Please Note</p>
    <h2 class="section-title">Additional Information</h2>
    <div class="section-divider"></div>
    <ul class="notes-list">
      <?php foreach ($event['notes'] as $note): ?>
        <li><?= htmlspecialchars($note) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- Host -->
<section id="host">
  <div class="container">
    <p class="section-label">Hosted By</p>
    <h2 class="section-title">Your Host</h2>
    <div class="section-divider"></div>
    <div class="card">
      <div class="host-card">
        <div class="host-avatar">
          <?= strtoupper(substr($event['host']['name'], 0, 1)) ?>
        </div>
        <div class="host-info">
          <h3><?= htmlspecialchars($event['host']['name']) ?></h3>
          <p><?= htmlspecialchars($event['host']['phone']) ?></p>
          <a href="mailto:<?= htmlspecialchars($event['host']['email']) ?>"><?= htmlspecialchars($event['host']['email']) ?></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RSVP -->
<section id="rsvp" class="rsvp-section">
  <div class="container">
    <p class="section-label">RSVP</p>
    <h2 class="section-title"><?= $already_rsvp ? 'Update Your Response' : 'Kindly Respond' ?></h2>
    <div class="section-divider"></div>
    <p style="color:var(--text-muted); margin-bottom:2rem;">Please respond by <strong style="color:var(--accent)"><?= $rsvp_deadline ?></strong>.</p>

    <form method="POST" action="rsvp.php">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <!-- Attending? -->
      <div class="form-group">
        <label>Will you attend? *</label>
        <div class="radio-group">
          <label class="radio-label">
            <input type="radio" name="attending" value="yes" required
                   <?= ($guest['rsvp_attending'] === true) ? 'checked' : '' ?>>
            Joyfully Accepts
          </label>
          <label class="radio-label">
            <input type="radio" name="attending" value="no"
                   <?= ($guest['rsvp_attending'] === false) ? 'checked' : '' ?>>
            Regretfully Declines
          </label>
        </div>
      </div>

      <!-- Plus one -->
      <?php if ($guest['plus_one_allowed']): ?>
      <div class="form-group" id="plus-one-group">
        <label>Number of Guests Attending (including yourself)</label>
        <select name="guest_count">
          <option value="1" <?= ($guest['rsvp_guests'] == 1) ? 'selected' : '' ?>>1 – Just me</option>
          <option value="2" <?= ($guest['rsvp_guests'] == 2) ? 'selected' : '' ?>>2 – Myself + 1 guest</option>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="guest_count" value="1">
      <?php endif; ?>

      <!-- Meal preference -->
      <?php if (!empty($guest['meal_options'])): ?>
      <div class="form-group" id="meal-group">
        <label>Meal Preference *</label>
        <select name="meal" required>
          <option value="" disabled <?= empty($guest['rsvp_meal']) ? 'selected' : '' ?>>Select your preference</option>
          <?php foreach ($guest['meal_options'] as $meal): ?>
            <option value="<?= htmlspecialchars($meal) ?>" <?= ($guest['rsvp_meal'] === $meal) ? 'selected' : '' ?>>
              <?= htmlspecialchars($meal) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <!-- Dietary restrictions -->
      <div class="form-group" id="dietary-group">
        <label>Dietary Restrictions or Allergies</label>
        <input type="text" name="dietary"
               placeholder="e.g. Gluten-free, Nut allergy, Vegan…"
               value="<?= htmlspecialchars($guest['rsvp_dietary'] ?? '') ?>">
        <p class="form-note">Leave blank if none.</p>
      </div>

      <!-- Notes -->
      <div class="form-group" id="notes-group">
        <label>Any Additional Notes</label>
        <textarea name="notes" rows="3"
                  placeholder="Anything you'd like the host to know…"><?= htmlspecialchars($guest['rsvp_notes'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-solid"><?= $already_rsvp ? 'Update RSVP' : 'Submit RSVP' ?></button>
    </form>
  </div>
</section>

<footer>
  <p><?= htmlspecialchars($event['title']) ?> &nbsp;·&nbsp; <?= $date_obj->format('Y') ?></p>
  <p style="margin-top:0.5rem;">Hosted by <?= htmlspecialchars($event['host']['name']) ?></p>
</footer>

<audio id="bg-music" loop preload="auto">
  <source src="assets/audio/background.mp3" type="audio/mpeg">
</audio>

<div class="music-player">
  <span class="music-label" id="music-label">Play music</span>
  <button class="music-btn pulse" id="music-btn" aria-label="Toggle background music" title="Toggle music">♫</button>
</div>

<script src="assets/js/music-player.js"></script>
<script src="assets/js/invite.js"></script>

</body>
</html>
