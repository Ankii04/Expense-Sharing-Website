<?php
session_start();
require_once 'config.php';

$group_id = $_GET['group_id'] ?? null;
if (!$group_id) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("
    SELECT u.id, COALESCE(u.name, u.username) as name 
    FROM users u
    JOIN group_members gm ON u.id = gm.user_id
    WHERE gm.group_id = ?
");
$stmt->execute([$group_id]);
echo json_encode($stmt->fetchAll());
?>
