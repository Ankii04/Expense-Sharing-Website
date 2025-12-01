<?php
session_start();
require_once 'config.php';
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get group ID
$group_id = $_POST['group_id'] ?? null;
if (!$group_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Group ID is required']);
    exit();
}

try {
    // Check if user is creator of the group
    $stmt = $pdo->prepare("
        SELECT created_by 
        FROM groups 
        WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$group_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authorized to delete this group']);
        exit();
    }

    // Start transaction
    $pdo->beginTransaction();

    // Delete all related records
    // The order matters due to foreign key constraints
    
    // Delete expense attachments
    $stmt = $pdo->prepare("
        DELETE FROM expense_attachments 
        WHERE expense_id IN (
            SELECT id FROM expenses WHERE group_id = ?
        )
    ");
    $stmt->execute([$group_id]);

    // Delete expense splits
    $stmt = $pdo->prepare("
        DELETE FROM expense_splits 
        WHERE expense_id IN (
            SELECT id FROM expenses WHERE group_id = ?
        )
    ");
    $stmt->execute([$group_id]);

    // Delete expenses
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE group_id = ?");
    $stmt->execute([$group_id]);

    // Delete group invites
    $stmt = $pdo->prepare("DELETE FROM group_invites WHERE group_id = ?");
    $stmt->execute([$group_id]);

    // Delete group members
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
    $stmt->execute([$group_id]);

    // Finally, delete the group
    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'Group deleted successfully';
    header('Location: dashboard.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error deleting group: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting group']);
    exit();
}
