<?php
require_once 'config.php';
$deviceId = (int)($_GET['id'] ?? 0);
if (!$deviceId) { header('Location: device_health_dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Device Detail — Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>🖥️</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --bg-primary:#000;--bg-secondary:#0f0f0f;--bg-tertiary:#0a0a0a;
    --border-color:#1a1a1a;--border-hover:#333;
    --text-primary:#fff;--text-secondary:#666;--text-muted:#444;
    --accent:#667eea;--accent2:#764ba2;
    --green:#10b981;--red:#ef4444;--yellow:#f59e0b;--orange:#f97316;--blue:#3b82f6;
}
body.light-mode{
    --bg-primary:#f5f5f5;--bg-secondary:#fff;--bg-tertiary:#f0f0f0;
    --border-color:#e0e0e0;--border-hover:#ccc;
    --text-primary:#111;--text-secondary:#666;--text-muted:#aaa;
}
body{font-family:'Montserrat',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;}
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg-primary);border-right:1px solid var(--border-color);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-header{padding:20px 16px 14px;border-bottom:1px solid var(--border-color);}
.sidebar-header h1{font-size:15px;font-weight:800;}
.sidebar-header p{font-size:10px;color:var(--text-secondary);margin-top:2px;}
.sidebar-nav{padding:10px 8px;flex:1;}
.nav-section{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;padding:8px 10px 4px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:7px;text-decoration:none;color:var(--text-secondary);font-size:12px;font-weight:500;margin-bottom:2px;border:1px solid transparent;transition:all .2s;}
.nav-link:hover{background:var(--bg-secondary);color:var(--text-primary);border-color:var(--border-color);}
.nav-link.active{background:var(--bg-secondary);color:var(--text-primary);border-color:var(--accent);}
.nav-link i{font-size:14px;color:var(--accent);}
.main-content{margin-left:220px;padding:24px;min-height:100vh;}
.topbar{display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;}
.back-btn{display:flex;align-items:center;gap:8px;padding:8px 14px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;color:var(--text-secondary);text-decoration:none;font-size:12px;font-weight:600;transition:all .2s;}
.back-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.top-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-outline{background:transparent;border:1px solid var(--border-color);color:var(--text-primary);}
.btn-outline:hover{border-color:var(--border-hover);}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{opacity:.85;}
.btn-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:var(--red);}
.btn-warning{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.25);color:var(--yellow);}
.btn-sm{padding:6px 12px;font-size:11px;}
.theme-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:8px 12px;cursor:pointer;color:var(--text-primary);font-size:15px;transition:all .2s;}
.hero-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:14px;padding:24px 28px;margin-bottom:20px;display:flex;align-items:center;gap:24px;position:relative;overflow:hidden;}
.hero-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#667eea,#764ba2);}
.hero-card.risk-CRITICAL::before{background:linear-gradient(90deg,#ef4444,#ff6b6b);}
.hero-card.risk-HIGH::before{background:linear-gradient(90deg,#f59e0b,#fbbf24);}
.hero-card.risk-MEDIUM::before{background:linear-gradient(90deg,#3b82f6,#60a5fa);}
.hero-card.risk-LOW::before{background:linear-gradient(90deg,#10b981,#34d399);}
.hero-card.status-offline{border-color:rgba(239,68,68,.3);}
.device-icon-wrap{width:68px;height:68px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;background:rgba(102,126,234,.1);border:1px solid rgba(102,126,234,.2);}
.device-icon-wrap.offline{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.2);}
.hero-info{flex:1;min-width:0;}
.hero-name{font-size:22px;font-weight:800;margin-bottom:3px;}
.hero-meta{font-size:12px;color:var(--text-secondary);margin-bottom:10px;}
.hero-badges{display:flex;gap:7px;flex-wrap:wrap;}
.hero-score{text-align:center;flex-shrink:0;}
.score-ring{width:88px;height:88px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;border:4px solid var(--border-color);}
.score-num{font-size:22px;font-weight:800;line-height:1;}
.score-lbl{font-size:9px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;margin-top:2px;}
.offline-alert{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px;}
.offline-alert-icon{font-size:22px;flex-shrink:0;margin-top:1px;}
.offline-alert-body{flex:1;}
.offline-alert-title{font-size:13px;font-weight:700;color:var(--red);margin-bottom:4px;}
.offline-alert-reason{font-size:12px;color:var(--text-secondary);line-height:1.5;}
.offline-alert-time{font-size:11px;color:var(--text-muted);margin-top:4px;}
.flags-bar{margin-bottom:18px;}
.flag-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-radius:10px;margin-bottom:8px;border:1px solid;}
.flag-item.critical{background:rgba(239,68,68,.06);border-color:rgba(239,68,68,.2);}
.flag-item.high{background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.2);}
.flag-item.medium{background:rgba(59,130,246,.06);border-color:rgba(59,130,246,.2);}
.flag-item.low{background:rgba(16,185,129,.06);border-color:rgba(16,185,129,.2);}
.flag-icon{font-size:18px;flex-shrink:0;}
.flag-body{flex:1;}
.flag-title{font-size:13px;font-weight:700;margin-bottom:2px;}
.flag-desc{font-size:11px;color:var(--text-secondary);}
.flag-meta{font-size:10px;color:var(--text-muted);margin-top:3px;}
.flag-actions{display:flex;gap:6px;flex-shrink:0;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:20px;}
.stat-box{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;padding:16px;text-align:center;}
.stat-box-val{font-size:26px;font-weight:800;line-height:1;margin-bottom:5px;}
.stat-box-lbl{font-size:10px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.stat-box-sub{font-size:10px;color:var(--text-muted);margin-top:3px;}
.tabs-bar{display:flex;gap:2px;border-bottom:1px solid var(--border-color);margin-bottom:20px;overflow-x:auto;}
.tab-btn{display:flex;align-items:center;gap:7px;padding:10px 18px;border:none;background:transparent;color:var(--text-secondary);cursor:pointer;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;border-bottom:2px solid transparent;transition:all .2s;white-space:nowrap;}
.tab-btn:hover{color:var(--text-primary);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;font-size:10px;font-weight:700;}
.tab-badge.red{background:rgba(239,68,68,.15);color:var(--red);}
.tab-badge.orange{background:rgba(245,158,11,.15);color:var(--yellow);}
.tab-panel{display:none;}
.tab-panel.active{display:block;}
.card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:20px;margin-bottom:18px;}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.card-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-secondary);display:flex;align-items:center;gap:7px;}
.card-title i{color:var(--accent);font-size:14px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border-color);font-size:12px;}
.info-row:last-child{border-bottom:none;}
.info-lbl{color:var(--text-secondary);font-weight:500;}
.info-val{font-weight:600;text-align:right;}
.hbar-wrap{margin-bottom:12px;}
.hbar-hdr{display:flex;justify-content:space-between;font-size:11px;margin-bottom:5px;}
.hbar-lbl{color:var(--text-secondary);font-weight:600;}
.hbar-val{font-weight:700;}
.hbar{height:7px;background:var(--border-color);border-radius:4px;overflow:hidden;}
.hbar-fill{height:100%;border-radius:4px;transition:width 1s ease;}
.hbar-fill.green{background:linear-gradient(90deg,#10b981,#34d399);}
.hbar-fill.yellow{background:linear-gradient(90deg,#f59e0b,#fbbf24);}
.hbar-fill.red{background:linear-gradient(90deg,#ef4444,#f87171);}
.hbar-fill.accent{background:linear-gradient(90deg,#667eea,#764ba2);}
.factor-row{display:flex;align-items:center;padding:11px 0;border-bottom:1px solid var(--border-color);gap:12px;}
.factor-row:last-child{border-bottom:none;}
.factor-icon-wrap{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;background:var(--bg-tertiary);border:1px solid var(--border-color);font-size:14px;flex-shrink:0;}
.factor-info{flex:1;}
.factor-name{font-size:12px;font-weight:600;margin-bottom:1px;}
.factor-desc{font-size:10px;color:var(--text-secondary);}
.factor-val{font-size:13px;font-weight:700;}
.fv-good{color:var(--green);}
.fv-warn{color:var(--yellow);}
.fv-bad{color:var(--red);}
.fv-neutral{color:var(--text-secondary);}
.log-filters{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;}
.log-filter-btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border-color);background:transparent;color:var(--text-secondary);font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;}
.log-filter-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.log-filter-btn.active{border-color:var(--accent);color:var(--accent);background:rgba(102,126,234,.08);}
.log-search{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 12px;border-radius:7px;font-family:'Montserrat',sans-serif;font-size:12px;flex:1;min-width:160px;}
.log-search:focus{outline:none;border-color:var(--accent);}
.log-list{max-height:500px;overflow-y:auto;}
.log-row{display:flex;align-items:flex-start;gap:12px;padding:9px 0;border-bottom:1px solid var(--border-color);font-size:12px;}
.log-row:last-child{border-bottom:none;}
.log-level-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px;}
.log-level-dot.CRITICAL,.log-level-dot.ERROR{background:var(--red);}
.log-level-dot.WARNING{background:var(--yellow);}
.log-level-dot.INFO{background:var(--green);}
.log-level-dot.DEBUG{background:var(--text-muted);}
.log-badge{padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;flex-shrink:0;}
.log-badge.CRITICAL,.log-badge.ERROR{background:rgba(239,68,68,.12);color:var(--red);}
.log-badge.WARNING{background:rgba(245,158,11,.12);color:var(--yellow);}
.log-badge.INFO{background:rgba(16,185,129,.12);color:var(--green);}
.log-badge.DEBUG{background:rgba(100,100,100,.12);color:var(--text-secondary);}
.log-msg{flex:1;line-height:1.4;}
.log-time{font-size:10px;color:var(--text-muted);flex-shrink:0;white-space:nowrap;}
.log-category{font-size:10px;color:var(--text-muted);margin-top:1px;}
.data-tbl{width:100%;border-collapse:collapse;font-size:12px;}
.data-tbl th{text-align:left;padding:8px 12px;color:var(--text-secondary);font-size:10px;text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid var(--border-color);font-weight:700;}
.data-tbl td{padding:10px 12px;border-bottom:1px solid var(--border-color);}
.data-tbl tr:last-child td{border-bottom:none;}
.data-tbl tr:hover td{background:var(--bg-tertiary);}
.badge{padding:3px 10px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
.badge-online{background:rgba(16,185,129,.12);color:var(--green);border:1px solid rgba(16,185,129,.25);}
.badge-offline{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.25);}
.badge-critical{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.25);}
.badge-high{background:rgba(245,158,11,.12);color:var(--yellow);border:1px solid rgba(245,158,11,.25);}
.badge-medium{background:rgba(59,130,246,.12);color:var(--blue);border:1px solid rgba(59,130,246,.25);}
.badge-low{background:rgba(16,185,129,.12);color:var(--green);border:1px solid rgba(16,185,129,.25);}
.badge-info{background:rgba(102,126,234,.12);color:var(--accent);border:1px solid rgba(102,126,234,.25);}
.timeline{position:relative;padding-left:22px;}
.timeline::before{content:'';position:absolute;left:7px;top:0;bottom:0;width:2px;background:var(--border-color);}
.tl-item{position:relative;margin-bottom:18px;}
.tl-dot{position:absolute;left:-18px;top:4px;width:12px;height:12px;border-radius:50%;border:2px solid var(--bg-secondary);}
.tl-dot.green{background:var(--green);box-shadow:0 0 0 3px rgba(16,185,129,.15);}
.tl-dot.yellow{background:var(--yellow);box-shadow:0 0 0 3px rgba(245,158,11,.15);}
.tl-dot.red{background:var(--red);box-shadow:0 0 0 3px rgba(239,68,68,.15);}
.tl-dot.muted{background:var(--border-hover);}
.tl-date{font-size:10px;color:var(--text-secondary);font-weight:600;margin-bottom:2px;}
.tl-title{font-size:12px;font-weight:700;}
.tl-desc{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.rec-item{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:8px;margin-bottom:8px;}
.rec-icon{font-size:15px;flex-shrink:0;margin-top:1px;}
.rec-text{font-size:12px;font-weight:500;line-height:1.4;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:500;display:none;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;width:100%;max-width:500px;animation:mIn .2s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:15px;font-weight:700;}
.modal-close{background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:20px;}
.modal-body{padding:22px;}
.modal-footer{padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:8px;}
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.form-control{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:9px 12px;border-radius:7px;font-family:'Montserrat',sans-serif;font-size:12px;}
.form-control:focus{outline:none;border-color:var(--accent);}
.form-control option{background:var(--bg-secondary);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.ping-result{padding:12px 16px;border-radius:8px;margin-top:10px;font-size:12px;display:none;}
.ping-result.online{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);}
.ping-result.offline{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);}
/* Toast */
.toast-area{position:fixed;bottom:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:7px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:9px;padding:12px 16px;min-width:240px;max-width:340px;display:flex;align-items:center;gap:10px;font-size:12px;font-weight:600;animation:tIn .3s ease;box-shadow:0 8px 24px rgba(0,0,0,.4);}
@keyframes tIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
.toast.success{border-color:var(--green);color:var(--green);}
.toast.error{border-color:var(--red);color:var(--red);}
.toast.info{border-color:var(--accent);color:var(--accent);}
.toast.warning{border-color:var(--yellow);color:var(--yellow);}
.spinner-sm{width:20px;height:20px;border:2px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;display:inline-block;vertical-align:middle;}
.spinner-lg{width:44px;height:44px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;margin:40px auto;display:block;}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{text-align:center;padding:40px 20px;color:var(--text-secondary);}
.empty-state i{font-size:36px;display:block;margin-bottom:12px;opacity:.3;}
.empty-state p{font-size:13px;}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <h1>🌐 Network Monitor</h1>
        <p>Device tracking system</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Monitoring</div>
        <a href="index.php"                   class="nav-link"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
        <a href="monitoring.php"              class="nav-link"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
        <a href="ai_dashboard.php"            class="nav-link"><i class="bi bi-robot"></i>AI Predictions</a>
        <a href="device_health_dashboard.php" class="nav-link active"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
        <a href="predictive_fault.php"        class="nav-link"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
        <div class="nav-section">Maintenance</div>
        <a href="maintenance_history.php"     class="nav-link"><i class="bi bi-clock-history"></i>Maintenance History</a>
        <a href="maintenance_schedules.php"   class="nav-link"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
        <a href="maintenance_reports.php"     class="nav-link"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
        <div class="nav-section">System</div>
        <a href="master_config.php"           class="nav-link"><i class="bi bi-gear-fill"></i>Master Config</a>
    </nav>
</aside>

<div class="main-content">
    <div id="loadingState" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;gap:14px;color:var(--text-secondary);">
        <div class="spinner-lg"></div>
        <p style="font-size:13px;">Loading device details…</p>
    </div>
    <div id="errorState" style="display:none;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;gap:14px;color:var(--text-secondary);">
        <i class="bi bi-exclamation-circle" style="font-size:44px;color:var(--red);"></i>
        <p style="font-size:14px;font-weight:700;color:var(--text-primary);">Failed to load device</p>
        <p id="errorMsg" style="font-size:12px;color:var(--red);max-width:400px;text-align:center;"></p>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-outline" onclick="loadAll()"><i class="bi bi-arrow-clockwise"></i> Retry</button>
            <a href="device_health_dashboard.php" class="btn btn-outline">← Back</a>
        </div>
    </div>
    <div id="mainContent" style="display:none;">
        <div class="topbar">
            <a href="javascript:history.back()" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>
            <div>
                <div class="page-title" id="pageTitle">Device Details</div>
                <div class="page-subtitle" id="pageSubtitle">Loading…</div>
            </div>
            <div class="top-actions">
                <button class="btn btn-outline" onclick="openEditDeviceModal()"><i class="bi bi-pencil-square"></i> Edit Device</button>
                <button class="btn btn-warning" onclick="openAddFlagModal()"><i class="bi bi-flag-fill"></i> Add Flag</button>
                <a href="maintenance_schedules.php?device_id=<?= $deviceId ?>" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Schedule</a>
                <button class="btn btn-outline" onclick="loadAll()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                <button class="theme-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
            </div>
        </div>

        <div class="hero-card" id="heroCard">
            <div class="device-icon-wrap" id="heroIconWrap"><span id="heroIconEmoji">🖥️</span></div>
            <div class="hero-info">
                <div class="hero-name" id="heroName">—</div>
                <div class="hero-meta" id="heroMeta">—</div>
                <div class="hero-badges" id="heroBadges"></div>
            </div>
            <div class="hero-score">
                <div class="score-ring" id="scoreRing">
                    <span class="score-num" id="scoreNum">—</span>
                    <span class="score-lbl">Health</span>
                </div>
                <div style="font-size:10px;color:var(--text-secondary);margin-top:6px;" id="riskLabel">—</div>
            </div>
        </div>

        <div class="offline-alert" id="offlineAlert" style="display:none;">
            <div class="offline-alert-icon" id="offlineAlertIcon">⚠️</div>
            <div class="offline-alert-body">
                <div class="offline-alert-title">Device Offline</div>
                <div class="offline-alert-reason" id="offlineAlertReason">Detecting reason…</div>
                <div class="offline-alert-time" id="offlineAlertTime"></div>
            </div>
        </div>

        <div class="flags-bar" id="flagsBar" style="display:none;">
            <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">⚑ Active Flags</div>
            <div id="flagsList"></div>
        </div>

        <div class="stats-grid">
            <div class="stat-box"><div class="stat-box-val" id="statHealth" style="color:var(--accent)">—</div><div class="stat-box-lbl">Health Score</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statDays">—</div><div class="stat-box-lbl">Days to Failure</div><div class="stat-box-sub" id="statFailDate">—</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statErrors">—</div><div class="stat-box-lbl">Errors (60d)</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statConf" style="color:var(--accent)">—</div><div class="stat-box-lbl">Confidence</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statUptime">—</div><div class="stat-box-lbl">Uptime (30d)</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statMaint" style="color:var(--accent)">—</div><div class="stat-box-lbl">Maintenances</div></div>
            <div class="stat-box"><div class="stat-box-val" id="statFlags" style="color:var(--red)">—</div><div class="stat-box-lbl">Active Flags</div></div>
        </div>

        <div class="tabs-bar">
            <button class="tab-btn active" onclick="switchTab('overview')" id="tab-overview"><i class="bi bi-grid-fill"></i>Overview</button>
            <button class="tab-btn" onclick="switchTab('health')"   id="tab-health"><i class="bi bi-heart-pulse-fill"></i>Health</button>
            <button class="tab-btn" onclick="switchTab('logs')"     id="tab-logs"><i class="bi bi-journal-text"></i>Logs <span class="tab-badge red" id="badge-logs">0</span></button>
            <button class="tab-btn" onclick="switchTab('flags')"    id="tab-flags"><i class="bi bi-flag-fill"></i>Flags <span class="tab-badge orange" id="badge-flags">0</span></button>
            <button class="tab-btn" onclick="switchTab('maint')"    id="tab-maint"><i class="bi bi-tools"></i>Maintenance</button>
            <button class="tab-btn" onclick="switchTab('info')"     id="tab-info"><i class="bi bi-info-circle-fill"></i>Device Info</button>
        </div>

        <div class="tab-panel active" id="panel-overview">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-graph-up"></i>Prediction Timeline</div></div>
                    <div class="timeline" id="predTimeline"><div class="spinner-sm"></div></div>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-lightbulb-fill"></i>Recommendations</div></div>
                    <div id="recsContainer"></div>
                </div>
            </div>
        </div>

        <div class="tab-panel" id="panel-health">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-activity"></i>Factor Breakdown</div></div>
                    <div class="hbar-wrap">
                        <div class="hbar-hdr"><span class="hbar-lbl">Overall Health</span><span class="hbar-val" id="hbarVal">—</span></div>
                        <div class="hbar"><div class="hbar-fill accent" id="hbarFill" style="width:0%"></div></div>
                    </div>
                    <div id="factorsList"></div>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-speedometer2"></i>Performance (7d avg)</div></div>
                    <div id="perfSection"><div class="empty-state"><i class="bi bi-bar-chart"></i><p>No performance data</p></div></div>
                </div>
            </div>
        </div>

        <div class="tab-panel" id="panel-logs">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-journal-text"></i>System Logs</div>
                    <button class="btn btn-outline btn-sm" onclick="openAddLogModal()"><i class="bi bi-plus"></i>Add Log</button>
                </div>
                <div class="log-filters">
                    <button class="log-filter-btn active" onclick="filterLogs('ALL',this)">ALL</button>
                    <button class="log-filter-btn" onclick="filterLogs('CRITICAL',this)">🔴 Critical</button>
                    <button class="log-filter-btn" onclick="filterLogs('ERROR',this)">❌ Error</button>
                    <button class="log-filter-btn" onclick="filterLogs('WARNING',this)">⚠️ Warning</button>
                    <button class="log-filter-btn" onclick="filterLogs('INFO',this)">ℹ️ Info</button>
                    <input type="text" class="log-search" id="logSearch" placeholder="Search logs…" oninput="filterLogs(currentLogLevel,null)">
                </div>
                <div class="log-list" id="logList"><div class="spinner-lg"></div></div>
                <div style="font-size:11px;color:var(--text-secondary);margin-top:10px;" id="logCount"></div>
            </div>
        </div>

        <div class="tab-panel" id="panel-flags">
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
                <button class="btn btn-warning" onclick="openAddFlagModal()"><i class="bi bi-flag-fill"></i>Add Flag</button>
            </div>
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header"><div class="card-title"><i class="bi bi-flag-fill"></i>Active Flags</div></div>
                <div id="activeFlagsPanel"><div class="empty-state"><i class="bi bi-flag"></i><p>No active flags — device is clean</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="bi bi-check-circle-fill"></i>Resolved (last 30 days)</div></div>
                <div id="resolvedFlagsPanel"><div class="empty-state"><i class="bi bi-check2-circle"></i><p>No resolved flags</p></div></div>
            </div>
        </div>

        <div class="tab-panel" id="panel-maint">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-tools"></i>Maintenance History</div>
                    <a href="maintenance_schedules.php?device_id=<?= $deviceId ?>" class="btn btn-primary btn-sm"><i class="bi bi-calendar-plus"></i>Schedule</a>
                </div>
                <div id="maintPanel"><div class="spinner-lg"></div></div>
            </div>
        </div>

        <div class="tab-panel" id="panel-info">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-hdd-network-fill"></i>Device Information</div></div>
                    <div id="deviceInfoRows"></div>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><i class="bi bi-shield-fill"></i>Network & Status</div></div>
                    <div id="networkInfoRows"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-wifi"></i>Live Connectivity</div>
                    <button class="btn btn-outline btn-sm" id="arpScanBtn" onclick="arpScan()"><i class="bi bi-radar"></i> Scan ARP</button>
                </div>
                <div id="pingResult" class="ping-result"></div>
                <div style="font-size:11px;color:var(--text-secondary);margin-top:8px;">ARP scan checks if device is currently active on the local network. Status below is from the database (updated by the background scanner).</div>
            </div>
        </div>
    </div>
</div>

<!-- Add Flag Modal -->
<div class="modal-overlay" id="flagModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-flag-fill" style="color:var(--yellow);margin-right:8px;"></i>Add Flag</h3>
            <button class="modal-close" onclick="closeFlagModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Flag Type</label>
                    <select class="form-control" id="flag-type">
                        <option value="network_issue">🌐 Network Issue</option>
                        <option value="maintenance_issue">🔧 Maintenance Issue</option>
                        <option value="software_issue">💻 Software Issue</option>
                        <option value="hardware_issue">🖥️ Hardware Issue</option>
                        <option value="security_issue">🔒 Security Issue</option>
                        <option value="custom">📌 Custom</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Severity</label>
                    <select class="form-control" id="flag-severity">
                        <option value="low">🟢 Low</option>
                        <option value="medium" selected>🔵 Medium</option>
                        <option value="high">🟠 High</option>
                        <option value="critical">🔴 Critical</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" class="form-control" id="flag-title" placeholder="e.g. Network keeps dropping every night">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="flag-desc" rows="3" placeholder="Describe the issue in detail…" style="resize:vertical;"></textarea>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Flagged by</label>
                <input type="text" class="form-control" id="flag-by" placeholder="Your name">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeFlagModal()">Cancel</button>
            <button class="btn btn-warning" id="flagSubmitBtn" onclick="submitFlag()"><i class="bi bi-flag-fill"></i> Add Flag</button>
        </div>
    </div>
</div>

<!-- Add Log Modal -->
<div class="modal-overlay" id="logModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-journal-plus" style="color:var(--accent);margin-right:8px;"></i>Add Log Entry</h3>
            <button class="modal-close" onclick="document.getElementById('logModal').classList.remove('open')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Level</label>
                    <select class="form-control" id="log-level">
                        <option value="INFO">INFO</option>
                        <option value="WARNING">WARNING</option>
                        <option value="ERROR">ERROR</option>
                        <option value="CRITICAL">CRITICAL</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" class="form-control" id="log-category" value="manual">
                </div>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Message *</label>
                <textarea class="form-control" id="log-message" rows="3" placeholder="Log message…" style="resize:vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="document.getElementById('logModal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary" id="logSubmitBtn" onclick="submitLog()"><i class="bi bi-plus-circle-fill"></i> Add Entry</button>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div class="modal-overlay" id="editDeviceModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px;"></i>Edit Device</h3>
            <button class="modal-close" onclick="document.getElementById('editDeviceModal').classList.remove('open')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Device Name *</label>
                    <input type="text" class="form-control" id="edev-name">
                </div>
                <div class="form-group">
                    <label class="form-label">IP Address *</label>
                    <input type="text" class="form-control" id="edev-ip">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Device Type</label>
                    <select class="form-control" id="edev-type">
                        <option value="computer">🖥️ Computer</option>
                        <option value="server">🗄️ Server</option>
                        <option value="laptop">💻 Laptop</option>
                        <option value="router">📡 Router</option>
                        <option value="switch">🔌 Switch</option>
                        <option value="printer">🖨️ Printer</option>
                        <option value="phone">📱 Phone</option>
                        <option value="tablet">📱 Tablet</option>
                        <option value="camera">📷 Camera</option>
                        <option value="other">📟 Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">MAC Address</label>
                    <input type="text" class="form-control" id="edev-mac" placeholder="AA:BB:CC:DD:EE:FF">
                </div>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Location / Room</label>
                <input type="text" class="form-control" id="edev-location" placeholder="e.g. Server Room B, Floor 2">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="document.getElementById('editDeviceModal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary" id="editSaveBtn" onclick="submitEditDevice()"><i class="bi bi-check-circle-fill"></i> Save Changes</button>
        </div>
    </div>
</div>

<div class="toast-area" id="toastArea"></div>

<script>
const DEVICE_ID = <?= $deviceId ?>;
let allLogs = [];
let currentLogLevel = 'ALL';
let _currentDevData = null;

// ── Toast ─────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const area = document.getElementById('toastArea');
    const icons = {success:'✅', error:'❌', info:'ℹ️', warning:'⚠️'};
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span>' + (icons[type]||'ℹ️') + '</span><span style="flex:1;">' + String(msg) + '</span>';
    area.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(), 350); }, 3500);
}

// ── Safe fetch ────────────────────────────────────────────────────
async function safeFetch(url, opts) {
    const r = await fetch(url, opts);
    const text = await r.text();
    console.log('safeFetch', url, '→', text.substring(0, 300));
    try { return JSON.parse(text); }
    catch(e) {
        const match = text.match(/<b>([^<]+)<\/b>/);
        const hint = match ? match[1] : text.substring(0, 120).replace(/<[^>]*>/g,'').trim();
        return { success: false, error: 'Server error: ' + hint };
    }
}

// ── Theme ─────────────────────────────────────────────────────────
function toggleTheme() {
    document.body.classList.toggle('light-mode');
    const light = document.body.classList.contains('light-mode');
    document.getElementById('themeIcon').className = light ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    localStorage.setItem('theme', light ? 'light' : 'dark');
}
if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light-mode');
    document.getElementById('themeIcon').className = 'bi bi-sun-fill';
}

// ── Tabs ──────────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
    document.getElementById('panel-'+name).classList.add('active');
}

const DEVICE_ICONS = {computer:'🖥️',server:'🗄️',laptop:'💻',router:'📡',switch:'🔌',printer:'🖨️',phone:'📱',tablet:'📱',camera:'📷',other:'📟'};
const FLAG_ICONS   = {network_issue:'🌐',maintenance_issue:'🔧',software_issue:'💻',hardware_issue:'🖥️',security_issue:'🔒',custom:'📌'};

function fmtDate(s)     { if(!s) return 'N/A'; return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); }
function fmtDatetime(s) { if(!s) return 'N/A'; return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
function fmtAgo(s) {
    if(!s) return 'Never';
    const m = Math.round((Date.now()-new Date(s))/60000);
    if(m<1) return 'Just now'; if(m<60) return m+'m ago'; if(m<1440) return Math.round(m/60)+'h ago'; return Math.round(m/1440)+'d ago';
}
function scoreColor(s) { s=parseFloat(s); return s<30?'var(--red)':s<50?'var(--yellow)':s<70?'var(--blue)':'var(--green)'; }
function riskClass(r)  { return {CRITICAL:'critical',HIGH:'high',MEDIUM:'medium',LOW:'low'}[r]||'medium'; }
function escHtml(s)    { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Load All ──────────────────────────────────────────────────────
async function loadAll() {
    document.getElementById('loadingState').style.display = 'flex';
    document.getElementById('mainContent').style.display  = 'none';
    document.getElementById('errorState').style.display   = 'none';
    try {
        const d = await safeFetch('api_device_detail.php?action=get_full&device_id='+DEVICE_ID);
        if (!d.success) throw new Error(d.error || 'API returned failure');
        const dev = d.device;
        if (dev.status === undefined || dev.status === null) {
            dev.status = (dev.online == 1 || dev.online === true || dev.online === 'online') ? 'online' : 'offline';
        }
        dev.health_score = dev.health_score ?? dev.health_score_cache ?? 0;
        dev.risk_level   = dev.risk_level ?? 'MEDIUM';
        allLogs = d.logs || [];
        try { renderAll(d); } catch(renderErr) { console.error('renderAll error:', renderErr); throw new Error('Render error: ' + renderErr.message); }
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('mainContent').style.display  = 'block';
    } catch(e) {
        console.error('loadAll error:', e);
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('errorState').style.display   = 'flex';
        document.getElementById('errorMsg').textContent = e.message;
    }
}

// ── Silent Reload ─────────────────────────────────────────────────
async function loadSilent() {
    try {
        const d = await safeFetch('api_device_detail.php?action=get_full&device_id='+DEVICE_ID);
        if (!d.success) return;
        const dev = d.device;
        if (dev.status === undefined || dev.status === null) {
            dev.status = (dev.online == 1 || dev.online === true || dev.online === 'online') ? 'online' : 'offline';
        }
        dev.health_score = dev.health_score ?? 0;
        dev.risk_level   = dev.risk_level   ?? 'MEDIUM';
        allLogs = d.logs || [];
        renderAll(d);
    } catch(e) { console.error('loadSilent error:', e); }
}

// ── Render All ────────────────────────────────────────────────────
function renderAll(d) {
    _currentDevData = d.device;
    const dev=d.device, f=d.factors||{}, flags=d.flags||[], stats=d.stats||{}, offline=d.offline_reason;
    const isOnline=dev.status==='online', score=parseFloat(dev.health_score||0), risk=(dev.risk_level||'MEDIUM').toUpperCase(), rc=riskClass(risk);
    const devIcon=DEVICE_ICONS[dev.device_type]||'📟';

    document.title = dev.name+' — Network Monitor';
    document.getElementById('pageTitle').textContent    = dev.name;
    document.getElementById('pageSubtitle').textContent = dev.ip_address+' · '+(dev.device_type||'Device')+' · '+(dev.network_range||'Unknown network');
    document.getElementById('heroCard').className = 'hero-card risk-'+risk+(isOnline?'':' status-offline');
    document.getElementById('heroIconWrap').className = 'device-icon-wrap'+(isOnline?'':' offline');
    document.getElementById('heroIconEmoji').textContent = devIcon;
    document.getElementById('heroName').textContent = dev.name;
    document.getElementById('heroMeta').textContent = dev.ip_address+' · '+(dev.mac_address||'No MAC')+' · '+(dev.location||'No location');
    document.getElementById('heroBadges').innerHTML =
        '<span class="badge '+(isOnline?'badge-online':'badge-offline')+'">'+(isOnline?'🟢 Online':'🔴 Offline')+'</span> '+
        '<span class="badge badge-'+rc+'">'+risk+' Risk</span> '+
        '<span class="badge badge-info">'+(dev.device_type||'Unknown')+'</span> '+
        '<span class="badge badge-info">'+(dev.network_range||'—')+'</span>'+
        (flags.length?' <span class="badge badge-high">⚑ '+flags.length+' Flag'+(flags.length>1?'s':'')+'</span>':'');

    const sc=document.getElementById('scoreNum');
    sc.textContent=score.toFixed(1)+'%'; sc.style.color=scoreColor(score);
    document.getElementById('scoreRing').style.borderColor=scoreColor(score);
    document.getElementById('riskLabel').innerHTML='<span class="badge badge-'+rc+'">'+risk+'</span>';

    // Offline alert
    if(!isOnline && offline) {
        document.getElementById('offlineAlert').style.display='flex';
        document.getElementById('offlineAlertIcon').textContent=offline.icon||'⚠️';
        document.getElementById('offlineAlertReason').textContent=offline.reason;
        document.getElementById('offlineAlertTime').textContent=dev.last_seen_at?'Last seen: '+fmtAgo(dev.last_seen_at)+' ('+fmtDatetime(dev.last_seen_at)+')':'Last seen: Unknown';
    } else {
        document.getElementById('offlineAlert').style.display='none';
    }

    // Flags bar
    if(flags.length > 0) {
        document.getElementById('flagsBar').style.display='block';
        document.getElementById('flagsList').innerHTML=flags.slice(0,3).map(fl=>renderFlagItem(fl,true)).join('')+
            (flags.length>3?'<div style="font-size:11px;color:var(--text-secondary);padding:6px 0;">+ '+(flags.length-3)+' more — <a href="javascript:switchTab(\'flags\')" style="color:var(--accent);">View all</a></div>':'');
    } else {
        document.getElementById('flagsBar').style.display='none';
    }

    // Stats
    const de=document.getElementById('statHealth'); de.textContent=score.toFixed(1)+'%'; de.style.color=scoreColor(score);
    const days=parseInt(dev.days_until_failure||0);
    const dEl=document.getElementById('statDays'); dEl.textContent=days||'—'; dEl.style.color=days<=14?'var(--red)':days<=45?'var(--yellow)':'var(--green)';
    document.getElementById('statFailDate').textContent=fmtDate(dev.predicted_failure_date);
    const eEl=document.getElementById('statErrors'); eEl.textContent=stats.error_count||0; eEl.style.color=!stats.error_count?'var(--green)':stats.error_count<10?'var(--yellow)':'var(--red)';
    document.getElementById('statConf').textContent=parseFloat(dev.confidence_level||0).toFixed(0)+'%';
    const uEl=document.getElementById('statUptime'); uEl.textContent=stats.uptime_pct!=null?stats.uptime_pct+'%':'—'; uEl.style.color=stats.uptime_pct>=99?'var(--green)':stats.uptime_pct>=95?'var(--yellow)':'var(--red)';
    document.getElementById('statMaint').textContent=f.maintenance_count||d.maintenance?.length||0;
    const flEl=document.getElementById('statFlags'); flEl.textContent=flags.length; flEl.style.color=!flags.length?'var(--green)':flags.length<=2?'var(--yellow)':'var(--red)';
    document.getElementById('badge-logs').textContent=Math.min(allLogs.length,999);
    document.getElementById('badge-flags').textContent=flags.length;

    renderTimeline(dev,days,f);
    renderRecs(dev,f,rc);
    renderHealthFactors(dev,f);
    renderPerfSection(d.perf_avg);
    renderLogs(allLogs);
    renderFlagsPanel(flags,d.resolved_flags||[]);
    renderMaint(d.maintenance||[]);
    renderDeviceInfo(dev,f,stats);
}

function renderTimeline(dev,days,f) {
    const today=new Date(), failDate=dev.predicted_failure_date?new Date(dev.predicted_failure_date):null;
    const items=[{dot:'green',date:'Today — '+fmtDate(today),title:'Current Status',desc:'Health: '+parseFloat(dev.health_score||0).toFixed(1)+'% · Risk: '+(dev.risk_level||'UNKNOWN')}];
    if(days<=14)      items.push({dot:'red',   date:'Urgent',         title:'⚠️ Critical — Act Immediately',    desc:'Device needs emergency maintenance now.'});
    else if(days<=30) items.push({dot:'yellow',date:fmtDate(new Date(Date.now()+14*86400000)),title:'Schedule Maintenance Soon',desc:'Recommended within 2 weeks.'});
    else if(days<=90) items.push({dot:'yellow',date:fmtDate(new Date(Date.now()+30*86400000)),title:'Plan Maintenance',desc:'Preventive maintenance within 30 days.'});
    else              items.push({dot:'muted', date:'Next 90 days',   title:'Routine Monitoring',               desc:'Device is healthy. Continue checks.'});
    if(failDate) items.push({dot:days<=30?'red':'yellow',date:fmtDate(failDate),title:'Predicted Failure (in '+days+' days)',desc:parseFloat(dev.confidence_level||0).toFixed(0)+'% confidence'});
    document.getElementById('predTimeline').innerHTML=items.map(it=>'<div class="tl-item"><div class="tl-dot '+it.dot+'"></div><div class="tl-date">'+it.date+'</div><div class="tl-title">'+it.title+'</div><div class="tl-desc">'+it.desc+'</div></div>').join('');
}

function renderRecs(dev,f,rc) {
    const recs=[], isOnline=dev.status==='online', days=parseInt(dev.days_until_failure||999), daysMaint=parseInt(f.days_since_maintenance??999);
    if(!isOnline)          recs.push({i:'🔴',t:'Device is offline. Investigate power and network connectivity immediately.'});
    if(rc==='critical')    recs.push({i:'⚠️',t:'Critical health score. Schedule emergency maintenance ASAP.'});
    if(daysMaint>180)      recs.push({i:'🔧',t:'No maintenance in '+daysMaint+' days. Overdue for full service check.'});
    else if(daysMaint>90)  recs.push({i:'🔧',t:'Last maintenance '+daysMaint+' days ago. Consider scheduling a routine check.'});
    if(parseInt(f.critical_errors)>0) recs.push({i:'📋',t:f.critical_errors+' critical error(s) in 60 days. Review logs for root cause.'});
    if(parseFloat(f.daily_error_rate)>1) recs.push({i:'📊',t:'High error rate: '+parseFloat(f.daily_error_rate).toFixed(2)+'/day. Investigate patterns.'});
    if(parseInt(f.offline_incidents)>3) recs.push({i:'📡',t:f.offline_incidents+' offline incidents in 90 days. Check cables and config.'});
    if(days<=30) recs.push({i:'📅',t:'Predicted failure in '+days+' days. Plan maintenance before '+fmtDate(dev.predicted_failure_date)+'.'});
    if(!recs.length) { recs.push({i:'✅',t:'Device is in good health. Continue scheduled maintenance.'}); recs.push({i:'📈',t:'Keep monitoring to maintain current health.'}); }
    document.getElementById('recsContainer').innerHTML=recs.map(r=>'<div class="rec-item"><span class="rec-icon">'+r.i+'</span><span class="rec-text">'+r.t+'</span></div>').join('');
}

function renderHealthFactors(dev,f) {
    const score=parseFloat(dev.health_score||0);
    document.getElementById('hbarVal').textContent=score.toFixed(1)+'%';
    document.getElementById('hbarFill').className='hbar-fill '+(score>=70?'green':score>=50?'yellow':'red');
    setTimeout(()=>{ document.getElementById('hbarFill').style.width=score+'%'; },80);
    const daysMaint=parseInt(f.days_since_maintenance??999), maintCls=daysMaint>180?'fv-bad':daysMaint>90?'fv-warn':'fv-good';
    const dailyErr=parseFloat(f.daily_error_rate||0), errCls=dailyErr===0?'fv-good':dailyErr<0.5?'fv-warn':'fv-bad';
    const hours=parseFloat(f.hours_since_last_seen||0), seenCls=hours<6?'fv-good':hours<24?'fv-warn':'fv-bad';
    const seenTxt=hours<1?'<1h ago':hours<24?Math.round(hours)+'h ago':Math.round(hours/24)+'d ago';
    const ageDays=parseInt(f.device_age_days||0), ageCls=ageDays<180?'fv-good':ageDays<365?'fv-warn':'fv-neutral';
    const rows=[
        {icon:'🔧',name:'Maintenance History',desc:(f.maintenance_count||0)+' services · 40% weight',val:daysMaint>=999?'Never':daysMaint+'d ago',cls:maintCls},
        {icon:'❗',name:'Error Rate',desc:(f.total_errors||0)+' total, '+(f.critical_errors||0)+' critical · 30% weight',val:dailyErr.toFixed(2)+'/day',cls:errCls},
        {icon:'📡',name:'Network Status',desc:(f.offline_incidents||0)+' offline incidents in 90d · 30% weight',val:dev.status==='online'?'Online':'Offline',cls:dev.status==='online'?'fv-good':'fv-bad'},
        {icon:'👁️',name:'Last Seen Freshness',desc:'Last network activity · 15% weight',val:seenTxt,cls:seenCls},
        {icon:'📅',name:'Device Age',desc:'10% weight',val:ageDays<365?ageDays+' days':(ageDays/365).toFixed(1)+' yrs',cls:ageCls},
    ];
    document.getElementById('factorsList').innerHTML=rows.map(r=>'<div class="factor-row"><div class="factor-icon-wrap">'+r.icon+'</div><div class="factor-info"><div class="factor-name">'+r.name+'</div><div class="factor-desc">'+r.desc+'</div></div><div class="factor-val '+r.cls+'">'+r.val+'</div></div>').join('');
}

function renderPerfSection(avg) {
    if(!avg||(!avg.avg_cpu&&!avg.avg_ram)) { document.getElementById('perfSection').innerHTML='<div class="empty-state"><i class="bi bi-bar-chart"></i><p>No performance data available</p></div>'; return; }
    const bars=[{l:'CPU Usage',v:parseFloat(avg.avg_cpu||0),u:'%',g:60,w:80},{l:'RAM Usage',v:parseFloat(avg.avg_ram||0),u:'%',g:60,w:80},{l:'Latency',v:parseFloat(avg.avg_latency||0),u:'ms',g:50,w:150},{l:'Disk Usage',v:parseFloat(avg.avg_disk||0),u:'%',g:60,w:80}];
    document.getElementById('perfSection').innerHTML=bars.map(b=>{
        const cls=b.v<=b.g?'green':b.v<=b.w?'yellow':'red', pct=Math.min(100,b.u==='%'?b.v:Math.min(100,b.v/3));
        const col=cls==='green'?'var(--green)':cls==='yellow'?'var(--yellow)':'var(--red)';
        return '<div class="hbar-wrap"><div class="hbar-hdr"><span class="hbar-lbl">'+b.l+'</span><span class="hbar-val" style="color:'+col+'">'+b.v.toFixed(1)+b.u+'</span></div><div class="hbar"><div class="hbar-fill '+cls+'" style="width:'+pct+'%"></div></div></div>';
    }).join('');
}

function renderLogs(logs) {
    const search=(document.getElementById('logSearch')?.value||'').toLowerCase();
    const filtered=logs.filter(l=>(currentLogLevel==='ALL'||l.log_level===currentLogLevel)&&(!search||(l.message||'').toLowerCase().includes(search)||(l.category||'').toLowerCase().includes(search)));
    document.getElementById('logCount').textContent='Showing '+filtered.length+' of '+logs.length+' logs';
    document.getElementById('logList').innerHTML=!filtered.length?'<div class="empty-state"><i class="bi bi-journal-x"></i><p>No logs match filter</p></div>':filtered.map(l=>'<div class="log-row"><div class="log-level-dot '+l.log_level+'"></div><span class="log-badge '+l.log_level+'">'+l.log_level+'</span><div style="flex:1;"><div class="log-msg">'+escHtml(l.message||'')+'</div><div class="log-category">'+escHtml(l.category||'system')+'</div></div><div class="log-time">'+fmtDatetime(l.created_at)+'</div></div>').join('');
}

function filterLogs(level,btn) {
    currentLogLevel=level;
    document.querySelectorAll('.log-filter-btn').forEach(b=>b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    renderLogs(allLogs);
}

function renderFlagItem(fl, compact=false) {
    const sevColors={critical:'var(--red)',high:'var(--yellow)',medium:'var(--blue)',low:'var(--green)'};
    const color=sevColors[fl.severity]||'var(--text-secondary)';
    return '<div class="flag-item '+(fl.severity||'medium')+'" style="margin-bottom:'+(compact?'6':'10')+'px;">'+
        '<div class="flag-icon">'+(FLAG_ICONS[fl.flag_type]||'📌')+'</div>'+
        '<div class="flag-body"><div class="flag-title" style="color:'+color+';">'+escHtml(fl.title)+'</div>'+
        (fl.description?'<div class="flag-desc">'+escHtml(fl.description)+'</div>':'')+
        '<div class="flag-meta">'+(fl.flag_type||'').replace(/_/g,' ')+' · '+(fl.severity||'')+' · by '+(fl.created_by||'system')+' · '+fmtAgo(fl.created_at)+'</div></div>'+
        (!compact?'<div class="flag-actions"><button class="btn btn-success btn-sm" onclick="resolveFlag('+fl.id+')"><i class="bi bi-check-lg"></i></button><button class="btn btn-danger btn-sm" onclick="deleteFlag('+fl.id+')"><i class="bi bi-trash-fill"></i></button></div>':'')+
        '</div>';
}

function renderFlagsPanel(active, resolved) {
    document.getElementById('activeFlagsPanel').innerHTML=active.length?active.map(fl=>renderFlagItem(fl)).join(''):'<div class="empty-state"><i class="bi bi-flag"></i><p>No active flags — device is clean</p></div>';
    document.getElementById('resolvedFlagsPanel').innerHTML=resolved.length?resolved.map(fl=>'<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border-color);font-size:12px;opacity:.7;"><span style="color:var(--green);">✅</span><div style="flex:1;"><strong>'+escHtml(fl.title)+'</strong><div style="font-size:10px;color:var(--text-muted);">Resolved '+fmtAgo(fl.resolved_at)+'</div></div></div>').join(''):'<div class="empty-state"><i class="bi bi-check2-circle"></i><p>No resolved flags</p></div>';
}

function renderMaint(maint) {
    if(!maint.length) { document.getElementById('maintPanel').innerHTML='<div class="empty-state"><i class="bi bi-tools"></i><p>No maintenance records</p></div>'; return; }
    document.getElementById('maintPanel').innerHTML='<div style="overflow-x:auto;"><table class="data-tbl"><thead><tr><th>Date</th><th>Type</th><th>Technician</th><th>Work Performed</th><th>Result</th></tr></thead><tbody>'+
        maint.map(m=>{const res=m.result||'completed';const cls=res==='passed'?'badge-low':res==='failed'?'badge-critical':'badge-info';return '<tr><td>'+fmtDate(m.completed_at||m.created_at)+'</td><td>'+(m.maintenance_type||m.task_name||'General')+'</td><td>'+(m.technician_name||'N/A')+'</td><td style="max-width:220px;">'+(m.work_performed||'N/A').substring(0,80)+'</td><td><span class="badge '+cls+'">'+res+'</span></td></tr>';}).join('')+
        '</tbody></table></div>';
}

function renderDeviceInfo(dev,f,stats) {
    const ageDays=parseInt(f.device_age_days||0);
    document.getElementById('deviceInfoRows').innerHTML=[
        {l:'Device Name',v:escHtml(dev.name)},{l:'IP Address',v:'<code style="color:var(--accent);">'+escHtml(dev.ip_address)+'</code>'},
        {l:'MAC Address',v:escHtml(dev.mac_address||'Unknown')},{l:'Device Type',v:escHtml(dev.device_type||'Unknown')},
        {l:'Location',v:escHtml(dev.location||'Not set')},{l:'Network Range',v:escHtml(dev.network_range||'N/A')},
        {l:'Discovery',v:escHtml(dev.discovery_method||'auto')},{l:'Device Age',v:ageDays<365?ageDays+' days':(ageDays/365).toFixed(1)+' years'},
        {l:'Created',v:fmtDate(dev.created_at)},{l:'Last Health Check',v:fmtDatetime(dev.health_updated)},
    ].map(r=>'<div class="info-row"><span class="info-lbl">'+r.l+'</span><span class="info-val">'+r.v+'</span></div>').join('');
    document.getElementById('networkInfoRows').innerHTML=[
        {l:'Status',v:'<span class="badge '+(dev.status==='online'?'badge-online':'badge-offline')+'">'+(dev.status==='online'?'🟢 Online':'🔴 Offline')+'</span>'},
        {l:'Last Seen',v:dev.last_seen_at?fmtAgo(dev.last_seen_at)+' ('+fmtDatetime(dev.last_seen_at)+')':'Never'},
        {l:'Risk Level',v:'<span class="badge badge-'+riskClass(dev.risk_level||'MEDIUM')+'">'+(dev.risk_level||'UNKNOWN')+'</span>'},
        {l:'Health Score',v:'<strong style="color:'+scoreColor(dev.health_score||0)+'">'+parseFloat(dev.health_score||0).toFixed(1)+'%</strong>'},
        {l:'Confidence',v:parseFloat(dev.confidence_level||0).toFixed(0)+'%'},
        {l:'Days to Failure',v:dev.days_until_failure||'—'},
        {l:'Offline Incidents',v:(f.offline_incidents||0)+' in 90 days'},
        {l:'Errors (60d)',v:(stats.error_count||0)+' ('+(stats.critical_count||0)+' critical)'},
        {l:'Warnings (60d)',v:stats.warn_count||0},
        {l:'Uptime (30d)',v:stats.uptime_pct!=null?stats.uptime_pct+'%':'N/A'},
    ].map(r=>'<div class="info-row"><span class="info-lbl">'+r.l+'</span><span class="info-val">'+r.v+'</span></div>').join('');
}

// ── Edit Device ───────────────────────────────────────────────────
function openEditDeviceModal() {
    if (!_currentDevData) { toast('Device data not loaded yet','error'); return; }
    const dev = _currentDevData;
    document.getElementById('edev-name').value     = dev.name || '';
    document.getElementById('edev-ip').value       = dev.ip_address || '';
    document.getElementById('edev-mac').value      = dev.mac_address || '';
    document.getElementById('edev-location').value = dev.location || '';
    const t = document.getElementById('edev-type');
    if (t) t.value = dev.device_type || 'computer';
    document.getElementById('editDeviceModal').classList.add('open');
}

async function submitEditDevice() {
    const id       = DEVICE_ID;
    const name     = document.getElementById('edev-name').value.trim();
    const ip       = document.getElementById('edev-ip').value.trim();
    const type     = document.getElementById('edev-type').value;
    const mac      = document.getElementById('edev-mac').value.trim();
    const location = document.getElementById('edev-location').value.trim();

    if (!name || !ip) { toast('Name and IP are required', 'error'); return; }

    const btn = document.getElementById('editSaveBtn');
    if (btn) { btn.disabled=true; btn.textContent='Saving…'; }

    const cfgApi = async (body) => {
        const r = await fetch('master_config.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });
        const text = await r.text();
        console.log('cfgApi', body.action, '→', text.substring(0,200));
        try { return JSON.parse(text); }
        catch(e) { return {success:false, error:'Server error: ' + text.substring(0,100)}; }
    };

    try {
        const r1 = await cfgApi({action:'rename_device',          id, name});
        const r2 = await cfgApi({action:'update_device_ip',       id, ip});
        const r3 = await cfgApi({action:'update_device_type',     id, device_type:type});
        const r4 = await cfgApi({action:'update_device_mac',      id, mac});
        const r5 = await cfgApi({action:'update_device_location', id, location});

        const errs = [r1,r2,r3,r4,r5].filter(r => !r?.success).map(r => r?.error).filter(Boolean);

        if (errs.length === 0) {
            toast('Device updated successfully', 'success');
            document.getElementById('editDeviceModal').classList.remove('open');
            setTimeout(loadSilent, 600);
        } else {
            toast('Issues: ' + errs.join(' · '), 'error');
            setTimeout(loadSilent, 1000);
        }
    } catch(e) {
        toast('Update failed: ' + e.message, 'error');
        console.error('submitEditDevice error:', e);
    } finally {
        if (btn) { btn.disabled=false; btn.innerHTML='<i class="bi bi-check-circle-fill"></i> Save Changes'; }
    }
}

// ── Flags ─────────────────────────────────────────────────────────
function openAddFlagModal() { document.getElementById('flagModal').classList.add('open'); }
function closeFlagModal()   { document.getElementById('flagModal').classList.remove('open'); }

async function submitFlag() {
    const flagType = document.getElementById('flag-type').value;
    const severity = document.getElementById('flag-severity').value;
    const title    = document.getElementById('flag-title').value.trim();
    const desc     = document.getElementById('flag-desc').value.trim();
    const by       = document.getElementById('flag-by').value.trim();

    if (!title) { toast('Title is required', 'error'); return; }

    const btn = document.getElementById('flagSubmitBtn');
    if (btn) { btn.disabled=true; btn.textContent='Adding…'; }

    try {
        const d = await safeFetch('api_device_detail.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action:'add_flag', device_id:DEVICE_ID, flag_type:flagType, severity, title, description:desc, created_by:by})
        });
        if (d.success) {
            toast('Flag added successfully', 'success');
            closeFlagModal();
            document.getElementById('flag-title').value = '';
            document.getElementById('flag-desc').value  = '';
            setTimeout(loadSilent, 400);
        } else {
            toast('Failed: '+(d.error||'Unknown error'), 'error');
        }
    } catch(e) {
        toast('Failed: '+e.message, 'error');
    } finally {
        if (btn) { btn.disabled=false; btn.innerHTML='<i class="bi bi-flag-fill"></i> Add Flag'; }
    }
}

async function resolveFlag(flagId) {
    if (!confirm('Mark this flag as resolved?')) return;
    const d = await safeFetch('api_device_detail.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'resolve_flag', flag_id:flagId, device_id:DEVICE_ID})
    });
    if (d.success) { toast('Flag resolved', 'success'); setTimeout(loadSilent, 400); }
    else toast('Failed: '+(d.error||'Unknown error'), 'error');
}

async function deleteFlag(flagId) {
    if (!confirm('Delete this flag permanently?')) return;
    const d = await safeFetch('api_device_detail.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'delete_flag', flag_id:flagId, device_id:DEVICE_ID})
    });
    if (d.success) { toast('Flag deleted', 'success'); setTimeout(loadSilent, 400); }
    else toast('Failed: '+(d.error||'Unknown error'), 'error');
}

// ── Logs ──────────────────────────────────────────────────────────
function openAddLogModal() { document.getElementById('logModal').classList.add('open'); }

async function submitLog() {
    const level   = document.getElementById('log-level').value;
    const cat     = document.getElementById('log-category').value.trim() || 'manual';
    const message = document.getElementById('log-message').value.trim();

    if (!message) { toast('Message is required', 'error'); return; }

    const btn = document.getElementById('logSubmitBtn');
    if (btn) { btn.disabled=true; btn.textContent='Adding…'; }

    try {
        const d = await safeFetch('api_device_detail.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action:'add_log', device_id:DEVICE_ID, log_level:level, category:cat, message})
        });
        if (d.success) {
            toast('Log entry added', 'success');
            document.getElementById('logModal').classList.remove('open');
            document.getElementById('log-message').value = '';
            setTimeout(loadSilent, 400);
        } else {
            toast('Failed: '+(d.error||'Unknown error'), 'error');
        }
    } catch(e) {
        toast('Failed: '+e.message, 'error');
    } finally {
        if (btn) { btn.disabled=false; btn.innerHTML='<i class="bi bi-plus-circle-fill"></i> Add Entry'; }
    }
}

