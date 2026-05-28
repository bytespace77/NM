<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - Network Monitor</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>🔔</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --bg-primary:#000;--bg-secondary:#0f0f0f;--bg-tertiary:#0a0a0a;
  --border-color:#1a1a1a;--border-hover:#333;
  --text-primary:#fff;--text-secondary:#888;--text-muted:#555;
  --critical:#ff4757;--high:#ffa502;--medium:#3742fa;--low:#2ed573;--accent:#667eea;
}
body.light-mode{
  --bg-primary:#f5f5f5;--bg-secondary:#fff;--bg-tertiary:#f0f0f0;
  --border-color:#e0e0e0;--border-hover:#ccc;
  --text-primary:#111;--text-secondary:#666;--text-muted:#aaa;
}
body{font-family:'Montserrat',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;}

/* Main */
.main{padding:24px 28px;}

/* Topbar */
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.page-title{font-size:20px;font-weight:800;}
.page-subtitle{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
.btn-accent{background:var(--accent);color:#fff;}
.btn-accent:hover{opacity:.85;}
.btn-danger{background:rgba(255,71,87,.12);color:var(--critical);border:1px solid rgba(255,71,87,.25);}
.btn-danger:hover{background:rgba(255,71,87,.22);}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);}
.btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:14px 16px;position:relative;overflow:hidden;}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;}
.sc-red::after{background:var(--critical);}
.sc-orange::after{background:var(--high);}
.sc-yellow::after{background:#ffd32a;}
.sc-blue::after{background:var(--medium);}
.sc-green::after{background:var(--low);}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}

/* Tabs */
.tabs{display:flex;gap:2px;border-bottom:1px solid var(--border-color);margin-bottom:14px;overflow-x:auto;}
.tab-btn{padding:8px 14px;border:none;background:none;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;color:var(--text-secondary);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:color .15s;display:flex;align-items:center;gap:6px;}
.tab-btn:hover{color:var(--text-primary);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-count{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-secondary);font-size:9px;padding:1px 6px;border-radius:8px;font-weight:800;}
.tab-btn.active .tab-count{background:var(--accent);color:#fff;border-color:var(--accent);}

/* Toolbar */
.toolbar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.search-inp{flex:1;min-width:200px;background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.search-inp:focus{outline:none;border-color:var(--accent);}
.filter-sel{background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-primary);padding:7px 11px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;}
.filter-sel:focus{outline:none;border-color:var(--accent);}
.live-indicator{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-secondary);margin-left:auto;}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--low);animation:livepulse 2s ease infinite;}
@keyframes livepulse{0%,100%{opacity:1;}50%{opacity:.3;}}

