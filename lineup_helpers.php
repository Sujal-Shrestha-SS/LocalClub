<?php
// Shared helpers for the lineup feature (public view + admin builder).

/** Formations the builder offers. Numbers run from defence to attack (GK is always added first). */
function lineup_formations() {
    return [
        '4-4-2', '4-3-3', '4-2-3-1', '4-2-2-2', '4-1-4-1', '4-1-1-4', '4-5-1', '4-1-2-1-2',
        '3-5-2', '3-4-3', '3-4-1-2', '3-4-2-1', '5-3-2', '5-4-1', '4-3-2-1',
    ];
}

/** Create the table on first use so no manual SQL is needed. */
function lineup_ensure_table($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS lineups (
        season_id INT NOT NULL PRIMARY KEY,
        title VARCHAR(100) NOT NULL DEFAULT '',
        formation VARCHAR(20) NOT NULL DEFAULT '4-3-3',
        slots TEXT NOT NULL,
        bench TEXT NULL,
        captain INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4");
    // Older installs created the table before the bench / captain existed.
    foreach (['bench' => 'TEXT NULL', 'captain' => 'INT NULL'] as $name => $def) {
        $col = mysqli_query($conn, "SHOW COLUMNS FROM lineups LIKE '$name'");
        if ($col && mysqli_num_rows($col) === 0) {
            mysqli_query($conn, "ALTER TABLE lineups ADD COLUMN $name $def");
        }
    }
}

/** Number of substitute spots on the bench. */
function lineup_bench_size() { return 9; }

/** Saved lineup for a season (or a sensible empty default). */
function lineup_load($conn, $season_id) {
    lineup_ensure_table($conn);
    $season_id = (int)$season_id;
    $res = mysqli_query($conn, "SELECT title, formation, slots, bench, captain FROM lineups WHERE season_id = $season_id");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return ['title' => '', 'formation' => '4-3-3', 'slots' => array_fill(0, 11, null), 'bench' => array_fill(0, lineup_bench_size(), null), 'captain' => null, 'saved' => false];
    }
    $slots = json_decode($row['slots'], true);
    if (!is_array($slots)) $slots = [];
    $slots = array_slice(array_pad($slots, 11, null), 0, 11);
    $bench = json_decode((string)$row['bench'], true);
    if (!is_array($bench)) $bench = [];
    $bench = array_slice(array_pad($bench, lineup_bench_size(), null), 0, lineup_bench_size());
    $toId = function ($v) { return $v ? (int)$v : null; };
    return [
        'title'     => $row['title'],
        'formation' => in_array($row['formation'], lineup_formations(), true) ? $row['formation'] : '4-3-3',
        'slots'     => array_map($toId, $slots),
        'bench'     => array_map($toId, $bench),
        'captain'   => $row['captain'] ? (int)$row['captain'] : null,
        'saved'     => true,
    ];
}

/** Squad data trimmed to what the lineup graphic needs. */
function lineup_players($conn, $season_id) {
    $season_id = (int)$season_id;
    $res = mysqli_query($conn, "SELECT id, name, jersey_no, nationality, category, position, picture, flag_picture
                                FROM players WHERE season_id = $season_id ORDER BY jersey_no");
    $out = [];
    while ($res && ($p = mysqli_fetch_assoc($res))) {
        $out[] = [
            'id'       => (int)$p['id'],
            'name'     => $p['name'],
            'jersey'   => $p['jersey_no'],
            'country'  => $p['nationality'],
            'category' => $p['category'],
            'position' => $p['position'],
            'picture'  => $p['picture'],
            'flag'     => $p['flag_picture'],
        ];
    }
    return $out;
}
