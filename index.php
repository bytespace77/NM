<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Network Monitor - SafeG</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>🌐</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
.sidebar-stats{padding:10px 12px;border-top:1px solid var(--border-color);margin-top:auto;}
.sidebar-stats-title{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}
.ss-row{display:flex;align-items:center;justify-content:space-between;padding:4px 6px;border-radius:4px;font-size:11px;margin-bottom:2px;}
.ss-row:hover{background:var(--bg-tertiary);}
.sidebar-devices{border-top:1px solid var(--border-color);overflow-y:auto;max-height:220px;}
.sd-item{display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;font-size:11px;transition:background .15s;border-bottom:1px solid var(--border-color);}
.sd-item:hover{background:var(--bg-tertiary);}
.sd-name{font-weight:600;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sd-ip{font-size:10px;color:var(--text-muted);}
.sd-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.sd-dot.online{background:var(--low);}
.sd-dot.offline{background:var(--critical);}
.main{margin-left:220px;padding:24px;}
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
.btn-accent{background:var(--accent);color:#fff;} .btn-accent:hover{opacity:.85;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);} .btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;}
.sc-blue::after{background:var(--medium);}
.sc-green::after{background:var(--low);}
.sc-red::after{background:var(--critical);}
.sc-purple::after{background:var(--accent);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
.filters-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.filter-btn{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);padding:6px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;}
.filter-btn:hover,.filter-btn.active{border-color:var(--accent);color:var(--accent);}
.filter-btn.online.active{border-color:var(--low);color:var(--low);}
.filter-btn.offline.active{border-color:var(--critical);color:var(--critical);}
.devices-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
.dev-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;border-left:3px solid var(--border-color);transition:all .2s;animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.dev-card.online{border-left-color:var(--low);}
.dev-card.offline{border-left-color:var(--critical);opacity:.7;}
.dev-card.highlight{border-color:var(--accent);box-shadow:0 0 0 2px rgba(102,126,234,.3);}
.dev-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.dev-name{font-size:13px;font-weight:700;margin-bottom:2px;}
.dev-vendor{font-size:10px;color:var(--text-muted);}
.status-badge{padding:3px 8px;border-radius:3px;font-size:10px;font-weight:700;white-space:nowrap;}
.status-badge.online{background:rgba(46,213,115,.12);color:var(--low);}
.status-badge.offline{background:rgba(255,71,87,.12);color:var(--critical);}
.dev-info{display:flex;flex-direction:column;gap:5px;}
.info-row{display:flex;align-items:center;justify-content:space-between;font-size:11px;}
.info-lbl{color:var(--text-muted);font-weight:500;}
.info-val{font-weight:600;font-family:'Montserrat',sans-serif;font-size:11px;}
.empty-state{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty-state i{font-size:48px;display:block;margin-bottom:16px;opacity:.2;}
.scan-bar{position:fixed;bottom:0;left:220px;right:0;background:rgba(102,126,234,.9);color:#fff;padding:10px 20px;display:none;align-items:center;gap:12px;font-size:12px;font-weight:600;z-index:200;backdrop-filter:blur(8px);}
.scan-bar.active{display:flex;}
.spin{width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;}
@keyframes sp{to{transform:rotate(360deg)}}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--low);animation:livepulse 2s ease infinite;}
@keyframes livepulse{0%,100%{opacity:1}50%{opacity:.3}}
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;font-size:12px;animation:tIn .2s ease;font-family:'Montserrat',sans-serif;}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);} .toast.err{border-color:var(--critical);}
::-webkit-scrollbar{width:4px;height:4px;} ::-webkit-scrollbar-track{background:transparent;} ::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>🌐 Network Monitor</h2><p>SafeG Monitoring System</p></div>
  <nav style="padding:8px 0;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item active"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
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
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Network Summary</div>
    <div class="ss-row"><span style="color:var(--text-secondary);font-size:11px;font-weight:500;">Total</span><span id="sbTotal" style="font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--low);font-size:11px;font-weight:500;">Online</span><span id="sbOnline" style="color:var(--low);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--critical);font-size:11px;font-weight:500;">Offline</span><span id="sbOffline" style="color:var(--critical);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--text-muted);font-size:10px;">Last scan</span><span id="sbLastScan" style="color:var(--text-muted);font-size:10px;">—</span></div>
  </div>
  <div class="sidebar-devices" id="sidebarDevices"></div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="page-title">🌐 Network Monitor</div>
      <div class="page-subtitle">Real-time device discovery &amp; status monitoring</div>
    </div>
    <div class="topbar-right">
      <div class="live-dot"></div>
      <button class="btn btn-accent btn-sm" id="scanBtn" onclick="scanNow()"><i class="bi bi-radar"></i> Scan Network</button>
      <button class="btn btn-ghost btn-sm" id="autoScanBtn" onclick="toggleAutoScan()"><i class="bi bi-pause-fill"></i> Pause Auto</button>
      <button class="btn btn-ghost btn-sm" onclick="exportToPDF()"><i class="bi bi-file-pdf"></i> PDF</button>
      <button class="btn btn-ghost btn-sm" onclick="exportToExcel()"><i class="bi bi-file-spreadsheet"></i> Excel</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card sc-blue"><div class="stat-num" id="stTotal" style="color:var(--medium)">—</div><div class="stat-label">Total Devices</div></div>
    <div class="stat-card sc-green"><div class="stat-num" id="stOnline" style="color:var(--low)">—</div><div class="stat-label">Online</div></div>
    <div class="stat-card sc-red"><div class="stat-num" id="stOffline" style="color:var(--critical)">—</div><div class="stat-label">Offline</div></div>
    <div class="stat-card sc-purple"><div class="stat-num" id="stPct" style="color:var(--accent)">—</div><div class="stat-label">Uptime %</div></div>
  </div>

  <div class="filters-bar">
    <button class="filter-btn active" data-filter="all"     onclick="filterDevices('all',this)">All Devices</button>
    <button class="filter-btn online"  data-filter="online"  onclick="filterDevices('online',this)">Online Only</button>
    <button class="filter-btn offline" data-filter="offline" onclick="filterDevices('offline',this)">Offline Only</button>
    <a href="add_device.php"  class="btn btn-ghost btn-sm" style="margin-left:auto;"><i class="bi bi-plus-lg"></i> Add Device</a>
    <a href="networks.php"    class="btn btn-ghost btn-sm"><i class="bi bi-diagram-3"></i> Networks</a>
  </div>

  <div class="devices-grid" id="devicesGrid">
    <div class="empty-state"><i class="bi bi-radar"></i><p>Scanning network...</p></div>
  </div>