/* Notif cards */
.notif-list{display:flex;flex-direction:column;gap:8px;}
.notif-card{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;transition:border-color .15s;}
.notif-card:hover{border-color:var(--border-hover);}
.notif-card.unread{border-left:3px solid var(--accent);}
.notif-card.unread.sev-critical{border-left-color:var(--critical);}
.notif-card.unread.sev-warning{border-left-color:var(--high);}
.udot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.udot.critical{background:var(--critical);}
.udot.warning{background:var(--high);}
.notif-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.ni-c{background:rgba(255,71,87,.15);color:var(--critical);}
.ni-w{background:rgba(255,165,2,.15);color:var(--high);}
.ni-m{background:rgba(55,66,250,.15);color:var(--medium);}
.notif-body{flex:1;min-width:0;}
.notif-title{font-size:13px;font-weight:700;margin-bottom:5px;display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
.notif-desc{font-size:12px;color:var(--text-secondary);line-height:1.55;margin-bottom:9px;}
.notif-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.notif-time{font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px;}
.badge{padding:2px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;}
.badge-critical{background:rgba(255,71,87,.12);color:var(--critical);}
.badge-warning{background:rgba(255,165,2,.12);color:var(--high);}
.badge-offline{background:rgba(255,71,87,.08);color:var(--critical);}
.badge-health{background:rgba(255,165,2,.08);color:var(--high);}
.badge-prediction{background:rgba(102,126,234,.1);color:var(--accent);}
.badge-overdue{background:rgba(255,211,42,.1);color:#ffd32a;}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;border:1px solid var(--border-color);color:var(--text-secondary);background:var(--bg-tertiary);cursor:pointer;transition:all .15s;font-family:'Montserrat',sans-serif;}
.act-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.act-btn.ack{border-color:rgba(46,213,115,.3);color:var(--low);background:rgba(46,213,115,.05);}
.act-btn.ack:hover{background:rgba(46,213,115,.12);}
.acked-lbl{font-size:11px;color:var(--low);display:flex;align-items:center;gap:4px;}

/* Empty/loader */
.empty{text-align:center;padding:60px 20px;color:var(--text-secondary);}
.empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.3;}
.loader{width:32px;height:32px;border:3px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:60px auto;}
@keyframes spin{to{transform:rotate(360deg);}}

/* Toast */
.back-btn{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border-radius:6px;background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary);font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;flex-shrink:0;}
.back-btn:hover{border-color:var(--border-hover);color:var(--text-primary);}
.toast-box{position:fixed;bottom:18px;left:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
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



<div class="main">
  <div class="topbar">
    <button class="back-btn" onclick="history.back()"><i class="bi bi-arrow-left"></i> Back</button>
    <div>
      <div class="page-title">🔔 Notifications</div>
      <div class="page-subtitle">Offline devices · Critical health · Predicted failures · Overdue maintenance</div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent btn-sm" onclick="acknowledgeAll()"><i class="bi bi-check2-all"></i> Mark All Read</button>
      <button class="btn btn-danger btn-sm" onclick="clearAcknowledged()"><i class="bi bi-trash3"></i> Clear Read</button>
      <button class="btn btn-ghost btn-sm" onclick="loadAll()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card sc-red">
      <div class="stat-num" id="statUnread" style="color:var(--critical)">—</div>
      <div class="stat-label">Unread Alerts</div>
    </div>
    <div class="stat-card sc-orange">
      <div class="stat-num" id="statOffline" style="color:var(--high)">—</div>
      <div class="stat-label">Devices Offline</div>
    </div>
    <div class="stat-card sc-yellow">
      <div class="stat-num" id="statHealth" style="color:#ffd32a">—</div>
      <div class="stat-label">Critical Health</div>
    </div>
    <div class="stat-card sc-blue">
      <div class="stat-num" id="statPrediction" style="color:var(--medium)">—</div>
      <div class="stat-label">Failure Predicted</div>
    </div>
    <div class="stat-card sc-green">
      <div class="stat-num" id="statOverdue" style="color:var(--low)">—</div>
      <div class="stat-label">Overdue Tasks</div>
    </div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('all',this)">All <span class="tab-count" id="count-all">0</span></button>
    <button class="tab-btn" onclick="switchTab('offline',this)"><i class="bi bi-wifi-off"></i> Offline <span class="tab-count" id="count-offline">0</span></button>
    <button class="tab-btn" onclick="switchTab('health',this)"><i class="bi bi-heart-pulse"></i> Critical Health <span class="tab-count" id="count-health">0</span></button>
    <button class="tab-btn" onclick="switchTab('prediction',this)"><i class="bi bi-lightning-charge"></i> Failure Prediction <span class="tab-count" id="count-prediction">0</span></button>
    <button class="tab-btn" onclick="switchTab('overdue',this)"><i class="bi bi-calendar-x"></i> Overdue <span class="tab-count" id="count-overdue">0</span></button>
  </div>

  <div class="toolbar">
    <input type="text" class="search-inp" id="searchInput" placeholder="🔍  Search notifications..." oninput="renderFiltered()">
    <select class="filter-sel" id="severityFilter" onchange="renderFiltered()">
      <option value="">All Severity</option>
      <option value="CRITICAL">🔴 Critical</option>
      <option value="WARNING">🟠 Warning</option>
    </select>
    <select class="filter-sel" id="readFilter" onchange="renderFiltered()">
      <option value="">All Status</option>
      <option value="unread">Unread only</option>
      <option value="read">Read only</option>
    </select>
    <div class="live-indicator">
      <div class="live-dot"></div>
      Auto-refresh 60s &nbsp;·&nbsp; Updated: <span id="lastUpdated">—</span>
    </div>
  </div>

  <div id="notifList"><div class="loader"></div></div>
</div>

<div class="toast-box" id="toastBox"></div>

<script>
let allNotifications = [], currentTab = 'all';
let localAcknowledged = new Set(JSON.parse(localStorage.getItem('notif_acked')||'[]'));
function saveLocalAcked(){ localStorage.setItem('notif_acked', JSON.stringify([...localAcknowledged])); }
function isAcked(n){ return n.acked || localAcknowledged.has(String(n.id)); }

function toggleTheme(){
  document.body.classList.toggle('light-mode');
  const l = document.body.classList.contains('light-mode');
  document.getElementById('themeIcon').className = l ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('notif_theme', l ? 'light' : 'dark');
}
if(localStorage.getItem('notif_theme')==='light'){
  document.body.classList.add('light-mode');
  document.getElementById('themeIcon').className = 'bi bi-sun-fill';
}

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(s){
  if(!s) return '—';
  try{
    const d=new Date(s), now=new Date(), diff=Math.round((now-d)/60000);
    if(diff<1)  return 'Just now';
    if(diff<60) return diff+'m ago';
    if(diff<1440) return Math.round(diff/60)+'h ago';
    return d.toLocaleDateString('en-MY',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
  }catch(e){ return s; }
}
function isDevOnline(d){
  if(d.status) return d.status==='online';
  const f=d.factors||{};
  return f.is_online===true||f.is_online===1;
}
function sevCls(s){ return s==='CRITICAL'?'critical':'warning'; }
function typeIcon(t){ return {offline:'bi-wifi-off',health:'bi-heart-pulse-fill',prediction:'bi-lightning-charge-fill',overdue:'bi-calendar-x-fill'}[t]||'bi-bell-fill'; }
function typeIconBg(s){ return s==='CRITICAL'?'ni-c':'ni-w'; }

function buildNotifications(alerts, devices, overdue){
  const list = [];

  alerts.forEach(a => {
    const days = parseInt(a.days_until||0);
    list.push({
      id:'alert_'+a.id, _rawId:a.id, type:'prediction', severity:a.severity||'WARNING',
      title:'Predicted Failure: '+a.device_name,
      desc:(a.message||'Device predicted to fail')+(days?' — '+days+' days remaining.':'')+( a.recommended_action?' '+a.recommended_action:''),
      device:a.device_name, device_id:a.device_id, time:a.created_at, acked:a.is_acknowledged==1
    });
  });

  devices.filter(d=>!isDevOnline(d)).forEach(d=>{
    const f=d.factors||{}, hrs=parseFloat(f.hours_since_last_seen||0);
    const since = hrs<1?'less than 1 hour':hrs<24?hrs.toFixed(0)+' hours':(hrs/24).toFixed(1)+' days';
    list.push({
      id:'offline_'+d.device_id, _rawId:null, type:'offline', severity:'CRITICAL',
      title:'Device Offline: '+d.device_name,
      desc:d.device_name+' ('+d.device_ip+') has been offline for '+since+'.',
      device:d.device_name, device_id:d.device_id, time:d.last_updated,
      acked:localAcknowledged.has('offline_'+d.device_id)
    });
  });

  devices.filter(d=>parseFloat(d.health_score)<30).forEach(d=>{
    list.push({
      id:'health_'+d.device_id, _rawId:null, type:'health', severity:'CRITICAL',
      title:'Critical Health Score: '+d.device_name,
      desc:'Health score at '+parseFloat(d.health_score).toFixed(1)+'% — Risk: '+d.risk_level+'. Immediate attention required.',
      device:d.device_name, device_id:d.device_id, time:d.last_updated,
      acked:localAcknowledged.has('health_'+d.device_id)
    });
  });

  const alertedIds = new Set(alerts.map(a=>String(a.device_id)));
  devices.filter(d=>parseInt(d.days_until_failure)<=14&&!alertedIds.has(String(d.device_id))).forEach(d=>{
    const days=parseInt(d.days_until_failure);
    list.push({
      id:'pred14_'+d.device_id, _rawId:null, type:'prediction', severity:days<=7?'CRITICAL':'WARNING',
      title:'Failure Imminent: '+d.device_name,
      desc:'Predicted to fail in '+days+' days. Confidence: '+parseFloat(d.confidence_level).toFixed(0)+'%.',
      device:d.device_name, device_id:d.device_id, time:d.last_updated,
      acked:localAcknowledged.has('pred14_'+d.device_id)
    });
  });

  overdue.forEach(s=>{
    list.push({
      id:'overdue_'+s.id, _rawId:null, type:'overdue', severity:s.priority==='high'?'CRITICAL':'WARNING',
      title:'Overdue Maintenance: '+s.device_name,
      desc:'"'+s.task_name+'" is '+s.days_overdue+' days overdue'+(s.ip_address?' for '+s.device_name+' ('+s.ip_address+')':'')+'. Priority: '+s.priority+'.',
      device:s.device_name, device_id:s.device_id, time:s.scheduled_date,
      acked:localAcknowledged.has('overdue_'+s.id)
    });
  });

  list.sort((a,b)=>{ const aA=isAcked(a)?1:0,bA=isAcked(b)?1:0; if(aA!==bA)return aA-bA; return new Date(b.time)-new Date(a.time); });
  return list;
}

function switchTab(tab, el){
  currentTab=tab;
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  el.classList.add('active');
  renderFiltered();
}

function renderFiltered(){
  const q   = document.getElementById('searchInput').value.toLowerCase();
  const sev = document.getElementById('severityFilter').value;
  const rd  = document.getElementById('readFilter').value;
  const list= allNotifications.filter(n=>{
    if(currentTab!=='all'&&n.type!==currentTab) return false;
    if(q&&!n.title.toLowerCase().includes(q)&&!n.desc.toLowerCase().includes(q)&&!(n.device||'').toLowerCase().includes(q)) return false;
    if(sev&&n.severity!==sev) return false;
    const a=isAcked(n);
    if(rd==='unread'&&a) return false;
    if(rd==='read'&&!a)  return false;
    return true;
  });
  renderNotifications(list);
}

function renderNotifications(notifs){
  const wrap=document.getElementById('notifList');
  if(!notifs.length){
    wrap.innerHTML='<div class="empty"><i class="bi bi-bell-slash"></i><p>No notifications found</p></div>';
    return;
  }
  wrap.innerHTML='<div class="notif-list">'+notifs.map(n=>{
    const acked=isAcked(n), sc=sevCls(n.severity);
    return `<div class="notif-card ${acked?'':'unread sev-'+sc}">
      ${acked?'<div style="width:8px;flex-shrink:0;"></div>':`<div class="udot ${sc}"></div>`}
      <div class="notif-icon ${typeIconBg(n.severity)}"><i class="bi ${typeIcon(n.type)}"></i></div>
      <div class="notif-body">
        <div class="notif-title">
          ${esc(n.title)}
          <span class="badge badge-${sc}">${n.severity}</span>
          <span class="badge badge-${n.type}">${n.type}</span>
        </div>
        <div class="notif-desc">${esc(n.desc)}</div>
        <div class="notif-meta">
          <span class="notif-time"><i class="bi bi-clock"></i> ${fmt(n.time)}</span>
          ${n.device_id?`<a href="device_detail.php?id=${n.device_id}" class="act-btn"><i class="bi bi-box-arrow-up-right"></i> View Device</a>`:''}
          ${!acked
            ?`<button class="act-btn ack" onclick="acknowledge('${esc(n.id)}',${n._rawId||'null'})"><i class="bi bi-check2"></i> Mark Read</button>`
            :'<span class="acked-lbl"><i class="bi bi-check2-all"></i> Read</span>'}
        </div>
      </div>
    </div>`;
  }).join('')+'</div>';
}

async function acknowledge(localId, dbId){
  localAcknowledged.add(String(localId));
  saveLocalAcked();
  if(dbId){ try{ await fetch('api_ai_predictions.php?action=acknowledge_alert',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alert_id:dbId})}); }catch(e){} }
  renderFiltered();
  updateCounts();
}

