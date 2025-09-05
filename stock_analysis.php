<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Analysis</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="header">
  <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
  <div class="header-middle">
    <div class="header-middle-title">Stock Management</div>
    
  </div>
  <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
  <div class="user-icon"></div>
</div>

<div class="layout">
<aside class="sidebar">
  <h1>Stock Dashboard</h1>
  <nav>
    <button class="salesbtn" disabled>Stock</button>
    <div class="otherbtn">
      <button class="Sbtn" onclick="window.location.href='sales.html'">Sales</button>
      <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
      <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>
    </div>
    <hr />
    <p>Sales Management</p>
    <div class="salebtn">
      <button class="tab-btn" onclick="window.location.href='stock.php'">Stock Dashboard</button>
      <button class="tab-btn active">Stock Analysis</button>
      <button class="tab-btn" onclick="window.location.href='stock_export.php'">Export Report</button>
    </div>
  </nav>
</aside>

<div class="free-area">
  <h1>📊 Stock Analysis</h1>

  <?php
  // Fetch stock data
  $categoryData = [];
  $catQuery = $conn->query("SELECT category, SUM(quantity) as total_qty FROM stock GROUP BY category");
  while ($row = $catQuery->fetch_assoc()) $categoryData[] = $row;

  $statusData = [];
  $statQuery = $conn->query("SELECT status, COUNT(*) as count FROM stock GROUP BY status");
  while ($row = $statQuery->fetch_assoc()) $statusData[] = $row;

  // Mini summary cards
  $totalStock = $conn->query("SELECT SUM(quantity) as total FROM stock")->fetch_assoc()['total'];
  $lowStock = $conn->query("SELECT COUNT(*) as total FROM stock WHERE status='Low'")->fetch_assoc()['total'];
  $outStock = $conn->query("SELECT COUNT(*) as total FROM stock WHERE status='Out of Stock'")->fetch_assoc()['total'];
  ?>

  <div class="mini-cards">
    <div class="mini-card">
      <h4>Total Stock</h4>
      <p><?= $totalStock ?></p>
    </div>
    <div class="mini-card">
      <h4>Low Stock</h4>
      <p><?= $lowStock ?></p>
    </div>
    <div class="mini-card">
      <h4>Out of Stock</h4>
      <p><?= $outStock ?></p>
    </div>
  </div>

  <div class="charts-grid">
    <div class="chart-card">
      <h3>Stock Quantity by Category</h3>
      <canvas id="barChart"></canvas>
    </div>
    <div class="chart-card">
      <h3>Stock Distribution by Status</h3>
      <canvas id="pieChart"></canvas>
    </div>
  </div>
</div>
</div>

<script>
const categoryLabels = <?php echo json_encode(array_column($categoryData, 'category')); ?>;
const categoryValues = <?php echo json_encode(array_column($categoryData, 'total_qty')); ?>;

const statusLabels = <?php echo json_encode(array_column($statusData, 'status')); ?>;
const statusValues = <?php echo json_encode(array_column($statusData, 'count')); ?>;

new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: categoryLabels,
        datasets: [{
            label: 'Total Quantity',
            data: categoryValues,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
        labels: statusLabels,
        datasets: [{
            label: 'Stock Status',
            data: statusValues,
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(255, 99, 132, 0.7)'
            ],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>
