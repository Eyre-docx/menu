<?php
require_once __DIR__.'/db.php';
if(is_logged_in() && !in_array($_SESSION['role'] ?? '', ['customer','admin','manager','cashier'], true)){
    echo '<div class="container mt-5"><div class="alert alert-danger">This area is for guests and customer-facing staff.</div></div>';
    exit;
}
$db = getDB();
if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['item_id'])){
    $item = $db->prepare('SELECT id, name, price, category FROM items WHERE id = ? AND is_active = 1');
    $item->execute([$_POST['item_id']]);
    $it = $item->fetch();
    if($it){
        $cart = &$_SESSION['cart'];
        if(!$cart) $cart = [];
        $options = $_POST['options'] ?? [];
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $cart[] = [
            'item_id'=>$it['id'],
            'name'=>$it['name'],
            'price'=>$it['price'],
            'category'=>$it['category'],
            'qty'=>$qty,
            'options'=>$options
        ];
    }
    header('Location: cart.php'); exit;
}
if(isset($_GET['clear'])){ unset($_SESSION['cart']); header('Location: cart.php'); exit; }
if(isset($_GET['remove'])){
    $i = (int)$_GET['remove'];
    if(isset($_SESSION['cart'][$i])){ unset($_SESSION['cart'][$i]); $_SESSION['cart'] = array_values($_SESSION['cart']); }
    header('Location: cart.php'); exit;
}
include 'header.php';
$cart = $_SESSION['cart'] ?? [];
$total = 0;
foreach($cart as $c) $total += $c['price']*$c['qty'];
?>
<h3>Cart</h3>
<?php if(isset($_GET['ordered'])): ?>
  <div class="alert alert-success">Your order has been placed successfully.</div>
<?php endif; ?>
<?php if(empty($cart)): ?>
  <div class="alert alert-info">Your cart is empty. <a href="menu.php">Browse menu</a></div>
<?php else: ?>
  <div class="panel-card">
    <div class="table-responsive">
      <table class="table dashboard-table align-middle mb-0">
        <thead><tr><th>#</th><th>Item</th><th>Options</th><th>Qty</th><th>Price</th><th>Subtotal</th><th></th></tr></thead>
        <tbody>
          <?php foreach($cart as $i=> $c): ?>
            <tr>
              <td><?php echo $i+1; ?></td>
              <td><?php echo htmlspecialchars($c['name']); ?></td>
              <td><pre class="cart-options"><?php echo htmlspecialchars(json_encode($c['options'])); ?></pre></td>
              <td><?php echo $c['qty']; ?></td>
              <td>&dollar;<?php echo number_format($c['price'],2); ?></td>
              <td>&dollar;<?php echo number_format($c['price']*$c['qty'],2); ?></td>
              <td><a class="btn btn-sm btn-danger" href="?remove=<?php echo $i; ?>">Remove</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="menu.php">Order More</a>
      <a class="btn btn-outline-secondary" href="?clear=1">Clear cart</a>
    </div>
    <div>
      <strong>Total: &dollar;<?php echo number_format($total,2); ?></strong>
      <form method="post" action="order_submit.php" style="display:inline-block; margin-left: 10px;">
        <button class="btn btn-primary">Place order</button>
      </form>
    </div>
  </div>
<?php endif; ?>
<?php include 'footer.php'; ?>