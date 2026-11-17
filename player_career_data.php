<?php
/**
 * player_career_data.php
 *
 * The `players` table stores one row PER SEASON (a fresh squad is inserted
 * every time a new season/club is created), and goal_stats links goals /
 * assists to a player by matching his name as free text within that same
 * season. There is no single "player_id" that already spans seasons.
 *
 * To build a career history we treat two player rows as "the same player"
 * when their names match (trimmed, case-insensitive) - the same rule the
 * rest of the app already uses to join goal_stats to players, just applied
 * across every season instead of one. See the note at the bottom of this
 * file for a more robust, longer-term alternative.
 *
 * get_all_player_careers($conn) returns an array keyed by a normalized
 * name key:
 * [
 *   'lionel messi' => [
 *       'display_name'  => 'Lionel Messi',
 *       'picture'       => 'uploads/xxx.png',   // most recent photo on file
 *       'nationality'   => 'Argentina',
 *       'flag_picture'  => 'uploads/xxx.png',
 *       'seasons'       => [
 *           season_id => [
 *               'season_title' => '...', 'club' => '...', 'jersey_no' => ..,
 *               'category' => '...', 'position' => '...',
 *               'goals' => n, 'assists' => n
 *           ], ...
 *       ],
 *       'total_goals'   => n,
 *       'total_assists' => n,
 *       'total_ga'      => n,
 *       'seasons_count' => n,
 *       'clubs_list'    => ['Arsenal', 'Real Madrid', ...],
 *   ], ...
 * ]
 */

function normalize_player_name($name) {
    return strtolower(trim($name));
}

function get_all_player_careers($conn) {
    $careers = [];

    // Every squad-entry a name has ever had, across every season
    $playersResult = mysqli_query($conn, "
        SELECT p.id, p.name, p.jersey_no, p.nationality, p.category, p.position,
               p.picture, p.flag_picture,
               s.id AS season_id, s.title AS season_title, s.club AS club_name
        FROM players p
        JOIN seasons s ON p.season_id = s.id
        ORDER BY p.id ASC
    ");

    while ($row = mysqli_fetch_assoc($playersResult)) {
        $key = normalize_player_name($row['name']);
        if ($key === '') continue;

        if (!isset($careers[$key])) {
            $careers[$key] = [
                'display_name' => $row['name'],
                'picture'      => '',
                'flag_picture' => '',
                'nationality'  => $row['nationality'],
                'category'     => $row['category'],
                'seasons'      => [],
            ];
        }

        // Later rows (higher id) represent the most recent look/spelling
        $careers[$key]['display_name'] = $row['name'];
        $careers[$key]['nationality']  = $row['nationality'];
        $careers[$key]['category']     = $row['category']; // most recent position group (Goalkeeper/Defender/Midfielder/Forward)
        if (!empty($row['picture']))      $careers[$key]['picture'] = $row['picture'];
        if (!empty($row['flag_picture'])) $careers[$key]['flag_picture'] = $row['flag_picture'];

        $careers[$key]['seasons'][$row['season_id']] = [
            'season_title' => $row['season_title'],
            'club'         => $row['club_name'],
            'jersey_no'    => $row['jersey_no'],
            'category'     => $row['category'],
            'position'     => $row['position'],
            'goals'        => 0,
            'assists'      => 0,
        ];
    }

    // Tally goals / assists per season, matched by normalized name
    $statsResult = mysqli_query($conn, "SELECT season_id, goal_scorer, assist_provider FROM goal_stats");

    while ($row = mysqli_fetch_assoc($statsResult)) {
        $season_id = $row['season_id'];

        $scorerKey = normalize_player_name($row['goal_scorer']);
        if ($scorerKey !== '' && isset($careers[$scorerKey]['seasons'][$season_id])) {
            $careers[$scorerKey]['seasons'][$season_id]['goals']++;
        }

        $assistKey = normalize_player_name($row['assist_provider']);
        if ($assistKey !== '' && isset($careers[$assistKey]['seasons'][$season_id])) {
            $careers[$assistKey]['seasons'][$season_id]['assists']++;
        }
    }

    // Matches played per season. The app doesn't track individual line-ups/
    // appearances - only who scored/assisted - so this is the number of
    // completed fixtures the CLUB played that season, applied to every
    // player who was on that season's squad.
    $matchesResult = mysqli_query($conn, "
        SELECT season_id, COUNT(*) AS matches
        FROM fixtures
        WHERE status = 'Match Completed'
        GROUP BY season_id
    ");
    $matchesBySeason = [];
    while ($row = mysqli_fetch_assoc($matchesResult)) {
        $matchesBySeason[$row['season_id']] = (int)$row['matches'];
    }
    foreach ($careers as $key => &$c) {
        foreach ($c['seasons'] as $season_id => &$s) {
            $s['matches'] = $matchesBySeason[$season_id] ?? 0;
        }
        unset($s);
    }
    unset($c);

    // Roll up totals
    foreach ($careers as $key => &$c) {
        $goals = 0; $assists = 0; $matches = 0; $clubs = [];
        foreach ($c['seasons'] as $s) {
            $goals   += $s['goals'];
            $assists += $s['assists'];
            $matches += $s['matches'];
            if (!empty($s['club'])) $clubs[$s['club']] = true;
        }
        $c['total_goals']   = $goals;
        $c['total_assists'] = $assists;
        $c['total_ga']      = $goals + $assists;
        $c['total_matches'] = $matches;
        $c['seasons_count'] = count($c['seasons']);
        $c['clubs_list']    = array_keys($clubs);
    }
    unset($c);

    return $careers;
}

/**
 * SUGGESTED LONGER-TERM IMPROVEMENT
 * ----------------------------------
 * Name-matching works today, but it will silently split one real player
 * into two "careers" if his name is ever typed differently between seasons
 * (nickname, spelling, extra space), and will silently merge two different
 * players who happen to share a name.
 *
 * A more robust design: add a `player_profiles` table (id, canonical_name,
 * date_of_birth, default photo, etc.) and a `player_profile_id` foreign key
 * on both `players` and `goal_stats`. When adding a player in
 * insert_players.php, the admin would pick "link to existing profile" or
 * "create new profile" instead of the name being re-typed from scratch.
 * That removes the name-matching guesswork entirely and also lets the
 * admin explicitly merge/fix mistakes later. Happy to build this if you
 * want the sturdier version.
 */