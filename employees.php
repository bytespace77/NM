<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
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
                <a href="employees.php" class="nav-item active">
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
                <h1 class="header-title">Employee Management</h1>
                <p class="header-subtitle">Manage employees and their asset assignments</p>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
                <input type="text" id="searchInput" placeholder="Search employees..." style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); width: 300px;">
                <button class="btn btn-primary" onclick="showAddModal()">Add Employee</button>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Assets</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeesTable"></tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="employeeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 24px; width: 500px; max-width: 90%;">
            <h3 style="margin-bottom: 20px; font-weight: 700;">Add Employee</h3>
            <input type="hidden" id="employeeId">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Employee ID</label>
                <input type="text" id="employee_id" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">First Name</label>
                    <input type="text" id="first_name" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Last Name</label>
                    <input type="text" id="last_name" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Email</label>
                <input type="email" id="email" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Department</label>
                    <input type="text" id="department" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary);">Position</label>
                    <input type="text" id="position" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary);">
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button onclick="closeModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-weight: 600;">Close</button>
                <button class="btn btn-primary" onclick="saveEmployee()">Save</button>
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

        async function loadEmployees(search = '') {
            const res = await fetch(`api_employees.php?action=list&search=${search}`);
            const data = await res.json();
            const html = data.data.map(e => `
                <tr>
                    <td>${e.employee_id}</td>
                    <td>${e.first_name} ${e.last_name}</td>
                    <td>${e.email}</td>
                    <td>${e.department || '-'}</td>
                    <td><span class="badge ${e.status === 'active' ? 'online' : 'offline'}">${e.status}</span></td>
                    <td>${e.active_assets}</td>
                    <td>
                        <button onclick="deleteEmployee(${e.id})" style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; font-size: 12px;">Delete</button>
                    </td>
                </tr>
            `).join('');
            document.getElementById('employeesTable').innerHTML = html;
        }

        function showAddModal() {
            document.getElementById('employee_id').value = 'EMP' + Date.now();
            document.getElementById('first_name').value = '';
            document.getElementById('last_name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('department').value = '';
            document.getElementById('position').value = '';
            document.getElementById('employeeModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        async function saveEmployee() {
            const data = {
                employee_id: document.getElementById('employee_id').value,
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                email: document.getElementById('email').value,
                department: document.getElementById('department').value,
                position: document.getElementById('position').value,
                status: 'active',
                hire_date: new Date().toISOString().split('T')[0]
            };
            const res = await fetch('api_employees.php?action=create', { method: 'POST', body: JSON.stringify(data) });
            if ((await res.json()).success) {
                closeModal();
                loadEmployees();
            }
        }

        async function deleteEmployee(id) {
            if (confirm('Delete this employee?')) {
                await fetch(`api_employees.php?action=delete&id=${id}`);
                loadEmployees();
            }
        }

        document.getElementById('searchInput').addEventListener('input', (e) => loadEmployees(e.target.value));
        loadEmployees();
    </script>
</body>
</html>
