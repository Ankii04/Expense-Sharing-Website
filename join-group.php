<?php
session_start();

require_once 'config.php';

$error = null;
$success = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Accept invite code from POST (form) or GET (link)
$token = isset($_GET['token']) ? trim($_GET['token']) : null;
$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_code'])) {
    $code = strtoupper(trim($_POST['invite_code']));
}
// New logic: if token is set and is 8 chars, treat as code
if (!empty($token) && strlen($token) === 8) {
    $code = strtoupper($token);
    $token = null;
}

if (!empty($token) || !empty($code)) {
    $where_clause = $token ? 'gi.token = ?' : 'gi.invite_code = ?';
    $param = $token ? $token : $code;
    
    $debug_info = [];
    $debug_info['user_id'] = $_SESSION['user_id'];
    $debug_info['user_email'] = isset($_SESSION['email']) ? $_SESSION['email'] : null;
    $debug_info['param'] = $param;
    $debug_info['where_clause'] = $where_clause;
    $debug_info['method'] = $token ? 'token' : 'invite_code';
    $debug_info['sql'] = "SELECT gi.*, g.name as group_name, COALESCE(u.name, u.username) as inviter_name, g.id as group_id FROM group_invites gi JOIN `groups` g ON gi.group_id = g.id LEFT JOIN users u ON gi.invited_by = u.id WHERE $where_clause AND gi.status = 'pending' AND gi.expires_at > CURRENT_TIMESTAMP";
    
    error_log('Attempting to join with token: ' . $token);
    
    try {
        // Get invitation details
        $stmt = $pdo->prepare("
            SELECT gi.*, g.name as group_name, 
                   COALESCE(u.name, u.username) as inviter_name,
                   g.id as group_id
            FROM group_invites gi
            JOIN `groups` g ON gi.group_id = g.id
            LEFT JOIN users u ON gi.invited_by = u.id
            WHERE $where_clause
            AND gi.status = 'pending'
            AND gi.expires_at > CURRENT_TIMESTAMP
        ");
        $query = $stmt->queryString;
        error_log('SQL Query: ' . $query);
        error_log('Parameter value: ' . $param);
        $stmt->execute([$param]);
        $invite = $stmt->fetch();
        $debug_info['invite_found'] = $invite ? 'yes' : 'no';
        $debug_info['invite'] = $invite;
        
        error_log('Invite found: ' . ($invite ? 'Yes' : 'No'));
        if ($invite) {
            error_log('Invite details: ' . print_r($invite, true));
        }

        if (!$invite) {
            // Check if invite exists but is invalid
            $stmt = $pdo->prepare("
                SELECT gi.*, g.name as group_name, 
                       COALESCE(u.name, u.username) as inviter_name,
                       gi.status,
                       gi.expires_at
                FROM group_invites gi
                JOIN `groups` g ON gi.group_id = g.id
                LEFT JOIN users u ON gi.invited_by = u.id
                WHERE $where_clause
            ");
            error_log('Debug SQL Query: ' . $stmt->queryString);
            error_log('Debug Parameter value: ' . $param);
            $stmt->execute([$param]);
            $debug_invite = $stmt->fetch();
            $debug_info['debug_invite'] = $debug_invite;
            
            error_log('Debug invite found: ' . ($debug_invite ? 'Yes' : 'No'));
            if ($debug_invite) {
                error_log('Debug invite details: ' . print_r($debug_invite, true));
                error_log('Current status: ' . $debug_invite['status']);
                error_log('Expiry time: ' . $debug_invite['expires_at']);
                $current_time = date('Y-m-d H:i:s');
                error_log('Current time: ' . $current_time);
                
                if ($debug_invite['status'] !== 'pending') {
                    throw new Exception('This invitation has already been used (Status: ' . $debug_invite['status'] . ')');
                }
                if ($debug_invite['expires_at'] <= $current_time) {
                    throw new Exception('This invitation has expired (Expired at: ' . $debug_invite['expires_at'] . ')');
                }
            } else {
                error_log('Invalid invitation token: ' . $param);
                throw new Exception('Invalid invitation token or code');
            }
        }

        // For email invites, verify email matches
        if ($invite['email'] && $invite['email'] !== 'general_invite@example.com' && $invite['email'] !== $_SESSION['email']) {
            throw new Exception('This invitation was sent to a different email address. Please log in with the invited email.');
        }

        // Add user to group and update invitation status
        $pdo->beginTransaction();

        try {
            error_log('Starting transaction to add user ' . $_SESSION['user_id'] . ' to group ' . $invite['group_id']);

            // First check if user is already a member
            $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$invite['group_id'], $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $_SESSION['info'] = 'You are already a member of this group.';
                header("Location: group-details.php?id=" . $invite['group_id']);
                exit();
            }

            // Add user to group
            $stmt = $pdo->prepare("
                INSERT INTO group_members (group_id, user_id, role, joined_at)
                VALUES (?, ?, 'member', NOW())
            ");
            $stmt->execute([$invite['group_id'], $_SESSION['user_id']]);
            error_log('Added user to group_members');

            // Update invitation status
            $stmt = $pdo->prepare("
                UPDATE group_invites 
                SET status = 'accepted', 
                    accepted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$invite['id']]);
            error_log('Updated invitation status');

            $pdo->commit();
            error_log('Transaction committed successfully');
            
            $_SESSION['success'] = 'Successfully joined ' . htmlspecialchars($invite['group_name']);
            header("Location: group-details.php?id=" . $invite['group_id']);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error in transaction: ' . $e->getMessage());
            throw $e;
        }
    } catch (Exception $e) {
        error_log('Error in join-group.php: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Group - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php // include 'navbar.php'; // File doesn't exist - using dashboard sidebar instead ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                        <?php else: ?>
                            <h2 class="mb-4">Join a Group</h2>
                            <p class="text-muted mb-4">Enter your invitation code below:</p>
                            <form method="POST" class="mb-4">
                                <div class="input-group mb-3">
                                    <input type="text" name="invite_code" class="form-control" placeholder="Enter invitation code" required>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Join Group
                                    </button>
                                </div>
                            </form>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($debug_info)): ?>
        <div class="alert alert-secondary mt-4"><strong>Debug: Join Attempt</strong><br><pre><?php echo htmlspecialchars(print_r($debug_info, true)); ?></pre></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
