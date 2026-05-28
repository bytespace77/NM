<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Schedules - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>📅</text></svg>">
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
.sc-red::after{background:var(--critical);}
.sc-orange::after{background:var(--high);}
.sc-blue::after{background:var(--medium);}
.sc-green::after{background:var(--low);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
.filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.filter-inp,.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-inp{flex:1;min-width:200px;} .filter-inp:focus,.filter-sel:focus{outline:none;border-color:var(--accent);}
/* Section headers */
.section-header{display:flex;align-items:center;gap:10px;margin:20px 0 10px;padding:0 2px;}
.section-title{font-size:13px;font-weight:700;}
.section-count{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px;}
/* Schedule cards */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;margin-bottom:8px;}
.sch-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;border-left:3px solid var(--border-color);transition:all .2s;cursor:pointer;}
.sch-card:hover{border-color:var(--border-hover);transform:translateY(-1px);}
.sch-card.overdue{border-left-color:var(--critical);}
.sch-card.upcoming{border-left-color:var(--high);}
.sch-card.completed{border-left-color:var(--low);}
.sch-card.pending{border-left-color:var(--medium);}
.sc-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;}
.sc-task{font-size:13px;font-weight:700;margin-bottom:2px;}
.sc-device{font-size:11px;color:var(--text-secondary);}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;}
.badge-completed{background:rgba(46,213,115,.12);color:var(--low);}
.badge-pending{background:rgba(55,66,250,.12);color:var(--medium);}
.badge-overdue,.badge-failed{background:rgba(255,71,87,.12);color:var(--critical);}
.sc-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:11px;color:var(--text-secondary);margin-bottom:8px;}
.sc-meta-item{display:flex;align-items:center;gap:4px;}
.pri-badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;}
.pri-badge.high{background:rgba(255,71,87,.12);color:var(--critical);}
.pri-badge.medium{background:rgba(255,165,2,.12);color:var(--high);}
.pri-badge.low{background:rgba(55,66,250,.12);color:var(--medium);}
.sc-actions{display:flex;gap:6px;padding-top:8px;border-top:1px solid var(--border-color);}
.sc-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);color:var(--text-secondary);background:var(--bg-tertiary);transition:all .15s;}
.sc-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.sc-btn.success-btn{border-color:rgba(46,213,115,.3);color:var(--low);}
.sc-btn.success-btn:hover{background:rgba(46,213,115,.08);}
.sc-btn.danger{border-color:rgba(255,71,87,.3);color:var(--critical);}
.sc-btn.danger:hover{background:rgba(255,71,87,.08);}
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
.empty{text-align:center;padding:40px 20px;color:var(--text-secondary);}
.empty i{font-size:32px;display:block;margin-bottom:8px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}
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
    <a href="maintenance_schedules.php"   class="nav-item active"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
  <div class="sidebar-stats">
    <div class="sidebar-stats-title">Summary</div>
    <div class="ss-row"><span style="color:var(--critical);font-size:11px;font-weight:500;">Overdue</span><span id="sb-overdue" style="color:var(--critical);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--high);font-size:11px;font-weight:500;">Upcoming (7d)</span><span id="sb-upcoming" style="color:var(--high);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--medium);font-size:11px;font-weight:500;">Pending</span><span id="sb-pending" style="color:var(--medium);font-weight:800;font-size:11px;">—</span></div>
    <div class="ss-row"><span style="color:var(--low);font-size:11px;font-weight:500;">Completed</span><span id="sb-completed" style="color:var(--low);font-weight:800;font-size:11px;">—</span></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="page-title">📅 Maintenance Schedules</div>
      <div class="page-subtitle">Upcoming, overdue, and completed maintenance tasks</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent btn-sm" onclick="showCreateModal()"><i class="bi bi-plus-lg"></i> New Schedule</button>
      <button class="btn btn-ghost btn-sm" onclick="loadSchedules()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card sc-red"><div class="stat-num" id="st-overdue" style="color:var(--critical)">—</div><div class="stat-label">Overdue</div></div>
    <div class="stat-card sc-orange"><div class="stat-num" id="st-upcoming" style="color:var(--high)">—</div><div class="stat-label">Due in 7 Days</div></div>
    <div class="stat-card sc-blue"><div class="stat-num" id="st-pending" style="color:var(--medium)">—</div><div class="stat-label">Pending</div></div>
    <div class="stat-card sc-green"><div class="stat-num" id="st-completed" style="color:var(--low)">—</div><div class="stat-label">Completed</div></div>
  </div>

  <div class="filters">
    <input type="text" class="filter-inp" id="searchInput" placeholder="🔍  Search task, device..." oninput="applyFilters()">
    <select class="filter-sel" id="networkFilter" onchange="loadSchedules()"><option value="">All Networks</option></select>
    <select class="filter-sel" id="priorityFilter" onchange="applyFilters()">
      <option value="">All Priorities</option>
      <option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option>
    </select>
  </div>

  <div id="loadingState"><div class="loader"></div></div>
  <div id="mainContent" style="display:none;"></div>
