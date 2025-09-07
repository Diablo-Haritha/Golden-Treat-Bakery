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

// ---------- FETCH SALES DATA ----------
$where = "1=1";
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $where .= " AND date BETWEEN '$from' AND '$to'";
}
if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $where .= " AND status = '$status'";
}
if (!empty($_GET['customer'])) {
    $customer = $_GET['customer'];
    $where .= " AND customer LIKE '%$customer%'";
}

$sql = "SELECT id, date, customer, total, status FROM sales WHERE $where ORDER BY id DESC";
$result = $conn->query($sql);
$sales = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }}

// ---------- EDIT SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sale'])) {
    $id     = $_POST['id'];
    $date   = $_POST['date'];
    $customer = $_POST['customer'];
    $total = $_POST['total'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE sales SET date=?, customer=?, total=?, status=? WHERE id=?");
    $stmt->bind_param("ssisi", $date, $customer, $total, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ---------- DELETE SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}


    // ---------- INSERT FORM DATA ----------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date     = $_POST['date'];
    $customer = $_POST['customer'];
    $total    = $_POST['total'];
    $status   = $_POST['status'];

    $sql = "INSERT INTO sales (date, customer, total, status) 
            VALUES ('$date', '$customer', '$total', '$status')";

    if ($conn->query($sql) === TRUE) {
       echo "<script>
        alert('✅ Sale record added successfully!')
        window.location.href = window.location.href.split('?')[0]; // Remove query parameters
      </script>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
}

// Count total orders
$sql = "SELECT COUNT(*) AS total_orders FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalOrders = $row['total_orders'];

// Count unique customers
$sql = "SELECT COUNT(DISTINCT customer) AS total_cus FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalCus = $row['total_cus'];


// Sum of today's sales total
$sql = "SELECT IFNULL(SUM(total),0) AS today_revenue 
        FROM sales 
        WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todayRevenue = $row['today_revenue'];


// ---------- TODAY'S SALES (count of orders today) ----------
$sql = "SELECT COUNT(*) AS today_sales FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todaySales = $row['today_sales'];













// ---------- BUILD FILTER ----------
$where = "1=1";
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $where .= " AND date BETWEEN '$from' AND '$to'";
}
if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $where .= " AND status = '$status'";
}
if (!empty($_GET['customer'])) {
    $customer = $_GET['customer'];
    $where .= " AND customer LIKE '%$customer%'";
}

// ---------- FETCH DATA ----------
$sql = "SELECT id, date, customer, total, status FROM sales WHERE $where ORDER BY id DESC";
$result = $conn->query($sql);
$sales = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}

