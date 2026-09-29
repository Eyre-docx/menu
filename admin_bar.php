<?php
require_once __DIR__.'/db.php';
require_role(['admin','manager','bar']);
$db = getDB();
$csrf = htmlspecialchars(get_csrf_token());

// Handle marking item or order done for beverages
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['action'])){
    $token = $_POST['csrf_token'] ?? '';
    if(!verify_csrf_token($token)){
        die('Invalid CSRF token');
    }
    if($_POST['action'] === 'done_item' && !empty($_POST['item_id'])){
        $id = (int)$_POST['item_id'];
        $db->prepare('UPDATE order_items SET status = ? WHERE id = ?')->execute(['done',$id]);
    }elseif($_POST['action'] === 'done_order' && !empty($_POST['order_id'])){
        $oid = (int)$_POST['order_id'];
        $db->prepare('UPDATE order_items SET status = ? WHERE order_id = ? AND category = ?')->execute(['done',$oid,'beverage']);
    }
    header('Location: admin_bar.php'); exit;
}

// Fetch pending beverage items and group by order
$rows = $db->query("SELECT oi.*, o.created_at, o.id as order_id, o.table_number FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.category = 'beverage' AND oi.status <> 'done' ORDER BY o.created_at ASC, oi.id ASC")->fetchAll();
$orders = [];
foreach($rows as $r){
    $oid = $r['order_id'];
    if(!isset($orders[$oid])){
        $orders[$oid] = ['order_id'=>$oid, 'created_at'=>$r['created_at'], 'table_number'=>$r['table_number'] ?? null, 'items'=>[]];
    }
    $orders[$oid]['items'][] = $r;
}

include 'header.php';
?>
<div class="content-shell">
  <div class="page-header mb-4">
    <span class="page-badge">🍷</span>
    <div>
      <p class="eyebrow">Drink service</p>
      <h3>Bar Queue</h3>
    </div>
  </div>

  <?php if(empty($orders)): ?>
    <div class="alert alert-info">No pending beverage items.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach($orders as $ord): ?>
        <div class="col-lg-6">
          <details class="panel-card" open>
            <summary class="panel-heading" style="cursor:pointer; list-style:none;">
              <h4>Order #<?php echo htmlspecialchars($ord['order_id']); ?></h4>
              <span class="soft-pill">Table <?php echo htmlspecialchars($ord['table_number'] ?? 'TBD'); ?></span>
            </summary>
            <div class="mt-3">
              <div class="text-muted small mb-3">Placed: <?php echo htmlspecialchars($ord['created_at']); ?></div>
              <div class="shortcut-list">
                <?php foreach($ord['items'] as $it): ?>
                  <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                      <div>
                        <strong><?php echo htmlspecialchars($it['name']); ?></strong> x <?php echo $it['qty']; ?>
                        <?php if(!empty($it['options_json'])): ?>
                          <div class="mt-2 small text-dark">Specifications: <?php echo htmlspecialchars($it['options_json']); ?></div>
                        <?php endif; ?>
                      </div>
                      <form method="post">
                        <input type="hidden" name="item_id" value="<?php echo $it['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <button class="btn btn-sm btn-success" name="action" value="done_item">Done</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <form method="post" class="mt-3">
                <input type="hidden" name="order_id" value="<?php echo $ord['order_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <button class="btn btn-outline-success" name="action" value="done_order">Mark whole order done</button>
              </form>
            </div>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>