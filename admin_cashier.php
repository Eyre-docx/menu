<?php
require_once __DIR__.'/db.php';
require_role(['admin','manager','cashier']);
$db = getDB();
// handle POST status update
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['order_id'])){
    $token = $_POST['csrf_token'] ?? '';
    if(!verify_csrf_token($token)){
        die('Invalid CSRF token');
    }
    $nid = (int)$_POST['order_id'];
    $ns = $_POST['new_status'] ?? 'new';
    $tableNumber = trim((string)($_POST['table_number'] ?? ''));
    $db->prepare('UPDATE orders SET status = ?, table_number = ? WHERE id = ?')->execute([$ns, $tableNumber !== '' ? $tableNumber : null, $nid]);
    header('Location: admin_cashier.php?view='.$nid); exit;
}
// show orders
$orders = $db->query('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 200')->fetchAll();
include 'header.php';
$csrf = htmlspecialchars(get_csrf_token());
?>
<div class="content-shell">
  <div class="page-header mb-4">
    <span class="page-badge">💳</span>
    <div>
      <p class="eyebrow">Service flow</p>
      <h3>Cashier - Orders</h3>
    </div>
  </div>

  <div class="panel-card cashier-panel">
    <div class="panel-heading">
      <h4>Recent orders</h4>
      <span class="soft-pill">Live queue</span>
    </div>

    <div class="table-responsive">
      <table class="table cashier-table align-middle mb-0">
        <thead>
          <tr>
            <th>Order</th>
            <th>Table</th>
            <th>User</th>
            <th>Total</th>
            <th>Status</th>
            <th>Time</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($orders as $o): ?>
            <tr>
              <td><strong>#<?php echo $o['id']; ?></strong></td>
              <td><?php echo htmlspecialchars($o['username'] ?? 'Guest'); ?></td>
              <td><strong>&dollar;<?php echo number_format($o['total_price'],2); ?></strong></td>
              <td>
                <?php
                  $statusKey = strtolower((string)($o['status'] ?? 'new'));
                  $statusKey = in_array($statusKey, ['new','paid','ready','completed'], true) ? $statusKey : 'new';
                ?>
                <span class="status-badge <?php echo $statusKey; ?>"><?php echo htmlspecialchars(ucfirst($o['status'] ?? 'New')); ?></span>
              </td>
              <td><?php echo htmlspecialchars($o['created_at']); ?></td>
              <td>
                <div class="cashier-actions">
                  <a class="btn btn-sm btn-primary" href="admin_cashier.php?view=<?php echo $o['id']; ?>">View</a>
                  <a class="btn btn-sm btn-outline-primary" href="admin_receipt.php?order=<?php echo $o['id']; ?>" target="_blank">Print</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if(!empty($_GET['view'])):
    $id = (int)$_GET['view'];
    $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?'); $stmt->execute([$id]);
    $items = $stmt->fetchAll();
  ?>
    <div class="panel-card cashier-detail mt-4">
      <div class="panel-heading">
        <h4>Order #<?php echo $id; ?></h4>
      </div>

      <ul class="order-items-list mb-3">
        <?php foreach($items as $it): ?>
          <li class="order-item-card">
            <div class="order-item-head">
              <strong><?php echo htmlspecialchars($it['name']); ?></strong>
              <span>x <?php echo $it['qty']; ?></span>
            </div>
            <div class="order-item-meta">
              <span>&dollar;<?php echo number_format($it['unit_price'],2); ?></span>
              <span>Subtotal: &dollar;<?php echo number_format((float)$it['unit_price'] * (int)$it['qty'], 2); ?></span>
            </div>
            <?php if(!empty($it['options_json'])): ?>
              <div class="order-options"><strong>Options:</strong> <?php echo htmlspecialchars($it['options_json']); ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <form method="post" class="cashier-form mb-0">
        <input type="hidden" name="order_id" value="<?php echo $id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <div class="cashier-form-row">
          <input type="text" name="table_number" class="form-control" value="<?php echo htmlspecialchars($order['table_number'] ?? ''); ?>" placeholder="Table number">
          <select name="new_status" class="form-select form-select-lg">
            <option value="new">New</option>
            <option value="paid">Paid</option>
            <option value="ready">Ready</option>
            <option value="completed">Completed</option>
          </select>
          <button class="btn btn-primary">Update status</button>
          <a class="btn btn-outline-primary" href="admin_receipt.php?order=<?php echo $id; ?>" target="_blank">Print receipt</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>