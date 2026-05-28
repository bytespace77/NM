<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --bg-primary: #000000;
    --bg-secondary: #0f0f0f;
    --bg-tertiary: #0a0a0a;
    --border-color: #1a1a1a;
    --border-hover: #333;
    --text-primary: #ffffff;
    --text-secondary: #666;
    --text-tertiary: #444;
}

body.light-mode {
    --bg-primary: #ffffff;
    --bg-secondary: #f5f5f5;
    --bg-tertiary: #fafafa;
    --border-color: #e0e0e0;
    --border-hover: #d0d0d0;
    --text-primary: #000000;
    --text-secondary: #666666;
    --text-tertiary: #999999;
}

body {
    font-family: 'Montserrat', sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: background 0.3s ease, color 0.3s ease;
}

.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1001;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.theme-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.app-container { display: flex; min-height: 100vh; }

.sidebar {
    width: 320px;
    background: var(--bg-primary);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
    transition: all 0.3s ease;
}

.sidebar-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
}

.sidebar-header h1 {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 4px;
}

.sidebar-header p {
    font-size: 12px;
    color: var(--text-secondary);
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
}

.nav-item {
    padding: 12px 16px;
    margin-bottom: 4px;
    background: var(--bg-tertiary);
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: var(--text-primary);
}

.nav-item:hover {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    transform: translateX(4px);
}

.nav-item.active {
    background: var(--bg-secondary);
    border-color: #667eea;
}

.main-content {
    flex: 1;
    margin-left: 320px;
    padding: 24px;
    transition: margin-left 0.3s ease;
}

.header {
    margin-bottom: 32px;
}

.header-title {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 8px;
}

.header-subtitle {
    font-size: 14px;
    color: var(--text-secondary);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    border-color: var(--border-hover);
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
}

.stat-value.purple { color: #667eea; }
.stat-value.green { color: #10b981; }
.stat-value.red { color: #ef4444; }
.stat-value.yellow { color: #f59e0b; }

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
    transform: translateY(-2px);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.table th {
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge.online { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.badge.offline { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.badge.available { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.badge.assigned { background: rgba(102, 126, 234, 0.2); color: #667eea; }
</style>
