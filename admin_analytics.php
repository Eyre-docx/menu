<?php
require_once __DIR__.'/db.php';
require_role('admin');
$db = getDB();

$todaySummary = $db->query("SELECT COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE()")->fetch();
$weekSummary = $db->query("SELECT COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetch();
$previousWeekSummary = $db->query("SELECT COALESCE(SUM(total_price), 0) as revenue FROM orders WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 DAY), INTERVAL 7 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetch();
$avgTicket = $db->query("SELECT COALESCE(AVG(total_price), 0) as avg_ticket FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch();
$previousAvgTicket = $db->query("SELECT COALESCE(AVG(total_price), 0) as avg_ticket FROM orders WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 30 DAY), INTERVAL 30 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch();
$staffCoverage = $db->query("SELECT u.role, COUNT(*) as total_staff, SUM(CASE WHEN COALESCE(em.is_on_shift,0)=1 THEN 1 ELSE 0 END) as on_shift FROM users u LEFT JOIN employee_meta em ON em.user_id = u.id WHERE u.is_active = 1 AND u.role != 'customer' GROUP BY u.role ORDER BY total_staff DESC")->fetchAll();
$allStaffCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role != 'customer'")->fetchColumn();
$allOnShift = (int)$db->query("SELECT COUNT(*) FROM users u LEFT JOIN employee_meta em ON em.user_id = u.id WHERE u.is_active = 1 AND u.role != 'customer' AND COALESCE(em.is_on_shift, 0) = 1")->fetchColumn();
$coverageRate = $allStaffCount > 0 ? ($allOnShift / $allStaffCount) * 100 : 0;
$weekDelta = ((float)($weekSummary['revenue'] ?? 0) - (float)($previousWeekSummary['revenue'] ?? 0));
$weekDeltaPct = ((float)($previousWeekSummary['revenue'] ?? 0) > 0) ? ($weekDelta / (float)($previousWeekSummary['revenue'])) * 100 : 0;
$ticketDelta = ((float)($avgTicket['avg_ticket'] ?? 0) - (float)($previousAvgTicket['avg_ticket'] ?? 0));
$ticketDeltaPct = ((float)($previousAvgTicket['avg_ticket'] ?? 0) > 0) ? ($ticketDelta / (float)($previousAvgTicket['avg_ticket'])) * 100 : 0;

if (!empty($_GET['export'])) {
    $which = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_' . preg_replace('/[^a-z0-9_-]/i', '', $which) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($which === 'daily') {
        $stmt = $db->prepare("SELECT DATE(created_at) as dt, COUNT(*) as orders, SUM(total_price) as revenue FROM orders GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC");
        $stmt->execute();
        fputcsv($out, ['date', 'orders', 'revenue']);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['dt'], $r['orders'], $r['revenue']]);
        }
    } elseif ($which === 'monthly') {
        $stmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as m, COUNT(*) as orders, SUM(total_price) as revenue FROM orders GROUP BY m ORDER BY m DESC");
        $stmt->execute();
        fputcsv($out, ['month', 'orders', 'revenue']);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['m'], $r['orders'], $r['revenue']]);
        }
    } elseif ($which === 'yearly') {
        $stmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y') as y, COUNT(*) as orders, SUM(total_price) as revenue FROM orders GROUP BY y ORDER BY y DESC");
        $stmt->execute();
        fputcsv($out, ['year', 'orders', 'revenue']);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['y'], $r['orders'], $r['revenue']]);
        }
    } elseif ($which === 'combos') {
        $stmt = $db->query("SELECT combo, COUNT(*) as cnt, SUM(total_price) as revenue FROM (
            SELECT o.id, GROUP_CONCAT(oi.item_id ORDER BY oi.item_id SEPARATOR ',') as combo, o.total_price
            FROM orders o JOIN order_items oi ON oi.order_id = o.id
            GROUP BY o.id
        ) t GROUP BY combo ORDER BY cnt DESC");
        fputcsv($out, ['combo_item_ids', 'orders', 'revenue']);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['combo'], $r['cnt'], $r['revenue']]);
        }
    } else {
        fputcsv($out, ['unknown export']);
    }
    fclose($out);
    exit;
}

