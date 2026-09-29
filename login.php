<?php
require_once __DIR__.'/db.php';
if(is_logged_in()) header('Location: index.php');
$err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare('SELECT id, password_hash, role FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if($row && password_verify($password, $row['password_hash'])){
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $row['role'];
        // record login in audit_log if table exists
        try{
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ins = $db->prepare('INSERT INTO audit_log (user_id, action, ip) VALUES (?, ?, ?)');
            $ins->execute([$row['id'], 'login', $ip]);
        }catch(Exception $e){
            // ignore if audit table missing
        }
        header('Location: index.php'); exit;
    }else{
        $err = 'Invalid credentials';
    }
}
include 'header.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-header">
      <span class="brand-mark">✦</span>
      <div>
        <p class="eyebrow">Employee access</p>
        <h3>Staff login</h3>
      </div>
    </div>

    <?php if($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input name="username" class="form-control luxury-input" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control luxury-input" required>
      </div>
      <button class="btn btn-primary w-100 premium-add-btn">Employee log in</button>
    </form>

    <div class="mt-3 text-center">
      <a href="menu.php" class="btn btn-outline-secondary w-100">Continue as guest</a>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>