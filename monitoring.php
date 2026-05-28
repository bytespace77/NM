<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

function isSystemMonitorRunning() {
    $hb = __DIR__ . '/runtime/system_monitor_heartbeat.txt';
    if (!file_exists($hb)) return false;
    $t = (int)trim(@file_get_contents($hb));
    return (time() - $t) < 30;
}

function findPhpBinary() {
    $binary = PHP_BINARY;
    if (stripos($binary,'php')===false || stripos($binary,'httpd')!==false) {
        $derived = dirname(dirname(dirname($binary))) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
        if (file_exists($derived)) return $derived;
    }
    if (stripos($binary,'php')!==false && file_exists($binary)) return $binary;
    foreach (['C:\\xampp\\php\\php.exe','C:\\wamp64\\bin\\php\\php.exe','/usr/bin/php','/usr/local/bin/php'] as $p) {
        if (file_exists($p)) return $p;
    }
    return null;
}

function startSystemMonitor() {
    $phpPath = findPhpBinary();
    $scriptPath = __DIR__ . '/system_monitor.php';
    if (!$phpPath || !file_exists($phpPath) || !file_exists($scriptPath)) return false;
    if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
        $vbs = sys_get_temp_dir().'\\start_monitor_'.time().'.vbs';
        file_put_contents($vbs,'Set o=CreateObject("WScript.Shell")'."\n".'o.Run Chr(34)&"'.$phpPath.'"&Chr(34)&" "&Chr(34)&"'.$scriptPath.'"&Chr(34),0,False'."\n");
        pclose(popen('"C:\\Windows\\System32\\wscript.exe" "'.$vbs.'"','r'));
    } else {
        exec('nohup "'.$phpPath.'" "'.$scriptPath.'" > /dev/null 2>&1 &');
    }
    sleep(3);
    for ($i=0;$i<10;$i++) { if (isSystemMonitorRunning()) return true; sleep(1); }
    return file_exists(__DIR__.'/runtime/system_monitor.pid');
}

$autoStartSuccess = false;
$autoStartFailed  = false;
if (!isSystemMonitorRunning()) {
    $started = startSystemMonitor();
    $autoStartSuccess = $started;
    $autoStartFailed  = !$started;
}

