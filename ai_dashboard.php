<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Predictions Dashboard - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>🤖</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
.sidebar-stats{padding:10px 12px;border-top:1px solid var(--border-color);margin-top:auto;}
.sidebar-stats-title{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
.ss-row{display:flex;align-items:center;justify-content:space-between;padding:4px 6px;border-radius:4px;font-size:11px;margin-bottom:2px;}
.ss-row:hover{background:var(--bg-tertiary);}

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
.sc-green::after{background:var(--low);}
.sc-orange::after{background:var(--high);}
.sc-red::after{background:var(--critical);}
.sc-blue::after{background:var(--medium);}
.sc-purple::after{background:var(--accent);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}

/* ── Layout: charts + right panel ── */
.layout{display:grid;grid-template-columns:1fr 280px;gap:18px;margin-bottom:22px;}
@media(max-width:1100px){.layout{grid-template-columns:1fr;}}
.charts-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:800px){.charts-row{grid-template-columns:1fr;}}

/* ── Panel ── */
.panel{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;}
.panel-title{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:6px;}
.chart-box{position:relative;height:200px;}

/* ── Filters bar ── */
.filters{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
.filter-inp{flex:1;min-width:180px;}
.risk-btn{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);padding:6px 12px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;}
.risk-btn:hover,.risk-btn.active{border-color:var(--accent);color:var(--accent);}
.risk-btn.rc-critical.active{border-color:var(--critical);color:var(--critical);}
.risk-btn.rc-high.active{border-color:var(--high);color:var(--high);}
.risk-btn.rc-medium.active{border-color:var(--medium);color:var(--medium);}
.risk-btn.rc-low.active{border-color:var(--low);color:var(--low);}

/* ── Device cards ── */
.devices-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;}
.device-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;padding:0;cursor:pointer;transition:all .2s;overflow:hidden;}
.device-card:hover{border-color:var(--border-hover);transform:translateY(-1px);box-shadow:0 4px 20px rgba(0,0,0,.25);}
.device-card.critical{border-top:3px solid var(--critical);}
.device-card.high{border-top:3px solid var(--high);}
.device-card.medium{border-top:3px solid var(--medium);}
.device-card.low{border-top:3px solid var(--low);}
.dc-head{padding:12px 14px 10px;display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
.dc-name{font-size:13px;font-weight:700;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;}
.dc-ip{font-size:10px;color:var(--text-secondary);}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:600;text-transform:uppercase;}
.badge-critical{background:rgba(255,71,87,.12);color:var(--critical);}
.badge-high{background:rgba(255,165,2,.12);color:var(--high);}
.badge-medium{background:rgba(55,66,250,.12);color:var(--medium);}
.badge-low{background:rgba(46,213,115,.12);color:var(--low);}
.badge-unknown{background:rgba(136,136,136,.1);color:var(--text-secondary);}
.days-pill{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800;}
.dp-urgent{background:rgba(255,71,87,.12);color:var(--critical);}
.dp-warn{background:rgba(255,165,2,.12);color:var(--high);}
.dp-ok{background:rgba(46,213,115,.1);color:var(--low);}
/* Why banner */
.dc-why{margin:0 14px 10px;padding:8px 11px;border-radius:7px;font-size:11px;line-height:1.5;}
.dc-why.why-critical{background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);}
.dc-why.why-high{background:rgba(255,165,2,.08);border:1px solid rgba(255,165,2,.2);}
.dc-why.why-medium{background:rgba(55,66,250,.08);border:1px solid rgba(55,66,250,.2);}
.dc-why.why-low{background:rgba(46,213,115,.08);border:1px solid rgba(46,213,115,.2);}
.dc-why-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.dc-why.why-critical .dc-why-title{color:var(--critical);}
.dc-why.why-high .dc-why-title{color:var(--high);}
.dc-why.why-medium .dc-why-title{color:var(--medium);}
.dc-why.why-low .dc-why-title{color:var(--low);}
.dc-why-row{display:flex;align-items:center;gap:5px;color:var(--text-primary);font-size:11px;}
/* metrics */
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
.dc-meta{font-size:10px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
/* keep hb classes for other uses */
.hb-wrap{margin:10px 0 6px;}
.hb-track{height:4px;background:var(--bg-tertiary);border-radius:2px;overflow:hidden;}
.hb-fill{height:100%;border-radius:2px;}
.hf-red{background:var(--critical);}
.hf-orange{background:var(--high);}
.hf-blue{background:var(--medium);}
.hf-green{background:var(--low);}

/* ── Right panel - alerts ── */
.alert-feed{display:flex;flex-direction:column;gap:5px;max-height:260px;overflow-y:auto;}
.af-item{display:flex;gap:8px;padding:8px 9px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:6px;cursor:pointer;transition:all .15s;}
.af-item:hover{border-color:var(--border-hover);}
.af-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;margin-top:4px;}
.af-dot.critical{background:var(--critical);}
.af-dot.warning,.af-dot.high{background:var(--high);}
.af-dot.medium{background:var(--medium);}
.af-body{flex:1;min-width:0;}
.af-name{font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-primary);}
.af-msg{font-size:10px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}
.af-time{font-size:9px;color:var(--text-muted);white-space:nowrap;flex-shrink:0;}

