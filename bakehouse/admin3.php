<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins and managers
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$current_role = $current_user['role'];

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $user_id_input = filter_var($_POST['fUserId'], FILTER_SANITIZE_STRING);
    $name = filter_var($_POST['fName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['fEmail'], FILTER_SANITIZE_EMAIL);
    $role = filter_var($_POST['fRole'], FILTER_SANITIZE_STRING);
    $status = filter_var($_POST['fStatus'], FILTER_SANITIZE_STRING);
    $last_active = $_POST['fLastActive'];
    $mobile = filter_var($_POST['fMobile'], FILTER_SANITIZE_STRING);
    $address = filter_var($_POST['fAddress'], FILTER_SANITIZE_STRING);
    $district = filter_var($_POST['fDistrict'], FILTER_SANITIZE_STRING);
    $password = password_hash('default123', PASSWORD_DEFAULT); // Default password for new users

    try {
        $stmt = $pdo->prepare("INSERT INTO users (user_id, full_name, email, mobile, address, district, password, role, status, last_active, date_joined) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id_input, $name, $email, $mobile, $address, $district, $password, $role, $status, $last_active, date('Y-m-d')]);
        $success_message = "User added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding user: " . $e->getMessage();
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = $_POST['id'];
    $user_id_input = filter_var($_POST['fUserId'], FILTER_SANITIZE_STRING);
    $name = filter_var($_POST['fName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['fEmail'], FILTER_SANITIZE_EMAIL);
    $role = filter_var($_POST['fRole'], FILTER_SANITIZE_STRING);
    $status = filter_var($_POST['fStatus'], FILTER_SANITIZE_STRING);
    $last_active = $_POST['fLastActive'];
    $mobile = filter_var($_POST['fMobile'], FILTER_SANITIZE_STRING);
    $address = filter_var($_POST['fAddress'], FILTER_SANITIZE_STRING);
    $district = filter_var($_POST['fDistrict'], FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("UPDATE users SET user_id = ?, full_name = ?, email = ?, mobile = ?, address = ?, district = ?, role = ?, status = ?, last_active = ? WHERE id = ?");
        $stmt->execute([$user_id_input, $name, $email, $mobile, $address, $district, $role, $status, $last_active, $id]);
        $success_message = "User updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating user: " . $e->getMessage();
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $id = $_POST['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "User deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}

// Fetch all users for rendering
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch analysis data
$years = $pdo->query("SELECT DISTINCT YEAR(last_active) AS year FROM users ORDER BY year")->fetchAll(PDO::FETCH_COLUMN);
$total_users = count($users);
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();
$admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$top_role_query = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC LIMIT 1");
$top_role = $top_role_query->fetch(PDO::FETCH_ASSOC)['role'] ?? '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Management Admin UI</title>
  <!-- Charts & export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <style>
    :root {
      --brand: #00a619;
      --ink: #111827;
      --paper: #fff;
      --muted: #6b7280;
      --soft: #e5e7eb;
      --warn: #ffc107;
      --danger: #dc3545;
      --primary: #007bff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f4f6f9;
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Header */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      padding: 12px 16px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, .08);
    }

    .header-left img {
      width: 56px;
      height: auto;
      border-radius: 8px;
      display: block;
      box-shadow: 2px 2px 5px rgba(0, 0, 0, .15);
    }

    .header-middle {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      margin: 0 16px;
      max-width: 720px;
    }

    .header-middle-title {
      font-weight: 800;
      font-size: 26px;
      color: var(--brand);
      white-space: nowrap;
    }

    .search-bar {
      flex: 1;
      display: flex;
    }

    .search-bar input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .role-btn {
      background: #111827;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }

    .role-btn:hover {
      opacity: .9;
    }

    .user-icon {
      width: 28px;
      height: 28px;
      background: linear-gradient(135deg, #bbb, #888);
      border-radius: 50%;
    }

    /* Layout */
    .layout {
      flex: 1;
      display: flex;
      min-height: 0;
    }

    .sidebar {
      width: 260px;
      background: #fff;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-right: 1px solid #e5e7eb;
    }

    .sidebar h1 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 8px;
      color: #0f172a;
    }

    .sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    /* Sidebar groups */
    .salesbtn {
      background: var(--brand);
      border: none;
      border-radius: 10px;
      color: #fff;
      font-weight: 800;
      font-size: 22px;
      padding: 12px;
      text-align: center;
    }

    .otherbtn button {
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      gap: 10px;
    }

    .otherbtn2 button {
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      gap: 10px;
      margin: 20px;
    }

    .salebtn button {
      background: #00a619;
      border: none;
      text-align: left;
      padding: 10px;
      margin: 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      color: #fff;
    }

    .Sbtn {
      background: #30b6a2;
      margin-left: 10px;
    }

    .Ubtn {
      background: #9c0dc7;
    }

    .Bbtn {
      background: #017612;
    }

    .otherbtn button:hover {
      filter: brightness(1.1);
    }

    .sidebar hr {
      margin: 8px 0;
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700;
    }

    .salebtn button {
      background: var(--brand);
      text-align: left;
    }

    .salebtn button.active {
      outline: 3px solid rgba(48, 182, 162, .35);
    }

    /* Main area */
    .free-area {
      flex: 1;
      background: #f3f4f6;
      padding: 24px;
      overflow: auto;
    }

    .panel {
      display: none;
    }

    .panel.active {
      display: block;
    }

    .content {
      background: #00a619;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      text-align: center;
    }

    .card h3 {
      font-size: 14px;
      color: #374151;
    }

    .card p {
      font-size: 22px;
      font-weight: 800;
      margin-top: 6px;
      color: #0f172a;
    }

    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }

    .filter-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .filter-bar input,
    .filter-bar select,
    .filter-bar button {
      padding: 8px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      background: #fff;
    }

    .btn {
      padding: 9px 12px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      color: #fff;
      background: var(--primary);
    }

    .btn.secondary {
      background: #000000;
    }

    .btn.warn {
      background: var(--warn);
      color: #000;
    }

    .btn.danger {
      background: var(--danger);
    }

    .btn.light {
      background: #e5e7eb;
      color: #111827;
      border: 1px solid #d1d5db;
    }

    .table-wrap {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      overflow: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 12px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      white-space: nowrap;
    }

    th {
      background: #f9fafb;
      font-size: 13px;
      color: #374151;
      cursor: pointer;
      position: sticky;
      top: 0;
    }

    tr:hover td {
      background: #fcfcfd;
    }

    .badge {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }

    .Active {
      background: #d1fae5;
      color: #065f46;
    }

    .Inactive {
      background: #fee2e2;
      color: #991b1b;
    }

    .row-actions button {
      padding: 6px 10px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }

    .row-actions .edit {
      background: var(--warn);
    }

    .row-actions .del {
      background: var(--danger);
      color: #fff;
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50;
    }

    .modal {
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      padding: 18px;
    }

    .modal h2 {
      margin-bottom: 10px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .form-grid .full {
      grid-column: 1/-1;
    }

    .modal input,
    .modal select {
      width: 100%;
      padding: 10px;
      border: 1px solid #d1d5db;
      border-radius: 10px;
    }

    .modal .footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px;
    }

    /* Analysis */
    .chart-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    }

    .analysis-controls {
      background: #fff;
      border-radius: 14px;
      padding: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      margin-bottom: 12px;
    }

    .analysis-controls .filter-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .analysis-controls label {
      font-size: 12px;
      color: #374151;
    }

    .mini-cards {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin: 12px 0;
    }

    .mini-card {
      background: #fff;
      border-radius: 12px;
      padding: 14px;
      text-align: center;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
    }

    .mini-card h4 {
      margin-bottom: 6px;
      font-size: 12px;
      color: #374151;
    }

    .mini-card p {
      font-size: 20px;
      font-weight: 800;
      color: #0f172a;
    }

    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .chart-wrap {
      position: relative;
      height: 320px;
    }

    /* Export panel */
    .export-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .export-grid .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .muted {
      color: var(--muted);
      font-size: 13px;
    }

    .error, .success {
      color: var(--ink);
      margin: 10px 0;
      text-align: center;
      font-weight: bold;
    }

    .error {
      color: var(--danger);
    }

    .success {
      color: var(--brand);
    }

    @media (max-width: 1000px) {
      .cards {
        grid-template-columns: 1fr;
      }

      .charts-grid {
        grid-template-columns: 1fr;
      }

      .sidebar {
        width: 220px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">User Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by name, email, or role..." /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" id="roleBtn"><?php echo htmlspecialchars($current_role); ?></button>
      <div class="user-icon" onclick="window.location.href='profile.php'"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>User Dashboard</h1>
      <nav>
        <button class="salesbtn" disabled>User</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='sales.html'">Sales</button>
          <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
          <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>
        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn active" data-page="user-dashboard">User Dashboard</button>
          <button class="tab-btn" data-page="user-analysis">User Analysis</button>
          <button class="tab-btn" data-page="user-export">Export Report</button>
        </div>
        <div class="otherbtn2">
          <button class="Ubtn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
      </nav>
    </aside>

    <!-- Main -->
    <main class="free-area">
      <!-- Dashboard -->
      <section id="user-dashboard" class="panel active">
        <div class="content">
          <h1>User Management</h1>
          <?php if (isset($success_message)) { ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
          <?php } ?>
          <?php if (isset($error_message)) { ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
          <?php } ?>
          <div class="cards">
            <div class="card">
              <h3>Total Users</h3>
              <p id="cardTotalUsers"><?php echo $total_users; ?></p>
            </div>
            <div class="card">
              <h3>Active Users</h3>
              <p id="cardActiveUsers"><?php echo $active_users; ?></p>
            </div>
            <div class="card">
              <h3>Admins</h3>
              <p id="cardAdmins"><?php echo $admins; ?></p>
            </div>
          </div>
          <div class="toolbar">
            <div class="filter-bar">
              <input type="date" id="filterLastActive" />
              <input type="text" id="filterRole" placeholder="Role" />
              <select id="filterStatus">
                <option value="">All Status</option>
                <option>Active</option>
                <option>Inactive</option>
              </select>
              <button class="btn light" id="btnFilter">Filter</button>
              <button class="btn light" id="btnReset">Reset</button>
            </div>
            <div style="flex:1"></div>
            <button class="btn secondary" id="btnAdd">➕ Add User</button>
            <button class="btn" id="btnExportCsv">⬇ CSV</button>
          </div>
          <div class="table-wrap">
            <table id="userTable">
              <thead>
                <tr>
                  <th data-sort="user_id">User ID ▲▼</th>
                  <th data-sort="full_name">Name ▲▼</th>
                  <th data-sort="email">Email ▲▼</th>
                  <th data-sort="role">Role ▲▼</th>
                  <th data-sort="status">Status ▲▼</th>
                  <th data-sort="last_active">Last Active ▲▼</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $index => $user) { ?>
                  <tr data-id="<?php echo $user['id']; ?>">
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><span class="badge <?php echo $user['status']; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($user['last_active']); ?></td>
                    <td class="row-actions">
                      <button classitro="edit" data-id="<?php echo $user['id']; ?>">✏ Edit</button>
                      <button class="del" data-id="<?php echo $user['id']; ?>">🗑 Delete</button>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Analysis -->
      <section id="user-analysis" class="panel">
        <div class="content">
          <h2>User Analysis</h2>
          <div class="analysis-controls">
            <div class="filter-row">
              <label>Year</label>
              <select id="anYear">
                <?php foreach ($years as $year) { ?>
                  <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                <?php } ?>
              </select>
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
                <option value="Active">Active</option>
                <option value="Inactive</option>
              </select>
              <button class="btn light" id="anReset">Reset</button>
            </div>
          </div>

          <div class="mini-cards">
            <div class="mini-card">
              <h4>Total Users</h4>
              <p id="anTotalUsers"><?php echo $total_users; ?></p>
            </div>
            <div class="mini-card">
              <h4>Active Users</h4>
              <p id="anActiveUsers"><?php echo $active_users; ?></p>
            </div>
            <div class="mini-card">
              <h4>Admins</h4>
              <p id="anAdmins"><?php echo $admins; ?></p>
            </div>
            <div class="mini-card">
              <h4>Top Role</h4>
              <p id="anTopRole"><?php echo htmlspecialchars($top_role); ?></p>
            </div>
          </div>

          <div class="charts-grid">
            <div class="chart-card">
              <h3>User Activity Over Time</h3>
              <p class="muted" id="anRangeLabel"></p>
              <div class="chart-wrap"><canvas id="anChartActivity"></canvas></div>
            </div>
            <div class="chart-card">
              <h3>Users by Role</h3>
              <p class="muted">Top 5 roles for the selected period.</p>
              <div class="chart-wrap"><canvas id="anChartTopRoles"></canvas></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Export -->
      <section id="user-export" class="panel">
        <div class="content">
          <h2>Export User Reports</h2>
          <div class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" />
              <input type="date" id="expTo" />
              <select id="expStatus">
                <option value="">All Status</option>
                <option>Active</option>
                <option>Inactive</option>
              </select>
              <input type="text" id="expRole" placeholder="Role" />
            </div>
            <div class="row">
              <button class="btn" id="expCsv">Export CSV</button>
              <button class="btn secondary" id="expXlsx">Export Excel</button>
              <button class="btn warn" id="expPdf">Export PDF</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal Add/Edit -->
  <div class="modal-backdrop" id="modalBackdrop">
    <div class="modal">
      <h2 id="modalTitle">Add User</h2>
      <form id="userForm" method="POST">
        <input type="hidden" id="id" name="id">
        <div class="form-grid">
          <div><label>User ID</label><input id="fUserId" name="fUserId" type="text" placeholder="e.g. UID1001"></div>
          <div><label>Name</label><input id="fName" name="fName" type="text" placeholder="User name"></div>
          <div class="full"><label>Email</label><input id="fEmail" name="fEmail" type="email" placeholder="user@example.com"></div>
          <div><label>Role</label><select id="fRole" name="fRole">
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
          </select></div>
          <div><label>Last Active</label><input id="fLastActive" name="fLastActive" type="date"></div>
          <div>
            <label>Status</label>
            <select id="fStatus" name="fStatus">
              <option>Active</option>
              <option>Inactive</option>
            </select>
          </div>
          <div><label>Mobile</label><input id="fMobile" name="fMobile" type="text" placeholder="Mobile number"></div>
          <div><label>Address</label><input id="fAddress" name="fAddress" type="text" placeholder="Address"></div>
          <div class="full"><label>District</label><input id="fDistrict" name="fDistrict" type="text" placeholder="District"></div>
        </div>
        <div class="footer">
          <button type="button" class="btn light" id="btnCancel">Cancel</button>
          <button type="submit" class="btn" id="btnSave" name="add_user">Save</button>
          <button type="submit" class="btn" id="btnUpdate" name="update_user" style="display: none;">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    /* ---------- UTIL ---------- */
    const $ = (s, root = document) => root.querySelector(s);
    const $$ = (s, root = document) => [...root.querySelectorAll(s)];

    let sortState = { key: 'last_active', dir: 'desc' };
    let editingId = null;

    /* ---------- RENDER (Dashboard) ---------- */
    function rowHTML(user, index) {
      return `<trಸtr data-id="${user.id}">
        <td>${user.user_id}</td>
        <td>${user.full_name}</td>
        <td>${user.email}</td>
        <td>${user.role}</td>
        <td><span class="badge ${user.status}">${user.status}</span></td>
        <td>${user.last_active}</td>
        <td class="row-actions"><button class="edit" data-id="${user.id}">✏ Edit</button><button class="del" data-id="${user.id}">🗑 Delete</button></td>
      </tr>`;
    }

    function renderTable(data = <?php echo json_encode($users); ?>) {
      const filtered = applyFilters(data);
      const sorted = applySort(filtered);
      const tbody = $('#userTable tbody');
      tbody.innerHTML = sorted.map((r, i) => rowHTML(r, i)).join('');
      render ROBERT

    /* ---------- TABLE INTERACTIONS ---------- */
    $$('#userTable thead th').forEach(th => {
      const key = th.dataset.sort;
      if (!key) return;
      th.onclick = () => {
        if (sortState.key === key) {
          sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
          sortState.key = key;
          sortState.dir = 'asc';
        }
        renderTable();
      };
    });

    /* ---------- MODAL (Add/Edit) ---------- */
    const backdrop = $('#modalBackdrop');
    function openModal(title, user = null) {
      $('#modalTitle').textContent = title;
      $('#id').value = user?.id ?? '';
      $('#fUserId').value = user?.user_id ?? `UID${nextId()}`;
      $('#fName').value = user?.full_name ?? '';
      $('#fEmail').value = user?.email ?? '';
      $('#fRole').value = user?.role ?? 'user';
      $('#fStatus').value = user?.status ?? 'Active';
      $('#fLastActive').value = user?.last_active ?? new Date().toISOString().slice(0, 10);
      $('#fMobile').value = user?.mobile ?? '';
      $('#fAddress').value = user?.address ?? '';
      $('#fDistrict').value = user?.district ?? '';
      editingId = user?.id ?? null;
      $('#btnSave').style.display = user ? 'none' : 'inline-block';
      $('#btnUpdate').style.display = user ? 'inline-block' : 'none';
      backdrop.style.display = 'flex';
    }
    function closeModal() { backdrop.style.display = 'none'; }
    function nextId() {
      const ids = <?php echo json_encode(array_column($users, 'user_id')); ?>;
      return ids.length ? Math.max(...ids.map(id => parseInt(id.replace('UID', '') || 0))) + 1 : 1001;
    }

    $('#btnAdd').onclick = () => openModal('Add User');
    $('#btnCancel').onclick = closeModal;

    $$('#userTable .edit').forEach(btn => btn.onclick = (e) => {
      const id = +e.target.dataset.id;
      const user = <?php echo json_encode($users); ?>.find(u => u.id === id);
      openModal('Edit User', user);
    });

    $$('#userTable .del').forEach(btn => btn.onclick = (e) => {
      const id = +e.target.dataset.id;
      if (confirm(`Delete user #${<?php echo json_encode($users); ?>.find(u => u.id === id).user_id}?`)) {
        fetch('admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `delete_user=1&id=${id}`
        }).then(() => location.reload());
      }
    });

    /* ---------- FILTERS & SEARCH ---------- */
    function getFilters() {
      return {
        lastActive: $('#filterLastActive').value.trim(),
        role: $('#filterRole').value.trim().toLowerCase(),
        status: $('#filterStatus').value.trim(),
        search: $('#globalSearch').value.trim().toLowerCase()
      };
    }
    function applyFilters(data) {
      const { lastActive, role, status, search } = getFilters();
      return data.filter(r => {
        const okDate = !lastActive || r.last_active === lastActive;
        const okRole = !role || r.role.toLowerCase().includes(role);
        const okStatus = !status || r.status === status;
        const okSearch = !search || (
          r.user_id.toLowerCase().includes(search) ||
          r.full_name.toLowerCase().includes(search) ||
          r.email.toLowerCase().includes(search) ||
          r.role.toLowerCase().includes(search)
        );
        return okDate && okRole && okStatus && okSearch;
      });
    }
    function applySort(data) {
      const { key, dir } = sortState, mult = dir === 'asc' ? 1 : -1;
      return data.slice().sort((a, b) => {
        let va = a[key], vb = b[key];
        if (key === 'last_active') { return (new Date(va) - new Date(vb)) * mult; }
        if (va < vb) return -1 * mult; if (va > vb) return 1 * mult; return 0;
      });
    }

    $('#btnFilter').onclick = () => renderTable();
    $('#btnReset').onclick = () => {
      $('#filterLastActive').value = '';
      $('#filterRole').value = '';
      $('#filterStatus').value = '';
      renderTable();
    };
    $('#globalSearch').addEventListener('input', renderTable);

    /* ---------- EXPORTS ---------- */
    function toCSV(rows) {
      const header = ['User ID', 'Name', 'Email', 'Role', 'Status', 'Last Active', 'Mobile', 'Address', 'District'];
      const lines = [header.join(',')];
      rows.forEach(r => lines.push([
        quoteCSV(r.user_id),
        quoteCSV(r.full_name),
        quoteCSV(r.email),
        quoteCSV(r.role),
        r.status,
        r.last_active,
        quoteCSV(r.mobile),
        quoteCSV(r.address),
        quoteCSV(r.district)
      ].join(',')));
      return lines.join('\n');
    }
    function quoteCSV(s) {
      const str = String(s ?? '');
      return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
    }
    function download(name, blob) {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = name;
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    $('#btnExportCsv').onclick = () => {
      const data = applyFilters(<?php echo json_encode($users); ?>);
      const csv = toCSV(data);
      download('user_report.csv', new Blob([csv], { type: 'text/csv' }));
    };

    function getExportFiltered() {
      const from = $('#expFrom').value, to = $('#expTo').value, status = $('#expStatus').value, role = $('#expRole').value.trim().toLowerCase();
      return <?php echo json_encode($users); ?>.filter(r => {
        const okFrom = !from || r.last_active >= from;
        const okTo = !to || r.last_active <= to;
        const okStatus = !status || r.status === status;
        const okRole = !role || r.role.toLowerCase().includes(role);
        return okFrom && okTo && okStatus && okRole;
      });
    }

    $('#expCsv').onclick = () => {
      const csv = toCSV(getExportFiltered());
      download('user_export.csv', new Blob([csv], { type: 'text/csv' }));
    };

    $('#expXlsx').onclick = () => {
      const rows = getExportFiltered().map(r => ({
        'User ID': r.user_id,
        Name: r.full_name,
        Email: r.email,
        Role: r.role,
        Status: r.status,
        'Last Active': r.last_active,
        Mobile: r.mobile,
        Address: r.address,
        District: r.district
      }));
      const ws = XLSX.utils.json_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Users');
      XLSX.writeFile(wb, 'user_export.xlsx');
    };

    $('#expPdf').onclick = () => {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      doc.setFontSize(14);
      doc.text('User Report', 14, 16);
      const rows = getExportFiltered().map(r => [r.user_id, r.full_name, r.email, r.role, r.status, r.last_active, r.mobile, r.address, r.district]);
      doc.autoTable({ startY: 22, head: [['User ID', 'Name', 'Email', 'Role', 'Status', 'Last Active', 'Mobile', 'Address', 'District']], body: rows });
      doc.save('user_export.pdf');
    };

    /* ---------- ANALYSIS (Charts & KPIs) ---------- */
    let anChartActivity = null, anChartTopRoles = null;
    function filterByYMStatus(data, y, m, status) {
      return data.filter(r => {
        const yOk = !y || r.last_active.startsWith(y);
        const mOk = (m === 'all' || !m) ? true : r.last_active.slice(5, 7) === m;
        const sOk = !status || r.status === status;
        return yOk && mOk && sOk;
      });
    }
    function dailyAgg(data, y, m) {
      if (m && m !== 'all') {
        const daysInMonth = new Date(+y, +m, 0).getDate();
        const labels = Array.from({ length: daysInMonth }, (_, i) => String(i + 1).padStart(2, '0'));
        const map = {};
        data.forEach(r => {
          const d = r.last_active.slice(8, 10);
          map[d] = (map[d] || 0) + 1;
        });
        const values = labels.map(d => +(map[d] || 0));
        return { labels, values, unit: 'Day' };
      } else {
        const labels = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
        const map = {};
        data.forEach(r => {
          const mm = r.last_active.slice(5, 7);
          map[mm] = (map[mm] || 0) + 1;
        });
        const values = labels.map(mm => +(map[mm] || 0));
        return { labels, values, unit: 'Month' };
      }
    }
    function topRolesAgg(data, topN = 5) {
      const map = {};
      data.forEach(r => {
        map[r.role] = (map[r.role] || 0) + 1;
      });
      const pairs = Object.entries(map).sort((a, b) => b[1] - a[1]).slice(0, topN);
      return { labels: pairs.map(p => p[0]), values: pairs.map(p => +p[1]) };
    }
    function updateKpis(data) {
      const totalUsers = data.length;
      const activeUsers = data.filter(r => r.status === 'Active').length;
      const admins = data.filter(r => r.role === 'admin').length;
      const tr = topRolesAgg(data, 1);
      $('#anTotalUsers').textContent = totalUsers;
      $('#anActiveUsers').textContent = activeUsers;
      $('#anAdmins').textContent = admins;
      $('#anTopRole').textContent = tr.labels?.[0] || '—';
    }
    function setRangeLabel(y, m) {
      const mapMonth = { '01': 'Jan', '02': 'Feb', '03': 'Mar', '04': 'Apr', '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Aug', '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dec' };
      $('#anRangeLabel').textContent = (m && m !== 'all') ? `${mapMonth[m]} ${y}` : `Year ${y}`;
    }
    function drawAnalysis() {
      const y = $('#anYear').value;
      const m = $('#anMonth').value;
      const st = $('#anStatus').value;
      const data = filterByYMStatus(<?php echo json_encode($users); ?>, y, m, st);
      updateKpis(data);
      setRangeLabel(y, m);
      const dAgg = dailyAgg(data, y, m);
      const tAgg = topRolesAgg(data, 5);
      const c1 = $('#anChartActivity').getContext('2d');
      if (anChartActivity) anChartActivity.destroy();
      anChartActivity = new Chart(c1, {
        type: 'bar',
        data: {
          labels: dAgg.labels,
          datasets: [{ label: `Active Users by ${dAgg.unit}`, data: dAgg.values, backgroundColor: '#2196f3' }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
      });
      const c2 = $('#anChartTopRoles').getContext('2d');
      if (anChartTopRoles) anChartTopRoles.destroy();
      anChartTopRoles = new Chart(c2, {
        type: 'pie',
        data: {
          labels: tAgg.labels,
          datasets: [{ label: 'Users', data: tAgg.values, backgroundColor: ['#2196f3', '#4caf50', '#ff9800', '#f44336', '#9c27b0'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }
    function initAnalysis() {
      const years = <?php echo json_encode($years); ?>;
      const yearSel = $('#anYear');
      yearSel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
      yearSel.value = years[years.length - 1] || new Date().getFullYear().toString();
      $('#anMonth').value = 'all';
      $('#anStatus').value = '';
      drawAnalysis();
    }
    function isAnalysisActive() {
      return $('#user-analysis').classList.contains('active');
    }
    function syncAnalysisIfVisible() {
      if (isAnalysisActive()) drawAnalysis();
    }
    document.addEventListener('change', (e) => {
      if (['anYear', 'anMonth', 'anStatus'].includes(e.target.id)) {
        if (isAnalysisActive()) drawAnalysis();
      }
    });
    $('#anReset').addEventListener('click', initAnalysis);

    /* ---------- NAVIGATION ---------- */
    function activatePanel(id) {
      $$('.panel').forEach(p => p.classList.remove('active'));
      $('#' + id).classList.add('active');
      $$('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.page === id));
      if (id === 'user-analysis') {
        setTimeout(() => {
          if (!$('#anYear').options.length) initAnalysis();
          else drawAnalysis();
        }, 50);
      }
    }
    $$('.tab-btn').forEach(btn => btn.onclick = () => activatePanel(btn.dataset.page));

    /* ---------- ROLE TOGGLE ---------- */
    $('#roleBtn').onclick = () => {
      window.location.href = 'profile.php';
    };

    /* ---------- INIT ---------- */
    renderTable();
  </script>
</body>
</html>