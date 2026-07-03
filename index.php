<?php
$config = json_decode(file_get_contents(__DIR__ . '/config/event.json'), true);
$event  = $config;

$date_obj  = new DateTime($event['date'] . ' ' . $event['time_start'], new DateTimeZone($event['timezone']));
$formatted_date = $date_obj->format('l, F j, Y');
$formatted_time = $date_obj->format('g:i A');

$end_obj   = new DateTime($event['date'] . ' ' . $event['time_end'], new DateTimeZone($event['timezone']));
$formatted_end = $end_obj->format('g:i A');

$now = new DateTime('now', new DateTimeZone($event['timezone']));
$diff = $now->diff($date_obj);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($event['title']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav>
  <a class="nav-brand" href="index.php"><?= htmlspecialchars($event['title']) ?></a>
  <ul class="nav-links">
    <li><a href="#details">Details</a></li>
    <li><a href="#dress-code">Attire</a></li>
    <li><a href="#notes">Notes</a></li>
    <li><a href="#access">My Invite</a></li>
  </ul>
</nav>

<!-- Hero -->
<section class="hero" style="padding-top:5rem;">
  <div class="hero-particles"></div>
  <div class="hero-content">
    <div class="hero-ornament">✦ &nbsp; ✦ &nbsp; ✦</div>
    <p class="hero-tagline"><?= htmlspecialchars($event['tagline']) ?></p>
    <h1><?= htmlspecialchars($event['title']) ?></h1>
    <div class="hero-divider"><span>◆</span></div>
    <p class="hero-date"><?= $formatted_date ?> &nbsp;·&nbsp; <?= $formatted_time ?> – <?= $formatted_end ?> <?= $event['timezone'] ?></p>

    <!-- Countdown -->
    <?php if ($date_obj > $now): ?>
    <div class="countdown" id="countdown"
         data-target="<?= $date_obj->format('Y-m-d\TH:i:s') ?>"
         data-tz="<?= htmlspecialchars($event['timezone']) ?>">
      <div class="countdown-unit"><span class="countdown-number" id="cd-days"><?= $diff->days ?></span><span class="countdown-label">Days</span></div>
      <div class="countdown-unit"><span class="countdown-number" id="cd-hours"><?= $diff->h ?></span><span class="countdown-label">Hours</span></div>
      <div class="countdown-unit"><span class="countdown-number" id="cd-minutes"><?= $diff->i ?></span><span class="countdown-label">Minutes</span></div>
      <div class="countdown-unit"><span class="countdown-number" id="cd-seconds"><?= $diff->s ?></span><span class="countdown-label">Seconds</span></div>
    </div>
    <?php endif; ?>

    <a href="#access" class="btn">Access My Invitation</a>
  </div>
</section>

<!-- Event Details -->
<section id="details">
  <div class="container">
    <p class="section-label">Event Details</p>
    <h2 class="section-title">An Evening to Remember</h2>
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
        <div class="detail-sub"><?= htmlspecialchars($event['location']['address']) ?>, <?= htmlspecialchars($event['location']['city']) ?>, <?= htmlspecialchars($event['location']['state']) ?></div>
        <a class="map-link" href="<?= htmlspecialchars($event['location']['maps_url']) ?>" target="_blank" rel="noopener">
          ↗ View on Map
        </a>
      </div>
      <div class="detail-item">
        <div class="detail-icon">📮</div>
        <div class="detail-label">RSVP By</div>
        <div class="detail-value"><?= (new DateTime($event['rsvp_deadline']))->format('F j, Y') ?></div>
        <div class="detail-sub">Kindly respond before this date</div>
      </div>
    </div>
  </div>
</section>

<!-- Dress Code -->
<section id="dress-code" style="background: var(--primary);">
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
<section id="notes">
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
<section id="host" style="background: var(--primary);">
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

<!-- Invite Access -->
<section id="access" style="background: var(--bg);">
  <div class="container text-center">
    <p class="section-label">Your Invitation</p>
    <h2 class="section-title">Access Your Personal Invite</h2>
    <div class="section-divider" style="margin:0 auto 2rem;"></div>
    <p class="landing-intro">
      Each guest has received a unique invitation code. Enter your code below to view your personalised invitation and RSVP.
    </p>
    <div class="token-form-wrap">
      <form method="GET" action="invite.php">
        <div class="token-input-group">
          <input type="text" name="token" placeholder="Enter your invite code" required
                 maxlength="20" autocomplete="off"
                 value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
          <button type="submit" class="btn btn-solid">Go</button>
        </div>
        <p class="form-note mt-1">Your code was included in your invitation email.</p>
      </form>
    </div>
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
<script src="assets/js/countdown.js"></script>

</body>
</html>