/* ── Prediction result panel ── */
.pred-result{background:rgba(102,126,234,.05);border:1px solid rgba(102,126,234,.2);border-radius:8px;padding:14px;display:none;}
.pred-result.show{display:block;}
.pred-result-title{font-size:12px;font-weight:700;color:var(--accent);margin-bottom:10px;}
.pred-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid var(--border-color);}
.pred-row:last-child{border-bottom:none;}
.pred-lbl{color:var(--text-secondary);}
.pred-val{font-weight:700;}

/* ── Modal ── */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.modal-content{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);}
.modal-header h2{font-size:15px;font-weight:700;}
.modal-close{background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;}
.modal-close:hover{color:var(--text-primary);}
.modal-body{padding:20px;}
.modal-footer{padding:12px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:8px;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.form-control{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:8px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.form-control:focus{outline:none;border-color:var(--accent);}

/* ── Notif ── */
.nm-overlay{display:none;position:fixed;inset:0;z-index:300;}
.nm-overlay.open{display:block;}
.nm-modal{position:fixed;top:70px;right:16px;width:360px;max-height:540px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;z-index:500;display:none;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.7);color:var(--text-primary);}
.nm-modal.open{display:flex;}
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
.nitem:hover{border-color:var(--border-hover);}
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

/* ── Misc ── */
.empty{text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
.pulse{animation:pulse 2s ease infinite;}
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:9px;font-size:12px;animation:tIn .2s ease;box-shadow:0 4px 20px rgba(0,0,0,.5);}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);}
.toast.err{border-color:var(--critical);}
.toast.info{border-color:var(--accent);}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>

<!-- Notif overlay -->
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
    <a href="ai_dashboard.php"            class="nav-item active"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Fleet Summary</div>
    <div class="ss-row"><span style="color:var(--text-secondary);font-weight:500;font-size:11px;">Total</span><span id="sb-total" style="font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--low);font-weight:500;font-size:11px;">Healthy</span><span id="sb-healthy" style="color:var(--low);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--critical);font-weight:500;font-size:11px;">Critical</span><span id="sb-critical" style="color:var(--critical);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--high);font-weight:500;font-size:11px;">Avg Health</span><span id="sb-avg" style="color:var(--high);font-weight:800;font-size:11px;">—</span></div>
  </div>
</aside>

