<?php
session_start();

// Dummy Login Handling untuk testing
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validasi sederhana
    if (!empty($email) && !empty($password) && !empty($role)) {
        $_SESSION['user_role'] = $role;
        $_SESSION['user_email'] = $email;
        $_SESSION['logged_in'] = true;
        
        // Redirect berdasarkan role
        switch($role) {
            case 'admin':
                header('Location: modules/admin/dashboard.php');
                exit;
            case 'kasir':
                header('Location: modules/kasir/dashboard.php');
                exit;
            case 'member':
                header('Location: modules/member/dashboard.php');
                exit;
            case 'customer':
                // Customer redirect ke menu order
                header('Location: modules/customer/menu.php');
                exit;
        }
    } else {
        $error = 'Email, Password, dan Role wajib diisi untuk login.';
    }
}

// Dummy Menu Data
$menus = [
    ['name' => 'Espresso', 'price' => '18K', 'icon' => '☕', 'desc' => 'Kopi pekat dengan aroma kuat.'],
    ['name' => 'Cappuccino', 'price' => '25K', 'icon' => '☕', 'desc' => 'Perpaduan espresso dan foam susu lembut.'],
    ['name' => 'Latte', 'price' => '28K', 'icon' => '🥛', 'desc' => 'Kopi susu yang creamy dan manis.'],
    ['name' => 'Matcha Latte', 'price' => '30K', 'icon' => '🍵', 'desc' => 'Teh hijau Jepang dengan susu.'],
    ['name' => 'Cinnamon Roll', 'price' => '22K', 'icon' => '🥐', 'desc' => 'Camilan manis beraroma kayu manis.'],
    ['name' => 'Croissant', 'price' => '20K', 'icon' => '🥐', 'desc' => 'Renyah di luar, lembut di dalam.'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Padud Coffee | Smart QR Ordering</title>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS Variables untuk Tema Navy & Cream */
        :root {
            --navy: #0B1F3B;
            --cream: #F5E6C8;
            --white: #FFFFFF;
            --gold: #D4AF37;
            --dark-gray: #333333;
            --light-gray: #f0f0f0;
            --transition: all 0.3s ease;
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background-color: var(--white);
            color: var(--dark-gray);
            line-height: 1.6;
            overflow-x: hidden;
        }
        a { text-decoration: none; }
        ul { list-style: none; }

        /* Typography */
        h1, h2, h3 { color: var(--navy); }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            outline: none;
            text-align: center;
            font-size: 1rem;
        }
        .btn-primary {
            background-color: var(--navy);
            color: var(--white);
        }
        .btn-primary:hover {
            background-color: #08162A;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(11, 31, 59, 0.25);
        }
        .btn-secondary {
            background-color: var(--cream);
            color: var(--navy);
        }
        .btn-secondary:hover {
            background-color: #E6D2AC;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(245, 230, 200, 0.5);
        }
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--navy);
            color: var(--navy);
        }
        .btn-outline:hover {
            background-color: var(--navy);
            color: var(--white);
        }

        /* Error Alert */
        .alert-error {
            background-color: #dc3545;
            color: white;
            text-align: center;
            padding: 12px;
            font-weight: 600;
        }

        /* Hero Section */
        .hero {
            background-color: var(--cream);
            padding: 120px 20px 80px;
            text-align: center;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 50px;
            position: relative;
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--navy);
            font-weight: 700;
        }
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            color: var(--dark-gray);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        /* Section Titles */
        .section-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 40px;
            position: relative;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background-color: var(--gold);
            margin: 10px auto 0;
            border-radius: 2px;
        }

        /* Role Access Section */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 80px;
        }
        .role-card {
            background-color: var(--white);
            border: 1px solid var(--light-gray);
            border-radius: 16px;
            padding: 35px 20px;
            text-align: center;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            display: block;
        }
        .role-card:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(11, 31, 59, 0.1);
            border-color: var(--navy);
        }
        .role-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .role-card h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--navy);
        }
        .role-card p {
            font-size: 0.9rem;
            color: #666;
        }

        /* Menu Preview (Horizontal Scroll) */
        .menu-section {
            margin-bottom: 80px;
        }
        .menu-scroll {
            display: flex;
            overflow-x: auto;
            gap: 20px;
            padding: 10px 10px 30px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }
        .menu-scroll::-webkit-scrollbar {
            height: 8px;
        }
        .menu-scroll::-webkit-scrollbar-track {
            background: #f1f1f1; 
            border-radius: 4px;
        }
        .menu-scroll::-webkit-scrollbar-thumb {
            background: var(--navy); 
            border-radius: 4px;
        }
        .menu-card {
            min-width: 220px;
            background: var(--white);
            border-radius: 16px;
            padding: 25px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            scroll-snap-align: start;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
            text-align: center;
        }
        .menu-card:hover {
            border-color: var(--gold);
            transform: translateY(-5px);
        }
        .menu-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .menu-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: var(--navy);
        }
        .menu-price {
            color: var(--gold);
            font-weight: 700;
            font-size: 1.2rem;
        }
        .menu-desc {
            font-size: 0.85rem;
            color: #777;
            margin-top: 10px;
        }

        /* QR Section */
        .qr-section {
            background-color: var(--navy);
            color: var(--white);
            padding: 70px 20px;
            text-align: center;
            border-radius: 24px;
            margin-bottom: 80px;
            position: relative;
            overflow: hidden;
        }
        .qr-section h2 {
            color: var(--white);
            margin-bottom: 15px;
            font-size: 2rem;
        }
        .qr-section p {
            margin-bottom: 40px;
            color: var(--cream);
            font-size: 1.1rem;
        }
        .qr-dummy {
            width: 180px;
            height: 180px;
            background-color: var(--white);
            margin: 0 auto;
            border-radius: 16px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            animation: pulse 2s infinite;
            box-shadow: 0 0 25px rgba(245, 230, 200, 0.4);
            color: var(--navy);
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 15px rgba(245, 230, 200, 0.2); }
            50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(245, 230, 200, 0.6); }
            100% { transform: scale(1); box-shadow: 0 0 15px rgba(245, 230, 200, 0.2); }
        }

        /* Modal Login */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 31, 59, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 420px;
            position: relative;
            transform: translateY(-30px);
            transition: var(--transition);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        .modal-close {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.8rem;
            cursor: pointer;
            color: #999;
            transition: var(--transition);
            line-height: 1;
        }
        .modal-close:hover {
            color: var(--navy);
            transform: scale(1.1);
        }
        .modal-content h2 {
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.8rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: var(--dark-gray);
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
            background-color: #fafafa;
        }
        .form-control:focus {
            border-color: var(--navy);
            background-color: var(--white);
            box-shadow: 0 0 0 4px rgba(11,31,59,0.05);
        }
        .form-error {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 6px;
            display: none;
            font-weight: 600;
        }

        /* Footer */
        footer {
            background-color: var(--navy);
            color: var(--white);
            text-align: center;
            padding: 40px 20px;
            margin-top: 40px;
        }
        .social-icons {
            margin-bottom: 20px;
            font-size: 1.8rem;
            letter-spacing: 20px;
            color: var(--cream);
        }
        .social-icons span {
            cursor: pointer;
            transition: var(--transition);
        }
        .social-icons span:hover {
            color: var(--gold);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.2rem; }
            .hero-buttons { flex-direction: column; }
            .btn { width: 100%; }
            .qr-dummy { width: 140px; height: 140px; font-size: 3rem; }
        }
    </style>
