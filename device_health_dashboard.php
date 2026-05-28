<?php
require_once 'config.php';
$yourIP = getLocalIP();
$parts = explode('.', $yourIP);
$networkRange = $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Device Health Dashboard - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>❤️</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

/* ── Sidebar ── */
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg-secondary);border-right:1px solid var(--border-color);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-brand{padding:18px 16px;border-bottom:1px solid var(--border-color);}
.sidebar-brand h2{font-size:13px;font-weight:700;}
.sidebar-brand p{font-size:10px;color:var(--text-secondary);margin-top:2px;}
.nav-group-label{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;padding:12px 14px 4px;}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:6px;text-decoration:none;color:var(--text-secondary);font-size:12px;font-weight:500;margin:1px 6px;transition:all .15s;}
.nav-item:hover{background:var(--bg-tertiary);color:var(--text-primary);}
.nav-item.active{background:rgba(102,126,234,.1);color:var(--accent);border:1px solid rgba(102,126,234,.2);}
.nav-item i{font-size:13px;width:16px;text-align:center;}

/* Sidebar stats */
.sidebar-stats{padding:10px 12px;border-top:1px solid var(--border-color);margin-top:auto;}
.sidebar-stats-title{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
.ss-row{display:flex;align-items:center;justify-content:space-between;padding:4px 6px;border-radius:4px;font-size:11px;margin-bottom:2px;}
.ss-row:hover{background:var(--bg-tertiary);}
.ss-label{font-weight:500;color:var(--text-secondary);}
.ss-count{font-weight:800;}

/* ── Main ── */
.main{margin-left:220px;padding:24px;}

/* ── Topbar ── */
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none;}
.btn-accent{background:var(--accent);color:#fff;}
.btn-accent:hover{opacity:.85;}
.btn-success{background:#2ed573;color:#000;}
.btn-success:hover{opacity:.85;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);}
.btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}

/* ── Stats row ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;}
.sc-red::after{background:var(--critical);}
.sc-orange::after{background:var(--high);}
.sc-blue::after{background:var(--medium);}
.sc-green::after{background:var(--low);}
.sc-yellow::after{background:#ffd32a;}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}

/* ── Controls bar ── */
.controls{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
.filter-inp{flex:1;min-width:180px;}
.sort-btn{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);padding:7px 12px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;}
.sort-btn.active,.sort-btn:hover{border-color:var(--accent);color:var(--accent);}

/* ── Devices grid ── */
.devices-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}

/* Health card — distinct from Predictive Fault */
.device-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;cursor:pointer;transition:all .2s;overflow:hidden;display:flex;flex-direction:column;}
.device-card:hover{border-color:var(--border-hover);transform:translateY(-1px);box-shadow:0 4px 18px rgba(0,0,0,.22);}
/* LEFT accent bar — differentiates from predictive fault top border */
.device-card.critical{border-left:4px solid var(--critical);}
.device-card.high    {border-left:4px solid var(--high);}
.device-card.medium  {border-left:4px solid var(--medium);}
.device-card.low     {border-left:4px solid var(--low);}

