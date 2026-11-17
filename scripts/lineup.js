/* Lineup board: draws the pitch graphic for both the public page (read-only)
   and the admin builder (editable). Usage:
   var board = LineupBoard(el, { players: [...], state: {...}, editable: bool, clubName: '', onChange: fn, onSelect: fn }); */
(function () {
  'use strict';

  var ROLE_CATEGORY = { GK: 'Goalkeeper', DEF: 'Defender', MID: 'Midfielder', FWD: 'Forward' };
  var FALLBACK_PIC = 'uploads/default.png';

  /** Rows that should sit out wide, as the % margin from each touchline. Keyed by formation, then row
      number (0 = goalkeeper, 1 = first row of outfield players, ...). */
  var WIDE_ROWS = { '4-2-2-2': { 3: 14 } };

  /** Slot coordinates (percent of the pitch) for a formation. GK at the top, attack at the bottom. */
  function layout(formation) {
    var rows = [1].concat(String(formation).split('-').map(Number));
    var last = rows.length - 1, slots = [];
    rows.forEach(function (n, r) {
      var y = 11 + r * (78 / last);
      var margin = n >= 5 ? 9 : n === 4 ? 13 : n === 3 ? 21 : 32;
      if (WIDE_ROWS[formation] && WIDE_ROWS[formation][r] !== undefined) margin = WIDE_ROWS[formation][r];
      var role = r === 0 ? 'GK' : r === 1 ? 'DEF' : r === last ? 'FWD' : 'MID';
      for (var i = 0; i < n; i++) {
        var x = n === 1 ? 50 : margin + i * (100 - 2 * margin) / (n - 1);
        slots.push({ x: x, y: y, role: role });
      }
    });
    return slots;
  }

  function shortName(name) {
    var parts = String(name || '').trim().split(/\s+/);
    if (parts.length < 2) return parts[0] || '';
    return parts[0].charAt(0).toUpperCase() + '. ' + parts.slice(1).join(' ');
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  var PITCH_LINES =
    '<svg class="lu-lines" viewBox="0 0 100 125" aria-hidden="true" focusable="false">' +
    '<g fill="none" stroke="rgba(255,255,255,.2)" stroke-width=".4">' +
    '<rect x="3" y="3" width="94" height="119" rx="3"/><path d="M3 62.5h94"/>' +
    '<circle cx="50" cy="62.5" r="11"/><circle cx="50" cy="62.5" r=".8" fill="rgba(255,255,255,.3)"/>' +
    '<path d="M24 3v19h52V3M37 3v8h26V3"/><path d="M24 122v-19h52v19M37 122v-8h26v8"/>' +
    '<path d="M40 22a11 11 0 0 0 20 0M40 103a11 11 0 0 1 20 0"/></g></svg>';

  window.LineupBoard = function (root, opts) {
    var players = opts.players || [];
    var byId = {};
    players.forEach(function (p) { byId[p.id] = p; });
    var editable = !!opts.editable;
    var XI = 11, BENCH = opts.benchSize || 9, TOTAL = XI + BENCH;
    // One list: indexes 0-10 are the starting XI, 11 and up are the bench.
    var all = [];
    for (var n = 0; n < TOTAL; n++) {
      var src = n < XI ? (opts.state.slots || [])[n] : (opts.state.bench || [])[n - XI];
      all.push(src || null);
    }
    var state = { title: opts.state.title || '', formation: opts.state.formation || '4-3-3', captain: opts.state.captain || null };
    var active = null;

    root.classList.add('lu-card');
    root.innerHTML = '';

    var head = el('div', 'lu-head');
    var titleEl = el('h2', 'lu-title');
    var meta = el('div', 'lu-meta');
    var formEl = el('span', 'lu-formation');
    var brandEl = el('span', 'lu-brand', opts.clubName || 'eFootball League');
    meta.appendChild(formEl); meta.appendChild(brandEl);
    head.appendChild(titleEl); head.appendChild(meta);

    var pitch = el('div', 'lu-pitch');
    pitch.innerHTML = PITCH_LINES;
    var layer = el('div', 'lu-layer');
    pitch.appendChild(layer);

    var benchWrap = el('div', 'lu-benchwrap');
    var benchHead = el('div', 'lu-benchhead');
    benchHead.appendChild(el('span', 'lu-benchtitle', 'Substitutes'));
    var benchCount = el('span', 'lu-benchcount');
    benchHead.appendChild(benchCount);
    var benchEl = el('div', 'lu-bench');
    benchWrap.appendChild(benchHead);
    benchWrap.appendChild(benchEl);

    root.appendChild(head);
    root.appendChild(pitch);
    root.appendChild(benchWrap);
    function changed() { if (opts.onChange) opts.onChange(api.getState()); }

    function buildToken(i, role) {
      var p = byId[all[i]];
      var tok = el('div', 'lu-slot' + (p ? '' : ' is-empty') + (active === i ? ' is-active' : ''));
      tok.dataset.index = i;

      var circle = el('div', 'lu-circle');
      if (p) {
        var img = el('img');
        img.src = p.picture || FALLBACK_PIC;
        img.alt = '';
        img.loading = 'lazy';
        circle.appendChild(img);
        circle.appendChild(el('span', 'lu-no', p.jersey));
        // Captain's armband: only starters can wear it.
        if (i < XI) {
          var isCap = state.captain === p.id;
          if (isCap && !editable) {
            var badge = el('span', 'lu-cap is-on', 'C');
            badge.title = 'Captain';
            circle.appendChild(badge);
          } else if (editable) {
            var cb = el('button', 'lu-cap' + (isCap ? ' is-on' : ''), 'C');
            cb.type = 'button';
            cb.title = isCap ? 'Captain (click to remove)' : 'Make captain';
            cb.setAttribute('aria-pressed', isCap ? 'true' : 'false');
            cb.setAttribute('aria-label', (isCap ? 'Remove captain: ' : 'Make captain: ') + p.name);
            cb.addEventListener('click', function (e) { e.stopPropagation(); api.setCaptain(i); });
            circle.appendChild(cb);
          }
        }
      } else if (editable) {
        circle.appendChild(el('span', 'lu-plus', '+'));
      }
      tok.appendChild(circle);

      if (p) {
        var pill = el('div', 'lu-pill');
        if (p.flag) {
          var f = el('img', 'flag lu-flag');
          f.src = p.flag; f.alt = '';
          pill.appendChild(f);
        }
        var txt = el('div', 'lu-txt');
        txt.appendChild(el('span', 'lu-name', shortName(p.name)));
        if (p.country) txt.appendChild(el('span', 'lu-country', p.country));
        pill.appendChild(txt);
        tok.appendChild(pill);
      } else if (editable) {
        tok.appendChild(el('div', 'lu-pill lu-pill-role', role));
      }

      if (editable) {
        tok.tabIndex = 0;
        tok.setAttribute('role', 'button');
        tok.setAttribute('aria-label', (p ? p.name + ', ' : 'Empty ') + (role === 'SUB' ? 'bench' : role) + ' slot');
        tok.draggable = !!p;
        tok.addEventListener('click', function (e) {
          if (e.target.classList.contains('lu-remove')) return;
          select(i);
        });
        tok.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(i); }
          if ((e.key === 'c' || e.key === 'C') && p && i < XI) { e.preventDefault(); api.setCaptain(i); }
          if ((e.key === 'Delete' || e.key === 'Backspace') && p) { e.preventDefault(); api.remove(i); }
        });
        tok.addEventListener('dragstart', function (e) {
          e.dataTransfer.setData('text/plain', JSON.stringify({ type: 'slot', index: i }));
          e.dataTransfer.effectAllowed = 'move';
        });
        tok.addEventListener('dragover', function (e) { e.preventDefault(); tok.classList.add('is-over'); });
        tok.addEventListener('dragleave', function () { tok.classList.remove('is-over'); });
        tok.addEventListener('drop', function (e) {
          e.preventDefault(); tok.classList.remove('is-over');
          var data; try { data = JSON.parse(e.dataTransfer.getData('text/plain')); } catch (_) { return; }
          if (data.type === 'player') api.place(i, data.id);
          else if (data.type === 'slot') api.swap(data.index, i);
        });
        if (p) {
          var rm = el('button', 'lu-remove', '\u00d7');
          rm.type = 'button';
          rm.setAttribute('aria-label', 'Remove ' + p.name);
          rm.addEventListener('click', function (e) { e.stopPropagation(); api.remove(i); });
          tok.appendChild(rm);
        }
      }
      return tok;
    }

    function render() {
      // The captain has to be in the starting XI; clear it if they were moved or removed.
      if (state.captain && all.slice(0, XI).indexOf(state.captain) === -1) state.captain = null;
      titleEl.textContent = state.title || opts.clubName || 'Starting XI';
      formEl.textContent = state.formation;

      layer.innerHTML = '';
      layout(state.formation).forEach(function (s, i) {
        var tok = buildToken(i, s.role);
        tok.style.left = s.x + '%';
        tok.style.top = s.y + '%';
        layer.appendChild(tok);
      });

      benchEl.innerHTML = '';
      var filled = 0;
      for (var j = XI; j < TOTAL; j++) {
        if (all[j]) filled++;
        if (!editable && !all[j]) continue;   // public view only shows chosen subs
        benchEl.appendChild(buildToken(j, 'SUB'));
      }
      benchCount.textContent = filled ? String(filled) : '';
      benchWrap.hidden = !editable && filled === 0;
    }

    function select(i) {
      active = i;
      render();
      if (opts.onSelect) opts.onSelect(i, roleOf(i));
    }

    function roleOf(i) { return i >= XI ? 'SUB' : layout(state.formation)[i].role; }

    /** Next empty spot, preferring the same area (XI or bench) before moving to the other. */
    function nextEmpty(from) {
      var lo = from >= XI ? XI : 0, hi = from >= XI ? TOTAL : XI;
      for (var k = 1; k < hi - lo; k++) {
        var j = lo + ((from - lo + k) % (hi - lo));
        if (!all[j]) return j;
      }
      for (var m = 0; m < TOTAL; m++) { if (!all[m]) return m; }
      return null;
    }

    var api = {
      getState: function () {
        return { title: state.title, formation: state.formation, slots: all.slice(0, XI), bench: all.slice(XI), captain: state.captain };
      },
      roleCategory: function (role) { return ROLE_CATEGORY[role]; },
      setTitle: function (t) { state.title = t; render(); },
      setFormation: function (f) { state.formation = f; render(); changed(); },
      getActive: function () { return active; },
      /** Toggle the captain on a starting-XI slot. */
      setCaptain: function (i) {
        var id = all[i];
        if (!id || i >= XI) return;
        state.captain = state.captain === id ? null : id;
        render(); changed();
      },
      /** Put a player into a slot (or the active / first empty one). A player can only be in one spot. */
      place: function (slot, playerId) {
        if (slot === undefined || slot === null) slot = active !== null ? active : all.indexOf(null);
        if (slot === -1 || slot === null) { if (window.toast) window.toast('The lineup and bench are full. Remove a player or pick a spot first.', 'error'); return; }
        var prev = all.indexOf(playerId);
        if (prev !== -1) all[prev] = null;
        all[slot] = playerId;
        active = nextEmpty(slot);
        render(); changed();
        if (opts.onSelect && active !== null) opts.onSelect(active, roleOf(active));
      },
      swap: function (a, b) {
        if (a === b) return;
        var t = all[a]; all[a] = all[b]; all[b] = t;
        render(); changed();
      },
      remove: function (i) { all[i] = null; render(); changed(); },
      clear: function () { all = all.map(function () { return null; }); active = null; render(); changed(); }
    };

    render();
    return api;
  };
})();
