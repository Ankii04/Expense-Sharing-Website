<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    if (!empty($group_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, created_by) VALUES (?, ?)");
            $stmt->execute([$group_name, $_SESSION['user_id']]);
            
            $group_id = $pdo->lastInsertId();
            
            // Add creator as a member
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt->execute([$group_id, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Group created successfully!";
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error creating group: " . $e->getMessage();
        }
    }
}

// Handle expense creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_expense'])) {
    $group_id = $_POST['group_id'];
    $amount = $_POST['amount'];
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    
    if (!empty($amount) && !empty($date)) {
        try {
            $pdo->beginTransaction();
            
            // Create expense
            $stmt = $pdo->prepare("INSERT INTO expenses (group_id, paid_by, amount, description, date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$group_id, $_SESSION['user_id'], $amount, $description, $date]);
            $expense_id = $pdo->lastInsertId();
            
            // Get group members
            $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Calculate split amount
            $split_amount = $amount / count($members);
            
            // Create splits
            $stmt = $pdo->prepare("INSERT INTO expense_splits (expense_id, user_id, amount) VALUES (?, ?, ?)");
            foreach ($members as $member_id) {
                $stmt->execute([$expense_id, $member_id, $split_amount]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Expense added successfully!";
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error creating expense: " . $e->getMessage();
        }
    }
}

// Get user's groups
$stmt = $pdo->prepare("
    SELECT g.*, u.name as creator_name 
    FROM `groups` g 
    JOIN users u ON g.created_by = u.id 
    WHERE g.id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
");
$stmt->execute([$_SESSION['user_id']]);
$groups = $stmt->fetchAll();

// Get user's expenses with monthly totals
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(e.date, '%Y-%m') as month,
        COALESCE(e.category, 'Other') as category,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as amount_spent,
        SUM(CASE WHEN e.paid_by = ? THEN e.amount ELSE 0 END) as amount_paid,
        COUNT(DISTINCT e.id) as transaction_count
    FROM expenses e 
    JOIN expense_splits es ON e.id = es.expense_id
    WHERE e.group_id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
    GROUP BY DATE_FORMAT(e.date, '%Y-%m'), COALESCE(e.category, 'Other')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$monthly_expenses = $stmt->fetchAll();

// Get category distribution
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(e.category, 'Other') as category,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as amount
    FROM expenses e 
    JOIN expense_splits es ON e.id = es.expense_id
    WHERE e.group_id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
    GROUP BY e.category
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$category_expenses = $stmt->fetchAll();

// Get recent expenses
$stmt = $pdo->prepare("
    SELECT e.*, g.name as group_name, u.name as paid_by_name 
    FROM expenses e 
    JOIN `groups` g ON e.group_id = g.id 
    JOIN users u ON e.paid_by = u.id 
    WHERE e.group_id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
    ORDER BY e.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$expenses = $stmt->fetchAll();

// Calculate total expenses
$total_expenses = 0;
foreach ($expenses as $expense) {
    $total_expenses += $expense['amount'];
}

// Get pending amounts (simplified version)
$stmt = $pdo->prepare("
    SELECT SUM(es.amount) as total_owed
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    WHERE es.user_id = ? AND e.paid_by != ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$owed = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT SUM(es.amount) as total_owes
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    WHERE es.user_id != ? AND e.paid_by = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$owes = $stmt->fetch();

$total_owed = $owed['total_owed'] ?? 0;
$total_owes = $owes['total_owes'] ?? 0;
$balance = $total_owes - $total_owed;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <!-- Add Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li class="sidebar-item active">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    <i class="fas fa-users"></i> Create Group
                </a>
            </li>
            <li class="sidebar-item">
                <?php
                // Get first group ID for My Groups link
                $firstGroup = !empty($groups) ? $groups[0]['id'] : '';
                ?>
                <a href="<?php echo !empty($firstGroup) ? "group-details.php?id=" . $firstGroup : "#"; ?>" class="sidebar-link">
                    <i class="fas fa-layer-group"></i> My Groups
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fas fa-plus-circle"></i> Add Expense
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-pie"></i> Reports
                </a>
            </li>
            <li class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>
        <div style="flex: 1;"></div>
        <div class="sidebar-bottom">
            <button class="btn btn-primary w-100 mb-4" data-bs-toggle="modal" data-bs-target="#joinGroupModal">
                <i class="fas fa-user-plus"></i> Join Group
            </button>
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                        $display_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'U';
                        echo htmlspecialchars(strtoupper(substr($display_name, 0, 1))); 
                    ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?>
                    </div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header Bar -->
        <div class="top-nav bg-white border-bottom">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div class="d-flex align-items-center gap-3">
                        <button class="sidebar-toggle btn btn-link text-dark p-0">
                            <i class="fas fa-bars fa-lg"></i>
                        </button>
                        <h4 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-receipt me-2"></i>Expense Sharing
                        </h4>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-sm btn-outline-primary" id="themeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <span class="badge bg-danger">3</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><a class="dropdown-item" href="#">New expense added</a></li>
                                <li><a class="dropdown-item" href="#">John settled up with you</a></li>
                                <li><a class="dropdown-item" href="#">Sarah added you to a new group</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="container-fluid py-4">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="welcome-section mb-4">
                <h1 class="h3">Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?>!</h1>
                <p class="text-muted">Here's what's happening with your expenses today.</p>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="stat-title">Total Expenses</h6>
                                    <h3 class="stat-value">₹<?php echo number_format($total_expenses, 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="stat-title">You Are Owed</h6>
                                    <h3 class="stat-value">₹<?php echo number_format($total_owes, 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="stat-title">You Owe</h6>
                                    <h3 class="stat-value">₹<?php echo number_format($total_owed, 2); ?></h3>
                                </div>
                                <div class="stat-icon bg-danger">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="stat-title">Balance</h6>
                                    <h3 class="stat-value <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        ₹<?php echo number_format(abs($balance), 2); ?>
                                        <?php echo $balance >= 0 ? '↑' : '↓'; ?>
                                    </h3>
                                </div>
                                <div class="stat-icon <?php echo $balance >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Expense Overview</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm dropdown-toggle" type="button" id="chartDropdown" data-bs-toggle="dropdown">
                                    This Month
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">This Week</a></li>
                                    <li><a class="dropdown-item" href="#">This Month</a></li>
                                    <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                                    <li><a class="dropdown-item" href="#">This Year</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="expenseChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Expense Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="distributionChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Your Groups Section -->
            <div class="container-fluid mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Your Groups</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="fas fa-plus"></i> New Group
                    </button>
                </div>

                <div class="row">
                    <?php if (empty($groups)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You haven't joined any groups yet. Create a new group or join an existing one!
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <a href="group-details.php?id=<?php echo $group['id']; ?>" class="text-decoration-none">
                                    <div class="card h-100 group-card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="group-avatar">
                                                    <?php echo strtoupper(substr($group['name'], 0, 1)); ?>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($group['name']); ?></h5>
                                                    <p class="card-text text-muted mb-0">
                                                        Created by <?php echo htmlspecialchars($group['creator_name'] ?? ''); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Expenses Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Expenses</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                            <i class="fas fa-plus"></i> Add Expense
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($expenses)): ?>
                            <div class="p-4 text-center">
                                <div class="empty-state">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <h5>No Expenses Yet</h5>
                                    <p class="text-muted">Add your first expense to start tracking.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                        Add Your First Expense
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Group</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Paid By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($expenses, 0, 5) as $expense): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['group_name']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['description'] ?? ''); ?></td>
                                                <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['paid_by_name'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($expenses) > 5): ?>
                                <div class="card-footer text-center">
                                    <a href="#" class="btn btn-sm btn-link">View All Expenses</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" placeholder="e.g., Trip to Paris, Roommates" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Group Type</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="group_type" id="typeEqual" value="equal" checked>
                                    <label class="form-check-label" for="typeEqual">
                                        Equal Split
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="group_type" id="typePercentage" value="percentage">
                                    <label class="form-check-label" for="typePercentage">
                                        Percentage Split
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="group_type" id="typeCustom" value="custom">
                                    <label class="form-check-label" for="typeCustom">
                                        Custom Split
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Join Group Modal -->
    <div class="modal fade" id="joinGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Join a Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="joinGroupForm" action="join-group.php" method="GET">
                        <div class="mb-3">
                            <label for="inviteCode" class="form-label">Invitation Code</label>
                            <input type="text" class="form-control" id="inviteCode" name="token" required>
                            <div class="form-text">Enter the invitation code you received</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Join Group</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addExpenseForm" action="add-expense.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="group_id" class="form-label">Group</label>
                            <select class="form-select" id="group_id" name="group_id" required onchange="updateMembersList(this.value)">
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="expense_type" class="form-label">Expense Type</label>
                            <select class="form-select" id="expense_type" name="expense_type" required>
                                <option value="food">Food</option>
                                <option value="clothes">Clothes</option>
                                <option value="travel">Travel</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Split Between</label>
                            <div id="splitWithContainer" class="d-flex flex-wrap gap-3">
                                <!-- Member checkboxes will be loaded here by JS -->
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Split Type</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="split_type" id="splitEqual" value="equal" checked>
                                    <label class="form-check-label" for="splitEqual">
                                        Equal Split
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="split_type" id="splitCustom" value="custom">
                                    <label class="form-check-label" for="splitCustom">
                                        Custom Split
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_expense" class="btn btn-primary">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle Join Group Form
            const joinGroupForm = document.getElementById('joinGroupForm');
            if (joinGroupForm) {
                joinGroupForm.addEventListener('submit', function(e) {
                    const inviteCode = document.getElementById('inviteCode').value.trim();
                    if (!inviteCode) {
                        e.preventDefault();
                        alert('Please enter an invitation code');
                        return;
                    }
                });
            }

            // Show success message if present
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            if (success) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${success}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container').prepend(alertDiv);
                
                // Remove success parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Toggle sidebar
            document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
            });

            // Expense Chart
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            let expenseChart;
            function renderExpenseChart(data, labels) {
                if (expenseChart) expenseChart.destroy();
                expenseChart = new Chart(expenseCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Your Share',
                                data: data.map(item => item.amount_spent),
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                borderColor: 'rgba(79, 70, 229, 1)',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Total Paid',
                                data: data.map(item => item.amount_paid),
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Initial chart render
            const initialData = <?php echo json_encode(array_reverse($monthly_expenses)); ?>;
            renderExpenseChart(initialData, initialData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleString('default', { month: 'short' });
            }));

            // Chart filter logic
            document.querySelectorAll('.dropdown-menu .dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filter = this.textContent.trim();
                    document.getElementById('chartDropdown').textContent = filter;
                    fetch('dashboard-data.php?filter=' + encodeURIComponent(filter))
                        .then(res => res.json())
                        .then(res => {
                            renderExpenseChart(res.data, res.labels);
                        });
                });
            });

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            // Get category distribution data from PHP
            const expenseTypes = <?php 
                $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE group_id IN (SELECT group_id FROM group_members WHERE user_id = ?) GROUP BY category");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            ?>;

            const distributionChart = new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: expenseTypes.map(item => item.category),
                    datasets: [{
                        data: expenseTypes.map(item => item.total),
                        backgroundColor: [
                            'rgba(79, 70, 229, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(107, 114, 128, 0.8)',
                            'rgba(192, 132, 252, 0.8)',
                            'rgba(251, 146, 60, 0.8)',
                            'rgba(147, 197, 253, 0.8)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        }
                    },
                    cutout: '70%'
                }
            });

            // Preload group members for all groups
            const groupMembers = <?php
            $groupMembersMap = [];
            foreach ($groups as $group) {
                $stmt = $pdo->prepare("SELECT u.id, u.name FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ?");
                $stmt->execute([$group['id']]);
                $groupMembersMap[$group['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($groupMembersMap);
            ?>;

            function updateMembersList(groupId) {
                const container = document.getElementById('splitWithContainer');
                container.innerHTML = '';
                if (!groupMembers[groupId]) return;
                groupMembers[groupId].forEach(member => {
                    const div = document.createElement('div');
                    div.className = 'form-check';
                    const checkbox = document.createElement('input');
                    checkbox.className = 'form-check-input';
                    checkbox.type = 'checkbox';
                    checkbox.name = 'split_with[]';
                    checkbox.value = member.id;
                    checkbox.id = 'member' + member.id;
                    if (member.id == <?php echo json_encode($_SESSION['user_id']); ?>) {
                        checkbox.checked = true;
                    }
                    const label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.htmlFor = 'member' + member.id;
                    label.textContent = member.name;
                    div.appendChild(checkbox);
                    div.appendChild(label);
                    container.appendChild(div);
                });
            }
            // Initialize members list for the first group on modal open
            const groupSelect = document.getElementById('group_id');
            groupSelect && updateMembersList(groupSelect.value);
            groupSelect && groupSelect.addEventListener('change', function() {
                updateMembersList(this.value);
            });

            // Add Expense Form AJAX
            const addExpenseForm = document.getElementById('addExpenseForm');
            if (addExpenseForm) {
                addExpenseForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('add-expense.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error adding expense');
                        }
                    })
                    .catch(() => {
                        alert('Error adding expense');
                    });
                });
            }
        });
    </script>
</body>
</html>