/* Header */
.dc-head{padding:11px 13px 8px;display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
.dc-name{font-size:13px;font-weight:700;margin-bottom:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:155px;}
.dc-ip{font-size:10px;color:var(--text-secondary);}
.dc-badges{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;}
.badge-critical{background:rgba(255,71,87,.12);color:var(--critical);}
.badge-high    {background:rgba(255,165,2,.12); color:var(--high);}
.badge-medium  {background:rgba(55,66,250,.12); color:var(--medium);}
.badge-low     {background:rgba(46,213,115,.12);color:var(--low);}
.badge-online  {background:rgba(46,213,115,.12);color:var(--low);}
.badge-offline {background:rgba(255,71,87,.12); color:var(--critical);}
.badge-unknown {background:rgba(136,136,136,.1);color:var(--text-secondary);}

/* Health bar — main focus of this page */
.hb-section{padding:4px 13px 8px;}
.hb-hdr{display:flex;justify-content:space-between;align-items:center;font-size:11px;margin-bottom:5px;}
.hb-lbl{color:var(--text-secondary);font-weight:500;}
.hb-pct{font-weight:800;font-size:14px;}
.hb-track{height:6px;background:var(--bg-tertiary);border-radius:3px;overflow:hidden;}
.hb-fill{height:100%;border-radius:3px;transition:width 1s ease;}
.hf-red   {background:linear-gradient(90deg,#ff4757,#ff6b81);}
.hf-orange{background:linear-gradient(90deg,#ffa502,#ffbe76);}
.hf-blue  {background:linear-gradient(90deg,#3742fa,#6c7bff);}
.hf-green {background:linear-gradient(90deg,#2ed573,#7bed9f);}

/* Maintenance strip */
.maint-strip{margin:0 13px 8px;padding:6px 10px;border-radius:6px;font-size:11px;display:flex;align-items:center;gap:8px;}
.maint-strip.ms-ok   {background:rgba(46,213,115,.07); border:1px solid rgba(46,213,115,.15);}
.maint-strip.ms-warn {background:rgba(255,165,2,.07);  border:1px solid rgba(255,165,2,.15);}
.maint-strip.ms-bad  {background:rgba(255,71,87,.07);  border:1px solid rgba(255,71,87,.15);}
.maint-strip.ms-none {background:rgba(136,136,136,.06);border:1px solid rgba(136,136,136,.12);}
.ms-icon{font-size:14px;flex-shrink:0;}
.ms-text{flex:1;color:var(--text-primary);}
.ms-when{font-size:10px;color:var(--text-muted);}

/* 3-stat row */
.dc-stats3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1px;background:var(--border-color);border-top:1px solid var(--border-color);}
.dc-s3{background:var(--bg-secondary);padding:8px 0;text-align:center;}
.dc-s3-val{font-size:12px;font-weight:800;line-height:1;}
.dc-s3-lbl{font-size:9px;color:var(--text-secondary);margin-top:2px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}

/* Offline banner */
.offline-banner{margin:0 13px 8px;padding:6px 10px;background:rgba(255,71,87,.07);border:1px solid rgba(255,71,87,.2);border-radius:6px;font-size:11px;color:var(--text-secondary);}
.offline-banner strong{color:var(--critical);}

/* Days pill */
.days-pill{display:inline-block;padding:3px 9px;border-radius:14px;font-size:11px;font-weight:800;}
.dp-urgent{background:rgba(255,71,87,.12);color:var(--critical);}
.dp-warn  {background:rgba(255,165,2,.12); color:var(--high);}
.dp-ok    {background:rgba(46,213,115,.1); color:var(--low);}

/* Footer */
.dc-footer{padding:8px 13px;border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;margin-top:auto;}
.dc-meta{font-size:10px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px;}

/* keep hb-wrap alias */
.hb-wrap{margin:8px 0 4px;}
.hb-track-sm{height:4px;background:var(--bg-tertiary);border-radius:2px;overflow:hidden;}


/* ── Modal ── */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);}
.modal-header h2{font-size:15px;font-weight:700;}
.modal-close{background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;padding:0 4px;}
.modal-close:hover{color:var(--text-primary);}
.modal-body{padding:20px;}
.modal-footer{padding:12px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:8px;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.form-control{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:8px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.form-control:focus{outline:none;border-color:var(--accent);}
textarea.form-control{resize:vertical;}

/* ── Notif bell ── */
/* notif-btn now uses .icon-btn style */
/* Notif modal */
.nm-overlay{display:none;position:fixed;inset:0;z-index:300;}
.nm-overlay.open{display:block;}
.nm-modal{position:fixed;top:70px;right:16px;width:360px;max-height:540px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;z-index:500;display:none;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.7);color:var(--text-primary);}
.nm-modal.open{display:flex;}
/* Force dark colors inside modal so light-mode body doesn't affect it */
.nm-modal *{font-family:'Montserrat',sans-serif;}
.nm-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border-color);flex-shrink:0;background:var(--bg-secondary);border-radius:10px 10px 0 0;}
.nm-head h3{font-size:13px;font-weight:700;color:var(--text-primary);}
.nm-actions{display:flex;gap:5px;}
.nm-btn{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer;font-family:'Montserrat',sans-serif;transition:all .15s;}
.nm-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.nm-tabs{display:flex;padding:6px 10px 0;border-bottom:1px solid var(--border-color);gap:2px;overflow-x:auto;background:var(--bg-secondary);flex-shrink:0;}
.ntab{padding:6px 9px;border:none;background:none;font-family:'Montserrat',sans-serif;font-size:10px;font-weight:700;color:var(--text-secondary);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:color .15s;}
.ntab:hover{color:var(--text-primary);}
.ntab.active{color:var(--accent);border-bottom-color:var(--accent);}
.ntab-cnt{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);font-size:9px;padding:1px 5px;border-radius:8px;font-weight:800;margin-left:2px;}
.ntab.active .ntab-cnt{background:var(--accent);color:#fff;border-color:var(--accent);}
.nm-list{flex:1;overflow-y:auto;padding:8px;min-height:0;}
.nm-footer{flex-shrink:0;border-top:1px solid var(--border-color);padding:10px 14px;}
.nm-see-all{display:flex;align-items:center;justify-content:center;gap:7px;color:var(--accent);font-size:12px;font-weight:700;text-decoration:none;padding:7px;border-radius:6px;transition:all .15s;}
.nm-see-all:hover{background:rgba(102,126,234,.1);}
.nitem{padding:9px 10px;border-radius:6px;cursor:pointer;display:flex;gap:9px;margin-bottom:4px;border:1px solid var(--border-color);background:var(--bg-tertiary);}
.nitem:hover{border-color:var(--border-hover);background:var(--bg-secondary);}
.nitem.unread.nc{border-color:rgba(255,71,87,.3);background:rgba(255,71,87,.06);}
.nitem.unread.nw{border-color:rgba(255,165,2,.3);background:rgba(255,165,2,.06);}
.nicon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.nic{background:rgba(255,71,87,.15);color:var(--critical);}
.niw{background:rgba(255,165,2,.15);color:var(--high);}
.nbody{flex:1;min-width:0;}
.ntitle{font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-primary);}
.ndesc{font-size:10px;color:var(--text-secondary);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ntime{font-size:9px;color:var(--text-muted);margin-top:2px;}
.nempty{text-align:center;padding:30px;color:var(--text-secondary);font-size:12px;}
.nempty i{display:block;font-size:28px;margin-bottom:8px;opacity:.3;}
.nspinner{width:24px;height:24px;border:2px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:30px auto;}

/* ── Empty / loader ── */
.empty{text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
.pulse{animation:pulse 2s ease infinite;}

/* Toast */
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:9px;font-size:12px;animation:tIn .2s ease;}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);}
.toast.err{border-color:var(--critical);}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>

<!-- Notif modal (overlay) -->
<div class="nm-overlay" id="notifOverlay" onclick="notifClose()"></div>

<aside class="sidebar">
  <div class="sidebar-brand">
    <h2>🌐 Network Monitor</h2>
    <p>SafeG Monitoring System</p>
  </div>
  <nav style="padding:8px 0;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
    <a href="ai_dashboard.php"            class="nav-item"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item active"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Risk Summary</div>
    <div class="ss-row"><span class="ss-label" style="color:var(--critical)">Critical</span><span class="ss-count" style="color:var(--critical)" id="sb-critical">—</span></div>
    <div class="ss-row"><span class="ss-label" style="color:var(--high)">High</span><span class="ss-count" style="color:var(--high)" id="sb-high">—</span></div>
    <div class="ss-row"><span class="ss-label" style="color:var(--medium)">Medium</span><span class="ss-count" style="color:var(--medium)" id="sb-medium">—</span></div>
    <div class="ss-row"><span class="ss-label" style="color:var(--low)">Low</span><span class="ss-count" style="color:var(--low)" id="sb-low">—</span></div>
  </div>
</aside>

<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div>
      <div class="page-title">❤️ Device Health Dashboard</div>
      <div class="page-subtitle" id="lastUpdated">Loading...</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-success btn-sm" onclick="showScheduleModal()"><i class="bi bi-calendar-plus"></i> Schedule Maintenance</button>
      <button class="btn btn-ghost btn-sm" onclick="loadDevices()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
      <!-- Bell button inline in topbar -->
      <div style="position:relative;">
        <button class="icon-btn" onclick="notifOpen()" title="Notifications"><i class="bi bi-bell-fill"></i></button>
        <span id="notifBadge" style="display:none;position:absolute;top:-5px;right:-5px;background:var(--critical);color:#fff;font-size:9px;font-weight:800;border-radius:8px;padding:1px 5px;min-width:18px;text-align:center;pointer-events:none;"></span>
      </div>
    </div>

    <!-- Notif dropdown (anchored below topbar) -->
    <div class="nm-modal" id="notifModal">
      <div class="nm-head">
        <h3>🔔 Notifications</h3>
        <div class="nm-actions">
          <button class="nm-btn" onclick="notifMarkAll()">Mark all read</button>
          <button class="nm-btn" onclick="notifRefresh()"><i class="bi bi-arrow-clockwise"></i></button>
          <button class="nm-btn" onclick="notifClose()">✕</button>
        </div>
      </div>
      <div class="nm-tabs">
        <button class="ntab active" onclick="notifTab('all',this)">All <span class="ntab-cnt" id="nc-all">0</span></button>
        <button class="ntab" onclick="notifTab('offline',this)"><i class="bi bi-wifi-off"></i> Offline <span class="ntab-cnt" id="nc-offline">0</span></button>
        <button class="ntab" onclick="notifTab('health',this)"><i class="bi bi-heart-pulse"></i> Health <span class="ntab-cnt" id="nc-health">0</span></button>
        <button class="ntab" onclick="notifTab('prediction',this)"><i class="bi bi-lightning-charge"></i> Failure <span class="ntab-cnt" id="nc-prediction">0</span></button>
        <button class="ntab" onclick="notifTab('overdue',this)"><i class="bi bi-calendar-x"></i> Overdue <span class="ntab-cnt" id="nc-overdue">0</span></button>
      </div>
      <div class="nm-list" id="notifList"><div class="nspinner"></div></div>
      <div class="nm-footer">
        <a href="notifications.php" class="nm-see-all"><i class="bi bi-list-ul"></i> See All Notifications</a>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card sc-red">
      <div class="stat-num" id="stCritical" style="color:var(--critical)">—</div>
      <div class="stat-label">Critical</div>
    </div>
    <div class="stat-card sc-orange">
      <div class="stat-num" id="stHigh" style="color:var(--high)">—</div>
      <div class="stat-label">High Risk</div>
    </div>
    <div class="stat-card sc-blue">
      <div class="stat-num" id="stMedium" style="color:var(--medium)">—</div>
      <div class="stat-label">Medium</div>
    </div>
    <div class="stat-card sc-green">
      <div class="stat-num" id="stLow" style="color:var(--low)">—</div>
      <div class="stat-label">Low / Healthy</div>
    </div>
    <div class="stat-card sc-yellow">
      <div class="stat-num" id="stOffline" style="color:#ffd32a">—</div>
      <div class="stat-label">Offline Devices</div>
    </div>
  </div>

  <!-- Controls -->
  <div class="controls">
    <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search device name or IP..." oninput="applyFilters()">
    <select class="filter-sel" id="riskFilter" onchange="applyFilters()">
      <option value="">All Risk Levels</option>
      <option value="CRITICAL">🔴 Critical</option>
      <option value="HIGH">🟠 High</option>
      <option value="MEDIUM">🔵 Medium</option>
      <option value="LOW">🟢 Low</option>
    </select>
    <select class="filter-sel" id="networkFilter" onchange="loadDevices()">
      <option value="">All Networks</option>
    </select>
    <select class="filter-sel" id="statusFilter" onchange="applyFilters()">
      <option value="">All Status</option>
      <option value="online">🟢 Online</option>
      <option value="offline">🔴 Offline</option>
    </select>
    <button class="sort-btn active" data-sort="risk"   onclick="setSort('risk')">Risk</button>
    <button class="sort-btn"        data-sort="health" onclick="setSort('health')">Health</button>
    <button class="sort-btn"        data-sort="days"   onclick="setSort('days')">Days Left</button>
    <button class="sort-btn"        data-sort="name"   onclick="setSort('name')">A–Z</button>
  </div>

  <!-- Grid -->
  <div id="loadingState"><div class="loader"></div></div>
  <div id="devicesGrid" class="devices-grid" style="display:none;"></div>
</div>

<!-- Schedule modal -->
<div id="scheduleModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="bi bi-calendar-plus"></i> Schedule Maintenance</h2>
      <button class="modal-close" onclick="closeModal('scheduleModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Device *</label>
        <select id="mdDevice" class="form-control"><option value="">Select a device...</option></select>
      </div>
      <div class="form-group">
        <label>Task Name *</label>
        <input type="text" id="mdTask" class="form-control" placeholder="e.g. Full hardware inspection">
      </div>
      <div class="form-group">
        <label>Scheduled Date *</label>
        <input type="datetime-local" id="mdDate" class="form-control">
      </div>
      <div class="form-group">
        <label>Priority</label>
        <select id="mdPriority" class="form-control">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
        </select>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea id="mdDesc" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('scheduleModal')">Cancel</button>
      <button class="btn btn-accent" onclick="submitSchedule()"><i class="bi bi-check-lg"></i> Schedule</button>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allDevices = [], filteredDevices = [], currentSort = 'risk';

// ── Theme ──
function toggleTheme() {
  document.body.classList.toggle('light-mode');
  const l = document.body.classList.contains('light-mode');
  document.getElementById('themeIcon').className = l ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('dhd_theme', l ? 'light' : 'dark');
}
if (localStorage.getItem('dhd_theme') === 'light') {
  document.body.classList.add('light-mode');
  document.getElementById('themeIcon').className = 'bi bi-sun-fill';
}

// ── Helpers ──
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s) { if (!s) return '—'; try { return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); } catch(e){ return s; } }

function riskCls(r) { return ({CRITICAL:'critical',HIGH:'high',MEDIUM:'medium',LOW:'low'})[String(r||'').toUpperCase()]||'unknown'; }
function hfCls(s)   { s=parseFloat(s); return s<40?'hf-red':s<60?'hf-orange':s<80?'hf-blue':'hf-green'; }
function hColor(s)  { s=parseFloat(s); return s<40?'var(--critical)':s<60?'var(--high)':s<80?'var(--medium)':'var(--low)'; }
function daysCls(d) { d=parseInt(d); return d<=14?'dp-urgent':d<=45?'dp-warn':'dp-ok'; }

function isDevOnline(d) {
  if (d.status) return d.status === 'online';
  const f = d.factors||{};
  return f.is_online === true || f.is_online === 1;
}

function detectOfflineReason(d) {
  const f = d.factors||{};
  const hrs = parseFloat(f.hours_since_last_seen||0);
  if (hrs < 1)  return 'Offline < 1h — may be rebooting';
  if (hrs < 6)  return `Offline ${hrs.toFixed(1)}h — check power & cable`;
  if (hrs < 24) return `Offline ${hrs.toFixed(0)}h — device likely powered off`;
  return `Offline ${(hrs/24).toFixed(1)} days — may be decommissioned`;
}

function maintStatus(d) {
  const f     = d.factors||{};
  const lastM = f.days_since_maintenance != null ? parseInt(f.days_since_maintenance) : 999;
  const cnt   = parseInt(f.maintenance_count)||0;
  if (lastM >= 999) return {cls:'ms-none', icon:'🔧', text:'No maintenance record',       when:''};
  if (lastM <= 30)  return {cls:'ms-ok',   icon:'✅', text:'Recently maintained',          when:lastM+'d ago'};
  if (lastM <= 90)  return {cls:'ms-ok',   icon:'🔧', text:'Maintenance up to date',       when:lastM+'d ago'};
  if (lastM <= 180) return {cls:'ms-warn', icon:'⚠️', text:'Maintenance due soon',         when:lastM+'d ago'};
  return               {cls:'ms-bad',  icon:'❗', text:'Overdue for maintenance',        when:lastM+'d ago'};
}

function renderCard(d) {
  const rc      = riskCls(d.risk_level);
  const score   = parseFloat(d.health_score||0);
  const days    = parseInt(d.days_until_failure)||365;
  const conf    = parseFloat(d.confidence_level||0);
  const online  = isDevOnline(d);
  const f       = d.factors||{};
  const errRate = parseFloat(f.daily_error_rate)||0;
  const incidents = parseInt(f.offline_incidents)||0;
  const lastM   = f.days_since_maintenance != null ? parseInt(f.days_since_maintenance) : 999;
  const hours   = parseFloat(f.hours_since_last_seen)||0;
  const ms      = maintStatus(d);

  // Health bar colour
  const hfCls   = score>=70?'hf-green':score>=50?'hf-blue':score>=30?'hf-orange':'hf-red';
  const hColor  = score>=70?'var(--low)':score>=50?'var(--medium)':score>=30?'var(--high)':'var(--critical)';

  // Last seen text
  const seenTxt = !online
    ? (hours<24 ? Math.round(hours)+'h ago' : Math.round(hours/24)+'d ago')
    : 'Now';

  // Uptime from stats — use offline incidents as proxy
  const uptimePct = incidents===0 ? '~100%' : incidents<3 ? '>95%' : '>80%';

  const devIcons = {computer:'🖥️',server:'🗄️',laptop:'💻',router:'📡',switch:'🔌',printer:'🖨️',phone:'📱',tablet:'📱',camera:'📷',other:'📟'};
  const devIcon  = devIcons[d.device_type]||'📟';

  return `<div class="device-card ${rc}" id="device-${d.device_id}" onclick="location.href='device_detail.php?id=${d.device_id}'">

    <!-- Header -->
    <div class="dc-head">
      <div style="flex:1;min-width:0;">
        <div class="dc-name">${rc==='critical'?'<span class="pulse">🔴</span> ':''}${esc(d.device_name)}</div>
        <div class="dc-ip">${esc(d.device_ip||'—')} · ${esc(d.network_range||'—')}</div>
        <div class="dc-badges">
          <span class="badge badge-${rc}">${d.risk_level||'?'}</span>
          <span class="badge badge-${online?'online':'offline'}">${online?'🟢 Online':'🔴 Offline'}</span>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;margin-left:6px;">
        <div style="font-size:10px;color:var(--text-secondary);margin-bottom:3px;">${devIcon} ${esc(d.device_type||'device')}</div>
        <span class="days-pill ${daysCls(days)}">${days}d left</span>
      </div>
    </div>

    <!-- Health bar — main focus -->
    <div class="hb-section">
      <div class="hb-hdr">
        <span class="hb-lbl">Health Score</span>
        <span class="hb-pct" style="color:${hColor}">${score.toFixed(1)}%</span>
      </div>
      <div class="hb-track"><div class="hb-fill ${hfCls}" style="width:${score}%"></div></div>
    </div>

    <!-- Offline banner (only if offline) -->
    ${!online ? `<div class="offline-banner">⚠️ <strong>Offline</strong> — ${esc(detectOfflineReason(d))}</div>` : ''}

    <!-- Maintenance status strip -->
    <div class="maint-strip ${ms.cls}">
      <span class="ms-icon">${ms.icon}</span>
      <span class="ms-text">${ms.text}</span>
      ${ms.when ? `<span class="ms-when">${ms.when}</span>` : ''}
    </div>

    <!-- 3 key stats -->
    <div class="dc-stats3">
      <div class="dc-s3">
        <div class="dc-s3-val" style="color:${errRate===0?'var(--low)':errRate<0.5?'var(--high)':'var(--critical)'}">${errRate.toFixed(2)}</div>
        <div class="dc-s3-lbl">Err/day</div>
      </div>
      <div class="dc-s3">
        <div class="dc-s3-val" style="color:${incidents===0?'var(--low)':incidents<3?'var(--high)':'var(--critical)'}">${incidents}</div>
        <div class="dc-s3-lbl">Incidents</div>
      </div>
      <div class="dc-s3">
        <div class="dc-s3-val" style="color:var(--text-secondary)">${seenTxt}</div>
        <div class="dc-s3-lbl">Last seen</div>
      </div>
    </div>

    <!-- Footer -->
    <div class="dc-footer">
      <div class="dc-meta">${esc(d.device_name)}</div>
      <div style="display:flex;gap:5px;">
        <a href="device_detail.php?id=${d.device_id}" class="btn btn-ghost btn-sm" onclick="event.stopPropagation()"><i class="bi bi-box-arrow-up-right"></i> Details</a>
        <button class="btn btn-accent btn-sm" onclick="event.stopPropagation();scheduleFor(${d.device_id})"><i class="bi bi-calendar-plus"></i> Schedule</button>
      </div>
    </div>
  </div>`;
}
function renderDevices() {
  const grid = document.getElementById('devicesGrid');
  if (!filteredDevices.length) {
    grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><i class="bi bi-search"></i><p>No devices match the filter</p></div>';
    return;
  }
  grid.innerHTML = filteredDevices.map(renderCard).join('');
}

function applyFilters() {
  const q      = document.getElementById('searchInput').value.toLowerCase();
  const risk   = document.getElementById('riskFilter').value;
  const status = document.getElementById('statusFilter').value;

  filteredDevices = allDevices.filter(d => {
    const nm = (d.device_name||'').toLowerCase(), ip = (d.device_ip||'').toLowerCase();
    const matchQ      = !q      || nm.includes(q) || ip.includes(q);
    const matchRisk   = !risk   || d.risk_level === risk;
    const online      = isDevOnline(d);
    const matchStatus = !status || (status==='online' && online) || (status==='offline' && !online);
    return matchQ && matchRisk && matchStatus;
  });

  const riskOrder = {CRITICAL:0,HIGH:1,MEDIUM:2,LOW:3,UNKNOWN:4};
  filteredDevices.sort((a,b) => {
    switch(currentSort) {
      case 'health': return parseFloat(a.health_score) - parseFloat(b.health_score);
      case 'days':   return (parseInt(a.days_until_failure)||365) - (parseInt(b.days_until_failure)||365);
      case 'name':   return (a.device_name||'').localeCompare(b.device_name||'');
      default:       return (riskOrder[a.risk_level]||4) - (riskOrder[b.risk_level]||4);
    }
  });

  renderDevices();
}

function setSort(sort) {
  currentSort = sort;
  document.querySelectorAll('.sort-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.sort === sort);
  });
  applyFilters();
}

