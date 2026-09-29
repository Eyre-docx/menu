<?php
require_once __DIR__.'/db.php';
require_role(['admin','manager','cashier']);
$db = getDB();
$id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
if(!$id){ echo "Invalid order"; exit; }
$stmt = $db->prepare('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?'); $stmt->execute([$id]);
$order = $stmt->fetch();
if(!$order){ echo "Order not found"; exit; }
$items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?'); $items->execute([$id]); $items = $items->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt #<?php echo $order['id']; ?></title>
  <style>
    body{font-family:Arial, sans-serif; max-width:480px;margin:20px auto}
    h2{text-align:center}
    table{width:100%;border-collapse:collapse}
    td,th{padding:6px;border-bottom:1px solid #ddd}
    .total{font-weight:700}
    .meta{font-size:0.9em;color:#666}
    .receipt-actions{display:flex;justify-content:center;gap:8px}
    .pay-button{padding:8px 18px;border:0;border-radius:4px;background:#176b4d;color:#fff;font-size:1rem;cursor:pointer}
    .payment-dialog{width:min( calc(100% - 32px), 360px);border:0;border-radius:12px;padding:24px;text-align:center;box-shadow:0 16px 48px #0004}
    .payment-dialog::backdrop{background:#0008}
    .payment-dialog h3{margin-top:0}
    .qr-placeholder{display:grid;place-items:center;width:192px;height:192px;margin:16px auto;border:10px solid #fff;outline:1px solid #ddd;background-color:#fff;background-image:repeating-conic-gradient(#222 0% 25%,#fff 0% 50%);background-size:24px 24px}
    .qr-placeholder svg{width:100%;height:100%;background:#fff}
    .payment-note{color:#666;font-size:.9rem}
    .dialog-close{padding:7px 16px;border:1px solid #bbb;border-radius:4px;background:#fff;cursor:pointer}
    @media print{.no-print{display:none}}
  </style>
</head>
<body>
  <h2>Receipt</h2>
  <div class="meta">Order #<?php echo $order['id']; ?> - Table: <?php echo htmlspecialchars($order['table_number'] ?? 'TBD'); ?> - <?php echo $order['created_at']; ?> - Customer: <?php echo htmlspecialchars($order['username'] ?? 'Guest'); ?></div>
  <table>
    <thead><tr><th>Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Subtotal</th></tr></thead>
    <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?php echo htmlspecialchars($it['name']); ?><br><small><?php echo htmlspecialchars($it['options_json']); ?></small></td>
          <td style="text-align:center"><?php echo $it['qty']; ?></td>
          <td style="text-align:right">&dollar;<?php echo number_format($it['unit_price']*$it['qty'],2); ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td colspan="2" class="total">Total</td><td style="text-align:right" class="total">&dollar;<?php echo number_format($order['total_price'],2); ?></td></tr>
    </tbody>
  </table>
  <div style="margin-top:12px;text-align:center">Thank you for your order!</div>
  <div style="margin-top:12px" class="receipt-actions no-print">
    <button type="button" class="pay-button" onclick="document.getElementById('payment-dialog').showModal()">Pay</button>
    <button type="button" onclick="window.print()">Print</button>
  </div>

  <dialog id="payment-dialog" class="payment-dialog" aria-labelledby="payment-title">
    <h3 id="payment-title">Pay for your order</h3>
    <p>Order #<?php echo $order['id']; ?> &middot; Total: <strong>&dollar;<?php echo number_format($order['total_price'],2); ?></strong></p>
    <div class="qr-placeholder" role="img" aria-label="QR code placeholder">
      <svg viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges">
        <rect width="21" height="21" fill="#fff"/>
        <path fill="#222" d="M0 0h7v7H0zm1 1v5h5V1zm1 1h3v3H2zm12-2h7v7h-7zm1 1v5h5V1zm1 1h3v3h-3zM0 14h7v7H0zm1 1v5h5v-5zm1 1h3v3H2zm8-16h2v2h-2zm-2 3h2v2H9zm3 1h2v2h-2zm-3 3h2v2H9zm3 1h2v2h-2zm3 1h2v2h-2zm-6 2h2v2H9zm3 1h2v2h-2zm3 2h2v2h-2zm3-4h2v2h-2zm-8 5h2v2h-2zm4 2h2v2h-2zm4 0h2v2h-2z"/>
      </svg>
    </div>
    <p class="payment-note">QR payment is a placeholder and cannot be scanned. No payment will be processed.</p>
    <form method="dialog"><button type="submit" class="dialog-close">Close</button></form>
  </dialog>
</body>
</html>