</div>

<!-- Create Schedule Modal -->
<div id="createModal" class="modal">
  <div class="mc">
    <div class="mh"><h2><i class="bi bi-plus-lg"></i> New Schedule</h2><button class="mc-close" onclick="closeModal('createModal')">&times;</button></div>
    <div class="mb">
      <div class="fg"><label>Device *</label><select id="sDevice" class="fc"><option value="">Select device...</option></select></div>
      <div class="fg"><label>Task Name *</label><input type="text" id="sTask" class="fc" placeholder="e.g. Monthly inspection"></div>
      <div class="fg"><label>Scheduled Date *</label><input type="datetime-local" id="sDate" class="fc"></div>
      <div class="fg"><label>Priority</label>
        <select id="sPriority" class="fc"><option value="medium">Medium</option><option value="high">High</option><option value="low">Low</option></select>
      </div>
      <div class="fg"><label>Est. Duration (min)</label><input type="number" id="sDuration" class="fc" placeholder="60"></div>
      <div class="fg"><label>Recurrence</label>
        <select id="sRecurrence" class="fc">
          <option value="none">None (one-time)</option><option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option>
        </select>
      </div>
      <div class="fg"><label>Notes</label><textarea id="sNotes" class="fc" rows="2" placeholder="Additional details..."></textarea></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('createModal')">Cancel</button>
      <button class="btn btn-accent btn-sm" onclick="createSchedule()"><i class="bi bi-check-lg"></i> Create</button>
    </div>
  </div>
</div>

<!-- Complete Modal -->
<div id="completeModal" class="modal">
  <div class="mc">
    <div class="mh"><h2><i class="bi bi-check-lg"></i> Mark as Completed</h2><button class="mc-close" onclick="closeModal('completeModal')">&times;</button></div>
    <div class="mb">
      <input type="hidden" id="completeId">
      <div class="fg"><label>Performed By</label><input type="text" id="cPerformedBy" class="fc" placeholder="Technician name"></div>
      <div class="fg"><label>Actual Duration (min)</label><input type="number" id="cDuration" class="fc"></div>
      <div class="fg"><label>Cost (RM)</label><input type="number" id="cCost" class="fc" placeholder="0.00" step="0.01"></div>
      <div class="fg"><label>Completion Notes</label><textarea id="cNotes" class="fc" rows="2" placeholder="What was done..."></textarea></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('completeModal')">Cancel</button>
      <button class="btn btn-accent btn-sm" onclick="completeSchedule()"><i class="bi bi-check-all"></i> Complete</button>
    </div>
  </div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allSchedules=[], allDevices=[], schedulesData={overdue:[],upcoming:[],pending:[],completed:[]};

