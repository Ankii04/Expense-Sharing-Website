<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?: 'dashboard.php'));
exit();
?>
