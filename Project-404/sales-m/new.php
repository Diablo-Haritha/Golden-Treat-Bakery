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
$params = [];
$types = "";
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $where .= " AND date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}
if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $where .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}
if (!empty($_GET['customer'])) {
    $customer = $_GET['customer'];
    $where .= " AND customer LIKE ?";
    $params[] = "%$customer%";
    $types .= "s";
}

$sql = "SELECT id, date, customer, total, status FROM sales WHERE $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$sales = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}
$stmt->close();

// ---------- EDIT SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sale'])) {
    $id     = $_POST['id'];
    $date   = $_POST['date'];
    $customer = $_POST['customer'];
    $total = $_POST['total'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE sales SET date=?, customer=?, total=?, status=? WHERE id=?");
    $stmt->bind_param("ssdsi", $date, $customer, $total, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF'] . (isset($_GET['from']) ? '?from=' . $_GET['from'] . '&to=' . $_GET['to'] : '') . (isset($_GET['status']) ? (isset($_GET['from']) ? '&' : '?') . 'status=' . $_GET['status'] : '') . (isset($_GET['customer']) ? (isset($_GET['status']) || isset($_GET['from']) ? '&' : '?') . 'customer=' . $_GET['customer'] : ''));
    exit;
}

// ---------- DELETE SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF'] . (isset($_GET['from']) ? '?from=' . $_GET['from'] . '&to=' . $_GET['to'] : '') . (isset($_GET['status']) ? (isset($_GET['from']) ? '&' : '?') . 'status=' . $_GET['status'] : '') . (isset($_GET['customer']) ? (isset($_GET['status']) || isset($_GET['from']) ? '&' : '?') . 'customer=' . $_GET['customer'] : ''));
    exit;
}

// ---------- INSERT FORM DATA ----------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['date'], $_POST['customer'], $_POST['total'], $_POST['status'])) {
    $date     = $_POST['date'];
    $customer = $_POST['customer'];
    $total    = $_POST['total'];
    $status   = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO sales (date, customer, total, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $date, $customer, $total, $status);
    if ($stmt->execute()) {
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
    $stmt->close();
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
$sql = "SELECT IFNULL(SUM(total),0) AS today_revenue FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todayRevenue = $row['today_revenue'];

// Today's sales (count of orders today)
$sql = "SELECT COUNT(*) AS today_sales FROM sales WHERE date = CURDATE()";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$todaySales = $row['today_sales'];

// ---------- ANALYSIS DATA ----------
$year = isset($_GET['an_year']) ? $_GET['an_year'] : date('Y');
$month = isset($_GET['an_month']) ? $_GET['an_month'] : 'all';
$status = isset($_GET['an_status']) ? $_GET['an_status'] : '';

$analysis_where = "1=1";
$params = [];
$types = "";
if ($year) {
    $analysis_where .= " AND YEAR(date) = ?";
    $params[] = $year;
    $types .= "s";
}
if ($month !== 'all') {
    $analysis_where .= " AND MONTH(date) = ?";
    $params[] = $month;
    $types .= "s";
}
if ($status) {
    $analysis_where .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql = "SELECT date, customer, total, status FROM sales WHERE $analysis_where";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$analysis_data = [
    'revenue' => 0,
    'orders' => 0,
    'aov' => 0,
    'topCustomer' => '—',
    'dailyRevenue' => [],
    'topCustomers' => []
];

$daily_revenue = [];
$customer_revenue = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $date_key = $month === 'all' ? substr($row['date'], 0, 7) : $row['date'];
        $total = floatval($row['total']);
        $customer = $row['customer'];

        $analysis_data['revenue'] += $total;
        $analysis_data['orders'] += 1;

        $daily_revenue[$date_key] = isset($daily_revenue[$date_key]) ? $daily_revenue[$date_key] + $total : $total;
        $customer_revenue[$customer] = isset($customer_revenue[$customer]) ? $customer_revenue[$customer] + $total : $total;
    }
}

$analysis_data['aov'] = $analysis_data['orders'] > 0 ? $analysis_data['revenue'] / $analysis_data['orders'] : 0;

if (!empty($customer_revenue)) {
    arsort($customer_revenue);
    $analysis_data['topCustomer'] = key($customer_revenue);
}

ksort($daily_revenue);
$analysis_data['dailyRevenue'] = $daily_revenue;

arsort($customer_revenue);
$analysis_data['topCustomers'] = array_slice($customer_revenue, 0, 5, true);

$analysis_json = json_encode([
    'revenue' => round($analysis_data['revenue'], 2),
    'orders' => $analysis_data['orders'],
    'aov' => round($analysis_data['aov'], 2),
    'topCustomer' => $analysis_data['topCustomer'],
    'dailyRevenue' => $analysis_data['dailyRevenue'],
    'topCustomers' => $analysis_data['topCustomers']
], JSON_NUMERIC_CHECK);

