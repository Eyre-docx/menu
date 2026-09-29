<?php
require_once __DIR__.'/db.php';
if(is_logged_in() && !in_array($_SESSION['role'] ?? '', ['customer','admin','manager','cashier'], true)){
    echo '<div class="container mt-5"><div class="alert alert-danger">Guest ordering is available only for customers and customer-facing staff.</div></div>';
    exit;
}
$db = getDB();
$cart = $_SESSION['cart'] ?? [];
if(empty($cart)){
    header('Location: cart.php'); exit;
}
// create order
$db->beginTransaction();
try{
    $stmt = $db->prepare('INSERT INTO orders (user_id, table_number, status, total_price, created_at) VALUES (?, NULL, ?, ?, NOW())');
    $total = 0; foreach($cart as $c) $total += $c['price']*$c['qty'];
    $stmt->execute([$_SESSION['user_id'] ?? null, 'new', $total]);
    $order_id = $db->lastInsertId();
    $stmt = $db->prepare('INSERT INTO order_items (order_id, item_id, name, category, qty, unit_price, options_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach($cart as $c){
        $stmt->execute([$order_id, $c['item_id'], $c['name'], $c['category'], $c['qty'], $c['price'], json_encode($c['options'])]);
    }
    // Simple routing: if any order item is food -> kitchen queue; if beverage -> bar queue. We'll mark statuses per-order-item via order_items.status
    $db->commit();
    // Optionally send notification to cashier/kitchen/bar via flags in DB
    unset($_SESSION['cart']);
    header('Location: cart.php?ordered=1'); exit;
}catch(Exception $e){
    $db->rollBack();
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>