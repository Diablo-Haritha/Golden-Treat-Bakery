<?php
// ---------- DB CONNECTION ----------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bakehouse";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- Initialize message ----------
$message = "";

// ---------- Handle form submissions ----------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // ADD USER
        if ($_POST['action'] == 'add') {
            $name       = mysqli_real_escape_string($conn, $_POST['fName']);
            $email      = mysqli_real_escape_string($conn, $_POST['fEmail']);
            $mobile     = mysqli_real_escape_string($conn, $_POST['fMobile']);
            $address    = mysqli_real_escape_string($conn, $_POST['fAddress']);
            $district   = mysqli_real_escape_string($conn, $_POST['fDistrict']);
            $role       = mysqli_real_escape_string($conn, $_POST['fRole']);
            $lastActive = mysqli_real_escape_string($conn, $_POST['fLastActive']);
            $status     = mysqli_real_escape_string($conn, $_POST['fStatus']);
            $password   = password_hash('default123', PASSWORD_DEFAULT); // Default password

            if (empty($lastActive)) {
                $message = "⚠ Please select a date joined.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "⚠ Please enter a valid email.";
            } else {
                $checkEmail = "SELECT id FROM users WHERE email = '$email'";
                $result = $conn->query($checkEmail);
                if ($result->num_rows > 0) {
                    $message = "⚠ Email already exists.";
                } else {
                    $sql = "INSERT INTO users 
                            (full_name, email, mobile, address, district, role, date_joined, status, password)
                            VALUES 
                            ('$name', '$email', '$mobile', '$address', '$district', '$role', '$lastActive', '$status', '$password')";
                    if ($conn->query($sql) === TRUE) {
                        $message = "✅ User added successfully.";
                    } else {
                        $message = "❌ Error: " . $conn->error;
                    }
                }
            }

        // UPDATE USER
        } elseif ($_POST['action'] == 'update') {
            $id         = (int)$_POST['fId'];
            $name       = mysqli_real_escape_string($conn, $_POST['fName']);
            $email      = mysqli_real_escape_string($conn, $_POST['fEmail']);
            $mobile     = mysqli_real_escape_string($conn, $_POST['fMobile']);
            $address    = mysqli_real_escape_string($conn, $_POST['fAddress']);
            $district   = mysqli_real_escape_string($conn, $_POST['fDistrict']);
            $role       = mysqli_real_escape_string($conn, $_POST['fRole']);
            $lastActive = mysqli_real_escape_string($conn, $_POST['fLastActive']);
            $status     = mysqli_real_escape_string($conn, $_POST['fStatus']);

            if (empty($lastActive)) {
                $message = "⚠ Please select a date joined.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "⚠ Please enter a valid email.";
            } else {
                $sql = "UPDATE users SET 
                        full_name='$name', 
                        email='$email', 
                        mobile='$mobile', 
                        address='$address', 
                        district='$district', 
                        role='$role', 
                        date_joined='$lastActive', 
                        status='$status'
                        WHERE id=$id";
                if ($conn->query($sql) === TRUE) {
                    $message = "✅ User updated successfully.";
                } else {
                    $message = "❌ Error: " . $conn->error;
                }
            }

        // DELETE USER
        } elseif ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM users WHERE id=$id";
            if ($conn->query($sql) === TRUE) {
                $message = "🗑 User deleted successfully.";
            } else {
                $message = "❌ Error: " . $conn->error;
            }

        // EXPORT CSV
        } elseif ($_POST['action'] == 'export_csv') {
            $from   = mysqli_real_escape_string($conn, $_POST['expFrom']);
            $to     = mysqli_real_escape_string($conn, $_POST['expTo']);
            $status = mysqli_real_escape_string($conn, $_POST['expStatus']);
            $role   = mysqli_real_escape_string($conn, $_POST['expRole']);

            $conditions = [];
            if ($from) $conditions[] = "date_joined >= '$from'";
            if ($to) $conditions[] = "date_joined <= '$to'";
            if ($status) $conditions[] = "status = '$status'";
            if ($role) $conditions[] = "role LIKE '%$role%'";
            $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

            $sql = "SELECT id, full_name, email, mobile, address, district, role, status, date_joined 
                    FROM users $where";
            $result = $conn->query($sql);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename=user_export.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['User ID', 'Name', 'Email', 'Mobile', 'Address', 'District', 'Role', 'Status', 'Date Joined']);
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        }
    }
}

