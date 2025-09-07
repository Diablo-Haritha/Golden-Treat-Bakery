<?php
// ---------- DATABASE CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}






// ---------- TOTAL ORDERS ----------
$sql = "SELECT COUNT(*) AS total_orders FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalOrders = $row['total_orders'];

// ---------- UNIQUE CUSTOMERS ----------
$sql = "SELECT COUNT(DISTINCT customer) AS total_cus FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalCus = $row['total_cus'];

// ---------- TODAY'S SALES ----------
$sql = "SELECT COUNT(*) AS today_sales FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todaySales = $row['today_sales'];

// ---------- TODAY'S REVENUE ----------
$sql = "SELECT IFNULL(SUM(total),0) AS today_revenue FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todayRevenue = $row['today_revenue'];

// ---------- TOTAL REVENUE ----------
$sql = "SELECT IFNULL(SUM(total),0) AS total_revenue FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalRevenue = $row['total_revenue'];

// ---------- CANCELLED ORDERS ----------
$sql = "SELECT COUNT(*) AS cancelled_orders FROM sales WHERE status = 'Cancelled'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$cancelledOrders = $row['cancelled_orders'];

// ---------- COMPLETED ORDERS ----------
$sql = "SELECT COUNT(*) AS completed_orders FROM sales WHERE status = 'Completed'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$completedOrders = $row['completed_orders'];

// ---------- PENDING ORDERS ----------
$sql = "SELECT COUNT(*) AS pending_orders FROM sales WHERE status = 'Pending'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$pendingOrders = $row['pending_orders'];

// ---------- PAID ORDERS ----------
$sql = "SELECT COUNT(*) AS paid_orders FROM sales WHERE status = 'Paid'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$paidOrders = $row['paid_orders'];

// ---------- HIGHEST SALE ----------
$sql = "SELECT MAX(total) AS highest_sale FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$highestSale = $row['highest_sale'];











// ---------- TOTAL ORDERS ----------
$sql = "SELECT COUNT(*) AS total_orders FROM sales";
$result = $conn->query($sql);
$totalOrders = ($row = $result->fetch_assoc()) ? $row['total_orders'] : 0;

// ---------- UNIQUE CUSTOMERS ----------
$sql = "SELECT COUNT(DISTINCT customer) AS total_cus FROM sales";
$result = $conn->query($sql);
$totalCus = ($row = $result->fetch_assoc()) ? $row['total_cus'] : 0;

// ---------- TODAY'S SALES ----------
$sql = "SELECT COUNT(*) AS today_sales FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$todaySales = ($row = $result->fetch_assoc()) ? $row['today_sales'] : 0;

// ---------- TODAY'S REVENUE ----------
$sql = "SELECT IFNULL(SUM(total),0) AS today_revenue FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$todayRevenue = ($row = $result->fetch_assoc()) ? $row['today_revenue'] : 0;

// ---------- TOTAL REVENUE ----------
$sql = "SELECT IFNULL(SUM(total),0) AS total_revenue FROM sales";
$result = $conn->query($sql);
$totalRevenue = ($row = $result->fetch_assoc()) ? $row['total_revenue'] : 0;

// ---------- CANCELLED ORDERS ----------
$sql = "SELECT COUNT(*) AS cancelled_orders FROM sales WHERE status = 'Cancelled'";
$result = $conn->query($sql);
$cancelledOrders = ($row = $result->fetch_assoc()) ? $row['cancelled_orders'] : 0;

// ---------- COMPLETED ORDERS ----------
$sql = "SELECT COUNT(*) AS completed_orders FROM sales WHERE status = 'Completed'";
$result = $conn->query($sql);
$completedOrders = ($row = $result->fetch_assoc()) ? $row['completed_orders'] : 0;

// ---------- PENDING ORDERS ----------
$sql = "SELECT COUNT(*) AS pending_orders FROM sales WHERE status = 'Pending'";
$result = $conn->query($sql);
$pendingOrders = ($row = $result->fetch_assoc()) ? $row['pending_orders'] : 0;

// ---------- PAID ORDERS ----------
$sql = "SELECT COUNT(*) AS paid_orders FROM sales WHERE status = 'Paid'";
$result = $conn->query($sql);
$paidOrders = ($row = $result->fetch_assoc()) ? $row['paid_orders'] : 0;

