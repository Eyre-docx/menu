<?php
require_once __DIR__.'/db.php';
require_role('admin');
$db = getDB();
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    $act = $_POST['action'];
    $token = $_POST['csrf_token'] ?? '';
    if(!verify_csrf_token($token)){
        $errors[] = 'Invalid CSRF token';
    } else {
        if($act==='create'){
            $name = trim($_POST['name']); $desc = trim($_POST['description']); $price = (float)$_POST['price']; $category = $_POST['category'];
            $options_json = $_POST['options_json'] ?? '[]';
            // Basic validation
            if($name === '') $errors[] = 'Name required';
            if($price < 0) $errors[] = 'Price invalid';
            if(empty($errors)){
                $stmt = $db->prepare('INSERT INTO items (name, description, price, category, options, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
                $stmt->execute([$name,$desc,$price,$category,$options_json]);
                header('Location: admin_menu.php'); exit;
            }
        }elseif($act==='toggle' && !empty($_POST['id'])){
            // Toggle active state so admin can re-enable items
            $stmt = $db->prepare('UPDATE items SET is_active = 1 - is_active WHERE id = ?');
            $stmt->execute([$_POST['id']]);
            header('Location: admin_menu.php'); exit;
        }
    }
}
$items = $db->query('SELECT * FROM items ORDER BY category, name')->fetchAll();
include 'header.php';
$csrf = htmlspecialchars(get_csrf_token());
?>
<h3>Menu Management</h3>
<?php if($errors): ?><div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars',$errors)); ?></div><?php endif; ?>
<div class="row">
  <div class="col-md-7">
    <h5>Existing items</h5>
    <div class="panel-card mt-3">
      <div class="table-responsive">
        <table class="table dashboard-table align-middle mb-0">
          <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Active</th><th></th></tr></thead>
          <tbody>
            <?php foreach($items as $it): ?>
              <tr>
                <td><?php echo htmlspecialchars($it['name']); ?></td>
                <td><?php echo htmlspecialchars($it['category']); ?></td>
                <td>&dollar;<?php echo number_format($it['price'],2); ?></td>
                <td><?php echo $it['is_active'] ? '<span class="badge bg-success">Active</span>': '<span class="badge bg-secondary">Disabled</span>'; ?></td>
                <td>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="id" value="<?php echo $it['id']; ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button class="btn btn-sm <?php echo $it['is_active'] ? 'btn-warning' : 'btn-success'; ?>"><?php echo $it['is_active'] ? 'Disable' : 'Enable'; ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <h5>Add item</h5>
    <form method="post" id="add-item-form">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
      <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control"></div>
      <div class="mb-2"><label class="form-label">Price</label><input name="price" type="number" step="0.01" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="food">Food</option>
          <option value="beverage">Beverage</option>
          <option value="other">Other</option>
        </select>
      </div>

      <h6>Visual Options Builder</h6>
      <div class="mb-2 border rounded p-2" id="options-builder">
        <div class="row g-2 align-items-end">
          <div class="col-5"><label class="form-label">Key</label><input id="opt-key" class="form-control" placeholder="toppings"></div>
          <div class="col-7"><label class="form-label">Label</label><input id="opt-label" class="form-control" placeholder="Toppings"></div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-6"><label class="form-label">Type</label>
            <select id="opt-type" class="form-select"><option value="checkboxes">Checkboxes</option><option value="select">Select</option><option value="number">Number</option><option value="text">Text</option></select>
          </div>
          <div class="col-6" id="choices-col"><label class="form-label">Choices (comma separated)</label><input id="opt-choices" class="form-control" placeholder="Onions,Cheese,Bacon"></div>
        </div>
        <div class="mt-2">
          <button type="button" id="add-opt" class="btn btn-sm btn-outline-primary">Add option</button>
        </div>
        <hr>
        <div id="options-list"></div>
      </div>

      <input type="hidden" name="options_json" id="options-json" value="[]">
      <button class="btn btn-primary">Add item</button>
    </form>
  </div>
</div>

<script>
(function(){
  const optKey = document.getElementById('opt-key');
  const optLabel = document.getElementById('opt-label');
  const optType = document.getElementById('opt-type');
  const optChoices = document.getElementById('opt-choices');
  const addBtn = document.getElementById('add-opt');
  const list = document.getElementById('options-list');
  const jsonField = document.getElementById('options-json');

  let options = [];

  function renderList(){
    list.innerHTML = '';
    options.forEach((o, i)=>{
      const div = document.createElement('div');
      div.className = 'mb-2 p-2 bg-white border rounded d-flex justify-content-between align-items-center';
      div.innerHTML = '<div><strong>'+escapeHtml(o.label)+'</strong> <small class="text-muted">('+escapeHtml(o.key)+') - '+escapeHtml(o.type)+'</small><br>' + (o.choices? 'Choices: '+escapeHtml(o.choices.join(', ')) : '') + '</div>';
      const rm = document.createElement('button'); rm.className='btn btn-sm btn-danger'; rm.textContent='Remove';
      rm.onclick = ()=>{ options.splice(i,1); syncJSON(); renderList(); };
      div.appendChild(rm);
      list.appendChild(div);
    });
    syncJSON();
  }
  function syncJSON(){
    jsonField.value = JSON.stringify(options, null, 2);
  }
  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  optType.addEventListener('change', ()=>{
    if(optType.value === 'checkboxes' || optType.value === 'select'){
      document.getElementById('choices-col').style.display = 'block';
    }else{
      document.getElementById('choices-col').style.display = 'none';
    }
  });

  addBtn.addEventListener('click', ()=>{
    const key = optKey.value.trim();
    const label = optLabel.value.trim();
    const type = optType.value;
    if(!key || !label){ alert('Key and label required'); return; }
    let obj = { key: key, label: label, type: type };
    if((type==='checkboxes' || type==='select')){
      const raw = optChoices.value.trim();
      if(!raw){ alert('Provide choices'); return; }
      obj.choices = raw.split(',').map(s=>s.trim()).filter(Boolean);
    }
    options.push(obj);
    optKey.value=''; optLabel.value=''; optChoices.value=''; optType.value='checkboxes';
    renderList();
  });

  // initialize
  renderList();
})();
</script>

<?php include 'footer.php'; ?>