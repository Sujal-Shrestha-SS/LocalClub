# LocalClub – eFootball League

A PHP + MySQL web app for tracking one club's eFootball seasons. Visitors can browse seasons, squads, line-ups, fixtures, results, player stats and match videos. An admin can create seasons, manage players, enter results and goal stats, and upload footage.

## Features

**Public site**
- Season list (`index.php`) and per-season squad page (`club.php`)
- Saved line-up and formation view (`lineup.php`)
- Fixtures and results with home/away filters (`club_fixtures.php`)
- Player statistics: goals, assists, performance and player-combination stats
- Player profiles with career history and results against opponents
- Media page with match videos (`club_media.php`)

**Admin area** (login required)
- Create seasons, set the club name and final league position
- Add, edit and delete players, with photos and one shared flag image per nationality
- Create fixtures, enter scores and goal scorers/assists
- Drag-and-drop line-up builder with formation, bench and captain (`lineup_admin.php`)
- Upload match videos, with custom or captured thumbnails (`videos.php`)
- Cross-season player history and players-by-nationality views
- Duplicate flag clean-up tool (`dedupe_flags.php`), which keeps a backup table

## Tech stack

- PHP 7.4+ (uses `mysqli`, `mbstring`)
- MySQL or MariaDB
- Plain HTML, CSS and JavaScript (no build step, no Composer or npm)
- Google Fonts, loaded from the CDN. The pages still work offline with fallback fonts.

## Project structure

```
LocalClub/
├── index.php                 Public season list
├── club.php                  Squad for a season
├── club_*.php                Public fixtures, media, stats pages
├── lineup.php                Public line-up view
├── player_*.php              Player profile, history, opponents, stats
├── admin_login.php           Admin sign-in
├── admin_sessioncheck.php    Guard included by admin pages
├── home.php                  Admin: seasons dashboard
├── season.php                Admin: fixtures
├── fixture_details.php       Admin: scores and goal stats
├── insert_players.php        Admin: players
├── lineup_admin.php          Admin: line-up builder
├── league_table.php          Admin: club name and position
├── videos.php                Admin: video uploads
├── db.php                    Database connection settings
├── head.php / nav.php        Shared layout
├── helpers.php               Shared helper functions
├── scripts/                  ui.js, lineup.js
├── styles/                   theme.css, lineup.css
└── uploads/                  Created by you (see setup), holds images and videos
```

## Setup

### 1. Prerequisites

Install a local PHP and MySQL stack. Any of these works:

- [XAMPP](https://www.apachefriends.org/) (Windows/macOS/Linux)
- WAMP or Laragon (Windows)
- MAMP (macOS)
- PHP and MySQL installed separately

### 2. Put the project in place

Copy the project folder into your web root, for example:

- XAMPP: `C:\xampp\htdocs\LocalClub` (or `/opt/lampp/htdocs/LocalClub`)
- MAMP: `/Applications/MAMP/htdocs/LocalClub`

### 3. Create the database

The app connects to a database named `efootball` (see `db.php`). The zip contains no SQL file, so create the tables yourself. Open phpMyAdmin or the MySQL CLI and run:

```sql
CREATE DATABASE IF NOT EXISTS efootball CHARACTER SET utf8mb4;
USE efootball;

CREATE TABLE seasons (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  title    VARCHAR(255) NOT NULL,
  club     VARCHAR(255) NULL,
  position VARCHAR(50)  NULL
) CHARACTER SET utf8mb4;

CREATE TABLE players (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  season_id    INT NULL,
  name         VARCHAR(255) NOT NULL,
  jersey_no    VARCHAR(10)  NULL,
  nationality  VARCHAR(100) NULL,
  category     VARCHAR(50)  NULL,   -- Goalkeeper / Defender / Midfielder / Forward
  position     VARCHAR(10)  NULL,   -- GK, CB, LB, RB, CM, CAM, RW, LW, ST
  picture      VARCHAR(255) NULL,
  flag_picture VARCHAR(255) NULL,
  INDEX (season_id)
) CHARACTER SET utf8mb4;

CREATE TABLE fixtures (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  season_id  INT NOT NULL,
  home_team  VARCHAR(255) NOT NULL,
  away_team  VARCHAR(255) NOT NULL,
  home_score INT NULL,
  away_score INT NULL,
  status     VARCHAR(50) NULL,      -- 'Match Completed' once a result is entered
  INDEX (season_id)
) CHARACTER SET utf8mb4;

CREATE TABLE goal_stats (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  fixture_id      INT NOT NULL,
  season_id       INT NOT NULL,
  team            VARCHAR(255) NULL,
  goal_scorer     VARCHAR(255) NULL,
  assist_provider VARCHAR(255) NULL,
  status          VARCHAR(50)  NULL,
  INDEX (fixture_id),
  INDEX (season_id)
) CHARACTER SET utf8mb4;

CREATE TABLE videos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  season_id INT NOT NULL,
  filename  VARCHAR(255) NOT NULL,
  title     VARCHAR(255) NULL,
  INDEX (season_id)
) CHARACTER SET utf8mb4;
```

The `lineups` and `players_flag_backup` tables, and the `videos.thumbnail` column, are created automatically the first time they're needed.

> These column definitions were worked out from the queries in the code. If you already have a working database from an earlier setup, export it and use that instead.

### 4. Configure the database connection

Edit `db.php` if your MySQL settings differ from the defaults:

```php
$host = "localhost";
$user = "root";
$pw   = "";          // XAMPP default is empty; MAMP uses "root"
$db   = "efootball";
```

### 5. Create the upload folders

The app saves images and videos under `uploads/`, which isn't included in the zip. Create these folders inside the project:

```
uploads/
uploads/flags/
uploads/videos/
uploads/videos/thumbs/
```

Then add a placeholder image named `uploads/default.png`. Pages use it whenever a player has no photo.

On Linux/macOS, make sure the web server can write to them:

```bash
chmod -R 775 uploads
```

For large video uploads, raise these in `php.ini` and restart Apache:

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
```

### 6. Run it

**Option A – Apache (XAMPP/MAMP/etc.):** start Apache and MySQL, then open

```
http://localhost/LocalClub/
```

**Option B – PHP's built-in server:** run this from the project folder (MySQL must already be running):

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/`.

## Using the app

1. Open `/admin_login.php` and sign in.
2. On the Seasons dashboard, click **+ Create season**.
3. Open the season and set the **Club info** (club name and position).
4. Add players, create fixtures and enter results and goal stats.
5. Build the line-up, upload videos, then browse the public site from `index.php`.

### Default admin credentials

The login is hardcoded in `admin_login.php`:

| Username | Password      |
|----------|---------------|
| `admin`  | `password123` |

Change these before exposing the app to anyone else.

## Security notes

This project is meant for local use. Before putting it online, you should:

- Change the hardcoded admin credentials and store hashed passwords in the database.
- Replace the `mysqli_real_escape_string` string-built queries with prepared statements. Some pages already use them.
- Restrict file types for uploads, and use a dedicated MySQL user instead of `root` with no password.
- Add CSRF protection to the admin forms.
- Note that `index.php` and `home.php` accept season creation without a login check on `index.php`. Remove that handler from the public page.

## Troubleshooting

| Problem | Fix |
|---|---|
| `Connection failed` | Check that MySQL is running and that `db.php` matches your host, user, password and database name. |
| `Table 'efootball.seasons' doesn't exist` | Run the SQL from step 3. |
| Images or videos don't save | Check that the `uploads/` folders exist and are writable. |
| Large video upload fails | Raise `upload_max_filesize` and `post_max_size` in `php.ini`. |
| Broken player photos | Add `uploads/default.png`. |
| Fonts look different offline | Google Fonts can't load without internet; the layout still works. |