function updateStats() {
  const critical = allDevices.filter(d=>d.risk_level==='CRITICAL').length;
  const high     = allDevices.filter(d=>d.risk_level==='HIGH').length;
  const medium   = allDevices.filter(d=>d.risk_level==='MEDIUM').length;
  const low      = allDevices.filter(d=>d.risk_level==='LOW').length;
  const offline  = allDevices.filter(d=>!isDevOnline(d)).length;
  document.getElementById('stCritical').textContent = critical;
  document.getElementById('stHigh').textContent     = high;
  document.getElementById('stMedium').textContent   = medium;
  document.getElementById('stLow').textContent      = low;
  document.getElementById('stOffline').textContent  = offline;
  document.getElementById('sb-critical').textContent = critical;
  document.getElementById('sb-high').textContent     = high;
  document.getElementById('sb-medium').textContent   = medium;
  document.getElementById('sb-low').textContent      = low;
}

function populateNetworkFilter() {
  const sel = document.getElementById('networkFilter');
  const cur = sel.value;
  const nets = [...new Set(allDevices.map(d=>d.network_range).filter(n=>n))];
  sel.innerHTML = '<option value="">All Networks</option>' +
    nets.map(n => `<option value="${esc(n)}">${esc(n)} (${allDevices.filter(d=>d.network_range===n).length})</option>`).join('');
  sel.value = cur;
}