// ---------- Handle filters and sorting ----------
$lastActive = isset($_GET['filterLastActive']) ? mysqli_real_escape_string($conn, $_GET['filterLastActive']) : '';
$role       = isset($_GET['filterRole']) ? mysqli_real_escape_string($conn, $_GET['filterRole']) : '';
$status     = isset($_GET['filterStatus']) ? mysqli_real_escape_string($conn, $_GET['filterStatus']) : '';
$search     = isset($_GET['globalSearch']) ? mysqli_real_escape_string($conn, $_GET['globalSearch']) : '';
$sortKey    = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'date_joined';
$sortDir    = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$conditions = [];
if ($lastActive) $conditions[] = "date_joined = '$lastActive'";
if ($role) $conditions[] = "role LIKE '%$role%'";
if ($status) $conditions[] = "status = '$status'";
if ($search) {
    $conditions[] = "(id LIKE '%$search%' 
                   OR full_name LIKE '%$search%' 
                   OR email LIKE '%$search%' 
                   OR mobile LIKE '%$search%' 
                   OR address LIKE '%$search%' 
                   OR district LIKE '%$search%' 
                   OR role LIKE '%$search%')";
}
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$sql = "SELECT id, full_name AS name, email, mobile, address, district, role, status, date_joined AS lastActive 
        FROM users $where ORDER BY $sortKey $sortDir";
