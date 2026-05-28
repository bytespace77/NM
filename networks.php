<?php
require_once 'config.php';

// Set timezone to match your location (Malaysia)
date_default_timezone_set('Asia/Kuala_Lumpur');

$allNetworks = getAllNetworks();
$currentNetwork = getNetworkInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Networks - Network Monitor</title>
    <link rel="icon" href="data:image/svg+xml,
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
        <text y='1.0em' font-size='85'>🌐</text>
        </svg>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            padding: 24px;
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }
        
        /* Theme Toggle Button - Hides on scroll */
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
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
            opacity: 1;
            transform: translateY(0);
        }

        .theme-toggle.hidden {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .theme-toggle.hidden:hover {
            transform: translateY(-20px);
        }

        .theme-toggle span {
            font-size: 13px;
            display: none;
        }

        @media (min-width: 768px) {
            .theme-toggle span {
                display: inline;
            }
        }
        
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            color: var(--text-primary);
            transform: translateX(-4px);
        }
        
        h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 32px;
        }
        
        .current-network {
            background: var(--bg-secondary);
            border: 2px solid #667eea;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 32px;
        }
        
        .current-network h2 {
            font-size: 14px;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        
        .current-network-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            background: var(--bg-primary);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .info-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .networks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 16px;
        }
        
        .network-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .network-card:hover {
            transform: translateY(-4px);
            border-color: #667eea;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.2);
        }
        
        .network-card.active {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }
        
        .network-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }
        
        .network-name {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .network-range {
            font-size: 14px;
            color: var(--text-secondary);
            font-family: 'Courier New', monospace;
        }
        
        .network-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .network-badge.active {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .network-badge.inactive {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
        
        .network-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }
        
        .stat-value.total { color: #667eea; }
        .stat-value.online { color: #10b981; }
        .stat-value.offline { color: #ef4444; }
        
        .stat-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        .network-time {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-secondary);
            border-radius: 12px;
            border: 2px dashed var(--border-color);
        }
        
        .empty-state h2 {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .info-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .info-box h3 {
            font-size: 13px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .info-box p {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .networks-grid {
                grid-template-columns: 1fr;
            }
            
            .current-network-info {
                grid-template-columns: 1fr;
            }
        }

        /* Custom Modal/Popup */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.2s ease;
        }
        
        .custom-modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
        }
        
        .modal-icon {
            font-size: 48px;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
            text-align: center;
            color: var(--text-primary);
        }
        
        .modal-message {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .modal-btn-primary {
            background: #667eea;
            color: #ffffff;
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" id="themeToggle">
        <i class="bi bi-moon-fill" id="themeIcon"></i>
        <span id="themeText">Dark</span>
    </button>

    <!-- Custom Modal -->
    <div class="custom-modal" id="customModal" onclick="closeModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-icon" id="modalIcon"></div>
            <div class="modal-title" id="modalTitle"></div>
            <div class="modal-message" id="modalMessage"></div>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-primary" onclick="closeModal()">OK</button>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Dashboard
        </a>
        
        <h1>🌐 All Networks</h1>
        <p class="subtitle">View all networks you've connected to</p>
        
        <div class="info-box">
            <h3><i class="bi bi-info-circle-fill"></i> How Network Tracking Works</h3>
            <p>
                Your Network Monitor automatically detects which network you're connected to (like Network A: 192.168.0.x or Network B: 192.168.1.x). 
                When you switch networks, the system automatically shows only devices from the current network. 
                All networks are tracked in the database, so you can see the history and devices for each network you've used.
            </p>
        </div>
        
        <div class="current-network">
            <h2><i class="bi bi-wifi"></i> Currently Connected Network</h2>
            <div class="current-network-info">
                <div class="info-item">
                    <div class="info-label">Your IP Address</div>
                    <div class="info-value"><?php echo $currentNetwork['ip']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Network Range</div>
                    <div class="info-value"><?php echo $currentNetwork['range']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Network</div>
                    <div class="info-value"><?php echo $currentNetwork['network']; ?>.x</div>
                </div>
            </div>
        </div>
        
        <?php if (empty($allNetworks)): ?>
            <div class="empty-state">
                <h2>No Networks Detected Yet</h2>
                <p>Start scanning devices to detect and track networks</p>
            </div>
        <?php else: ?>
            <h2 style="font-size: 18px; margin-bottom: 16px; color: var(--text-secondary);">
                All Detected Networks (<?php echo count($allNetworks); ?>)
            </h2>
            
            <div class="networks-grid">
                <?php foreach ($allNetworks as $network): 
                    $isActive = $network['network_range'] === $currentNetwork['range'];
                    
                    // FIXED: Better timezone handling
                    $lastSeenStr = $network['last_seen_at'];
                    $lastSeenTimestamp = strtotime($lastSeenStr);
                    $nowTimestamp = time();
                    $secondsAgo = $nowTimestamp - $lastSeenTimestamp;
                    
                    // Calculate time ago with more accuracy
                    if ($secondsAgo < 60) {
                        $timeAgo = 'Just now';
                    } elseif ($secondsAgo < 3600) {
                        $minutes = floor($secondsAgo / 60);
                        $timeAgo = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
                    } elseif ($secondsAgo < 86400) {
                        $hours = floor($secondsAgo / 3600);
                        $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                    } elseif ($secondsAgo < 2592000) {
                        $days = floor($secondsAgo / 86400);
                        $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                    } else {
                        $months = floor($secondsAgo / 2592000);
                        $timeAgo = $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
                    }
                    
                    // Debug info (comment out in production)
                    // echo "<!-- Network: {$network['network_range']}, Last Seen: $lastSeenStr, Seconds Ago: $secondsAgo, Display: $timeAgo -->";
                ?>
                <div class="network-card <?php echo $isActive ? 'active' : ''; ?>" onclick="<?php echo $isActive ? '' : "showNetworkSwitchModal()"; ?>">
                    <div class="network-header">
                        <div>
                            <div class="network-name">
                                <?php echo $network['network_name'] ?? 'Network ' . explode('.', $network['network_range'])[2]; ?>
                            </div>
                            <div class="network-range"><?php echo $network['network_range']; ?></div>
                        </div>
                        <span class="network-badge <?php echo $isActive ? 'active' : 'inactive'; ?>">
                            <?php echo $isActive ? '● ACTIVE' : 'SAVED'; ?>
                        </span>
                    </div>
                    
                    <div class="network-stats">
                        <div class="stat">
                            <span class="stat-value total"><?php echo $network['total_devices']; ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value online"><?php echo $network['online_devices']; ?></span>
                            <span class="stat-label">Online</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value offline"><?php echo $network['offline_devices']; ?></span>
                            <span class="stat-label">Offline</span>
                        </div>
                    </div>
                    
                    <div class="network-time">
                        <i class="bi bi-clock-history"></i>
                        Last active: <?php echo $timeAgo; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<script>
    // Custom Modal Functions
    function showModal(title, message, icon = '💡') {
        const modal = document.getElementById('customModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalIcon = document.getElementById('modalIcon');
        
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modalIcon.textContent = icon;
        
        modal.classList.add('show');
    }
    
    function closeModal() {
        const modal = document.getElementById('customModal');
        modal.classList.remove('show');
    }
    
    function showNetworkSwitchModal() {
        showModal(
            'Network Switch Required',
            'To switch to this network, connect your computer to it and refresh the page.',
            '🌐'
        );
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    function toggleTheme() {
        const body = document.body;
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        
        if (body.classList.contains('light-mode')) {
            body.classList.remove('light-mode');
            themeIcon.className = 'bi bi-moon-fill';
            themeText.textContent = 'Dark';
            localStorage.setItem('theme', 'dark');
        } else {
            body.classList.add('light-mode');
            themeIcon.className = 'bi bi-sun-fill';
            themeText.textContent = 'Light';
            localStorage.setItem('theme', 'light');
        }
    }

    function loadTheme() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            document.body.classList.add('light-mode');
            document.getElementById('themeIcon').className = 'bi bi-sun-fill';
            document.getElementById('themeText').textContent = 'Light';
        }
    }

    loadTheme();

    // Theme toggle - only visible when scrolled to top
    window.addEventListener('scroll', function() {
        const themeToggle = document.getElementById('themeToggle');
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            themeToggle.style.opacity = '0';
            themeToggle.style.pointerEvents = 'none';
        } else {
            themeToggle.style.opacity = '1';
            themeToggle.style.pointerEvents = 'auto';
        }
    }, false);
</script>
</body>
</html>