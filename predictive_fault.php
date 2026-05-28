<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Predictive Fault Analysis - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>⚡</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --bg-primary:#000;--bg-secondary:#0f0f0f;--bg-tertiary:#0a0a0a;
  --border-color:#1a1a1a;--border-hover:#333;
  --text-primary:#fff;--text-secondary:#888;--text-muted:#555;
  --critical:#ff4757;--high:#ffa502;--medium:#3742fa;--low:#2ed573;
  --accent:#667eea;
}
body.light-mode{
  --bg-primary:#f5f5f5;--bg-secondary:#fff;--bg-tertiary:#f0f0f0;
  --border-color:#e0e0e0;--border-hover:#ccc;
  --text-primary:#111;--text-secondary:#666;--text-muted:#aaa;
}
body{font-family:'Montserrat',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;}

/* ── Sidebar (same as existing pages) ── */
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg-secondary);border-right:1px solid var(--border-color);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-brand{padding:18px 16px;border-bottom:1px solid var(--border-color);}
.sidebar-brand h2{font-size:13px;font-weight:700;letter-spacing:.3px;}
.sidebar-brand p{font-size:10px;color:var(--text-secondary);margin-top:2px;}
.nav-group-label{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;padding:12px 14px 4px;}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:6px;text-decoration:none;color:var(--text-secondary);font-size:12px;font-weight:500;margin:1px 6px;transition:all .15s;}
.nav-item:hover{background:var(--bg-tertiary);color:var(--text-primary);}
.nav-item.active{background:rgba(102,126,234,.1);color:var(--accent);border:1px solid rgba(102,126,234,.2);}
.nav-item i{font-size:13px;width:16px;text-align:center;}

/* ── Main ── */
.main{margin-left:220px;padding:24px;}

/* ── Top bar ── */
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none;}
.btn-accent{background:var(--accent);color:#fff;}
.btn-accent:hover{opacity:.85;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);}
.btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}

/* ── Stats row ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px 18px;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;}
.stat-card.s-red::after{background:var(--critical);}
.stat-card.s-orange::after{background:var(--high);}
.stat-card.s-yellow::after{background:#ffd32a;}
.stat-card.s-blue::after{background:var(--medium);}
.stat-card.s-green::after{background:var(--low);}
.stat-num{font-size:30px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;}

/* ── Layout ── */
.layout{display:grid;grid-template-columns:1fr 300px;gap:18px;}
@media(max-width:1100px){.layout{grid-template-columns:1fr;}}

