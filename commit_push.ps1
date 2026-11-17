#!/usr/bin/env bash
# commit_push.sh — commit every changed file separately with a descriptive
# message, then push. Assumes the git repo and remote are already set up.
#
# Usage:  ./commit_push.sh        (add -n for a dry run)
#
# Messages:
#   new file   -> description from the table below (or the file's first comment)
#   edited     -> what changed: functions touched, DB tables touched, lines +/-
#   deleted    -> "Remove <file>"
#   renamed    -> "Rename <old> -> <new>"

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

DRY_RUN=0
[ "${1:-}" = "-n" ] && DRY_RUN=1

# --- Descriptions for known files (used when a file is first added) ----------
# Add a line here for any new file to control its commit message.
declare -A DESC=(
  [admin_login.php]="admin login page"
  [admin_sessioncheck.php]="session guard for admin-only pages"
  [backtohome.php]="back-to-home link partial"
  [backtoindex.php]="back-to-index link partial"
  [club.php]="club page with squad listing for a season"
  [club_fixtures.php]="club fixtures page with results and goal stats"
  [club_media.php]="club media page listing season videos"
  [club_performance.php]="club performance page (overall, home and away metrics)"
  [club_player_combination.php]="player combination page for comparing two players"
  [club_player_performance.php]="club player performance page"
  [club_player_statistics.php]="club player statistics (goals and assists)"
  [db.php]="database connection"
  [dedupe_flags.php]="one-time tool to dedupe flag images by nationality"
  [fixture_details.php]="fixture details page with editable match info"
  [flag_helpers.php]="helpers for shared per-nationality flag images"
  [head.php]="shared <head> partial"
  [helpers.php]="shared interface helpers (club badges, recent form)"
  [home.php]="home page listing seasons with new-season form"
  [home_sidebar.php]="home sidebar menu"
  [index.php]="index page listing club seasons"
  [insert_players.php]="player management page (add, update, delete)"
  [league_table.php]="league table page"
  [lineup.php]="public lineup page"
  [lineup_admin.php]="lineup builder for admins"
  [lineup_helpers.php]="shared lineup helpers (formations, table setup)"
  [logout.php]="logout script that destroys the admin session"
  [nav.php]="shared navigation"
  [player_career_data.php]="helpers to aggregate player career data"
  [player_history.php]="player history page"
  [player_opponents.php]="player opponents page"
  [player_profile.php]="player profile page"
  [player_stats.php]="player stats page"
  [players_by_nationality.php]="players-by-nationality page"
  [season.php]="season page with fixture creation"
  [sidebar.php]="sidebar menu"
  [videos.php]="videos page with custom thumbnails"
  [scripts/lineup.js]="lineup pitch graphic script"
  [scripts/ui.js]="shared UI behaviour script (tabs, filters, sorting, toasts)"
  [styles/lineup.css]="lineup graphic and builder styles"
  [styles/theme.css]="site theme stylesheet"
)

join() { local IFS=", "; echo "$*"; }

# Message for a brand-new file.
describe_new() {
  local path="$1" d="${DESC[$1]:-}"
  if [ -z "$d" ] && [ -f "$path" ]; then
    # Fall back to the file's first comment line.
    d="$(grep -m1 -E '^\s*(//|#|/\*+|\*)\s*\S' "$path" 2>/dev/null \
        | sed -E 's@^\s*(//|#|/\*+|\*)\s*@@; s@\s*\*/\s*$@@' | cut -c1-60 | tr -d '\r')"
  fi
  if [ -z "$d" ]; then
    local base; base="$(basename "$path")"; base="${base%.*}"
    d="${base//[_-]/ } file"
  fi
  echo "Add $d"
}

# Message for an edited file, built from its diff.
describe_update() {
  local path="$1" diff added removed funcs tables parts=()
  diff="$(git diff HEAD -U0 --no-color -- "$path" 2>/dev/null || true)"
  read -r added removed _ < <(git diff HEAD --numstat -- "$path" | head -1) || true
  added="${added:--}"; removed="${removed:--}"

  # Functions whose definitions or bodies changed (PHP/JS).
  funcs="$(printf '%s\n' "$diff" | grep -E '^[+-]\s*(async\s+)?function\s+\w+' \
    | sed -E 's/^[+-]\s*(async\s+)?function\s+(\w+).*/\2/' | sort -u | head -4 | sed 's/$/()/' | paste -sd, - | sed 's/,/, /g')"
  # DB tables mentioned on changed lines.
  tables="$(printf '%s\n' "$diff" | grep -E '^[+-]' | grep -oiE '\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+`?[a-z_]+' \
    | awk '{print tolower($2)}' | tr -d '`' | sort -u | head -4 | paste -sd, - | sed 's/,/, /g')"

  [ -n "$funcs" ]  && parts+=("update $funcs")
  [ -n "$tables" ] && parts+=("touch $tables queries")

  local what
  case "$path" in
    *.css) what="styles" ;;
    *.js)  what="script" ;;
    *)     what="" ;;
  esac
  if [ ${#parts[@]} -eq 0 ]; then
    if [ "$added" = "0" ] && [ "$removed" != "-" ] && [ "$removed" -gt 0 ]; then parts+=("remove ${removed} lines")
    elif [ "$removed" = "0" ] && [ "$added" != "-" ] && [ "$added" -gt 0 ]; then parts+=("add ${added} lines${what:+ of $what}")
    else parts+=("revise ${what:-logic and markup}"); fi
  fi
  echo "Update $path: $(join "${parts[@]}") (+${added}/-${removed})" | sed 's/, update /, /'
}

# --- Collect changes (NUL-separated so spaces in names are safe) --------------
# Unstage anything pre-staged so every file is committed on its own.
git reset -q 2>/dev/null || true
mapfile -d '' ENTRIES < <(git status --porcelain -z -uall)

COUNT=0
i=0
while [ "$i" -lt "${#ENTRIES[@]}" ]; do
  entry="${ENTRIES[$i]}"
  status="${entry:0:2}"
  path="${entry:3}"
  i=$((i + 1))

  old_path=""
  if [[ "$status" == R* || "$status" == C* ]]; then
    old_path="${ENTRIES[$i]}"
    i=$((i + 1))
  fi

  case "$status" in
    "??"|A*|" A") msg="$(describe_new "$path")" ;;
    *D*)          msg="Remove $path" ;;
    R*)           msg="Rename $old_path -> $path" ;;
    *)            msg="$(describe_update "$path")" ;;
  esac

  if [ "$DRY_RUN" -eq 1 ]; then echo "[dry-run] $msg"; continue; fi

  if [ -n "$old_path" ]; then
    git add -A -- "$old_path" "$path"
    git commit -q -m "$msg" -- "$old_path" "$path"
  else
    git add -A -- "$path"
    git commit -q -m "$msg" -- "$path"
  fi
  echo "committed: $msg"
  COUNT=$((COUNT + 1))
done

[ "$DRY_RUN" -eq 1 ] && exit 0

[ "$COUNT" -eq 0 ] && echo "Nothing new to commit."
echo "Pushing..."
git push -u origin HEAD
echo "Done ($COUNT commit(s))."