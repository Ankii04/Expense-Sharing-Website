<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get expense overview data
$stmt = $pdo->prepare("
    SELECT 
        e.category,
        SUM(e.amount) as total_amount,
        COUNT(*) as count,
        DATE_FORMAT(e.date, '%Y-%m') as month
    FROM expenses e
    WHERE e.paid_by = ?
    GROUP BY e.category, DATE_FORMAT(e.date, '%Y-%m')
    ORDER BY e.date DESC
    LIMIT 12
");
$stmt->execute([$user_id]);
$expense_data = $stmt->fetchAll();

// Get expense distribution data
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        SUM(es.amount) as total_amount
    FROM expense_splits es
    JOIN users u ON u.id = es.user_id
    JOIN expenses e ON e.id = es.expense_id
    WHERE e.paid_by = ?
    GROUP BY u.id, u.name
    ORDER BY total_amount DESC
");
$stmt->execute([$user_id]);
$distribution_data = $stmt->fetchAll();

// Convert data for charts
$categories = [];
$amounts = [];
$months = [];
$monthly_data = [];

foreach ($expense_data as $row) {
    if (!in_array($row['category'], $categories)) {
        $categories[] = $row['category'];
    }
    if (!in_array($row['month'], $months)) {
        $months[] = $row['month'];
    }
    $monthly_data[$row['month']][$row['category']] = $row['total_amount'];
}

// Sort months chronologically
sort($months);

// Distribution data
$distribution_labels = [];
$distribution_amounts = [];
foreach ($distribution_data as $row) {
    $distribution_labels[] = $row['name'];
    $distribution_amounts[] = $row['total_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Overview - Expense Maker</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-body">
    <?php include 'navbar.php'; ?>

    <div class="content-wrapper">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card card-premium shadow">
                    <div class="card-body">
                        <h4 class="card-title fw-bold">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Monthly Expense Overview
                        </h4>
                        <div style="height: 300px; position: relative;">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card card-premium shadow h-100">
                    <div class="card-body">
                        <h4 class="card-title fw-bold">
                            <i class="fas fa-tags text-success me-2"></i>
                            Expense by Category
                        </h4>
                        <div style="height: 250px; position: relative;">
                            <canvas id="categoryPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card card-premium shadow h-100">
                    <div class="card-body">
                        <h4 class="card-title fw-bold">
                            <i class="fas fa-chart-line text-info me-2"></i>
                            Expense Distribution
                        </h4>
                        <div style="height: 250px; position: relative;">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- .content-wrapper -->

    <script>
        // Monthly Expense Overview Chart
        const ctx = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: <?php 
                    $datasets = [];
                    foreach ($categories as $category) {
                        $data = [];
                        foreach ($months as $month) {
                            $data[] = $monthly_data[$month][$category] ?? 0;
                        }
                        $datasets[] = [
                            'label' => $category,
                            'data' => $data,
                            'backgroundColor' => 'rgba(' . rand(0,255) . ',' . rand(0,255) . ',' . rand(0,255) . ',0.5)'
                        ];
                    }
                    echo json_encode($datasets);
                ?>
            },
            options: {
                responsive: true,
                scales: {
                    x: { stacked: true },
                    y: { 
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Pie Chart
        const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($categories); ?>,
                datasets: [{
                    data: <?php 
                        $category_totals = [];
                        foreach ($categories as $category) {
                            $total = 0;
                            foreach ($months as $month) {
                                $total += $monthly_data[$month][$category] ?? 0;
                            }
                            $category_totals[] = $total;
                        }
                        echo json_encode($category_totals);
                    ?>,
                    backgroundColor: <?php 
                        $colors = [];
                        foreach ($categories as $category) {
                            $colors[] = 'rgba(' . rand(0,255) . ',' . rand(0,255) . ',' . rand(0,255) . ',0.5)';
                        }
                        echo json_encode($colors);
                    ?>
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Distribution Chart
        const distCtx = document.getElementById('distributionChart').getContext('2d');
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($distribution_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($distribution_amounts); ?>,
                    backgroundColor: <?php 
                        $colors = [];
                        foreach ($distribution_labels as $label) {
                            $colors[] = 'rgba(' . rand(0,255) . ',' . rand(0,255) . ',' . rand(0,255) . ',0.5)';
                        }
                        echo json_encode($colors);
                    ?>
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