</div>

<div class="scan-bar" id="scanBar"><div class="spin"></div> Scanning network for devices...</div>
<div class="toast-box" id="toastBox"></div>

<script>
let allDevices=[], currentFilter='all', autoScanEnabled=true, isScanning=false;
let autoScanTimer=null;

function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('nm_theme',l?'light':'dark'); }
if(localStorage.getItem('nm_theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s){ if(!s) return 'Unknown'; try{ const d=new Date(s),now=new Date(),diff=Math.floor((now-d)/60000); if(diff<1)return 'Just now'; if(diff<60)return diff+'m ago'; if(diff<1440)return Math.floor(diff/60)+'h ago'; return Math.floor(diff/1440)+'d ago'; }catch(e){ return s; } }

function sortDevices(devs){ return [...devs].sort((a,b)=>{ if(a.online&&!b.online)return -1; if(!a.online&&b.online)return 1; return 0; }); }

function updateStats(){
  const total=allDevices.length, online=allDevices.filter(d=>d.online).length, offline=total-online;
  const pct=total>0?Math.round(online/total*100):0;
  document.getElementById('stTotal').textContent=total;
  document.getElementById('stOnline').textContent=online;
  document.getElementById('stOffline').textContent=offline;
  document.getElementById('stPct').textContent=pct+'%';
  document.getElementById('sbTotal').textContent=total;
  document.getElementById('sbOnline').textContent=online;
  document.getElementById('sbOffline').textContent=offline;
}

function updateSidebar(){
  const sd=document.getElementById('sidebarDevices');
  if(!allDevices.length){ sd.innerHTML='<div style="padding:12px;text-align:center;font-size:11px;color:var(--text-muted);">No devices found</div>'; return; }
  sd.innerHTML=allDevices.map(d=>`<div class="sd-item" onclick="scrollToDevice('${esc(d.ip)}')">
    <div class="sd-dot ${d.online?'online':'offline'}"></div>
    <div><div class="sd-name">${esc(d.hostname||d.name)}</div><div class="sd-ip">${esc(d.ip)}</div></div>
  </div>`).join('');
}

function filterDevices(f,el){
  currentFilter=f;
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  if(el) el.classList.add('active');
  renderDevices();
}

function renderDevices(){
  const grid=document.getElementById('devicesGrid');
  let devs=allDevices;
  if(currentFilter==='online') devs=allDevices.filter(d=>d.online);
  if(currentFilter==='offline') devs=allDevices.filter(d=>!d.online);
  if(!devs.length){
    grid.innerHTML=`<div class="empty-state"><i class="bi bi-${currentFilter==='offline'?'wifi-off':'diagram-3'}"></i><p>${currentFilter==='all'?'No devices found':currentFilter==='online'?'No online devices':'No offline devices'}</p></div>`;
    return;
  }
  grid.innerHTML=devs.map((d,i)=>`<div class="dev-card ${d.online?'online':'offline'}" id="device-${esc(d.ip)}" style="animation-delay:${i*.04}s">
    <div class="dev-head">
      <div><div class="dev-name">${d.icon||'🖥️'} ${esc(d.hostname||d.name)}</div><div class="dev-vendor">${esc(d.vendor||'Unknown vendor')}</div></div>
      <span class="status-badge ${d.online?'online':'offline'}">${d.online?'● ONLINE':'○ OFFLINE'}</span>
    </div>
    <div class="dev-info">
      <div class="info-row"><span class="info-lbl">IP Address</span><span class="info-val">${esc(d.ip)}</span></div>
      <div class="info-row"><span class="info-lbl">MAC Address</span><span class="info-val">${esc(d.mac||'—')}</span></div>
      <div class="info-row"><span class="info-lbl">Device Type</span><span class="info-val">${esc(d.type||'—')}</span></div>
      <div class="info-row"><span class="info-lbl">Last Active</span><span class="info-val">${d.online?'Now':fmt(d.last_seen)}</span></div>
    </div>
  </div>`).join('');
}

function scrollToDevice(ip){
  const el=document.getElementById('device-'+ip); if(!el) return;
  el.scrollIntoView({behavior:'smooth',block:'center'});
  el.classList.add('highlight'); setTimeout(()=>el.classList.remove('highlight'),2000);
  document.querySelectorAll('.sd-item').forEach(s=>s.style.background='');
}

async function scanNow(){
  if(isScanning) return;
  isScanning=true;
  document.getElementById('scanBtn').disabled=true;
  document.getElementById('scanBar').classList.add('active');
  try{
    const r=await fetch('api_scan_real.php'); if(!r.ok) throw new Error('HTTP '+r.status);
    const d=await r.json(); if(!d.success) throw new Error(d.error||'Scan failed');
    allDevices=sortDevices(d.devices||[]);
    updateStats(); updateSidebar(); renderDevices();
    document.getElementById('sbLastScan').textContent=new Date().toLocaleTimeString('en-MY');
    toast('Scan complete — '+allDevices.length+' devices','ok');
  }catch(err){
    toast('Scan error: '+err.message,'err');
    document.getElementById('devicesGrid').innerHTML=`<div class="empty-state"><i class="bi bi-exclamation-circle"></i><p>${esc(err.message)}</p><button class="btn btn-ghost btn-sm" onclick="scanNow()" style="margin-top:12px;">Retry</button></div>`;
  }finally{
    isScanning=false;
    document.getElementById('scanBtn').disabled=false;
    document.getElementById('scanBar').classList.remove('active');
  }
}

function toggleAutoScan(){
  autoScanEnabled=!autoScanEnabled;
  const btn=document.getElementById('autoScanBtn');
  if(autoScanEnabled){
    btn.innerHTML='<i class="bi bi-pause-fill"></i> Pause Auto';
    startAutoScan();
  } else {
    btn.innerHTML='<i class="bi bi-play-fill"></i> Resume Auto';
    clearInterval(autoScanTimer);
  }
}

function startAutoScan(){ clearInterval(autoScanTimer); if(autoScanEnabled) autoScanTimer=setInterval(scanNow,30000); }

function exportToPDF(){
  if(!allDevices.length){ toast('No devices to export','err'); return; }
  const {jsPDF}=window.jspdf; const doc=new jsPDF();
  doc.setFontSize(20); doc.text('Network Monitor Report',14,22);
  doc.setFontSize(11); doc.setTextColor(100);
  doc.text('Generated: '+new Date().toLocaleString(),14,32);
  doc.text('Total Devices: '+allDevices.length,14,38);
  doc.autoTable({startY:44,head:[['Name','IP','MAC','Vendor','Type','Status','Last Seen']],
    body:allDevices.map(d=>[d.hostname||d.name,d.ip,d.mac||'—',d.vendor||'—',d.type||'—',d.online?'ONLINE':'OFFLINE',d.online?'Now':fmt(d.last_seen)]),
    theme:'grid',headStyles:{fillColor:[102,126,234],textColor:255,fontStyle:'bold',fontSize:9},bodyStyles:{fontSize:8},
    didParseCell:function(data){ if(data.column.index===5){ data.cell.styles.textColor=data.cell.raw==='ONLINE'?[16,185,129]:[239,68,68]; data.cell.styles.fontStyle='bold'; } }
  });
  doc.save('network-report-'+Date.now()+'.pdf');
  toast('PDF exported','ok');
}

function exportToExcel(){
  if(!allDevices.length){ toast('No devices to export','err'); return; }
  const ws=XLSX.utils.json_to_sheet(allDevices.map(d=>({'Name':d.hostname||d.name,'IP':d.ip,'MAC':d.mac||'—','Vendor':d.vendor||'—','Type':d.type||'—','Status':d.online?'ONLINE':'OFFLINE','Last Seen':d.online?'Now':fmt(d.last_seen)})));
  const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Devices');
  XLSX.writeFile(wb,'network-report-'+Date.now()+'.xlsx');
  toast('Excel exported','ok');
}

function toast(msg,type='ok'){ const box=document.getElementById('toastBox'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; box.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3000); }

scanNow();
startAutoScan();
</script>
</body>
</html>