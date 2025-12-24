<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$group_id = $_GET['id'] ?? null;

if (!$group_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify user is a member of the group
$stmt = $pdo->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$group_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    die("Access denied");
}

// Get group details
$stmt = $pdo->prepare("SELECT * FROM `groups` WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter an email address";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if already a member
            $stmt = $pdo->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user['id']]);
            
            if ($stmt->fetch()) {
                $error = "User is already a member of this group";
            } else {
                // Add member
                $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                if ($stmt->execute([$group_id, $user['id']])) {
                    $success = "Member added successfully!";
                } else {
                    $error = "Error adding member";
                }
            }
        } else {
            $error = "User with this email not found. Please ask them to register first.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite Members - <?php echo htmlspecialchars($group['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="dashboard-body">
    <?php include 'navbar.php'; ?>

    <div class="content-wrapper">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card card-premium shadow">
                    <div class="card-header bg-transparent border-0 pt-4">
                        <h5 class="fw-bold mb-0 text-center text-primary">
                            Invite Members to <?php echo htmlspecialchars($group['name']); ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 shadow-sm"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-envelope text-primary"></i></span>
                                    <input type="email" class="form-control bg-light border-0" id="email" name="email" required placeholder="Enter user's email">
                                </div>
                                <div class="form-text mt-2 small">The user must already have an account to be added.</div>
                            </div>
                            <button type="submit" class="btn btn-premium btn-primary-premium w-100 py-3">
                                <i class="fas fa-paper-plane"></i> Send Invitation
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- .content-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
