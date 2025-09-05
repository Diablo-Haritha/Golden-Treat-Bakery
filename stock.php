<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Management Admin UI</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
<style>
  :root {
      --brand: #e37200;
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
      box-sizing: border-box
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f4f6f9;
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      flex-direction: column
    }

    /* Header */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      padding: 12px 16px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, .08)
    }

    .header-left img {
      width: 56px;
      height: auto;
      border-radius: 8px;
      display: block;
      box-shadow: 2px 2px 5px rgba(0, 0, 0, .15)
    }

    .header-middle {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      margin: 0 16px;
      max-width: 720px
    }

    .header-middle-title {
      font-weight: 800;
      font-size: 26px;
      color: var(--brand);
      white-space: nowrap
    }

    .search-bar {
      flex: 1;
      display: flex
    }

    .search-bar input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      border-radius: 8px
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .role-btn {
      background: #111827;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer
    }

    .role-btn:hover {
      opacity: .9
    }

    .user-icon {
      width: 28px;
      height: 28px;
      background: linear-gradient(135deg, #bbb, #888);
      border-radius: 50%
    }

    /* Layout */
    .layout {
      flex: 1;
      display: flex;
      min-height: 0
    }

    .sidebar {
      width: 260px;
      background: #fff;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-right: 1px solid #e5e7eb
    }

    .sidebar h1 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 8px;
      color: #0f172a
    }

    .sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 10px
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
      text-align: center
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

    .salebtn button {
      background: #e37200;
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
      background: #9c0dc7
    }

    .Bbtn {
      background: #edcd00
    }

    .otherbtn button:hover {
      filter: brightness(1.1)
    }

    .sidebar hr {
      margin: 8px 0
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700
    }

    .salebtn button {
      background: var(--brand);
      text-align: left
    }

    .salebtn button.active {
      outline: 3px solid rgba(48, 182, 162, .35)
    }

    .sidebar hr {
      margin: 8px 0
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700
    }

    /* Sales Management sub-tabs */
    .salebtn {
      display: flex;
      flex-direction: column
    }

    .salebtn .tab-btn {
      background: var(--brand);
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      text-align: left
    }

    .salebtn .tab-btn+.tab-btn {
      margin-top: 8px
    }

    .salebtn .tab-btn.active {
      outline: 3px solid rgba(48, 182, 162, .35)
    }

    /* Main area */
    .free-area {
      flex: 1;
      background: #f3f4f6;
      padding: 24px;
      overflow: auto
    }

    .panel {
      display: none
    }

    .panel.active {
      display: block
    }

    .content {
      background: #e37200;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      display: flex;
      flex-direction: column;
      gap: 16px
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px
    }

    .card {
      background: #fff;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      text-align: center
    }

    .card h3 {
      font-size: 14px;
      color: #374151
    }

    .card p {
      font-size: 22px;
      font-weight: 800;
      margin-top: 6px;
      color: #0f172a
    }

    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center
    }

    .filter-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .filter-bar input,
    .filter-bar select,
    .filter-bar button {
      padding: 8px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      background: #fff
    }

    .btn {
      padding: 9px 12px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      color: #fff;
      background: var(--primary)
    }

    .btn.secondary {
      background: #000000
    }

    .btn.warn {
      background: var(--warn);
      color: #000
    }

    .btn.danger {
      background: var(--danger)
    }

    .btn.light {
      background: #e5e7eb;
      color: #111827;
      border: 1px solid #d1d5db
    }

    .table-wrap {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      overflow: auto
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      padding: 12px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      white-space: nowrap
    }

    th {
      background: #f9fafb;
      font-size: 13px;
      color: #374151;
      cursor: pointer;
      position: sticky;
      top: 0
    }

    tr:hover td {
      background: #fcfcfd
    }

    .badge {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700
    }

    .Completed {
      background: #d1fae5;
      color: #065f46
    }

    .Pending {
      background: #fef3c7;
      color: #92400e
    }

    .Cancelled {
      background: #fee2e2;
      color: #991b1b
    }

    .row-actions button {
      padding: 6px 10px;
      border: none;
      border-radius: 8px;
      cursor: pointer
    }

    .row-actions .edit {
      background: var(--warn)
    }

    .row-actions .del {
      background: var(--danger);
      color: #fff
    }

    /* Stock Status Badges */
