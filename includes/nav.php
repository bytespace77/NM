<style>
.unified-nav { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.unified-nav .nav-link { color: rgba(255,255,255,0.8); transition: all 0.3s; }
.unified-nav .nav-link:hover { color: white; transform: translateY(-2px); }
.unified-nav .nav-link.active { color: white; font-weight: 600; border-bottom: 2px solid white; }
</style>
<nav class="navbar navbar-expand-lg unified-nav">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="asset_dashboard.php">🏢 IT Management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="asset_dashboard.php">📊 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="employees.php">👥 Employees</a></li>
                <li class="nav-item"><a class="nav-link" href="assets.php">💼 Assets</a></li>
                <li class="nav-item"><a class="nav-link" href="device_asset_link.php">🔗 Link Devices</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php">📡 Network Devices</a></li>
                <li class="nav-item"><a class="nav-link" href="monitoring.php">📈 System Monitor</a></li>
            </ul>
        </div>
    </div>
</nav>