if (!empty($_GET['json'])) {
    $daily = $db->query("SELECT DATE(created_at) as label, COUNT(*) as orders, COALESCE(SUM(total_price),0) as revenue FROM orders GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC LIMIT 7")->fetchAll();
    $daily = array_reverse($daily);
    $topItems = $db->query("SELECT oi.name, SUM(oi.qty) as sold, SUM(oi.qty * oi.unit_price) as revenue FROM order_items oi GROUP BY oi.name ORDER BY sold DESC LIMIT 5")->fetchAll();
    $categoryMix = $db->query("SELECT oi.category, SUM(oi.qty) as sold FROM order_items oi GROUP BY oi.category ORDER BY sold DESC")->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'summary' => [
            'todayOrders' => (int)($todaySummary['orders'] ?? 0),
            'todayRevenue' => (float)($todaySummary['revenue'] ?? 0),
            'weekRevenue' => (float)($weekSummary['revenue'] ?? 0),
            'avgTicket' => (float)($avgTicket['avg_ticket'] ?? 0),
            'weekDeltaPct' => round($weekDeltaPct, 2),
            'ticketDeltaPct' => round($ticketDeltaPct, 2),
            'coverageRate' => round($coverageRate, 1),
        ],
        'daily' => array_map(function ($row) {
            return [
                'label' => $row['label'],
                'orders' => (int)($row['orders'] ?? 0),
                'revenue' => (float)($row['revenue'] ?? 0),
            ];
        }, $daily),
        'topItems' => array_map(function ($row) {
            return [
                'label' => $row['name'],
                'sold' => (int)($row['sold'] ?? 0),
                'revenue' => (float)($row['revenue'] ?? 0),
            ];
        }, $topItems),
        'categoryMix' => array_map(function ($row) {
            return [
                'label' => ucfirst($row['category']),
                'value' => (int)($row['sold'] ?? 0),
            ];
        }, $categoryMix),
        'staffCoverage' => array_map(function ($row) {
            $total = (int)($row['total_staff'] ?? 0);
            $onShift = (int)($row['on_shift'] ?? 0);
            return [
                'label' => ucfirst($row['role']),
                'value' => $total > 0 ? (int)round(($onShift / $total) * 100) : 0,
            ];
        }, $staffCoverage),
    ], JSON_PRETTY_PRINT);
    exit;
}

$daily = $db->query("SELECT DATE(created_at) as label, COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue FROM orders GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC LIMIT 7")->fetchAll();
$daily = array_reverse($daily);
$topItems = $db->query("SELECT oi.name, SUM(oi.qty) as sold, SUM(oi.qty * oi.unit_price) as revenue FROM order_items oi GROUP BY oi.name ORDER BY sold DESC LIMIT 5")->fetchAll();
$categoryMix = $db->query("SELECT oi.category, SUM(oi.qty) as sold FROM order_items oi GROUP BY oi.category ORDER BY sold DESC")->fetchAll();