/* ── Filters ── */
.filters{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
.filter-inp{flex:1;min-width:160px;}

/* ── View toggle ── */
.view-toggle{display:flex;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:2px;gap:2px;}
.vtb{padding:5px 10px;border-radius:4px;border:none;background:transparent;color:var(--text-secondary);cursor:pointer;font-size:12px;transition:all .15s;}
.vtb.active{background:var(--bg-tertiary);color:var(--text-primary);}

/* ── Device cards ── */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:14px;}
.device-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;padding:0;cursor:pointer;transition:all .2s;position:relative;overflow:hidden;}
.device-card:hover{border-color:var(--border-hover);transform:translateY(-1px);box-shadow:0 4px 20px rgba(0,0,0,.3);}
.device-card.rc-critical{border-top:3px solid var(--critical);}
.device-card.rc-high{border-top:3px solid var(--high);}
.device-card.rc-medium{border-top:3px solid var(--medium);}
.device-card.rc-low{border-top:3px solid var(--low);}
/* card header */
.dc-header{padding:12px 14px 10px;display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
.dc-name{font-size:13px;font-weight:700;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;}
.dc-ip{font-size:10px;color:var(--text-secondary);}
.dc-badges{display:flex;gap:4px;flex-wrap:wrap;margin-top:5px;}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.2px;}
.badge-critical{background:rgba(255,71,87,.12);color:var(--critical);}
.badge-high{background:rgba(255,165,2,.12);color:var(--high);}
.badge-medium{background:rgba(55,66,250,.12);color:var(--medium);}
.badge-low{background:rgba(46,213,115,.12);color:var(--low);}
.badge-hardware{background:rgba(255,211,42,.1);color:#ffd32a;}
.badge-network{background:rgba(55,66,250,.12);color:#74b9ff;}
.badge-software{background:rgba(162,155,254,.12);color:#a29bfe;}
.badge-combined{background:rgba(255,165,2,.12);color:var(--high);}
.badge-unknown{background:rgba(136,136,136,.1);color:var(--text-secondary);}
.badge-healthy{background:rgba(46,213,115,.1);color:var(--low);}
.badge-online{background:rgba(46,213,115,.12);color:var(--low);}
.badge-offline{background:rgba(255,71,87,.12);color:var(--critical);}
/* days pill */
.days-pill{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800;white-space:nowrap;}
.dp-urgent{background:rgba(255,71,87,.12);color:var(--critical);}
.dp-warn{background:rgba(255,165,2,.12);color:var(--high);}
.dp-ok{background:rgba(46,213,115,.1);color:var(--low);}
/* WHY critical banner */
.dc-why{margin:0 14px 10px;padding:9px 11px;border-radius:7px;font-size:11px;line-height:1.5;}
.dc-why.why-critical{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);}
.dc-why.why-high{background:rgba(255,165,2,.08);border:1px solid rgba(255,165,2,.2);}
.dc-why.why-medium{background:rgba(55,66,250,.08);border:1px solid rgba(55,66,250,.2);}
.dc-why.why-low{background:rgba(46,213,115,.08);border:1px solid rgba(46,213,115,.2);}
.dc-why-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:flex;align-items:center;gap:5px;}
.dc-why.why-critical .dc-why-title{color:var(--critical);}
.dc-why.why-high .dc-why-title{color:var(--high);}
.dc-why.why-medium .dc-why-title{color:var(--medium);}
.dc-why.why-low .dc-why-title{color:var(--low);}
.dc-why-reasons{display:flex;flex-direction:column;gap:3px;}
.dc-why-row{display:flex;align-items:center;gap:6px;color:var(--text-primary);font-size:11px;}
.dc-why-row span:first-child{font-size:13px;flex-shrink:0;}
/* health + metrics row */
.dc-metrics{padding:8px 14px 10px;display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:center;border-top:1px solid var(--border-color);}
.dc-score-ring{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;border:3px solid;}
.dc-score-ring.sr-red{border-color:var(--critical);color:var(--critical);}
.dc-score-ring.sr-orange{border-color:var(--high);color:var(--high);}
.dc-score-ring.sr-blue{border-color:var(--medium);color:var(--medium);}
.dc-score-ring.sr-green{border-color:var(--low);color:var(--low);}
.dc-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;}
.dc-stat{display:flex;flex-direction:column;}
.dc-stat-val{font-size:12px;font-weight:700;}
.dc-stat-lbl{font-size:10px;color:var(--text-secondary);}
/* footer */
.dc-footer{padding:8px 14px;border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;}
.dc-meta{font-size:10px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
/* AI reasoning section - kept for compat but hidden */
.ai-section{display:none;}
.av-bad{color:var(--critical);}
.av-neu{color:var(--text-secondary);}
.dc-footer{display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:9px;border-top:1px solid var(--border-color);}
.dc-meta{font-size:10px;color:var(--text-muted);}

/* ── Fault table ── */
.fault-table{width:100%;border-collapse:collapse;font-size:12px;}
.fault-table th{text-align:left;padding:9px 12px;color:var(--text-secondary);font-size:10px;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--border-color);font-weight:600;}
.fault-table td{padding:10px 12px;border-bottom:1px solid var(--border-color);}
.fault-table tr:last-child td{border-bottom:none;}
.fault-table tr:hover td{background:var(--bg-tertiary);}
.mini-bar{display:inline-block;width:50px;height:4px;background:var(--bg-tertiary);border-radius:2px;overflow:hidden;vertical-align:middle;margin-right:6px;}
.mini-fill{height:100%;border-radius:2px;}
.mf-critical{background:var(--critical);}
.mf-high{background:var(--high);}
.mf-medium{background:var(--medium);}
.mf-low{background:var(--low);}

