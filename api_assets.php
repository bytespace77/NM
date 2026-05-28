<?php
require_once 'config.php';
header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT a.*, c.name as category_name, c.icon as category_icon,
                v.name as vendor_name,
                d.ip_address, d.status as device_status,
                CASE WHEN aa.id IS NOT NULL THEN CONCAT(e.first_name, ' ', e.last_name) ELSE NULL END as assigned_to
                FROM assets a
                LEFT JOIN asset_categories c ON a.category_id = c.id
                LEFT JOIN vendors v ON a.vendor_id = v.id
                LEFT JOIN devices d ON a.device_id = d.id
                LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.status = 'active'
                LEFT JOIN employees e ON aa.employee_id = e.id
                WHERE 1=1";

        if ($status) $sql .= " AND a.status = '" . $db->real_escape_string($status) . "'";
        if ($category) $sql .= " AND a.category_id = " . intval($category);
        if ($search) {
            $search = $db->real_escape_string($search);
            $sql .= " AND (a.name LIKE '%$search%' OR a.asset_tag LIKE '%$search%' OR a.serial_number LIKE '%$search%')";
        }

        $sql .= " ORDER BY a.created_at DESC";
        $result = $db->query($sql);

        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $assets]);
        break;

    case 'get':
        $id = intval($_GET['id']);
        $result = $db->query("SELECT a.*, c.name as category_name, v.name as vendor_name
                              FROM assets a
                              LEFT JOIN asset_categories c ON a.category_id = c.id
                              LEFT JOIN vendors v ON a.vendor_id = v.id
                              WHERE a.id = $id");

        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Asset not found']);
        }
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $db->prepare("INSERT INTO assets (asset_tag, name, category_id, device_id, manufacturer, model, serial_number, purchase_date, purchase_cost, vendor_id, warranty_expiry, status, condition, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiissssdisssss",
            $data['asset_tag'], $data['name'], $data['category_id'], $data['device_id'],
            $data['manufacturer'], $data['model'], $data['serial_number'], $data['purchase_date'],
            $data['purchase_cost'], $data['vendor_id'], $data['warranty_expiry'],
            $data['status'], $data['condition'], $data['location'], $data['notes']
        );

        if ($stmt->execute()) {
            $asset_id = $db->insert_id;
            $db->query("INSERT INTO asset_history (asset_id, action, description, performed_by) VALUES ($asset_id, 'created', 'Asset created', 'System')");
            echo json_encode(['success' => true, 'id' => $asset_id]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);

        $stmt = $db->prepare("UPDATE assets SET name=?, category_id=?, device_id=?, manufacturer=?, model=?, serial_number=?, purchase_date=?, purchase_cost=?, vendor_id=?, warranty_expiry=?, status=?, condition=?, location=?, notes=? WHERE id=?");
        $stmt->bind_param("siissssdiissssi",
            $data['name'], $data['category_id'], $data['device_id'], $data['manufacturer'],
            $data['model'], $data['serial_number'], $data['purchase_date'], $data['purchase_cost'],
            $data['vendor_id'], $data['warranty_expiry'], $data['status'], $data['condition'],
            $data['location'], $data['notes'], $id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'delete':
        $id = intval($_GET['id']);
        if ($db->query("DELETE FROM assets WHERE id = $id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'stats':
        $stats = [
            'total' => $db->query("SELECT COUNT(*) as c FROM assets")->fetch_assoc()['c'],
            'available' => $db->query("SELECT COUNT(*) as c FROM assets WHERE status='available'")->fetch_assoc()['c'],
            'assigned' => $db->query("SELECT COUNT(*) as c FROM assets WHERE status='assigned'")->fetch_assoc()['c'],
            'maintenance' => $db->query("SELECT COUNT(*) as c FROM assets WHERE status='in_maintenance'")->fetch_assoc()['c'],
            'total_value' => $db->query("SELECT SUM(purchase_cost) as c FROM assets")->fetch_assoc()['c'] ?? 0
        ];
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    case 'categories':
        $result = $db->query("SELECT * FROM asset_categories ORDER BY name");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $categories]);
        break;
}
