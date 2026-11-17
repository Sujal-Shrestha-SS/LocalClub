<?php
// Shared helpers for the interface layer. No business logic lives here.

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** Short club monogram badge (used where no crest image exists). */
if (!function_exists('crest')) {
    function crest($name, $extra = '') {
        $name = trim((string)$name);
        if ($name === '') $name = '?';
        $words = preg_split('/\s+/u', $name);
        if (count($words) > 1) {
            $ini = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
        } else {
            $ini = mb_substr($name, 0, 3);
        }
        $hue = crc32($name) % 360;
        return '<span class="crest ' . h($extra) . '" style="--h:' . $hue . '" aria-hidden="true">'
             . h(mb_strtoupper($ini)) . '</span>';
    }
}

/** Last N completed results for a club, oldest first: ['W','D','L',...] */
if (!function_exists('club_form')) {
    function club_form($conn, $season_id, $club, $limit = 5) {
        $season_id = (int)$season_id;
        $limit = (int)$limit;
        $c = mysqli_real_escape_string($conn, (string)$club);
        $res = mysqli_query($conn,
            "SELECT home_team, away_team, home_score, away_score FROM fixtures
             WHERE season_id=$season_id AND status='Match Completed'
             AND (home_team='$c' OR away_team='$c') ORDER BY id DESC LIMIT $limit");
        $out = [];
        while ($res && ($r = mysqli_fetch_assoc($res))) {
            $mine   = ($r['home_team'] === $club) ? (int)$r['home_score'] : (int)$r['away_score'];
            $theirs = ($r['home_team'] === $club) ? (int)$r['away_score'] : (int)$r['home_score'];
            $out[] = $mine > $theirs ? 'W' : ($mine == $theirs ? 'D' : 'L');
        }
        return array_reverse($out);
    }
}

/** Little football icon used in goal lists. */
if (!function_exists('ball_icon')) {
    function ball_icon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
             . '<circle cx="12" cy="12" r="9"/><path d="m12 8 3.5 2.5-1.3 4h-4.4l-1.3-4z"/></svg>';
    }
}