/* ── Right panel ── */
.right-panel{display:flex;flex-direction:column;gap:14px;}
.panel{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;}
.panel-title{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:6px;}
.chart-box{position:relative;height:170px;}
/* Risk dist */
.rdist{display:flex;flex-direction:column;gap:8px;}
.rd-row{display:flex;align-items:center;gap:9px;}
.rd-label{width:66px;font-size:11px;font-weight:600;}
.rd-bar{flex:1;height:5px;background:var(--bg-tertiary);border-radius:2px;overflow:hidden;}
.rd-fill{height:100%;border-radius:2px;}
.rdf-critical{background:var(--critical);}
.rdf-high{background:var(--high);}
.rdf-medium{background:var(--medium);}
.rdf-low{background:var(--low);}
.rd-count{width:24px;text-align:right;font-size:11px;font-weight:700;}
/* Alerts */
.alert-feed{display:flex;flex-direction:column;gap:6px;max-height:280px;overflow-y:auto;}
.af-item{display:flex;gap:9px;padding:8px 10px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:6px;}
.af-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.af-dot.critical{background:var(--critical);}
.af-dot.high,.af-dot.warning{background:var(--high);}
.af-dot.medium{background:var(--medium);}
.af-body{flex:1;min-width:0;}
.af-name{font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.af-meta{font-size:10px;color:var(--text-secondary);margin-top:1px;}
.af-time{font-size:10px;color:var(--text-muted);white-space:nowrap;flex-shrink:0;}

/* ── Misc ── */
.empty{text-align:center;padding:50px 20px;color:var(--text-secondary);}
.empty i{font-size:36px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:50px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
.pulse{animation:pulse 2s ease infinite;}
/* Toast */
.toast-box{position:fixed;bottom:18px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:9px;font-size:12px;animation:tIn .2s ease;box-shadow:0 4px 20px rgba(0,0,0,.5);}
@keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);}
.toast.err{border-color:var(--critical);}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <h2>🌐 Network Monitor</h2>
    <p>SafeG Monitoring System</p>
  </div>
  <nav style="padding:8px 0;flex:1;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
    <a href="ai_dashboard.php"            class="nav-item"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item active"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
</aside>

<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div>
      <div class="page-title">⚡ Predictive Fault Analysis</div>
      <div class="page-subtitle" id="lastUpdated">Loading data...</div>
    </div>
    <div class="topbar-right">
      <div class="view-toggle">
        <button class="vtb active" id="btnCards" onclick="setView('cards')"><i class="bi bi-grid"></i> Cards</button>
        <button class="vtb"        id="btnTable" onclick="setView('table')"><i class="bi bi-list-ul"></i> Table</button>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="btn btn-accent btn-sm" onclick="runAnalysis()"><i class="bi bi-lightning-fill"></i> Run Analysis</button>
      <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card s-red">
      <div class="stat-num" id="stCritical" style="color:var(--critical)">—</div>
      <div class="stat-label">Critical</div>
    </div>
    <div class="stat-card s-orange">
      <div class="stat-num" id="stHigh" style="color:var(--high)">—</div>
      <div class="stat-label">High Risk</div>
    </div>
    <div class="stat-card s-yellow">
      <div class="stat-num" id="stSoon" style="color:#ffd32a">—</div>
      <div class="stat-label">Failing ≤ 14 Days</div>
    </div>
    <div class="stat-card s-blue">
      <div class="stat-num" id="stTotal" style="color:var(--medium)">—</div>
      <div class="stat-label">Total Monitored</div>
    </div>
    <div class="stat-card s-green">
      <div class="stat-num" id="stHealthy" style="color:var(--low)">—</div>
      <div class="stat-label">Healthy</div>
    </div>
  </div>

  <!-- Layout -->
  <div class="layout">
    <!-- Left: devices -->
    <div>
      <div class="filters">
        <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search device name or IP..." oninput="filterDevices()">
        <select class="filter-sel" id="riskFilter" onchange="filterDevices()">
          <option value="">All Risk Levels</option>
          <option value="CRITICAL">🔴 Critical</option>
          <option value="HIGH">🟠 High</option>
          <option value="MEDIUM">🔵 Medium</option>
          <option value="LOW">🟢 Low</option>
        </select>
        <select class="filter-sel" id="faultFilter" onchange="filterDevices()">
          <option value="">All Fault Types</option>
          <option value="hardware">⚙️ Hardware</option>
          <option value="network">🌐 Network</option>
          <option value="software">💻 Software</option>
          <option value="combined">🔀 Combined</option>
        </select>
        <select class="filter-sel" id="sortBy" onchange="filterDevices()">
          <option value="days">Sort: Days to Failure</option>
          <option value="health">Sort: Health Score</option>
          <option value="name">Sort: Name A–Z</option>
        </select>
      </div>

      <!-- Cards -->
      <div id="cardsView">
        <div class="cards-grid" id="cardsGrid">
          <div style="grid-column:1/-1"><div class="loader"></div></div>
        </div>
      </div>

      <!-- Table -->
      <div id="tableView" style="display:none;">
        <div style="overflow-x:auto;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;">
          <div id="tableWrap"><div class="loader"></div></div>
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="right-panel">
      <div class="panel">
        <div class="panel-title"><i class="bi bi-bar-chart-fill"></i> Risk Distribution</div>
        <div class="rdist" id="riskDist"><div style="color:var(--text-secondary);font-size:12px;">Loading...</div></div>
      </div>
      <div class="panel">
        <div class="panel-title"><i class="bi bi-pie-chart-fill"></i> Fault Type Breakdown</div>
        <div class="chart-box"><canvas id="faultChart"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-title"><i class="bi bi-graph-up"></i> Failure Timeline (Days)</div>
        <div class="chart-box"><canvas id="timelineChart"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-title"><i class="bi bi-bell-fill"></i> Recent Alerts</div>
        <div class="alert-feed" id="alertFeed"><div style="color:var(--text-secondary);font-size:12px;">Loading...</div></div>
      </div>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