.InStock {
  background: #d1fae5;   /* light green */
  color: #065f46;        /* dark green text */
}

.Low {
  background: #fff3cd;   /* light yellow */
  color: #856404;        /* dark golden */
}

.OutofStock {
  background: #f8d7da;   /* light red */
  color: #721c24;        /* dark red text */
}

/* Edit & Delete buttons inside table */
.edit {
  background: #ffc107;   /* warning yellow */
  color: #000;
  padding: 6px 10px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.del {
  background: #dc3545;   /* danger red */
  color: #fff;
  padding: 6px 10px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.edit:hover {
  filter: brightness(1.1);
}

.del:hover {
  filter: brightness(1.1);
}


    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50
    }

    .modal {
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      padding: 18px
    }

    .modal h2 {
      margin-bottom: 10px
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px
    }

    .form-grid .full {
      grid-column: 1/-1
    }

    .modal input,
    .modal select {
      width: 100%;
      padding: 10px;
      border: 1px solid #d1d5db;
      border-radius: 10px
    }

    .modal .footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px
    }

    /* Analysis */
    .chart-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
    }

    .analysis-controls {
      background: #fff;
      border-radius: 14px;
      padding: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      margin-bottom: 12px
    }

    .analysis-controls .filter-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center
    }

    .analysis-controls label {
      font-size: 12px;
      color: #374151
    }

    .mini-cards {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin: 12px 0
    }

    .mini-card {
      background: #fff;
      border-radius: 12px;
      padding: 14px;
      text-align: center;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06)
    }

    .mini-card h4 {
      margin-bottom: 6px;
      font-size: 12px;
      color: #374151
    }

    .mini-card p {
      font-size: 20px;
      font-weight: 800;
      color: #0f172a
    }

    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .chart-wrap {
      position: relative;
      height: 320px
    }

    /* Export panel */
    .export-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px
    }

    .export-grid .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .muted {
      color: var(--muted);
      font-size: 13px
    }

    @media (max-width:1000px) {
      .cards {
        grid-template-columns: 1fr
      }

      .charts-grid {
        grid-template-columns: 1fr
      }

      .sidebar {
        width: 220px
      }
    }

</style>
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
      <button class="tab-btn active" data-page="stock-dashboard">Stock Dashboard</button>
      <button class="tab-btn" data-page="stock-analysis" onclick="window.location.href='stock_analysis.php'">Stock Analysis</button>
      <button class="tab-btn" data-page="stock-export" id="btnExportCsv" onclick="window.location.href='stock_export.php'">Export Report</button>
    </div>
  </nav>
</aside>

<main class="free-area">
<section id="stock-dashboard" class="panel active">
<div class="content">
<h1>Stock Management</h1>
<div class="cards">
  <div class="card"><h3>Total Parts</h3><p id="cardTotalParts"><?php
    $res = $conn->query("SELECT COUNT(*) as c FROM stock");
    $row = $res->fetch_assoc();
    echo $row['c'];
  ?></p></div>
  <div class="card"><h3>Total Quantity</h3><p id="cardQuantity"><?php
    $res = $conn->query("SELECT SUM(quantity) as s FROM stock");
    $row = $res->fetch_assoc();
    echo $row['s'] ?? 0;
  ?></p></div>
  <div class="card"><h3>Low Stock Items</h3><p id="cardLowStock"><?php
    $res = $conn->query("SELECT COUNT(*) as l FROM stock WHERE status='Low'");
    $row = $res->fetch_assoc();
    echo $row['l'];
  ?></p></div>
</div>

<div class="toolbar">
  <div class="filter-bar">
    <input type="date" id="filterDate" />
    <input type="text" id="filterCategory" placeholder="Category" />
    <select id="filterStatus">
      <option value="">All Status</option>
      <option>In Stock</option>
      <option>Low</option>
      <option>Out of Stock</option>
    </select>
    <button class="btn light" id="btnFilter">Filter</button>
    <button class="btn light" id="btnReset">Reset</button>
  </div>
  <div style="flex:1"></div>
  <button class="btn secondary" id="btnAdd">➕ Add Part</button>
  <button class="btn" id="btnImportCsv">⬇ Import CSV</button>
  <input type="file" id="csvFileInput" accept=".csv" style="display:none;">