<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div>
      <div class="page-title">🤖 AI Predictions Dashboard</div>
      <div class="page-subtitle" id="lastUpdated">Loading...</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-success btn-sm" onclick="showReportModal()"><i class="bi bi-file-earmark-text"></i> Generate Report</button>
      <button class="btn btn-accent btn-sm" onclick="showPredictModal()"><i class="bi bi-lightning-fill"></i> Run Prediction</button>
      <button class="btn btn-ghost btn-sm" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
      <div style="position:relative;">
        <button class="icon-btn" onclick="notifOpen()" title="Notifications"><i class="bi bi-bell-fill"></i></button>
        <span id="notifBadge" style="display:none;position:absolute;top:-5px;right:-5px;background:var(--critical);color:#fff;font-size:9px;font-weight:800;border-radius:8px;padding:1px 5px;min-width:18px;text-align:center;pointer-events:none;"></span>
      </div>
    </div>

    <!-- Notif dropdown -->
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
    <div class="stat-card sc-green">
      <div class="stat-num" id="st-healthy" style="color:var(--low)">—</div>
      <div class="stat-label">Healthy Devices</div>
    </div>
    <div class="stat-card sc-orange">
      <div class="stat-num" id="st-maintenance" style="color:var(--high)">—</div>
      <div class="stat-label">Needs Maintenance</div>
    </div>
    <div class="stat-card sc-red">
      <div class="stat-num" id="st-critical" style="color:var(--critical)">—</div>
      <div class="stat-label">Critical Devices</div>
    </div>
    <div class="stat-card sc-blue">
      <div class="stat-num" id="st-avg" style="color:var(--medium)">—</div>
      <div class="stat-label">Avg Health Score</div>
    </div>
    <div class="stat-card sc-purple">
      <div class="stat-num" id="st-total" style="color:var(--accent)">—</div>
      <div class="stat-label">Total Monitored</div>
    </div>
  </div>

  <!-- Charts + alerts layout -->
  <div class="layout">
    <div class="charts-row">
      <div class="panel">
        <div class="panel-title"><i class="bi bi-pie-chart-fill"></i> Risk Distribution</div>
        <div class="chart-box"><canvas id="riskChart"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-title"><i class="bi bi-bar-chart-fill"></i> Health Overview</div>
        <div class="chart-box"><canvas id="healthChart"></canvas></div>
      </div>
    </div>
    <!-- Right: alerts + prediction result -->
    <div style="display:flex;flex-direction:column;gap:14px;">
      <div class="panel">
        <div class="panel-title"><i class="bi bi-bell-fill"></i> Recent Alerts</div>
        <div class="alert-feed" id="alertFeed"><div style="color:var(--text-secondary);font-size:12px;">Loading...</div></div>
      </div>
      <div class="pred-result" id="predResult">
        <div class="pred-result-title">⚡ Last Prediction Result</div>
        <div id="predResultBody"></div>
      </div>
    </div>
  </div>

  <!-- Devices section -->
  <div class="filters">
    <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search device..." oninput="applyFilters()">
    <select class="filter-sel" id="networkFilter" onchange="loadData()"><option value="">All Networks</option></select>
    <select class="filter-sel" id="sortSelect" onchange="applyFilters()">
      <option value="risk">Sort: Risk Level</option>
      <option value="health">Sort: Health Score</option>
      <option value="days">Sort: Days to Failure</option>
      <option value="name">Sort: Name A–Z</option>
    </select>
    <button class="risk-btn active" data-risk="ALL"      onclick="setRisk('ALL',this)">All</button>
    <button class="risk-btn rc-critical" data-risk="CRITICAL" onclick="setRisk('CRITICAL',this)">Critical</button>
    <button class="risk-btn rc-high"     data-risk="HIGH"     onclick="setRisk('HIGH',this)">High</button>
    <button class="risk-btn rc-medium"   data-risk="MEDIUM"   onclick="setRisk('MEDIUM',this)">Medium</button>
    <button class="risk-btn rc-low"      data-risk="LOW"      onclick="setRisk('LOW',this)">Healthy</button>
  </div>

  <div id="loadingState"><div class="loader"></div></div>
  <div id="devicesGrid" class="devices-grid" style="display:none;"></div>
</div>

<!-- Run Prediction modal -->
<div id="predictModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="bi bi-lightning-fill"></i> Run AI Prediction</h2>
      <button class="modal-close" onclick="closeModal('predictModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Device *</label>
        <select id="predDevice" class="form-control"><option value="">Select device...</option></select>
      </div>
      <div class="form-group">
        <label>Analysis Type</label>
        <select id="predType" class="form-control">
          <option value="standard">Standard Analysis</option>
          <option value="deep">Deep Analysis</option>
          <option value="quick">Quick Check</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('predictModal')">Cancel</button>
      <button class="btn btn-accent" onclick="runPrediction()"><i class="bi bi-lightning-fill"></i> Run</button>
    </div>
  </div>
</div>