$db = getDB();
$required_tables = ['system_metrics','system_logs','alert_rules','alert_history','device_statistics'];
$missing_tables = [];
foreach ($required_tables as $t) {
    $r = $db->query("SHOW TABLES LIKE '$t'");
    if (!$r || $r->num_rows == 0) $missing_tables[] = $t;
}
$tablesExist = empty($missing_tables);
$dataPointCount = 0;
if ($tablesExist) {
    $cr = $db->query("SELECT COUNT(*) as c FROM system_metrics WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($cr) $dataPointCount = $cr->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Monitor - SafeG</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>📊</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg-primary:#000;--bg-secondary:#0f0f0f;--bg-tertiary:#0a0a0a;--border-color:#1a1a1a;--border-hover:#333;--text-primary:#fff;--text-secondary:#888;--text-muted:#555;--critical:#ff4757;--high:#ffa502;--medium:#3742fa;--low:#2ed573;--accent:#667eea;}
body.light-mode{--bg-primary:#f5f5f5;--bg-secondary:#fff;--bg-tertiary:#f0f0f0;--border-color:#e0e0e0;--border-hover:#ccc;--text-primary:#111;--text-secondary:#666;--text-muted:#aaa;}
body{font-family:'Montserrat',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;}
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg-secondary);border-right:1px solid var(--border-color);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-brand{padding:18px 16px;border-bottom:1px solid var(--border-color);}
.sidebar-brand h2{font-size:13px;font-weight:700;}
.sidebar-brand p{font-size:10px;color:var(--text-secondary);margin-top:2px;}
.nav-group-label{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;padding:12px 14px 4px;}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:6px;text-decoration:none;color:var(--text-secondary);font-size:12px;font-weight:500;margin:1px 6px;transition:all .15s;}
.nav-item:hover{background:var(--bg-tertiary);color:var(--text-primary);}
.nav-item.active{background:rgba(102,126,234,.1);color:var(--accent);border:1px solid rgba(102,126,234,.2);}
.nav-item i{font-size:13px;width:16px;text-align:center;}
/* Monitor control panel in sidebar */
.ctrl-panel{margin:8px 8px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:8px;overflow:hidden;}
.ctrl-head{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--border-color);}
.ctrl-title{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;}
.ctrl-minimize{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:2px;}
.ctrl-body{padding:10px 12px;}
.ctrl-status{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;}
.status-running{background:rgba(46,213,115,.15);color:var(--low);border:1px solid rgba(46,213,115,.3);}
.status-stopped{background:rgba(255,71,87,.15);color:var(--critical);border:1px solid rgba(255,71,87,.3);}
.status-unknown{background:rgba(156,163,175,.15);color:#9ca3af;border:1px solid rgba(156,163,175,.3);}
.sdot{width:5px;height:5px;border-radius:50%;animation:sdpulse 2s ease infinite;}
.status-running .sdot{background:var(--low);box-shadow:0 0 6px var(--low);}
.status-stopped .sdot{background:var(--critical);}
.status-unknown .sdot{background:#9ca3af;animation:none;}
@keyframes sdpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}
.ctrl-info{font-size:10px;color:var(--text-muted);margin-bottom:8px;}
.ctrl-info-row{display:flex;justify-content:space-between;padding:2px 0;}
.ctrl-info-row span:last-child{color:var(--text-secondary);font-weight:600;}
.ctrl-btn{display:flex;align-items:center;gap:5px;width:100%;padding:7px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);color:var(--text-secondary);background:var(--bg-secondary);transition:all .15s;margin-bottom:5px;}
.ctrl-btn:hover:not(:disabled){border-color:var(--accent);color:var(--accent);}
.ctrl-btn.start{border-color:rgba(46,213,115,.3);color:var(--low);}
.ctrl-btn.start:hover:not(:disabled){background:rgba(46,213,115,.08);}
.ctrl-btn:disabled{opacity:.4;cursor:not-allowed;}
.ctrl-footer{font-size:10px;color:var(--text-muted);padding-top:6px;border-top:1px solid var(--border-color);}
.sidebar-mini{margin-top:auto;}
.main{margin-left:220px;padding:24px;}
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);} .btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}
/* Metric cards */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:22px;}
.metric-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;position:relative;overflow:hidden;}
.mc-head{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;}
.mc-icon{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.mc-icon.cpu{background:rgba(102,126,234,.15);color:var(--accent);}
.mc-icon.ram{background:rgba(16,185,129,.15);color:#10b981;}
.mc-icon.net{background:rgba(55,66,250,.15);color:var(--medium);}
.mc-icon.temp{background:rgba(245,158,11,.15);color:#f59e0b;}
.mc-lbl{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
.mc-trend{font-size:10px;color:var(--text-muted);margin-top:2px;}
.mc-value{font-size:30px;font-weight:800;line-height:1;margin-bottom:4px;}
.mc-detail{font-size:11px;color:var(--text-secondary);}
canvas.sparkline{position:absolute;bottom:0;right:0;width:120px;height:50px;opacity:.6;}
/* Chart section */
.chart-section{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:20px;margin-bottom:20px;}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;}
.section-title{font-size:14px;font-weight:700;}
.section-actions{display:flex;gap:6px;flex-wrap:wrap;}
.date-btn{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);padding:5px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;}
.date-btn:hover,.date-btn.active{border-color:var(--accent);color:var(--accent);}
.chart-wrap{height:280px;position:relative;}
/* Alerts & logs */
.alerts-section{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:20px;margin-bottom:20px;}
.alert-item{display:flex;align-items:flex-start;justify-content:space-between;padding:12px 14px;border:1px solid var(--border-color);border-radius:6px;margin-bottom:8px;border-left:3px solid var(--border-color);}
.alert-item.CRITICAL{border-left-color:var(--critical);}
.alert-item.WARNING{border-left-color:var(--high);}
.alert-item.INFO{border-left-color:var(--medium);}
.alert-badge{padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;text-transform:uppercase;margin-right:6px;}
.alert-badge.CRITICAL{background:rgba(255,71,87,.12);color:var(--critical);}
.alert-badge.WARNING{background:rgba(255,165,2,.12);color:var(--high);}
.alert-badge.INFO{background:rgba(55,66,250,.12);color:var(--medium);}
.alert-msg{font-size:12px;margin:4px 0;}
.alert-time{font-size:10px;color:var(--text-muted);}
.ack-btn{flex-shrink:0;padding:5px 10px;border-radius:5px;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;}
.ack-btn:hover{border-color:var(--low);color:var(--low);}
/* Logs */
.log-filters{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;}
.log-sel{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:6px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;}
.log-container{max-height:300px;overflow-y:auto;font-family:'Montserrat',sans-serif;}
.log-row{display:flex;gap:8px;padding:5px 6px;border-bottom:1px solid var(--border-color);font-size:11px;align-items:baseline;flex-wrap:wrap;}
.log-row:hover{background:var(--bg-tertiary);}
.log-time{color:var(--text-muted);white-space:nowrap;min-width:120px;}
.log-level{font-weight:700;padding:1px 5px;border-radius:2px;font-size:9px;}
.log-level.INFO{color:var(--medium);}
.log-level.WARNING{color:var(--high);}
.log-level.ERROR,.log-level.CRITICAL{color:var(--critical);}
.log-cat{color:var(--accent);font-size:10px;}
.log-msg{color:var(--text-secondary);flex:1;}
/* Notice banner */
.notice{background:rgba(102,126,234,.08);border:1px solid rgba(102,126,234,.2);border-radius:6px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;font-size:12px;}
.notice.warn{background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.2);}
.notice.err{background:rgba(255,71,87,.08);border-color:rgba(255,71,87,.2);}
.notice i{font-size:16px;flex-shrink:0;margin-top:1px;}
/* Toast */
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;font-size:12px;animation:tIn .2s ease;font-family:'Montserrat',sans-serif;}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok,.toast.SUCCESS{border-color:var(--low);}
.toast.err,.toast.ERROR{border-color:var(--critical);}
.toast.INFO{border-color:var(--medium);}
.empty{text-align:center;padding:40px;color:var(--text-secondary);}
.empty i{font-size:36px;display:block;margin-bottom:10px;opacity:.3;}
::-webkit-scrollbar{width:4px;height:4px;} ::-webkit-scrollbar-track{background:transparent;} ::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>🌐 Network Monitor</h2><p>SafeG Monitoring System</p></div>
  <nav style="padding:8px 0;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item active"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
    <a href="ai_dashboard.php"            class="nav-item"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <a href="notifications.php"           class="nav-item"><i class="bi bi-bell-fill"></i>Notifications</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>

  <!-- Monitor Control Panel -->
  <div class="ctrl-panel" id="ctrlPanel">
    <div class="ctrl-head">
      <div class="ctrl-title"><i class="bi bi-cpu-fill" style="color:var(--accent);"></i> Monitor Control</div>
      <button class="ctrl-minimize" onclick="toggleCtrl()" title="Toggle"><i class="bi bi-dash-lg"></i></button>
    </div>
    <div class="ctrl-body" id="ctrlBody">
      <div class="ctrl-status">
        <span style="font-size:10px;font-weight:600;color:var(--text-secondary);">Status</span>
        <div class="status-badge status-unknown" id="monitorBadge"><div class="sdot"></div><span id="monitorText">Checking...</span></div>
      </div>
      <div class="ctrl-info" id="ctrlInfo">
        <div class="ctrl-info-row"><span>PID</span><span id="mPID">—</span></div>
        <div class="ctrl-info-row"><span>Heartbeat</span><span id="mHB">—</span></div>
        <div class="ctrl-info-row"><span>Age</span><span id="mAge">—</span></div>
      </div>
      <button class="ctrl-btn start" id="btnStart" onclick="startMonitor()"><i class="bi bi-play-fill"></i> Start Monitor</button>
      <button class="ctrl-btn" onclick="refreshMonitorStatus()"><i class="bi bi-arrow-clockwise"></i> Refresh Status</button>
      <div class="ctrl-footer">Updated: <span id="ctrlUpdated">Never</span></div>
    </div>
  </div>

  <div class="sidebar-mini" style="padding:10px 12px;border-top:1px solid var(--border-color);margin-top:auto;">
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:4px;">
      <span>CPU</span><span id="sbCPU" style="font-weight:700;color:var(--accent)">—</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:4px;">
      <span>RAM</span><span id="sbRAM" style="font-weight:700;color:#10b981">—</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:4px;">
      <span>Network</span><span id="sbNet" style="font-weight:700;color:var(--medium)">—</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);">
      <span>Temp</span><span id="sbTemp" style="font-weight:700;color:#f59e0b">—</span>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="page-title">📊 System Monitor</div>
      <div class="page-subtitle">Real-time CPU, RAM, network &amp; temperature monitoring</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-ghost btn-sm" onclick="exportToPDF()"><i class="bi bi-file-pdf"></i> PDF</button>
      <button class="btn btn-ghost btn-sm" onclick="exportToCSV()"><i class="bi bi-file-spreadsheet"></i> CSV</button>
      <button class="btn btn-ghost btn-sm" onclick="loadLogs()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <?php if ($autoStartSuccess): ?>
  <div class="notice"><i class="bi bi-check-circle-fill" style="color:var(--low);"></i><div><strong>System Monitor Started</strong><br>Background monitoring service started automatically.</div></div>
  <?php elseif ($autoStartFailed): ?>
  <div class="notice warn"><i class="bi bi-exclamation-triangle-fill" style="color:var(--high);"></i><div><strong>Auto-Start Failed</strong><br>Please run: <code>php system_monitor.php</code></div></div>
  <?php endif; ?>

  <?php if (!$tablesExist): ?>
  <div class="notice err"><i class="bi bi-x-circle-fill" style="color:var(--critical);"></i><div><strong>Tables Missing:</strong> <?php echo implode(', ', $missing_tables); ?><br>Run system_monitor.php to create required tables.</div></div>
  <?php else: ?>

  <?php if ($dataPointCount < 5): ?>
  <div class="notice"><i class="bi bi-info-circle-fill" style="color:var(--accent);"></i><div><strong>Building Historical Data</strong><br><?php echo $dataPointCount; ?> data points collected. Chart auto-updates as data arrives (~24h for full graph).</div></div>
  <?php endif; ?>

  <!-- Metric Cards -->
  <div class="metrics-grid">
    <div class="metric-card" id="cpuCard">
      <div class="mc-head">
        <div class="mc-icon cpu"><i class="bi bi-cpu"></i></div>
        <div><div class="mc-lbl">CPU Usage</div><div class="mc-trend" id="cpuTrend">→ 0.0%</div></div>
      </div>
      <div class="mc-value" id="cpuValue">—</div>
      <div class="mc-detail" id="cpuDetail">—</div>
      <canvas class="sparkline" id="cpuSparkline" width="120" height="50"></canvas>
    </div>
    <div class="metric-card" id="ramCard">
      <div class="mc-head">
        <div class="mc-icon ram"><i class="bi bi-memory"></i></div>
        <div><div class="mc-lbl">RAM Usage</div><div class="mc-trend" id="ramTrend">→ 0.0%</div></div>
      </div>
      <div class="mc-value" id="ramValue">—</div>
      <div class="mc-detail" id="ramDetail">—</div>
      <canvas class="sparkline" id="ramSparkline" width="120" height="50"></canvas>
    </div>
    <div class="metric-card" id="netCard">
      <div class="mc-head">
        <div class="mc-icon net"><i class="bi bi-router"></i></div>
        <div><div class="mc-lbl">Network + Latency</div><div class="mc-trend" id="netTrend">→ 0 MB/s</div></div>
      </div>
      <div class="mc-value" id="netValue">— MB/s</div>
      <div class="mc-detail" id="netDetail">Ping: — ms</div>
      <canvas class="sparkline" id="netSparkline" width="120" height="50"></canvas>
    </div>
    <div class="metric-card" id="tempCard">
      <div class="mc-head">
        <div class="mc-icon temp"><i class="bi bi-thermometer-half"></i></div>
        <div><div class="mc-lbl">Temperature</div><div class="mc-trend" id="tempTrend">→ 0.0°C</div></div>
      </div>
      <div class="mc-value" id="tempValue">—</div>
      <div class="mc-detail" id="tempDetail">CPU Temperature</div>
      <canvas class="sparkline" id="tempSparkline" width="120" height="50"></canvas>
    </div>
  </div>

  <!-- Performance Chart -->
  <div class="chart-section">
    <div class="section-head">
      <span class="section-title">📈 Performance History</span>
      <div class="section-actions">
        <button class="date-btn active" id="filtertoday"  onclick="setDateFilter('today')">Today</button>
        <button class="date-btn"        id="filter7days"  onclick="setDateFilter('7days')">7 Days</button>
        <button class="date-btn"        id="filter30days" onclick="setDateFilter('30days')">30 Days</button>
      </div>
    </div>
    <div class="chart-wrap"><canvas id="perfChart"></canvas></div>
  </div>

  <!-- Active Alerts -->
  <div class="alerts-section">
    <div class="section-head">
      <span class="section-title"><i class="bi bi-exclamation-triangle"></i> Active Alerts (<span id="alertCount">0</span>)</span>
      <button class="btn btn-ghost btn-sm" onclick="loadAlerts()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div id="alertsContainer"><div class="empty"><i class="bi bi-check-circle"></i><p>Loading alerts...</p></div></div>
  </div>

  <!-- System Logs -->
  <div class="alerts-section">
    <div class="section-head">
      <span class="section-title"><i class="bi bi-file-earmark-text"></i> System Logs</span>
      <div class="section-actions">
        <button class="btn btn-ghost btn-sm" onclick="exportLogsCSV()"><i class="bi bi-file-spreadsheet"></i> Export</button>
        <button class="btn btn-ghost btn-sm" onclick="loadLogs()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      </div>
    </div>
    <div class="log-filters">
      <select class="log-sel" id="logLevel" onchange="loadLogs()">
        <option value="">All Levels</option>
        <option value="INFO">INFO</option><option value="WARNING">WARNING</option>
        <option value="ERROR">ERROR</option><option value="CRITICAL">CRITICAL</option>
      </select>
      <select class="log-sel" id="logCat" onchange="loadLogs()">
        <option value="">All Categories</option>
        <option value="system">System</option><option value="network">Network</option>
        <option value="scan">Scan</option><option value="alert">Alert</option><option value="monitoring">Monitoring</option>
      </select>
    </div>
    <div class="log-container" id="logsContainer"><div class="empty" style="padding:20px;"><p>Loading logs...</p></div></div>
  </div>

  <?php endif; ?>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