// ---------- HIGHEST SALE ----------
$sql = "SELECT MAX(total) AS highest_sale FROM sales";
$result = $conn->query($sql);
$highestSale = ($row = $result->fetch_assoc()) ? $row['highest_sale'] : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales Management Admin UI</title>
  <!-- Charts & export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="style1.css">
  <style>
    .card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    }

    .card h3 {
      font-size: 1.2rem;
      margin-bottom: 10px;
      color: #ffdd57;
    }

    .card p {
      font-size: 1.5rem;
      font-weight: bold;
      margin: 10px 0;
    }

    .info-btn {
      margin-top: 10px;
      padding: 10px 15px;
      border: none;
      border-radius: 12px;
      background: #ffdd57;
      color: #333;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
    }

    .info-btn:hover {
      background: #ffd633;
      transform: scale(1.05);
    }

    /* POPUP STYLES */
    .popup {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(8px);
      align-items: center;
      justify-content: center;
    }

    .popup-content {
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(20px);
      padding: 30px;
      border-radius: 20px;
      width: 400px;
      max-width: 90%;
      color: #fff;
      text-align: left;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      animation: fadeIn 0.3s ease-in-out;
    }

    .popup-content h2 {
      margin-top: 0;
      font-size: 1.6rem;
      color: #ffdd57;
    }

    .popup-content ul {
      margin: 15px 0;
      padding-left: 20px;
    }

    .popup-content li {
      margin: 8px 0;
    }

    .close {
      float: right;
      font-size: 1.5rem;
      cursor: pointer;
      color: #fff;
    }

    @keyframes fadeIn {
      from {opacity: 0; transform: scale(0.9);}
      to {opacity: 1; transform: scale(1);}
    }
