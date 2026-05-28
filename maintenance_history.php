<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance History - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>🔧</text></svg>">
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
.btn-accent{background:var(--accent);color:#fff;}
.btn-accent:hover{opacity:.85;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);}
.btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;}
.sc-green::after{background:var(--low);}
.sc-blue::after{background:var(--medium);}
.sc-orange::after{background:var(--high);}
.sc-purple::after{background:var(--accent);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
.filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp{flex:1;min-width:200px;}
.filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
.risk-btn{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);padding:6px 12px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;}
.risk-btn:hover,.risk-btn.active{border-color:var(--accent);color:var(--accent);}
.risk-btn.rc-green.active{border-color:var(--low);color:var(--low);}
.risk-btn.rc-orange.active{border-color:var(--high);color:var(--high);}
.risk-btn.rc-red.active{border-color:var(--critical);color:var(--critical);}
/* Timeline */
.timeline{display:flex;flex-direction:column;gap:10px;}
.tl-item{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;border-left:3px solid var(--border-color);transition:border-color .15s;}
.tl-item:hover{border-color:var(--border-hover);}
.tl-item.completed{border-left-color:var(--low);}
.tl-item.in-progress,.tl-item.in_progress{border-left-color:var(--high);}
.tl-item.pending{border-left-color:var(--medium);}
.tl-item.failed{border-left-color:var(--critical);}
.tl-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.tl-task{font-size:13px;font-weight:700;margin-bottom:3px;}
.tl-device{font-size:11px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;}
.badge-completed{background:rgba(46,213,115,.12);color:var(--low);}
.badge-in-progress,.badge-in_progress{background:rgba(255,165,2,.12);color:var(--high);}
.badge-pending{background:rgba(55,66,250,.12);color:var(--medium);}
.badge-failed{background:rgba(255,71,87,.12);color:var(--critical);}
.tl-meta{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:8px;}
.tl-meta-item{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-secondary);}
.tl-desc{font-size:12px;color:var(--text-secondary);line-height:1.5;margin-bottom:8px;padding:8px 10px;background:var(--bg-tertiary);border-radius:5px;}
.parts-row{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;}
.part-tag{padding:2px 8px;background:rgba(102,126,234,.1);border:1px solid rgba(102,126,234,.2);color:var(--accent);border-radius:3px;font-size:10px;font-weight:600;}
.tl-actions{display:flex;gap:6px;padding-top:8px;border-top:1px solid var(--border-color);}
.tl-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);color:var(--text-secondary);background:var(--bg-tertiary);transition:all .15s;}
.tl-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.tl-btn.danger{border-color:rgba(255,71,87,.3);color:var(--critical);}
.tl-btn.danger:hover{background:rgba(255,71,87,.08);}
/* Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;}
.modal.show{display:flex;}
.mc{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;}
.mh{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);}
.mh h2{font-size:15px;font-weight:700;}
.mc-close{background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;}
.mb{padding:20px;}
.mf{padding:12px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:8px;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.fc{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:8px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.fc:focus{outline:none;border-color:var(--accent);}
/* Empty/loader */
.empty{text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
.count-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.count-label{font-size:11px;color:var(--text-secondary);}
.toast-box{position:fixed;bottom:18px;left:240px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:10px 14px;font-size:12px;animation:tIn .2s ease;font-family:'Montserrat',sans-serif;}
@keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.toast.ok{border-color:var(--low);}
.toast.err{border-color:var(--critical);}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
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
    <a href="maintenance_history.php"     class="nav-item active"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Summary</div>
    <div class="ss-row"><span style="color:var(--text-secondary);font-size:11px;font-weight:500;">Total Records</span><span id="sb-total" style="font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--low);font-size:11px;font-weight:500;">Completed</span><span id="sb-completed" style="color:var(--low);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--high);font-size:11px;font-weight:500;">In Progress</span><span id="sb-inprog" style="color:var(--high);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--accent);font-size:11px;font-weight:500;">Total Hours</span><span id="sb-hours" style="color:var(--accent);font-weight:800;font-size:11px;">—</span></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="page-title">🔧 Maintenance History</div>
      <div class="page-subtitle">Complete log of all maintenance activities</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent btn-sm" onclick="showAddModal()"><i class="bi bi-plus-lg"></i> Add Record</button>
      <button class="btn btn-ghost btn-sm" onclick="loadHistory()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card sc-green"><div class="stat-num" id="st-total" style="color:var(--low)">—</div><div class="stat-label">Total Records</div></div>
    <div class="stat-card sc-blue"><div class="stat-num" id="st-completed" style="color:var(--low)">—</div><div class="stat-label">Completed</div></div>
    <div class="stat-card sc-orange"><div class="stat-num" id="st-inprog" style="color:var(--high)">—</div><div class="stat-label">In Progress</div></div>
    <div class="stat-card sc-purple"><div class="stat-num" id="st-hours" style="color:var(--accent)">—</div><div class="stat-label">Total Hours</div></div>
  </div>

  <div class="filters">
    <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search task, device..." oninput="applyFilters()">
    <select class="filter-sel" id="networkFilter" onchange="loadHistory()"><option value="">All Networks</option></select>
    <select class="filter-sel" id="categoryFilter" onchange="applyFilters()">
      <option value="">All Categories</option>
      <option value="preventive">Preventive</option>
      <option value="corrective">Corrective</option>
      <option value="emergency">Emergency</option>
      <option value="inspection">Inspection</option>
      <option value="general">General</option>
    </select>
    <button class="risk-btn active" data-s="ALL"         onclick="setStatus('ALL',this)">All</button>
    <button class="risk-btn rc-green"  data-s="completed"  onclick="setStatus('completed',this)">Completed</button>
    <button class="risk-btn rc-orange" data-s="in_progress" onclick="setStatus('in_progress',this)">In Progress</button>
    <button class="risk-btn rc-red"    data-s="failed"      onclick="setStatus('failed',this)">Failed</button>
  </div>

  <div class="count-bar">
    <span class="count-label" id="countLabel">Loading...</span>
  </div>

  <div id="loadingState"><div class="loader"></div></div>
  <div id="timeline" class="timeline" style="display:none;"></div>
</div>

<!-- Add Record Modal -->
<div id="addModal" class="modal">
  <div class="mc">
    <div class="mh"><h2><i class="bi bi-plus-lg"></i> Add Maintenance Record</h2><button class="mc-close" onclick="closeModal('addModal')">&times;</button></div>
    <div class="mb">
      <div class="fg"><label>Device *</label><select id="rDevice" class="fc"><option value="">Select device...</option></select></div>
      <div class="fg"><label>Task Name *</label><input type="text" id="rTask" class="fc" placeholder="e.g. Cable replacement"></div>
      <div class="fg"><label>Category</label>
        <select id="rCategory" class="fc">
          <option value="general">General</option><option value="preventive">Preventive</option>
          <option value="corrective">Corrective</option><option value="emergency">Emergency</option><option value="inspection">Inspection</option>
        </select>
      </div>
      <div class="fg"><label>Result</label>
        <select id="rStatus" class="fc"><option value="success">Success</option><option value="partial">Partial</option><option value="failed">Failed</option></select>
      </div>
      <div class="fg"><label>Performed By</label><input type="text" id="rPerformedBy" class="fc" placeholder="Technician name"></div>
      <div class="fg"><label>Duration (minutes)</label><input type="number" id="rDuration" class="fc" placeholder="60"></div>
      <div class="fg"><label>Description</label><textarea id="rDescription" class="fc" rows="3" placeholder="What was done..."></textarea></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('addModal')">Cancel</button>
      <button class="btn btn-accent btn-sm" onclick="addRecord()"><i class="bi bi-check-lg"></i> Add Record</button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="mc">
    <div class="mh"><h2><i class="bi bi-pencil-fill"></i> Edit Record</h2><button class="mc-close" onclick="closeModal('editModal')">&times;</button></div>
    <div class="mb">
      <input type="hidden" id="editId">
      <div class="fg"><label>Status</label>
        <select id="editStatus" class="fc"><option value="completed">Completed</option><option value="in_progress">In Progress</option><option value="pending">Pending</option><option value="failed">Failed</option></select>
      </div>
      <div class="fg"><label>Description</label><textarea id="editDesc" class="fc" rows="3"></textarea></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('editModal')">Cancel</button>
      <button class="btn btn-accent btn-sm" onclick="updateRecord()"><i class="bi bi-check-lg"></i> Save</button>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allRecords=[], filteredRecords=[], allDevices=[], statusFilter='ALL';

function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('mh_theme',l?'light':'dark'); }
if(localStorage.getItem('mh_theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s){ if(!s)return '—'; try{ return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}); }catch(e){ return s; } }
function fmtDate(s){ if(!s)return '—'; try{ return new Date(s).toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'}); }catch(e){ return s; } }

function setStatus(s,el){ statusFilter=s; document.querySelectorAll('[data-s]').forEach(b=>b.classList.toggle('active',b.dataset.s===s)); applyFilters(); }

function applyFilters(){
  const q=document.getElementById('searchInput').value.toLowerCase();
  const cat=document.getElementById('categoryFilter').value;
  filteredRecords=allRecords.filter(r=>{
    const mQ=!q||(r.task_name||'').toLowerCase().includes(q)||(r.device_name||'').toLowerCase().includes(q)||(r.description||'').toLowerCase().includes(q);
    const mS=statusFilter==='ALL'||r.status===statusFilter;
    const mC=!cat||r.task_category===cat;
    return mQ&&mS&&mC;
  });
  renderTimeline();
  updateStats();
}

function updateStats(){
  const total=allRecords.length, comp=allRecords.filter(r=>r.status==='completed').length;
  const inp=allRecords.filter(r=>r.status==='in_progress').length;
  const hrs=Math.round(allRecords.reduce((s,r)=>s+(parseInt(r.duration_minutes)||0),0)/60);
  ['total','completed','inprog','hours'].forEach(k=>{ const el=document.getElementById('st-'+k); if(el) el.textContent=k==='total'?total:k==='completed'?comp:k==='inprog'?inp:hrs+'h'; });
  ['total','completed','inprog','hours'].forEach(k=>{ const el=document.getElementById('sb-'+k); if(el) el.textContent=k==='total'?total:k==='completed'?comp:k==='inprog'?inp:hrs+'h'; });
}

function populateNetworkFilter(){
  const sel=document.getElementById('networkFilter'), cur=sel.value;
  const nets=[...new Set(allRecords.map(r=>r.network_range).filter(n=>n))];
  sel.innerHTML='<option value="">All Networks</option>'+nets.map(n=>`<option value="${esc(n)}">${esc(n)} (${allRecords.filter(r=>r.network_range===n).length})</option>`).join('');
  sel.value=cur;
}

function renderTimeline(){
  const tl=document.getElementById('timeline');
  document.getElementById('countLabel').textContent=filteredRecords.length+' record'+(filteredRecords.length!==1?'s':'');
  if(!filteredRecords.length){ tl.innerHTML='<div class="empty"><i class="bi bi-clock-history"></i><p>No maintenance records found</p></div>'; return; }
  tl.innerHTML=filteredRecords.map(r=>{
    const parts=Array.isArray(r.parts_replaced)?r.parts_replaced:(r.parts_replaced?JSON.parse(r.parts_replaced):[]);
    const sc=r.status.replace('_','-');
    return `<div class="tl-item ${sc}">
      <div class="tl-head">
        <div>
          <div class="tl-task">${esc(r.task_name)}</div>
          <div class="tl-device"><i class="bi bi-hdd-network"></i> ${esc(r.device_name||'Unknown')} · ${esc(r.ip_address||'—')}</div>
        </div>
        <span class="badge badge-${sc}">${r.status.replace('_',' ')}</span>
      </div>
      <div class="tl-meta">
        <div class="tl-meta-item"><i class="bi bi-tag"></i>${esc(r.task_category||'—')}</div>
        <div class="tl-meta-item"><i class="bi bi-calendar"></i>${fmt(r.completed_at)}</div>
        ${r.performed_by?`<div class="tl-meta-item"><i class="bi bi-person"></i>${esc(r.performed_by)}</div>`:''}
        ${r.duration_minutes?`<div class="tl-meta-item"><i class="bi bi-clock"></i>${r.duration_minutes} min</div>`:''}
        ${r.cost?`<div class="tl-meta-item"><i class="bi bi-currency-dollar"></i>${parseFloat(r.cost).toFixed(2)}</div>`:''}
        ${r.network_range?`<div class="tl-meta-item"><i class="bi bi-wifi"></i>${esc(r.network_range)}</div>`:''}
      </div>
      ${r.description?`<div class="tl-desc">${esc(r.description)}</div>`:''}
      ${parts.length?`<div class="parts-row">${parts.map(p=>`<span class="part-tag">${esc(p)}</span>`).join('')}</div>`:''}
      ${r.location_address?`<div class="tl-meta-item" style="margin-bottom:6px;font-size:11px;color:var(--text-secondary);"><i class="bi bi-geo-alt-fill"></i> ${esc(r.location_address)}</div>`:''}
      <div class="tl-actions">
        <button class="tl-btn" onclick="editRecord(${r.id})"><i class="bi bi-pencil-fill"></i> Edit</button>
        <button class="tl-btn danger" onclick="deleteRecord(${r.id})"><i class="bi bi-trash-fill"></i> Delete</button>
      </div>
    </div>`;
  }).join('');
}

async function loadDevices(){
  try{ const r=await fetch('api_ai_predictions.php?action=get_health_metrics'); const d=await r.json(); if(d.success) allDevices=d.data||[]; }catch(e){}
}

async function loadHistory(){
  document.getElementById('loadingState').style.display='block';
  document.getElementById('timeline').style.display='none';
  const net=document.getElementById('networkFilter').value;
  try{
    let url='api_maintenance_history.php?action=list&limit=100';
    if(net) url+=`&network_range=${encodeURIComponent(net)}`;
    const r=await fetch(url); const d=await r.json();
    if(d.success){
      allRecords=d.data||[];
      populateNetworkFilter(); applyFilters();
      document.getElementById('loadingState').style.display='none';
      document.getElementById('timeline').style.display='flex';
    } else throw new Error(d.error||'Failed to load');
  }catch(err){
    document.getElementById('loadingState').innerHTML=`<div class="empty"><i class="bi bi-exclamation-circle"></i><p>${esc(err.message)}</p><button class="btn btn-ghost btn-sm" onclick="loadHistory()" style="margin-top:12px;">Retry</button></div>`;
  }
}

function showAddModal(){
  document.getElementById('rDevice').innerHTML='<option value="">Select device...</option>'+allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} (${esc(d.ip_address||d.device_ip||'')})</option>`).join('');
  document.getElementById('addModal').classList.add('show');
}
function editRecord(id){
  const r=allRecords.find(x=>x.id===id); if(!r) return;
  document.getElementById('editId').value=id;
  document.getElementById('editStatus').value=r.status;
  document.getElementById('editDesc').value=r.description||'';
  document.getElementById('editModal').classList.add('show');
}
function closeModal(id){ document.getElementById(id).classList.remove('show'); }

async function addRecord(){
  const deviceId=document.getElementById('rDevice').value, taskName=document.getElementById('rTask').value;
  if(!deviceId||!taskName){ toast('Please fill in required fields','err'); return; }
  closeModal('addModal');
  try{
    const r=await fetch('api_maintenance_history.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({device_id:parseInt(deviceId),task_name:taskName,task_category:document.getElementById('rCategory').value,status:'completed',result:document.getElementById('rStatus').value,performed_by:document.getElementById('rPerformedBy').value,duration_minutes:document.getElementById('rDuration').value?parseInt(document.getElementById('rDuration').value):null,description:document.getElementById('rDescription').value,notes:document.getElementById('rDescription').value})});
    const d=await r.json();
    if(d.success){ toast('Record added successfully','ok'); document.getElementById('rTask').value=''; document.getElementById('rDescription').value=''; loadHistory(); }
    else toast(d.error||'Failed to add record','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function updateRecord(){
  const id=document.getElementById('editId').value;
  closeModal('editModal');
  try{
    const r=await fetch('api_maintenance_history.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:parseInt(id),status:document.getElementById('editStatus').value,result:document.getElementById('editStatus').value,description:document.getElementById('editDesc').value})});
    const d=await r.json();
    if(d.success){ toast('Record updated','ok'); loadHistory(); }
    else toast(d.error||'Failed to update','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function deleteRecord(id){
  if(!confirm('Delete this record?')) return;
  try{
    const r=await fetch(`api_maintenance_history.php?action=delete&id=${id}`,{method:'DELETE'});
    const d=await r.json();
    if(d.success){ toast('Record deleted','ok'); loadHistory(); }
    else toast(d.error||'Failed to delete','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

function toast(msg,type='ok'){ const box=document.getElementById('toastBox'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; box.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3000); }

loadDevices(); loadHistory();
</script>
</body>
</html>