// ---------- EXPORTS ----------
if (isset($_GET['export'])) {
    $type = $_GET['export'];

    // --- CSV ---
    if ($type == "csv") {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=sales_report.csv");
        $out = fopen("php://output", "w");
        fputcsv($out, ["ID","Date","Customer","Total","Status"]);
        foreach ($sales as $s) {
            fputcsv($out, $s);
        }
        fclose($out);
        exit;
    }

    // --- Excel ---
    if ($type == "excel") {
        require "vendor/autoload.php";
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(["ID","Date","Customer","Total","Status"], NULL, "A1");
        $sheet->fromArray($sales, NULL, "A2");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=sales_report.xlsx");
        $writer->save("php://output");
        exit;
    }


  }



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
        <button class="salesbtn" onclick="window.location.href='index.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
          <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn active" onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='index2.php'">Sales SUM</button>
         <button class="tab-btn " onclick="window.location.href='index3.php'">Sales Analysis</button>
          
        </div>
      </nav>
    </aside>

    <!-- Main -->

    <main class="free-area">
      <!-- Dashboard -->
      <section id="sales-dashboard" class="panel active">
        <div class="content">
          <h1>Sales Management</h1>
          <div class="cards">

            <div class="card">
              <h3>Total Today Sales</h3>
              <p>
                <?php echo $todaySales; ?>
              </p>
            </div>
            <div class="card">
              <h3>Total Orders</h3>
              <p id="cardOrders">
                <?= $totalOrders ?>
              </p>
            </div>
            <div class="card">
              <h3>Total Customers</h3>
              <p id="cardCustomers">
                <?= $totalCus ?>
              </p>
            </div>
          </div>
          <div class="toolbar">
            <div class="filter-bar">




              <!-- FILTER FORM -->
              <form method="GET">
                From: <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
                To: <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
                Status:
                <select name="status">
                  <option value="">All</option>
                  <option <?=(($_GET['status']??'')=="Completed" ?"selected":"") ?>>Completed</option>
                  <option <?=(($_GET['status']??'')=="Pending" ?"selected":"") ?>>Pending</option>
                  <option <?=(($_GET['status']??'')=="Cancelled" ?"selected":"") ?>>Cancelled</option>
                </select>
                Customer: <input type="text" name="customer" value="<?= $_GET['customer'] ?? '' ?>">
                <button type="submit">Filter</button>
              </form>
            </div>
            <div style="flex:1"></div>

            <button class="btn secondary" onclick="openForm()">➕ Add Sale</button>
            <a class="btn" href="?<?= http_build_query(array_merge($_GET,[" export"=>"csv"])) ?>">⬇ Export CSV</a>
            <button class="btn" id="btnExportCsv">⬇ CSV</button>
          </div>
          <div class="table-wrap">
            <table id="salesTable">
              <tr>
                <th data-sort="id">ID ▲▼</th>
                <th data-sort="date">Date ▲▼</th>
                <th data-sort="customer">Customer ▲▼</th>
                <th data-sort="total">Total ▲▼</th>
                <th data-sort="status">Status ▲▼</th>
                <th>Action </th>
              </tr>
              </thead>
              <tbody>
                <?php if(empty($sales)): ?>
                <tr>
                  <td colspan="5" class="text-center">No records found</td>
                </tr>
                <?php else: ?>
                <?php foreach($sales as $s): ?>
                <tr>
                  <td>
                    <?= $s['id'] ?>
                  </td>
                  <td>
                    <?= $s['date'] ?>
                  </td>
                  <td>
                    <?= $s['customer'] ?>
                  </td>
                  <td>$
                    <?= number_format($s['total'],2) ?>
                  </td>
                  <td>
                    <?= $s['status'] ?>
                  </td>
                  <td>
                    <div class="row-actions">
                      <button class="edit" onclick="document.getElementById('edit_id').value='<?= $s['id'] ?>';
                                          document.getElementById('edit_date').value='<?= $s['date'] ?>';
                                          document.getElementById('edit_customer').value='<?= $s['customer'] ?>';
                                          document.getElementById('edit_total').value='<?= $s['total'] ?>';
                                          document.getElementById('edit_status').value='<?= $s['status'] ?>';
                                          openModal('editModal');">✏️</button>

                      <button class="del"
                        onclick="document.getElementById('delete_id').value='<?= $s['id'] ?>'; openModal('deleteModal');">🗑️</button>
                  </td>
          </div>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
          </table>
        </div>
  </div>
  </section>



  <!-- Export -->
  <section>
    <!-- Popup Form -->
    <div id="AddForm" class="addform">
      <div class="addform-content">
        <span class="close" onclick="closeForm()">&times;</span>
        <h2>Add New Sale</h2>
        <form method="POST" action="">
          <label for="date">Date</label>
          <input type="date" name="date" required>

          <label for="customer">Customer</label>
          <input type="text" name="customer" placeholder="Enter customer name" required>

          <label for="total">Total</label>
          <input type="number" step="0.01" name="total" placeholder="Enter total" required>

          <label for="status">Status</label>
          <select name="status" required>
            <option value="Pending">Pending</option>
            <option value="Paid">Paid</option>
            <option value="Cancelled">Cancelled</option>
          </select>

          <button type="submit">Save</button>
        </form>
      </div>
    </div>
    <!-- Edit Modal -->
    <div class="addform" id="editModal">
      <div class="addform-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>

        <h2>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Edit Sale</h2>
        <br><br>

        <form method="post">
          <input type="hidden" name="id" id="edit_id">
          <input type="date" name="date" id="edit_date" required>
          <input type="text" name="customer" id="edit_customer" required>
          <input type="number" name="total" id="edit_total" required>
          <select name="status" id="edit_status" required>
            <option value="Pending">Pending</option>
            <option value="Paid">Paid</option>
          </select>
          <div class="row-actions">
            <br>
            <hr>
            <br>
            <button type="submit" name="edit_sale" class="edit">Update</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Modal -->
    <div class="addform" id="deleteModal">
      <div class="addform-content">
        <span class="close" onclick="closeModal('deleteModal')">&times;</span>

        <h2>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Delete Sale</h2>
        <br>
        <form method="post">
          <input type="hidden" name="id" id="delete_id">
          <p>Are you sure you want to delete this sale?</p>
          <div class="row-actions">
            <br><br>
            <hr>
            <br><br>
            <button type="submit" name="delete_sale" class="delx">Delete</button>
          </div>
        </form>
      </div>
    </div>



    <!-- Analysis -->
    <section id="sales-analysis" class="panel">
      <div class="content">
        <h2>Sales Analysis</h2>
        <div class="analysis-controls">
          <div class="filter-row">
            <label>Year</label>
            <select id="anYear"></select>
            <label>Month</label>
            <select id="anMonth">
              <option value="all">All</option>
              <option value="01">Jan</option>
              <option value="02">Feb</option>
              <option value="03">Mar</option>
              <option value="04">Apr</option>
              <option value="05">May</option>
              <option value="06">Jun</option>
              <option value="07">Jul</option>
              <option value="08">Aug</option>
              <option value="09">Sep</option>
              <option value="10">Oct</option>
              <option value="11">Nov</option>
              <option value="12">Dec</option>
            </select>
            <label>Status</label>
            <select id="anStatus">
              <option value="">All</option>
              <option value="Completed">Completed</option>
              <option value="Pending">Pending</option>
              <option value="Cancelled">Cancelled</option>
            </select>
            <button class="btn light" id="anReset">Reset</button>
          </div>
        </div>

        <div class="mini-cards">
          <div class="mini-card">
            <h4>Revenue</h4>
            <p id="anRevenue">$0</p>
          </div>
          <div class="mini-card">
            <h4>Orders</h4>
            <p id="anOrders">0</p>
          </div>
          <div class="mini-card">
            <h4>Avg Order</h4>
            <p id="anAOV">$0</p>
          </div>
          <div class="mini-card">
            <h4>Top Customer</h4>
            <p id="anTopCustomer">—</p>
          </div>
        </div>

        <div class="charts-grid">
          <div class="chart-card">
            <h3>Revenue Over Time</h3>
            <p class="muted" id="anRangeLabel"></p>
            <div class="chart-wrap"><canvas id="anChartRevenue"></canvas></div>
          </div>
          <div class="chart-card">
            <h3>Top Customers (Revenue)</h3>
            <p class="muted">Top 5 for the selected period.</p>
            <div class="chart-wrap"><canvas id="anChartTopCust"></canvas></div>
          </div>
        </div>
      </div>
    </section>


    <!-- Export 
      <section id="sales-export" class="panel">
        <div class="content">
          <h2>Export Reports</h2>
          <div class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" />
              <input type="date" id="expTo" />
              <select id="expStatus">
                <option value="">All Status</option>
                <option>Completed</option>
                <option>Pending</option>
                <option>Cancelled</option>
              </select>
              <input type="text" id="expCustomer" placeholder="Customer" />
            </div>
            <div class="row">
              <button class="btn" id="expCsv">Export CSV</button>
              <button class="btn secondary" id="expXlsx">Export Excel</button>
              <button class="btn warn" id="expPdf">Export PDF</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </div>
        </div>
      </section>-->

    <!-- Analysis -->
    <section id="sales-analysis" class="panel">
      <div class="content">
        <h2>Sales Analysis</h2>
        <div class="analysis-controls">
          <div class="filter-row">
            <label>Year</label>
            <select id="anYear"></select>
            <label>Month</label>
            <select id="anMonth">
              <option value="all">All</option>
              <option value="01">Jan</option>
              <option value="02">Feb</option>
              <option value="03">Mar</option>
              <option value="04">Apr</option>
              <option value="05">May</option>
              <option value="06">Jun</option>
              <option value="07">Jul</option>
              <option value="08">Aug</option>
              <option value="09">Sep</option>
              <option value="10">Oct</option>
              <option value="11">Nov</option>
              <option value="12">Dec</option>
            </select>
            <label>Status</label>
            <select id="anStatus">
              <option value="">All</option>
              <option value="Completed">Completed</option>
              <option value="Pending">Pending</option>
              <option value="Cancelled">Cancelled</option>
            </select>
            <button class="btn light" id="anReset">Reset</button>
          </div>
        </div>

        <div class="mini-cards">
          <div class="mini-card">
            <h4>Revenue</h4>
            <p id="anRevenue">$0</p>
          </div>
          <div class="mini-card">
            <h4>Orders</h4>
            <p id="anOrders">0</p>
          </div>
          <div class="mini-card">
            <h4>Avg Order</h4>
            <p id="anAOV">$0</p>
          </div>
          <div class="mini-card">
            <h4>Top Customer</h4>
            <p id="anTopCustomer">—</p>
          </div>
        </div>

        <div class="charts-grid">
          <div class="chart-card">
            <h3>Revenue Over Time</h3>
            <p class="muted" id="anRangeLabel"></p>
            <div class="chart-wrap"><canvas id="anChartRevenue"></canvas></div>
          </div>
          <div class="chart-card">
            <h3>Top Customers (Revenue)</h3>
            <p class="muted">Top 5 for the selected period.</p>
            <div class="chart-wrap"><canvas id="anChartTopCust"></canvas></div>
          </div>
        </div>
      </div>
    </section>





    </main>
    </div>

    <!-- Modal Add/Edit -->
    <div class="modal-backdrop" id="modalBackdrop">
      <div class="modal">
        <h2 id="modalTitle">Add Sale</h2>
        <div class="form-grid">
          <div><label>ID</label><input id="fId" type="number" placeholder="e.g. 1001"></div>
          <div><label>Date</label><input id="fDate" type="date"></div>
          <div class="full"><label>Customer</label><input id="fCustomer" type="text" placeholder="Customer name"></div>
          <div><label>Total</label><input id="fTotal" type="number" step="0.01" placeholder="Amount"></div>
          <div>
            <label>Status</label>
            <select id="fStatus">
              <option>Completed</option>
              <option>Pending</option>
              <option>Cancelled</option>
            </select>
          </div>
        </div>
        <div class="footer">
          <button class="btn light" id="btnCancel">Cancel</button>
          <button class="btn" id="btnSave">Save</button>
        </div>
      </div>
    </div>




    <script>
      let chartRevenue, chartTopCust;

      // Populate year dropdown
      const yearSel = document.getElementById("anYear");
      const thisYear = new Date().getFullYear();
      for (let y = thisYear; y >= thisYear - 5; y--) {
        let opt = document.createElement("option");
        opt.value = y; opt.textContent = y;
        if (y === thisYear) opt.selected = true;
        yearSel.appendChild(opt);
      }

      function loadAnalysis() {
        const year = document.getElementById("anYear").value;
        const month = document.getElementById("anMonth").value;
        const status = document.getElementById("anStatus").value;

        fetch(`sales_analysis_api.php?year=${year}&month=${month}&status=${status}`)
          .then(res => res.json())
          .then(data => {
            // Mini-cards
            document.getElementById("anRevenue").textContent = "$" + data.revenue.toFixed(2);
            document.getElementById("anOrders").textContent = data.orders;
            document.getElementById("anAOV").textContent = "$" + data.aov.toFixed(2);
            document.getElementById("anTopCustomer").textContent = data.topCustomer;

            // Revenue chart
            const labels = Object.keys(data.dailyRevenue);
            const values = Object.values(data.dailyRevenue);

            if (chartRevenue) chartRevenue.destroy();
            chartRevenue = new Chart(document.getElementById("anChartRevenue"), {
              type: "line",
              data: {
                labels: labels,
                datasets: [{
                  label: "Revenue",
                  data: values,
                  borderColor: "#2563eb",
                  backgroundColor: "rgba(37,99,235,0.2)",
                  tension: 0.3,
                  fill: true
                }]
              }
            });

            // Top customers chart
            if (chartTopCust) chartTopCust.destroy();
            chartTopCust = new Chart(document.getElementById("anChartTopCust"), {
              type: "bar",
              data: {
                labels: Object.keys(data.topCustomers),
                datasets: [{
                  label: "Revenue",
                  data: Object.values(data.topCustomers),
                  backgroundColor: "#16a34a"
                }]
              }
            });

            // Range label
            document.getElementById("anRangeLabel").textContent =
              (month === "all" ? "Monthly revenue in " + year : "Daily revenue in " + year + "-" + month);
          });
      }

      // Event listeners
      document.getElementById("anYear").addEventListener("change", loadAnalysis);
      document.getElementById("anMonth").addEventListener("change", loadAnalysis);
      document.getElementById("anStatus").addEventListener("change", loadAnalysis);
      document.getElementById("anReset").addEventListener("click", () => {
        document.getElementById("anYear").value = thisYear;
        document.getElementById("anMonth").value = "all";
        document.getElementById("anStatus").value = "";
        loadAnalysis();
      });

      // Initial load
      loadAnalysis();





      document.getElementById("btnExportCsv").addEventListener("click", () => {
        window.location.href = "?<?= http_build_query(array_merge($_GET,["export "=>"csv"])) ?>";
      });





      const sales = <?= json_encode($sales) ?>;

      // --- Prepare Data by Date ---
      const daily = {};
      sales.forEach(s => {
        daily[s.date] = (daily[s.date] || 0) + Number(s.total);
      });
      const labels = Object.keys(daily).sort();
      const values = labels.map(d => daily[d]);

      new Chart(document.getElementById("chartRevenue"), {
        type: "line",
        data: {
          labels,
          datasets: [{
            label: "Revenue",
            data: values,
            borderColor: "blue",
            fill: false
          }]
        }
      });

      //popup Add 

      function openForm() {
        document.getElementById("AddForm").style.display = "flex"; // show
      }

      function closeForm() {
        document.getElementById("AddForm").style.display = "none"; // hide
      }

      //edit and delete
      function openModal(id) { document.getElementById(id).style.display = "flex"; }
      function closeModal(id) { document.getElementById(id).style.display = "none"; }


      //sort js

      document.querySelectorAll("#salesTable th").forEach((th, idx) => {
        th.addEventListener("click", () => {
          const table = th.closest("table");
          const tbody = table.querySelector("tbody");
          const rows = Array.from(tbody.querySelectorAll("tr"));
          const asc = th.classList.toggle("asc"); // toggle ascending/descending

          rows.sort((a, b) => {
            let valA = a.cells[idx].innerText.trim();
            let valB = b.cells[idx].innerText.trim();

            // If numeric, compare as numbers
            if (!isNaN(valA) && !isNaN(valB)) {
              valA = Number(valA);
              valB = Number(valB);
            }

            return asc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
          });

          rows.forEach(row => tbody.appendChild(row));
        });
      });








    </script>
</body>

</html>