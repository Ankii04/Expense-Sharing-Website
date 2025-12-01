<?php
session_start();
require_once 'db.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's groups
$stmt = $pdo->prepare("
    SELECT g.* 
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ?
");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get monthly expenses
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(e.created_at, '%Y-%m') as month,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as total_share,
        SUM(CASE WHEN e.paid_by = ? THEN e.amount ELSE 0 END) as total_spent,
        COUNT(*) as expense_count
    FROM expenses e
    JOIN group_members gm ON e.group_id = gm.group_id
    LEFT JOIN expense_splits es ON e.id = es.expense_id
    WHERE gm.user_id = ?
    GROUP BY DATE_FORMAT(e.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$user_id, $user_id, $user_id]);
$monthly_expenses = $stmt->fetchAll();

// Get category-wise expenses
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(e.category, 'Other') as category,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as total_share,
        SUM(CASE WHEN e.paid_by = ? THEN e.amount ELSE 0 END) as total_spent,
        COUNT(*) as expense_count
    FROM expenses e
    JOIN group_members gm ON e.group_id = gm.group_id
    LEFT JOIN expense_splits es ON e.id = es.expense_id
    WHERE gm.user_id = ?
    GROUP BY e.category
    ORDER BY total_spent DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$category_expenses = $stmt->fetchAll();

// Get group-wise expenses
$stmt = $pdo->prepare("
    SELECT 
        g.name as group_name,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as total_share,
        SUM(CASE WHEN e.paid_by = ? THEN e.amount ELSE 0 END) as total_spent,
        COUNT(*) as expense_count
    FROM expenses e
    JOIN groups g ON e.group_id = g.id
    JOIN group_members gm ON e.group_id = gm.group_id
    LEFT JOIN expense_splits es ON e.id = es.expense_id
    WHERE gm.user_id = ?
    GROUP BY g.id, g.name
    ORDER BY total_spent DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$group_expenses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Reports - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <i class="fas fa-chart-bar me-2"></i>
                            Expense Reports
                        </h2>

                        <!-- Monthly Trend -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h4>Monthly Expense Trend</h4>
                                        <canvas id="monthlyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category and Group Distribution -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h4>Category-wise Distribution</h4>
                                        <canvas id="categoryChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h4>Group-wise Distribution</h4>
                                        <canvas id="groupChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Table -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h4>Summary</h4>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Group</th>
                                                        <th>Total Spent</th>
                                                        <th>Your Share</th>
                                                        <th>Balance</th>
                                                        <th>Expenses</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($group_expenses as $expense): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($expense['group_name']); ?></td>
                                                            <td>₹<?php echo number_format($expense['total_spent'], 2); ?></td>
                                                            <td>₹<?php echo number_format($expense['total_share'], 2); ?></td>
                                                            <td>₹<?php echo number_format($expense['total_spent'] - $expense['total_share'], 2); ?></td>
                                                            <td><?php echo $expense['expense_count']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Export Options -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h4>Export Reports</h4>
                                        <div class="btn-group">
                                            <a href="export-report.php?type=pdf" class="btn btn-primary">
                                                <i class="fas fa-file-pdf me-2"></i>
                                                Export as PDF
                                            </a>
                                            <a href="export-report.php?type=excel" class="btn btn-success">
                                                <i class="fas fa-file-excel me-2"></i>
                                                Export as Excel
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Trend Chart
        const monthlyData = <?php echo json_encode(array_reverse($monthly_expenses)); ?>;
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Your Share',
                    data: monthlyData.map(item => item.total_share),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Total Spent',
                    data: monthlyData.map(item => item.total_spent),
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Chart
        const categoryData = <?php echo json_encode($category_expenses); ?>;
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category),
                datasets: [{
                    data: categoryData.map(item => item.total_spent),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });

        // Group Chart
        const groupData = <?php echo json_encode($group_expenses); ?>;
        new Chart(document.getElementById('groupChart'), {
            type: 'pie',
            data: {
                labels: groupData.map(item => item.group_name),
                datasets: [{
                    data: groupData.map(item => item.total_spent),
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