async function acknowledgeAll(){
  for(const n of allNotifications.filter(n=>!isAcked(n))){
    localAcknowledged.add(String(n.id));
    if(n._rawId){ try{ await fetch('api_ai_predictions.php?action=acknowledge_alert',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alert_id:n._rawId})}); }catch(e){} }
  }
  saveLocalAcked(); renderFiltered(); updateCounts();
  toast('All notifications marked as read','ok');
}

function clearAcknowledged(){
  allNotifications=allNotifications.filter(n=>!isAcked(n));
  renderFiltered(); updateCounts();
  toast('Read notifications cleared','ok');
}

function updateCounts(){
  const unread=allNotifications.filter(n=>!isAcked(n)).length;
  const offline=allNotifications.filter(n=>n.type==='offline').length;
  const health=allNotifications.filter(n=>n.type==='health').length;
  const pred=allNotifications.filter(n=>n.type==='prediction').length;
  const over=allNotifications.filter(n=>n.type==='overdue').length;
  document.getElementById('statUnread').textContent   =unread;
  document.getElementById('statOffline').textContent  =offline;
  document.getElementById('statHealth').textContent   =health;
  document.getElementById('statPrediction').textContent=pred;
  document.getElementById('statOverdue').textContent  =over;
  document.getElementById('count-all').textContent       =allNotifications.length;
  document.getElementById('count-offline').textContent   =offline;
  document.getElementById('count-health').textContent    =health;
  document.getElementById('count-prediction').textContent=pred;
  document.getElementById('count-overdue').textContent   =over;

}

