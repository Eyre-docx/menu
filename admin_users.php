<?php
require_once __DIR__.'/db.php';
require_admin_only();
$db = getDB();
$errors = [];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token = $_POST['csrf_token'] ?? '';
    if(!verify_csrf_token($token)){
        $errors[] = 'Invalid CSRF token';
    } else {
        if(!empty($_POST['add_employee'])){
            $newUsername = trim((string)($_POST['new_username'] ?? ''));
            $newPassword = $_POST['new_password'] ?? '';
            $newRole = trim((string)($_POST['new_role'] ?? 'cashier'));
            if($newUsername !== '' && $newPassword !== ''){
                $exists = $db->prepare('SELECT id FROM users WHERE username = ?');
                $exists->execute([$newUsername]);
                if($exists->fetch()){
                    $errors[] = 'Employee username already exists.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')->execute([$newUsername, $hash, $newRole]);
                }
            }
        }

        if(!empty($_POST['change_role'])){
            $id = (int)$_POST['id'];
            $role = trim((string)($_POST['role'] ?? 'customer'));
            if($role !== 'customer'){
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            }
        }

        if(!empty($_POST['toggle_active'])){
            $id = (int)$_POST['id'];
            $stmt = $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
            $stmt->execute([$id]);
        }

        if(!empty($_POST['clock_in'])){
            $id = (int)$_POST['id'];
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO employee_meta (user_id, is_on_shift, clock_in_at, clock_out_at) VALUES (?, 1, ?, NULL) ON DUPLICATE KEY UPDATE is_on_shift = 1, clock_in_at = COALESCE(clock_in_at, VALUES(clock_in_at)), clock_out_at = NULL');
            $stmt->execute([$id, $now]);
        }

        if(!empty($_POST['clock_out'])){
            $id = (int)$_POST['id'];
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO employee_meta (user_id, is_on_shift, clock_in_at, clock_out_at) VALUES (?, 0, NULL, ?) ON DUPLICATE KEY UPDATE is_on_shift = 0, clock_out_at = ?, clock_in_at = COALESCE(clock_in_at, NULL)');
            $stmt->execute([$id, $now, $now]);
        }

        if(!empty($_POST['save_meta'])){
            $id = (int)$_POST['id'];
            $jobTitle = trim((string)($_POST['job_title'] ?? ''));
            $quote = trim((string)($_POST['quote'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $shiftStatus = !empty($_POST['is_on_shift']) ? 1 : 0;
            $shiftNote = trim((string)($_POST['shift_note'] ?? ''));
            $check = $db->prepare('SELECT 1 FROM employee_meta WHERE user_id = ?');
            $check->execute([$id]);
            if($check->fetch()){
                $stmt = $db->prepare('UPDATE employee_meta SET job_title = ?, quote = ?, notes = ?, is_on_shift = ?, shift_note = ? WHERE user_id = ?');
                $stmt->execute([$jobTitle, $quote, $notes, $shiftStatus, $shiftNote, $id]);
            } else {
                $stmt = $db->prepare('INSERT INTO employee_meta (user_id, job_title, quote, notes, is_on_shift, shift_note) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$id, $jobTitle, $quote, $notes, $shiftStatus, $shiftNote]);
            }
        }
    }
}
$users = $db->query('SELECT u.*, em.job_title, em.quote, em.notes, em.is_on_shift, em.shift_note, em.clock_in_at, em.clock_out_at FROM users u LEFT JOIN employee_meta em ON em.user_id = u.id WHERE u.role != "customer" ORDER BY u.created_at DESC')->fetchAll();
include 'header.php';
$csrf = htmlspecialchars(get_csrf_token());
?>
<div class="content-shell">
  <div class="panel-card mb-4">
    <div class="panel-heading">
      <h4>Add employee</h4>
    </div>
    <form method="post" class="row g-3 align-items-end">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input type="text" name="new_username" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Password</label>
        <input type="password" name="new_password" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Role</label>
        <select name="new_role" class="form-select">
          <option value="cashier">Cashier</option>
          <option value="kitchen">Kitchen</option>
          <option value="bar">Bar</option>
          <option value="manager">Manager</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary" name="add_employee" value="1">Add</button>
      </div>
    </form>
  </div>
  <div class="page-header mb-4">
    <span class="page-badge">👥</span>
    <div>
      <p class="eyebrow">Team directory</p>
      <h3>User Management</h3>
    </div>
  </div>

  <?php if($errors): ?><div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars',$errors)); ?></div><?php endif; ?>

  <div class="panel-card">
    <div class="table-responsive">
      <table class="table staff-table align-middle">
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Shift</th>
            <th>Clock times</th>
            <th>Title</th>
            <th>Quote</th>
            <th>Notes</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($users as $u): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
              <td>
                <span class="role-tag"><?php echo htmlspecialchars($u['role']); ?></span>
              </td>
              <td>
                <?php echo (int)($u['is_on_shift'] ?? 0) === 1 ? '<span class="status-badge on">On shift</span>' : '<span class="status-badge off">Off shift</span>'; ?>
              </td>
              <td>
                <small>
                  In: <?php echo htmlspecialchars($u['clock_in_at'] ?? '—'); ?><br>
                  Out: <?php echo htmlspecialchars($u['clock_out_at'] ?? '—'); ?>
                </small>
              </td>
              <td><?php echo htmlspecialchars($u['job_title'] ?? $u['role']); ?></td>
              <td><?php echo htmlspecialchars($u['quote'] ?? ''); ?></td>
              <td><small><?php echo nl2br(htmlspecialchars($u['notes'] ?? '')); ?></small></td>
              <td><?php echo htmlspecialchars($u['created_at']); ?></td>
              <td>
                <div class="stacked-forms">
                  <form method="post" class="compact-form">
                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <select name="role" class="form-select form-select-sm">
                      <option value="cashier" <?php if($u['role']=='cashier') echo 'selected'; ?>>Cashier</option>
                      <option value="kitchen" <?php if($u['role']=='kitchen') echo 'selected'; ?>>Kitchen</option>
                      <option value="bar" <?php if($u['role']=='bar') echo 'selected'; ?>>Bar</option>
                      <option value="manager" <?php if($u['role']=='manager') echo 'selected'; ?>>Manager</option>
                      <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Admin</option>
                    </select>
                    <button class="btn btn-sm btn-primary" name="change_role" value="1">Update</button>
                  </form>

                  <form method="post" class="compact-form">
                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <?php if((int)($u['is_on_shift'] ?? 0) === 1): ?>
                      <button class="btn btn-sm btn-danger" name="clock_out" value="1">Clock out</button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-success" name="clock_in" value="1">Clock in</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-warning" name="toggle_active" value="1"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></button>
                  </form>

                  <form method="post" class="compact-form meta-form">
                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input name="job_title" class="form-control form-control-sm" placeholder="Job title" value="<?php echo htmlspecialchars($u['job_title'] ?? ''); ?>">
                    <input name="quote" class="form-control form-control-sm" placeholder="Short quote" value="<?php echo htmlspecialchars($u['quote'] ?? ''); ?>">
                    <input name="shift_note" class="form-control form-control-sm" placeholder="Shift note" value="<?php echo htmlspecialchars($u['shift_note'] ?? ''); ?>">
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Private notes"><?php echo htmlspecialchars($u['notes'] ?? ''); ?></textarea>
                    <label class="checkbox-row"><input type="checkbox" name="is_on_shift" value="1" <?php echo !empty($u['is_on_shift']) ? 'checked' : ''; ?>> On shift</label>
                    <button class="btn btn-sm btn-outline-primary" name="save_meta" value="1">Save</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include 'footer.php'; ?>