async function loadDevices() {
  document.getElementById('loadingState').style.display = 'block';
  document.getElementById('devicesGrid').style.display  = 'none';
  document.getElementById('lastUpdated').textContent = 'Loading...';

  try {
    const net = document.getElementById('networkFilter').value;
    let url = 'api_ai_predictions.php?action=get_health_metrics';
    if (net) url += '&network_range=' + encodeURIComponent(net);

    const res  = await fetch(url);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('API error: '+text.substring(0,80)); }
    if (!data.success) throw new Error(data.error||'Failed to load');

    allDevices = (data.data||[]).map(d => {
      if (d.factors && typeof d.factors === 'string') {
        try { d.factors = JSON.parse(d.factors); } catch(e) { d.factors = {}; }
      }
      if (!d.factors) d.factors = {};
      return d;
    });

    populateNetworkFilter();
    updateStats();
    applyFilters();
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('devicesGrid').style.display  = 'grid';
    document.getElementById('lastUpdated').textContent = 'Updated: '+new Date().toLocaleTimeString('en-MY');
  } catch(err) {
    document.getElementById('loadingState').innerHTML =
      `<div class="empty"><i class="bi bi-exclamation-circle"></i><p>Error: ${esc(err.message)}</p>
       <button class="btn btn-ghost" onclick="loadDevices()" style="margin-top:12px;"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>`;
  }
}