const API = 'api_monitoring.php';
let perfChart=null, chartData=null, lastDataCount=0, currentDateFilter='today';
let autoRefreshTimer=null, metricsTimer=null;
const sparkData={cpu:[],ram:[],net:[],temp:[]};

function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('sm_theme',l?'light':'dark'); createPerfChart(chartData); }
if(localStorage.getItem('sm_theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmtTime(s){ if(!s||s==='-') return '—'; try{ const d=new Date(s),my=new Date(d.getTime()+8*3600000); return String(my.getUTCDate()).padStart(2,'0')+'/'+(String(my.getUTCMonth()+1).padStart(2,'0'))+'/'+my.getUTCFullYear()+' '+String(my.getUTCHours()).padStart(2,'0')+':'+String(my.getUTCMinutes()).padStart(2,'0')+':'+String(my.getUTCSeconds()).padStart(2,'0'); }catch(e){ return s; } }
function fmtTS(s){ if(!s) return '—'; try{ return new Date(s).toLocaleString('en-MY',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}); }catch(e){ return s; } }

// Sparklines
function drawSparkline(id, data, color, fill){
  const cv=document.getElementById(id); if(!cv||data.length<2) return;
  const cx=cv.getContext('2d'), w=cv.width, h=cv.height;
  cx.clearRect(0,0,w,h);
  const mx=Math.max(...data), mn=Math.min(...data), rng=mx-mn||1;
  cx.beginPath(); cx.strokeStyle=color; cx.lineWidth=2; cx.lineJoin='round';
  data.forEach((v,i)=>{ const x=i/(data.length-1)*w, y=h-((v-mn)/rng)*(h-10)-5; i===0?cx.moveTo(x,y):cx.lineTo(x,y); });
  cx.stroke();
  cx.lineTo(w,h); cx.lineTo(0,h); cx.closePath(); cx.fillStyle=fill; cx.fill();
}
function pushSparkle(key,val){ if(val===null||val===undefined||isNaN(val)) return; sparkData[key].push(parseFloat(val)); if(sparkData[key].length>20) sparkData[key].shift(); }
function updateTrend(id,data){
  const el=document.getElementById(id); if(!el||data.length<2) return;
  const diff=data[data.length-1]-data[data.length-2];
  el.textContent=(diff>0?'↑':diff<0?'↓':'→')+' '+Math.abs(diff).toFixed(1)+(id.includes('net')?'':id.includes('temp')?'°C':'%');
}

// Metrics loading
async function loadMetrics(){
  try{
    const r=await fetch(`${API}?action=current_metrics&t=${Date.now()}`);
    const d=await r.json(); if(!d.success) return;
    const m=d.metrics;
    const cpu=m.cpu_usage?parseFloat(m.cpu_usage):null;
    const ram=m.ram_usage?parseFloat(m.ram_usage):null;
    const netTot=parseFloat(m.network_total_speed||0);
    const temp=m.temperature?parseFloat(m.temperature):null;
    if(cpu!==null){ document.getElementById('cpuValue').textContent=cpu.toFixed(1)+'%'; document.getElementById('cpuDetail').textContent=m.cpu_frequency?parseFloat(m.cpu_frequency).toFixed(2)+' GHz':'Frequency N/A'; document.getElementById('sbCPU').textContent=cpu.toFixed(1)+'%'; pushSparkle('cpu',cpu); drawSparkline('cpuSparkline',sparkData.cpu,'#667eea','rgba(102,126,234,.15)'); updateTrend('cpuTrend',sparkData.cpu); }
    if(ram!==null){ const rU=parseFloat(m.ram_used_gb||0).toFixed(1), rT=parseFloat(m.ram_total_gb||0).toFixed(1); document.getElementById('ramValue').textContent=ram.toFixed(1)+'%'; document.getElementById('ramDetail').textContent=rT>0?rU+' GB / '+rT+' GB':'Details N/A'; document.getElementById('sbRAM').textContent=ram.toFixed(1)+'%'; pushSparkle('ram',ram); drawSparkline('ramSparkline',sparkData.ram,'#10b981','rgba(16,185,129,.15)'); updateTrend('ramTrend',sparkData.ram); }
    if(m.network_upload_speed!==null&&m.network_upload_speed!==undefined){ const up=parseFloat(m.network_upload_speed||0).toFixed(2), dn=parseFloat(m.network_download_speed||0).toFixed(2), lat=m.latency_ms?parseFloat(m.latency_ms).toFixed(0):'N/A'; document.getElementById('netValue').textContent=netTot.toFixed(2)+' MB/s'; document.getElementById('netDetail').textContent=`Ping: ${lat} ms | ↑${up} ↓${dn} MB/s`; document.getElementById('sbNet').textContent=netTot.toFixed(2)+' MB/s'; pushSparkle('net',netTot); drawSparkline('netSparkline',sparkData.net,'#3742fa','rgba(55,66,250,.15)'); updateTrend('netTrend',sparkData.net); }
    if(temp!==null){ document.getElementById('tempValue').textContent=temp.toFixed(1)+'°C'; document.getElementById('tempDetail').textContent='CPU Temperature'; document.getElementById('sbTemp').textContent=temp.toFixed(1)+'°C'; pushSparkle('temp',temp); drawSparkline('tempSparkline',sparkData.temp,'#f59e0b','rgba(245,158,11,.15)'); updateTrend('tempTrend',sparkData.temp); }
    checkNewData();
  }catch(e){ console.error('Metrics error:',e); }
}

async function checkNewData(){
  try{
    const r=await fetch(`${API}?action=historical_metrics&hours=24&t=${Date.now()}`);
    const d=await r.json(); if(!d.success) return;
    if(lastDataCount>0&&d.count>lastDataCount) loadHistorical();
    lastDataCount=d.count;
  }catch(e){}
}

async function loadHistorical(){
  try{
    const r=await fetch(`${API}?action=historical_metrics&date_filter=${currentDateFilter}&t=${Date.now()}`);
    const d=await r.json();
    if(d.success){ chartData=d.data; lastDataCount=d.count; createPerfChart(d.data); }
  }catch(e){ console.error('Historical error:',e); }
}

function setDateFilter(f){
  currentDateFilter=f;
  document.querySelectorAll('.date-btn').forEach(b=>b.classList.remove('active'));
  const el=document.getElementById('filter'+f); if(el) el.classList.add('active');
  loadHistorical();
}

function createPerfChart(data){
  if(!data) return;
  const ctx=document.getElementById('perfChart'); if(!ctx) return;
  if(perfChart) perfChart.destroy();
  const isDark=!document.body.classList.contains('light-mode');
  const tc=isDark?'#666':'#999', gc=isDark?'#1a1a1a':'#e0e0e0';
  const ds=[
    {label:'CPU (%)',data:data.cpu,borderColor:'#667eea',backgroundColor:'rgba(102,126,234,.1)',borderWidth:2,tension:.4,fill:true,spanGaps:true},
    {label:'RAM (%)',data:data.ram,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.1)',borderWidth:2,tension:.4,fill:true,spanGaps:true}
  ];
  if(data.temperature&&data.temperature.some(t=>t!==null))
    ds.push({label:'Temp (°C)',data:data.temperature,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.1)',borderWidth:2,tension:.4,fill:true,yAxisID:'y1',spanGaps:true});
  perfChart=new Chart(ctx,{type:'line',data:{labels:data.labels,datasets:ds},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true,position:'top',labels:{color:tc,font:{family:'Montserrat',weight:'600'}}},tooltip:{backgroundColor:isDark?'#1a1a1a':'#fff',titleColor:isDark?'#fff':'#000',bodyColor:'#666',borderColor:isDark?'#333':'#e0e0e0',borderWidth:1}},scales:{x:{ticks:{color:tc,font:{family:'Montserrat'}},grid:{color:gc}},y:{type:'linear',position:'left',ticks:{color:tc,font:{family:'Montserrat'}},grid:{color:gc},beginAtZero:true,max:100},y1:{type:'linear',display:ds.length>2,position:'right',ticks:{color:tc,font:{family:'Montserrat'}},grid:{drawOnChartArea:false},beginAtZero:true}}}});
}