$result = $conn->query($sql);

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// ---------- Calculate card metrics ----------
$totalUsers  = count($users);
$activeUsers = count(array_filter($users, fn($r) => $r['status'] === 'Active'));
$admins      = count(array_filter($users, fn($r) => $r['role'] === 'admin'));
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management - Golden Treat</title>
<link rel="stylesheet" href="style1.css">
</head>
<body>
  <?php if ($message): ?>
    <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

   <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">User Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
      </div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>User Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">User</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../sales/index.php'">Sales</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>

        </div>
        <hr />
        <p>User Management</p>
        <div class="salebtn">
          <button class="tab-btn active" onclick="window.location.href='index.php'">User Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='index2.php'">User SUM</button>
         <button class="tab-btn " onclick="window.location.href='index3.php'">User Analysis</button>
          
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <section id="user-dashboard" class="panel active">
        <div class="content">
          <h1>User Management</h1>
          <div class="cards">
            <div class="card">
              <h3>Total Users</h3>
              <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="card">
              <h3>Active Users</h3>
              <p><?php echo $activeUsers; ?></p>
            </div>
            <div class="card">
              <h3>Admins</h3>
              <p><?php echo $admins; ?></p>
            </div>
          </div>
          <div class="toolbar">
            <form class="filter-bar" method="GET" action="">
              <input type="date" id="filterLastActive" name="filterLastActive" value="<?php echo htmlspecialchars($lastActive); ?>" />
              <input type="text" id="filterRole" name="filterRole" placeholder="Role" value="<?php echo htmlspecialchars($role); ?>" />
              <select id="filterStatus" name="filterStatus">
                <option value="">All Status</option>
                <option <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
              <button class="btn light" type="submit">Filter</button>
              <button class="btn light" type="button" onclick="window.location.href='?panel=user-dashboard'">Reset</button>
              <input type="hidden" name="panel" value="user-dashboard">
            </form>
            <div style="flex:1"></div>
            <button class="btn secondary" onclick="window.location.href='?panel=user-dashboard&modal=add'">➕ Add User</button>
            <form method="POST" action="">
              <input type="hidden" name="action" value="export_csv">
              <button class="btn" type="submit">⬇ CSV</button>
            </form>
          </div>
          <div class="table-wrap">
            <table id="userTable">
              <thead>
                <tr>
                  <th><a href="?sort=id&dir=<?php echo $sortKey === 'id' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">User ID ▲▼</a></th>
                  <th><a href="?sort=full_name&dir=<?php echo $sortKey === 'full_name' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Name ▲▼</a></th>
                  <th><a href="?sort=email&dir=<?php echo $sortKey === 'email' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Email ▲▼</a></th>
                  <th><a href="?sort=mobile&dir=<?php echo $sortKey === 'mobile' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Mobile ▲▼</a></th>
                  <th><a href="?sort=address&dir=<?php echo $sortKey === 'address' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Address ▲▼</a></th>
                  <th><a href="?sort=district&dir=<?php echo $sortKey === 'district' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">District ▲▼</a></th>
                  <th><a href="?sort=role&dir=<?php echo $sortKey === 'role' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Role ▲▼</a></th>
                  <th><a href="?sort=status&dir=<?php echo $sortKey === 'status' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Status ▲▼</a></th>
                  <th><a href="?sort=date_joined&dir=<?php echo $sortKey === 'date_joined' && $sortDir === 'ASC' ? 'desc' : 'asc'; ?>&panel=user-dashboard">Last Active ▲▼</a></th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                    <td><?php echo htmlspecialchars($user['address']); ?></td>
                    <td><?php echo htmlspecialchars($user['district']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><span class="badge <?php echo htmlspecialchars($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($user['lastActive']); ?></td>
                    <td class="row-actions">
                      <button class="edit" onclick="window.location.href='?panel=user-dashboard&modal=edit&id=<?php echo $user['id']; ?>'">✏ Edit</button>
                      <form method="POST" action="" onsubmit="return confirm('Delete user #<?php echo $user['id']; ?>?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <button class="del" type="submit">🗑 Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section id="user-export" class="panel <?php echo isset($_GET['panel']) && $_GET['panel'] === 'user-export' ? 'active' : ''; ?>">
        <div class="content">
          <h2>Export User Reports</h2>
          <form method="POST" action="" class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" name="expFrom" />
              <input type="date" id="expTo" name="expTo" />
              <select id="expStatus" name="expStatus">
                <option value="">All Status</option>
                <option>Active</option>
                <option>Inactive</option>
              </select>
              <input type="text" id="expRole" name="expRole" placeholder="Role" />
            </div>
            <div class="row">
              <input type="hidden" name="action" value="export_csv">
              <button class="btn" type="submit">Export CSV</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>

  <?php
  // Handle modal display
  $modal = isset($_GET['modal']) ? $_GET['modal'] : '';
  $editUser = null;
  if ($modal === 'edit' && isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $sql = "SELECT id, full_name AS name, email, mobile, address, district, role, status, date_joined AS lastActive 
              FROM users WHERE id = $id";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
          $editUser = $result->fetch_assoc();
      }
  }
  ?>
  <div class="modal-backdrop" style="display: <?php echo $modal ? 'flex' : 'none'; ?>;">
    <div class="modal">
      <h2><?php echo $modal === 'edit' ? 'Edit User' : 'Add User'; ?></h2>
      <form method="POST" action="">
        <div class="form-grid">
          <div><label>User ID</label><input id="fId" name="fId" type="text" value="<?php echo $editUser ? htmlspecialchars($editUser['id']) : ''; ?>" readonly></div>
          <div><label>Name</label><input id="fName" name="fName" type="text" placeholder="User name" value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>" required></div>
          <div class="full"><label>Email</label><input id="fEmail" name="fEmail" type="email" placeholder="user@example.com" value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" required></div>
          <div><label>Mobile</label><input id="fMobile" name="fMobile" type="tel" placeholder="Mobile Number" value="<?php echo $editUser ? htmlspecialchars($editUser['mobile']) : ''; ?>"></div>
          <div class="full"><label>Address</label><input id="fAddress" name="fAddress" type="text" placeholder="Address" value="<?php echo $editUser ? htmlspecialchars($editUser['address']) : ''; ?>"></div>
          <div><label>District</label><input id="fDistrict" name="fDistrict" type="text" placeholder="District" value="<?php echo $editUser ? htmlspecialchars($editUser['district']) : ''; ?>"></div>
          <div>
            <label>Role</label>
            <select id="fRole" name="fRole" required>
              <option value="customer" <?php echo $editUser && $editUser['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
              <option value="manager" <?php echo $editUser && $editUser['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
              <option value="admin" <?php echo $editUser && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
          </div>
          <div><label>Last Active</label><input id="fLastActive" name="fLastActive" type="date" value="<?php echo $editUser ? htmlspecialchars($editUser['lastActive']) : ''; ?>" required></div>
          <div>
            <label>Status</label>
            <select id="fStatus" name="fStatus" required>
              <option value="Active" <?php echo $editUser && $editUser['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
              <option value="Inactive" <?php echo $editUser && $editUser['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="footer">
          <button class="btn light" type="button" onclick="window.location.href='?panel=user-dashboard'">Cancel</button>
          <input type="hidden" name="action" value="<?php echo $modal === 'edit' ? 'update' : 'add'; ?>">
          <button class="btn" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
<?php $conn->close(); ?>