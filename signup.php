<?php
require_once __DIR__.'/db.php';
if(is_logged_in()) header('Location: index.php');
$errors = [];
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if(!$username) $errors[] = 'Username required';
    if(strlen($password) < 4) $errors[] = 'Password too short';
    if(empty($errors)){
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if($stmt->fetch()){ $errors[] = 'Username taken'; }
        else{
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$username, $hash, 'customer']);
            header('Location: login.php?signup=1'); exit;
        }
    }
}
include 'header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <h3>Sign up</h3>
    <?php if($errors): ?>
      <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars',$errors)); ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary">Sign up</button>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>