async function loadAlerts(){
  try{
    const r=await fetch(`${API}?action=active_alerts&limit=20`);
    const d=await r.json();
    if(d.success){ renderAlerts(d.alerts); document.getElementById('alertCount').textContent=d.count||d.alerts.length; }
  }catch(e){ console.error('Alerts error:',e); }
}

function renderAlerts(alerts){
  const c=document.getElementById('alertsContainer');
  if(!alerts.length){ c.innerHTML='<div class="empty"><i class="bi bi-check-circle"></i><p>No active alerts — all systems normal</p></div>'; return; }
  c.innerHTML=alerts.map(a=>`<div class="alert-item ${esc(a.alert_level)}">
    <div><div><span class="alert-badge ${esc(a.alert_level)}">${esc(a.alert_level)}</span><strong>${esc(a.device_name)}</strong> <span style="color:var(--text-muted);font-size:11px;">(${esc(a.ip_address)})</span></div>
    <div class="alert-msg">${esc(a.message)}</div>
    <div class="alert-time">${fmtTS(a.created_at)}</div></div>
    <button class="ack-btn" onclick="ackAlert(${a.id})"><i class="bi bi-check2"></i> Ack</button>
  </div>`).join('');
}

async function ackAlert(id){
  try{
    const fd=new FormData(); fd.append('alert_id',id); fd.append('acknowledged_by','Admin');
    const r=await fetch(`${API}?action=acknowledge_alert`,{method:'POST',body:fd});
    const d=await r.json(); if(d.success){ toast('Alert acknowledged','ok'); loadAlerts(); }
  }catch(e){}
}

