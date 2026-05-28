<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management</title>
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
                <a href="asset_dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="employees.php" class="nav-item">
                    <i class="bi bi-people"></i> Employees
                </a>
                <a href="assets.php" class="nav-item active">
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
                <h1 class="header-title">Asset Management</h1>
                <p class="header-subtitle">Track and manage your IT assets</p>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 24px; gap: 12px;">
                <input type="text" id="searchInput" placeholder="Search assets..." style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); width: 300px;">
                <select id="statusFilter" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_maintenance">Maintenance</option>
                </select>
                <button class="btn btn-primary" onclick="showAddModal()">Add Asset</button>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assetsTable"></tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="assetModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 24px; width: 500px; max-width: 90%;">
            <h3 style="margin-bottom: 20px; font-weight: 700;">Add Asset</h3>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Asset Tag</label>
                <input type="text" id="asset_tag" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Name</label>
                <input type="text" id="name" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Category</label>
                <select id="category_id" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);"></select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button onclick="closeModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-weight: 600;">Close</button>
                <button class="btn btn-primary" onclick="saveAsset()">Save</button>
            </div>
        </div>
    </div>

    <div id="assignModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 24px; width: 400px; max-width: 90%;">
            <h3 style="margin-bottom: 20px; font-weight: 700;">Assign Asset</h3>
            <input type="hidden" id="assign_asset_id">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Employee</label>
                <select id="assign_employee_id" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);"></select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button onclick="closeAssignModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-weight: 600;">Close</button>
                <button class="btn btn-primary" onclick="assignAsset()">Assign</button>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            document.querySelector('.theme-toggle').textContent = document.body.classList.contains('light-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        }
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-mode');
            document.querySelector('.theme-toggle').textContent = '☀️';
        }

        async function loadAssets(search = '', status = '') {
            const res = await fetch(`api_assets.php?action=list&search=${search}&status=${status}`);
            const data = await res.json();
            const html = data.data.map(a => `
                <tr>
                    <td>${a.asset_tag}</td>
                    <td>${a.name}</td>
                    <td>${a.category_icon} ${a.category_name}</td>
                    <td><span class="badge ${a.status === 'available' ? 'available' : 'assigned'}">${a.status}</span></td>
                    <td>${a.assigned_to || '-'}</td>
                    <td>
                        ${a.status === 'available' ? `<button onclick="showAssignModal(${a.id})" style="padding: 6px 12px; border-radius: 6px; background: #10b981; color: white; border: none; cursor: pointer; font-size: 12px; font-weight: 600;">Assign</button>` : ''}
                        <button onclick="deleteAsset(${a.id})" style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-size: 12px; margin-left: 4px;">Delete</button>
                    </td>
                </tr>
            `).join('');
            document.getElementById('assetsTable').innerHTML = html;
        }

        async function loadCategories() {
            const res = await fetch('api_assets.php?action=categories');
            const data = await res.json();
            document.getElementById('category_id').innerHTML = data.data.map(c => `<option value="${c.id}">${c.icon} ${c.name}</option>`).join('');
        }

        async function loadEmployees() {
            const res = await fetch('api_employees.php?action=list&status=active');
            const data = await res.json();
            document.getElementById('assign_employee_id').innerHTML = data.data.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('');
        }

        function showAddModal() {
            document.getElementById('asset_tag').value = 'AST' + Date.now();
            document.getElementById('name').value = '';
            document.getElementById('assetModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('assetModal').style.display = 'none';
        }

        function showAssignModal(assetId) {
            document.getElementById('assign_asset_id').value = assetId;
            document.getElementById('assignModal').style.display = 'flex';
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        async function saveAsset() {
            const data = {
                asset_tag: document.getElementById('asset_tag').value,
                name: document.getElementById('name').value,
                category_id: document.getElementById('category_id').value,
                status: 'available'
            };
            const res = await fetch('api_assets.php?action=create', { method: 'POST', body: JSON.stringify(data) });
            if ((await res.json()).success) {
                closeModal();
                loadAssets();
            }
        }

        async function assignAsset() {
            const data = {
                asset_id: document.getElementById('assign_asset_id').value,
                employee_id: document.getElementById('assign_employee_id').value,
                condition_on_assignment: 'good',
                assigned_by: 'Admin'
            };
            const res = await fetch('api_assignments.php?action=assign', { method: 'POST', body: JSON.stringify(data) });
            if ((await res.json()).success) {
                closeAssignModal();
                loadAssets();
            }
        }

        async function deleteAsset(id) {
            if (confirm('Delete this asset?')) {
                await fetch(`api_assets.php?action=delete&id=${id}`);
                loadAssets();
            }
        }

        document.getElementById('searchInput').addEventListener('input', (e) => loadAssets(e.target.value, document.getElementById('statusFilter').value));
        document.getElementById('statusFilter').addEventListener('change', (e) => loadAssets(document.getElementById('searchInput').value, e.target.value));

        loadCategories();
        loadEmployees();
        loadAssets();
    </script>
</body>
</html>
