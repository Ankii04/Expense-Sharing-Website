<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update user profile
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $currency = $_POST['currency'];
            $timezone = $_POST['timezone'];

            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, currency = ?, timezone = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $currency, $timezone, $user_id]);
            $success_message = "Profile updated successfully!";
        }

        // Change password
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "New passwords do not match!";
                }
            } else {
                $error_message = "Current password is incorrect!";
            }
        }

        // Update notification preferences
        if (isset($_POST['update_notifications'])) {
            $success_message = "Notification preferences updated successfully!";
        }

        // Update Avatar
        if (isset($_POST['update_avatar'])) {
            $avatar_url = $_POST['avatar_url'];
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$avatar_url, $user_id]);
            $_SESSION['avatar_url'] = $avatar_url;
            $success_message = "Avatar updated successfully!";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current user data
$stmt = $pdo->prepare("
    SELECT u.*
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button class="sidebar-toggle btn-icon">
                            <i class="fas fa-bars fa-lg"></i>
                        </button>
                        <h4 class="mb-0 fw-bold text-primary d-none d-sm-block">
                             Settings
                        </h4>
                    </div>
                    <div class="d-flex align-items-center">
                        <button id="themeToggle" class="btn-icon">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <div class="content-container">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <h2 class="card-title mb-4">
                                <i class="fas fa-cog me-2 text-primary"></i>
                                Account Settings
                            </h2>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Profile Settings -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h4 class="card-title">Profile Settings</h4>
                                <form method="POST" class="mt-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" name="currency">
                                                <option value="INR" <?php echo ($user['currency'] ?? 'INR') === 'INR' ? 'selected' : ''; ?>>Indian Rupee (₹)</option>
                                                <option value="USD" <?php echo ($user['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                <option value="EUR" <?php echo ($user['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                                <option value="GBP" <?php echo ($user['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <?php
                                                $timezones = DateTimeZone::listIdentifiers();
                                                foreach ($timezones as $tz) {
                                                    $selected = ($user['timezone'] ?? 'Asia/Kolkata') === $tz ? 'selected' : '';
                                                    echo "<option value=\"$tz\" $selected>$tz</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>

                        <!-- Avatar Settings -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h4 class="card-title">Avatar Settings</h4>
                                <p class="text-muted small">Choose an avatar to represent you in groups and activity logs.</p>
                                <form method="POST" class="mt-4">
                                    <div class="d-flex flex-wrap gap-4 mb-4 avatar-selection">
                                        <?php 
                                        $avatars = [
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Aneka',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Amaya',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=James',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Willow',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Luna',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Milo',
                                            'https://api.dicebear.com/7.x/avataaars/svg?seed=Zoe'
                                        ];
                                        foreach ($avatars as $url): ?>
                                            <label class="avatar-option">
                                                <input type="radio" name="avatar_url" value="<?php echo $url; ?>" <?php echo (($user['avatar_url'] ?? '') === $url) ? 'checked' : ''; ?> class="d-none">
                                                <div class="avatar-wrapper p-1 rounded-circle border-2 <?php echo (($user['avatar_url'] ?? '') === $url) ? 'border-primary' : 'border-transparent'; ?>">
                                                    <img src="<?php echo $url; ?>" alt="Avatar" class="rounded-circle shadow-sm" width="70" height="70">
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" name="update_avatar" class="btn btn-primary">Save Selected Avatar</button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h4 class="card-title">Change Password</h4>
                                <form method="POST" class="mt-4">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <!-- Notification settings section removed -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- .content-container -->
    </div><!-- .main-content -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