async function loadAll(){
  document.getElementById('notifList').innerHTML='<div class="loader"></div>';
  try{
    const [ar,hr,or_]=await Promise.all([
      fetch('api_ai_predictions.php?action=get_alerts'),
      fetch('api_ai_predictions.php?action=get_health_metrics'),
      fetch('api_maintenance_schedules.php?action=overdue')
    ]);
    const [ad,hd,od]=await Promise.all([ar.json(),hr.json(),or_.json()]);
    const alerts=ad.success?(ad.data||[]):[];
    let devices=(hd.success?(hd.data||[]):[]).map(d=>{
      if(d.factors&&typeof d.factors==='string'){try{d.factors=JSON.parse(d.factors);}catch(e){d.factors={};}}
      if(!d.factors)d.factors={};
      return d;
    });
    const overdue=od.success?(od.data||[]):[];
    allNotifications=buildNotifications(alerts,devices,overdue);
    updateCounts();
    renderFiltered();
    document.getElementById('lastUpdated').textContent=new Date().toLocaleTimeString('en-MY');
  }catch(err){
    document.getElementById('notifList').innerHTML=`<div class="empty"><i class="bi bi-exclamation-circle"></i><p>Error: ${esc(err.message)}</p><button class="btn btn-ghost btn-sm" onclick="loadAll()" style="margin-top:12px;"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>`;
  }
}

function toast(msg,type='ok'){
  const box=document.getElementById('toastBox');
  const t=document.createElement('div');
  t.className='toast '+type; t.textContent=msg;
  box.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';t.style.transition='.3s';setTimeout(()=>t.remove(),300);},3000);
}

loadAll();
setInterval(loadAll,60000);
</script>
</body>
</html>