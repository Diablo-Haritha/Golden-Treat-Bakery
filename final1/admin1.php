




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Professional Product Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Roboto',sans-serif;}
body{display:flex;min-height:100vh;background:#f0f2f5;color:#333;}

/* Sidebar */
.sidebar{
  width:220px;background:#1f1f1f;color:#ff7f50;flex-shrink:0;display:flex;flex-direction:column;padding:30px;
}
.sidebar h2{text-align:center;margin-bottom:40px;font-weight:700;font-size:24px;}
.sidebar a{
  color:#ff7f50;text-decoration:none;padding:12px 20px;margin-bottom:15px;border-radius:10px;display:block;font-weight:500;
  transition:0.3s;
}
.sidebar a:hover{background:#ff7f50;color:#1f1f1f;}

/* Main */
.main{flex:1;padding:30px;}

/* Form Card */
.card{
  background:#fff;border-radius:15px;padding:25px;margin-bottom:40px;box-shadow:0 8px 20px rgba(0,0,0,0.08);
  transition:0.3s;
}
.card:hover{box-shadow:0 12px 25px rgba(0,0,0,0.15);}
.card h3{margin-bottom:20px;color:#333;font-weight:600;}

input, select, textarea, button{
  width:100%;margin-bottom:15px;padding:12px;border-radius:8px;border:1px solid #ccc;font-size:15px;transition:0.3s;
}
input:focus, select:focus, textarea:focus{border-color:#ff7f50;outline:none;}
button{
  background:linear-gradient(90deg,#ff7f50,#ffb347);color:#fff;border:none;font-weight:600;cursor:pointer;font-size:16px;
}
button:hover{opacity:0.9;}

/* Image Preview */
#imgPreview{width:100px;height:100px;object-fit:cover;border-radius:10px;margin-bottom:15px;transition:0.3s;}
#imgPreview:hover{transform:scale(1.1);}

/* Table */
table{width:100%;border-collapse:separate;border-spacing:0 10px;}
thead th{background:#ff7f50;color:#fff;padding:15px;text-align:left;border-radius:10px;}
tbody tr{background:#fff;transition:0.3s;box-shadow:0 4px 10px rgba(0,0,0,0.05);}
tbody tr:hover{box-shadow:0 8px 15px rgba(0,0,0,0.1);}
td{padding:12px 15px;vertical-align:middle;}
td img{width:60px;height:60px;object-fit:cover;border-radius:8px;transition:0.3s;}
td img:hover{transform:scale(1.2);}
.action-btn{cursor:pointer;font-size:18px;margin-right:10px;transition:0.3s;}
.action-btn:hover{transform:scale(1.2);}
.edit{color:#28a745;}
.delete{color:#dc3545;}

/* Responsive */
@media(max-width:768px){
  body{flex-direction:column;}
  .sidebar{width:100%;flex-direction:row;overflow-x:auto;padding:10px;}
  .sidebar a{margin-right:10px;margin-bottom:0;}
}
</style>
</head>
<body>

<div class="sidebar">
  <h2>Admin Panel</h2>
  <a href="#">Dashboard</a>
  <a href="#">Products</a>
</div>

<div class="main">

  <div class="card">
    <h3>Add / Edit Product</h3>
    <form id="productForm" enctype="multipart/form-data">
      <input type="hidden" id="productId">
      <input type="text" id="title" placeholder="Product Title" required>
      <select id="category">
        <option value="food">Food</option>
        <option value="cake">Cake</option>
      </select>
      <textarea id="description" placeholder="Description"></textarea>
      <input type="number" id="price" placeholder="Price" step="0.01" required>
      <input type="text" id="specialOffer" placeholder="Special Offer">
      <input type="file" id="imgInput" accept="image/*">
      <img id="imgPreview" src="" alt="" style="display:none;">
      <button type="submit">Add Product</button>
    </form>
  </div>

  <div class="card">
    <h3>Product List</h3>
    <table id="productsTable">
      <thead>
        <tr>
          <th>Image</th>
          <th>Title</th>
          <th>Category</th>
          <th>Price</th>
          <th>Special Offer</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

</div>

<script>
let products = [];
let editingProductId = null;

const form = document.getElementById('productForm');
const tableBody = document.querySelector('#productsTable tbody');
const imgInput = document.getElementById('imgInput');
const imgPreview = document.getElementById('imgPreview');

imgInput.addEventListener('change', function(){
  const file = this.files[0];
  if(file){
    const reader = new FileReader();
    reader.onload = e => { imgPreview.src = e.target.result; imgPreview.style.display='block'; }
    reader.readAsDataURL(file);
  } else { imgPreview.src=''; imgPreview.style.display='none'; }
});

// Load products from database
async function loadProducts(){
  try {
    const response = await fetch('api.php?action=getProducts');
    const data = await response.json();
    if (Array.isArray(data)) {
      products = data;
      renderProducts();
    } else {
      console.error('Error loading products:', data.error);
      alert('Error loading products: ' + data.error);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error loading products: ' + error.message);
  }
}

function renderProducts(){
  tableBody.innerHTML = '';
  products.forEach((p)=>{
    const imageUrl = p.image_url || 'https://via.placeholder.com/60x60?text=No+Image';
    tableBody.innerHTML += `
      <tr>
        <td><img src="${imageUrl}" alt="${p.title}"></td>
        <td>${p.title}</td>
        <td>${p.category}</td>
        <td>$${parseFloat(p.price).toFixed(2)}</td>
        <td>${p.special_offer || '-'}</td>
        <td>
          <i class="fas fa-edit action-btn edit" onclick="editProduct(${p.id})"></i>
          <i class="fas fa-trash-alt action-btn delete" onclick="deleteProduct(${p.id})"></i>
        </td>
      </tr>
    `;
  });
}

function resetForm(){
  form.reset();
  imgPreview.src=''; imgPreview.style.display='none';
  form.querySelector('button').textContent='Add Product';
  editingProductId = null;
  document.getElementById('productId').value = '';
}

form.addEventListener('submit', async function(e){
  e.preventDefault();
  
  const formData = new FormData();
  formData.append('title', document.getElementById('title').value);
  formData.append('category', document.getElementById('category').value);
  formData.append('description', document.getElementById('description').value);
  formData.append('price', document.getElementById('price').value);
  formData.append('specialOffer', document.getElementById('specialOffer').value);
  
  if (imgInput.files[0]) {
    formData.append('image', imgInput.files[0]);
  }
  
  try {
    let url = 'api.php?action=addProduct';
    if (editingProductId) {
      url = 'api.php?action=updateProduct';
      formData.append('id', editingProductId);
    }
    
    const response = await fetch(url, {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert(result.message);
      resetForm();
      loadProducts(); // Reload products
    } else {
      alert('Error: ' + result.error);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error: ' + error.message);
  }
});

async function editProduct(id){
  try {
    const response = await fetch(`api.php?action=getProduct&id=${id}`);
    const product = await response.json();
    
    if (product.error) {
      alert('Error: ' + product.error);
      return;
    }
    
    editingProductId = id;
    document.getElementById('productId').value = id;
    document.getElementById('title').value = product.title;
    document.getElementById('category').value = product.category;
    document.getElementById('description').value = product.description || '';
    document.getElementById('price').value = product.price;
    document.getElementById('specialOffer').value = product.special_offer || '';
    
    if (product.image_url) {
      imgPreview.src = product.image_url;
      imgPreview.style.display = 'block';
    } else {
      imgPreview.src = '';
      imgPreview.style.display = 'none';
    }
    
    form.querySelector('button').textContent = 'Update Product';
  } catch (error) {
    console.error('Error:', error);
    alert('Error loading product: ' + error.message);
  }
}

async function deleteProduct(id){
  if(confirm('Are you sure you want to delete this product?')){
    try {
      const formData = new FormData();
      formData.append('id', id);
      
      const response = await fetch('api.php?action=deleteProduct', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert(result.message);
        loadProducts(); // Reload products
        resetForm();
      } else {
        alert('Error: ' + result.error);
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Error: ' + error.message);
    }
  }
}

// Load products when page loads
window.addEventListener('DOMContentLoaded', function() {
  loadProducts();
});
</script>

</body>
</html>