include 'header.php';
?>
<div class="analytics-shell">
  <div class="page-header mb-4">
    <span class="page-badge">📈</span>
    <div>
      <p class="eyebrow">Business intelligence</p>
      <h3>Analytics dashboard</h3>
    </div>
  </div>

  <div class="analytics-toolbar mb-4">
    <div class="live-status">
      <span class="live-dot"></span>
      <span>Live update</span>
      <strong id="liveStamp">just now</strong>
    </div>
    <div class="export-group">
      <button type="button" class="btn btn-sm btn-primary" id="pdfExportBtn">Export PDF</button>
      <a class="btn btn-sm btn-outline-secondary" href="?export=daily">Export Daily CSV</a>
      <a class="btn btn-sm btn-outline-secondary" href="?export=monthly">Export Monthly CSV</a>
      <a class="btn btn-sm btn-outline-secondary" href="?export=yearly">Export Yearly CSV</a>
      <a class="btn btn-sm btn-outline-secondary" href="?export=combos">Export Combos CSV</a>
    </div>
  </div>

  <div class="summary-grid mb-4">
    <div class="summary-card accent-purple">
      <div class="summary-icon">🧾</div>
      <div>
        <small>Orders today</small>
        <strong id="todayOrders"><?php echo (int)($todaySummary['orders'] ?? 0); ?></strong>
      </div>
    </div>
    <div class="summary-card accent-green">
      <div class="summary-icon">💰</div>
      <div>
        <small>Revenue today</small>
        <strong id="todayRevenue">$<?php echo number_format((float)($todaySummary['revenue'] ?? 0), 2); ?></strong>
      </div>
    </div>
    <div class="summary-card accent-gold">
      <div class="summary-icon">📊</div>
      <div>
        <small>7-day revenue</small>
        <strong id="weekRevenue">$<?php echo number_format((float)($weekSummary['revenue'] ?? 0), 2); ?></strong>
      </div>
    </div>
    <div class="summary-card accent-rose">
      <div class="summary-icon">🧠</div>
      <div>
        <small>Avg ticket</small>
        <strong id="avgTicket">$<?php echo number_format((float)($avgTicket['avg_ticket'] ?? 0), 2); ?></strong>
      </div>
    </div>
  </div>

  <div class="trend-strip mb-4">
    <div class="trend-card positive" id="weekTrendCard">
      <span>Week-on-week</span>
      <strong id="weekDeltaValue"><?php echo (($weekDeltaPct >= 0) ? '+' : '') . number_format($weekDeltaPct, 1) . '%'; ?></strong>
    </div>
    <div class="trend-card positive" id="ticketTrendCard">
      <span>Avg ticket</span>
      <strong id="ticketDeltaValue"><?php echo (($ticketDeltaPct >= 0) ? '+' : '') . number_format($ticketDeltaPct, 1) . '%'; ?></strong>
    </div>
    <div class="trend-card info" id="coverageTrendCard">
      <span>Shift coverage</span>
      <strong id="coverageValue"><?php echo number_format($coverageRate, 1) . '%'; ?></strong>
    </div>
  </div>

  <div class="chart-grid">
    <div class="chart-panel panel-card">
      <div class="panel-heading">
        <h4>Revenue trend</h4>
        <span class="soft-pill">7-day</span>
      </div>
      <div class="chart-wrap">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <div class="chart-panel panel-card">
      <div class="panel-heading">
        <h4>Orders by day</h4>
        <span class="soft-pill">Volume</span>
      </div>
      <div class="chart-wrap">
        <canvas id="ordersChart"></canvas>
      </div>
    </div>

    <div class="chart-panel panel-card wide-panel">
      <div class="panel-heading">
        <h4>Best sellers</h4>
        <span class="soft-pill">Top 5</span>
      </div>
      <div class="chart-wrap large-wrap">
        <canvas id="itemsChart"></canvas>
      </div>
    </div>

    <div class="chart-panel panel-card">
      <div class="panel-heading">
        <h4>Category mix</h4>
        <span class="soft-pill">Sold</span>
      </div>
      <div class="chart-wrap">
        <canvas id="categoryChart"></canvas>
      </div>
    </div>

    <div class="chart-panel panel-card wide-panel">
      <div class="panel-heading">
        <h4>Staff coverage</h4>
        <span class="soft-pill"><?php echo $allOnShift; ?>/<?php echo $allStaffCount; ?> on shift</span>
      </div>
      <div class="chart-wrap large-wrap">
        <canvas id="coverageChart"></canvas>
      </div>
    </div>
  </div>

  <div class="analytics-lower mt-4 row g-4">
    <div class="col-lg-6">
      <div class="panel-card">
        <div class="panel-heading">
          <h4>Top items</h4>
          <span class="soft-pill">Sales</span>
        </div>
        <div class="table-responsive">
          <table class="table dashboard-table align-middle mb-0">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty sold</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topItems as $item): ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['name']); ?></td>
                  <td><?php echo (int)($item['sold'] ?? 0); ?></td>
                  <td>$<?php echo number_format((float)($item['revenue'] ?? 0), 2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="panel-card">
        <div class="panel-heading">
          <h4>Role coverage</h4>
          <span class="soft-pill">On shift</span>
        </div>
        <div class="table-responsive">
          <table class="table dashboard-table align-middle mb-0">
            <thead>
              <tr>
                <th>Role</th>
                <th>On shift</th>
                <th>Coverage</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staffCoverage as $role): ?>
                <?php $total = (int)($role['total_staff'] ?? 0); $shifted = (int)($role['on_shift'] ?? 0); $coverage = $total > 0 ? round(($shifted / $total) * 100) : 0; ?>
                <tr>
                  <td><?php echo htmlspecialchars(ucfirst($role['role'])); ?></td>
                  <td><?php echo $shifted; ?>/<?php echo $total; ?></td>
                  <td><?php echo $coverage; ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const revenueChart = new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Revenue', data: [], borderColor: '#6d4ec2', backgroundColor: 'rgba(109, 78, 194, 0.12)', borderWidth: 3, tension: 0.35, fill: true }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { callback: (value) => '$' + value.toFixed(0) } }
      }
    }
  });

  const ordersChart = new Chart(document.getElementById('ordersChart'), {
    type: 'bar',
    data: { labels: [], datasets: [{ label: 'Orders', data: [], backgroundColor: '#4ea97f', borderRadius: 8 }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });

  const itemsChart = new Chart(document.getElementById('itemsChart'), {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#6d4ec2', '#4ea97f', '#d7a34b', '#d9728d', '#4d8bff'] }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  const categoryChart = new Chart(document.getElementById('categoryChart'), {
    type: 'polarArea',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#6d4ec2', '#d9728d', '#4ea97f', '#d7a34b'] }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: { r: { beginAtZero: true } }
    }
  });

  const coverageChart = new Chart(document.getElementById('coverageChart'), {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#6d4ec2', '#d9728d', '#4ea97f', '#d7a34b', '#5d647a'] }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  function formatMoney(value) {
    return '$' + Number(value || 0).toFixed(2);
  }

  function formatDelta(value) {
    return (value >= 0 ? '+' : '') + Number(value).toFixed(1) + '%';
  }

  function setTrendState(elementId, value) {
    const card = document.getElementById(elementId);
    if (!card) return;
    card.classList.toggle('negative', value < 0);
    card.classList.toggle('positive', value >= 0);
  }

  function updateSummary(data) {
    const weekDelta = Number(data.summary.weekDeltaPct || 0);
    const ticketDelta = Number(data.summary.ticketDeltaPct || 0);
    const coverageRate = Number(data.summary.coverageRate || 0);

    document.getElementById('todayOrders').textContent = data.summary.todayOrders;
    document.getElementById('todayRevenue').textContent = formatMoney(data.summary.todayRevenue);
    document.getElementById('weekRevenue').textContent = formatMoney(data.summary.weekRevenue);
    document.getElementById('avgTicket').textContent = formatMoney(data.summary.avgTicket);
    document.getElementById('weekDeltaValue').textContent = formatDelta(weekDelta);
    document.getElementById('ticketDeltaValue').textContent = formatDelta(ticketDelta);
    document.getElementById('coverageValue').textContent = coverageRate.toFixed(1) + '%';
    document.getElementById('liveStamp').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    setTrendState('weekTrendCard', weekDelta);
    setTrendState('ticketTrendCard', ticketDelta);
    setTrendState('coverageTrendCard', 0);
  }

  function updateCharts(data) {
    const daily = data.daily || [];
    const topItems = data.topItems || [];
    const categoryMix = data.categoryMix || [];
    const staffCoverage = data.staffCoverage || [];

    revenueChart.data.labels = daily.map(item => item.label.slice(5));
    revenueChart.data.datasets[0].data = daily.map(item => Number(item.revenue || 0));
    revenueChart.update();

    ordersChart.data.labels = daily.map(item => item.label.slice(5));
    ordersChart.data.datasets[0].data = daily.map(item => Number(item.orders || 0));
    ordersChart.update();

    itemsChart.data.labels = topItems.map(item => item.label);
    itemsChart.data.datasets[0].data = topItems.map(item => Number(item.sold || 0));
    itemsChart.update();

    categoryChart.data.labels = categoryMix.map(item => item.label);
    categoryChart.data.datasets[0].data = categoryMix.map(item => Number(item.value || 0));
    categoryChart.update();

    coverageChart.data.labels = staffCoverage.map(item => item.label);
    coverageChart.data.datasets[0].data = staffCoverage.map(item => Number(item.value || 0));
    coverageChart.update();
  }

  async function refreshAnalytics() {
    try {
      const response = await fetch('admin_analytics.php?json=1&ts=' + Date.now());
      const data = await response.json();
      updateSummary(data);
      updateCharts(data);
    } catch (error) {
      console.error('Analytics refresh failed', error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshAnalytics();
    setInterval(refreshAnalytics, 15000);
  });
</script>

<?php include 'footer.php'; ?>