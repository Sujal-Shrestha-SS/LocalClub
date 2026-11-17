<?php
// One flag image per nationality, shared by every player of that nationality.
// players.flag_picture still holds a path, so every existing page keeps working unchanged.

function flag_norm($s) { return mb_strtolower(trim((string)$s)); }

function flag_slug($nationality) {
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string)$nationality)));
    $s = trim($s, '-');
    return $s !== '' ? $s : 'flag';
}

/** Only touch files that live inside uploads/ (never follow ".." tricks). */
function flag_safe_path($path) {
    return is_string($path) && strpos($path, 'uploads/') === 0 && strpos($path, '..') === false;
}

/** The flag already saved for a nationality (most-used file that still exists), or ''. */
function flag_find_existing($conn, $nationality) {
    $key = flag_norm($nationality);
    if ($key === '') return '';
    $stmt = mysqli_prepare($conn, "SELECT flag_picture, COUNT(*) c FROM players
        WHERE LOWER(TRIM(nationality)) = ? AND flag_picture <> '' GROUP BY flag_picture ORDER BY c DESC");
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if (file_exists($row['flag_picture'])) return $row['flag_picture'];
    }
    return '';
}

/** nationality (lowercase) => flag path, for the form's "flag already saved" hint. */
function flag_map($conn) {
    $map = [];
    $res = mysqli_query($conn, "SELECT LOWER(TRIM(nationality)) n, flag_picture, COUNT(*) c FROM players
        WHERE flag_picture <> '' AND TRIM(nationality) <> '' GROUP BY n, flag_picture ORDER BY c DESC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!isset($map[$row['n']]) && file_exists($row['flag_picture'])) $map[$row['n']] = $row['flag_picture'];
    }
    return $map;
}

/** True if any player other than $exclude_id still points at this file. */
function flag_path_in_use($conn, $path, $exclude_id = 0) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM players WHERE flag_picture = ? AND id <> ?");
    mysqli_stmt_bind_param($stmt, 'si', $path, $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $count > 0;
}

/**
 * Save an uploaded flag as uploads/flags/<nationality>.<ext>.
 * Returns the saved path, or '' if nothing valid was uploaded.
 */
function flag_store_upload($file, $nationality) {
    if (!isset($file) || ($file['error'] ?? 1) !== 0 || !is_uploaded_file($file['tmp_name'])) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) return '';
    if ($ext !== 'svg' && @getimagesize($file['tmp_name']) === false) return '';
    $dir = 'uploads/flags/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $dest = $dir . flag_slug($nationality) . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dest) ? $dest : '';
}

/**
 * Point every player of this nationality at $path (so a replaced flag changes everywhere),
 * then delete any old flag files nobody uses any more.
 */
function flag_apply_to_nationality($conn, $nationality, $path) {
    $key = flag_norm($nationality);
    $old = [];
    $stmt = mysqli_prepare($conn, "SELECT DISTINCT flag_picture FROM players
        WHERE LOWER(TRIM(nationality)) = ? AND flag_picture <> '' AND flag_picture <> ?");
    mysqli_stmt_bind_param($stmt, 'ss', $key, $path);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $old[] = $row['flag_picture'];

    $stmt = mysqli_prepare($conn, "UPDATE players SET flag_picture = ? WHERE LOWER(TRIM(nationality)) = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $path, $key);
    mysqli_stmt_execute($stmt);

    foreach ($old as $p) {
        if (flag_safe_path($p) && file_exists($p) && !flag_path_in_use($conn, $p, 0)) @unlink($p);
    }
}
