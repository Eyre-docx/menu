<?php
require_once __DIR__.'/db.php';
if(is_logged_in() && !in_array($_SESSION['role'] ?? '', ['customer','admin','manager','cashier'], true)){
    echo '<div class="container mt-5"><div class="alert alert-danger">This area is for guests and customer-facing staff.</div></div>';
    exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['item_id'])){
    $db = getDB();
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
    header('Location: menu.php?added=1');
    exit;
}
$db = getDB();
$allItems = $db->query("SELECT * FROM items WHERE is_active = 1 AND category IN ('food', 'drink') ORDER BY category, name")->fetchAll();
$itemsByCategory = ['food' => [], 'drink' => []];
foreach($allItems as $item){
    if(count($itemsByCategory[$item['category']]) < 2){
        $itemsByCategory[$item['category']][] = $item;
    }
}
$items = array_merge($itemsByCategory['food'], $itemsByCategory['drink']);
include 'header.php';
?>
<div class="menu-shell">
  <div class="page-header mb-4">
    <span class="page-badge">🍽️</span>
    <div>
      <p class="eyebrow">Curated collection</p>
      <h3>Signature menu</h3>
    </div>
  </div>

  <div class="menu-intro mb-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <p class="mb-0">Choose items and customize your order with a polished service experience built for warm, memorable evenings.</p>
    <a class="btn btn-outline-primary" href="cart.php">View cart</a>
  </div>

  <?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success">Item added to cart. Keep browsing or view your cart when ready.</div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach($items as $it): ?>
      <div class="col-lg-6">
        <article class="menu-item-card">
          <a class="menu-item-image-link" href="customize.php?item_id=<?php echo (int)$it['id']; ?>" aria-label="Customize <?php echo htmlspecialchars($it['name']); ?>">
            <img class="menu-item-image" src="<?php echo htmlspecialchars(menu_item_image($it['name'])); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
          </a>
          <div class="menu-item-top">
            <div>
              <span class="menu-category-tag"><?php echo htmlspecialchars(strtoupper($it['category'])); ?></span>
              <h5><?php echo htmlspecialchars($it['name']); ?></h5>
            </div>
            <div class="menu-price">&dollar;<?php echo number_format($it['price'],2); ?></div>
          </div>

          <p class="menu-description"><?php echo htmlspecialchars($it['description']); ?></p>

          <a class="btn btn-primary menu-customize-btn" href="customize.php?item_id=<?php echo (int)$it['id']; ?>">Customize order</a>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include 'footer.php'; ?>