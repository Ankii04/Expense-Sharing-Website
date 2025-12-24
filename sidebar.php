<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <i class="fas fa-wallet"></i> <span>Expense Maker</span>
        </a>
    </div>
    <ul class="sidebar-nav">
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="sidebar-item">
            <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="fas fa-plus-square"></i> Create Group
            </a>
        </li>
        <li class="sidebar-item">
            <a href="reports.php" class="sidebar-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Reports
            </a>
        </li>
        <li class="sidebar-item">
            <a href="expense-overview.php" class="sidebar-link <?php echo $current_page == 'expense-overview.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i> My Expenses
            </a>
        </li>
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
    </ul>
    
    <div class="sidebar-bottom">
        <button class="btn btn-primary btn-sm w-100 mb-3" data-bs-toggle="modal" data-bs-target="#joinGroupModal">
            <i class="fas fa-user-plus"></i> Join Group
        </button>
        <div class="user-info">
            <div class="user-avatar overflow-hidden">
                <?php if (isset($_SESSION['avatar_url']) && $_SESSION['avatar_url']): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Profile" class="w-100 h-100 object-fit-cover">
                <?php else: ?>
                    <?php 
                        $display_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'U';
                        echo htmlspecialchars(strtoupper(substr($display_name, 0, 1))); 
                    ?>
                <?php endif; ?>
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
<div class="sidebar-overlay"></div>