<button class="btn danger" id="btnDeleteSelected">🗑 Delete Selected</button>

</div>

<div class="table-wrap">
<table id="stockTable">
  <thead>
    <tr>
      <th><input type="checkbox" id="selectAll"></th> <!-- New select all checkbox -->
      <th>Part Number</th>
      <th>Last Updated</th>
      <th>Description</th>
      <th>Quantity</th>
      <th>Unit</th>
      <th>Category</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $res = $conn->query("SELECT * FROM stock ORDER BY date DESC");
    while($row = $res->fetch_assoc()){
        echo "<tr data-id='{$row['id']}'>
            <td><input type='checkbox' class='rowCheckbox'></td> <!-- New checkbox -->
            <td>{$row['partNumber']}</td>
            <td>{$row['date']}</td>
            <td>{$row['description']}</td>
            <td>{$row['quantity']}</td>
            <td>{$row['unit']}</td>
            <td>{$row['category']}</td>
            <td><span class='badge ".str_replace(' ','',$row['status'])."'>{$row['status']}</span></td>
            <td>
                <button class='edit'>✏ </button>
                <button class='del'>🗑 </button>
            </td>
        </tr>";
    }
    ?>
  </tbody>
</table>
</div>
</section>
</main>

<!-- Modal Add/Edit -->
<div class="modal-backdrop" id="modalBackdrop" style="display:none;">
  <div class="modal">
    <h2 id="modalTitle">Add Part</h2>
    <div class="form-grid">
      <input type="hidden" id="fId">
      <div><label>Part Number</label><input id="fPartNumber" type="text"></div>
      <div><label>Last Updated</label><input id="fDate" type="date"></div>
      <div class="full"><label>Description</label><input id="fDescription" type="text"></div>
      <div><label>Quantity</label><input id="fQuantity" type="number"></div>
      <div><label>Unit</label><input id="fUnit" type="text"></div>
      <div><label>Category</label><input id="fCategory" type="text"></div>
      <div>
        <label>Status</label>
        <select id="fStatus">
          <option>In Stock</option>
          <option>Low</option>
          <option>Out of Stock</option>
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
// Modal logic
const backdrop = document.getElementById('modalBackdrop');
let editingId = null;