<!-- Generate Report modal -->
<div id="reportModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="bi bi-file-earmark-text"></i> Generate Report</h2>
      <button class="modal-close" onclick="closeModal('reportModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Report Title *</label>
        <input type="text" id="reportTitle" class="form-control" placeholder="AI Prediction Report">
      </div>
      <div class="form-group">
        <label>Report Type</label>
        <select id="reportType" class="form-control" onchange="updateReportTitle()">
          <option value="ai_prediction">AI Prediction Report</option>
          <option value="health_summary">Health Summary Report</option>
          <option value="risk_assessment">Risk Assessment Report</option>
        </select>
      </div>
      <div class="form-group">
        <label>Device (optional)</label>
        <select id="reportDevice" class="form-control"><option value="">All Critical/High Risk Devices</option></select>
      </div>
      <div class="form-group">
        <label>Time Period</label>
        <select id="reportPeriod" class="form-control">
          <option value="30">30 Days</option>
          <option value="60">60 Days</option>
          <option value="90">90 Days</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('reportModal')">Cancel</button>
      <button class="btn btn-success" onclick="generateReport()"><i class="bi bi-file-earmark-check"></i> Generate</button>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allDevices = [], filteredDevices = [], allData = null;
let riskFilter = 'ALL';
let riskChart = null, healthChart = null;

// ── Theme ──
function toggleTheme() {
  document.body.classList.toggle('light-mode');
  const l = document.body.classList.contains('light-mode');
  document.getElementById('themeIcon').className = l ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('ai_theme', l ? 'light' : 'dark');
  if (allData) updateCharts(allData.overall);
}
if (localStorage.getItem('ai_theme') === 'light') {
  document.body.classList.add('light-mode');
  document.getElementById('themeIcon').className = 'bi bi-sun-fill';
}

// ── Helpers ──
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s) { if (!s) return '—'; try { return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); } catch(e){ return s; } }
function ago(s) { if (!s) return '—'; const m=Math.round((Date.now()-new Date(s))/60000); if(m<1)return 'just now'; if(m<60)return m+'m ago'; if(m<1440)return Math.round(m/60)+'h ago'; return Math.round(m/1440)+'d ago'; }
function riskCls(r) { return ({CRITICAL:'critical',HIGH:'high',MEDIUM:'medium',LOW:'low'})[String(r||'').toUpperCase()]||'unknown'; }
function hfCls(s) { s=parseFloat(s); return s<40?'hf-red':s<60?'hf-orange':s<80?'hf-blue':'hf-green'; }
function hColor(s) { s=parseFloat(s); return s<40?'var(--critical)':s<60?'var(--high)':s<80?'var(--medium)':'var(--low)'; }
function daysCls(d) { d=parseInt(d); return d<=14?'dp-urgent':d<=45?'dp-warn':'dp-ok'; }
function isOnline(d) { if (d.status) return d.status==='online'; const f=d.factors||{}; return f.is_online===true||f.is_online===1; }

function whySection(d) {
  const rc     = (d.risk_level||'').toUpperCase();
  const score  = parseFloat(d.health_score||0);
  const f      = d.factors||{};
  const online = isOnline(d);
  const days   = parseInt(d.days_until_failure)||365;
  const critE  = parseInt(f.critical_errors)||0;
  const offline= parseInt(f.offline_incidents)||0;
  const errR   = parseFloat(f.daily_error_rate)||0;
  const lastM  = f.days_since_maintenance != null ? parseInt(f.days_since_maintenance) : 999;

  const reasons = [];
  if (!online)          reasons.push('Device is currently offline');
  if (days <= 14)       reasons.push(`Predicted failure in ${days} days`);
  if (critE > 0)        reasons.push(`${critE} critical error${critE>1?'s':''} in last 60 days`);
  if (offline >= 3)     reasons.push(`${offline} offline incidents in 90 days`);
  if (errR >= 0.5)      reasons.push(`High error rate: ${errR.toFixed(2)}/day`);
  if (lastM >= 999)     reasons.push('No maintenance record');
  else if (lastM > 180) reasons.push(`No maintenance for ${lastM} days`);
  if (score < 40)       reasons.push(`Health score critically low: ${score.toFixed(0)}%`);
  if (reasons.length === 0)
    reasons.push(score >= 80 ? `Health at ${score.toFixed(0)}% — looking good` : `Health at ${score.toFixed(0)}% — monitoring closely`);

  const cls   = rc==='CRITICAL'?'why-critical':rc==='HIGH'?'why-high':rc==='MEDIUM'?'why-medium':'why-low';
  const title = rc==='CRITICAL'?'⚠️ Why Critical':rc==='HIGH'?'⚠️ Why High Risk':rc==='MEDIUM'?'📋 Risk Factors':'✅ Status';
  return `<div class="dc-why ${cls}">
    <div class="dc-why-title">${title}</div>
    ${reasons.slice(0,2).map(r=>`<div class="dc-why-row"><span style="opacity:.5;margin-right:4px;">›</span>${esc(r)}</div>`).join('')}
  </div>`;
}

