<?php
require_once 'config.php';
header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $employee_id = $_GET['employee_id'] ?? '';
        $asset_id = $_GET['asset_id'] ?? '';

        $sql = "SELECT aa.*,
                a.asset_tag, a.name as asset_name, c.name as category_name,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_id
                FROM asset_assignments aa
                JOIN assets a ON aa.asset_id = a.id
                JOIN asset_categories c ON a.category_id = c.id
                JOIN employees e ON aa.employee_id = e.id
                WHERE 1=1";

        if ($employee_id) $sql .= " AND aa.employee_id = " . intval($employee_id);
        if ($asset_id) $sql .= " AND aa.asset_id = " . intval($asset_id);

        $sql .= " ORDER BY aa.assigned_date DESC";
        $result = $db->query($sql);

        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $assignments]);
        break;

    case 'assign':
        $data = json_decode(file_get_contents('php://input'), true);

        $db->begin_transaction();
        try {
            $stmt = $db->prepare("INSERT INTO asset_assignments (asset_id, employee_id, assigned_date, expected_return_date, assigned_by, condition_on_assignment, notes) VALUES (?, ?, NOW(), ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $data['asset_id'], $data['employee_id'], $data['expected_return_date'], $data['assigned_by'], $data['condition_on_assignment'], $data['notes']);
            $stmt->execute();

            $db->query("UPDATE assets SET status='assigned' WHERE id=" . intval($data['asset_id']));
            $db->query("INSERT INTO asset_history (asset_id, action, description, performed_by) VALUES (" . intval($data['asset_id']) . ", 'assigned', 'Assigned to employee', '" . $db->real_escape_string($data['assigned_by']) . "')");

            $db->commit();
            echo json_encode(['success' => true, 'id' => $db->insert_id]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'return':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);

        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE asset_assignments SET returned_date=NOW(), condition_on_return=?, status='returned' WHERE id=?");
            $stmt->bind_param("si", $data['condition_on_return'], $id);
            $stmt->execute();

            $assignment = $db->query("SELECT asset_id FROM asset_assignments WHERE id=$id")->fetch_assoc();
            $db->query("UPDATE assets SET status='available' WHERE id=" . $assignment['asset_id']);
            $db->query("INSERT INTO asset_history (asset_id, action, description) VALUES (" . $assignment['asset_id'] . ", 'returned', 'Asset returned')");

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
