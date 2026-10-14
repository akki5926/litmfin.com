<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Family Settings | Portfolio Link</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=DM+Mono:ital,wght@0,400;0,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
  <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    
    /* Ensure Font Awesome icons display */
    i[class^="fas "], i[class*=" fas "],
    i[class^="far "], i[class*=" far "],
    i[class^="fab "], i[class*=" fab "] {
      display: inline-block !important;
      font-style: normal !important;
    }

    :root {
      --accent:    #fd7101;
      --accent-lt: #fff4ec;
      --accent-md: #ffd8a8;
      --bg:        #eef1f7;
      --surface:   #ffffff;
      --border:    #e2e8f0;
      --text-1:    #111827;
      --text-2:    #4b5563;
      --text-3:    #9ca3af;
      --green:     #16a34a;
      --green-lt:  #dcfce7;
      --red:       #dc2626;
      --red-lt:    #fee2e2;
      --r:         11px;
      --r-lg:      15px;
    }

    html, body {
      height: 100%; overflow: hidden;
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--text-1);
    }

    /* ── LAYOUT SHELL ──────────────────────────── */
    .shell {
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* ── TOP BAR ───────────────────────────────── */
    .topbar {
      height: 54px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 18px;
      flex-shrink: 0;
      z-index: 10;
    }

    .brand {
      display: flex; align-items: center; gap: 8px;
      padding-right: 14px; border-right: 1px solid var(--border);
      flex-shrink: 0;
    }
    .brand-icon {
      width: 28px; height: 28px; border-radius: 8px;
      background: var(--accent-lt); border: 1px solid var(--accent-md);
      display: flex; align-items: center; justify-content: center;
      color: var(--accent); font-size: 12px;
    }
    .brand-text .b1 { font-size: 12px; font-weight: 700; color: var(--text-1); line-height: 1.2; }
    .brand-text .b2 { font-size: 9px; font-weight: 500; color: var(--text-3); text-transform: uppercase; letter-spacing: .5px; }

    .search-wrap { position: relative; width: 270px; flex-shrink: 0; }
    .search-wrap .ico {
      position: absolute; left: 9px; top: 50%; transform: translateY(-50%);
      color: var(--text-3); font-size: 11px; pointer-events: none;
    }
    .search-wrap input {
      width: 100%; padding: 6px 9px 6px 28px;
      border: 1.5px solid var(--border); border-radius: var(--r);
      font-family: 'Outfit', sans-serif; font-size: 13px;
      background: var(--bg); color: var(--text-1);
      transition: border-color .15s, background .15s;
    }
    .search-wrap input:focus { outline: none; border-color: var(--accent); background: #fff; }
    .search-wrap input::placeholder { color: var(--text-3); }

    .btn-load {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 6px 15px; background: var(--accent); border: none;
      border-radius: var(--r); font-family: 'Outfit', sans-serif;
      font-size: 12px; font-weight: 700; color: #fff; cursor: pointer;
      flex-shrink: 0; transition: opacity .15s;
    }
    .btn-load:hover { opacity: .85; }

    .spinner { display: none; align-items: center; flex-shrink: 0; }
    .spin-ring {
      width: 16px; height: 16px;
      border: 2px solid var(--accent-md); border-top-color: var(--accent);
      border-radius: 50%; animation: spin .6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .head-pill {
      display: none; align-items: center; gap: 6px;
      background: var(--accent-lt); border: 1.5px solid var(--accent-md);
      border-radius: 20px; padding: 3px 12px; flex-shrink: 0;
    }
    .hp-dot { width: 6px; height: 6px; background: #22c55e; border-radius: 50%; }
    .hp-ucc { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--accent); }
    .hp-sep { color: var(--text-3); font-size: 10px; }
    .hp-name { font-size: 12px; font-weight: 600; color: var(--text-1); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* ── AUTOCOMPLETE ──────────────────────────── */
    .ac-dropdown {
      position: absolute; top: calc(100% + 4px); left: 0;
      width: 100%; background: var(--surface);
      border: 1.5px solid var(--border); border-radius: var(--r-lg);
      z-index: 99999; display: none;
      box-shadow: 0 10px 28px rgba(0,0,0,.12);
      max-height: 200px; overflow-y: auto;
    }
    .ac-item {
      padding: 8px 12px; cursor: pointer; font-size: 12px;
      display: flex; align-items: center; gap: 10px;
      border-bottom: 1px solid #f8fafc;
    }
    .ac-item:last-child { border-bottom: none; }
    .ac-item:hover { background: var(--accent-lt); }
    .ac-ucc {
      font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 500;
      background: #f1f5f9; color: #1e293b;
      padding: 3px 9px; border-radius: 6px; white-space: nowrap; flex-shrink: 0;
    }
    .ac-name { color: var(--text-2); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── BODY AREA ─────────────────────────────── */
    .body-area {
      flex: 1; min-height: 0;
      display: flex; align-items: stretch;
      padding: 12px 18px 14px;
      gap: 12px;
      overflow: hidden;
    }

    /* empty state */
    #emptyState {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 8px; color: var(--text-3);
    }
    #emptyState i { font-size: 40px; opacity: .25; }
    #emptyState p { font-size: 13px; font-weight: 600; }
    #emptyState small { font-size: 11px; }

    /* split */
    #familySection {
      flex: 1; min-height: 0; min-width: 0;
      display: none;
      gap: 12px;
    }
    #familySection.active {
      display: grid;
      grid-template-columns: 440px 1fr;
    }

    /* ── PANEL ─────────────────────────────────── */
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      display: flex; flex-direction: column;
      overflow: hidden; min-height: 0;
    }
    .panel-head {
      padding: 9px 13px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 7px; flex-shrink: 0;
    }
    .ph-icon {
      width: 24px; height: 24px; border-radius: 7px;
      background: var(--accent-lt); border: 1px solid var(--accent-md);
      display: flex; align-items: center; justify-content: center;
      color: var(--accent); font-size: 10px; flex-shrink: 0;
    }
    .panel-head h3 { font-size: 12px; font-weight: 700; }
    .rec-count {
      margin-left: auto;
      font-size: 9px; font-weight: 700;
      background: var(--accent-lt); color: var(--accent);
      padding: 2px 7px; border-radius: 20px;
    }

    /* ── LEFT: MEMBERS LIST ────────────────────── */
    .members-list {
      flex: 1; overflow-y: auto; padding: 8px;
      display: flex; flex-direction: column; gap: 6px; min-height: 0;
    }

    .member-strip {
      border: 1.5px solid var(--border); border-radius: var(--r);
      background: #fff; transition: border-color .2s, background .2s;
    }
    .member-strip.is-saved { border-color: #a7f3d0; background: #fafef8; }

    .ms-row {
      display: flex; align-items: center; gap: 7px; padding: 7px 10px;
    }
    .ms-num {
      width: 22px; height: 22px; border-radius: 50%;
      background: var(--accent-lt); border: 1.5px solid var(--accent-md);
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 800; color: var(--accent); flex-shrink: 0;
    }
    .is-saved .ms-num { background: var(--green-lt); border-color: #6ee7b7; color: var(--green); }

    .ms-info { flex: 1; min-width: 0; }
    .ms-title { font-size: 11px; font-weight: 700; line-height: 1.3; }
    .ms-sub {
      font-size: 10px; color: var(--text-3);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      max-width: 260px;
    }
    .is-saved .ms-sub { color: var(--green); }

    .ms-badge {
      font-size: 9px; font-weight: 700; padding: 2px 7px;
      border-radius: 20px; background: #f1f5f9; color: #64748b; flex-shrink: 0;
    }
    .is-saved .ms-badge { background: var(--green-lt); color: var(--green); }

    .ms-actions { display: flex; gap: 3px; flex-shrink: 0; }
    .xbtn {
      display: inline-flex; align-items: center; gap: 3px;
      padding: 3px 8px; border-radius: 7px; border: 1px solid transparent;
      font-size: 10px; font-weight: 700; font-family: 'Outfit', sans-serif;
      cursor: pointer; transition: opacity .15s; white-space: nowrap;
    }
    .xbtn:hover { opacity: .75; }
    .xbtn-add  { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .xbtn-edit { background: #fef9e6; color: #d97706; border-color: #fde68a; }
    .xbtn-del  { background: var(--red-lt); color: var(--red); border-color: #fecaca; }

    /* inline form */
    .ms-form {
      display: none; padding: 10px 12px 12px;
      border-top: 1px solid #f1efec; background: #fffdf9;
    }
    .form-row { display: flex; flex-direction: column; gap: 8px; }
    .form-row-inner { display: flex; gap: 8px; align-items: flex-end; }
    .fc { display: flex; flex-direction: column; gap: 3px; }
    .fc label {
      font-size: 9px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .4px; color: var(--text-3);
    }
    .fc-ucc  { flex: 1; position: relative; }
    .fc-name { flex: 1.4; }
    .fc-rel  { flex: 1; }
    .fi {
      padding: 6px 9px; border: 1.5px solid var(--border);
      border-radius: 8px; font-family: 'Outfit', sans-serif;
      font-size: 12px; width: 100%; background: #fff; color: var(--text-1);
    }
    .fi:focus { outline: none; border-color: var(--accent); }
    .name-display {
      padding: 6px 9px; background: #f8fafc;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-size: 11px; font-family: 'DM Mono', monospace;
      color: var(--text-2); min-height: 32px;
      display: flex; align-items: center;
    }
    .form-btns { display: flex; gap: 6px; }
    .btn-save {
      background: var(--accent); border: none; padding: 6px 16px;
      border-radius: 8px; font-family: 'Outfit', sans-serif;
      font-weight: 700; font-size: 12px; color: #fff; cursor: pointer;
    }
    .btn-cancel {
      background: #f1f5f9; border: 1px solid #cbd5e1;
      padding: 6px 12px; border-radius: 8px;
      font-family: 'Outfit', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer;
    }

    /* ── RIGHT: TABLE ──────────────────────────── */
    .table-wrapper { flex: 1; overflow-y: auto; min-height: 0; }

    .data-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .data-table th {
      background: #f8fafc; padding: 8px 11px; text-align: left;
      font-weight: 700; font-size: 9px; color: var(--text-3);
      text-transform: uppercase; letter-spacing: .4px;
      border-bottom: 1.5px solid var(--border);
      position: sticky; top: 0; z-index: 10;
    }
    .data-table td {
      padding: 9px 11px; border-bottom: 1px solid #f0f3f7; vertical-align: middle;
    }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: #fafcff; }

    .head-cell { display: flex; flex-direction: column; gap: 3px; }
    .head-ucc-tag {
      font-family: 'DM Mono', monospace; font-size: 10px; font-weight: 500;
      background: #fef3c7; color: #b45309;
      padding: 2px 7px; border-radius: 5px; display: inline-block; width: fit-content;
    }
    .head-name-tag { font-size: 10px; font-weight: 500; color: var(--text-2); }

    .member-cell { display: flex; flex-direction: column; gap: 3px; }
    .member-ucc-tag {
      font-family: 'DM Mono', monospace; font-size: 10px;
      background: #f1f5f9; color: var(--text-1);
      padding: 2px 6px; border-radius: 5px; display: inline-block; width: fit-content;
    }
    .member-name-tag { font-size: 10px; color: var(--text-2); }
    .member-rel-tag {
      font-size: 9px; font-weight: 700; background: var(--accent-lt); color: var(--accent);
      padding: 1px 7px; border-radius: 10px; display: inline-block; width: fit-content;
    }
    .empty-member { color: #d1d5db; font-size: 10px; font-style: italic; }

    .del-btn {
      background: var(--red-lt); border: 1px solid #fecaca;
      padding: 4px 10px; border-radius: 6px;
      font-size: 10px; font-weight: 600; color: var(--red);
      cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
      font-family: 'Outfit', sans-serif; transition: background .15s;
    }
    .del-btn:hover { background: #fee2e2; }

    .empty-table { text-align: center; padding: 40px 20px; color: var(--text-3); }
    .empty-table i { font-size: 28px; margin-bottom: 8px; display: block; opacity: .35; }
    .empty-table p { font-size: 12px; }

    /* ── TOAST ─────────────────────────────────── */
    #toastMsg {
      position: fixed; bottom: 16px; right: 18px;
      padding: 7px 16px; background: #065f46;
      border-radius: 30px; font-weight: 600; font-size: 12px;
      color: #fff; z-index: 999999; display: none;
      box-shadow: 0 4px 12px rgba(0,0,0,.15);
      font-family: 'Outfit', sans-serif;
    }

    /* scrollbar */
    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
  </style>
</head>
<body>
<div class="shell">

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fas fa-users"></i></div>
      <div class="brand-text">
        <div class="b1">Family Settings</div>
        <div class="b2">Portfolio Link</div>
      </div>
    </div>

    <div class="search-wrap" id="headUCCWrap">
      <i class="fas fa-search ico"></i>
      <input type="text" id="custUCC" placeholder="Search by UCC or name…" autocomplete="off">
      <div id="acCustList" class="ac-dropdown"></div>
    </div>

    <div id="custSpinner" class="spinner"><div class="spin-ring"></div></div>

    <button class="btn-load" id="selectCustBtn">
      <i class="fas fa-bolt"></i> Load
    </button>

    <div id="headInfoPreview" class="head-pill">
      <span class="hp-dot"></span>
      <span id="hbUCC" class="hp-ucc"></span>
      <span class="hp-sep">—</span>
      <span id="hbName" class="hp-name"></span>
    </div>
  </div>

  <!-- BODY AREA -->
  <div class="body-area">

    <!-- Empty state -->
    <div id="emptyState">
      <i class="fas fa-search"></i>
      <p>Search and load a customer to begin</p>
      <small>Enter a UCC or name in the search bar above</small>
    </div>

    <!-- Split layout (shown after customer loaded) -->
    <div id="familySection">

      <!-- LEFT: Manage -->
      <div class="panel">
        <div class="panel-head">
          <div class="ph-icon"><i class="fas fa-link"></i></div>
          <h3>Manage Members</h3>
        </div>
        <div class="members-list">

          <div id="member1Strip" class="member-strip">
            <div class="ms-row">
              <div class="ms-num">1</div>
              <div class="ms-info">
                <div class="ms-title">Primary Member</div>
                <div id="mSub1" class="ms-sub">Not added</div>
              </div>
              <span class="ms-badge" id="mBadge1">Not added</span>
              <div class="ms-actions">
                <button class="xbtn xbtn-add" id="mAddBtn1"><i class="fas fa-plus"></i> Add</button>
                <button class="xbtn xbtn-edit" id="mEditBtn1" style="display:none;"><i class="fas fa-edit"></i> Edit</button>
              </div>
            </div>
            <div id="mForm1" class="ms-form"></div>
          </div>

          <div id="member2Strip" class="member-strip">
            <div class="ms-row">
              <div class="ms-num">2</div>
              <div class="ms-info">
                <div class="ms-title">Member 2</div>
                <div id="mSub2" class="ms-sub">Not added</div>
              </div>
              <span class="ms-badge" id="mBadge2">Not added</span>
              <div class="ms-actions">
                <button class="xbtn xbtn-add" id="mAddBtn2"><i class="fas fa-plus"></i> Add</button>
                <button class="xbtn xbtn-edit" id="mEditBtn2" style="display:none;"><i class="fas fa-edit"></i> Edit</button>
                <button class="xbtn xbtn-del" id="mDelBtn2" style="display:none;"><i class="fas fa-trash"></i></button>
              </div>
            </div>
            <div id="mForm2" class="ms-form"></div>
          </div>

          <div id="member3Strip" class="member-strip">
            <div class="ms-row">
              <div class="ms-num">3</div>
              <div class="ms-info">
                <div class="ms-title">Member 3</div>
                <div id="mSub3" class="ms-sub">Not added</div>
              </div>
              <span class="ms-badge" id="mBadge3">Not added</span>
              <div class="ms-actions">
                <button class="xbtn xbtn-add" id="mAddBtn3"><i class="fas fa-plus"></i> Add</button>
                <button class="xbtn xbtn-edit" id="mEditBtn3" style="display:none;"><i class="fas fa-edit"></i> Edit</button>
                <button class="xbtn xbtn-del" id="mDelBtn3" style="display:none;"><i class="fas fa-trash"></i></button>
              </div>
            </div>
            <div id="mForm3" class="ms-form"></div>
          </div>

        </div>
      </div>

      <!-- RIGHT: Records table -->
      <div class="panel">
        <div class="panel-head">
          <div class="ph-icon"><i class="fas fa-table"></i></div>
          <h3>Linked Family Records</h3>
          <span class="rec-count" id="recordCount">0 records</span>
        </div>
        <div class="table-wrapper">
          <table class="data-table" id="familyRecordsTable">
            <thead>
              <tr>
                <th style="width:18%">Head</th>
                <th style="width:22%">Member 1</th>
                <th style="width:22%">Member 2</th>
                <th style="width:22%">Member 3</th>
                <th style="width:10%;text-align:center">Action</th>
               </tr>
            </thead>
            <tbody id="recordsTableBody">
              <tr><td colspan="5" class="empty-table"><i class="fas fa-spinner fa-pulse"></i><p>Loading…</p></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /familySection -->
  </div><!-- /body-area -->
</div><!-- /shell -->

<div id="toastMsg"></div>

<script>
(function() {
  'use strict';

  let currentHeadUCC = '';
  let uccListCache = null;
  let uccFetching = false;
  let uccWaiters = [];

  let familyData = {
    member1: { ucc: '', name: '', rel: '', saved: false },
    member2: { ucc: '', name: '', rel: '', saved: false },
    member3: { ucc: '', name: '', rel: '', saved: false }
  };

  let formDestroyers = {};
  let headerACDestroyer = null;
  let headerEnterListener = null;

  async function getUCCList() {
    if (uccListCache) return uccListCache;
    if (uccFetching) return new Promise(r => uccWaiters.push(r));
    uccFetching = true;
    try {
      const res = await fetch('getUCClistwithname.php').then(r => r.json());
      uccListCache = Array.isArray(res) ? res : [];
    } catch(e) { uccListCache = []; }
    uccFetching = false;
    uccWaiters.forEach(r => r(uccListCache));
    uccWaiters = [];
    return uccListCache;
  }

  function invalidateCache() { uccListCache = null; uccFetching = false; uccWaiters = []; }

  function esc(str) { return String(str ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[c]); }

  function showToast(msg, isErr = false) {
    let t = document.getElementById('toastMsg');
    t.textContent = msg;
    t.style.background = isErr ? '#b91c1c' : '#065f46';
    t.style.display = 'block';
    clearTimeout(t._t);
    t._t = setTimeout(() => t.style.display = 'none', 3200);
  }

  function postAjax(url, params) {
    return new Promise((ok, fail) => {
      let x = new XMLHttpRequest();
      x.open('POST', url, true);
      x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      x.onload = () => { 
        try { 
          let json = JSON.parse(x.responseText);
          ok(json); 
        } catch(e) { 
          ok(x.responseText); 
        } 
      };
      x.onerror = () => fail('Network error');
      let body = Object.entries(params).map(([k,v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v ?? '')).join('&');
      x.send(body);
    });
  }

  async function getUCCName(ucc) {
    let res = await postAjax('getInvesteddata.php', { method: 'getUCCName', UCC: ucc });
    return res || false;
  }

  function buildAutocomplete(inputEl, dropEl, onSelect) {
    let active = true;
    async function onInput() {
      if (!active) return;
      let val = inputEl.value.trim().toLowerCase();
      if (val.length < 2) { hideAC(); return; }
      let list = await getUCCList();
      if (!active) return;
      if (!list.length) { hideAC(); return; }
      if (inputEl.value.trim().toLowerCase() !== val) return;
      let hits = list.filter(d => d.UCC.toLowerCase().includes(val) || d.name.toLowerCase().includes(val)).slice(0, 10);
      dropEl.innerHTML = '';
      if (!hits.length) { hideAC(); return; }
      hits.forEach(m => {
        let div = document.createElement('div');
        div.className = 'ac-item';
        div.innerHTML = `<span class="ac-ucc">${esc(m.UCC)}</span><span class="ac-name">${esc(m.name)}</span>`;
        div.addEventListener('mousedown', e => {
          e.preventDefault();
          inputEl.value = m.UCC;
          hideAC();
          if (onSelect) onSelect(m.UCC, m.name);
        });
        dropEl.appendChild(div);
      });
      dropEl.style.display = 'block';
    }
    function hideAC() { if (active) { dropEl.style.display = 'none'; dropEl.innerHTML = ''; } }
    function handleInput() { onInput(); }
    function handleBlur() { setTimeout(() => { if (active) hideAC(); }, 150); }
    function handleKeydown(e) { if (e.key === 'Escape') hideAC(); }
    inputEl.addEventListener('input', handleInput);
    inputEl.addEventListener('blur', handleBlur);
    inputEl.addEventListener('keydown', handleKeydown);
    return () => {
      active = false;
      inputEl.removeEventListener('input', handleInput);
      inputEl.removeEventListener('blur', handleBlur);
      inputEl.removeEventListener('keydown', handleKeydown);
      dropEl.innerHTML = '';
    };
  }

  function renderView(slot) {
    let m = familyData[`member${slot}`];
    let strip  = document.getElementById(`member${slot}Strip`);
    let sub    = document.getElementById(`mSub${slot}`);
    let badge  = document.getElementById(`mBadge${slot}`);
    let addBtn = document.getElementById(`mAddBtn${slot}`);
    let editBtn= document.getElementById(`mEditBtn${slot}`);
    let delBtn = document.getElementById(`mDelBtn${slot}`);
    if (!strip) return;

    if (m.saved && m.ucc) {
      strip.classList.add('is-saved');
      badge.textContent = '✓ Saved';
      sub.textContent = (m.name || m.ucc) + (m.rel ? ' · ' + m.rel : '');
      if (addBtn)  addBtn.style.display  = 'none';
      if (editBtn) editBtn.style.display = 'inline-flex';
      if (delBtn && slot !== 1) delBtn.style.display  = 'inline-flex';
    } else {
      strip.classList.remove('is-saved');
      badge.textContent = 'Not added';
      sub.textContent = 'Not added';
      if (addBtn)  addBtn.style.display  = 'inline-flex';
      if (editBtn) editBtn.style.display = 'none';
      if (delBtn)  delBtn.style.display  = 'none';
    }
    let formDiv = document.getElementById(`mForm${slot}`);
    if (formDiv) { 
      formDiv.style.display = 'none'; 
      formDiv.innerHTML = ''; 
    }
    strip.classList.remove('is-open');
  }

  function cancelForm(slot) {
    if (formDestroyers[slot]) { 
      formDestroyers[slot](); 
      delete formDestroyers[slot]; 
    }
    let fd = document.getElementById(`mForm${slot}`);
    if (fd) {
      fd.style.display = 'none'; 
      fd.innerHTML = '';
    }
    let strip = document.getElementById(`member${slot}Strip`);
    if (strip) strip.classList.remove('is-open');
  }

  async function buildForm(slot, mode) {
    // Cancel any existing form first
    cancelForm(slot);
    
    // VALIDATION: Check if primary member exists before adding member 2 or 3
    if (slot !== 1 && (!familyData.member1.saved || !familyData.member1.ucc)) {
      showToast('⚠️ Please add the Primary Member (Member 1) first before adding other members', true);
      return;
    }
    
    // VALIDATION: Check if member 2 exists before adding member 3
    if (slot === 3 && (!familyData.member2.saved || !familyData.member2.ucc)) {
      showToast('⚠️ Please add Member 2 first before adding Member 3', true);
      return;
    }
    
    let m = familyData[`member${slot}`];
    let prefUCC = mode === 'edit' ? (m.ucc || '') : '';
    let prefRel = mode === 'edit' ? m.rel : '';
    let relOpts = ['Spouse','Child','Parent','Sibling','Guardian','Nominee']
      .map(r => `<option value="${r}" ${prefRel===r?'selected':''}>${r}</option>`).join('');

    let html = `<div class="form-row">
      <div class="fc fc-ucc">
        <label>UCC${slot===1?' *':''}</label>
        <input type="text" id="ff_ucc${slot}" class="fi" placeholder="Type to search UCC or name…" value="${esc(prefUCC)}" autocomplete="off">
        <div id="ff_ac${slot}" class="ac-dropdown"></div>
      </div>
      <div class="form-row-inner">
        <div class="fc fc-name">
          <label>Holder Name</label>
          <div id="ff_name${slot}" class="name-display">—</div>
        </div>
        <div class="fc fc-rel">
          <label>Relationship</label>
          <select id="ff_rel${slot}" class="fi">
            <option value="">— Select —</option>${relOpts}
          </select>
        </div>
      </div>
      <div class="form-btns">
        <button class="btn-save" id="saveBtn${slot}"><i class="fas fa-check"></i> Save</button>
        <button class="btn-cancel" id="cancelBtn${slot}">Cancel</button>
      </div>
    </div>`;

    let fd = document.getElementById(`mForm${slot}`);
    if (!fd) return;
    
    fd.innerHTML = html; 
    fd.style.display = 'block';
    let strip = document.getElementById(`member${slot}Strip`);
    if (strip) strip.classList.add('is-open');

    let uccInput  = document.getElementById(`ff_ucc${slot}`);
    let nameField = document.getElementById(`ff_name${slot}`);
    let acDrop    = document.getElementById(`ff_ac${slot}`);

    const setName = async (ucc) => {
      if (!ucc) { 
        if (nameField) nameField.textContent = '—'; 
        return; 
      }
      if (nameField) nameField.textContent = '⏳ fetching...';
      let res = await getUCCName(ucc);
      if (nameField) {
        nameField.textContent = (res && typeof res === 'string') ? res : (res?.name || '❌ Not found');
      }
    };

    if (uccInput) {
      uccInput.addEventListener('blur', () => setName(uccInput.value.trim()));
    }

    if (uccInput && acDrop) {
      formDestroyers[slot] = buildAutocomplete(uccInput, acDrop, async (ucc, selName) => {
        if (uccInput) uccInput.value = ucc;
        if (selName && nameField) nameField.textContent = selName;
        else await setName(ucc);
      });
    }

    if (prefUCC) await setName(prefUCC);

    let saveBtn = document.getElementById(`saveBtn${slot}`);
    let cancelBtn = document.getElementById(`cancelBtn${slot}`);
    
    if (saveBtn) {
      // Remove existing listeners by cloning and replacing
      let newSaveBtn = saveBtn.cloneNode(true);
      saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
      newSaveBtn.addEventListener('click', () => saveMember(slot));
    }
    
    if (cancelBtn) {
      let newCancelBtn = cancelBtn.cloneNode(true);
      cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
      newCancelBtn.addEventListener('click', () => cancelForm(slot));
    }
    
    if (uccInput) uccInput.focus();
  }

  async function saveMember(slot) {
    if (!currentHeadUCC) { 
      showToast('Please load a customer first', true); 
      return; 
    }
    
    // VALIDATION 1: Check if primary member exists before adding member 2 or 3
    if (slot !== 1 && (!familyData.member1.saved || !familyData.member1.ucc)) {
      showToast('⚠️ Please add the Primary Member (Member 1) first before adding other members', true);
      cancelForm(slot);
      return;
    }
    
    // VALIDATION 2: Check if member 2 exists before adding member 3
    if (slot === 3 && (!familyData.member2.saved || !familyData.member2.ucc)) {
      showToast('⚠️ Please add Member 2 first before adding Member 3', true);
      cancelForm(slot);
      return;
    }
    
    let uccInput = document.getElementById(`ff_ucc${slot}`);
    let relSelect = document.getElementById(`ff_rel${slot}`);
    let nameField = document.getElementById(`ff_name${slot}`);
    
    if (!uccInput || !relSelect) {
      showToast('Form not found', true);
      return;
    }
    
    let ucc = uccInput.value.trim();
    let rel = relSelect.value;
    
    if (slot === 1 && !ucc) { 
      showToast('Member 1 UCC is required', true); 
      return; 
    }
    
    if (ucc && !rel) { 
      showToast('Please select a relationship', true); 
      return; 
    }
    
    // If no UCC is entered and it's not member 1, we're removing the member
    if (!ucc && slot !== 1) {
      // This means user wants to remove the member
      if (familyData[`member${slot}`].saved) {
        if (confirm(`Remove ${familyData[`member${slot}`].name || familyData[`member${slot}`].ucc} from family?`)) {
          await deleteSingleMemberAPI(slot);
        }
      }
      cancelForm(slot);
      return;
    }
    
    if (ucc) {
      let nv = nameField ? nameField.textContent : '';
      if (!nv || nv === '⏳ fetching...' || nv === '❌ Not found') { 
        showToast('Please wait for UCC verification or enter a valid UCC', true); 
        return; 
      }
      
      if (ucc === currentHeadUCC) { 
        showToast('Head UCC cannot be added as a family member', true); 
        return; 
      }
      
      // Check for duplicate across all members
      for (let i = 1; i <= 3; i++) {
        if (i !== slot && familyData[`member${i}`].saved && familyData[`member${i}`].ucc === ucc) {
          showToast(`⚠️ ${ucc} is already added as Member ${i}. Cannot add duplicate.`, true);
          return;
        }
      }
      
      let exists = await getUCCName(ucc);
      if (!exists) { 
        showToast(`❌ UCC "${ucc}" not found in system`, true); 
        return; 
      }
    }
    
    let payload = {
      action: 'add', 
      UCC: currentHeadUCC,
      UCC1: familyData.member1.ucc, 
      rel1: familyData.member1.rel,
      UCC2: familyData.member2.ucc, 
      rel2: familyData.member2.rel,
      UCC3: familyData.member3.ucc, 
      rel3: familyData.member3.rel
    };
    
    if (slot === 1)      { payload.UCC1 = ucc; payload.rel1 = rel; }
    else if (slot === 2) { payload.UCC2 = ucc; payload.rel2 = rel; }
    else                 { payload.UCC3 = ucc; payload.rel3 = rel; }
    
    try {
      let resp = await postAjax('processfamily.php', payload);
      if (resp.status === 'error') { 
        showToast(resp.message, true); 
      } else { 
        showToast(`✅ Member ${slot} saved successfully!`); 
        cancelForm(slot); 
        await loadFamilyRecords(currentHeadUCC); 
      }
    } catch(e) { 
      showToast('Request failed: ' + e, true); 
    }
  }

  async function deleteSingleMemberAPI(slot) {
    if (slot === 1) { 
      showToast('Primary Member cannot be deleted individually', true); 
      return false; 
    }
    try {
      let resp = await postAjax('processfamily.php', { action: 'deleteMember', UCC: currentHeadUCC, memberNum: slot });
      if (resp.status === 'error') { 
        showToast(resp.message, true); 
        return false;
      } else { 
        showToast(`✅ Member ${slot} removed`); 
        await loadFamilyRecords(currentHeadUCC); 
        return true;
      }
    } catch(e) { 
      showToast('Request failed: ' + e, true); 
      return false;
    }
  }

  window.deleteSingleMember = async function(slot) {
    await deleteSingleMemberAPI(slot);
  };

  window.deleteAllMembers = async function(slno) {
    if (!confirm('⚠️ Delete ALL family members for this head? This cannot be undone.')) return;
    try {
      let resp = await postAjax('processfamily.php', { action: 'delete', slno: slno });
      if (resp.status === 'error') { 
        showToast(resp.message, true); 
      } else { 
        showToast('✅ All family members deleted'); 
        await loadFamilyRecords(currentHeadUCC); 
      }
    } catch(e) { 
      showToast('Delete failed: ' + e, true); 
    }
  };

  function renderFamilyRecordsTable(records) {
    const tbody   = document.getElementById('recordsTableBody');
    const countEl = document.getElementById('recordCount');
    if (!tbody) return;

    if (!records || records.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="empty-table"><i class="fas fa-inbox"></i><p>No family records found for this head</p></td></tr>`;
      if (countEl) countEl.textContent = '0 records';
      return;
    }

    if (countEl) countEl.textContent = records.length + ' record' + (records.length !== 1 ? 's' : '');
    tbody.innerHTML = '';

    records.forEach(record => {
      const row = document.createElement('tr');
      const headUCC  = record.UCC || record.head_UCC || '';
      const headName = record.PRIMARY_HOLDER_NAME || record.head_PRIMARY_HOLDER_NAME || '—';

      const memberCell = (ucc, name, rel) => {
        if (ucc && ucc !== '') {
          return `<div class="member-cell">
            <span class="member-ucc-tag">${esc(ucc)}</span>
            <span class="member-name-tag">${esc(name || 'Name not found')}</span>
            <span class="member-rel-tag">${esc(rel || '')}</span>
          </div>`;
        }
        return '<span class="empty-member">— Not added —</span>';
      };

      row.innerHTML = `
        <td>
          <div class="head-cell">
            <span class="head-ucc-tag">${esc(headUCC)}</span>
            <span class="head-name-tag">${esc(headName)}</span>
          </div>
        </td>
        <td>${memberCell(record.member1_UCC, record.member1_PRIMARY_HOLDER_NAME, record.member1_rel || record.rel1)}</td>
        <td>${memberCell(record.member2_UCC, record.member2_PRIMARY_HOLDER_NAME, record.member2_rel || record.rel2)}</td>
        <td>${memberCell(record.member3_UCC, record.member3_PRIMARY_HOLDER_NAME, record.member3_rel || record.rel3)}</td>
        <td style="text-align:center;">
          <button class="del-btn" onclick="deleteAllMembers('${record.slno}')">
            <i class="fas fa-trash"></i> Delete
          </button>
        </td>`;
      tbody.appendChild(row);
    });
  }

  async function loadFamilyRecords(headUCC) {
    if (!headUCC) return;
    const tbody = document.getElementById('recordsTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="empty-table"><i class="fas fa-spinner fa-pulse"></i><p>Loading…</p></td></tr>`;

    try {
      let response = await postAjax('processfamily.php', { action: 'getData', UCC: headUCC });
      let records = Array.isArray(response) ? response : [];

      if (records.length > 0) {
        let rec = records[0];
        const getVal = (obj, key) => obj[key] || obj[key.toLowerCase()] || '';

        familyData.member1 = { 
          ucc: getVal(rec,'member1_UCC'), 
          name: getVal(rec,'member1_PRIMARY_HOLDER_NAME'), 
          rel: getVal(rec,'member1_rel') || getVal(rec,'rel1'), 
          saved: !!getVal(rec,'member1_UCC') 
        };
        familyData.member2 = { 
          ucc: getVal(rec,'member2_UCC'), 
          name: getVal(rec,'member2_PRIMARY_HOLDER_NAME'), 
          rel: getVal(rec,'member2_rel') || getVal(rec,'rel2'), 
          saved: !!getVal(rec,'member2_UCC') 
        };
        familyData.member3 = { 
          ucc: getVal(rec,'member3_UCC'), 
          name: getVal(rec,'member3_PRIMARY_HOLDER_NAME'), 
          rel: getVal(rec,'member3_rel') || getVal(rec,'rel3'), 
          saved: !!getVal(rec,'member3_UCC') 
        };

        for (let i = 1; i <= 3; i++) {
          if (familyData[`member${i}`].ucc && !familyData[`member${i}`].name) {
            let name = await getUCCName(familyData[`member${i}`].ucc);
            familyData[`member${i}`].name = typeof name === 'string' ? name : (name?.name || '');
          }
        }

        // Fix: copy resolved names + normalise rel keys back into records for the table
        for (let i = 1; i <= 3; i++) {
          records[0][`member${i}_PRIMARY_HOLDER_NAME`] = familyData[`member${i}`].name || records[0][`member${i}_PRIMARY_HOLDER_NAME`] || '';
          if (!records[0][`member${i}_rel`] && records[0][`rel${i}`]) {
            records[0][`member${i}_rel`] = records[0][`rel${i}`];
          }
        }
      } else {
        for (let i = 1; i <= 3; i++) {
          familyData[`member${i}`] = { ucc: '', name: '', rel: '', saved: false };
        }
      }

      for (let i = 1; i <= 3; i++) renderView(i);
      renderFamilyRecordsTable(records);

    } catch(e) {
      console.error('loadFamilyRecords error:', e);
      showToast('Error loading family records', true);
      if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="empty-table"><i class="fas fa-exclamation"></i><p>Error loading records</p></td></tr>`;
    }
  }

  async function loadCustomer(ucc) {
    ucc = ucc.trim();
    if (!ucc) { showToast('Please enter a UCC to search', true); return; }
    document.getElementById('custSpinner').style.display = 'flex';
    try {
      let nameResp = await getUCCName(ucc);
      if (!nameResp) { showToast('UCC not found in system', true); return; }
      let name = typeof nameResp === 'string' ? nameResp : (nameResp.name || '');
      currentHeadUCC = ucc;
      document.getElementById('custUCC').value = ucc;
      document.getElementById('hbUCC').textContent = ucc;
      document.getElementById('hbName').textContent = name;
      document.getElementById('headInfoPreview').style.display = 'flex';
      document.getElementById('emptyState').style.display = 'none';
      document.getElementById('familySection').classList.add('active');
      showToast(`✓ Loaded: ${name}`);
      await loadFamilyRecords(ucc);
      try { sessionStorage.setItem('fs_headUCC', ucc); } catch(_) {}
    } catch(e) {
      console.error('loadCustomer error:', e);
      showToast('Error loading customer details', true);
    } finally {
      document.getElementById('custSpinner').style.display = 'none';
    }
  }

  function reinitializeHeaderAutocomplete() {
    if (headerACDestroyer) { headerACDestroyer(); headerACDestroyer = null; }
    let inp  = document.getElementById('custUCC');
    let drop = document.getElementById('acCustList');
    if (inp && drop) {
      if (headerEnterListener) { inp.removeEventListener('keydown', headerEnterListener); headerEnterListener = null; }
      headerACDestroyer = buildAutocomplete(inp, drop, (ucc) => loadCustomer(ucc));
      headerEnterListener = e => { if (e.key === 'Enter') loadCustomer(inp.value); };
      inp.addEventListener('keydown', headerEnterListener);
    }
  }

  // Event Listeners
  document.getElementById('selectCustBtn')?.addEventListener('click', () => loadCustomer(document.getElementById('custUCC').value));
  reinitializeHeaderAutocomplete();

  // Member button listeners
  document.getElementById('mAddBtn1')?.addEventListener('click', () => buildForm(1, 'add'));
  document.getElementById('mAddBtn2')?.addEventListener('click', () => buildForm(2, 'add'));
  document.getElementById('mAddBtn3')?.addEventListener('click', () => buildForm(3, 'add'));
  document.getElementById('mEditBtn1')?.addEventListener('click', () => buildForm(1, 'edit'));
  document.getElementById('mEditBtn2')?.addEventListener('click', () => buildForm(2, 'edit'));
  document.getElementById('mEditBtn3')?.addEventListener('click', () => buildForm(3, 'edit'));
  document.getElementById('mDelBtn2')?.addEventListener('click', () => window.deleteSingleMember(2));
  document.getElementById('mDelBtn3')?.addEventListener('click', () => window.deleteSingleMember(3));

  window.addEventListener('pageshow', e => {
    invalidateCache();
    getUCCList();
    if (e.persisted) {
      reinitializeHeaderAutocomplete();
      for (let i = 1; i <= 3; i++) renderView(i);
      if (currentHeadUCC) loadFamilyRecords(currentHeadUCC);
    } else {
      try { let saved = sessionStorage.getItem('fs_headUCC'); if (saved && !currentHeadUCC) loadCustomer(saved); } catch(_) {}
    }
  });
})();
</script>
</body>
</html>