function scoreRingCls(s) {
  return s<30?'sr-red':s<50?'sr-orange':s<70?'sr-blue':'sr-green';
}

function renderCard(d) {
  const f       = d.factors||{};
  const rc      = riskCls(d.risk_level);
  const score   = parseFloat(d.health_score||0);
  const days    = parseInt(d.days_until_failure)||365;
  const conf    = parseFloat(d.confidence_level||0);
  const online  = isOnline(d);
  const errR    = parseFloat(f.daily_error_rate)||0;
  const incidents = parseInt(f.offline_incidents)||0;
  const devIcons  = {computer:'🖥️',server:'🗄️',laptop:'💻',router:'📡',switch:'🔌',printer:'🖨️',phone:'📱',tablet:'📱',camera:'📷',other:'📟'};
  const devIcon   = devIcons[d.device_type]||'📟';

  return `<div class="device-card ${rc}" onclick="location.href='device_detail.php?id=${d.device_id}'">

    <!-- Header -->
    <div class="dc-head">
      <div style="flex:1;min-width:0;">
        <div class="dc-name">${rc==='critical'?'<span class="pulse">🔴</span> ':''}${esc(d.device_name)}</div>
        <div class="dc-ip">${esc(d.device_ip||'—')} · ${esc(d.network_range||'—')}</div>
        <div style="display:flex;gap:4px;margin-top:5px;flex-wrap:wrap;">
          <span class="badge badge-${rc}">${d.risk_level||'UNKNOWN'}</span>
          <span class="badge badge-${online?'low':'critical'}">${online?'🟢 Online':'🔴 Offline'}</span>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;margin-left:8px;">
        <span class="days-pill ${daysCls(days)}">${days}d</span>
        <div style="font-size:10px;color:var(--text-secondary);margin-top:3px;">${fmt(d.predicted_failure_date)}</div>
      </div>
    </div>

    <!-- Why banner -->
    ${whySection(d)}

    <!-- Score ring + stats -->
    <div class="dc-metrics">
      <div class="dc-score-ring ${scoreRingCls(score)}">${score.toFixed(0)}%</div>
      <div class="dc-stats-grid">
        <div class="dc-stat">
          <span class="dc-stat-val" style="color:${errR===0?'var(--low)':errR<0.5?'var(--high)':'var(--critical)'}">${errR.toFixed(2)}/d</span>
          <span class="dc-stat-lbl">Error rate</span>
        </div>
        <div class="dc-stat">
          <span class="dc-stat-val" style="color:${incidents===0?'var(--low)':incidents<3?'var(--high)':'var(--critical)'}">${incidents}</span>
          <span class="dc-stat-lbl">Incidents</span>
        </div>
        <div class="dc-stat">
          <span class="dc-stat-val" style="color:var(--text-secondary)">${conf.toFixed(0)}%</span>
          <span class="dc-stat-lbl">Confidence</span>
        </div>
        <div class="dc-stat">
          <span class="dc-stat-val">${devIcon} ${esc(d.device_type||'device')}</span>
          <span class="dc-stat-lbl">Type</span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="dc-footer">
      <div class="dc-meta">${esc(d.device_name)}</div>
      <div style="display:flex;gap:5px;">
        <button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();quickPredict(${d.device_id})"><i class="bi bi-lightning-fill"></i></button>
        <a href="device_detail.php?id=${d.device_id}" class="btn btn-accent btn-sm" onclick="event.stopPropagation()"><i class="bi bi-box-arrow-up-right"></i> Details</a>
      </div>
    </div>
  </div>`;
}
function applyFilters() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const sort = document.getElementById('sortSelect').value;
  const riskOrder = {CRITICAL:0,HIGH:1,MEDIUM:2,LOW:3,UNKNOWN:4};

  filteredDevices = allDevices.filter(d => {
    const nm=(d.device_name||'').toLowerCase(), ip=(d.device_ip||'').toLowerCase();
    const matchQ = !q || nm.includes(q) || ip.includes(q);
    const matchR = riskFilter==='ALL' || d.risk_level===riskFilter;
    return matchQ && matchR;
  });

  filteredDevices.sort((a,b) => {
    switch(sort) {
      case 'health': return parseFloat(a.health_score)-parseFloat(b.health_score);
      case 'days':   return (parseInt(a.days_until_failure)||365)-(parseInt(b.days_until_failure)||365);
      case 'name':   return (a.device_name||'').localeCompare(b.device_name||'');
      default:       return (riskOrder[a.risk_level]||4)-(riskOrder[b.risk_level]||4);
    }
  });

  const grid = document.getElementById('devicesGrid');
  if (!filteredDevices.length) {
    grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><i class="bi bi-search"></i><p>No devices match</p></div>';
    return;
  }
  grid.innerHTML = filteredDevices.map(renderCard).join('');
}

