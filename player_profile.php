<?php
session_start();
include 'db.php';

include 'admin_sessioncheck.php';
include 'player_career_data.php';

$career = null;
if (isset($_GET['player']) && trim($_GET['player']) !== '') {
    // $_GET is already URL-decoded by PHP
    $playerKey = normalize_player_name($_GET['player']);
    $careers = get_all_player_careers($conn);
    if (isset($careers[$playerKey])) {
        $career = $careers[$playerKey];
    }
}

$page_title = ($career ? $career['display_name'] . ' | Career history' : 'Player not found') . ' | Admin';
$nav_mode = 'global';

if ($career) {
    // Season rows sorted by season id (roughly chronological order of creation)
    $seasonRows = $career['seasons'];
    ksort($seasonRows);

    // Against each club, across every season he was in the squad.
    // Appearances aren't recorded anywhere, so "matches" = every completed match against that club
    // in a season where he was in the squad. Goals/assists then come from goal_stats.
    // The opponent is the other side of the fixture; a "Home" goal was scored against the away team.
    $vsClubs = [];   // normalized club => label, goals, assists, matches
    $vsMatches = []; // fixture id => details

    $addMatch = function ($fid, $opp, $row, $done) use (&$vsClubs, &$vsMatches) {
        $ck = mb_strtolower(trim($opp));
        if (!isset($vsClubs[$ck])) $vsClubs[$ck] = ['label' => trim($opp), 'goals' => 0, 'assists' => 0, 'matches' => []];
        if (!isset($vsMatches[$fid])) {
            $vsMatches[$fid] = ['club' => $ck, 'season' => $row['season_title'] ?? '', 'home' => $row['home_team'], 'away' => $row['away_team'],
                                'hs' => $row['home_score'], 'as' => $row['away_score'], 'done' => $done, 'goals' => 0, 'assists' => 0, 'sid' => (int)$row['season_id']];
        }
        $vsClubs[$ck]['matches'][$fid] = true;
        return $ck;
    };

    // Step 1: every completed match of his seasons, against whoever the opponent was.
    $seasonIds = array_map('intval', array_keys($career['seasons']));
    if ($seasonIds) {
        $in = implode(',', $seasonIds);
        $fx = mysqli_query($conn, "SELECT f.id, f.season_id, f.home_team, f.away_team, f.home_score, f.away_score, f.status,
                s.title AS season_title, s.club AS season_club
            FROM fixtures f LEFT JOIN seasons s ON s.id = f.season_id
            WHERE f.season_id IN ($in) ORDER BY f.season_id, f.id");
        while ($fx && ($f = mysqli_fetch_assoc($fx))) {
            $done = $f['status'] === 'Match Completed' || strtolower($f['status']) === 'finished';
            if (!$done) continue;
            if (strcasecmp(trim($f['home_team']), trim((string)$f['season_club'])) === 0)      $opp = $f['away_team'];
            elseif (strcasecmp(trim($f['away_team']), trim((string)$f['season_club'])) === 0)  $opp = $f['home_team'];
            else continue; // not one of this club's matches
            $addMatch((int)$f['id'], $opp, $f, true);
        }
    }

    // Step 2: his goals and assists in those matches.
    $statsRes = mysqli_query($conn, "SELECT g.fixture_id, g.team, g.goal_scorer, g.assist_provider,
            f.home_team, f.away_team, f.home_score, f.away_score, f.status, f.season_id,
            s.title AS season_title, s.club AS season_club
        FROM goal_stats g
        JOIN fixtures f ON g.fixture_id = f.id
        LEFT JOIN seasons s ON s.id = f.season_id
        ORDER BY f.season_id, f.id");
    while ($statsRes && ($r = mysqli_fetch_assoc($statsRes))) {
        $isGoal   = normalize_player_name($r['goal_scorer']) === $playerKey;
        $isAssist = normalize_player_name($r['assist_provider']) === $playerKey;
        if (!$isGoal && !$isAssist) continue;

        if ($r['team'] === 'Home')     $opp = $r['away_team'];
        elseif ($r['team'] === 'Away') $opp = $r['home_team'];
        else $opp = (strcasecmp($r['home_team'], (string)$r['season_club']) === 0) ? $r['away_team'] : $r['home_team'];

        $fid = (int)$r['fixture_id'];
        $ck = $addMatch($fid, $opp, $r, $r['status'] === 'Match Completed'); // also covers a match not yet marked completed
        if ($isGoal)   { $vsClubs[$ck]['goals']++;   $vsMatches[$fid]['goals']++; }
        if ($isAssist) { $vsClubs[$ck]['assists']++; $vsMatches[$fid]['assists']++; }
    }
    uasort($vsMatches, function ($a, $b) { return $a['sid'] <=> $b['sid']; });
    uasort($vsClubs, function ($a, $b) {
        return ($b['goals'] + $b['assists']) <=> ($a['goals'] + $a['assists']) ?: strcasecmp($a['label'], $b['label']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'head.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="page stack">
  <div>
    <a class="back-link" href="player_history.php">← Back to player history</a>
  </div>

<?php if (!$career): ?>
  <div class="empty">
    <h3>Player not found</h3>
    <p>This player isn't on record. They may have been removed from every season.</p>
    <a class="btn" href="player_history.php">Back to player history</a>
  </div>

<?php else: ?>
  <section class="panel">
    <div class="profile-head">
      <div class="profile-photo-wrap">
        <img class="profile-photo" src="<?php echo h($career['picture'] ?: 'uploads/default.png'); ?>" alt="<?php echo h($career['display_name']); ?>">
        <?php if (!empty($career['flag_picture'])): ?>
          <img class="flag-chip" src="<?php echo h($career['flag_picture']); ?>" alt="<?php echo h($career['nationality']); ?> flag">
        <?php endif; ?>
      </div>
      <div class="profile-meta">
        <h1><?php echo h($career['display_name']); ?></h1>
        <div class="row">
          <span class="pos pos-<?php echo h($career['category']); ?>"><?php echo h($career['category']); ?></span>
          <span class="nat"><?php echo h($career['nationality']); ?></span>
        </div>
        <div class="row">
          <?php if ($career['clubs_list']): foreach ($career['clubs_list'] as $club): ?>
            <span class="badge badge-ft"><?php echo h($club); ?></span>
          <?php endforeach; else: ?>
            <span class="muted">No club recorded</span>
          <?php endif; ?>
        </div>
        <div class="muted"><?php echo (int)$career['seasons_count']; ?> season<?php echo $career['seasons_count'] == 1 ? '' : 's'; ?> on record</div>
      </div>
    </div>
  </section>

 <div class="emo lg">
  <span>
    <i class="stat-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"></circle>
        <path d="M12 7l2.4 1.8-.9 2.9h-3l-.9-2.9L12 7z"></path>
        <path d="M12 7V3"></path>
        <path d="M14.4 8.8l3.4-2"></path>
        <path d="M13.5 11.7l2.8 2"></path>
        <path d="M10.5 11.7l-2.8 2"></path>
        <path d="M9.6 8.8l-3.4-2"></path>
      </svg>
    </i>
    <?php echo (int)$career['total_goals']; ?>
    <small>Goals</small>
  </span>

  <span>
    <i class="stat-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"></circle>
        <circle cx="12" cy="12" r="5"></circle>
        <circle cx="12" cy="12" r="2"></circle>
      </svg>
    </i>
    <?php echo (int)$career['total_assists']; ?>
    <small>Assists</small>
  </span>

  <span>
    <i class="stat-icon stat-icon-ga" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <path d="M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z"></path>
      </svg>
    </i>
    <?php echo (int)$career['total_ga']; ?>
    <small>G/A</small>
  </span>
</div>

  <section>
    <h2 style="margin-bottom:14px">Season by season</h2>
    <div class="table-wrap">
      <table class="t">
        <thead>
          <tr>
            <th>Season</th>
            <th>Club</th>
            <th class="num">No.</th>
            <th>Position</th>
            <th class="num">Goals</th>
            <th class="num">Assists</th>
            <th class="num">G/A</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($seasonRows as $season_id_row => $s): ?>
            <tr>
              <td><strong><?php echo h($s['season_title']); ?></strong></td>
              <td><?php echo h($s['club']); ?></td>
              <td class="num"><?php echo h($s['jersey_no']); ?></td>
              <td><?php echo h($s['category']) . ', ' . h($s['position']); ?></td>
              <td class="num"><?php echo (int)$s['goals']; ?></td>
              <td class="num"><?php echo (int)$s['assists']; ?></td>
              <td class="num strong"><?php echo (int)$s['goals'] + (int)$s['assists']; ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="is-total">
            <td colspan="4">Career total</td>
            <td class="num"><?php echo (int)$career['total_goals']; ?></td>
            <td class="num"><?php echo (int)$career['total_assists']; ?></td>
            <td class="num"><?php echo (int)$career['total_ga']; ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section id="vsClubs">
    <h2 style="margin-bottom:14px">Against clubs</h2>
    <?php if (!$vsClubs): ?>
      <p class="muted">No matches against clubs on record yet.</p>
    <?php else: ?>
      <div class="panel" style="display:grid;gap:12px">
        <div class="field" style="margin:0">
          <label for="clubSearch">Search a club</label>
          <input type="search" id="clubSearch" list="clubList" autocomplete="off" placeholder="Type a club name" style="max-width:360px">
          <datalist id="clubList">
            <?php foreach ($vsClubs as $c): ?><option value="<?php echo h($c['label']); ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div id="vsSummary" aria-live="polite" style="font-weight:600"></div>
        <p class="muted" style="margin:0;font-size:.88rem">Matches counts every completed match against the club in seasons <?php echo h($career['display_name']); ?> was in the squad. Individual appearances aren't recorded, so it assumes he played them all.</p>
      </div>

      <div class="table-wrap" style="margin-top:14px">
        <table class="t" data-sortable id="vsClubTable">
          <thead>
            <tr>
              <th data-sort="text">Club</th>
              <th class="num" data-sort="num" title="Completed matches against this club while in the squad">Matches</th>
              <th class="num" data-sort="num">Goals</th>
              <th class="num" data-sort="num">Assists</th>
              <th class="num" data-sort="num">G/A</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vsClubs as $ck => $c): ?>
              <tr data-club="<?php echo h($ck); ?>" data-goals="<?php echo $c['goals']; ?>" data-assists="<?php echo $c['assists']; ?>" data-matches="<?php echo count($c['matches']); ?>">
                <td><strong><?php echo h($c['label']); ?></strong></td>
                <td class="num"><?php echo count($c['matches']); ?></td>
                <td class="num"><?php echo $c['goals']; ?></td>
                <td class="num"><?php echo $c['assists']; ?></td>
                <td class="num strong"><?php echo $c['goals'] + $c['assists']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3 style="margin:22px 0 10px">Match by match</h3>
      <div class="table-wrap">
        <table class="t" id="vsMatchTable">
          <thead>
            <tr><th>Season</th><th>Match</th><th class="num">Goals</th><th class="num">Assists</th></tr>
          </thead>
          <tbody>
            <?php foreach ($vsMatches as $m): ?>
              <tr data-club="<?php echo h($m['club']); ?>">
                <td><?php echo h($m['season'] ?? ''); ?></td>
                <td>
                  <?php echo h($m['home']); ?>
                  <strong class="tnum"><?php echo $m['done'] ? (int)$m['hs'] . '–' . (int)$m['as'] : 'v'; ?></strong>
                  <?php echo h($m['away']); ?>
                </td>
                <td class="num"><?php echo $m['goals']; ?></td>
                <td class="num"><?php echo $m['assists']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</main>

<?php if ($career): ?>
<script>
(function () {
  var input = document.getElementById('clubSearch');
  if (!input) return;
  var summary = document.getElementById('vsSummary');
  var clubRows = document.querySelectorAll('#vsClubTable tbody tr');
  var matchRows = document.querySelectorAll('#vsMatchTable tbody tr');
  var playerName = <?php echo json_encode($career['display_name']); ?>;

  function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

  function update() {
    var q = input.value.trim().toLowerCase();
    var visible = [], g = 0, a = 0, m = 0;
    clubRows.forEach(function (tr) {
      var show = q === '' || tr.dataset.club.indexOf(q) !== -1;
      tr.hidden = !show;
      if (show) { visible.push(tr); g += +tr.dataset.goals; a += +tr.dataset.assists; m += +tr.dataset.matches; }
    });
    matchRows.forEach(function (tr) { tr.hidden = !(q === '' || tr.dataset.club.indexOf(q) !== -1); });

    if (q === '') {
      summary.textContent = 'Across all clubs: ' + plural(g, 'goal') + ', ' + plural(a, 'assist') + '.';
    } else if (visible.length === 0) {
      summary.textContent = 'No matches found for ' + playerName + ' against \u201c' + input.value.trim() + '\u201d.';
    } else if (visible.length === 1) {
      var name = visible[0].cells[0].textContent.trim();
      summary.textContent = playerName + ' against ' + name + ': ' + plural(g, 'goal') + ', ' + plural(a, 'assist') +
        ' (' + (g + a) + ' G/A) in ' + plural(m, 'match') + '.';
    } else {
      summary.textContent = visible.length + ' clubs match \u201c' + input.value.trim() + '\u201d: ' +
        plural(g, 'goal') + ', ' + plural(a, 'assist') + ' in total. Pick one for exact figures.';
    }
  }

  input.addEventListener('input', update);
  update();
})();
</script>
<?php endif; ?>
</body>
</html>
