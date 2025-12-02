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
        $pdo->beginTransaction();
        
        // Create expense
        $stmt = $pdo->prepare("INSERT INTO expenses (group_id, paid_by, amount, description, category, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$group_id, $_SESSION['user_id'], $amount, $description, $category]);
        $expense_id = $pdo->lastInsertId();
        
        // Calculate split amount
        $split_amount = $amount / count($split_with);
        
        // Create splits
        $stmt = $pdo->prepare("INSERT INTO expense_splits (expense_id, user_id, amount) VALUES (?, ?, ?)");
        foreach ($split_with as $user_id) {
            $stmt->execute([$expense_id, $user_id, $split_amount]);
        }
        
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
