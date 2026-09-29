<?php
require_once __DIR__.'/db.php';
require_role(['admin','manager']);
$db = getDB();
$staffRows = $db->query("SELECT u.id, u.username, u.role, u.is_active, COALESCE(em.job_title, u.role) AS job_title, COALESCE(em.is_on_shift, 0) AS is_on_shift, COALESCE(em.shift_note, '') AS shift_note, em.clock_in_at, em.clock_out_at FROM users u LEFT JOIN employee_meta em ON em.user_id = u.id WHERE u.is_active = 1 AND u.role != 'customer' ORDER BY u.role, u.username")->fetchAll();
$onShift = 0;
foreach ($staffRows as $staffMember) {
    if ((int)$staffMember['is_on_shift'] === 1) $onShift++;
}
$staffCount = count($staffRows);
include 'header.php';
?>
<div class="content-shell">
  <div class="page-header mb-4">
    <span class="page-badge">📊</span>
    <div>
      <p class="eyebrow">Operations overview</p>
      <h3>Admin Dashboard</h3>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="metric-card accent-purple">
        <span>👥</span>
        <div>
          <strong><?php echo $staffCount; ?></strong>
          <small>Active staff</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="metric-card accent-green">
        <span>✅</span>
        <div>
          <strong><?php echo $onShift; ?></strong>
          <small>On shift</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="metric-card accent-gold">
        <span>🔥</span>
        <div>
          <strong>7</strong>
          <small>Service roles</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="metric-card accent-rose">
        <span>⭐</span>
        <div>
          <strong>3</strong>
          <small>Quick actions</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="panel-card">
        <div class="panel-heading">
          <h4>Staff roster</h4>
          <span class="soft-pill">Live status</span>
        </div>
        <div class="staff-list">
          <?php foreach ($staffRows as $member): ?>
            <div class="staff-row <?php echo (int)$member['is_on_shift'] === 1 ? 'on-shift' : 'off-shift'; ?>">
              <div class="staff-bio">
                <div class="avatar"><?php echo strtoupper(substr($member['username'], 0, 1)); ?></div>
                <div>
                  <strong><?php echo htmlspecialchars($member['username']); ?></strong>
                  <small><?php echo htmlspecialchars($member['job_title'] ?: ucfirst($member['role'])); ?></small>
                </div>
              </div>
              <div class="staff-status">
                <span class="status-dot"></span>
                <?php echo (int)$member['is_on_shift'] === 1 ? 'On shift' : 'Off shift'; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="panel-card">
        <div class="panel-heading">
          <h4>Quick access</h4>
        </div>
        <div class="shortcut-list">
          <?php if($_SESSION['role'] ?? '' === 'admin'): ?>
            <a href="admin_menu.php"><span>🍽️</span> Menu Management</a>
          <?php endif; ?>
          <a href="admin_cashier.php"><span>💳</span> Cashier / Orders</a>
          <a href="admin_kitchen.php"><span>👨‍🍳</span> Kitchen Queue</a>
          <a href="admin_bar.php"><span>🍷</span> Bar Queue</a>
          <?php if($_SESSION['role'] ?? '' === 'admin'): ?>
            <a href="admin_users.php"><span>👥</span> User Management</a>
            <a href="admin_analytics.php"><span>📈</span> Analytics & reports</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>