function setRisk(r, el) {
  riskFilter = r;
  document.querySelectorAll('.risk-btn').forEach(b => b.classList.toggle('active', b.dataset.risk===r));
  applyFilters();
}

function updateStats(s) {
  document.getElementById('st-healthy').textContent    = s.healthy_devices||0;
  document.getElementById('st-maintenance').textContent= s.needs_maintenance||0;
  document.getElementById('st-critical').textContent   = s.failing_devices||0;
  document.getElementById('st-avg').textContent        = (s.avg_health_score||0).toFixed(1)+'%';
  document.getElementById('st-total').textContent      = s.total_devices||0;
  document.getElementById('sb-total').textContent      = s.total_devices||0;
  document.getElementById('sb-healthy').textContent    = s.healthy_devices||0;
  document.getElementById('sb-critical').textContent   = s.failing_devices||0;
  document.getElementById('sb-avg').textContent        = (s.avg_health_score||0).toFixed(1)+'%';
}

function updateCharts(s) {
  const isDark = !document.body.classList.contains('light-mode');
  const tc = isDark?'#888':'#666';
  const gc = isDark?'rgba(255,255,255,.05)':'rgba(0,0,0,.05)';

  // Risk doughnut
  if (riskChart) riskChart.destroy();
  riskChart = new Chart(document.getElementById('riskChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['Critical','High','Medium','Low'],
      datasets: [{
        data: [s.critical_devices||0, s.high_risk_devices||0, s.medium_risk_devices||0, s.low_risk_devices||0],
        backgroundColor: ['rgba(255,71,87,.7)','rgba(255,165,2,.7)','rgba(55,66,250,.7)','rgba(46,213,115,.7)'],
        borderColor: ['#ff4757','#ffa502','#3742fa','#2ed573'],
        borderWidth:1,
      }]
    },
    options: {
      responsive:true, maintainAspectRatio:false, cutout:'60%',
      plugins:{ legend:{ position:'bottom', labels:{ color:tc, font:{family:'Montserrat',size:10}, padding:8 } } }
    }
  });

  // Health bar chart
  const buckets = {'< 30':0,'30–50':0,'50–70':0,'70–90':0,'90–100':0};
  allDevices.forEach(d => {
    const s = parseFloat(d.health_score||0);
    if(s<30) buckets['< 30']++;
    else if(s<50) buckets['30–50']++;
    else if(s<70) buckets['50–70']++;
    else if(s<90) buckets['70–90']++;
    else buckets['90–100']++;
  });
  if (healthChart) healthChart.destroy();
  healthChart = new Chart(document.getElementById('healthChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: Object.keys(buckets),
      datasets: [{
        label: 'Devices',
        data: Object.values(buckets),
        backgroundColor: ['rgba(255,71,87,.65)','rgba(255,165,2,.65)','rgba(255,211,42,.65)','rgba(55,66,250,.65)','rgba(46,213,115,.65)'],
        borderColor: ['#ff4757','#ffa502','#ffd32a','#3742fa','#2ed573'],
        borderWidth:1, borderRadius:4,
      }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false} },
      scales:{
        x:{ ticks:{color:tc,font:{family:'Montserrat',size:10}}, grid:{color:gc} },
        y:{ ticks:{color:tc,font:{family:'Montserrat',size:10},stepSize:1}, grid:{color:gc} }
      }
    }
  });
}

