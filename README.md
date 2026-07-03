# eInvite

A PHP-based digital invitation system for private events. Each guest receives a unique invite link with a personal greeting and RSVP form. The host manages the guest list and views responses through a password-protected admin panel.

## Features

- **Landing page** — event details, countdown timer, dress code, and invite code entry
- **Personal invite page** — per-guest greeting, seating info, and RSVP form
- **RSVP handling** — meal preference, dietary restrictions, plus-one support
- **Admin panel** — add/edit/delete guests, view RSVP status, export CSV, change password
- **Background music** — optional audio player with autoplay and session memory

## Requirements

- PHP 8.0+
- A web server (Apache via XAMPP, Nginx, etc.)
- Write permissions on the `config/` directory

## Setup

1. Clone or copy the project into your web server's document root.
2. Edit `config/event.json` to set your event details (title, date, location, host, dress code, etc.).
3. Open `admin.php` in a browser and sign in with the default credentials:
   - **Username:** `admin`
   - **Password:** `changeme123`
4. Change the password immediately via **Settings → Change Password**.
5. Add guests using **+ Add Guest**. Each guest gets a unique 8-character invite token.
6. Share each guest's invite link: `https://yourdomain.com/invite.php?token=<token>`

## Project Structure

```
├── index.php          # Public landing page
├── invite.php         # Per-guest invitation page
├── rsvp.php           # RSVP form handler
├── admin.php          # Admin dashboard
├── assets/
│   ├── css/
│   │   ├── style.css       # Shared styles (index, invite)
│   │   └── admin.css       # Admin panel styles
│   ├── js/
│   │   ├── music-player.js # Background audio player
│   │   ├── countdown.js    # Event countdown timer
│   │   ├── invite.js       # RSVP form show/hide logic
│   │   └── admin.js        # Admin modals and clipboard
│   └── audio/
│       └── background.mp3  # Optional background music
└── config/
    ├── event.json     # Event configuration
    ├── guests.json    # Guest list and RSVP data
    └── admin.json     # Admin credentials (auto-generated)
```

## Configuration

All event details are stored in `config/event.json`:

```json
{
  "title": "Annual Gala Night 2026",
  "tagline": "An Evening of Elegance & Celebration",
  "date": "2026-08-15",
  "time_start": "19:00",
  "time_end": "23:00",
  "timezone": "America/New_York",
  "location": { ... },
  "host": { ... },
  "dress_code": { ... },
  "notes": [ ... ],
  "rsvp_deadline": "2026-08-01"
}
```

## Background Music

Place an MP3 file at `assets/audio/background.mp3`. The player respects the user's session — if they pause it, it stays paused across pages. Remove the `<audio>` element and music player markup from `index.php` and `invite.php` if you don't want music.

## Exporting RSVPs

In the admin panel, click **↓ Export CSV** to download a spreadsheet of all guest responses including attendance, meal choices, dietary notes, and submission timestamps.

## Security Notes

- Change the default admin password before sharing the site.
- The `config/` directory contains sensitive data. Consider blocking direct HTTP access to it via `.htaccess` or Nginx config.
- CSRF tokens protect all admin form submissions.
