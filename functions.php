<?php
// functions.php

/**
 * Log an activity for a group
 */
function logActivity($pdo, $group_id, $user_id, $action_type, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (group_id, user_id, action_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$group_id, $user_id, $action_type, $description]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Send a notification to all members of a group except the actor
 */
function notifyGroup($pdo, $group_id, $actor_id, $type, $message) {
    try {
        // Get all group members except the actor
        $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ?");
        $stmt->execute([$group_id, $actor_id]);
        $members = $stmt->fetchAll();

        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, group_id, type, message) VALUES (?, ?, ?, ?)");
        foreach ($members as $member) {
            $stmtNotify->execute([$member['user_id'], $group_id, $type, $message]);
        }
    } catch (Exception $e) {
        error_log("Failed to send group notifications: " . $e->getMessage());
    }
}

/**
 * Get unread notifications for a user
 */
function getUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}
?>