$sales_json = json_encode($sales, JSON_NUMERIC_CHECK);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sales Management Admin UI</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style1.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
        <div class="header-middle">
            <div class="header-middle-title">Sales Management</div>
            <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." /></div>
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
                <button class="salesbtn" onclick="window.location.href='salesdash.php'">Sales</button>
                <div class="otherbtn">
                    <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
                    <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
                    <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>
                </div>
                <hr />
                <p>Sales Management</p>
                <div class="salebtn">
                    <button class="tab-btn active" data-tab="sales-dashboard">Sales Dashboard</button>
                    <button class="tab-btn" onclick="window.location.href='salesorg.php'">Sales SUM</button>
                    <button class="tab-btn" data-tab="sales-analysis">Sales Analysis</button>
                </div>
                <div style="margin-top: 20px;">
                    <a href="?magic=1" class="btn" style="display: block; width: 100%;">🪄 Generate Sample Data</a>
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
                    <p><?php echo $todaySales; ?></p>
                </div>
                <div class="card">
                    <h3>Total Orders</h3>
                    <p id="cardOrders"><?= $totalOrders ?></p>
                </div>
                <div class="card">
                    <h3>Total Customers</h3>
                    <p id="cardCustomers"><?= $totalCus ?></p>
                </div>
            </div>
            <div class="toolbar">
                <div class="filter-bar">
                    <form method="GET">
                        From: <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
                        To: <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
                        Status:
                        <select name="status">
                            <option value="">All</option>
                            <option <?= (($_GET['status'] ?? '') == "Paid" ? "selected" : "") ?>>Paid</option>
                            <option <?= (($_GET['status'] ?? '') == "Pending" ? "selected" : "") ?>>Pending</option>
                            <option <?= (($_GET['status'] ?? '') == "Cancelled" ? "selected" : "") ?>>Cancelled</option>
                        </select>
                        Customer: <input type="text" name="customer" value="<?= $_GET['customer'] ?? '' ?>">
                        <button type="submit">Filter</button>
                    </form>
                </div>
                <div style="flex:1"></div>
                <button class="btn secondary" onclick="openForm()">➕ Add Sale</button>
                <a class="btn" href="?<?= http_build_query(array_merge($_GET, ["export" => "csv"])) ?>">⬇ Export CSV</a>
                <button class="btn" onclick="exportSalesPDF()">📄 Export PDF Report</button>
            </div>
            <div class="table-wrap">
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th data-sort="id">ID ▲▼</th>
                            <th data-sort="date">Date ▲▼</th>
                            <th data-sort="customer">Customer ▲▼</th>
                            <th data-sort="total">Total ▲▼</th>
                            <th data-sort="status">Status ▲▼</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td><?= $s['id'] ?></td>
                                    <td><?= $s['date'] ?></td>
                                    <td><?= $s['customer'] ?></td>
                                    <td>$<?= number_format($s['total'], 2) ?></td>
                                    <td><?= $s['status'] ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <button class="edit" onclick="document.getElementById('edit_id').value='<?= $s['id'] ?>';
                                                document.getElementById('edit_date').value='<?= $s['date'] ?>';
                                                document.getElementById('edit_customer').value='<?= $s['customer'] ?>';
                                                document.getElementById('edit_total').value='<?= $s['total'] ?>';
                                                document.getElementById('edit_status').value='<?= $s['status'] ?>';
                                                openModal('editModal');">✏️</button>
                                            <button class="del" onclick="document.getElementById('delete_id').value='<?= $s['id'] ?>'; openModal('deleteModal');">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

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
                        <option value="Paid">Paid</option>
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
            <div style="margin-top: 20px;">
                <button class="btn" onclick="exportAnalysisPDF()">📄 Export Analysis Report PDF</button>
            </div>
        </div>
    </section>

    <!-- Add Form -->
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
            <h2>Edit Sale</h2>
            <form method="post">
                <input type="hidden" name="id" id="edit_id">
                <label>Date</label><input type="date" name="date" id="edit_date" required>
                <label>Customer</label><input type="text" name="customer" id="edit_customer" required>
                <label>Total</label><input type="number" step="0.01" name="total" id="edit_total" required>
                <label>Status</label>
                <select name="status" id="edit_status" required>
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <div class="row-actions">
                    <button type="submit" name="edit_sale" class="edit">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="addform" id="deleteModal">
        <div class="addform-content">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h2>Delete Sale</h2>
            <form method="post">
                <input type="hidden" name="id" id="delete_id">
                <p>Are you sure you want to delete this sale?</p>
                <div class="row-actions">
                    <button type="submit" name="delete_sale" class="delx">Delete</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    const analysisData = <?php echo $analysis_json; ?>;
    const salesData = <?php echo $sales_json; ?>;

    let chartRevenue, chartTopCust;

    // Populate year dropdown
    const yearSel = document.getElementById("anYear");
    const thisYear = new Date().getFullYear();
    for (let y = thisYear; y >= thisYear - 5; y--) {
        let opt = document.createElement("option");
        opt.value = y; opt.textContent = y;
        if (y == <?php echo json_encode($year); ?>) opt.selected = true;
        yearSel.appendChild(opt);
    }

    // Set month and status dropdowns
    document.getElementById("anMonth").value = <?php echo json_encode($month); ?>;
    document.getElementById("anStatus").value = <?php echo json_encode($status); ?>;

    function loadAnalysis() {
        if (!document.getElementById("sales-analysis").classList.contains("active")) return;

        const data = analysisData;

        // Mini-cards
        document.getElementById("anRevenue").textContent = "$" + data.revenue.toFixed(2);
        document.getElementById("anOrders").textContent = data.orders;
        document.getElementById("anAOV").textContent = "$" + data.aov.toFixed(2);
        document.getElementById("anTopCustomer").textContent = data.topCustomer;

        // Revenue chart
        const labels = Object.keys(data.dailyRevenue);
        const values = Object.values(data.dailyRevenue);

        if (chartRevenue) chartRevenue.destroy();
        const ctxRevenue = document.getElementById("anChartRevenue").getContext('2d');
        chartRevenue = new Chart(ctxRevenue, {
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
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Top customers chart
        if (chartTopCust) chartTopCust.destroy();
        const ctxTopCust = document.getElementById("anChartTopCust").getContext('2d');
        chartTopCust = new Chart(ctxTopCust, {
            type: "bar",
            data: {
                labels: Object.keys(data.topCustomers),
                datasets: [{
                    label: "Revenue",
                    data: Object.values(data.topCustomers),
                    backgroundColor: "#16a34a"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Range label
        const monthLabel = document.getElementById("anMonth").options[document.getElementById("anMonth").selectedIndex].text;
        const yearLabel = document.getElementById("anYear").value;
        const statusLabel = document.getElementById("anStatus").value || 'All';
        document.getElementById("anRangeLabel").textContent = `Analysis for ${yearLabel}, ${monthLabel === 'All' ? 'All Months' : monthLabel}, Status: ${statusLabel}`;
    }

    // Tab switching
    document.querySelectorAll(".tab-btn[data-tab]").forEach(btn => {
        btn.addEventListener("click", () => {
            // Remove active class from all tabs and panels
            document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
            document.querySelectorAll(".panel").forEach(p => p.classList.remove("active"));

            // Add active class to clicked tab and corresponding panel
            btn.classList.add("active");
            const tabId = btn.getAttribute("data-tab");
            document.getElementById(tabId).classList.add("active");

            // Load analysis data if Sales Analysis tab is active
            if (tabId === "sales-analysis") {
                loadAnalysis();
                history.replaceState(null, null, "#sales-analysis");
            } else {
                history.replaceState(null, null, "");
            }
        });
    });

    // Event listeners for filter changes
    function updateFilters() {
        const year = document.getElementById("anYear").value;
        const month = document.getElementById("anMonth").value;
        const status = document.getElementById("anStatus").value;
        const params = new URLSearchParams();
        if (year) params.append("an_year", year);
        if (month !== "all") params.append("an_month", month);
        if (status) params.append("an_status", status);
        const url = window.location.pathname + "?" + params.toString() + "#sales-analysis";
        window.location.href = url;
    }

    document.getElementById("anYear").addEventListener("change", updateFilters);
    document.getElementById("anMonth").addEventListener("change", updateFilters);
    document.getElementById("anStatus").addEventListener("change", updateFilters);
    document.getElementById("anReset").addEventListener("click", () => {
        window.location = window.location.pathname + "#sales-analysis";
    });

    // Handle initial hash
    if (location.hash === "#sales-analysis") {
        document.querySelector('[data-tab="sales-analysis"]').click();
    }

    // Initial load
    loadAnalysis(); // Call once on load for analysis if active, but since dashboard is default, it will be called on tab switch

    // Export Sales PDF Report
    function exportSalesPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(16);
        doc.text('Sales Report', 14, 20);

        // Add filters info
        let yPos = 30;
        <?php if (!empty($_GET['from']) && !empty($_GET['to'])): ?>
        doc.text(`Date Range: <?php echo $_GET['from']; ?> to <?php echo $_GET['to']; ?>`, 14, yPos);
        yPos += 10;
        <?php endif; ?>
        <?php if (!empty($_GET['status'])): ?>
        doc.text(`Status: <?php echo $_GET['status']; ?>`, 14, yPos);
        yPos += 10;
        <?php endif; ?>
        <?php if (!empty($_GET['customer'])): ?>
        doc.text(`Customer: <?php echo $_GET['customer']; ?>`, 14, yPos);
        yPos += 10;
        <?php endif; ?>

        if (salesData.length === 0) {
            doc.text('No sales data found.', 14, yPos);
        } else {
            const tableColumn = ['ID', 'Date', 'Customer', 'Total', 'Status'];
            const tableRows = salesData.map(row => [row.id, row.date, row.customer, `$${parseFloat(row.total).toFixed(2)}`, row.status]);
            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: yPos,
                theme: 'striped'
            });
        }

        doc.save(`sales_report_${new Date().toISOString().split('T')[0]}.pdf`);
    }

    // Export Analysis PDF Report
    function exportAnalysisPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(16);
        doc.text('Sales Analysis Report', 14, 20);

        let yPos = 30;
        const year = document.getElementById("anYear").value;
        const month = document.getElementById("anMonth").options[document.getElementById("anMonth").selectedIndex].text;
        const status = document.getElementById("anStatus").value || 'All';

        doc.text(`Year: ${year}`, 14, yPos); yPos += 7;
        doc.text(`Month: ${month === 'All' ? 'All Months' : month}`, 14, yPos); yPos += 7;
        doc.text(`Status Filter: ${status}`, 14, yPos); yPos += 10;

        doc.text(`Total Revenue: $${analysisData.revenue.toFixed(2)}`, 14, yPos); yPos += 7;
        doc.text(`Total Orders: ${analysisData.orders}`, 14, yPos); yPos += 7;
        doc.text(`Average Order Value: $${analysisData.aov.toFixed(2)}`, 14, yPos); yPos += 7;
        doc.text(`Top Customer: ${analysisData.topCustomer}`, 14, yPos); yPos += 10;

        // Daily Revenue Table
        if (Object.keys(analysisData.dailyRevenue).length > 0) {
            const dailyColumn = ['Period', 'Revenue'];
            const dailyRows = Object.entries(analysisData.dailyRevenue).map(([period, rev]) => [period, `$${parseFloat(rev).toFixed(2)}`]);
            doc.autoTable({
                head: [dailyColumn],
                body: dailyRows,
                startY: yPos,
                theme: 'grid'
            });
            yPos = doc.lastAutoTable.finalY + 10;
        } else {
            doc.text('No daily data available.', 14, yPos);
            yPos += 10;
        }

        // Top Customers Table
        if (Object.keys(analysisData.topCustomers).length > 0) {
            const topColumn = ['Customer', 'Revenue'];
            const topRows = Object.entries(analysisData.topCustomers).map(([cust, rev]) => [cust, `$${parseFloat(rev).toFixed(2)}`]);
            doc.autoTable({
                head: [topColumn],
                body: topRows.slice(0, 5), // Top 5
                startY: yPos,
                theme: 'grid'
            });
        }

        doc.save(`analysis_report_${year}_${month.toLowerCase().replace(' ', '_')}.pdf`);
    }

    // Popup Add
    function openForm() {
        document.getElementById("AddForm").style.display = "flex";
    }

    function closeForm() {
        document.getElementById("AddForm").style.display = "none";
    }

    // Edit and delete modals
    function openModal(id) {
        document.getElementById(id).style.display = "flex";
    }

    function closeModal(id) {
        document.getElementById(id).style.display = "none";
    }

    // Table sorting
    document.querySelectorAll("#salesTable th[data-sort]").forEach((th, idx) => {
        th.addEventListener("click", () => {
            const table = th.closest("table");
            const tbody = table.querySelector("tbody");
            const rows = Array.from(tbody.querySelectorAll("tr:not(.text-center)"));
            let asc = !th.classList.contains("asc");
            document.querySelectorAll("#salesTable th").forEach(t => t.classList.remove("asc"));
            if (asc) th.classList.add("asc");

            const sortIdx = idx < 4 ? idx : 3; // For action column, skip
            rows.sort((a, b) => {
                let valA = a.cells[sortIdx].innerText.trim().replace('$', '').replace(',', '');
                let valB = b.cells[sortIdx].innerText.trim().replace('$', '').replace(',', '');

                if (!isNaN(valA) && !isNaN(valB)) {
                    valA = parseFloat(valA);
                    valB = parseFloat(valB);
                    return asc ? (valA > valB ? 1 : valA < valB ? -1 : 0) : (valA < valB ? 1 : valA > valB ? -1 : 0);
                } else {
                    return asc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Global search
    document.getElementById('globalSearch').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#salesTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });
</script>
</body>
</html>