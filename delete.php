<?php
include 'config.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the raw POST data
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

if (isset($data['ids']) && is_array($data['ids'])) {
    // Delete multiple records
    $ids = $data['ids'];
    $stmt = $conn->prepare("DELETE FROM zeiterfassung WHERE id = ? AND user_id = ?");
    $deletedCount = 0;

    foreach ($ids as $id) {
        $stmt->execute([$id, $user_id]);
        $deletedCount += $stmt->rowCount();
    }

    if ($deletedCount > 0) {
        echo json_encode(['success' => true, 'message' => "$deletedCount record(s) deleted successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No records were deleted. They may not exist or you don\'t have permission.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
}
?>
