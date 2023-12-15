<?php
include 'config.php';

// Check if an ID has been sent
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Prepare a statement to delete the record with the given ID
    $stmt = $conn->prepare('DELETE FROM zeiterfassung WHERE id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    // Execute the statement and check if the execution was successful
    if ($stmt->execute()) {
        // Check if any rows were affected
        $affectedRows = $stmt->rowCount();
        if ($affectedRows == 0) {
            die("No record found with ID $id.");
        }
    } else {
        // Error handling for failed deletion
        die("Error deleting the record: " . $stmt->errorInfo()[2]);
    }

    // Redirect to the main page after successful deletion
    header("Location: index.php");
    exit;
} else {
    // Handling the case when no ID is provided
    die("ID not provided.");
}
?>