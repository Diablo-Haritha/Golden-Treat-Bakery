<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Righteous&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg: #FFE8B7;
            --primary: #D4AF37;
            --secondary: #8B4513;
            --accent: #FFE5B4;
            --dark: #2C1810;
            --light: #FFF8F0;
            --white: #FFFFFF;
            --gradient-1: linear-gradient(135deg, #D4AF37, #FFE5B4);
            --gradient-2: linear-gradient(135deg, #8B4513, #D2691E);
            --shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
            --shadow-hover: 0 15px 40px rgba(212, 175, 55, 0.3);
            --radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Frosting background */
        .frosting-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            background: var(--gradient-1);
            animation: spreadFrosting 15s ease-in-out infinite;
            overflow: hidden;
        }

        @keyframes spreadFrosting {
            0% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
            50% { background: linear-gradient(225deg, #FFE5B4, #D2691E); }
            100% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
        }

        /* Sprinkle particles */
        .sprinkles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        .sprinkle {
            position: absolute;
            width: 8px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), #ff6f91);
            border-radius: 2px;
            opacity: 0.6;
            animation: fall 4s linear infinite;
        }

        @keyframes fall {
            0% { transform: translateY(-10vh) rotate(0deg); opacity: 0.6; }
            100% { transform: translateY(110vh) rotate(360deg); opacity: 0.2; }
        }

        /* Sprinkle burst for transitions */
        .sprinkle-burst {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            pointer-events: none;
            opacity: 0;
            overflow: hidden;
        }

        .sprinkle-burst.active {
            opacity: 1;
        }

        .burst-particle {
            position: absolute;
            width: 10px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), #ff6f91);
            border-radius: 3px;
            opacity: 0.8;
            animation: burst 0.8s ease-out forwards;
        }

        @keyframes burst {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0.8; }
            100% { transform: translate(calc(var(--x) * 1px), calc(var(--y) * 1px)) rotate(360deg); opacity: 0; }
        }

        /* Message styling */
        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white);
            color: var(--dark);
            padding: 12px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 4;
            font-size: 0.9rem;
            text-align: center;
            max-width: 90%;
            width: 300px;
            opacity: 0;
            animation: fadeInOut 3s ease forwards;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            10% { opacity: 1; transform: translateX(-50%) translateY(0); }
            90% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }

        .container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            justify-content: center;
            z-index: 2;
            position: relative;
        }

        /* Profile Card */
        .profile-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 15px;
            box-shadow: var(--shadow);
            flex: 1 1 400px;
            min-height: 700px;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: url('https://images.unsplash.com/photo-1606755962779-253bcd11e5c5?auto=format&fit=crop&w=900&q=80') center/cover no-repeat;
            border-radius: var(--radius);
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-family: 'Dancing Script', cursive;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.5);
        }

        /* Menu Button & Side Panel */
        .menu-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-1);
            border: none;
            padding: 8px 12px;
            border-radius: var(--radius);
            color: var(--white);
            cursor: pointer;
            font-size: 14px;
            z-index: 10;
            transition: transform 0.3s ease;
        }

        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .menu-btn::before {
            content: '⚙️';
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            opacity: 0;
            transition: left 0.3s ease, opacity 0.3s ease;
        }

        .menu-btn:hover::before {
            left: 8px;
            opacity: 1;
        }

        .side-panel, .settings-panel {
            position: fixed;
            top: 0;
            right: -100%;
            width: 300px;
            height: 100%;
            background: var(--white);
            transition: right 0.3s ease;
            padding: 15px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow: hidden;
            box-shadow: -5px 0 15px rgba(0,0,0,0.2);
        }

        .side-panel.active, .settings-panel.active {
            right: 0;
        }

        .side-panel h3, .settings-panel h3 {
            color: var(--secondary);
            font-family: 'Dancing Script', cursive;
            font-size: 1.6rem;
        }

        .side-panel a, .settings-panel a {
            text-decoration: none;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: var(--radius);
            transition: background 0.3s;
            font-weight: bold;
        }

        .side-panel a:hover, .settings-panel a:hover {
            background: var(--gradient-1);
            color: var(--white);
        }

        .close-btn {
            align-self: flex-end;
            background: var(--gradient-1);
            border: none;
            padding: 6px 10px;
            border-radius: var(--radius);
            color: var(--white);
            cursor: pointer;
            font-size: 12px;
            transition: transform 0.3s;
        }

        .close-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* Settings Panel Options */
        .settings-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px;
            border-radius: var(--radius);
            background: var(--light);
            color: var(--secondary);
        }

        .settings-option label {
            font-weight: bold;
        }

        .settings-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Profile Picture */
        .profile-picture {
            text-align: center;
            margin-bottom: 10px;
        }

        .profile-picture img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
            transition: transform 0.3s;
        }

        .profile-picture img:hover {
            transform: scale(1.05);
        }

        .profile-picture label {
            display: inline-block;
            margin-top: 8px;
            background: var(--gradient-1);
            color: var(--white);
            padding: 6px 10px;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 12px;
        }

        .profile-picture input {
            display: none;
        }

        /* Personal Info */
        .profile-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .input-box {
            flex: 1 1 45%;
            display: flex;
            flex-direction: column;
        }

        .input-box label {
            font-weight: bold;
            margin-bottom: 4px;
            color: var(--secondary);
        }

        .input-box input, select {
            padding: 5px;
            border-radius: var(--radius);
            border: 1px solid var(--accent);
            outline: none;
            font-size: 0.75rem;
            background: var(--light);
            transition: border-color 0.3s ease, transform 0.3s ease;
        }

        .input-box input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(212, 175, 55, 0.5);
            transform: scale(1.02);
        }

        /* Buttons */
        .btn {
            background: var(--gradient-1);
            color: var(--white);
            padding: 8px 15px;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            transition: transform 0.3s ease;
            margin-top: 10px;
            margin-right: 8px;
            align-self: center;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn::before {
            content: '🥐';
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            opacity: 0;
            transition: left 0.3s ease, opacity 0.3s ease;
        }

        .btn:hover::before {
            left: 8px;
            opacity: 1;
        }

        /* Orders */
        .orders {
            margin-top: 15px;
        }

        .orders h3 {
            color: var(--secondary);
            font-family: 'Dancing Script', cursive;
            font-size: 1.6rem;
            margin-bottom: 8px;
        }

        /* Scrollable Order History */
        .order-history {
            max-height: 150px;
            overflow-y: auto;
            margin-top: 8px;
            border: 2px solid var(--accent);
            border-radius: var(--radius);
            padding: 5px;
            background: var(--white);
        }

        .order-history p {
            color: var(--secondary);
            margin: 4px 0;
        }

        /* Analytics */
        .analytics {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .analytics .card {
            background: var(--light);
            flex: 1 1 45%;
            padding: 10px;
            border-radius: var(--radius);
            text-align: center;
            color: var(--secondary);
            font-weight: bold;
            transition: transform 0.3s;
        }

        .analytics .card:hover {
            transform: scale(1.03);
        }

        /* Social / Quick Actions */
        .social {
            margin-top: 15px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .social button {
            flex: 1 1 45%;
            padding: 8px;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            font-weight: bold;
            background: var(--gradient-1);
            color: var(--white);
            transition: transform 0.3s;
            font-size: 0.85rem;
        }

        .social button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            .input-box {
                flex: 1 1 100%;
            }
            .analytics .card {
                flex: 1 1 100%;
            }
            .social button {
                flex: 1 1 100%;
            }
            .side-panel, .settings-panel {
                width: 250px;
            }
            .profile-card {
                padding: 10px;
                min-height: 500px;
                max-height: 85vh;
            }
            .welcome-banner {
                font-size: 20px;
                height: 120px;
            }
            .input-box input, select {
                padding: 4px;
                font-size: 0.7rem;
            }
            .btn, .social button {
                padding: 6px;
                font-size: 0.8rem;
            }
            .orders h3 {
                font-size: 1.4rem;
            }
            .analytics .card {
                padding: 8px;
            }
            .message {
                width: 80%;
                font-size: 0.8rem;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Frosting background -->
    <div class="frosting-bg"></div>
    <!-- Sprinkle particles -->
    <div class="sprinkles"></div>
    <!-- Sprinkle burst for transition -->
    <div class="sprinkle-burst" id="sprinkle-burst"></div>

    <div class="container">
        <div class="profile-card">
            <button class="menu-btn" onclick="togglePanel()">__ Menu</button>
            <div class="welcome-banner"></div>

            <div class="profile-picture">
                <img id="profile-img" src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=140&q=80" alt="Profile Picture">
                <label for="profile-upload"><i class="fas fa-camera"></i> Change Photo
                    <input type="file" id="profile-upload" accept="image/*">
                </label>
            </div>

            <!-- Personal Info -->
            <form id="profile-form">
                <div class="profile-details">
                    <div class="input-box"><label>Full Name</label><input type="text" name="fullName" readonly></div>
                    <div class="input-box"><label>Email</label><input type="email" name="email" readonly></div>
                    <div class="input-box"><label>Mobile Number</label><input type="tel" name="mobile" readonly></div>
                    <div class="input-box"><label>Address</label><input type="text" name="address" readonly></div>
                    <div class="input-box"><label>District</label><input type="text" name="district" readonly></div>
                    <div class="input-box"><label>Date Joined</label><input type="text" name="dateJoined" readonly></div>
                </div>
                <div class="button-group">
                    <button type="button" class="btn" id="edit-btn" onclick="enableEdit()">Edit Profile</button>
                    <button type="submit" class="btn" id="update-btn" style="display: none;">Update Profile</button>
                </div>
            </form>

            <!-- Orders -->
            <div class="orders">
                <h3>Order History</h3>
                <div class="order-history" id="order-history"></div>
            </div>

            <!-- Analytics -->
            <div class="analytics">
                <div class="card">Days since last login: <span id="days-since"></span></div>
                <div class="card">Total Orders Placed: <span id="total-orders"></span></div>
                <div class="card">Total Amount Spent: <span id="total-spent"></span></div>
                <div class="card">Next Recommended Product: <span id="recommended-product"></span></div>
            </div>

            <!-- Social / Quick Actions -->
            <div class="social">
                <button><i class="fas fa-share-alt"></i> Share Profile</button>
                <button><i class="fas fa-star"></i> Reviews & Ratings</button>
                <button onclick="downloadInvoice()"><i class="fas fa-download"></i> Download Invoice</button>
            </div>
        </div>
    </div>

    <!-- Side Panel -->
    <div class="side-panel" id="sidePanel">
        <button class="close-btn" onclick="closePanel()"><i class="fas fa-times"></i> Close</button>
        <h3>Settings</h3>
        <a href="#" onclick="toggleSettingsPanel(); return false;"><i class="fas fa-user-cog"></i> Profile Settings</a>
        <a href="#" onclick="confirmLogout(); return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Profile Settings Sub-Panel -->
    <div class="settings-panel" id="settingsPanel">
        <button class="close-btn" onclick="closeSettingsPanel()"><i class="fas fa-times"></i> Close</button>
        <h3>Profile Settings</h3>
        <div class="settings-option">
            <label for="notifications">Enable Notifications</label>
            <input type="checkbox" id="notifications" checked>
        </div>
        <div class="settings-option">
            <label for="darkMode">Dark Mode</label>
            <input type="checkbox" id="darkMode">
        </div>
        <div class="settings-option">
            <label for="emailPrefs">Receive Promotional Emails</label>
            <input type="checkbox" id="emailPrefs" checked>
        </div>
        <a href="#" onclick="confirmDeleteAccount(); return false;"><i class="fas fa-trash-alt"></i> Delete Account</a>
    </div>

    <script>
        // Create sprinkle particles
        function createSprinkles() {
            const sprinkleContainer = document.querySelector('.sprinkles');
            for (let i = 0; i < 100; i++) {
                const sprinkle = document.createElement('div');
                sprinkle.className = 'sprinkle';
                sprinkle.style.left = Math.random() * 100 + '%';
                sprinkle.style.animationDelay = Math.random() * 4 + 's';
                sprinkle.style.animationDuration = (Math.random() * 2 + 3) + 's';
                sprinkleContainer.appendChild(sprinkle);
            }
        }

        // Create sprinkle burst for transitions
        function createSprinkleBurst() {
            const burstContainer = document.getElementById('sprinkle-burst');
            burstContainer.innerHTML = '';
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'burst-particle';
                const angle = Math.random() * 360;
                const distance = 50 + Math.random() * 100;
                particle.style.setProperty('--x', Math.cos(angle * Math.PI / 180) * distance);
                particle.style.setProperty('--y', Math.sin(angle * Math.PI / 180) * distance);
                particle.style.left = '50%';
                particle.style.top = '50%';
                particle.style.animationDelay = Math.random() * 0.2 + 's';
                burstContainer.appendChild(particle);
            }
            burstContainer.classList.add('active');
            setTimeout(() => {
                burstContainer.classList.remove('active');
                burstContainer.innerHTML = '';
            }, 800);
        }

        // Show in-page message
        function showMessage(text, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isError ? 'error' : 'success'}`;
            messageDiv.textContent = text;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
        }

        // Fetch user data and orders
        function fetchUserData() {
            $.ajax({
                url: 'profile_api.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Fetch user data response:', response);
                    if (response.status === 'success') {
                        const user = response.user;
                        document.querySelector('input[name="fullName"]').value = user.full_name || '';
                        document.querySelector('input[name="email"]').value = user.email || '';
                        document.querySelector('input[name="mobile"]').value = user.mobile || '';
                        document.querySelector('input[name="address"]').value = user.address || '';
                        document.querySelector('input[name="district"]').value = user.district || '';
                        document.querySelector('input[name="dateJoined"]').value = user.date_joined || '';
                        document.querySelector('.welcome-banner').textContent = `Welcome Back, ${user.full_name}!`;
                        if (user.profile_picture) {
                            document.getElementById('profile-img').src = user.profile_picture;
                        }

                        const orders = response.orders;
                        const orderHistory = document.getElementById('order-history');
                        orderHistory.innerHTML = orders.length > 0
                            ? orders.map(order => `<p>#${order.order_id} - ${order.product_name} - ${order.order_date} - ${order.status}</p>`).join('')
                            : '<p>No orders found.</p>';

                        document.getElementById('total-orders').textContent = response.total_orders || '0';
                        document.getElementById('total-spent').textContent = response.total_spent ? `$${response.total_spent}` : '$0';
                        document.getElementById('recommended-product').textContent = response.recommended_product || 'None';

                        // Calculate days since last login
                        const lastLogin = new Date(user.last_login || new Date());
                        const today = new Date();
                        const diffTime = Math.abs(today - lastLogin);
                        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                        document.getElementById('days-since').textContent = diffDays + ' day(s)';
                    } else {
                        showMessage(response.message, true);
                        console.error('Fetch error:', response.message);
                        if (response.message.includes('not logged in')) {
                            setTimeout(() => window.location.href = 'login.php', 3000);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Error fetching user data.', true);
                    console.error('AJAX error:', status, error, xhr.responseText);
                }
            });
        }

        // Enable editing of profile fields
        function enableEdit() {
            const inputs = document.querySelectorAll('#profile-form input:not([name="dateJoined"])');
            inputs.forEach(input => input.removeAttribute('readonly'));
            document.getElementById('edit-btn').style.display = 'none';
            document.getElementById('update-btn').style.display = 'inline-block';
        }

        // Update profile
        document.getElementById('profile-form').addEventListener('submit', function(event) {
            event.preventDefault();
            createSprinkleBurst();

            const formData = new FormData(this);
            const profileData = {
                action: 'update',
                fullName: formData.get('fullName'),
                email: formData.get('email'),
                mobile: formData.get('mobile'),
                address: formData.get('address'),
                district: formData.get('district')
            };

            if (!profileData.fullName || !profileData.email || !profileData.mobile || !profileData.address || !profileData.district) {
                showMessage('Please fill in all required fields.', true);
                return;
            }

            $.ajax({
                url: 'profile_api.php',
                type: 'POST',
                data: JSON.stringify(profileData),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    console.log('Update profile response:', response);
                    if (response.status === 'success') {
                        showMessage('Profile updated successfully!');
                        const inputs = document.querySelectorAll('#profile-form input:not([name="dateJoined"])');
                        inputs.forEach(input => {
                            input.setAttribute('readonly', 'true');
                            input.value = profileData[input.name];
                        });
                        document.getElementById('edit-btn').style.display = 'inline-block';
                        document.getElementById('update-btn').style.display = 'none';
                        document.querySelector('.welcome-banner').textContent = `Welcome Back, ${profileData.fullName}!`;
                    } else {
                        showMessage(response.message, true);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Error updating profile.', true);
                    console.error('Update error:', status, error, xhr.responseText);
                }
            });
        });

        // Profile picture upload
        document.getElementById('profile-upload').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) {
                showMessage('No file selected.', true);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload_picture');
            formData.append('profile_picture', file);

            $.ajax({
                url: 'profile_api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log('Profile picture upload response:', response);
                    if (response.status === 'success') {
                        document.getElementById('profile-img').src = response.profile_picture;
                        showMessage('Profile picture updated successfully!');
                    } else {
                        showMessage(response.message, true);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Error uploading profile picture.', true);
                    console.error('Upload error:', status, error, xhr.responseText);
                }
            });
        });

        // Side panel toggle
        function togglePanel() {
            createSprinkleBurst();
            document.getElementById('sidePanel').classList.toggle('active');
            document.getElementById('settingsPanel').classList.remove('active');
        }

        // Close side panel
        function closePanel() {
            createSprinkleBurst();
            document.getElementById('sidePanel').classList.remove('active');
        }

        // Toggle profile settings sub-panel
        function toggleSettingsPanel() {
            createSprinkleBurst();
            document.getElementById('settingsPanel').classList.toggle('active');
        }

        // Close profile settings sub-panel
        function closeSettingsPanel() {
            createSprinkleBurst();
            document.getElementById('settingsPanel').classList.remove('active');
        }

        // Confirm logout
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                $.ajax({
                    url: 'profile_api.php',
                    type: 'POST',
                    data: JSON.stringify({ action: 'logout' }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Logout response:', response);
                        if (response.status === 'success') {
                            showMessage('Logged out successfully!');
                            setTimeout(() => window.location.href = 'login.php', 3000);
                        } else {
                            showMessage(response.message, true);
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('Error logging out.', true);
                        console.error('Logout error:', status, error, xhr.responseText);
                    }
                });
            }
        }

        // Confirm account deletion
        function confirmDeleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                $.ajax({
                    url: 'profile_api.php',
                    type: 'POST',
                    data: JSON.stringify({ action: 'delete' }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Delete account response:', response);
                        if (response.status === 'success') {
                            showMessage('Account deleted successfully!');
                            setTimeout(() => window.location.href = 'login.php', 3000);
                        } else {
                            showMessage(response.message, true);
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('Error deleting account.', true);
                        console.error('Delete error:', status, error, xhr.responseText);
                    }
                });
            }
        }

        // Download invoice
        function downloadInvoice() {
            try {
                if (!window.jspdf || !window.jspdf.jsPDF) {
                    showMessage('Error: jsPDF library not loaded.', true);
                    return;
                }

                $.ajax({
                    url: 'profile_api.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Invoice data response:', response);
                        if (response.status === 'success') {
                            const { jsPDF } = window.jspdf;
                            const doc = new jsPDF();

                            const user = response.user;
                            const orders = response.orders;
                            const totalSpent = response.total_spent || '0';

                            doc.setFontSize(18);
                            doc.text('Golden Treat Invoice', 20, 20);

                            doc.setFontSize(12);
                            doc.text('Customer Details:', 20, 40);
                            doc.text(`Name: ${user.full_name || 'Unknown'}`, 20, 50);
                            doc.text(`Email: ${user.email || 'Unknown'}`, 20, 60);
                            doc.text(`Address: ${user.address || 'Unknown'}, ${user.district || 'Unknown'}`, 20, 70);
                            doc.text(`Date Joined: ${user.date_joined || 'Unknown'}`, 20, 80);

                            doc.text('Order History:', 20, 100);
                            const tableColumn = ['Order ID', 'Product', 'Date', 'Status'];
                            const tableRows = orders.map(order => [order.order_id, order.product_name, order.order_date, order.status]);
                            doc.autoTable({
                                startY: 110,
                                head: [tableColumn],
                                body: tableRows,
                                theme: 'grid',
                                styles: { fontSize: 10 },
                                headStyles: { fillColor: [212, 175, 55] },
                                margin: { left: 20, right: 20 }
                            });

                            doc.text(`Total Amount Spent: $${totalSpent}`, 20, doc.lastAutoTable.finalY + 20);

                            doc.setFontSize(10);
                            doc.text('Thank you for your business!', 20, doc.lastAutoTable.finalY + 40);
                            doc.text('Golden Treat', 20, doc.lastAutoTable.finalY + 50);

                            doc.save(`Invoice_${user.full_name}_${new Date().toISOString().split('T')[0]}.pdf`);
                        } else {
                            showMessage(response.message, true);
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('Error fetching data for invoice.', true);
                        console.error('Invoice error:', status, error, xhr.responseText);
                    }
                });
            } catch (error) {
                console.error('Error generating invoice:', error);
                showMessage('Error generating invoice.', true);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createSprinkles();
            fetchUserData();
        });
    </script>
</body>
</html>