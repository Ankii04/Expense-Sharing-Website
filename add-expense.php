<?php
session_start();
require_once 'config.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    // Get and validate inputs
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['expense_type'] ?? 'other');
    $date = $_POST['date'] ?? date('Y-m-d');
    
    // Validate expense type
    $valid_types = ['food', 'clothes', 'travel', 'other'];
    if (!in_array($category, $valid_types)) {
        $category = 'other';
    }
    $split_with = $_POST['split_with'] ?? [];
    
    // Validate inputs
    if (!$group_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
        exit();
    }
    
    if (!$amount || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
        exit();
    }
    
    if (empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Description is required']);
        exit();
    }
    
    // If no one is selected to split with, make it a personal expense
    if (empty($split_with)) {
        $split_with = [$_SESSION['user_id']];
    }
    
    // For non-personal expenses, ensure the user is included in the split
    if (count($split_with) > 1 && !in_array($_SESSION['user_id'], $split_with)) {
        $split_with[] = $_SESSION['user_id'];
    }
    
    try {
        require_once 'functions.php';
        $pdo->beginTransaction();
        
        // Handle Receipt Upload (Item 3: Receipt scanning/attachment)
        $receipt_path = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/receipts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $receipt_path = $upload_dir . uniqid('receipt_') . '.' . $file_ext;
            move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt_path);
        }

        // Create expense
        $stmt = $pdo->prepare("INSERT INTO expenses (group_id, paid_by, amount, description, category, date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$group_id, $_SESSION['user_id'], $amount, $description, $category, $date]);
        $expense_id = $pdo->lastInsertId();
        
        // If receipt uploaded, record it
        if ($receipt_path) {
            $stmt = $pdo->prepare("INSERT INTO expense_attachments (expense_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$expense_id, basename($receipt_path), $receipt_path, $_SESSION['user_id']]);
        }

        // Calculate split amount
        $split_amount = $amount / count($split_with);
        
        // Create splits
        $stmt = $pdo->prepare("INSERT INTO expense_splits (expense_id, user_id, amount) VALUES (?, ?, ?)");
        foreach ($split_with as $user_id) {
            $stmt->execute([$expense_id, $user_id, $split_amount]);
        }
        
        // Item 1: Activity Log
        logActivity($pdo, $group_id, $_SESSION['user_id'], 'add_expense', "Added an expense: ₹" . number_format($amount, 2) . " for '{$description}'");
        
        // Notifications: Notify group members
        notifyGroup($pdo, $group_id, $_SESSION['user_id'], 'new_expense', "{$_SESSION['username']} added an expense of ₹" . number_format($amount, 2) . " to your group.");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error adding expense: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error adding expense. Please try again.']);
        exit();
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit();
