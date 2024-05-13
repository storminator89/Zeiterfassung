<?php
include 'config.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if an ID has been sent
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Prepare a statement to delete the record with the given ID and user_id
    $stmt = $conn->prepare('DELETE FROM zeiterfassung WHERE id = :id AND user_id = :user_id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    // Execute the statement and check if the execution was successful
    if ($stmt->execute()) {
        // Check if any rows were affected
        $affectedRows = $stmt->rowCount();
        if ($affectedRows == 0) {
            die("No record found with ID $id or you don't have permission to delete this record.");
        }
    } else {
        // Error handling for failed deletion
        die("Error deleting the record: " . $stmt->errorInfo()[2]);
    }

    // Redirect to the main page after successful deletion
    header("Location: index.php");
    exit;
} elseif (isset($_POST["ids"])) {
    // Delete multiple records
    $ids = $_POST["ids"];

    // Prepare a statement to delete records with the given IDs and user_id
    $stmt = $conn->prepare("DELETE FROM zeiterfassung WHERE id = ? AND user_id = ?");

    foreach ($ids as $id) {
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Redirect to the main page after successful deletion
    header("Location: index.php");
    exit;
} else {
    // Handling the case when no ID is provided
    die("ID not provided.");
}
?>