function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('ms_theme',l?'light':'dark'); }
if(localStorage.getItem('ms_theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function getDaysUntil(d){ return Math.ceil((new Date(d)-new Date())/(86400000)); }

function fmtDate(s,category){
  if(!s) return '—';
  const d=new Date(s), diff=getDaysUntil(s);
  if(diff<0) return `<strong style="color:var(--critical)">${Math.abs(diff)} day${Math.abs(diff)!==1?'s':''} overdue</strong>`;
  if(diff===0) return '<strong style="color:var(--high)">Today</strong>';
  if(diff===1) return '<strong style="color:var(--high)">Tomorrow</strong>';
  if(diff<=7)  return `<strong style="color:var(--high)">In ${diff} days</strong>`;
  return d.toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric'});
}

function categorize(){
  const now=new Date(), soon=new Date(now.getTime()+7*86400000);
  schedulesData={overdue:[],upcoming:[],pending:[],completed:[]};
  allSchedules.forEach(s=>{
    const d=new Date(s.scheduled_date);
    if(s.status==='completed') schedulesData.completed.push(s);
    else if(s.status==='pending'&&d<now) schedulesData.overdue.push(s);
    else if(s.status==='pending'&&d<=soon) schedulesData.upcoming.push(s);
    else schedulesData.pending.push(s);
  });
  ['overdue','upcoming','pending'].forEach(k=>schedulesData[k].sort((a,b)=>new Date(a.scheduled_date)-new Date(b.scheduled_date)));
  schedulesData.completed.sort((a,b)=>new Date(b.completed_at||b.scheduled_date)-new Date(a.completed_at||a.scheduled_date));
}

function updateStats(){
  ['overdue','upcoming','pending','completed'].forEach(k=>{
    ['st','sb'].forEach(p=>{ const el=document.getElementById(p+'-'+k); if(el) el.textContent=schedulesData[k].length; });
  });
}

function populateNetworkFilter(){
  const sel=document.getElementById('networkFilter'), cur=sel.value;
  const nets=[...new Set(allSchedules.map(s=>s.network_range).filter(n=>n))];
  sel.innerHTML='<option value="">All Networks</option>'+nets.map(n=>`<option value="${esc(n)}">${esc(n)}</option>`).join('');
  sel.value=cur;
}

function renderCard(s,cat){
  const sc=cat==='overdue'?'overdue':cat==='upcoming'?'upcoming':cat==='completed'?'completed':'pending';
  return `<div class="sch-card ${sc}">
    <div class="sc-head">
      <div><div class="sc-task">${esc(s.task_name)}</div><div class="sc-device"><i class="bi bi-hdd-network"></i> ${esc(s.device_name||'Unknown')}${s.ip_address?' · '+esc(s.ip_address):''}</div></div>
      <span class="badge badge-${s.status}">${s.status.replace('_',' ')}</span>
    </div>
    <div class="sc-meta">
      <div class="sc-meta-item"><i class="bi bi-calendar"></i>${fmtDate(s.scheduled_date,cat)}</div>
      ${s.priority?`<div class="sc-meta-item"><span class="pri-badge ${s.priority}">${s.priority}</span></div>`:''}
      ${s.recurrence_pattern&&s.recurrence_pattern!=='none'?`<div class="sc-meta-item"><i class="bi bi-arrow-repeat"></i>${esc(s.recurrence_pattern)}</div>`:''}
      ${s.estimated_duration?`<div class="sc-meta-item"><i class="bi bi-clock"></i>${s.estimated_duration} min</div>`:''}
    </div>
    ${s.description?`<div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;padding:6px 8px;background:var(--bg-tertiary);border-radius:4px;">${esc(s.description)}</div>`:''}
    ${s.status==='pending'?`<div class="sc-actions">
      <button class="sc-btn success-btn" onclick="showCompleteModal(${s.id})"><i class="bi bi-check-lg"></i> Complete</button>
      <button class="sc-btn danger" onclick="deleteSchedule(${s.id})"><i class="bi bi-trash-fill"></i> Delete</button>
    </div>`:''}
  </div>`;
}

function applyFilters(){
  const q=document.getElementById('searchInput').value.toLowerCase();
  const pri=document.getElementById('priorityFilter').value;
  const filt=s=>(!q||(s.task_name||'').toLowerCase().includes(q)||(s.device_name||'').toLowerCase().includes(q))&&(!pri||s.priority===pri);
  const mc=document.getElementById('mainContent');
  const sections=[
    {key:'overdue',  label:'🔴 Overdue',     color:'var(--critical)'},
    {key:'upcoming', label:'🟠 Due Soon',     color:'var(--high)'},
    {key:'pending',  label:'🔵 Pending',      color:'var(--medium)'},
    {key:'completed',label:'✅ Completed',    color:'var(--low)'},
  ];
  let html='';
  sections.forEach(sec=>{
    const items=schedulesData[sec.key].filter(filt);
    if(!items.length) return;
    html+=`<div class="section-header"><span class="section-title" style="color:${sec.color}">${sec.label}</span><span class="section-count">${items.length}</span></div>`;
    html+=`<div class="cards-grid">${items.map(s=>renderCard(s,sec.key)).join('')}</div>`;
  });
  mc.innerHTML=html||'<div class="empty"><i class="bi bi-calendar-x"></i><p>No schedules found</p></div>';
}

async function loadDevices(){ try{ const r=await fetch('api_ai_predictions.php?action=get_health_metrics'); const d=await r.json(); if(d.success) allDevices=d.data||[]; }catch(e){} }

async function loadSchedules(){
  document.getElementById('loadingState').style.display='block';
  document.getElementById('mainContent').style.display='none';
  const net=document.getElementById('networkFilter').value;
  try{
    let url='api_maintenance_schedules.php?action=list';
    if(net) url+=`&network_range=${encodeURIComponent(net)}`;
    const r=await fetch(url); const d=await r.json();
    if(d.success){
      allSchedules=d.data||[]; categorize(); populateNetworkFilter(); updateStats(); applyFilters();
      document.getElementById('loadingState').style.display='none';
      document.getElementById('mainContent').style.display='block';
    } else throw new Error(d.error||'Failed');
  }catch(err){
    document.getElementById('loadingState').innerHTML=`<div class="empty"><i class="bi bi-exclamation-circle"></i><p>${esc(err.message)}</p><button class="btn btn-ghost btn-sm" onclick="loadSchedules()" style="margin-top:12px;">Retry</button></div>`;
  }
}

function showCreateModal(){
  document.getElementById('sDevice').innerHTML='<option value="">Select device...</option>'+allDevices.map(d=>`<option value="${d.device_id}">${esc(d.device_name)} (${esc(d.ip_address||d.device_ip||'')})</option>`).join('');
  document.getElementById('createModal').classList.add('show');
}
function showCompleteModal(id){
  const s=allSchedules.find(x=>x.id===id); if(!s) return;
  document.getElementById('completeId').value=id;
  document.getElementById('cDuration').value=s.estimated_duration||'';
  document.getElementById('completeModal').classList.add('show');
}
function closeModal(id){ document.getElementById(id).classList.remove('show'); }

async function createSchedule(){
  const did=document.getElementById('sDevice').value, task=document.getElementById('sTask').value, date=document.getElementById('sDate').value;
  if(!did||!task||!date){ toast('Please fill required fields','err'); return; }
  closeModal('createModal');
  try{
    const r=await fetch('api_maintenance_schedules.php?action=create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({device_id:did,task_name:task,task_category:'general',scheduled_date:date,estimated_duration:document.getElementById('sDuration').value||null,priority:document.getElementById('sPriority').value,status:'pending',recurring:document.getElementById('sRecurrence').value!=='none'?1:0,recurring_interval:document.getElementById('sRecurrence').value!=='none'?document.getElementById('sRecurrence').value:null,description:document.getElementById('sNotes').value})});
    const d=await r.json();
    if(d.success){ toast('Schedule created','ok'); loadSchedules(); }
    else toast(d.error||'Failed to create','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function completeSchedule(){
  const id=document.getElementById('completeId').value;
  closeModal('completeModal');
  try{
    const r=await fetch('api_maintenance_schedules.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,status:'completed',notes:document.getElementById('cNotes').value,performed_by:document.getElementById('cPerformedBy').value,duration_minutes:document.getElementById('cDuration').value||null,cost:document.getElementById('cCost').value||null})});
    const d=await r.json();
    if(d.success){ toast('Task completed!','ok'); loadSchedules(); }
    else toast(d.error||'Failed','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

async function deleteSchedule(id){
  if(!confirm('Delete this schedule?')) return;
  try{
    const r=await fetch(`api_maintenance_schedules.php?action=delete&id=${id}`,{method:'DELETE'});
    const d=await r.json();
    if(d.success){ toast('Schedule deleted','ok'); loadSchedules(); }
    else toast(d.error||'Failed','err');
  }catch(e){ toast('Error: '+e.message,'err'); }
}

function toast(msg,type='ok'){ const box=document.getElementById('toastBox'); const t=document.createElement('div'); t.className='toast '+type; t.textContent=msg; box.appendChild(t); setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3000); }

loadDevices(); loadSchedules();
</script>
</body>
</html>