const DEVICE_ICONS = {computer:'🖥️',server:'🗄️',laptop:'💻',router:'📡',switch:'🔌',printer:'🖨️',phone:'📱',tablet:'📱',camera:'📷',other:'📟'};
let allDevices = [];
let faultChart = null, timelineChart = null;

// Theme
function toggleTheme() {
  document.body.classList.toggle('light-mode');
  const l = document.body.classList.contains('light-mode');
  document.getElementById('themeIcon').className = l ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('pf_theme', l ? 'light' : 'dark');
}
if (localStorage.getItem('pf_theme') === 'light') {
  document.body.classList.add('light-mode');
  document.getElementById('themeIcon').className = 'bi bi-sun-fill';
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s) {
  if (!s) return '—';
  try { return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); }
  catch(e) { return s; }
}
function ago(s) {
  if (!s) return '—';
  const m = Math.round((Date.now()-new Date(s))/60000);
  if (m<1) return 'just now'; if (m<60) return m+'m ago';
  if (m<1440) return Math.round(m/60)+'h ago'; return Math.round(m/1440)+'d ago';
}

function riskCls(r) {
  return ({CRITICAL:'critical',HIGH:'high',MEDIUM:'medium',LOW:'low'})[String(r||'').toUpperCase()]||'low';
}
function daysCls(d) {
  d=parseInt(d); return d<=14?'dp-urgent':d<=45?'dp-warn':'dp-ok';
}
function hfCls(s) {
  s=parseFloat(s); return s<40?'hf-red':s<60?'hf-orange':s<80?'hf-blue':'hf-green';
}
function hColor(s) {
  s=parseFloat(s); return s<40?'var(--critical)':s<60?'var(--high)':s<80?'var(--medium)':'var(--low)';
}
function mfCls(r) {
  return ({critical:'mf-critical',high:'mf-high',medium:'mf-medium',low:'mf-low'})[r]||'mf-low';
}

function classifyFault(d) {
  const f = d.factors || {};
  const offline = parseInt(f.offline_incidents) || 0;
  const errors  = parseInt(f.total_errors)      || 0;
  const maint   = f.maintenance_count != null ? parseInt(f.maintenance_count) : -1; // -1 = unknown
  const critE   = parseInt(f.critical_errors)   || 0;
  const isOnline = d.status ? d.status==='online' : (f.is_online===true||f.is_online==1);
  const score   = parseFloat(d.health_score) || 0;
  const rc      = (d.risk_level||'').toUpperCase();

  // Healthy device with no real issues — classify by what we know
  if (rc==='LOW' || score>=80) {
    // Even healthy devices need a category — use dominant factor
    if (!isOnline) return 'network';
    if (offline > 0) return 'network';
    if (critE > 0)   return 'hardware';
    return 'healthy'; // explicit healthy state
  }

  const net = offline*2 + (!isOnline ? 3 : 0);
  const hw  = (maint===0 ? 2 : 0) + (critE>2 ? 2 : 0);
  const sw  = (errors>10 ? 2 : 0) + (critE>0 ? 1 : 0);
  const mx  = Math.max(net, hw, sw);

  if (mx===0) {
    // Still no signals — guess from risk level
    if (rc==='CRITICAL'||rc==='HIGH') return 'network'; // most common cause
    return 'healthy';
  }
  if (net===hw && hw>0 && net===mx) return 'combined';
  if (net===mx) return 'network';
  if (hw===mx)  return 'hardware';
  return 'software';
}

function faultLabel(t) {
  return ({hardware:'⚙️ Hardware',network:'🌐 Network',software:'💻 Software',combined:'🔀 Combined',healthy:'✅ Healthy',unknown:'❓ Unknown'})[t]||t;
}

