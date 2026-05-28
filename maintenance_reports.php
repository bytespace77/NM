<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Reports - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>📋</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
.sc-purple::after{background:var(--accent);}
.sc-blue::after{background:var(--medium);}
.sc-green::after{background:var(--low);}
.sc-orange::after{background:var(--high);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
.filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp{flex:1;min-width:200px;} .filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
.risk-btn{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);padding:6px 12px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;}
.risk-btn:hover,.risk-btn.active{border-color:var(--accent);color:var(--accent);}
/* Report cards */
.reports-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;}
.rep-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;border-left:3px solid var(--accent);transition:all .2s;cursor:pointer;position:relative;}
.rep-card:hover{border-color:var(--border-hover);transform:translateY(-1px);}
.rep-card.ai{border-left-color:var(--accent);}
.rep-card.manual{border-left-color:var(--low);}
.rep-card.is-new{border-color:rgba(46,213,115,.35);box-shadow:0 0 0 1px rgba(46,213,115,.15);}
.new-badge{position:absolute;top:-1px;right:12px;background:var(--low);color:#000;font-size:9px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;padding:3px 8px;border-radius:0 0 6px 6px;display:flex;align-items:center;gap:4px;cursor:pointer;transition:all .2s;user-select:none;}
.new-badge:hover{background:#27d96a;padding-bottom:5px;}
.new-badge i{font-size:9px;}
.rep-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;}
.rep-num{font-size:13px;font-weight:800;}
.rep-device{font-size:11px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;margin-top:2px;}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;}
.badge-ai{background:rgba(102,126,234,.12);color:var(--accent);}
.badge-manual{background:rgba(46,213,115,.12);color:var(--low);}
.rep-title{font-size:12px;color:var(--text-secondary);margin:6px 0 10px;line-height:1.4;}
.rep-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;}
.meta-item{background:var(--bg-tertiary);border-radius:4px;padding:6px 8px;}
.meta-lbl{font-size:9px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;}
.meta-val{font-size:12px;font-weight:700;margin-top:1px;}
.rep-actions{display:flex;gap:6px;padding-top:10px;border-top:1px solid var(--border-color);}
.rep-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);color:var(--text-secondary);background:var(--bg-tertiary);transition:all .15s;}
.rep-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.rep-btn.dl{border-color:rgba(102,126,234,.3);color:var(--accent);}
.rep-btn.dl:hover{background:rgba(102,126,234,.08);}
.rep-btn.danger{border-color:rgba(255,71,87,.3);color:var(--critical);}
.rep-btn.danger:hover{background:rgba(255,71,87,.08);}
/* Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.mc{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;}
.mh{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);}
.mh h2{font-size:15px;font-weight:700;}
.mc-close{background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;}
.mb{padding:20px;} .mf{padding:12px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:8px;}
.fg{margin-bottom:14px;} .fg label{display:block;font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.fc{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:8px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.fc:focus{outline:none;border-color:var(--accent);}
.empty{text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
.count-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.count-label{font-size:11px;color:var(--text-secondary);}
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;font-size:12px;animation:tIn .2s ease;font-family:'Montserrat',sans-serif;}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);} .toast.err{border-color:var(--critical);}
::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:transparent;} ::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>🌐 Network Monitor</h2><p>SafeG Monitoring System</p></div>
  <nav style="padding:8px 0;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
    <a href="ai_dashboard.php"            class="nav-item"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item active"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Summary</div>
    <div class="ss-row"><span style="color:var(--text-secondary);font-size:11px;font-weight:500;">Total</span><span id="sb-total" style="font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--accent);font-size:11px;font-weight:500;">AI Reports</span><span id="sb-ai" style="color:var(--accent);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--low);font-size:11px;font-weight:500;">Manual</span><span id="sb-manual" style="color:var(--low);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--high);font-size:11px;font-weight:500;">This Week</span><span id="sb-recent" style="color:var(--high);font-weight:800;font-size:11px;">—</span></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="page-title">📋 Maintenance Reports</div>
      <div class="page-subtitle">AI prediction reports and maintenance documentation</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent btn-sm" onclick="showCreateModal()"><i class="bi bi-plus-lg"></i> New Report</button>
      <button class="btn btn-ghost btn-sm" onclick="loadReports()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card sc-purple"><div class="stat-num" id="st-total" style="color:var(--accent)">—</div><div class="stat-label">Total Reports</div></div>
    <div class="stat-card sc-blue"><div class="stat-num" id="st-ai" style="color:var(--accent)">—</div><div class="stat-label">AI Predictions</div></div>
    <div class="stat-card sc-green"><div class="stat-num" id="st-manual" style="color:var(--low)">—</div><div class="stat-label">Maintenance</div></div>
    <div class="stat-card sc-orange"><div class="stat-num" id="st-recent" style="color:var(--high)">—</div><div class="stat-label">This Week</div></div>
  </div>

  <div class="filters">
    <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search report, device..." oninput="applyFilters()">
    <select class="filter-sel" id="typeFilter" onchange="applyFilters()">
      <option value="">All Types</option>
      <option value="ai_prediction">AI Prediction</option>
      <option value="scheduled_maintenance">Maintenance</option>
    </select>
    <select class="filter-sel" id="equipmentFilter" onchange="applyFilters()">
      <option value="">All Equipment</option>
      <option value="HARDWARE">Hardware</option>
      <option value="TOWER">Tower</option>
      <option value="RADAR">Radar</option>
      <option value="DATABASE">Database</option>
    </select>
    <button class="risk-btn active" data-t="ALL"               onclick="setType('ALL',this)">All</button>
    <button class="risk-btn"        data-t="ai_prediction"      onclick="setType('ai_prediction',this)">AI</button>
    <button class="risk-btn"        data-t="scheduled_maintenance" onclick="setType('scheduled_maintenance',this)">Manual</button>
  </div>

  <div class="count-bar">
    <span class="count-label" id="countLabel">Loading...</span>
  </div>

  <div id="loadingState"><div class="loader"></div></div>
  <div id="reportsGrid" class="reports-grid" style="display:none;"></div>
</div>

<!-- Create Report Modal -->
<div id="createModal" class="modal">
  <div class="mc">
    <div class="mh"><h2><i class="bi bi-file-earmark-plus"></i> New Report</h2><button class="mc-close" onclick="closeModal('createModal')">&times;</button></div>
    <div class="mb">
      <div class="fg"><label>Device *</label><select id="repDevice" class="fc"><option value="">Select device...</option></select></div>
      <div class="fg"><label>Report Title *</label><input type="text" id="repTitle" class="fc" placeholder="e.g. Q1 Predictive Maintenance"></div>
      <div class="fg"><label>Report Type</label>
        <select id="repType" class="fc"><option value="ai_prediction">AI Prediction</option><option value="scheduled_maintenance">Scheduled Maintenance</option></select>
      </div>
      <div class="fg"><label>Equipment Type</label>
        <select id="repEquipment" class="fc"><option value="1">Hardware</option><option value="2">Tower</option><option value="3">Radar</option><option value="4">Database</option></select>
      </div>
      <div class="fg"><label>Description *</label><textarea id="repDesc" class="fc" rows="3" placeholder="Describe the issue or maintenance activity..."></textarea></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('createModal')">Cancel</button>
      <button class="btn btn-accent btn-sm" onclick="createReport()"><i class="bi bi-file-earmark-check"></i> Generate</button>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allReports=[], filteredReports=[], allDevices=[], typeFilter='ALL';

function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('mr_theme',l?'light':'dark'); }
if(localStorage.getItem('mr_theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s){ if(!s)return '—'; try{ return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); }catch(e){ return s; } }

function setType(t,el){ typeFilter=t; document.querySelectorAll('[data-t]').forEach(b=>b.classList.toggle('active',b.dataset.t===t)); applyFilters(); }

function updateStats(){
  const week=new Date(); week.setDate(week.getDate()-7);
  const ai=allReports.filter(r=>r.report_type==='ai_prediction').length;
  const manual=allReports.filter(r=>r.report_type!=='ai_prediction').length;
  const recent=allReports.filter(r=>new Date(r.generated_at||r.created_at)>week).length;
  document.getElementById('st-total').textContent=allReports.length;
  document.getElementById('st-ai').textContent=ai;
  document.getElementById('st-manual').textContent=manual;
  document.getElementById('st-recent').textContent=recent;
  document.getElementById('sb-total').textContent=allReports.length;
  document.getElementById('sb-ai').textContent=ai;
  document.getElementById('sb-manual').textContent=manual;
  document.getElementById('sb-recent').textContent=recent;
}

function applyFilters(){
  const q=document.getElementById('searchInput').value.toLowerCase();
  const tf=document.getElementById('typeFilter').value;
  const ef=document.getElementById('equipmentFilter').value;
  filteredReports=allReports.filter(r=>{
    const mQ=!q||(r.report_number||'').toLowerCase().includes(q)||(r.report_title||r.title||'').toLowerCase().includes(q)||(r.device_name||'').toLowerCase().includes(q);
    const mT=(typeFilter==='ALL'||r.report_type===typeFilter)&&(!tf||r.report_type===tf);
    const mE=!ef||(r.equipment_type_code||r.equipment_type||'')===ef;
    return mQ&&mT&&mE;
  });
  filteredReports.sort((a,b)=>new Date(b.generated_at||b.created_at)-new Date(a.generated_at||a.created_at));
  renderReports();
}

// ── NEW badge helpers ─────────────────────────────────────────────────────
const SEEN_KEY = 'mr_seen_reports';
function getSeenIds() {
  try { return new Set(JSON.parse(localStorage.getItem(SEEN_KEY)||'[]')); }
  catch(e) { return new Set(); }
}
function markSeen(reportId) {
  const seen = getSeenIds();
  seen.add(String(reportId));
  localStorage.setItem(SEEN_KEY, JSON.stringify([...seen]));
}
function isNewReport(r) {
  if (getSeenIds().has(String(r.id))) return false;
  // Consider "new" if generated within last 48h
  const generated = new Date(r.generated_at || r.created_at);
  const ageHours = (Date.now() - generated.getTime()) / 3600000;
  return ageHours <= 48;
}
function dismissNew(reportId, event) {
  event.stopPropagation();
  markSeen(reportId);
  const card = document.getElementById('rep-'+reportId);
  if (card) {
    card.classList.remove('is-new');
    const badge = card.querySelector('.new-badge');
    if (badge) { badge.style.opacity='0'; badge.style.transform='translateY(-4px)'; setTimeout(()=>badge.remove(), 200); }
  }
}
// ─────────────────────────────────────────────────────────────────────────

function renderReports(){
  const grid=document.getElementById('reportsGrid');
  document.getElementById('countLabel').textContent=filteredReports.length+' report'+(filteredReports.length!==1?'s':'');
  if(!filteredReports.length){ grid.innerHTML='<div class="empty"><i class="bi bi-file-earmark-x"></i><p>No reports found</p></div>'; return; }
  grid.innerHTML=filteredReports.map(r=>{
    const isAI=r.report_type==='ai_prediction';
    const tc=isAI?'ai':'manual';
    const title=r.report_title||r.title||'Untitled Report';
    const num=r.report_number||r.id;
    const equip=r.equipment_type_code||r.equipment_type||'—';
    const conf=parseFloat(r.confidence_score||0);
    const cond=r.overall_condition||'';
    const condColor=cond==='good'?'var(--low)':cond==='fair'?'var(--high)':'var(--critical)';
    const isNew = isNewReport(r);
    return `<div class="rep-card ${tc}${isNew?' is-new':''}" id="rep-${r.id}" onclick="void(0)">
      ${isNew ? `<div class="new-badge" onclick="dismissNew(${r.id},event)" title="Click to dismiss"><i class="bi bi-stars"></i> NEW</div>` : ''}
      <div class="rep-head">
        <div>
          <div class="rep-num">${esc(String(num))}</div>
          <div class="rep-device"><i class="bi bi-hdd-network"></i> ${esc(r.device_name||'Unknown')}</div>
        </div>
        <span class="badge badge-${tc}">${isAI?'AI Prediction':'Maintenance'}</span>
      </div>
      <div class="rep-title">${esc(title)}</div>
      <div class="rep-meta">
        <div class="meta-item"><div class="meta-lbl">Equipment</div><div class="meta-val">${esc(equip)}</div></div>
        <div class="meta-item"><div class="meta-lbl">Generated</div><div class="meta-val">${fmt(r.generated_at||r.created_at)}</div></div>
        ${cond?`<div class="meta-item"><div class="meta-lbl">Condition</div><div class="meta-val" style="color:${condColor}">${cond.toUpperCase()}</div></div>`:''}
        ${isAI&&conf>0?`<div class="meta-item"><div class="meta-lbl">Confidence</div><div class="meta-val" style="color:${conf>80?'var(--low)':conf>60?'var(--high)':'var(--critical)'}">${conf.toFixed(0)}%</div></div>`:''}
      </div>
      <div class="rep-actions">
        <button class="rep-btn dl" onclick="event.stopPropagation();downloadReport(${r.id},'pdf')"><i class="bi bi-file-pdf-fill"></i> PDF</button>
        <button class="rep-btn danger" onclick="event.stopPropagation();deleteReport(${r.id})"><i class="bi bi-trash-fill"></i> Delete</button>
      </div>
    </div>`;
  }).join('');
}

async function loadDevices(){ try{ const r=await fetch('api_ai_predictions.php?action=get_health_metrics'); const d=await r.json(); if(d.success) allDevices=d.data||[]; }catch(e){} }

async function loadReports(){
  document.getElementById('loadingState').style.display='block';
  document.getElementById('reportsGrid').style.display='none';
  try{
    const r=await fetch('api_report_list.php?action=list'); const d=await r.json();
    if(d.success){
      allReports=d.reports||d.data||[]; updateStats(); applyFilters();
      document.getElementById('loadingState').style.display='none';
      document.getElementById('reportsGrid').style.display='grid';
    } else throw new Error(d.error||'Failed');
  }catch(err){
    document.getElementById('loadingState').innerHTML=`<div class="empty"><i class="bi bi-exclamation-circle"></i><p>${esc(err.message)}</p><button class="btn btn-ghost btn-sm" onclick="loadReports()" style="margin-top:12px;">Retry</button></div>`;
  }
}

function showCreateModal(){
  document.getElementById('repDevice').innerHTML='<option value="">Select device...</option>'+allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} (${esc(d.ip_address||d.device_ip||'')})</option>`).join('');
  document.getElementById('createModal').classList.add('show');
}
function closeModal(id){ document.getElementById(id).classList.remove('show'); }

async function createReport(force = false){
  const did=document.getElementById('repDevice').value, title=document.getElementById('repTitle').value, desc=document.getElementById('repDesc').value;
  if(!did||!title||!desc){ toast('Please fill required fields','err'); return; }
  if (!force) closeModal('createModal');
  try{
    const payload = {
      report_type: document.getElementById('repType').value,
      device_id: did,
      equipment_type_id: document.getElementById('repEquipment').value,
      predicted_issue: desc,
      confidence_score: 0.95,
      title: title,
      force: force
    };
    const r=await fetch('api_generate_report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const text = await r.text();
    let d;
    try { d = JSON.parse(text); } catch(e) { toast('Server error: '+text.substring(0,100),'err'); return; }
    if(d.success){
      if(d.duplicate){
        // Ask user if they want to generate a new one anyway
        if(confirm(`⚠️ A report for this device was already generated today (${d.report_number}).\n\nGenerate another one anyway?`)){
          await createReport(true); // re-call with force=true
        } else {
          toast(`Using existing report: ${d.report_number}`,'ok');
          loadReports();
        }
      } else {
        toast('Report generated!','ok');
        document.getElementById('repTitle').value='';
        document.getElementById('repDesc').value='';
        loadReports();
      }
    }
    else toast(d.error||'Failed to generate','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function downloadReport(id,format){
  toast(`Preparing ${format.toUpperCase()}...`,'ok');
  markSeen(id); // auto-dismiss NEW badge when downloaded
  try{
    const r=await fetch(`api_report_list.php?action=download&id=${id}&format=${format}`);
    if(r.ok){ const blob=await r.blob(); const url=URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download=`report_${id}.${format}`; document.body.appendChild(a); a.click(); URL.revokeObjectURL(url); a.remove(); toast('Download started','ok');
      // Remove badge visually if still showing
      const card=document.getElementById('rep-'+id);
      if(card){ card.classList.remove('is-new'); const b=card.querySelector('.new-badge'); if(b) b.remove(); }
    }
    else throw new Error('Download failed');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function deleteReport(id){
  if(!confirm('Delete this report?')) return;
  try{
    const r=await fetch(`api_report_list.php?action=delete&id=${id}`,{method:'DELETE'});
    const d=await r.json();
    if(d.success){ toast('Report deleted','ok'); loadReports(); }
    else toast(d.error||'Failed','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

function toast(msg,type='ok'){ const box=document.getElementById('toastBox'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; box.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3000); }

loadDevices(); loadReports();
</script>
</body>
</html>