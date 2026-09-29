<?php
require_once __DIR__.'/db.php';
if(is_logged_in() && !in_array($_SESSION['role'] ?? '', ['customer','admin','manager','cashier'], true)){
    echo '<div class="container mt-5"><div class="alert alert-danger">This area is for guests and customer-facing staff.</div></div>';
    exit;
}

$db = getDB();
$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM items WHERE id = ? AND is_active = 1 AND category IN ('food', 'drink')");
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if(!$item){
    header('Location: menu.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $cart = &$_SESSION['cart'];
    if(!$cart) $cart = [];
    $cart[] = [
        'item_id'=>$item['id'],
        'name'=>$item['name'],
        'price'=>$item['price'],
        'category'=>$item['category'],
        'qty'=>max(1, (int)($_POST['qty'] ?? 1)),
        'options'=>$_POST['options'] ?? []
    ];
    header('Location: cart.php');
    exit;
}

$options = json_decode($item['options'] ?? '[]', true) ?: [];
include 'header.php';
?>
<div class="customize-shell">
  <a class="back-link" href="menu.php">&larr; Back to menu</a>
  <div class="customize-card">
    <div class="customize-visual">
      <img src="<?php echo htmlspecialchars(menu_item_image($item['name'])); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
    </div>
    <div class="customize-content">
      <span class="menu-category-tag"><?php echo htmlspecialchars(strtoupper($item['category'])); ?></span>
      <div class="customize-title-row">
        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
        <div class="menu-price">&dollar;<?php echo number_format($item['price'], 2); ?></div>
      </div>
      <p class="menu-description"><?php echo htmlspecialchars($item['description']); ?></p>

      <form class="menu-item-form customize-form" method="post">
        <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
        <?php if(!empty($options)): ?>
          <div class="menu-options-block">
            <?php foreach($options as $option): ?>
              <div class="menu-option-row">
                <label class="menu-option-label"><?php echo htmlspecialchars($option['label']); ?></label>
                <?php if(($option['type'] ?? '') === 'checkboxes'): ?>
                  <?php foreach($option['choices'] as $choice): ?>
                    <div class="form-check custom-check">
                      <input class="form-check-input" type="checkbox" name="options[<?php echo htmlspecialchars($option['key']); ?>][]" value="<?php echo htmlspecialchars($choice); ?>" id="opt_<?php echo $item['id'].'_'.md5($choice); ?>">
                      <label class="form-check-label" for="opt_<?php echo $item['id'].'_'.md5($choice); ?>"><?php echo htmlspecialchars($choice); ?></label>
                    </div>
                  <?php endforeach; ?>
                <?php elseif(($option['type'] ?? '') === 'select'): ?>
                  <select name="options[<?php echo htmlspecialchars($option['key']); ?>]" class="form-select luxury-select">
                    <?php foreach($option['choices'] as $choice): ?><option value="<?php echo htmlspecialchars($choice); ?>"><?php echo htmlspecialchars($choice); ?></option><?php endforeach; ?>
                  </select>
                <?php elseif(($option['type'] ?? '') === 'number'): ?>
                  <input type="number" name="options[<?php echo htmlspecialchars($option['key']); ?>]" class="form-control luxury-input" value="<?php echo (int)($option['min'] ?? 0); ?>">
                <?php elseif(($option['type'] ?? '') === 'text'): ?>
                  <input type="text" name="options[<?php echo htmlspecialchars($option['key']); ?>]" class="form-control luxury-input">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="menu-form-footer">
          <label class="qty-wrap"><span>Quantity</span><input type="number" name="qty" value="1" min="1" class="form-control luxury-input qty-input"></label>
          <button class="btn btn-primary premium-add-btn" type="submit">Add to cart</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>