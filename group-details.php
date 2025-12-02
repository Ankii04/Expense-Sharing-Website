<?php
session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get group ID from URL
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get group details
$stmt = $pdo->prepare("
    SELECT g.*, u.name as creator_name 
    FROM `groups` g 
    JOIN users u ON g.created_by = u.id 
    WHERE g.id = ? AND g.id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
");
$stmt->execute([$group_id, $_SESSION['user_id']]);
$group = $stmt->fetch();

if (!$group) {
    header("Location: dashboard.php");
    exit();
}

// Get group members
$stmt = $pdo->prepare("
    SELECT u.id, u.name as name
    FROM users u 
    JOIN group_members gm ON u.id = gm.user_id 
    WHERE gm.group_id = ?
");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Get expenses
$stmt = $pdo->prepare("
    SELECT e.*, u.name as paid_by_name 
    FROM expenses e 
    JOIN users u ON e.paid_by = u.id 
    WHERE e.group_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$group_id]);
$expenses = $stmt->fetchAll();

// Get expense splits
foreach ($expenses as &$expense) {
    $stmt = $pdo->prepare("
        SELECT es.*, u.name as member_name 
        FROM expense_splits es 
        JOIN users u ON es.user_id = u.id 
        WHERE es.expense_id = ?
    ");
    $stmt->execute([$expense['id']]);
    $expense['splits'] = $stmt->fetchAll();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Details - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.08);
            --sidebar-bg: #ffffff;
            --sidebar-hover: #f1f5f9;
            --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --success-gradient: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --text-muted: #64748b;
            --link-color: #3b82f6;
            --header-bg: rgba(255, 255, 255, 0.8);
            --table-header: #f8fafc;
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --input-focus: #3b82f6;
        }

        [data-theme="dark"] {
            --bg-color: #1a1f2e;
            --text-color: #ffffff;
            --card-bg: #242b3d;
            --border-color: rgba(255, 255, 255, 0.15);
            --sidebar-bg: #1a1f2e;
            --sidebar-hover: #2f3a54;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --text-muted: #a0aec0;
            --link-color: #60a5fa;
            --header-bg: rgba(26, 31, 46, 0.95);
            --table-header: #242b3d;
            --input-bg: #242b3d;
            --input-border: #374151;
            --input-focus: #60a5fa;
            --modal-bg: #1a1f2e;
            --btn-text: #ffffff;
            --group-info-text: #e2e8f0;
            --group-title: #ffffff;
            --member-name: #ffffff;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .sidebar {
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            width: 280px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-brand {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
            font-size: 1.5rem;
            padding: 1.75rem 1.5rem;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .sidebar-link {
            color: var(--text-color);
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            margin: 0.25rem 0.75rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .sidebar-link:hover {
            background-color: var(--sidebar-hover);
            transform: translateX(5px);
            color: var(--link-color);
        }

        .sidebar-link.active {
            background: var(--primary-gradient);
            color: white;
        }

        .card {
            background-color: var(--card-bg);
            border: none;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .card-title {
            font-weight: 600;
            color: var(--text-color);
            font-size: 1.25rem;
            margin: 0;
        }

        .btn {
            border-radius: 10px;
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: #0d6efd !important;
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0b5ed7 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
            color: white;
        }

        .btn-danger {
            background: var(--danger-gradient);
            border: none;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .top-nav {
            background-color: var(--header-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .table {
            color: var(--text-color);
            border-radius: 12px;
            overflow: hidden;
            margin: 0;
        }

        .table thead th {
            background-color: var(--table-header);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            color: var(--text-muted);
        }

        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .form-control {
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background-color: var(--input-bg);
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            color: var(--text-color);
        }

        .input-group-text {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-muted);
        }

        .form-label {
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-check-input:checked {
            background-color: var(--link-color);
            border-color: var(--link-color);
        }

        .badge {
            padding: 0.5em 1em;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge.bg-primary {
            background: var(--primary-gradient) !important;
        }

        .list-group-item {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            padding: 1rem;
            color: var(--text-color);
        }

        .modal-content {
            background-color: var(--card-bg);
            border: none;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        /* Avatar styling */
        .avatar-circle {
            width: 32px;
            height: 32px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }

        .list-group-item .avatar-circle {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        /* Dark mode toggle button */
        #darkModeToggle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
        }

        #darkModeToggle:hover {
            background-color: var(--sidebar-hover);
            transform: scale(1.1);
        }

        #darkModeToggle i {
            font-size: 16px;
            transition: transform 0.5s ease;
        }

        [data-theme="dark"] #darkModeToggle i {
            transform: rotate(360deg);
        }

        /* Dark mode specific styles */
        [data-theme="dark"] {
            color-scheme: dark;
        }

        [data-theme="dark"] .card {
            background-color: var(--card-bg);
        }

        [data-theme="dark"] .form-control {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--input-border);
        }

        [data-theme="dark"] .form-control:focus {
            background-color: var(--input-bg);
            color: var(--text-color);
            border-color: var(--input-focus);
        }

        [data-theme="dark"] .modal-content {
            background-color: var(--modal-bg);
            color: var(--text-color);
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-theme="dark"] .table {
            color: var(--text-color);
        }

        [data-theme="dark"] .table thead th {
            background-color: var(--table-header);
            color: var(--text-muted);
        }

        [data-theme="dark"] .list-group-item {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .input-group-text {
            background-color: var(--input-bg);
            color: var(--text-muted);
            border-color: var(--input-border);
        }

        [data-theme="dark"] .sidebar {
            background-color: var(--sidebar-bg);
        }

        [data-theme="dark"] .top-nav {
            background-color: var(--header-bg);
        }

        [data-theme="dark"] .btn {
            color: var(--btn-text);
        }

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }

        .container-fluid {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .container-fluid {
                padding: 1rem;
            }
        }

        /* Custom scrollbar for better visibility */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-color);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--link-color);
        }

        .group-info {
            color: var(--text-color);
        }

        .group-title {
            color: var(--group-title);
            font-weight: 600;
        }

        .member-name {
            color: var(--member-name);
        }

        [data-theme="dark"] .group-info-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        [data-theme="dark"] .group-info-value {
            color: var(--group-info-text);
        }

        [data-theme="dark"] .member-item {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        [data-theme="dark"] .btn {
            color: var(--btn-text);
        }

        [data-theme="dark"] .btn-primary {
            background-color: #0d6efd !important;
            border: none;
            color: white;
        }

        [data-theme="dark"] .btn-primary:hover {
            background-color: #0b5ed7 !important;
        }

        [data-theme="dark"] .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
        }

        [data-theme="dark"] .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            border: none;
        }

        [data-theme="dark"] .modal-content {
            background-color: var(--modal-bg);
            color: var(--text-color);
        }

        [data-theme="dark"] .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        [data-theme="dark"] .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        /* Update the group information section */
        .group-info-section {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        [data-theme="dark"] .group-info-section {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
        }

        .group-info-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .group-info-label {
            min-width: 120px;
            font-weight: 500;
        }

        [data-theme="dark"] .group-members-section {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
        }

        [data-theme="dark"] .group-members-title {
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        [data-theme="dark"] .member-badge {
            background-color: var(--sidebar-hover);
            color: var(--text-color);
        }

        [data-theme="dark"] .admin-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        /* Ensure text visibility in all states */
        [data-theme="dark"] * {
            color-scheme: dark;
        }

        [data-theme="dark"] input::placeholder {
            color: var(--text-muted);
        }

        [data-theme="dark"] .form-control:disabled {
            background-color: var(--input-bg);
            color: var(--text-muted);
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: 500;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-link {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
        }

        .logout-link:hover {
            color: var(--link-color);
        }

        /* Update member avatars in the list */
        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 500;
            margin-right: 0.75rem;
        }

        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        [data-theme="dark"] .card-title,
        [data-theme="dark"] .group-title,
        [data-theme="dark"] .member-name,
        [data-theme="dark"] .user-name,
        [data-theme="dark"] .sidebar-brand,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] .form-label,
        [data-theme="dark"] .modal-title,
        [data-theme="dark"] .table,
        [data-theme="dark"] td,
        [data-theme="dark"] th,
        [data-theme="dark"] p,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .form-check-label {
            color: #ffffff !important;
        }

        [data-theme="dark"] .sidebar-link {
            color: #e2e8f0;
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .input-group-text {
            background-color: var(--input-bg);
            color: #ffffff;
            border-color: var(--input-border);
        }

        .input-group {
            width: 100%;
        }

        #amount {
            min-width: 150px;
            width: 100%;
        }

        .input-group .form-control {
            flex: 1;
        }
    </style>
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-wallet"></i> Expense Maker
            </a>
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item active">
                <a href="group-details.php?id=<?php echo $group_id; ?>" class="sidebar-link">
                    <i class="fas fa-users"></i> Group Details
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (isset($_SESSION['avatar']) && $_SESSION['avatar']): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($_SESSION['name'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?>
                    </div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <button class="sidebar-toggle d-md-none">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0">Group Details</h4>
                    </div>
                    <div class="d-flex align-items-center">
                        <button id="darkModeToggle" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Group Management Buttons -->
        <div class="container-fluid mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($group['name']); ?></h5>
                    <div class="d-flex gap-2">
                        <a href="invite-members.php?id=<?php echo $group_id; ?>" class="btn btn-info">
                            <i class="fas fa-user-plus"></i> Invite Members
                        </a>
                        <a href="group-history.php?id=<?php echo $group_id; ?>" class="btn btn-primary">
                            <i class="fas fa-history"></i> History
                        </a>
                        <?php if ($group['created_by'] == $_SESSION['user_id']): ?>
                        <button type="button" class="btn btn-danger" onclick="deleteGroup(<?php echo $group_id; ?>)">
                            <i class="fas fa-trash"></i> Delete Group
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Group Details Content -->
                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#settleUpModal">
                            <i class="fas fa-money-bill-wave me-2"></i>Settle Up
                        </button>
                    </div>

                    <!-- Settlements Section -->
                    <?php // include 'settle-button.php'; // File doesn't exist ?>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6>Group Information</h6>
                            <p><strong>Created by:</strong> <?php echo htmlspecialchars($group['creator_name'] ?? ''); ?></p>
                            <p><strong>Created on:</strong> <?php echo date('F j, Y', strtotime($group['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Group Members</h6>
                            <ul class="list-group">
                                <?php foreach ($members as $member): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="member-avatar">
                                            <?php if ($member['avatar']): ?>
                                                    <img src="<?php echo htmlspecialchars($member['avatar'] ?? ''); ?>" alt="Profile">
                                            <?php else: ?>
                                                    <span><?php echo strtoupper(substr($member['name'] ?? 'U', 0, 1)); ?></span>
                                            <?php endif; ?>
                                            </div>
                                            <span class="member-name"><?php echo htmlspecialchars($member['name'] ?? ''); ?></span>
                                        </div>
                                        <?php if ($member['id'] == $group['created_by']): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rest of the content -->
        <div class="container-fluid mt-4">
            <!-- Add New Expense -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Expense</h5>
                </div>
                <div class="card-body">
                    <form id="addExpenseForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <input type="text" class="form-control" id="description" name="description" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                    <label for="amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                            <label class="form-label">Split Between</label>
                                <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($members as $member): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="split_with[]" 
                                                   value="<?php echo $member['id']; ?>" 
                                                   id="member<?php echo $member['id']; ?>"
                                                   <?php echo ($member['id'] == $_SESSION['user_id']) ? 'checked disabled' : ''; ?>>
                                            <label class="form-check-label" for="member<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="addExpenseBtn">
                            Add Expense
                        </button>
                    </form>
                </div>
            </div>

            <!-- Expenses List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Paid By</th>
                                    <th>Split Between</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($expense['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                        <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($expense['paid_by_name']); ?></td>
                                        <td>
                                            <?php
                                            $split_members = [];
                                            foreach ($expense['splits'] as $split) {
                                                $split_members[] = htmlspecialchars($split['member_name']);
                                            }
                                            echo implode(', ', $split_members);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($expense['paid_by'] !== $_SESSION['user_id']): ?>
                                                <?php
                                                $user_split = array_filter($expense['splits'], function($split) {
                                                    return $split['user_id'] == $_SESSION['user_id'];
                                                });
                                                $user_split = reset($user_split);
                                                if ($user_split && !isset($user_split['paid_at'])):
                                                ?>
                                                    <button class="btn btn-success btn-sm pay-button" 
                                                            data-expense-id="<?php echo $expense['id']; ?>"
                                                            data-amount="<?php echo $user_split['amount']; ?>"
                                                            data-description="<?php echo htmlspecialchars($expense['description']); ?>">
                                                        Pay ₹<?php echo number_format($user_split['amount'], 2); ?>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Group Modal -->
    <div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteGroupModalLabel">Delete Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">Warning: This action cannot be undone. All expenses and settlements in this group will be permanently deleted.</p>
                    <p>Are you sure you want to delete this group?</p>
                </div>
                <form method="POST" action="group-actions.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="deleteGroupBtn" class="btn btn-danger">
                            <span class="normal-text">Delete Group</span>
                            <span class="loading-text d-none">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                Deleting...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved dark mode preference
            const darkMode = localStorage.getItem('darkMode') === 'true';
            if (darkMode) {
                htmlElement.setAttribute('data-theme', 'dark');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Toggle dark mode
            darkModeToggle.addEventListener('click', function() {
                const isDark = htmlElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    htmlElement.removeAttribute('data-theme');
                    localStorage.setItem('darkMode', 'false');
                    this.innerHTML = '<i class="fas fa-moon"></i>';
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('darkMode', 'true');
                    this.innerHTML = '<i class="fas fa-sun"></i>';
                }
            });

            // Expense form handling
            const form = document.getElementById('addExpenseForm');
            const button = document.getElementById('addExpenseBtn');

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(this);
                    
                    // Validate split selection
                    const splitChecks = document.querySelectorAll('input[name="split_with[]"]:checked');
                    if (splitChecks.length === 0) {
                        alert('Please select at least one person to split with');
                        return;
                    }

                    // Disable button and show loading state
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

                    // Send request
                    fetch('add-expense.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            throw new Error(data.message || 'Error adding expense');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert(error.message || 'Error adding expense');
                    })
                    .finally(() => {
                        // Always reset button state
                        button.disabled = false;
                        button.innerHTML = 'Add Expense';
                    });
                });

                // Make sure the current user is always included in split
                document.querySelectorAll('input[name="split_with[]"]').forEach(checkbox => {
                    if (checkbox.disabled) {
                        checkbox.checked = true;
                    }
                });
            }

            // Payment handling
            document.querySelectorAll('.pay-button').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.dataset.expenseId;
                    const amount = parseFloat(this.dataset.amount);
                    const description = this.dataset.description;
                    
                    // Create Razorpay order
                    fetch('create-order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            amount: amount * 100, // Convert to paise
                            expense_id: expenseId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const options = {
                                key: '<?php echo RAZORPAY_KEY_ID; ?>', // Replace with your key
                                amount: amount * 100,
                                currency: 'INR',
                                name: 'Expense Maker',
                                description: `Payment for ${description}`,
                                order_id: data.order_id,
                                handler: function(response) {
                                    // Verify payment
                                    fetch('verify-payment.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                        },
                                        body: JSON.stringify({
                                            razorpay_payment_id: response.razorpay_payment_id,
                                            razorpay_order_id: response.razorpay_order_id,
                                            razorpay_signature: response.razorpay_signature,
                                            expense_id: expenseId
                                        })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('Payment successful!');
                                            window.location.reload();
                                        } else {
                                            alert('Payment verification failed. Please contact support.');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error verifying payment. Please contact support.');
                                    });
                                },
                                prefill: {
                                    name: '<?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>',
                                    email: '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>'
                                },
                                theme: {
                                    color: '#0d6efd'
                                }
                            };
                            const rzp = new Razorpay(options);
                            rzp.open();
                        } else {
                            alert('Error creating payment order. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error creating payment order. Please try again.');
                    });
                });
            });

            // Delete group functionality
            window.deleteGroup = function(groupId) {
                const modal = new bootstrap.Modal(document.getElementById('deleteGroupModal'));
                modal.show();
            };

            // Handle delete form submission
            const deleteForm = document.querySelector('#deleteGroupModal form');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const button = document.getElementById('deleteGroupBtn');
                    const normalText = button.querySelector('.normal-text');
                    const loadingText = button.querySelector('.loading-text');
                    
                    // Disable button and show loading state
                    button.disabled = true;
                    normalText.classList.add('d-none');
                    loadingText.classList.remove('d-none');

                    // Add CSRF token to form data
                    const formData = new FormData(this);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                    // Submit form
                    fetch('group-actions.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'dashboard.php';
                        } else {
                            throw new Error(data.message || 'Failed to delete group');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert(error.message || 'Error deleting group. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        button.disabled = false;
                        normalText.classList.remove('d-none');
                        loadingText.classList.add('d-none');
                        
                        // Close the modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteGroupModal'));
                        if (modal) {
                            modal.hide();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>