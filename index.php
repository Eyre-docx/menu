<?php
require_once __DIR__.'/db.php';
if(is_logged_in()){
    $role = $_SESSION['role'];
    if(in_array($role, ['admin', 'manager'], true)) header('Location: admin_dashboard.php');
    elseif(in_array($role, ['cashier', 'kitchen', 'bar'], true)) header('Location: admin_' . $role . '.php');
    else header('Location: menu.php');
    exit;
}
include 'header.php';
?>
<div class="hero-panel">
  <div class="hero-copy">
    <p class="kicker">SoulBound hospitality</p>
    <h1>Choose how you want to enter.</h1>
    <p class="lead">Guests can browse the menu and place an order without creating an account. Employees log in to access staff operations and the admin dashboard.</p>
    <div class="hero-actions">
      <a href="menu.php" class="btn btn-primary">Continue as Guest</a>
      <a href="login.php" class="btn btn-outline-primary">Employee Log in</a>
    </div>
  </div>
  <div class="hero-card">
    <div class="mini-badge">Tonight</div>
    <h4>Service rhythm</h4>
    <ul>
      <li>🍽️ Guest ordering available</li>
      <li>👨‍🍳 Kitchen queue ready</li>
      <li>🍷 Bar queue ready</li>
      <li>💬 Staff access restricted</li>
    </ul>
  </div>
</div>
<?php include 'footer.php'; ?>