function whySection(d) {
  const f = d.factors || {};
  const rc = riskCls(d.risk_level);
  const isOnline = d.status === 'online' || f.is_online === true || f.is_online == 1;
  const offline  = parseInt(f.offline_incidents || 0);
  const critE    = parseInt(f.critical_errors   || 0);
  const errR     = parseFloat(f.daily_error_rate || 0);
  const lastM    = parseInt(f.days_since_maintenance ?? 999);
  const days     = parseInt(d.days_until_failure) || 365;
  const score    = parseFloat(d.health_score || 0);
  const ftype    = classifyFault(d);

  // Build reason sentences — most severe first
  const reasons = [];

  if (!isOnline)
    reasons.push({icon:'📵', text:'Device is currently <strong>offline</strong>'});
  if (days <= 14)
    reasons.push({icon:'⏰', text:`Predicted failure in <strong>${days} day${days===1?'':'s'}</strong>`});
  if (critE > 0)
    reasons.push({icon:'🚨', text:`<strong>${critE}</strong> critical error${critE>1?'s':''} in last 60 days`});
  if (offline >= 3)
    reasons.push({icon:'📡', text:`<strong>${offline}</strong> offline incidents in last 90 days`});
  if (errR >= 0.5)
    reasons.push({icon:'❗', text:`High error rate: <strong>${errR.toFixed(2)}/day</strong>`});
  if (lastM >= 999)
    reasons.push({icon:'🔧', text:'<strong>Never</strong> had maintenance'});
  else if (lastM > 180)
    reasons.push({icon:'🔧', text:`No maintenance for <strong>${lastM} days</strong>`});
  if (score < 40)
    reasons.push({icon:'💔', text:`Health score critically low: <strong>${score.toFixed(0)}%</strong>`});

  // Fallback if nothing specific
  if (reasons.length === 0) {
    if (ftype === 'network')  reasons.push({icon:'🌐', text:'Network connectivity issues detected'});
    else if (ftype === 'hardware') reasons.push({icon:'⚙️', text:'Hardware degradation indicators present'});
    else reasons.push({icon:'📊', text:`Health at <strong>${score.toFixed(0)}%</strong> — monitoring closely`});
  }

  const titleMap = {critical:'⚠️ Why Critical', high:'⚠️ Why High Risk', medium:'📋 Risk Factors', low:'✅ Status'};
  const title = titleMap[rc] || '📋 Risk Factors';

  return `<div class="dc-why why-${rc}">
    <div class="dc-why-title">${title}</div>
    <div class="dc-why-reasons">
      ${reasons.slice(0,3).map(r=>`<div class="dc-why-row"><span>${r.icon}</span><span>${r.text}</span></div>`).join('')}
    </div>
  </div>`;
}

function renderCards(devices) {
  const grid = document.getElementById('cardsGrid');
  if (!devices.length) {
    grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><i class="bi bi-check-circle"></i><p>No devices match the filter</p></div>';
    return;
  }
  grid.innerHTML = devices.map(d => {
    const f      = d.factors || {};
    const rc     = riskCls(d.risk_level);
    const score  = parseFloat(d.health_score || 0);
    const days   = parseInt(d.days_until_failure) || 365;
    const ftype  = classifyFault(d);
    const isOnline = d.status === 'online' || f.is_online === true || f.is_online == 1;
    const isCrit = rc === 'critical';
    const errR   = parseFloat(f.daily_error_rate || 0);
    const offline= parseInt(f.offline_incidents || 0);
    const conf   = parseFloat(d.confidence_level || 50);

    // Score ring color
    const srCls = score < 40 ? 'sr-red' : score < 60 ? 'sr-orange' : score < 80 ? 'sr-blue' : 'sr-green';

    return `<div class="device-card rc-${rc}" onclick="location.href='device_detail.php?id=${d.device_id}'">

      <div class="dc-header">
        <div style="min-width:0;">
          <div class="dc-name">${isCrit?'<span class="pulse">🔴</span> ':''}${esc(d.device_name)}</div>
          <div class="dc-ip">${esc(d.device_ip||'—')} · ${esc(d.network_range||'—')}</div>
          <div class="dc-badges" style="margin-top:5px;">
            <span class="badge badge-${rc}">${d.risk_level}</span>
            <span class="badge badge-${ftype}">${faultLabel(ftype)}</span>
            <span class="badge badge-${isOnline?'online':'offline'}">${isOnline?'🟢 Online':'🔴 Offline'}</span>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="days-pill ${daysCls(days)}">${days}d</div>
          <div style="font-size:9px;color:var(--text-secondary);margin-top:3px;">${fmt(d.predicted_failure_date)}</div>
        </div>
      </div>

      ${whySection(d)}

      <div class="dc-metrics">
        <div class="dc-score-ring ${srCls}">${score.toFixed(0)}%</div>
        <div class="dc-stats-grid">
          <div class="dc-stat">
            <span class="dc-stat-val" style="color:${errR>0?'var(--high)':'var(--low)'}">${errR.toFixed(2)}/d</span>
            <span class="dc-stat-lbl">Error rate</span>
          </div>
          <div class="dc-stat">
            <span class="dc-stat-val" style="color:${offline>0?'var(--high)':'var(--low)'}">${offline}</span>
            <span class="dc-stat-lbl">Offline incidents</span>
          </div>
          <div class="dc-stat">
            <span class="dc-stat-val">${conf.toFixed(0)}%</span>
            <span class="dc-stat-lbl">Confidence</span>
          </div>
          <div class="dc-stat">
            <span class="dc-stat-val">${DEVICE_ICONS[d.device_type]||'📟'} ${esc(d.device_type||'device')}</span>
            <span class="dc-stat-lbl">Type</span>
          </div>
        </div>
      </div>

      <div class="dc-footer">
        <div class="dc-meta">${esc(d.device_name)}</div>
        <div style="display:flex;gap:5px;">
          <a href="device_detail.php?id=${d.device_id}" class="btn btn-ghost btn-sm" onclick="event.stopPropagation()"><i class="bi bi-box-arrow-up-right"></i> Details</a>
          <a href="maintenance_schedules.php?device_id=${d.device_id}" class="btn btn-accent btn-sm" onclick="event.stopPropagation()"><i class="bi bi-calendar-plus"></i> Schedule</a>
        </div>
      </div>
    </div>`;
  }).join('');
}

