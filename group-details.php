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
        /* Keep only page-specific overrides if any */
        .group-header-section {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body class="dashboard-body">
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="content-wrapper">

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
                    <!-- Settle Up Section -->
                    <div class="card settle-up-card card-premium mb-4">
                        <div class="card-body">
                            <div>
                                <h4 class="mb-1 text-white">Settle Up Balances</h4>
                                <p class="mb-0 text-white-50 small">Resolve pending payments with group members</p>
                            </div>
                            <button class="btn btn-light btn-premium shadow-sm" data-bs-toggle="modal" data-bs-target="#settleUpModal">
                                <i class="fas fa-hand-holding-usd text-success"></i> Settle Now
                            </button>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6 mb-4">
                            <div class="card card-premium h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary me-2"></i>Group Information</h6>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted small">Created by:</span>
                                            <span class="fw-semibold small"><?php echo htmlspecialchars($group['creator_name'] ?? ''); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted small">Created on:</span>
                                            <span class="fw-semibold small"><?php echo date('F j, Y', strtotime($group['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card card-premium h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-users text-primary me-2"></i>Group Members</h6>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($members as $member): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar-nav me-2" style="width: 24px; height: 24px; font-size: 10px;">
                                                        <?php echo strtoupper(substr($member['name'] ?? 'U', 0, 1)); ?>
                                                    </div>
                                                    <span class="small"><?php echo htmlspecialchars($member['name'] ?? ''); ?></span>
                                                </div>
                                                <?php if ($member['id'] == $group['created_by']): ?>
                                                    <span class="badge bg-primary px-2" style="font-size: 8px;">Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
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
                                                <?php echo htmlspecialchars($member['name'] ?? ''); ?>
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
                                        <td><?php echo htmlspecialchars($expense['description'] ?? ''); ?></td>
                                        <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($expense['paid_by_name'] ?? ''); ?></td>
                                        <td>
                                            <?php
                                            $split_members = [];
                                            foreach ($expense['splits'] as $split) {
                                                $split_members[] = htmlspecialchars($split['member_name'] ?? '');
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