document.getElementById('btnAdd').onclick = ()=> {
    editingId = null;
    document.getElementById('modalTitle').textContent = 'Add Part';
    document.getElementById('fId').value = '';
    document.getElementById('fPartNumber').value = '';
    document.getElementById('fDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('fDescription').value = '';
    document.getElementById('fQuantity').value = '';
    document.getElementById('fUnit').value = '';
    document.getElementById('fCategory').value = '';
    document.getElementById('fStatus').value = 'In Stock';
    backdrop.style.display = 'flex';
};

document.getElementById('btnCancel').onclick = ()=> backdrop.style.display='none';

document.getElementById('btnSave').onclick = async ()=> {
    const form = {
        id: document.getElementById('fId').value,
        partNumber: document.getElementById('fPartNumber').value,
        date: document.getElementById('fDate').value,
        description: document.getElementById('fDescription').value,
        quantity: document.getElementById('fQuantity').value,
        unit: document.getElementById('fUnit').value,
        category: document.getElementById('fCategory').value,
        status: document.getElementById('fStatus').value
    };

    // Validate Unit field (letters only)
const unit = document.getElementById('fUnit').value.trim();
if(!/^[A-Za-z]+$/.test(unit)){
    alert('Unit can contain letters only (no numbers or special characters).');
    return;
}


    const action = editingId ? 'edit' : 'add';
    const params = new URLSearchParams({...form, action});

    const res = await fetch('stock_crud.php', {method:'POST', body:params});
    const r = await res.json();
    if(r.success) location.reload();
    else alert('Error saving part');
};

// Attach Edit/Delete events
function attachRowEvents(){
    document.querySelectorAll('.edit').forEach(btn=>{
        btn.onclick = e=>{
            const tr = e.target.closest('tr');
            editingId = tr.dataset.id;
            document.getElementById('modalTitle').textContent = 'Edit Part';
            document.getElementById('fId').value = editingId;
            document.getElementById('fPartNumber').value = tr.children[0].textContent;
            document.getElementById('fDate').value = tr.children[1].textContent;
            document.getElementById('fDescription').value = tr.children[2].textContent;
            document.getElementById('fQuantity').value = tr.children[3].textContent;
            document.getElementById('fUnit').value = tr.children[4].textContent;
            document.getElementById('fCategory').value = tr.children[5].textContent;
            document.getElementById('fStatus').value = tr.children[6].textContent.trim();
            backdrop.style.display = 'flex';
        };
    });

    document.querySelectorAll('.del').forEach(btn=>{
        btn.onclick = e=>{
            const tr = e.target.closest('tr');
            const id = tr.dataset.id;
            if(confirm('Delete this part?')){
                fetch('stock_crud.php',{method:'POST', body: new URLSearchParams({action:'delete',id})})
                .then(r=>r.json()).then(r=>location.reload());
            }
        };
    });
}

// Attach initially
attachRowEvents();

// Filter
document.getElementById('btnFilter').onclick = () => {
    const date = document.getElementById('filterDate').value;
    const category = document.getElementById('filterCategory').value;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams({action:'filter', date, category, status});
    fetch('stock_crud.php',{method:'POST', body:params})
    .then(r=>r.json()).then(r=>{
        if(r.success){
            const tbody = document.querySelector('#stockTable tbody');
            tbody.innerHTML = '';
            r.data.forEach(row=>{
                const tr = document.createElement('tr');
                tr.dataset.id = row.id;
                tr.innerHTML = `
                    <td>${row.partNumber}</td>
                    <td>${row.date}</td>
                    <td>${row.description}</td>
                    <td>${row.quantity}</td>
                    <td>${row.unit}</td>
                    <td>${row.category}</td>
                    <td><span class='badge ${row.status.replace(/\s+/g,'')}'>${row.status}</span></td>
                    <td>
                        <button class='edit'>✏ Edit</button>
                        <button class='del'>🗑 Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            attachRowEvents();
        }
    });
};

// CSV Import
document.getElementById('btnImportCsv').onclick = () => document.getElementById('csvFileInput').click();

document.getElementById('csvFileInput').onchange = async (e) => {
    const file = e.target.files[0];
    if(!file) return;

    const formData = new FormData();
    formData.append('action', 'import_csv');
    formData.append('csvFile', file);

    try {
        const res = await fetch('stock_crud.php', {method:'POST', body: formData});
        const result = await res.json();

        if(result.success){
            alert(`CSV Imported successfully! ${result.imported} rows added.`);

            const tbody = document.querySelector('#stockTable tbody');
            
            // Append new rows
            result.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.dataset.id = row.id;
                tr.innerHTML = `
                    <td>${row.partNumber}</td>
                    <td>${row.date}</td>
                    <td>${row.description}</td>
                    <td>${row.quantity}</td>
                    <td>${row.unit}</td>
                    <td>${row.category}</td>
                    <td><span class='badge ${row.status.replace(/\s+/g,'')}'>${row.status}</span></td>
                    <td>
                        <button class='edit'>✏ </button>
                        <button class='del'>🗑 </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            attachRowEvents(); // Reattach edit/delete events
        } else {
            alert('Error importing CSV: ' + result.msg);
        }
    } catch(err){
        alert('Error importing CSV: ' + err.message);
    }

    // Reset file input
    e.target.value = '';
};

// Select All checkbox logic
const selectAll = document.getElementById('selectAll');
selectAll.addEventListener('change', () => {
    document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = selectAll.checked);
});

// Multi-delete button
document.getElementById('btnDeleteSelected').onclick = () => {
    const selectedIds = Array.from(document.querySelectorAll('.rowCheckbox:checked'))
                             .map(cb => cb.closest('tr').dataset.id);
    if(selectedIds.length === 0) {
        alert('No parts selected.');
        return;
    }
    if(confirm(`Delete ${selectedIds.length} selected part(s)?`)){
        const params = new URLSearchParams();
        params.append('action','delete_multiple');
        selectedIds.forEach(id => params.append('ids[]', id));

        fetch('stock_crud.php', {method:'POST', body:params})
            .then(r=>r.json())
            .then(r=> {
                if(r.success) location.reload();
                else alert('Error deleting parts.');
            });
    }
    
};



// Reset
document.getElementById('btnReset').onclick = ()=> location.reload();
</script>

</body>
</html>