function renderTable(devices) {
  const wrap = document.getElementById('tableWrap');
  if (!devices.length) {
    wrap.innerHTML = '<div class="empty"><i class="bi bi-table"></i><p>No devices match</p></div>';
    return;
  }
  wrap.innerHTML = `<table class="fault-table">
    <thead><tr>
      <th>#</th><th>Device</th><th>Health</th><th>Risk</th><th>Fault Type</th>
      <th>Days Left</th><th>Predicted Date</th><th>Incidents</th><th>Errors</th><th>Action</th>
    </tr></thead>
    <tbody>${devices.map((d,i) => {
      const rc    = riskCls(d.risk_level);
      const score = parseFloat(d.health_score||0);
      const days  = parseInt(d.days_until_failure||365);
      const ftype = classifyFault(d);
      const f     = d.factors||{};
      return `<tr>
        <td style="color:var(--text-muted);font-size:11px;">${i+1}</td>
        <td>
          <a href="device_detail.php?id=${d.device_id}" style="color:var(--text-primary);text-decoration:none;font-weight:700;">${esc(d.device_name)}</a>
          <div style="font-size:11px;color:var(--text-secondary);">${esc(d.device_ip)}</div>
        </td>
        <td>
          <div style="display:flex;align-items:center;">
            <span class="mini-bar"><span class="mini-fill ${mfCls(rc)}" style="width:${score}%;display:block;height:100%;border-radius:2px;"></span></span>
            <span style="font-weight:700;font-size:11px;color:${hColor(score)}">${score.toFixed(1)}%</span>
          </div>
        </td>
        <td><span class="badge badge-${rc}">${d.risk_level}</span></td>
        <td><span class="badge badge-${ftype}">${faultLabel(ftype)}</span></td>
        <td><span class="days-pill ${daysCls(days)}">${days}d</span></td>
        <td style="font-size:11px;color:var(--text-secondary);">${fmt(d.predicted_failure_date)}</td>
        <td style="font-size:11px;color:var(--text-secondary);">${parseInt(f.offline_incidents||0)}</td>
        <td style="font-size:11px;color:var(--text-secondary);">${parseInt(f.total_errors||0)}</td>
        <td><a href="device_detail.php?id=${d.device_id}" class="btn btn-ghost btn-sm">Details</a></td>
      </tr>`;
    }).join('')}</tbody>
  </table>`;
}

