<?php
// navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-3">
            <?php if ($current_page !== 'dashboard.php'): ?>
                <a href="javascript:history.back()" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php endif; ?>
            <a class="navbar-brand navbar-brand-custom" href="dashboard.php">
                <i class="fas fa-wallet text-primary"></i> Expense Maker
            </a>
        </div>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-2">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link-custom <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <?php if (isset($groups) && !empty($groups)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link-custom dropdown-toggle" href="#" id="groupsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-layer-group"></i> My Groups
                    </a>
                    <ul class="dropdown-menu border-0 shadow-lg mt-2">
                        <?php foreach (array_slice($groups, 0, 5) as $nav_group): ?>
                            <li><a class="dropdown-item" href="group-details.php?id=<?php echo $nav_group['id'] ?? $nav_group['group_id']; ?>"><?php echo htmlspecialchars($nav_group['name'] ?? $nav_group['group_name']); ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createGroupModal">Create New Group</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                        <i class="fas fa-users"></i> Create Group
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="fas fa-plus-circle"></i> Add Expense
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link-custom <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i> Reports
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <button class="btn-premium btn-primary-premium btn-sm" data-bs-toggle="modal" data-bs-target="#joinGroupModal">
                    <i class="fas fa-user-plus"></i> Join Group
                </button>
                
                <button class="btn btn-link text-main p-0" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>

                <div class="dropdown">
                    <button class="user-nav-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar-nav">
                            <?php 
                                $nav_display_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'U';
                                echo htmlspecialchars(strtoupper(substr($nav_display_name, 0, 1))); 
                            ?>
                        </div>
                        <span class="d-none d-sm-inline fw-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-3">
                        <li><a class="dropdown-item py-2" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