function updateAlerts(alerts) {
  const feed = document.getElementById('alertFeed');
  if (!alerts||!alerts.length) {
    feed.innerHTML = '<div style="color:var(--text-secondary);font-size:12px;text-align:center;padding:14px;">No recent alerts</div>';
    return;
  }
  feed.innerHTML = alerts.slice(0,15).map(a => {
    const sev = (a.severity||'WARNING').toLowerCase();
    return `<div class="af-item" onclick="location.href='device_detail.php?id=${a.device_id}'">
      <div class="af-dot ${sev}"></div>
      <div class="af-body">
        <div class="af-name">${esc(a.device_name)}</div>
        <div class="af-msg">${esc(a.message||a.alert_type||'Alert')}</div>
      </div>
      <div class="af-time">${ago(a.created_at)}</div>
    </div>`;
  }).join('');
}

function populateNetworkFilter(networks) {
  const sel = document.getElementById('networkFilter');
  const cur = sel.value;
  sel.innerHTML = '<option value="">All Networks</option>' +
    (networks||[]).map(n => `<option value="${esc(n.network_range)}">${esc(n.network_range)} (${n.total_devices})</option>`).join('');
  sel.value = cur;
}

async function loadData() {
  document.getElementById('loadingState').style.display = 'block';
  document.getElementById('devicesGrid').style.display  = 'none';
  document.getElementById('lastUpdated').textContent = 'Loading...';

  try {
    const net = document.getElementById('networkFilter').value;
    let statsUrl = 'api_dashboard_stats.php', devUrl = 'api_ai_predictions.php?action=get_health_metrics';
    if (net) { statsUrl += '?network_range='+encodeURIComponent(net); devUrl += '&network_range='+encodeURIComponent(net); }

    const [sr, dr] = await Promise.all([fetch(statsUrl), fetch(devUrl)]);
    const [sd, dd] = await Promise.all([sr.json(), dr.json()]);

    if (!sd.success) throw new Error(sd.error||'Stats API failed');

    allData = sd;
    allDevices = (dd.success ? dd.data||[] : []).map(d => {
      if (d.factors && typeof d.factors==='string') { try{ d.factors=JSON.parse(d.factors); }catch(e){ d.factors={}; } }
      if (!d.factors) d.factors = {};
      return d;
    });

    updateStats(sd.overall);
    updateCharts(sd.overall);
    updateAlerts(sd.recent_alerts||[]);
    populateNetworkFilter(sd.by_network||[]);
    applyFilters();

    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('devicesGrid').style.display  = 'grid';
    document.getElementById('lastUpdated').textContent = 'Updated: '+new Date().toLocaleTimeString('en-MY');
  } catch(err) {
    document.getElementById('loadingState').innerHTML =
      `<div class="empty"><i class="bi bi-exclamation-circle"></i><p>Error: ${esc(err.message)}</p>
       <button class="btn btn-ghost" onclick="loadData()" style="margin-top:12px;"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>`;
  }
}

// ── Modals ──
function showPredictModal() {
  document.getElementById('predDevice').innerHTML = '<option value="">Select device...</option>' +
    allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} — ${esc(d.device_ip)}</option>`).join('');
  document.getElementById('predictModal').classList.add('show');
}
function updateReportTitle() {
  const labels = {
    'ai_prediction':   'AI Prediction Report',
    'health_summary':  'Health Summary Report',
    'risk_assessment': 'Risk Assessment Report'
  };
  const type = document.getElementById('reportType').value;
  const date = new Date().toLocaleDateString('en-MY', {day:'2-digit',month:'short',year:'numeric'});
  document.getElementById('reportTitle').value = (labels[type]||'Report') + ' — ' + date;
}

function showReportModal() {
  document.getElementById('reportDevice').innerHTML =
    '<option value="">All Critical/High Risk Devices</option>' +
    allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} (${esc(d.device_ip||'')})</option>`).join('');
  updateReportTitle();
  document.getElementById('reportModal').classList.add('show');
}
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