function renderRiskDist(devices) {
  const c={CRITICAL:0,HIGH:0,MEDIUM:0,LOW:0};
  devices.forEach(d=>{ c[d.risk_level]=(c[d.risk_level]||0)+1; });
  const total = devices.length || 1;
  const meta = {
    CRITICAL:{cls:'rdf-critical',color:'var(--critical)'},
    HIGH:    {cls:'rdf-high',    color:'var(--high)'},
    MEDIUM:  {cls:'rdf-medium',  color:'var(--medium)'},
    LOW:     {cls:'rdf-low',     color:'var(--low)'},
  };
  document.getElementById('riskDist').innerHTML = Object.entries(c).map(([lv,cnt]) => `
    <div class="rd-row">
      <span class="rd-label" style="color:${meta[lv].color}">${lv}</span>
      <div class="rd-bar"><div class="rd-fill ${meta[lv].cls}" style="width:${Math.round(cnt/total*100)}%"></div></div>
      <span class="rd-count" style="color:${meta[lv].color}">${cnt}</span>
    </div>`).join('');
}

function renderFaultChart(devices) {
  const t={hardware:0,network:0,software:0,combined:0,healthy:0,unknown:0};
  devices.forEach(d=>{ const ft=classifyFault(d); t[ft]=(t[ft]||0)+1; });
  if (faultChart) faultChart.destroy();
  const isDark = !document.body.classList.contains('light-mode');
  const tickColor = isDark ? '#888' : '#666';
  faultChart = new Chart(document.getElementById('faultChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['Hardware','Network','Software','Combined','Healthy','Unknown'],
      datasets: [{
        data: [t.hardware,t.network,t.software,t.combined,t.healthy,t.unknown],
        backgroundColor: ['rgba(255,211,42,.7)','rgba(116,185,255,.7)','rgba(162,155,254,.7)','rgba(255,165,2,.7)','rgba(46,213,115,.7)','rgba(136,136,136,.3)'],
        borderColor:     ['#ffd32a','#74b9ff','#a29bfe','#ffa502','#10b981','#555'],
        borderWidth: 1,
      }]
    },
    options: {
      responsive:true, maintainAspectRatio:false, cutout:'62%',
      plugins:{ legend:{ position:'bottom', labels:{ color:tickColor, font:{family:'Montserrat',size:10}, padding:8 } } }
    }
  });
}

function renderTimelineChart(devices) {
  const b={'0-14d':0,'15-30d':0,'31-60d':0,'61-90d':0,'90d+':0};
  devices.forEach(d=>{
    const days = parseInt(d.days_until_failure||999);
    if(days<=14) b['0-14d']++; else if(days<=30) b['15-30d']++; else if(days<=60) b['31-60d']++; else if(days<=90) b['61-90d']++; else b['90d+']++;
  });
  if (timelineChart) timelineChart.destroy();
  const isDark = !document.body.classList.contains('light-mode');
  const tickColor = isDark ? '#888' : '#666';
  const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.06)';
  timelineChart = new Chart(document.getElementById('timelineChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: Object.keys(b),
      datasets: [{
        label: 'Devices',
        data: Object.values(b),
        backgroundColor: ['rgba(255,71,87,.65)','rgba(255,165,2,.65)','rgba(255,211,42,.65)','rgba(55,66,250,.65)','rgba(46,213,115,.65)'],
        borderColor:     ['var(--critical)','var(--high)','#ffd32a','var(--medium)','var(--low)'],
        borderWidth:1, borderRadius:4,
      }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false} },
      scales:{
        x:{ ticks:{color:tickColor,font:{family:'Montserrat',size:10}}, grid:{color:gridColor} },
        y:{ ticks:{color:tickColor,font:{family:'Montserrat',size:10},stepSize:1}, grid:{color:gridColor} }
      }
    }
  });
}

function renderAlerts(alerts) {
  const feed = document.getElementById('alertFeed');
  if (!alerts.length) {
    feed.innerHTML = '<div style="color:var(--text-secondary);font-size:12px;text-align:center;padding:14px;">No recent alerts</div>';
    return;
  }
  feed.innerHTML = alerts.slice(0,20).map(a => {
    const sev = (a.severity||'WARNING').toLowerCase();
    const dotCls = sev==='critical'?'critical':sev==='warning'?'warning':'medium';
    const badgeCls = sev==='critical'?'critical':sev==='warning'?'high':'medium';
    return `<div class="af-item">
      <div class="af-dot ${dotCls}"></div>
      <div class="af-body">
        <div class="af-name">${esc(a.device_name)} — ${esc(a.message||a.alert_type||'Alert')}</div>
        <div class="af-meta">
          <span class="badge badge-${badgeCls}">${a.severity}</span>
          ${a.recommended_action?' · '+esc(a.recommended_action.substring(0,36)):''}
          ${a.is_acknowledged==1?' · <span style="color:var(--low)">✓ Acked</span>':''}
        </div>
      </div>
      <div class="af-time">${ago(a.created_at)}</div>
    </div>`;
  }).join('');
}