// ── Schedule modal ──
function showScheduleModal() {
  const sel = document.getElementById('mdDevice');
  sel.innerHTML = '<option value="">Select a device...</option>' +
    allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} — ${esc(d.device_ip)}</option>`).join('');
  const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate()+1);
  document.getElementById('mdDate').value = tomorrow.toISOString().slice(0,16);
  document.getElementById('scheduleModal').classList.add('show');
}

function scheduleFor(deviceId) {
  showScheduleModal();
  document.getElementById('mdDevice').value = deviceId;
  const d = allDevices.find(x=>x.device_id==deviceId);
  if (d) document.getElementById('mdTask').value = 'Maintenance for '+d.device_name;
}

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

async function submitSchedule() {
  const deviceId = document.getElementById('mdDevice').value;
  const taskName = document.getElementById('mdTask').value.trim();
  const date     = document.getElementById('mdDate').value;
  if (!deviceId || !taskName || !date) { toast('Please fill all required fields', 'err'); return; }

  closeModal('scheduleModal');
  try {
    const r = await fetch('api_maintenance_schedules.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        device_id: parseInt(deviceId), task_name: taskName,
        task_category: 'general', scheduled_date: date,
        estimated_duration: 60, priority: document.getElementById('mdPriority').value,
        status: 'pending', recurring: 0,
        description: document.getElementById('mdDesc').value
      })
    });
    const d = JSON.parse(await r.text());
    if (d.success) {
      toast('✅ Maintenance scheduled!', 'ok');
      document.getElementById('mdTask').value = '';
      document.getElementById('mdDesc').value = '';
    } else {
      toast(d.error||'Failed to schedule', 'err');
    }
  } catch(e) { toast('Error: '+e.message, 'err'); }
}

function toast(msg, type='ok') {
  const box = document.getElementById('toastBox');
  const t = document.createElement('div');
  t.className = 'toast '+type;
  t.innerHTML = `<span>${esc(msg)}</span>`;
  box.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

// ── Init ──
loadDevices();
setInterval(loadDevices, 60000);
</script>

<!-- Notification system (preserved from original) -->
<script>
(function(){
  var _n=[],_tab="all",_loaded=false;
  var _acked=new Set(JSON.parse(localStorage.getItem("nb_acked")||"[]"));
  function saveAcked(){localStorage.setItem("nb_acked",JSON.stringify([..._acked]));}
  function isAcked(n){return n.acked||_acked.has(String(n.id));}
  window.notifOpen=function(){document.getElementById("notifModal").classList.add("open");document.getElementById("notifOverlay").classList.add("open");if(!_loaded)notifLoad();};
  window.notifClose=function(){document.getElementById("notifModal").classList.remove("open");document.getElementById("notifOverlay").classList.remove("open");};
  window.notifTab=function(tab,el){_tab=tab;document.querySelectorAll(".ntab").forEach(function(b){b.classList.remove("active");});el.classList.add("active");notifRender();};
  window.notifRefresh=function(){_loaded=false;notifLoad();};
  window.notifMarkAll=function(){_n.forEach(function(n){_acked.add(String(n.id));});saveAcked();notifRender();updateBadge();};
  function fmtAgo(d){if(!d)return"";var diff=Math.floor((Date.now()-new Date(d))/1000);if(diff<60)return"Just now";if(diff<3600)return Math.floor(diff/60)+"m ago";if(diff<86400)return Math.floor(diff/3600)+"h ago";return Math.floor(diff/86400)+"d ago";}
  function buildNotifs(alerts,devices,overdue){
    var list=[];
    (alerts||[]).forEach(function(a){list.push({id:"alert_"+a.id,_dbId:a.id,type:"prediction",sev:a.severity||"WARNING",title:"Predicted Failure: "+a.device_name,desc:(a.message||"Device predicted to fail")+(a.days_until?" - "+a.days_until+" days left":""),device_id:a.device_id,time:a.created_at,acked:a.is_acknowledged==1});});
    (devices||[]).filter(function(d){return!isDevOnline(d);}).forEach(function(d){list.push({id:"offline_"+d.device_id,_dbId:null,type:"offline",sev:"CRITICAL",title:"Device Offline: "+d.device_name,desc:d.device_name+" ("+d.device_ip+") is offline.",device_id:d.device_id,time:d.last_updated,acked:_acked.has("offline_"+d.device_id)});});
    (devices||[]).filter(function(d){return parseFloat(d.health_score)<30;}).forEach(function(d){list.push({id:"health_"+d.device_id,_dbId:null,type:"health",sev:"CRITICAL",title:"Critical Health: "+d.device_name,desc:"Health: "+parseFloat(d.health_score).toFixed(1)+"% — Risk: "+d.risk_level,device_id:d.device_id,time:d.last_updated,acked:_acked.has("health_"+d.device_id)});});
    var aIds=new Set((alerts||[]).map(function(a){return String(a.device_id);}));
    (devices||[]).filter(function(d){return parseInt(d.days_until_failure)<=14&&!aIds.has(String(d.device_id));}).forEach(function(d){list.push({id:"pred_"+d.device_id,_dbId:null,type:"prediction",sev:parseInt(d.days_until_failure)<=7?"CRITICAL":"WARNING",title:"Failure Imminent: "+d.device_name,desc:"Fails in "+d.days_until_failure+" days. Confidence: "+parseFloat(d.confidence_level).toFixed(0)+"%",device_id:d.device_id,time:d.last_updated,acked:_acked.has("pred_"+d.device_id)});});
    (overdue||[]).forEach(function(s){list.push({id:"ov_"+s.id,_dbId:null,type:"overdue",sev:s.priority==="high"?"CRITICAL":"WARNING",title:"Overdue: "+s.device_name,desc:'"'+s.task_name+'" is '+s.days_overdue+" days overdue.",device_id:s.device_id,time:s.scheduled_date,acked:_acked.has("ov_"+s.id)});});
    list.sort(function(a,b){var aA=isAcked(a)?1:0,bA=isAcked(b)?1:0;if(aA!==bA)return aA-bA;return new Date(b.time)-new Date(a.time);});
    return list;
  }
  function iconFor(t,s){if(t==="offline")return["bi-wifi-off","nic"];if(t==="overdue")return["bi-calendar-x-fill","niw"];if(t==="health")return["bi-heart-pulse-fill","nic"];return s==="CRITICAL"?["bi-lightning-charge-fill","nic"]:["bi-lightning-charge-fill","niw"];}
  function notifRender(){
    var list=_tab==="all"?_n:_n.filter(function(n){return n.type===_tab;});
    var el=document.getElementById("notifList");
    if(!list.length){el.innerHTML='<div class="nempty"><i class="bi bi-bell-slash"></i>No notifications</div>';return;}
    el.innerHTML=list.map(function(n){var acked=isAcked(n),ic=iconFor(n.type,n.sev),cls=acked?"":"unread "+(n.sev==="CRITICAL"?"nc":"nw");return'<div class="nitem '+cls+'" onclick="notifAck(\''+n.id+'\',' +(n._dbId||"null")+','+(n.device_id||"null")+')">'+'<div class="nicon '+ic[1]+'"><i class="bi '+ic[0]+'"></i></div><div class="nbody"><div class="ntitle">'+n.title+'</div><div class="ndesc">'+n.desc+'</div><div class="ntime">'+fmtAgo(n.time)+'</div></div></div>';}).join("");
  }
  function updateBadge(){var u=_n.filter(function(n){return!isAcked(n);}).length;var b=document.getElementById("notifBadge");if(u>0){b.style.display="flex";b.textContent=u>99?"99+":u;}else{b.style.display="none";}document.getElementById("nc-all").textContent=_n.length;["offline","health","prediction","overdue"].forEach(function(t){var el=document.getElementById("nc-"+t);if(el)el.textContent=_n.filter(function(n){return n.type===t;}).length;});}
  window.notifAck=function(localId,dbId,deviceId){_acked.add(String(localId));saveAcked();if(deviceId){notifClose();window.location.href="device_detail.php?id="+deviceId;return;}if(dbId){fetch("api_ai_predictions.php?action=acknowledge_alert",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({alert_id:dbId})}).catch(function(){});}notifRender();updateBadge();};
  async function notifLoad(){
    document.getElementById("notifList").innerHTML='<div class="nspinner"></div>';
    try{
      var r=await Promise.all([fetch("api_ai_predictions.php?action=get_alerts"),fetch("api_ai_predictions.php?action=get_health_metrics"),fetch("api_maintenance_schedules.php?action=overdue")]);
      var d=await Promise.all(r.map(function(x){return x.json();}));
      var devices=(d[1].success?d[1].data:[]).map(function(dev){if(dev.factors&&typeof dev.factors==="string"){try{dev.factors=JSON.parse(dev.factors);}catch(e){dev.factors={};}}if(!dev.factors)dev.factors={};return dev;});
      _n=buildNotifs(d[0].success?d[0].data:[],devices,d[2].success?d[2].data:[]);
      _loaded=true;updateBadge();notifRender();
    }catch(e){document.getElementById("notifList").innerHTML='<div class="nempty"><i class="bi bi-exclamation-circle"></i>Failed to load</div>';}
  }
})();
</script>
</body>
</html>