async function runPrediction() {
  const deviceId = document.getElementById('predDevice').value;
  const type     = document.getElementById('predType').value;
  if (!deviceId) { toast('Please select a device', 'err'); return; }
  closeModal('predictModal');
  toast('Running AI prediction...', 'info');
  try {
    const r = await fetch('api_ai_predictions.php?action=run_prediction', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({device_id: deviceId, analysis_type: type})
    });
    const d = JSON.parse(await r.text());
    if (d.success) {
      toast('✅ Prediction complete', 'ok');
      const p = d.data||{};
      const box = document.getElementById('predResult');
      document.getElementById('predResultBody').innerHTML = [
        {l:'Device',       v: allDevices.find(x=>x.device_id==deviceId)?.device_name||deviceId},
        {l:'Health Score', v: p.health_score!==undefined ? parseFloat(p.health_score).toFixed(1)+'%' : '—'},
        {l:'Risk Level',   v: p.risk_level||'—'},
        {l:'Days to Fail', v: p.days_until_failure!==undefined ? p.days_until_failure+' days' : '—'},
        {l:'Confidence',   v: p.confidence_level!==undefined ? parseFloat(p.confidence_level).toFixed(1)+'%' : '—'},
        {l:'Failure Date', v: fmt(p.predicted_failure_date)},
      ].map(r=>`<div class="pred-row"><span class="pred-lbl">${r.l}</span><span class="pred-val">${esc(String(r.v))}</span></div>`).join('');
      box.classList.add('show');
      setTimeout(loadData, 500);
    } else {
      toast(d.error||'Prediction failed', 'err');
    }
  } catch(e) { toast('Error: '+e.message, 'err'); }
}

async function quickPredict(deviceId) {
  toast('Running prediction...', 'info');
  try {
    const r = await fetch('api_ai_predictions.php?action=run_prediction', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({device_id: deviceId})
    });
    const d = JSON.parse(await r.text());
    if (d.success) {
      const p = d.data||{};
      toast(`✅ ${allDevices.find(x=>x.device_id==deviceId)?.device_name||'Device'}: ${parseFloat(p.health_score||0).toFixed(0)}% health · ${p.risk_level}`, 'ok');
      setTimeout(loadData, 500);
    } else {
      toast(d.error||'Failed', 'err');
    }
  } catch(e) { toast('Error: '+e.message, 'err'); }
}

async function generateReport() {
  const title     = document.getElementById('reportTitle').value.trim();
  const repType   = document.getElementById('reportType').value;
  const deviceId  = document.getElementById('reportDevice').value;
  const days      = document.getElementById('reportPeriod').value;

  if (!title) { toast('Please enter a report title', 'err'); return; }

  // If "All Devices" selected, we generate one report per critical device
  const targets = deviceId
    ? [deviceId]
    : allDevices.filter(d => (d.risk_level==='CRITICAL'||d.risk_level==='HIGH')).slice(0,5).map(d=>d.device_id);

  if (!targets.length && !deviceId) {
    toast('No critical/high risk devices to report on', 'err');
    return;
  }

  closeModal('reportModal');
  toast('Generating report…', 'info');

  try {
    let successCount = 0, errorMsgs = [];

    for (const did of (deviceId ? [deviceId] : targets)) {
      const text = await fetch('api_generate_report.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          report_type:       'ai_prediction',  // normalized — API accepts this
          device_id:         did,
          equipment_type_id: 1,
          predicted_issue:   title,
          confidence_score:  0.95,
          days:              days,
          title:             title,
          force:             false
        })
      }).then(r=>r.text());

      let d;
      try { d = JSON.parse(text); } catch(e) { errorMsgs.push('Server error: '+text.substring(0,80)); continue; }

      if (d.success) {
        successCount++;
      } else {
        errorMsgs.push(d.error||'Failed');
      }
    }

    if (successCount > 0) {
      toast(`✅ ${successCount} report${successCount>1?'s':''} generated!`, 'ok');
      setTimeout(()=>{ window.location.href='maintenance_reports.php'; }, 1400);
    } else {
      toast('Failed: '+(errorMsgs[0]||'Unknown error'), 'err');
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

loadData();
setInterval(loadData, 60000);
</script>

<!-- Notification system -->
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
  function isDevOnline(d){if(d.status)return d.status==="online";var f=d.factors||{};return f.is_online===true||f.is_online===1;}
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