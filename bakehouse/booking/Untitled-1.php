<?php
// DB connection
$host = "localhost";
$user = "root";      // XAMPP default
$pass = "";          // XAMPP default password is empty
$dbname = "booking_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle booking submission via AJAX (JSON fetch)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["action"]) && $data["action"] === "book_table") {
        $name     = trim(htmlspecialchars($data["name"]));
        $email    = trim(htmlspecialchars($data["email"]));
        $phone    = trim(htmlspecialchars($data["phone"]));
        $date     = $data["date"];
        $time     = $data["time"];
        $guests   = intval($data["guests"]);
        $requests = trim(htmlspecialchars($data["requests"]));

        // Generate next bookingId (BID100X…)
        $res = $conn->query("SELECT bookingId FROM bookings ORDER BY id DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $last = $res->fetch_assoc()["bookingId"];
            $num  = intval(substr($last, 3)) + 1;
            $bookingId = "BID" . str_pad($num, 4, "0", STR_PAD_LEFT);
        } else {
            $bookingId = "BID1001";
        }

        // Insert booking (status defaults to Pending)
        $stmt = $conn->prepare(
            "INSERT INTO bookings (bookingId, customerName, date, time, tableNumber, status) 
             VALUES (?,?,?,?,?,?)"
        );
        $status = "Pending";
        $stmt->bind_param("ssssss", $bookingId, $name, $date, $time, $guests, $status);

        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "msg" => "Booking successful!", "bookingId" => $bookingId]);
        } else {
            echo json_encode(["ok" => false, "msg" => "DB Error: " . $conn->error]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Golden Treat - Table Booking</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Poppins', sans-serif; background:#FFE8B7; margin:0; padding:0; }
    nav { position: fixed; top:20px; left:50%; transform:translateX(-50%);
          background: rgba(255,255,255,0.9); padding:10px 30px; border-radius:50px;
          box-shadow:0 10px 30px rgba(212,175,55,0.2); z-index:1000; }
    nav ul { list-style:none; display:flex; gap:30px; margin:0; padding:0; }
    nav a { text-decoration:none; color:#2C1810; font-weight:500; }
    .hero { height:50vh; display:flex; flex-direction:column; justify-content:center; align-items:center;
            background:linear-gradient(135deg,#D4AF37,#FFE5B4); color:#2C1810; text-align:center; }
    .booking-section { max-width:700px; margin:50px auto; padding:40px; background:#fff; border-radius:20px;
                       box-shadow:0 10px 30px rgba(212,175,55,0.2); }
    .booking-section h2 { text-align:center; margin-bottom:30px; color:#8B4513; }
    form { display:flex; flex-direction:column; gap:15px; }
    input, select, textarea { padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; }
    button { padding:12px; background:#D4AF37; border:none; border-radius:8px; color:white; font-size:1.1rem; cursor:pointer; }
    button:hover { background:#b58d2b; }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);
             justify-content:center; align-items:center; }
    .modal-content { background:#fff; padding:20px; border-radius:15px; text-align:center; max-width:400px; }
  </style>
</head>
<body>
<nav>
  <ul>
    <li><a href="#home">Home</a></li>
    <li><a href="#booking">Booking</a></li>
  </ul>
</nav>

<section class="hero" id="home">
  <h1>Book Your Table</h1>
  <p>Reserve your spot at Golden Treat Bakery</p>
</section>

<section class="booking-section" id="booking">
  <h2>Table Booking Form</h2>
  <form id="bookingForm">
    <input type="text" id="name" placeholder="Full Name" required>
    <input type="email" id="email" placeholder="Email" required>
    <input type="tel" id="phone" placeholder="Phone Number" required>
    <input type="date" id="date" required>
    <input type="time" id="time" required>
    <select id="guests" required>
      <option value="">Number of Guests</option>
      <?php for($i=1;$i<=20;$i++){ echo "<option value='$i'>$i</option>"; } ?>
    </select>
    <textarea id="requests" rows="4" placeholder="Special Requests (Optional)"></textarea>
    <button type="submit">Book Now</button>
  </form>
</section>

<div class="modal" id="bookingModal">
  <div class="modal-content">
    <h2>Booking Confirmation</h2>
    <p id="modalMsg"></p>
    <button onclick="document.getElementById('bookingModal').style.display='none'">OK</button>
  </div>
</div>

<script>
  const bookingForm = document.getElementById("bookingForm");
  const modal = document.getElementById("bookingModal");
  const modalMsg = document.getElementById("modalMsg");

  bookingForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = {
      action: "book_table",
      name: document.getElementById("name").value.trim(),
      email: document.getElementById("email").value.trim(),
      phone: document.getElementById("phone").value.trim(),
      date: document.getElementById("date").value,
      time: document.getElementById("time").value,
      guests: document.getElementById("guests").value,
      requests: document.getElementById("requests").value.trim()
    };

    const r = await fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
    const res = await r.json();
    modalMsg.textContent = res.ok ? res.msg + " (ID: " + res.bookingId + ")" : res.msg;
    modal.style.display = "flex";
    if (res.ok) bookingForm.reset();
  });
</script>
</body>
</html>
