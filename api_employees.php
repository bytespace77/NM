<?php
require_once 'config.php';
header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT e.*,
                (SELECT COUNT(*) FROM asset_assignments aa WHERE aa.employee_id = e.id AND aa.status = 'active') as active_assets,
                CONCAT(m.first_name, ' ', m.last_name) as manager_name
                FROM employees e
                LEFT JOIN employees m ON e.manager_id = m.id
                WHERE 1=1";

        if ($status) $sql .= " AND e.status = '" . $db->real_escape_string($status) . "'";
        if ($search) {
            $search = $db->real_escape_string($search);
            $sql .= " AND (e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%' OR e.email LIKE '%$search%' OR e.employee_id LIKE '%$search%')";
        }

        $sql .= " ORDER BY e.created_at DESC";
        $result = $db->query($sql);

        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $employees]);
        break;

    case 'get':
        $id = intval($_GET['id']);
        $result = $db->query("SELECT e.*, CONCAT(m.first_name, ' ', m.last_name) as manager_name
                              FROM employees e
                              LEFT JOIN employees m ON e.manager_id = m.id
                              WHERE e.id = $id");

        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
        }
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $db->prepare("INSERT INTO employees (employee_id, first_name, last_name, email, phone, department, position, manager_id, hire_date, status, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssissss",
            $data['employee_id'], $data['first_name'], $data['last_name'], $data['email'],
            $data['phone'], $data['department'], $data['position'], $data['manager_id'],
            $data['hire_date'], $data['status'], $data['location'], $data['notes']
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $db->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);

        $stmt = $db->prepare("UPDATE employees SET first_name=?, last_name=?, email=?, phone=?, department=?, position=?, manager_id=?, hire_date=?, termination_date=?, status=?, location=?, notes=? WHERE id=?");
        $stmt->bind_param("ssssssisssssi",
            $data['first_name'], $data['last_name'], $data['email'], $data['phone'],
            $data['department'], $data['position'], $data['manager_id'], $data['hire_date'],
            $data['termination_date'], $data['status'], $data['location'], $data['notes'], $id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'delete':
        $id = intval($_GET['id']);
        if ($db->query("DELETE FROM employees WHERE id = $id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $db->error]);
        }
        break;

    case 'stats':
        $stats = [
            'total' => $db->query("SELECT COUNT(*) as c FROM employees")->fetch_assoc()['c'],
            'active' => $db->query("SELECT COUNT(*) as c FROM employees WHERE status='active'")->fetch_assoc()['c'],
            'onboarding' => $db->query("SELECT COUNT(*) as c FROM employees WHERE status='onboarding'")->fetch_assoc()['c'],
            'offboarding' => $db->query("SELECT COUNT(*) as c FROM employees WHERE status='offboarding'")->fetch_assoc()['c']
        ];
        echo json_encode(['success' => true, 'data' => $stats]);
        break;
}