</head>
<body>

    <!-- Menampilkan pesan error dari PHP -->
    <?php if(!empty($error)): ?>
        <div class="alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- HERO SECTION -->
    <header class="hero">
        <div class="container">
            <h1>☕ Padud Coffee</h1>
            <p>Smart Coffee Ordering with QR Technology. Nikmati pengalaman memesan kopi yang cepat, praktis, dan modern.</p>
            <div class="hero-buttons">
                <a href="modules/customer/menu.php" class="btn btn-primary">Order Now</a>
                <button onclick="openModal()" class="btn btn-secondary">Login</button>
                <a href="modules/customer/register.php" class="btn btn-outline">Register</a>
            </div>
        </div>
    </header>

    <div class="container">
        
        <!-- ROLE ACCESS SECTION -->
        <section class="role-access">
            <h2 class="section-title">System Access</h2>
            <div class="role-grid">
                <a href="modules/admin/dashboard.php" class="role-card">
                    <div class="role-icon">🛡️</div>
                    <h3>Admin Panel</h3>
                    <p>Manajemen penuh sistem, menu, dan laporan keuangan.</p>
                </a>
                <a href="modules/kasir/dashboard.php" class="role-card">
                    <div class="role-icon">💻</div>
                    <h3>Kasir Dashboard</h3>
                    <p>Kelola pesanan dan proses transaksi secara real-time.</p>
                </a>
                <a href="modules/member/dashboard.php" class="role-card">
                    <div class="role-icon">⭐</div>
                    <h3>Member Area</h3>
                    <p>Lihat poin reward, riwayat pesanan, dan diskon eksklusif.</p>
                </a>
                <a href="modules/customer/menu.php" class="role-card">
                    <div class="role-icon">📱</div>
                    <h3>Customer Order</h3>
                    <p>Lihat menu dan lakukan pemesanan mandiri tanpa antre.</p>
                </a>
            </div>
        </section>

        <!-- MENU PREVIEW SECTION -->
        <section class="menu-section">
            <h2 class="section-title">Signature Menu</h2>
            <div class="menu-scroll">
                <?php foreach($menus as $menu): ?>
                <div class="menu-card">
                    <div class="menu-icon"><?php echo $menu['icon']; ?></div>
                    <div class="menu-name"><?php echo htmlspecialchars($menu['name']); ?></div>
                    <div class="menu-price"><?php echo htmlspecialchars($menu['price']); ?></div>
                    <p class="menu-desc"><?php echo htmlspecialchars($menu['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- QR ORDER SECTION -->
        <section class="qr-section">
            <h2>Order from your Table</h2>
            <p>Scan QR Code yang tersedia di meja Anda untuk mulai memesan makanan dan minuman dengan instan.</p>
            <div class="qr-dummy">
                🔳
            </div>
        </section>

    </div>

    <!-- FOOTER -->
    <footer>
        <div class="social-icons">
            <span>📷</span>
            <span>🐦</span>
            <span>📘</span>
        </div>
        <p>&copy; <?php echo date('Y'); ?> Padud Coffee. All rights reserved.</p>
    </footer>

    <!-- LOGIN MODAL (INLINE JAVASCRIPT) -->
    <div class="modal-overlay" id="loginModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2>Sign In</h2>
            <form id="loginForm" method="POST" action="" onsubmit="return validateForm()">
                <input type="hidden" name="login" value="1">
                
                <div class="form-group">
                    <label for="email">Email / Username</label>
                    <input type="text" id="email" name="email" class="form-control" placeholder="admin / kasir / email">
                    <div class="form-error" id="emailError">Email atau Username wajib diisi</div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••">
                    <div class="form-error" id="passwordError">Password wajib diisi</div>
                </div>

                <div class="form-group">
                    <label for="role">Masuk Sebagai</label>
                    <select id="role" name="role" class="form-control">
                        <option value="">-- Pilih Akses --</option>
                        <option value="admin">Admin</option>
                        <option value="kasir">Kasir</option>
                        <option value="member">Member</option>
                        <option value="customer">Customer</option>
                    </select>
                    <div class="form-error" id="roleError">Silakan pilih akses role terlebih dahulu</div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Login Sekarang</button>
            </form>
        </div>
    </div>

    <!-- Inline JavaScript -->
    <script>
        // Modal Element
        const modal = document.getElementById('loginModal');
        
        // Fungsi Membuka Modal
        function openModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Nonaktifkan scroll body
        }

        // Fungsi Menutup Modal
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto'; // Aktifkan scroll body
            // Reset form
            document.getElementById('loginForm').reset();
            document.querySelectorAll('.form-error').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.form-control').forEach(el => el.style.borderColor = '#eee');
        }

        // Tutup modal jika klik di luar area konten
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Validasi Form Login
        function validateForm() {
            let isValid = true;
            
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const role = document.getElementById('role');
            
            const emailErr = document.getElementById('emailError');
            const passwordErr = document.getElementById('passwordError');
            const roleErr = document.getElementById('roleError');

            // Reset tampilan error
            emailErr.style.display = 'none';
            passwordErr.style.display = 'none';
            roleErr.style.display = 'none';
            [email, password, role].forEach(el => el.style.borderColor = '#eee');

            // Validasi Email/Username
            if (email.value.trim() === '') {
                emailErr.style.display = 'block';
                email.style.borderColor = '#dc3545';
                isValid = false;
            }

            // Validasi Password
            if (password.value.trim() === '') {
                passwordErr.style.display = 'block';
                password.style.borderColor = '#dc3545';
                isValid = false;
            }

            // Validasi Role Select
            if (role.value === '') {
                roleErr.style.display = 'block';
                role.style.borderColor = '#dc3545';
                isValid = false;
            }

            return isValid; // Jika false, form tidak akan di-submit
        }
    </script>
</body>
</html>
