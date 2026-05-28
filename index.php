<?php
require_once 'config.php';

$yourIP = getLocalIP();
$uptimeKumaStatus = isUptimeKumaRunning();

$parts = explode('.', $yourIP);
$networkRange = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Monitor - Device Tracker</title>
    <link rel="icon" href="data:image/svg+xml,
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
        <text y='1.0em' font-size='85'>🌐</text>
        </svg>">

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
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
            overflow-x: hidden;
            transition: background 0.3s ease, color 0.3s ease;
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
        
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
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
            transition: transform 0.3s ease, background 0.3s ease, border-color 0.3s ease;
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
        
        /* Sidebar Stats */
        .sidebar-stats {
            padding: 16px 24px;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            transition: background 0.3s ease;
        }
        
        .sidebar-stat {
            text-align: center;
        }
        
        .sidebar-stat-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }
        
        .sidebar-stat-value.total { color: #667eea; }
        .sidebar-stat-value.online { color: #10b981; }
        .sidebar-stat-value.offline { color: #ef4444; }
        
        .sidebar-stat-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
            display: block;
        }
        
        /* Sidebar Filter Dropdown */
        .sidebar-filter {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .filter-dropdown {
            position: relative;
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--border-hover);
        }
        
        .filter-select option {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 10px;
        }
        
        /* Search Box */
        .sidebar-search {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 14px center;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--border-hover);
        }
        
        .search-input::placeholder {
            color: var(--text-secondary);
        }
        
        /* Device List */
        .sidebar-devices {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        .sidebar-device {
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
        }
        
        .sidebar-device:hover {
            background: var(--bg-secondary);
            border-color: var(--border-color);
            transform: translateX(4px);
        }
        
        .sidebar-device.active {
            background: var(--bg-secondary);
            border-color: #667eea;
        }
        
        .sidebar-device-icon {
            font-size: 20px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            border-radius: 6px;
            flex-shrink: 0;
        }
        
        .sidebar-device-info {
            flex: 1;
            min-width: 0;
        }
        
        .sidebar-device-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-device-ip {
            font-size: 11px;
            color: var(--text-secondary);
            font-family: 'Courier New', monospace;
        }
        
        .sidebar-device-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .sidebar-device-status.online {
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
        }
        
        .sidebar-device-status.offline {
            background: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 320px;
            padding: 24px;
            transition: margin-left 0.3s ease;
        }
        
        /* Header */
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
        
        /* Network Info */
        .network-info {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
            transition: all 0.3s ease;
        }
        
        .network-info h2 {
            font-size: 14px;
            margin-bottom: 16px;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .network-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .network-item {
            background: var(--bg-primary);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .network-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .network-value {
            font-size: 16px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        /* Controls */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        /* Filter Buttons */
        .filters {
            display: flex;
            gap: 8px;
            background: var(--bg-secondary);
            padding: 6px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            background: transparent;
            color: var(--text-secondary);
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .filter-btn:hover {
            color: var(--text-primary);
        }
        
        .filter-btn.active {
            background: var(--text-primary);
            color: var(--bg-primary);
        }
        
        .filter-btn.active.online {
            background: #10b981;
            color: #ffffff;
        }
        
        .filter-btn.active.offline {
            background: #ef4444;
            color: #ffffff;
        }
        
        /* Actions - Responsive */
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 32px;
        }
        
        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:disabled {
            background: var(--text-tertiary);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        /* Device Grid */
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px;
        }
        
        .device-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            border-left: 3px solid #10b981;
            border-top: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            animation: slideIn 0.4s ease-out;
            transition: all 0.3s;
            scroll-margin-top: 20px;
        }
        
        .device-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.1);
        }
        
        .device-card.offline {
            border-left-color: #ef4444;
            opacity: 0.7;
        }
        
        .device-card.hidden {
            display: none;
        }
        
        .device-card.highlight {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.3);
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .device-header {
            margin-bottom: 20px;
        }
        
        .device-name-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }
        
        .device-name {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .device-vendor {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-badge.online {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        .status-badge.offline {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .device-info {
            margin-bottom: 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }
        
        /* Scanning indicator */
        .scanning-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: var(--bg-secondary);
            border: 1px solid #667eea;
            border-radius: 8px;
            padding: 16px 24px;
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .scanning-indicator.active {
            display: flex;
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid var(--border-color);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-secondary);
            border-radius: 12px;
            border: 2px dashed var(--border-color);
            transition: all 0.3s ease;
        }
        
        .empty-state h2 {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--text-tertiary);
            font-size: 14px;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 20px;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-btn:hover {
            background: var(--bg-tertiary);
        }
        
        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 280px;
            }
            
            .main-content {
                margin-left: 280px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 16px 16px 16px;
            }
            
            .mobile-menu-btn {
                display: flex;
            }
            
            .devices-grid {
                grid-template-columns: 1fr;
            }
            
            .theme-toggle {
                top: 20px;
                right: 80px;
            }
            
            .actions {
                grid-template-columns: 1fr;
            }
            
            .filter-btn {
                padding: 8px 16px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .actions {
                grid-template-columns: 1fr;
            }
            
            .btn {
                font-size: 12px;
                padding: 12px 16px;
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

    <div class="app-container">
        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="bi bi-list" id="menuIcon"></i>
        </button>
        
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1 data-get="/">🌐 Network Monitor</h1>
                <p>Device tracking system</p>
            </div>
            
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value total" id="sidebarTotal">0</span>
                    <span class="sidebar-stat-label">Total</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value online" id="sidebarOnline">0</span>
                    <span class="sidebar-stat-label">Online</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value offline" id="sidebarOffline">0</span>
                    <span class="sidebar-stat-label">Offline</span>
                </div>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="sidebar-filter">
                <div class="filter-dropdown">
                    <select class="filter-select" id="sidebarFilterSelect" onchange="filterSidebar(this.value)">
                        <option value="all">All Devices</option>
                        <option value="online">Online Only</option>
                        <option value="offline">Offline Only</option>
                    </select>
                </div>
            </div>
            
            <div class="sidebar-search">
                <input type="text" class="search-input" id="searchInput" placeholder="Search devices...">
            </div>
            
            <!-- NAVIGATION MENU START -->
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: var(--bg-tertiary); margin: 0;">
                <h3 style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-compass-fill" style="font-size: 13px; color: #667eea;"></i>
                    Dashboard Navigation
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <!-- Network Devices Button -->
                    <a href="index.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #667eea; border-radius: 8px; text-decoration: none; color: #ffffff; font-weight: 600; font-size: 13px; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2); font-family: 'Montserrat', sans-serif;" onmouseover="this.style.transform='translateX(4px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='none';">
                        <i class="bi bi-diagram-3-fill" style="font-size: 16px;"></i>
                        <span>Network Devices</span>
                    </a>
                    
                    <!-- System Monitoring Button -->
                    <a href="monitoring.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-primary); font-weight: 600; font-size: 13px; transition: all 0.3s ease; font-family: 'Montserrat', sans-serif;" onmouseover="this.style.transform='translateX(4px)'; this.style.borderColor='#667eea'; this.style.background='var(--bg-tertiary)';" onmouseout="this.style.transform='translateX(0)'; this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-secondary)';">
                        <i class="bi bi-bar-chart-fill" style="font-size: 16px; color: #667eea;"></i>
                        <span>System Monitoring</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-devices" id="sidebarDevices">
                <!-- Devices will be populated here -->
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title">Dashboard</h1>
                <p class="header-subtitle">Real-time network device monitoring</p>
            </div>

            <div class="network-info">
                <h2>Network Information</h2>
                <div class="network-grid">
                    <div class="network-item">
                        <div class="network-label">Your IP Address</div>
                        <div class="network-value"><?php echo $yourIP; ?></div>
                    </div>
                    <div class="network-item">
                        <div class="network-label">Network Range</div>
                        <div class="network-value"><?php echo $networkRange; ?></div>
                    </div>
                    <div class="network-item">
                        <div class="network-label">Last Scan</div>
                        <div class="network-value" id="lastScan" style="font-size: 14px;">--:--:--</div>
                    </div>
                    <div class="network-item">
                        <div class="network-label">Auto-Refresh</div>
                        <div class="network-value" style="font-size: 14px; color: #667eea;">Every 10s</div>
                    </div>
                </div>
            </div>

            <div class="controls">
                <div class="filters">
                    <button class="filter-btn active" data-filter="all" onclick="filterDevices('all')">
                        All Devices
                    </button>
                    <button class="filter-btn online" data-filter="online" onclick="filterDevices('online')">
                        Online Only
                    </button>
                    <button class="filter-btn offline" data-filter="offline" onclick="filterDevices('offline')">
                        Offline Only
                    </button>
                </div>
            </div>

            <div class="actions">
                <button onclick="scanNow()" class="btn btn-primary" id="scanBtn">
                    <span>🔍</span> Scan Network
                </button>
                <button onclick="toggleAutoScan()" class="btn btn-secondary" id="autoScanBtn">
                    <span>⏸</span> Pause Auto-Scan
                </button>
                <a href="add_device.php" class="btn btn-secondary">
                    <span>➕</span> Add Device
                </a>
                <a href="networks.php" class="btn btn-secondary">
                    <span>🌐</span> All Networks
                </a>
                <button onclick="exportToPDF()" class="btn btn-secondary">
                    <span>📄</span> Export PDF
                </button>
                <button onclick="exportToExcel()" class="btn btn-secondary">
                    <span>📊</span> Export Excel
                </button>
                
            </div>

            <div class="devices-grid" id="devicesGrid">
                <div class="empty-state">
                    <h2>Initializing...</h2>
                    <p>Preparing to scan network</p>
                </div>
            </div>
        </div>
    </div>

    <div class="scanning-indicator" id="scanningIndicator">
        <div class="spinner"></div>
        <div style="font-size: 13px; font-weight: 600;">Scanning network...</div>
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
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

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

    // Theme toggle - only visible when scrolled to top
    window.addEventListener('scroll', function() {
        const themeToggle = document.getElementById('themeToggle');
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            // Scrolled down - hide
            themeToggle.classList.add('hidden');
        } else {
            // At top - show
            themeToggle.classList.remove('hidden');
        }
    }, false);

    // Load theme before page renders
    loadTheme();

    let autoScanEnabled = true;
    let autoScanInterval = null;
    let isScanning = false;
    let allDevices = [];
    let currentFilter = 'all';
    let sidebarFilter = 'all';
    
    window.addEventListener('load', () => {
        console.log('Page loaded, starting initial scan...');
        loadTheme(); // Ensure theme is applied
        scanNow();
        startAutoScan();
    });
    
    function startAutoScan() {
        autoScanInterval = setInterval(() => {
            if (autoScanEnabled && !isScanning) {
                console.log('Auto-scan triggered');
                scanNow();
            }
        }, 10000);
    }
    
    function toggleAutoScan() {
        autoScanEnabled = !autoScanEnabled;
        const btn = document.getElementById('autoScanBtn');
        const iconSpan = btn.querySelector('span');
        if (autoScanEnabled) {
            btn.innerHTML = '<span>⏸</span> Pause Auto-Scan';
        } else {
            btn.innerHTML = '<span>▶</span> Resume Auto-Scan';
        }
        console.log('Auto-scan:', autoScanEnabled ? 'enabled' : 'disabled');
    }
    
    // Sort devices: Online first, then by name
    function sortDevices(devices) {
        return devices.sort((a, b) => {
            // First sort by status (online first)
            if (a.online && !b.online) return -1;
            if (!a.online && b.online) return 1;
            
            // If same status, sort alphabetically by name
            return a.name.localeCompare(b.name);
        });
    }
    
    function scanNow() {
        if (isScanning) {
            console.log('Already scanning, skipping...');
            return;
        }
        
        console.log('Starting scan...');
        isScanning = true;
        document.getElementById('scanBtn').disabled = true;
        document.getElementById('scanningIndicator').classList.add('active');
        
        fetch('api_scan_real.php')
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Scan complete! Response:', data);
                
                if (!data.success) {
                    throw new Error(data.error || 'Scan failed');
                }
                
                // Sort devices: online first
                allDevices = sortDevices(data.devices);
                
                updateStats();
                updateSidebar();
                updateDevices();
                
                isScanning = false;
                document.getElementById('scanBtn').disabled = false;
                document.getElementById('scanningIndicator').classList.remove('active');
                
                const now = new Date();
                document.getElementById('lastScan').textContent = 
                    now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                console.log('Scan successful:', allDevices.length, 'devices (online first)');
            })
            .catch(error => {
                console.error('Scan error:', error);
                
                isScanning = false;
                document.getElementById('scanBtn').disabled = false;
                document.getElementById('scanningIndicator').classList.remove('active');
                
                const grid = document.getElementById('devicesGrid');
                grid.innerHTML = `
                    <div class="empty-state">
                        <h2 style="color: #ef4444;">Scan Failed</h2>
                        <p>Error: ${error.message}</p>
                        <p style="margin-top: 12px; font-size: 12px; color: var(--text-secondary);"></p>
                        <div style="display: flex; justify-content: center; margin-top: 20px;">
                            <button onclick="scanNow()" class="btn btn-primary">Try Again</button>
                        </div>
                    </div>
                `;
            });
    }
    
    function updateStats() {
        const online = allDevices.filter(d => d.online).length;
        const offline = allDevices.filter(d => !d.online).length;
        
        document.getElementById('sidebarTotal').textContent = allDevices.length;
        document.getElementById('sidebarOnline').textContent = online;
        document.getElementById('sidebarOffline').textContent = offline;
        
        console.log('Stats updated - Total:', allDevices.length, 'Online:', online, 'Offline:', offline);
    }
    
    function updateSidebar() {
        const sidebar = document.getElementById('sidebarDevices');
        
        if (allDevices.length === 0) {
            sidebar.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-secondary);">No devices found</div>';
            return;
        }
        
        // Apply sidebar filter
        let filteredDevices = allDevices;
        if (sidebarFilter === 'online') {
            filteredDevices = allDevices.filter(d => d.online);
        } else if (sidebarFilter === 'offline') {
            filteredDevices = allDevices.filter(d => !d.online);
        }
        
        // Already sorted by sortDevices() - online first
        sidebar.innerHTML = filteredDevices.map(device => `
            <div class="sidebar-device" onclick="scrollToDevice('${device.ip}')" data-ip="${device.ip}" data-status="${device.online ? 'online' : 'offline'}">
                <div class="sidebar-device-icon">
                    ${device.icon}
                </div>
                <div class="sidebar-device-info">
                    <div class="sidebar-device-name">${device.name}</div>
                    <div class="sidebar-device-ip">${device.ip}</div>
                </div>
                <div class="sidebar-device-status ${device.online ? 'online' : 'offline'}"></div>
            </div>
        `).join('');
    }
    
    function filterSidebar(filter) {
        sidebarFilter = filter;
        updateSidebar();
        
        // Also update main filter to match
        filterDevices(filter);
    }
    
    function filterDevices(filter) {
        console.log('Filter changed to:', filter);
        currentFilter = filter;
        
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
        
        // Update sidebar dropdown to match
        document.getElementById('sidebarFilterSelect').value = filter;
        sidebarFilter = filter;
        
        // Filter devices
        updateDevices();
    }
    
    function updateDevices() {
        const grid = document.getElementById('devicesGrid');
        
        let filteredDevices = allDevices;
        if (currentFilter === 'online') {
            filteredDevices = allDevices.filter(d => d.online);
        } else if (currentFilter === 'offline') {
            filteredDevices = allDevices.filter(d => !d.online);
        }
        
        console.log('Displaying devices:', filteredDevices.length, 'Filter:', currentFilter);
        
        if (filteredDevices.length === 0) {
            const filterText = currentFilter === 'all' ? 'No devices found' : 
                               currentFilter === 'online' ? 'No online devices' : 'No offline devices';
            grid.innerHTML = `
                <div class="empty-state">
                    <h2>${filterText}</h2>
                    <p>${currentFilter === 'all' ? 'Scan network to discover devices' : 'Try changing the filter'}</p>
                </div>
            `;
            return;
        }
        
        // Already sorted by sortDevices() - online first
        grid.innerHTML = filteredDevices.map((device, index) => `
            <div class="device-card ${device.online ? 'online' : 'offline'}" id="device-${device.ip}" style="animation-delay: ${index * 0.05}s">
                <div class="device-header">
                    <div class="device-name-row">
                        <div>
                            <div class="device-name">
                                ${device.icon} ${device.name}
                            </div>
                            ${device.hostname ? `<div class="device-hostname">${device.hostname}</div>` : ''}
                            <div class="device-vendor">${device.vendor}</div>
                        </div>
                        <span class="status-badge ${device.online ? 'online' : 'offline'}">
                            ${device.online ? '● ONLINE' : '○ OFFLINE'}
                        </span>
                    </div>
                </div>
                
                <div class="device-info">
                    <div class="info-row">
                        <span class="info-label">IP Address</span>
                        <span class="info-value">${device.ip}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">MAC Address</span>
                        <span class="info-value">${device.mac}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Device Type</span>
                        <span class="info-value">${device.type}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Active</span>
                        <span class="info-value">${device.online ? 'Online' : formatLastSeen(device.last_seen)}</span>
                    </div>
                </div>
            </div>
        `).join('');
        
        console.log('Devices rendered successfully (online first)');
    }
    
    function formatLastSeen(datetime) {
        if (!datetime) return 'Unknown';
        
        const now = new Date();
        const lastSeen = new Date(datetime);
        const diffMs = now - lastSeen;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return diffHours + 'h ago';
        
        const diffDays = Math.floor(diffHours / 24);
        return diffDays + 'd ago';
    }
    
    function scrollToDevice(ip) {
        const deviceCard = document.getElementById('device-' + ip);
        if (deviceCard) {
            deviceCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Highlight effect
            deviceCard.classList.add('highlight');
            setTimeout(() => {
                deviceCard.classList.remove('highlight');
            }, 2000);
            
            // Update sidebar active state
            document.querySelectorAll('.sidebar-device').forEach(d => d.classList.remove('active'));
            const sidebarDevice = document.querySelector(`.sidebar-device[data-ip="${ip}"]`);
            if (sidebarDevice) {
                sidebarDevice.classList.add('active');
            }
            
            // Close sidebar on mobile after selection
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        }
    }
    
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const menuIcon = document.getElementById('menuIcon');
        
        sidebar.classList.toggle('mobile-open');
        
        // Change icon
        if (sidebar.classList.contains('mobile-open')) {
            menuIcon.className = 'bi bi-x-lg';
        } else {
            menuIcon.className = 'bi bi-list';
        }
    }

    // Export to PDF
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Get filtered devices based on current filter
        let devicesToExport = allDevices;
        if (currentFilter === 'online') {
            devicesToExport = allDevices.filter(d => d.online);
        } else if (currentFilter === 'offline') {
            devicesToExport = allDevices.filter(d => !d.online);
        }
        
        // Title
        doc.setFontSize(20);
        doc.setTextColor(40);
        doc.text('Network Monitor Report', 14, 22);
        
        // Subtitle with date
        doc.setFontSize(11);
        doc.setTextColor(100);
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        doc.text(`Generated: ${dateStr}`, 14, 32);
        doc.text(`Filter: ${currentFilter.toUpperCase()}`, 14, 38);
        doc.text(`Total Devices: ${devicesToExport.length}`, 14, 44);
        
        // Prepare table data
        const tableData = devicesToExport.map(device => [
            device.name,
            device.ip,
            device.mac,
            device.vendor,
            device.type,
            device.online ? 'ONLINE' : 'OFFLINE',
            device.online ? 'Just now' : formatLastSeen(device.last_seen)
        ]);
        
        // Create table
        doc.autoTable({
            startY: 50,
            head: [['Device Name', 'IP Address', 'MAC Address', 'Vendor', 'Type', 'Status', 'Last Seen']],
            body: tableData,
            theme: 'grid',
            headStyles: {
                fillColor: [102, 126, 234],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 9
            },
            bodyStyles: {
                fontSize: 8
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            columnStyles: {
                0: { cellWidth: 30 }, // Device Name
                1: { cellWidth: 28 }, // IP
                2: { cellWidth: 32 }, // MAC
                3: { cellWidth: 22 }, // Vendor
                4: { cellWidth: 20 }, // Type
                5: { cellWidth: 20 }, // Status
                6: { cellWidth: 22 }  // Last Seen
            },
            didParseCell: function(data) {
                // Color status column
                if (data.column.index === 5) {
                    if (data.cell.raw === 'ONLINE') {
                        data.cell.styles.textColor = [16, 185, 129];
                        data.cell.styles.fontStyle = 'bold';
                    } else {
                        data.cell.styles.textColor = [239, 68, 68];
                        data.cell.styles.fontStyle = 'bold';
                    }
                }
            }
        });
        
        // Save PDF
        const filename = `network-monitor-${now.getTime()}.pdf`;
        doc.save(filename);
        
        console.log('PDF exported:', filename);
    }

    // Export to Excel (CSV format)
    function exportToExcel() {
        // Get filtered devices based on current filter
        let devicesToExport = allDevices;
        if (currentFilter === 'online') {
            devicesToExport = allDevices.filter(d => d.online);
        } else if (currentFilter === 'offline') {
            devicesToExport = allDevices.filter(d => !d.online);
        }
        
        // Create CSV content
        let csv = 'Device Name,IP Address,MAC Address,Vendor,Device Type,Status,Last Seen\n';
        
        devicesToExport.forEach(device => {
            const row = [
                device.name,
                device.ip,
                device.mac,
                device.vendor,
                device.type,
                device.online ? 'ONLINE' : 'OFFLINE',
                device.online ? 'Just now' : formatLastSeen(device.last_seen)
            ];
            
            // Escape commas and quotes
            const escapedRow = row.map(field => {
                field = String(field);
                if (field.includes(',') || field.includes('"') || field.includes('\n')) {
                    return '"' + field.replace(/"/g, '""') + '"';
                }
                return field;
            });
            
            csv += escapedRow.join(',') + '\n';
        });
        
        // Create download link
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        const now = new Date();
        const filename = `network-monitor-${now.getTime()}.csv`;
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('Excel/CSV exported:', filename);
    }
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        document.querySelectorAll('.sidebar-device').forEach(device => {
            const name = device.querySelector('.sidebar-device-name').textContent.toLowerCase();
            const ip = device.querySelector('.sidebar-device-ip').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || ip.includes(searchTerm)) {
                device.style.display = 'flex';
            } else {
                device.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>