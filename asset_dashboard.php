<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management Dashboard</title>
    <?php include 'includes/theme.php'; ?>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>

    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>🏢 IT Management</h1>
                <p>Asset & Network Monitor</p>
            </div>
            <nav class="sidebar-nav">
                <a href="asset_dashboard.php" class="nav-item active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="employees.php" class="nav-item">
                    <i class="bi bi-people"></i> Employees
                </a>
                <a href="assets.php" class="nav-item">
                    <i class="bi bi-box-seam"></i> Assets
                </a>
                <a href="device_asset_link.php" class="nav-item">
                    <i class="bi bi-link-45deg"></i> Link Devices
                </a>
                <a href="index.php" class="nav-item">
                    <i class="bi bi-router"></i> Network Devices
                </a>
                <a href="monitoring.php" class="nav-item">
                    <i class="bi bi-graph-up"></i> System Monitor
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1 class="header-title">Asset Management Dashboard</h1>
                <p class="header-subtitle">Overview of your IT assets and employees</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value purple" id="totalAssets">-</div>
                    <div class="stat-label">Total Assets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value green" id="availableAssets">-</div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value yellow" id="assignedAssets">-</div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value red" id="activeEmployees">-</div>
                    <div class="stat-label">Active Employees</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <div class="card">
                    <h3 style="margin-bottom: 16px; font-weight: 700;">Recent Employees</h3>
                    <div id="employeesList"></div>
                </div>
                <div class="card">
                    <h3 style="margin-bottom: 16px; font-weight: 700;">Recent Assets</h3>
                    <div id="assetsList"></div>
                </div>
                <div class="card">
                    <h3 style="margin-bottom: 16px; font-weight: 700;">Network Devices</h3>
                    <div id="devicesList"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            const btn = document.querySelector('.theme-toggle');
            btn.textContent = document.body.classList.contains('light-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        }

        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-mode');
            document.querySelector('.theme-toggle').textContent = '☀️';
        }

        async function loadStats() {
            const [assets, employees] = await Promise.all([
                fetch('api_assets.php?action=stats').then(r => r.json()),
                fetch('api_employees.php?action=stats').then(r => r.json())
            ]);
            document.getElementById('totalAssets').textContent = assets.data.total;
            document.getElementById('availableAssets').textContent = assets.data.available;
            document.getElementById('assignedAssets').textContent = assets.data.assigned;
            document.getElementById('activeEmployees').textContent = employees.data.active;
        }

        async function loadEmployees() {
            const res = await fetch('api_employees.php?action=list&status=active');
            const data = await res.json();
            const html = data.data.slice(0, 5).map(e =>
                `<div style="padding: 8px 0; border-bottom: 1px solid var(--border-color);"><strong>${e.first_name} ${e.last_name}</strong><br><small style="color: var(--text-secondary);">${e.department || 'N/A'} - ${e.active_assets} assets</small></div>`
            ).join('');
            document.getElementById('employeesList').innerHTML = html || '<p style="color: var(--text-secondary);">No employees</p>';
        }

        async function loadAssets() {
            const res = await fetch('api_assets.php?action=list');
            const data = await res.json();
            const html = data.data.slice(0, 5).map(a =>
                `<div style="padding: 8px 0; border-bottom: 1px solid var(--border-color);"><strong>${a.name}</strong><br><small style="color: var(--text-secondary);">${a.category_name} - ${a.status}</small></div>`
            ).join('');
            document.getElementById('assetsList').innerHTML = html || '<p style="color: var(--text-secondary);">No assets</p>';
        }

        async function loadDevices() {
            const res = await fetch('api_get_devices_fast.php');
            const data = await res.json();
            const online = data.devices.filter(d => d.status === 'online').slice(0, 5);
            const html = online.map(d =>
                `<div style="padding: 8px 0; border-bottom: 1px solid var(--border-color);"><strong>${d.name}</strong><br><small style="color: var(--text-secondary);">${d.ip_address} - ${d.device_type}</small></div>`
            ).join('');
            document.getElementById('devicesList').innerHTML = html || '<p style="color: var(--text-secondary);">No devices</p>';
        }

        loadStats();
        loadEmployees();
        loadAssets();
        loadDevices();
    </script>
</body>
</html>
