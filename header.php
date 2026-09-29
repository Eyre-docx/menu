<?php
require_once __DIR__.'/db.php';
if(session_status() == PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? '';
$isAdminRole = $userRole === 'admin';
$isManagerRole = $userRole === 'manager';
$isStaffBackOfficeRole = in_array($userRole, ['admin', 'manager'], true);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SoulBound POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="custom.css" rel="stylesheet">
</head>
<body class="theme-shell <?php echo (is_logged_in() && $isStaffBackOfficeRole) ? 'admin-layout' : ''; ?>">
<?php if(is_logged_in() && $isStaffBackOfficeRole): ?>
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a class="nav-chip <?php echo ($currentPage === 'admin_dashboard.php') ? 'active' : ''; ?>" href="admin_dashboard.php"><span>📊</span><span>Dashboard</span></a>
      <?php if($isAdminRole): ?>
        <a class="nav-chip <?php echo ($currentPage === 'admin_menu.php') ? 'active' : ''; ?>" href="admin_menu.php"><span>🍽️</span><span>Menu</span></a>
      <?php endif; ?>
      <a class="nav-chip <?php echo ($currentPage === 'admin_kitchen.php') ? 'active' : ''; ?>" href="admin_kitchen.php"><span>👨‍🍳</span><span>Kitchen</span></a>
      <a class="nav-chip <?php echo ($currentPage === 'admin_bar.php') ? 'active' : ''; ?>" href="admin_bar.php"><span>🍷</span><span>Bar</span></a>
      <a class="nav-chip <?php echo ($currentPage === 'admin_cashier.php') ? 'active' : ''; ?>" href="admin_cashier.php"><span>💳</span><span>Cashier</span></a>
      <?php if(in_array($userRole, ['admin', 'manager', 'kitchen', 'bar'], true)): ?>
        <a class="nav-chip <?php echo ($currentPage === 'admin_analytics.php') ? 'active' : ''; ?>" href="admin_analytics.php"><span>📈</span><span>Analytics</span></a>
      <?php endif; ?>
      <?php if($isAdminRole): ?>
        <a class="nav-chip <?php echo ($currentPage === 'admin_users.php') ? 'active' : ''; ?>" href="admin_users.php"><span>👥</span><span>Staff</span></a>
      <?php endif; ?>
      <a class="nav-chip" href="logout.php"><span>🚪</span><span>Logout</span></a>
    </nav>
  </aside>
<?php else: ?>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">SoulBound POS</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navcol" aria-controls="navcol" aria-expanded="false">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navcol">
        <ul class="navbar-nav ms-auto">
          <?php if(is_logged_in()): ?>
            <li class="nav-item"><a class="nav-link" href="menu.php">Menu</a></li>
            <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true)): ?>
              <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin</a></li>
            <?php elseif(in_array($_SESSION['role'] ?? '', ['cashier', 'kitchen', 'bar'], true)): ?>
              <li class="nav-item"><a class="nav-link" href="admin_<?php echo htmlspecialchars($_SESSION['role']); ?>.php">Dashboard</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="menu.php">Guest menu</a></li>
            <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
            <li class="nav-item"><a class="nav-link" href="login.php">Employee login</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
<?php endif; ?>
<?php if(is_logged_in() && in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true)): ?>
  <main class="admin-main">
<?php else: ?>
  <main class="main-shell">
<?php endif; ?>
<div class="container mt-4">