function filterDevices() {
  const q    = document.getElementById('searchInput').value.toLowerCase();
  const risk = document.getElementById('riskFilter').value;
  const type = document.getElementById('faultFilter').value;
  const sort = document.getElementById('sortBy').value;

  let filtered = allDevices.filter(d => {
    const nm=(d.device_name||'').toLowerCase(), ip=(d.device_ip||'').toLowerCase();
    return (!q||nm.includes(q)||ip.includes(q))&&(!risk||d.risk_level===risk)&&(!type||classifyFault(d)===type);
  });

  filtered.sort((a,b)=>{
    if(sort==='health') return parseFloat(a.health_score)-parseFloat(b.health_score);
    if(sort==='name')   return (a.device_name||'').localeCompare(b.device_name||'');
    return parseInt(a.days_until_failure)-parseInt(b.days_until_failure);
  });

  renderCards(filtered);
  renderTable(filtered);
}

function setView(v) {
  document.getElementById('cardsView').style.display = v==='cards'?'block':'none';
  document.getElementById('tableView').style.display  = v==='table'?'block':'none';
  document.getElementById('btnCards').classList.toggle('active', v==='cards');
  document.getElementById('btnTable').classList.toggle('active', v==='table');
}

async function loadData() {
  document.getElementById('cardsGrid').innerHTML = '<div style="grid-column:1/-1"><div class="loader"></div></div>';
  document.getElementById('lastUpdated').textContent = 'Loading...';
  try {
    const [hr, ar] = await Promise.all([
      fetch('api_ai_predictions.php?action=get_health_metrics'),
      fetch('api_ai_predictions.php?action=get_alerts')
    ]);
    const ht = await hr.text(), at = await ar.text();
    let hd, ad;
    try { hd = JSON.parse(ht); } catch(e) { throw new Error('API returned non-JSON: '+ht.substring(0,80)); }
    try { ad = JSON.parse(at); } catch(e) { ad = {success:false,data:[]}; }
    if (!hd.success) throw new Error(hd.error||'Failed to load health data');

    allDevices = (hd.data||[]).map(d => {
      if (d.factors && typeof d.factors==='string') {
        try { d.factors = JSON.parse(d.factors); } catch(e) { d.factors={}; }
      }
      if (!d.factors) d.factors = {};
      return d;
    });

    const critical = allDevices.filter(d=>d.risk_level==='CRITICAL').length;
    const high     = allDevices.filter(d=>d.risk_level==='HIGH').length;
    const soon     = allDevices.filter(d=>parseInt(d.days_until_failure)<=14).length;
    const healthy  = allDevices.filter(d=>d.risk_level==='LOW').length;
    document.getElementById('stCritical').textContent = critical;
    document.getElementById('stHigh').textContent     = high;
    document.getElementById('stSoon').textContent     = soon;
    document.getElementById('stTotal').textContent    = allDevices.length;
    document.getElementById('stHealthy').textContent  = healthy;
    document.getElementById('lastUpdated').textContent = 'Last updated: '+new Date().toLocaleTimeString('en-MY');

    filterDevices();
    renderRiskDist(allDevices);
    renderFaultChart(allDevices);
    renderTimelineChart(allDevices);
    renderAlerts(ad.success ? ad.data||[] : []);
  } catch(err) {
    document.getElementById('cardsGrid').innerHTML = `<div class="empty" style="grid-column:1/-1"><i class="bi bi-exclamation-circle"></i><p>Error: ${esc(err.message)}</p></div>`;
    document.getElementById('lastUpdated').textContent = 'Failed to load';
    toast(err.message, 'err');
  }
}

async function runAnalysis() {
  toast('Running analysis on all devices...', 'ok');
  try {
    const r = await fetch('master_config.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'trigger_bulk_predict'})
    });
    const d = JSON.parse(await r.text());
    if (d.success) { toast(d.message||'Analysis complete', 'ok'); setTimeout(loadData, 800); }
    else toast(d.error||'Analysis failed', 'err');
  } catch(e) { toast('Error: '+e.message, 'err'); }
}

function toast(msg, type='ok') {
  const box = document.getElementById('toastBox');
  const t = document.createElement('div');
  t.className = 'toast '+type;
  t.innerHTML = `<span>${type==='ok'?'✅':'❌'}</span><span>${esc(msg)}</span>`;
  box.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

loadData();
setInterval(loadData, 60000);
</script>
</body>
</html>