async function loadLogs(){
  const lv=document.getElementById('logLevel').value, cat=document.getElementById('logCat').value;
  try{
    let url=`${API}?action=system_logs&limit=50`;
    if(lv) url+=`&level=${lv}`; if(cat) url+=`&category=${cat}`;
    const r=await fetch(url); const d=await r.json();
    if(d.success) renderLogs(d.logs);
  }catch(e){}
}

function renderLogs(logs){
  const c=document.getElementById('logsContainer');
  if(!logs.length){ c.innerHTML='<div class="empty" style="padding:20px;"><p>No logs found</p></div>'; return; }
  c.innerHTML=logs.map(l=>`<div class="log-row">
    <span class="log-time">${fmtTS(l.created_at)}</span>
    <span class="log-level ${esc(l.log_level)}">${esc(l.log_level)}</span>
    <span class="log-cat">${esc(l.category||'')}</span>
    <span class="log-msg">${esc(l.message)}</span>
  </div>`).join('');
}

async function exportLogsCSV(){
  const lv=document.getElementById('logLevel').value, cat=document.getElementById('logCat').value;
  try{
    let url=`${API}?action=export_logs_csv&limit=1000`;
    if(lv) url+=`&level=${lv}`; if(cat) url+=`&category=${cat}`;
    const r=await fetch(url); const d=await r.json();
    if(d.success&&d.logs){
      let csv='Timestamp,Level,Category,Device,IP,Message\n';
      d.logs.forEach(l=>{csv+=`"${l.created_at||''}","${l.log_level||''}","${l.category||''}","${(l.device_name||'System').replace(/,/g,';')}","${l.device_ip||'N/A'}","${(l.message||'').replace(/,/g,';').replace(/"/g,'""')}"\n`;});
      const blob=new Blob([csv],{type:'text/csv'}); const a=document.createElement('a');
      a.href=URL.createObjectURL(blob); a.download='logs-'+Date.now()+'.csv'; a.click();
      toast('Logs exported','ok');
    }
  }catch(e){ toast('Export failed','err'); }
}

