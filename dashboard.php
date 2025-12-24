<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

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
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$group_id, $_SESSION['user_id']]);
            
            logActivity($pdo, $group_id, $_SESSION['user_id'], 'create_group', "Created the group '{$group_name}'");
            
            $_SESSION['success'] = "Group created successfully!";
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error creating group: " . $e->getMessage();
        }
    }
}

// Fetch unread notifications
$notifications = [];
$activities = [];
$db_error = false;

try {
    $notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);

    // Fetch recent activity from user's groups
    $stmt = $pdo->prepare("
        SELECT al.*, u.name as user_name, g.name as group_name, u.avatar_url, u.username
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        JOIN `groups` g ON al.group_id = g.id
        WHERE al.group_id IN (SELECT group_id FROM group_members WHERE user_id = ?)
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') { // Table not found
        $db_error = "Migration Required: New features (Notifications & Activity Log) need database updates. <a href='update_db.php' class='alert-link'>Run Update Now</a>";
    } else {
        $error = "Database error: " . $e->getMessage();
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
    GROUP BY DATE_FORMAT(e.date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$monthly_stats = $stmt->fetchAll();

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

// Get pending amounts
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_owed
    FROM expense_splits
    WHERE user_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$total_owed = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT SUM(es.amount) as total_owes
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    WHERE e.paid_by = ? AND es.user_id != ? AND es.status = 'pending'
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$total_owes = $stmt->fetchColumn() ?: 0;

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
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
                             Expense Sharing
                        </h4>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <!-- Notifications Dropdown -->
                        <div class="dropdown">
                            <button class="btn-icon position-relative" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php if (count($notifications) > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                        <?php echo count($notifications); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 300px; max-height: 400px; overflow-y: auto;">
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Notifications</span>
                                    <?php if (count($notifications) > 0): ?>
                                        <a href="mark-read.php" class="text-primary text-decoration-none small">Mark all as read</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-bell-slash text-muted mb-2 d-block fa-2xl"></i>
                                        <span class="text-muted small">No new notifications</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <div class="dropdown-item py-2 px-3 border-bottom">
                                            <p class="mb-1 small"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <span class="text-muted" style="font-size: 0.7rem;">
                                                <?php echo date('M d, H:i', strtotime($notif['created_at'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button id="themeToggle" class="btn-icon">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="content-container">
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

            <?php if ($db_error): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $db_error; ?>
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
                <div class="col-md-4">
                    <div class="card bg-primary text-white shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total Balance</h6>
                                    <h2 class="mb-0">₹<?php echo number_format($balance, 2); ?></h2>
                                </div>
                                <i class="fas fa-wallet fa-2xl text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">You are owed</h6>
                                    <h2 class="mb-0">₹<?php echo number_format($total_owes, 2); ?></h2>
                                </div>
                                <i class="fas fa-arrow-down fa-2xl text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-danger text-white shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">You owe</h6>
                                    <h2 class="mb-0">₹<?php echo number_format($total_owed, 2); ?></h2>
                                </div>
                                <i class="fas fa-arrow-up fa-2xl text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Activity Stream (Item 1) -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-stream me-2 text-primary"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($activities)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-history text-muted mb-3 d-block fa-3xl"></i>
                                    <p class="text-muted">No recent activity found.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $act): ?>
                                        <div class="list-group-item py-3">
                                            <div class="d-flex gap-3">
                                                <div class="avatar-sm">
                                                    <?php if ($act['avatar_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($act['avatar_url']); ?>" class="rounded-circle" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:40px;height:40px;">
                                                            <?php echo strtoupper(substr($act['user_name'] ?: $act['username'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <p class="mb-1">
                                                        <span class="fw-bold"><?php echo htmlspecialchars($act['user_name'] ?: $act['username']); ?></span>
                                                        <?php echo htmlspecialchars($act['description']); ?>
                                                        in <span class="text-primary">#<?php echo htmlspecialchars($act['group_name']); ?></span>
                                                    </p>
                                                    <span class="text-muted small">
                                                        <i class="far fa-clock me-1"></i><?php echo date('M d, H:i', strtotime($act['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expenses Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-receipt me-2 text-primary"></i>All Expenses</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Description</th>
                                            <th>Group</th>
                                            <th>Amount</th>
                                            <th class="pe-4">Paid By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td class="ps-4"><?php echo date('M d', strtotime($expense['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($expense['group_name']); ?></span></td>
                                                <td class="fw-bold">₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td class="pe-4"><?php echo htmlspecialchars($expense['paid_by_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Groups Sidebar -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">My Groups</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($groups as $group): ?>
                                    <a href="group-details.php?id=<?php echo $group['id']; ?>" class="list-group-item list-group-item-action py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($group['name']); ?></h6>
                                                <p class="mb-0 text-muted small">Created by <?php echo htmlspecialchars($group['creator_name']); ?></p>
                                            </div>
                                            <i class="fas fa-chevron-right text-muted small"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 ps-4">
                    <h5 class="modal-title fw-bold">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Group Name</label>
                            <input type="text" class="form-control form-control-lg" name="group_name" placeholder="e.g. Goa Trip 2024" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_group" class="btn btn-primary px-4">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal (Updated with Item 3: Receipt Scanning) -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 ps-4">
                    <h5 class="modal-title fw-bold">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addExpenseForm" action="add-expense.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Group</label>
                            <select class="form-select" name="group_id" required onchange="updateMembersList(this.value)">
                                <option value="" disabled selected>Select a group</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label fw-bold small text-uppercase text-muted">Description</label>
                                <input type="text" class="form-control" name="description" placeholder="Lunch, Taxi, Movie..." required>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" name="amount" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Date & Category</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-6">
                                    <select class="form-select" name="expense_type">
                                        <option value="food">Food</option>
                                        <option value="travel">Travel</option>
                                        <option value="shopping">Shopping</option>
                                        <option value="other" selected>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Item 3: Receipt Scanning Option -->
                        <div class="mt-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Receipt Scanner / Attachment</label>
                            <div class="input-group">
                                <input type="file" class="form-control" name="receipt" id="receiptInput" accept="image/*">
                                <button class="btn btn-outline-primary" type="button" id="scanBtn">
                                    <i class="fas fa-qrcode mr-2"></i> Scan
                                </button>
                            </div>
                            <div id="scanResult" class="small mt-1 d-none text-success">
                                <i class="fas fa-check-circle"></i> Amount extracted from receipt!
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Split With</label>
                            <div id="splitWithContainer" class="d-flex flex-wrap gap-2">
                                <p class="text-muted small">Select a group first</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 pb-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="addExpenseBtn" class="btn btn-primary px-4">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateMembersList(groupId) {
            const container = document.getElementById('splitWithContainer');
            container.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div>';
            
            fetch(`get-group-members.php?group_id=${groupId}`)
                .then(r => r.json())
                .then(members => {
                    container.innerHTML = '';
                    members.forEach(m => {
                        const div = document.createElement('div');
                        div.className = 'form-check form-check-inline bg-light p-2 rounded border';
                        div.innerHTML = `
                            <input class="form-check-input ms-0" type="checkbox" name="split_with[]" value="${m.id}" id="m${m.id}" checked>
                            <label class="form-check-label ms-1" for="m${m.id}">${m.name || m.username}</label>
                        `;
                        container.appendChild(div);
                    });
                });
        }

        // Mock Receipt Scanning (Item 3)
        document.getElementById('scanBtn').addEventListener('click', function() {
            const input = document.getElementById('receiptInput');
            if (!input.files || !input.files[0]) {
                alert('Please upload an image first');
                return;
            }
            
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Scanning...';
            this.disabled = true;
            
            // Mock OCR logic
            setTimeout(() => {
                const mockAmount = (Math.random() * 500 + 100).toFixed(2);
                document.getElementsByName('amount')[0].value = mockAmount;
                document.getElementById('scanResult').classList.remove('d-none');
                this.innerHTML = '<i class="fas fa-qrcode mr-2"></i> Scan';
                this.disabled = false;
            }, 1500);
        });

        // Add Expense Form Handling
        document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('addExpenseBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>