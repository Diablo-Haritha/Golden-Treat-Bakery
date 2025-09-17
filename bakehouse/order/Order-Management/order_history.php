<?php
// order_history.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function clean($s) { return trim((string)$s); }

// fetch orders (adjust LIMIT as needed)
$orders = [];
$sql = "SELECT id, order_date, customer, product, quantity, price, status FROM orders ORDER BY id DESC LIMIT 1000";
$res = $conn->query($sql);
if ($res) $orders = $res->fetch_all(MYSQLI_ASSOC);

// count total orders
$row2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders")->fetch_assoc();
$totalOrders = (int)$row2['total_orders'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Order History</title>

  <!-- Export libs -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>

  <style>
    :root {
      --brand: #9c0dc7;
      --ink: #111827;
      --paper: #fff;
      --muted: #6b7280;
      --soft: #e5e7eb;
      --warn: #ffc107;
      --danger: #dc3545;
      --primary: #007bff;
    }
    * { margin:0; padding:0; box-sizing:border-box }
    body { font-family: Arial, Helvetica, sans-serif; background:#f4f6f9; color:#0f172a; min-height:100vh; display:flex; flex-direction:column }
    .header { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:12px 16px; box-shadow:0 2px 5px rgba(0,0,0,.08) }
    .header-left img { width:56px; height:auto; border-radius:8px }
    .header-middle { display:flex; align-items:center; gap:12px; flex:1; margin:0 16px; max-width:720px }
    .header-middle-title { font-weight:800; font-size:26px; color:var(--brand) }
    .search-bar { flex:1; display:flex }
    .search-bar input { width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px }
    .header-right { display:flex; align-items:center; gap:12px }
    .role-btn { background:#111827; color:#fff; padding:8px 14px; border:none; border-radius:8px; cursor:pointer }

    .layout { flex:1; display:flex; min-height:0 }
    .sidebar { width:260px; background:#fff; padding:18px; display:flex; flex-direction:column; gap:10px; border-right:1px solid #e5e7eb }
    .sidebar h1{ text-align:center; font-size:20px; margin-bottom:8px }
    .salebtn .tab-btn { background:var(--brand); border:none; border-radius:10px; color:#fff; padding:10px }

    .free-area { flex:1; background:#f3f4f6; padding:24px; overflow:auto }
    .content { background: #fff; border-radius:14px; padding:18px; box-shadow: 0 2px 8px rgba(0,0,0,.08); display:flex; flex-direction:column; gap:16px }

    .cards { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px }
    .card { background:#fff; border-radius:12px; padding:16px; box-shadow:0 1px 6px rgba(0,0,0,.06); text-align:center }
    .card h3 { font-size:14px; color:#374151 }
    .card p { font-size:22px; font-weight:800; margin-top:6px; color:#0f172a }

    .toolbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap }
    .btn { padding:9px 12px; border:none; border-radius:10px; cursor:pointer; color:#fff; background:var(--primary) }
    .btn.secondary { background:#10b981 }
    .btn.warn { background:var(--warn); color:#000 }
    .btn.light { background:#e5e7eb; color:#111827; border:1px solid #d1d5db }

    .table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,.06); overflow:auto }
    table { width:100%; border-collapse:collapse }
    th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; white-space:nowrap }
    th { background:#f9fafb; font-size:13px; color:#374151; position:sticky; top:0 }
    tr:hover td { background:#fcfcfd }

    .badge { padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px }
    .Completed { background:#d1fae5; color:#065f46 }
    .Pending { background:#fef3c7; color:#92400e }
    .Cancelled { background:#fee2e2; color:#991b1b }
    .Returned { background:#ffe7f5; color:#8b1a66 }

    .row-actions button { padding:6px 10px; border:none; border-radius:8px; cursor:pointer; margin-right:6px; color:#fff }
    .viewBtn { background:#1a73e8 }
    .invoiceBtn { background:#6b21a8 }
    .delBtn { background:#e74c3c }

    /* modal */
    .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:50 }
    .modal { width:100%; max-width:720px; background:#fff; border-radius:12px; padding:18px; box-shadow:0 10px 30px rgba(0,0,0,.25) }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px }
    .form-grid .full { grid-column:1/-1 }

    @media (max-width:1000px) {
      .cards { grid-template-columns:1fr }
      .sidebar { width:220px }
    }
  </style>
  
</head>
<body>
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo"></div>
    <div class="header-middle">
      <div class="header-middle-title">Order History</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by ID, customer, product or status..."></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='order.php'">Order Management</button>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h1>Orders</h1>
      <nav>
        <button class="salebtn tab-btn" disabled>History</button>
        <hr>
        <p style="font-size:12px;color:var(--muted)">View and export completed & past orders</p>
      </nav>
    </aside>

    <main class="free-area">
      <section>
        <div class="content">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div class="cards" style="grid-template-columns:repeat(3,1fr);max-width:480px;">
              <div class="card"><h3>Total Orders</h3><p><?= $totalOrders ?></p></div>
              <div class="card"><h3>Displayed</h3><p id="displayCount"><?= count($orders) ?></p></div>
              <div class="card"><h3>Last Update</h3><p><?= date('Y-m-d H:i') ?></p></div>
            </div>

            <div class="toolbar" aria-hidden="false" style="margin-left:auto">
              <select id="filterStatus" style="padding:8px;border-radius:8px;border:1px solid #d1d5db">
                <option value="">All Status</option>
                <option>Order Received</option>
                <option>Payment Confirmed</option>
                <option>Queued for Baking</option>
                <option>In Preparation</option>
                <option>Decorating</option>
                <option>Ready for Pickup</option>
                <option>Out for Delivery</option>
                <option>Completed</option>
                <option>Cancelled</option>
                <option>Refunded</option>
                <option>Returned</option>
              </select>
              <button class="btn" id="expCsv"><i class="fa-solid fa-file-csv"></i>&nbsp;CSV</button>
              <button class="btn secondary" id="expXlsx"><i class="fa-solid fa-file-excel"></i>&nbsp;Excel</button>
              <button class="btn warn" id="expPdf"><i class="fa-solid fa-file-pdf"></i>&nbsp;PDF</button>
            </div>
          </div>

          <div class="table-wrap" style="margin-top:8px">
            <table>
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Date</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Qty</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="ordersTable">
                <?php if(empty($orders)): ?>
                  <tr><td colspan="8" style="text-align:center;padding:18px">No orders found</td></tr>
                <?php else: foreach($orders as $o): ?>
                  <tr data-status="<?= htmlspecialchars($o['status']) ?>">
                    <td class="col-id"><?= (int)$o['id'] ?></td>
                    <td><?= htmlspecialchars($o['order_date']) ?></td>
                    <td><?= htmlspecialchars($o['customer']) ?></td>
                    <td><?= htmlspecialchars($o['product']) ?></td>
                    <td><?= (int)$o['quantity'] ?></td>
                    <td><?= number_format((float)$o['price'],2) ?></td>
                    <td>
                      <span class="badge <?= htmlspecialchars(str_replace(' ','',$o['status'])) ?>"><?= htmlspecialchars($o['status']) ?></span>
                    </td>
                    <td class="row-actions">
                      <!-- data attributes for modal -->
                      <button class="viewBtn" type="button"
                        data-id="<?= htmlspecialchars($o['id']) ?>"
                        data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($o['customer']) ?>"
                        data-product="<?= htmlspecialchars($o['product']) ?>"
                        data-quantity="<?= (int)$o['quantity'] ?>"
                        data-price="<?= htmlspecialchars($o['price']) ?>"
                        data-status="<?= htmlspecialchars($o['status']) ?>">
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button class="invoiceBtn" type="button"
                        data-id="<?= htmlspecialchars($o['id']) ?>"
                        data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($o['customer']) ?>"
                        data-product="<?= htmlspecialchars($o['product']) ?>"
                        data-quantity="<?= (int)$o['quantity'] ?>"
                        data-price="<?= htmlspecialchars($o['price']) ?>"
                        data-status="<?= htmlspecialchars($o['status']) ?>">
                        <i class="fa-solid fa-file-invoice"></i>
                      </button>

                      <form method="post" action="order.php" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <button class="delBtn" type="submit" onclick="return confirm('Delete order #<?= (int)$o['id'] ?>?')"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- View modal -->
  <div class="modal-backdrop" id="viewModal" role="dialog" aria-modal="true">
    <div class="modal" id="viewModalInner">
      <h2 id="viewTitle">Order</h2>
      <div style="margin-top:8px" id="viewBody">
        <div class="form-grid">
          <div><strong>Order ID</strong><div id="v_id"></div></div>
          <div><strong>Date</strong><div id="v_date"></div></div>
          <div class="full"><strong>Customer</strong><div id="v_customer"></div></div>
          <div class="full"><strong>Product</strong><div id="v_product"></div></div>
          <div><strong>Quantity</strong><div id="v_quantity"></div></div>
          <div><strong>Price</strong><div id="v_price"></div></div>
          <div class="full"><strong>Status</strong><div id="v_status"></div></div>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
        <button class="btn light" onclick="closeView()">Close</button>
        <button class="btn invoiceBtn" id="viewInvoiceBtn"><i class="fa-solid fa-file-invoice"></i>&nbsp;Invoice</button>
      </div>
    </div>
  </div>

<script>
(function(){
  // Simple utilities
  function el(q, root=document) { return root.querySelector(q); }
  function elAll(q, root=document) { return Array.from((root||document).querySelectorAll(q)); }

  // Filter table by input + status select
  const input = document.getElementById('globalSearch');
  const statusSelect = document.getElementById('filterStatus');
  const tbody = document.getElementById('ordersTable');

  function filterRows() {
    const q = (input?.value || '').trim().toLowerCase();
    const status = (statusSelect?.value || '').trim().toLowerCase();
    let count = 0;
    elAll('#ordersTable tr').forEach(tr => {
      const tds = Array.from(tr.querySelectorAll('td'));
      if (!tds.length) { tr.style.display = ''; return; }
      const rowText = tds.map(td=>td.textContent.toLowerCase()).join(' ');
      const matchesQ = !q || rowText.includes(q);
      const rowStatus = (tr.getAttribute('data-status') || '').toLowerCase();
      const matchesStatus = !status || rowStatus === status;
      const show = matchesQ && matchesStatus;
      tr.style.display = show ? '' : 'none';
      if (show) count++;
    });
    document.getElementById('displayCount').textContent = count;
  }

  input?.addEventListener('input', filterRows);
  statusSelect?.addEventListener('change', filterRows);

  // Exports: CSV / XLSX / PDF
  function gatherVisibleOrders() {
    const rows = Array.from(document.querySelectorAll('#ordersTable tr')).filter(r => r.style.display !== 'none');
    return rows.map(r => {
      const tds = r.querySelectorAll('td');
      if (!tds.length) return null;
      return {
        id: tds[0].textContent.trim(),
        date: tds[1].textContent.trim(),
        customer: tds[2].textContent.trim(),
        product: tds[3].textContent.trim(),
        quantity: tds[4].textContent.trim(),
        price: tds[5].textContent.trim(),
        status: tds[6].textContent.trim()
      };
    }).filter(Boolean);
  }

  function download(name, blob) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href), 1500);
  }

  function quoteCSV(s) {
    const str = String(s ?? '');
    return /[",\n]/.test(str) ? `"${str.replace(/"/g,'""')}"` : str;
  }

  document.getElementById('expCsv').addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    const hdr = ['Order ID','Date','Customer','Product','Qty','Price','Status'];
    const lines = [hdr.join(',')];
    rows.forEach(r => lines.push([quoteCSV(r.id), quoteCSV(r.date), quoteCSV(r.customer), quoteCSV(r.product), r.quantity, r.price, quoteCSV(r.status)].join(',')));
    download('orders_history.csv', new Blob([lines.join('\n')], { type: 'text/csv' }));
  });

  document.getElementById('expXlsx').addEventListener('click', () => {
    const rows = gatherVisibleOrders().map(r => ({ 'Order ID': r.id, 'Date': r.date, 'Customer': r.customer, 'Product': r.product, 'Qty': +r.quantity, 'Price': r.price, 'Status': r.status }));
    const ws = XLSX.utils.json_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Orders');
    XLSX.writeFile(wb, 'orders_history.xlsx');
  });

  document.getElementById('expPdf').addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const head = [['ID','Date','Customer','Product','Qty','Price','Status']];
    const body = rows.map(r => [r.id, r.date, r.customer, r.product, r.quantity, r.price, r.status]);
    doc.setFontSize(12);
    doc.text('Orders History', 14, 16);
    doc.autoTable({ startY: 22, head, body, styles: { fontSize: 9 } });
    doc.save('orders_history.pdf');
  });

  // View modal logic
  const viewModal = document.getElementById('viewModal');
  function openView(data) {
    el('#viewTitle').textContent = 'Order #' + data.id;
    el('#v_id').textContent = data.id;
    el('#v_date').textContent = data.orderDate;
    el('#v_customer').textContent = data.customer;
    el('#v_product').textContent = data.product;
    el('#v_quantity').textContent = data.quantity;
    el('#v_price').textContent = data.price;
    el('#v_status').textContent = data.status;
    viewModal.style.display = 'flex';

    // set invoice button data
    const invBtn = document.getElementById('viewInvoiceBtn');
    invBtn.dataset.id = data.id;
    invBtn.dataset.orderDate = data.orderDate;
    invBtn.dataset.customer = data.customer;
    invBtn.dataset.product = data.product;
    invBtn.dataset.quantity = data.quantity;
    invBtn.dataset.price = data.price;
    invBtn.dataset.status = data.status;
  }
  window.closeView = function(){ viewModal.style.display = 'none'; };

  // wiring view buttons (delegation)
  document.addEventListener('click', function(e){
    const view = e.target.closest('.viewBtn');
    if (view) {
      const d = view.dataset;
      openView({ id:d.id, orderDate:d.orderDate, customer:d.customer, product:d.product, quantity:d.quantity, price:d.price, status:d.status });
      return;
    }
    const inv = e.target.closest('.invoiceBtn');
    if (inv) {
      const d = inv.dataset;
      openInvoiceWindow({ id:d.id, orderDate:d.orderDate, customer:d.customer, product:d.product, quantity:d.quantity, price:d.price, status:d.status });
      return;
    }
  });

  // view modal invoice button
  document.getElementById('viewInvoiceBtn').addEventListener('click', function(){
    const d = this.dataset;
    openInvoiceWindow({ id:d.id, orderDate:d.orderDate, customer:d.customer, product:d.product, quantity:d.quantity, price:d.price, status:d.status });
  });

  // close modal on backdrop click
  viewModal.addEventListener('click', function(ev){ if (ev.target === viewModal) closeView(); });

  // invoice: opens new printable window with invoice markup
  function openInvoiceWindow(data) {
    const total = (Number(data.price) || 0).toFixed(2);
    const content = `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Invoice - Order ${data.id}</title>
        <style>
          body{font-family:Arial;margin:24px;color:#111}
          .box{max-width:720px;margin:0 auto}
          header{display:flex;justify-content:space-between;align-items:center}
          h1{color:#6b21a8}
          table{width:100%;border-collapse:collapse;margin-top:16px}
          th,td{padding:8px;border:1px solid #ddd;text-align:left}
          .tot{text-align:right;font-weight:800}
          .meta{margin-top:12px}
          .print{margin-top:18px}
          @media print{ .print{display:none} }
        </style>
      </head>
      <body>
        <div class="box">
          <header>
            <div>
              <h1>TechShelf</h1>
              <div>Order Invoice</div>
            </div>
            <div>
              <div>Order #: <strong>${escapeHtml(data.id)}</strong></div>
              <div>Date: ${escapeHtml(data.orderDate)}</div>
            </div>
          </header>

          <div class="meta">
            <strong>Customer:</strong> ${escapeHtml(data.customer)}
          </div>

          <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
            <tbody>
              <tr>
                <td>${escapeHtml(data.product)}</td>
                <td style="width:80px">${escapeHtml(data.quantity)}</td>
                <td style="width:120px">${escapeHtml(Number(data.price).toFixed(2))}</td>
                <td style="width:120px">${escapeHtml(Number(data.price).toFixed(2))}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td colspan="3" class="tot">Total</td><td>${escapeHtml(total)}</td></tr>
            </tfoot>
          </table>

          <div class="print">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
          </div>
        </div>
      </body>
      </html>
    `;
    const w = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
    if (!w) { alert('Please allow popups to open the invoice.'); return; }
    w.document.open();
    w.document.write(content);
    w.document.close();
  }

  // simple escaping for the invoice window
  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // initial filter run
  filterRows();

})();
</script>
</body>
</html>
