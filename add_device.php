<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    $name = $db->real_escape_string($_POST['name']);
    $ip = $db->real_escape_string($_POST['ip_address']);
    $mac = $db->real_escape_string($_POST['mac_address'] ?? '');
    $type = $db->real_escape_string($_POST['device_type']);
    $location = $db->real_escape_string($_POST['location'] ?? '');
    
    // If MAC is empty, try to get it
    if (empty($mac)) {
        $mac = getMacFromIP($ip);
    }
    
    $sql = "INSERT INTO devices (name, ip_address, mac_address, device_type, location, created_at) 
            VALUES ('$name', '$ip', '$mac', '$type', '$location', NOW())";
    
    if ($db->query($sql)) {
        $deviceId = $db->insert_id;
        
        // Get initial status
        $result = pingDevice($ip);
        $status = $result['online'] ? 'online' : 'offline';
        $responseTime = $result['response_time'] ?? 'NULL';
        
        $db->query("UPDATE devices SET 
            status='$status', 
            response_time=$responseTime, 
            last_checked_at=NOW(),
            last_seen_at=" . ($result['online'] ? 'NOW()' : 'NULL') . "
            WHERE id=$deviceId");
        
        header('Location: index.php?added=1');
        exit;
    } else {
        $error = urlencode($db->error);
        header("Location: add_device.php?error=$error");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Device - Network Monitor</title>
    <link rel="icon" href="data:image/svg+xml,
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
        <text y='1.0em' font-size='85'>🌐</text>
        </svg>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        /* Theme Variables */
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

        /* Light Theme */
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
        
        /* Theme Toggle Button */
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
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .theme-toggle span {
            font-size: 13px;
        }
        
        .container {
            max-width: 600px;
            margin: 40px auto;
            position: relative;
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
        
        .card {
            background: var(--bg-secondary);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        h1 { 
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 32px;
        }
        
        .alert {
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            color: #ef4444;
            margin-bottom: 24px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }
        
        .required {
            color: #ef4444;
        }
        
        input, select {
            width: 100%;
            padding: 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--border-hover);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input::placeholder {
            color: var(--text-tertiary);
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        
        select option {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .help-text {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 6px;
        }
        
        .buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 32px;
        }
        
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary { 
            background: #667eea;
            color: #ffffff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary { 
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }
        
        .info-box {
            background: var(--bg-tertiary);
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-box p {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 16px;
            }
            
            .container {
                margin: 20px auto;
            }
            
            .card {
                padding: 24px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .buttons {
                grid-template-columns: 1fr;
            }
            
            .theme-toggle span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" id="themeToggle">
        <i class="bi bi-moon-fill" id="themeIcon"></i>
        <span id="themeText">Dark</span>
    </button>

    <div class="container">
        <a href="index.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Dashboard
        </a>
        
        <div class="card">
            <h1>Add New Device</h1>
            <p class="subtitle">Manually add a device to the network monitor</p>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Error: <?php echo htmlspecialchars($_GET['error']); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3><i class="bi bi-info-circle-fill"></i> Auto-Detection</h3>
                <p>If you leave the MAC Address empty, the system will try to automatically detect it from your network's ARP table.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>
                        Device Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        required 
                        placeholder="e.g., Office PC, Living Room TV"
                        autocomplete="off"
                    >
                    <div class="help-text">A friendly name to identify this device</div>
                </div>
                
                <div class="form-group">
                    <label>
                        IP Address <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="ip_address" 
                        required 
                        placeholder="e.g., 192.168.1.100" 
                        pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}"
                        autocomplete="off"
                    >
                    <div class="help-text">The device's IP address on your network</div>
                </div>
                
                <div class="form-group">
                    <label>
                        MAC Address (Optional)
                    </label>
                    <input 
                        type="text" 
                        name="mac_address" 
                        placeholder="e.g., 00:1A:2B:3C:4D:5E"
                        pattern="([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})"
                        autocomplete="off"
                    >
                    <div class="help-text">Will be auto-detected if left empty</div>
                </div>
                
                <div class="form-group">
                    <label>
                        Device Type <span class="required">*</span>
                    </label>
                    <select name="device_type" required>
                        <option value="" disabled selected>Select device type...</option>
                        <option value="computer">💻 Computer</option>
                        <option value="server">🖥️ Server</option>
                        <option value="printer">🖨️ Printer</option>
                        <option value="router">📡 Router</option>
                        <option value="switch">🔌 Switch</option>
                        <option value="phone">📱 Phone</option>
                        <option value="tablet">📱 Tablet</option>
                        <option value="other">📟 Other</option>
                    </select>
                    <div class="help-text">Choose the type that best describes this device</div>
                </div>
                
                <div class="form-group">
                    <label>
                        Location (Optional)
                    </label>
                    <input 
                        type="text" 
                        name="location" 
                        placeholder="e.g., Office Room 101, Server Rack A"
                        autocomplete="off"
                    >
                    <div class="help-text">Physical location of the device</div>
                </div>
                
                <div class="buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle-fill"></i>
                        Add Device
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle-fill"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

<script>
    // Theme Management - Load immediately
    function toggleTheme() {
        const body = document.body;
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        
        if (body.classList.contains('light-mode')) {
            // Switch to dark mode
            body.classList.remove('light-mode');
            themeIcon.className = 'bi bi-moon-fill';
            themeText.textContent = 'Dark';
            localStorage.setItem('theme', 'dark');
        } else {
            // Switch to light mode
            body.classList.add('light-mode');
            themeIcon.className = 'bi bi-sun-fill';
            themeText.textContent = 'Light';
            localStorage.setItem('theme', 'light');
        }
    }

    // Load saved theme on page load
    function loadTheme() {
        const savedTheme = localStorage.getItem('theme');
        const body = document.body;
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        
        if (savedTheme === 'light') {
            body.classList.add('light-mode');
            if (themeIcon) themeIcon.className = 'bi bi-sun-fill';
            if (themeText) themeText.textContent = 'Light';
        } else {
            if (themeIcon) themeIcon.className = 'bi bi-moon-fill';
            if (themeText) themeText.textContent = 'Dark';
        }
    }

    // Load theme immediately
    loadTheme();

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const ip = document.querySelector('input[name="ip_address"]').value;
        const ipPattern = /^(\d{1,3}\.){3}\d{1,3}$/;
        
        if (!ipPattern.test(ip)) {
            e.preventDefault();
            alert('Please enter a valid IP address (e.g., 192.168.1.100)');
            return false;
        }
        
        // Validate IP octets are in range 0-255
        const octets = ip.split('.');
        for (let octet of octets) {
            if (parseInt(octet) > 255) {
                e.preventDefault();
                alert('IP address octets must be between 0 and 255');
                return false;
            }
        }
        
        return true;
    });
</script>
</body>
</html>