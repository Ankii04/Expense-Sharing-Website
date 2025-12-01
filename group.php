<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if group ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$group_id = $_GET['id'];

// Check if user is a member of the group
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$group_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header("Location: dashboard.php");
    exit();
}

// Get group details
$stmt = $pdo->prepare("
    SELECT g.*, u.username as creator_name 
    FROM groups g 
    JOIN users u ON g.created_by = u.id 
    WHERE g.id = ?
");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

// Get group members
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email 
    FROM users u 
    JOIN group_members gm ON u.id = gm.user_id 
    WHERE gm.group_id = ?
");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Get group expenses
$stmt = $pdo->prepare("
    SELECT e.*, u.username as paid_by_name 
    FROM expenses e 
    JOIN users u ON e.paid_by = u.id 
    WHERE e.group_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$group_id]);
$expenses = $stmt->fetchAll();

// Calculate balances
$balances = [];
foreach ($members as $member) {
    $balances[$member['id']] = 0;
}

foreach ($expenses as $expense) {
    $split_amount = $expense['amount'] / count($members);
    $balances[$expense['paid_by']] += $expense['amount'] - $split_amount;
    foreach ($members as $member) {
        if ($member['id'] != $expense['paid_by']) {
            $balances[$member['id']] -= $split_amount;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - Expense Sharing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-wallet"></i> Expense Sharing</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Group Info -->
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($group['name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($group['description']); ?></p>
                        <p class="card-text"><small class="text-muted">Created by <?php echo htmlspecialchars($group['creator_name']); ?></small></p>
                        
                        <!-- Prominent Settle Button -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-lg" onclick="showSettlements()">
                                <i class="fas fa-money-bill-wave me-2"></i>Settle Up
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Members -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Members & Balances</h5>
                        <div class="d-grid gap-2 mb-3">
                            <!-- Debug Info -->
                            <div class="alert alert-info">
                                Group Creator ID: <?php echo $group['created_by']; ?><br>
                                Current User ID: <?php echo $_SESSION['user_id']; ?>
                            </div>
                            
                            <a href="settle-up.php?group_id=<?php echo $group_id; ?>" class="btn btn-primary">
                                <i class="fas fa-money-bill-wave"></i> Settle Up
                            </a>
                            <a href="invite-members.php?id=<?php echo $group_id; ?>" class="btn btn-success">
                                <i class="fas fa-user-plus"></i> Invite Members
                            </a>
                            <?php if ((int)$group['created_by'] === (int)$_SESSION['user_id']): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
                                <i class="fas fa-trash"></i> Delete Group
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Delete Group Modal -->
                        <div class="modal fade" id="deleteGroupModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Group</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this group? This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="delete-group.php" class="d-inline">
                                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                            <button type="submit" class="btn btn-danger">Delete Group</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <ul class="list-group">
                            <?php foreach ($members as $member): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($member['username']); ?>
                                    <span class="badge <?php echo $balances[$member['id']] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        $<?php echo number_format($balances[$member['id']], 2); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Add Expense Form -->
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Add New Expense</h5>
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <div class="mb-3">
                                <label for="expense_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="expense_description" name="expense_description" required>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                            <button type="submit" name="add_expense" class="btn btn-primary">Add Expense</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Expenses List -->
            <div class="col-md-8">
                <!-- Settlements Section -->
                <?php include 'settle-button.php'; ?>

                <div class="card shadow mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Expenses</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Paid By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                            <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($expense['paid_by_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="script.js"></script>
    <script>
        function showSettlements() {
            const settlementsSection = document.getElementById('settlementsSection');
            if (settlementsSection) {
                settlementsSection.scrollIntoView({ behavior: 'smooth' });
                settlementsSection.classList.add('highlight-section');
                setTimeout(() => {
                    settlementsSection.classList.remove('highlight-section');
                }, 2000);
            }
        }
    </script>
    <style>
        .highlight-section {
            animation: highlight 2s ease-in-out;
        }
        @keyframes highlight {
            0% { box-shadow: none; }
            50% { box-shadow: 0 0 20px rgba(40, 167, 69, 0.5); }
            100% { box-shadow: none; }
        }
    </style>
</body>
</html> 