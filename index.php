<?php
// Padud Coffee Landing Page
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Padud Coffee - Selamat Datang</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f0c0b;
            --text-main: #fcf8f2;
            --text-muted: #a3958c;
            --accent-primary: #d4a373;
            --accent-hover: #faedcd;
            --glass-bg: rgba(25, 20, 18, 0.6);
            --glass-border: rgba(212, 163, 115, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Abstract Background Elements */
        .bg-element {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.5;
            animation: float 20s infinite ease-in-out alternate;
        }

        .bg-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #5c3a21, transparent 70%);
            top: -100px;
            left: -100px;
        }

        .bg-2 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, #8a5a44, transparent 70%);
            bottom: -150px;
            right: -150px;
            animation-delay: -5s;
        }

        .bg-3 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #d4a373, transparent 70%);
            top: 40%;
            left: 60%;
            opacity: 0.15;
            animation-delay: -10s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 50px) scale(1.1); }
            100% { transform: translate(-20px, 20px) scale(0.9); }
        }

        /* Hero Section */
        .hero {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }

        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 4rem 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: translateY(30px);
            opacity: 0;
            animation: fadeUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--accent-primary), #8a5a44);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--bg-color);
            box-shadow: 0 10px 25px rgba(212, 163, 115, 0.3);
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 600;
            line-height: 1.1;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, var(--accent-hover));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            font-size: 1.125rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-weight: 300;
        }

        /* Action Button */
        .btn-menu {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem 2.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--bg-color);
            background: linear-gradient(135deg, var(--accent-primary), #e9c46a);
            border-radius: 50px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
        }

        .btn-menu::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: all 0.6s ease;
        }

        .btn-menu:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(212, 163, 115, 0.3);
            background: linear-gradient(135deg, var(--accent-hover), var(--accent-primary));
        }

        .btn-menu:hover::before {
            left: 100%;
        }

        .btn-icon {
            transition: transform 0.3s ease;
        }

        .btn-menu:hover .btn-icon {
            transform: translateX(4px);
        }

        /* Navbar (Minimal) */
        nav {
            position: relative;
            z-index: 10;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 400;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--accent-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .glass-container { padding: 3rem 1.5rem; }
            .bg-1 { width: 300px; height: 300px; }
            .bg-2 { width: 400px; height: 400px; }
        }
    </style>
</head>
<body>

    <!-- Ambient Backgrounds -->
    <div class="bg-element bg-1"></div>
    <div class="bg-element bg-2"></div>
    <div class="bg-element bg-3"></div>

    <!-- Navigation -->
    <nav>
        <a href="#" class="nav-brand">Padud</a>
        <div class="nav-links">
            <a href="modules/customer/login.php" class="nav-link">Login</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="hero">
        <div class="glass-container">
            <div class="logo-placeholder">P</div>
            <h1>Padud Coffee</h1>
            <p class="subtitle">Nikmati racikan kopi terbaik dari biji pilihan. Temukan rasa yang sesuai dengan seleramu dan rasakan pengalaman ngopi yang tak terlupakan.</p>
            
            <a href="modules/customer/menu.php" class="btn-menu">
                Lihat Menu Kami
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>
    </main>

</body>
</html>