</style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Sales Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
      </div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn"onclick="window.location.href='index2.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
          <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn " onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn active" onclick="window.location.href='index2.php'">Sales SUM</button>
          <button class="tab-btn " onclick="window.location.href='index3.php'">Sales Analysis</button>
        </div>
      </nav>
    </aside>

    <!-- Main -->





  <main class="free-area">
    <section id="sales-dashboard" class="panel active">
      <div class="content">
        <h1>Sales Management</h1>
        <div class="cards">

          <div class="card">
            <h3>Total Today Sales</h3>
            <p><?= $todaySales ?></p>
            <button class="info-btn" data-popup="popup1">More Info</button>
          </div>

          <div class="card">
            <h3>Total Orders</h3>
            <p><?= $totalOrders ?></p>
            <button class="info-btn" data-popup="popup2">More Info</button>
          </div>

          <div class="card">
            <h3>Total Customers</h3>
            <p><?= $totalCus ?></p>
            <button class="info-btn" data-popup="popup3">More Info</button>
          </div>

          <div class="card">
            <h3>Today Revenue</h3>
            <p>Rs. <?= number_format($todayRevenue, 2) ?></p>
            <button class="info-btn" data-popup="popup4">More Info</button>
          </div>

          <div class="card">
            <h3>Total Revenue</h3>
            <p>Rs. <?= number_format($totalRevenue, 2) ?></p>
            <button class="info-btn" data-popup="popup5">More Info</button>
          </div>

          <div class="card">
            <h3>Cancelled Orders</h3>
            <p><?= $cancelledOrders ?></p>
            <button class="info-btn" data-popup="popup6">More Info</button>
          </div>

          <div class="card">
            <h3>Completed Orders</h3>
            <p><?= $completedOrders ?></p>
            <button class="info-btn" data-popup="popup7">More Info</button>
          </div>

          <div class="card">
            <h3>Pending Orders</h3>
            <p><?= $pendingOrders ?></p>
            <button class="info-btn" data-popup="popup8">More Info</button>
          </div>

          <div class="card">
            <h3>Paid Orders</h3>
            <p><?= $paidOrders ?></p>
            <button class="info-btn" data-popup="popup9">More Info</button>
          </div>

          <div class="card">
            <h3>Highest Sale</h3>
            <p>Rs. <?= number_format($highestSale, 2) ?></p>
            <button class="info-btn" data-popup="popup10">More Info</button>
          </div>

        </div>
      </div>
    </section>
  </main>

  <!-- POPUPS -->
 <!-- POPUPS -->
    <div id="popup1" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Total Today Sales</h2>
        <p>You made <strong><?= $todaySales ?></strong> orders today.</p>
        <ul>
          <li>Revenue Today: Rs. <?= number_format($todayRevenue, 2) ?></li>
          <li>Pending Orders: <?= $pendingOrders ?></li>
          <li>Completed Orders: <?= $completedOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup2" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Total Orders</h2>
        <p>You have <strong><?= $totalOrders ?></strong> total orders in the system.</p>
        <ul>
          <li>Completed: <?= $completedOrders ?></li>
          <li>Pending: <?= $pendingOrders ?></li>
          <li>Cancelled: <?= $cancelledOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup3" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Total Customers</h2>
        <p>You have <strong><?= $totalCus ?></strong> unique customers in the system.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Average Orders per Customer: <?= $totalCus > 0 ? number_format($totalOrders / $totalCus, 2) : 0 ?></li>
          <li>Total Revenue: Rs. <?= number_format($totalRevenue, 2) ?></li>
        </ul>
      </div>
    </div>

    <div id="popup4" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Today's Revenue</h2>
        <p>Today's total revenue is <strong>Rs. <?= number_format($todayRevenue, 2) ?></strong>.</p>
        <ul>
          <li>Today's Sales: <?= $todaySales ?></li>
          <li>Average Revenue per Sale: Rs. <?= $todaySales > 0 ? number_format($todayRevenue / $todaySales, 2) : 0 ?></li>
          <li>Total Revenue: Rs. <?= number_format($totalRevenue, 2) ?></li>
        </ul>
      </div>
    </div>

    <div id="popup5" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Total Revenue</h2>
        <p>Total revenue generated is <strong>Rs. <?= number_format($totalRevenue, 2) ?></strong>.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Average Revenue per Order: Rs. <?= $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : 0 ?></li>
          <li>Highest Sale: Rs. <?= number_format($highestSale, 2) ?></li>
        </ul>
      </div>
    </div>

    <div id="popup6" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Cancelled Orders</h2>
        <p>You have <strong><?= $cancelledOrders ?></strong> cancelled orders.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Percentage Cancelled: <?= $totalOrders > 0 ? number_format(($cancelledOrders / $totalOrders) * 100, 2) : 0 ?>%</li>
          <li>Completed Orders: <?= $completedOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup7" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Completed Orders</h2>
        <p>You have <strong><?= $completedOrders ?></strong> completed orders.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Percentage Completed: <?= $totalOrders > 0 ? number_format(($completedOrders / $totalOrders) * 100, 2) : 0 ?>%</li>
          <li>Pending Orders: <?= $pendingOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup8" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Pending Orders</h2>
        <p>You have <strong><?= $pendingOrders ?></strong> pending orders.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Percentage Pending: <?= $totalOrders > 0 ? number_format(($pendingOrders / $totalOrders) * 100, 2) : 0 ?>%</li>
          <li>Paid Orders: <?= $paidOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup9" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Paid Orders</h2>
        <p>You have <strong><?= $paidOrders ?></strong> paid orders.</p>
        <ul>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Percentage Paid: <?= $totalOrders > 0 ? number_format(($paidOrders / $totalOrders) * 100, 2) : 0 ?>%</li>
          <li>Completed Orders: <?= $completedOrders ?></li>
        </ul>
      </div>
    </div>

    <div id="popup10" class="popup">
      <div class="popup-content">
        <span class="close">&times;</span>
        <h2>Highest Sale</h2>
        <p>The highest sale recorded is <strong>Rs. <?= number_format($highestSale, 2) ?></strong>.</p>
        <ul>
          <li>Total Revenue: Rs. <?= number_format($totalRevenue, 2) ?></li>
          <li>Total Orders: <?= $totalOrders ?></li>
          <li>Average Revenue per Order: Rs. <?= $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : 0 ?></li>
        </ul>
      </div>
    </div>

  <!-- Repeat for popup3 ... popup10 with more detailed info -->

  <script>
    const buttons = document.querySelectorAll('.info-btn');
    const popups = document.querySelectorAll('.popup');
    const closes = document.querySelectorAll('.close');

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById(btn.dataset.popup).style.display = 'flex';
      });
    });

    closes.forEach(c => {
      c.addEventListener('click', () => {
        c.closest('.popup').style.display = 'none';
      });
    });

    window.addEventListener('click', (e) => {
      popups.forEach(popup => {
        if (e.target === popup) popup.style.display = 'none';
      });
    });
  </script>

</body>
</html>