// ── ARP Scan ──────────────────────────────────────────────────────
async function arpScan() {
    const el  = document.getElementById('pingResult');
    const btn = document.getElementById('arpScanBtn');
    if (el)  { el.style.display='block'; el.className='ping-result'; el.innerHTML='⏳ Scanning ARP…'; }
    if (btn) { btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Scanning…'; }

    try {
        const d = await safeFetch('api_device_detail.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action:'ping_now', device_id:DEVICE_ID})
        });
        const online = !!d.online;
        const mac    = d.mac || '';
        if (el) {
            el.className = 'ping-result ' + (online ? 'online' : 'offline');
            el.innerHTML = online
                ? '✅ <strong>Online</strong> — found in ARP table' + (mac ? ' · <code>'+mac+'</code>' : '')
                : '❌ <strong>Not found in ARP table</strong> — device may be offline or idle';
        }
        toast(online ? '✅ Online — ARP confirmed'+(mac?' ('+mac+')':'') : '❌ Not in ARP table', online?'success':'error');
        setTimeout(loadSilent, 500);
    } catch(e) {
        if (el) { el.className='ping-result offline'; el.innerHTML='❌ Scan failed: '+e.message; }
        toast('Scan failed: '+e.message, 'error');
    } finally {
        if (btn) { btn.disabled=false; btn.innerHTML='<i class="bi bi-radar"></i> Scan ARP'; }
    }
}

// ── Init ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadAll);
</script>
</body>
</html>