function exportToPDF(){
  if(!chartData){ toast('No data to export yet','err'); return; }
  const {jsPDF}=window.jspdf; const doc=new jsPDF();
  doc.setFontSize(20); doc.text('System Monitoring Report',14,22);
  doc.setFontSize(11); doc.setTextColor(100);
  doc.text('Generated: '+new Date().toLocaleString(),14,32);
  doc.text('Period: '+currentDateFilter+' | Points: '+chartData.labels.length,14,38);
  doc.autoTable({startY:44,head:[['Time','CPU','RAM','Temp']],
    body:chartData.labels.map((l,i)=>[l,chartData.cpu[i]?chartData.cpu[i].toFixed(1)+'%':'N/A',chartData.ram[i]?chartData.ram[i].toFixed(1)+'%':'N/A',chartData.temperature&&chartData.temperature[i]?chartData.temperature[i].toFixed(1)+'°C':'N/A']),
    theme:'grid',headStyles:{fillColor:[102,126,234],textColor:255,fontStyle:'bold',fontSize:9},bodyStyles:{fontSize:8}});
  doc.save('system-monitoring-'+Date.now()+'.pdf');
  toast('PDF exported','ok');
}

function exportToCSV(){
  if(!chartData){ toast('No data to export yet','err'); return; }
  let csv='Time,CPU (%),RAM (%),Temperature (°C)\n';
  chartData.labels.forEach((l,i)=>{ csv+=`${l},${chartData.cpu[i]||''},${chartData.ram[i]||''},${chartData.temperature&&chartData.temperature[i]||''}\n`; });
  const blob=new Blob([csv],{type:'text/csv'}); const a=document.createElement('a');
  a.href=URL.createObjectURL(blob); a.download='system-monitoring-'+Date.now()+'.csv'; a.click();
  toast('CSV exported','ok');
}

// Monitor control panel
let ctrlMinimized=false;
function toggleCtrl(){ ctrlMinimized=!ctrlMinimized; document.getElementById('ctrlBody').style.display=ctrlMinimized?'none':'block'; }

async function refreshMonitorStatus(){
  try{
    const r=await fetch('api_monitor_control.php?action=status&t='+Date.now());
    const d=await r.json();
    const badge=document.getElementById('monitorBadge'), txt=document.getElementById('monitorText'), btn=document.getElementById('btnStart');
    if(d.running){
      badge.className='status-badge status-running'; txt.textContent='Running';
      btn.disabled=true; btn.innerHTML='<i class="bi bi-check-circle-fill"></i> Running';
    } else {
      badge.className='status-badge status-stopped'; txt.textContent='Stopped';
      btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i> Start Monitor';
    }
    document.getElementById('mPID').textContent=d.pid||'—';
    document.getElementById('mHB').textContent=fmtTime(d.last_heartbeat);
    const age=parseInt(d.heartbeat_age); document.getElementById('mAge').textContent=isNaN(age)?'—':age<60?age+'s ago':age<3600?Math.floor(age/60)+'m ago':Math.floor(age/3600)+'h ago';
    document.getElementById('ctrlUpdated').textContent=new Date().toLocaleTimeString();
  }catch(e){ console.error('Control status error:',e); }
}

async function startMonitor(){
  const btn=document.getElementById('btnStart');
  btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Starting...';
  toast('Starting monitor...','INFO');
  try{
    const r=await fetch('api_monitor_control.php?action=start&t='+Date.now());
    const d=await r.json();
    if(d.success){ toast('Monitor started!','ok'); setTimeout(refreshMonitorStatus,2000); }
    else{ toast('Failed: '+(d.error||'Unknown error'),'err'); btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i> Start Monitor'; }
  }catch(e){ toast('Error: '+e.message,'err'); btn.disabled=false; btn.innerHTML='<i class="bi bi-play-fill"></i> Start Monitor'; }
}

function toast(msg,type='ok'){ const box=document.getElementById('toastBox'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; box.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},4000); }

// Init
loadMetrics();
loadHistorical();
loadAlerts();
loadLogs();
refreshMonitorStatus();
metricsTimer=setInterval(loadMetrics,5000);
autoRefreshTimer=setInterval(refreshMonitorStatus,30000);
</script>
</body>
</html>