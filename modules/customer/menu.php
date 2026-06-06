<?php
// ── Session & Auth (Padud Coffee Customer) ──────────────────────
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$tableToken  = htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8');
$tableNum    = htmlspecialchars($_GET['table'] ?? '', ENT_QUOTES, 'UTF-8');

$displayTableNumber = $tableNum; // Fallback to URL for UI testing
if (!empty($tableToken) && !empty($tableNum)) {
    $stmt = $conn->prepare("SELECT t.table_number FROM qr_codes q JOIN tables t ON q.table_id = t.id WHERE q.token = ? AND (t.id = ? OR t.table_number = ?)");
    if ($stmt) {
        $stmt->bind_param("sis", $tableToken, $tableNum, $tableNum);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $displayTableNumber = htmlspecialchars($row['table_number'], ENT_QUOTES, 'UTF-8');
            $tableNum = $displayTableNumber; // Ensure tableNum uses the nice format going forward
        }
        $stmt->close();
    }
}

$isLoggedIn  = isLoggedIn();
$user        = $isLoggedIn ? currentUser() : ['name' => 'Tamu', 'role' => 'guest'];
$isMember    = in_array($user['role'], ['member', 'admin', 'kasir'], true);
?>
<!DOCTYPE html>
<html class="dark" lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="description" content="Menu Padud Coffee – Temukan minuman dan makanan favorit Anda">
  <meta name="theme-color" content="#050B14">
  <title>Padud Coffee – Menu</title>

  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            display: ['"Outfit"', 'sans-serif'],
          },
          colors: {
            "navy-bg": "#050B14",
            "navy-card": "rgba(255, 255, 255, 0.03)",
            "navy-border": "rgba(255, 255, 255, 0.08)",
            "glass-bg": "rgba(255, 255, 255, 0.05)",
            "glass-border": "rgba(255, 255, 255, 0.1)",
            "primary": "#F5BD58",
            "primary-hover": "#FDE68A",
          },
          animation: {
            'blob': 'blob 10s infinite',
            'float': 'float 6s ease-in-out infinite',
            'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          },
          keyframes: {
            blob: {
              '0%': { transform: 'translate(0px, 0px) scale(1)' },
              '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
              '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
              '100%': { transform: 'translate(0px, 0px) scale(1)' },
            },
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-10px)' },
            }
          }
        }
      }
    }
  </script>

  <style>
    body {
      background-color: #050B14;
      color: #E2E8F0;
      min-height: 100dvh;
      overflow-x: hidden;
      /* Background ambient light */
      background-image: 
        radial-gradient(circle at 15% 50%, rgba(245, 189, 88, 0.04), transparent 25%),
        radial-gradient(circle at 85% 30%, rgba(56, 189, 248, 0.03), transparent 25%);
    }

    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Neo-Glassmorphism Utilities */
    .glass {
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    }
    .glass-strong {
      background: rgba(255, 255, 255, 0.07);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }
    
    /* Text Gradients */
    .text-gradient {
      background: linear-gradient(135deg, #FDE68A 0%, #F5BD58 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Animations */
    .slide-up { transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); }
    .slide-up.open { transform: translateY(0); }
    .slide-right { transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); }
    .slide-right.open { transform: translateX(0); }
    .scale-in { transform: scale(0.95) translateY(20px); opacity: 0; transition: all 0.4s cubic-bezier(0.32, 0.72, 0, 1); }
    .scale-in.open { transform: scale(1) translateY(0); opacity: 1; }
    
    .overlay { opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
    .overlay.open { opacity: 1; pointer-events: all; }

    /* Custom Inputs */
    .glass-input {
      background: rgba(255, 255, 255, 0.03) !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      color: white !important;
      transition: all 0.2s;
    }
    .glass-input:focus {
      background: rgba(255, 255, 255, 0.1) !important;
      border-color: #F5BD58 !important;
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(245, 189, 88, 0.2) !important;
    }
    .glass-input::placeholder { color: rgba(255, 255, 255, 0.4) !important; font-weight: 400 !important; }

    /* Chips */
    .chip {
      padding: 8px 18px;
      border-radius: 9999px;
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .chip-active {
      background: #F5BD58;
      color: #050B14;
      box-shadow: 0 0 20px rgba(245, 189, 88, 0.3);
      font-weight: 600;
    }
    .chip-idle {
      background: rgba(255, 255, 255, 0.05);
      color: #A0AEC0;
      border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .chip-idle:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }

    /* Cards */
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(20px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .card-anim { animation: cardIn 0.4s cubic-bezier(0.2, 0.8, 0.2, 1) both; }
    
    .menu-card {
      transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .menu-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
      border-color: rgba(245, 189, 88, 0.3);
    }

    /* Floating Pill Nav (Mobile) */
    .pill-nav-container {
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    /* Custom Radio */
    .pay-method-card { transition: all 0.2s; }
    .pay-method-card:has(input:checked) {
      background: rgba(245, 189, 88, 0.08);
      border-color: #F5BD58;
    }
    .pay-check-inner { transition: transform 0.2s; transform: scale(0); }
    .pay-method-card:has(input:checked) .pay-check { border-color: #F5BD58; }
    .pay-method-card:has(input:checked) .pay-check-inner { display: block; transform: scale(1); }

    /* Stepper */
    .step-line::after {
      content: ''; position: absolute; top: 50%; left: 100%; width: 100%; height: 2px;
      background: rgba(255,255,255,0.1); transform: translateY(-50%);
    }
    .step-line.done::after { background: #F5BD58; }
  </style>
</head>

<body class="antialiased pb-32">

<!-- Background Ambient Orbs -->
<div class="fixed inset-0 overflow-hidden pointer-events-none z-[-1]">
  <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/10 rounded-full blur-[120px] mix-blend-screen animate-blob"></div>
  <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-amber-500/10 rounded-full blur-[120px] mix-blend-screen animate-blob" style="animation-delay: 2s"></div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     TOP HEADER (Glassmorphism)
════════════════════════════════════════════════════════════════ -->
<header class="fixed top-0 left-0 w-full z-40 glass-strong border-t-0 border-l-0 border-r-0">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16 sm:h-20">
      
      <!-- Logo & Brand -->
      <a href="menu.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="flex items-center gap-3 group">
        <div class="w-11 h-11 rounded-2xl bg-white/5 flex items-center justify-center overflow-hidden border border-white/10 group-hover:bg-white/10 transition-all flex-shrink-0">
          <img src="../../assets/logo/logo.png" alt="Padud Coffee" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
          <span class="material-symbols-outlined text-primary text-[24px] hidden" style="font-variation-settings:'FILL' 1;">coffee</span>
        </div>
        <div class="flex flex-col">
          <span class="font-display font-bold text-lg leading-tight text-white tracking-wide">Padud<span class="text-primary">Coffee</span></span>
          <span class="text-[11px] text-white/50 font-medium tracking-widest uppercase">Specialty</span>
        </div>
      </a>
      
      <!-- Table Indicator (Header Badge) -->
      <?php if ($displayTableNumber): ?>
      <div class="hidden sm:flex items-center gap-2 bg-primary/10 border border-primary/20 rounded-full px-4 py-1.5 ml-4">
        <span class="material-symbols-outlined text-primary text-[18px]">table_restaurant</span>
        <span class="text-primary font-bold text-sm uppercase tracking-wide">Meja <?= htmlspecialchars($displayTableNumber ?? '') ?></span>
      </div>
      <?php endif; ?>

      <!-- Desktop Search -->
      <div class="hidden md:flex flex-1 max-w-md mx-8 relative group">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-white/40 group-focus-within:text-primary transition-colors text-[22px]">search</span>
        <input id="search-desk" class="w-full glass-input rounded-full py-3 pl-12 pr-4 text-sm font-medium tracking-wide" placeholder="Cari menu favoritmu..." type="text">
      </div>

      <!-- Right Actions -->
      <div class="flex items-center gap-3">
        <!-- Notification -->
        <button id="notif-btn" class="relative w-10 h-10 rounded-full glass flex items-center justify-center hover:bg-white/10 transition-colors">
          <span class="material-symbols-outlined text-white/80 text-[22px]" style="font-variation-settings:'FILL' 0;">notifications</span>
          <span id="notif-dot" class="<?= $isLoggedIn ? '' : 'hidden' ?> absolute top-2 right-2.5 w-2 h-2 bg-red-500 rounded-full border-2 border-[#050B14]"></span>
        </button>

        <?php if ($isLoggedIn): ?>
          <a href="history.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="hidden sm:flex w-10 h-10 rounded-full glass items-center justify-center hover:bg-white/10 transition-colors" aria-label="Riwayat">
            <span class="material-symbols-outlined text-white/80 text-[22px]">history</span>
          </a>
          <div class="hidden sm:flex items-center gap-2 glass rounded-full pl-2 pr-4 py-1.5 border-white/10">
            <div class="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center">
              <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings:'FILL' 1;">person</span>
            </div>
            <span class="font-medium text-sm text-white"><?= htmlspecialchars($user['name'] ?? '') ?></span>
          </div>
        <?php else: ?>
          <a href="login.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="hidden sm:flex items-center gap-2 glass rounded-full px-5 py-2 hover:bg-white/10 transition-colors text-sm font-semibold">
            Masuk
          </a>
          <a href="register.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="hidden sm:flex items-center gap-2 bg-primary text-navy-bg rounded-full px-5 py-2 hover:bg-primary-hover transition-colors text-sm font-bold shadow-[0_0_20px_rgba(245,189,88,0.3)]">
            Daftar
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<!-- ════════════════════════════════════════════════════════════════
     MAIN CONTENT WRAPPER
════════════════════════════════════════════════════════════════ -->
<div class="pt-24 sm:pt-28 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-start gap-8">

  <!-- ── DESKTOP SIDEBAR (lg+) ─────────────────────────────────── -->
  <aside class="hidden lg:flex flex-col w-64 flex-shrink-0 sticky top-28 h-[calc(100vh-8rem)] glass rounded-3xl p-5 gap-6 overflow-y-auto no-scrollbar pb-8">
    <!-- User Profile Area -->
    <div class="flex items-center gap-3 pb-5 border-b border-white/10">
      <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary/30 to-blue-500/30 flex items-center justify-center flex-shrink-0 border border-white/10">
        <span class="material-symbols-outlined text-white text-[24px]" style="font-variation-settings:'FILL' 1;">
          <?= $isLoggedIn ? 'person' : 'waving_hand' ?>
        </span>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-xs text-white/50 truncate"><?= $isLoggedIn ? 'Selamat datang,' : 'Halo, Guest!' ?></p>
        <h3 class="font-display font-bold text-white text-lg leading-tight mb-2">
          <?= $isLoggedIn ? htmlspecialchars($user['name'] ?? '') : 'Silakan Masuk' ?>
        </h3>
        <?php if ($displayTableNumber): ?>
        <div class="inline-flex items-center gap-1.5 bg-primary/10 border border-primary/30 rounded-lg px-2.5 py-1 w-max shadow-[0_0_10px_rgba(245,189,88,0.1)]">
          <span class="material-symbols-outlined text-primary text-[14px]">table_restaurant</span>
          <span class="text-primary text-xs font-bold uppercase tracking-wide">Meja <?= htmlspecialchars($displayTableNumber ?? '') ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex flex-col gap-2">
      <button onclick="scrollToTop()" class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-primary/10 text-primary font-semibold transition-colors">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings:'FILL' 1;">home</span> Beranda
      </button>
      <a href="history.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-white/70 hover:bg-white/5 hover:text-white font-medium transition-colors">
        <span class="material-symbols-outlined text-[20px]">history</span> Riwayat
      </a>
      <button onclick="openCartDrawer()" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-white/70 hover:bg-white/5 hover:text-white font-medium transition-colors">
        <span class="material-symbols-outlined text-[20px]">shopping_bag</span> Keranjang
        <span id="desk-cart-badge" class="hidden ml-auto bg-primary text-navy-bg text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
      </button>
      <?php if (!$isLoggedIn): ?>
      <a href="login.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-white/70 hover:bg-white/5 hover:text-white font-medium transition-colors mt-2">
        <span class="material-symbols-outlined text-[20px]">login</span> Masuk
      </a>
      <?php endif; ?>
    </nav>

    <!-- Member Promo -->
    <?php if (!$isMember): ?>
    <div class="mt-auto mb-2 relative flex-shrink-0 rounded-3xl overflow-hidden p-5 border border-primary/30 bg-gradient-to-br from-primary/10 to-transparent shadow-[0_0_20px_rgba(245,189,88,0.1)]">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/20 to-transparent z-0"></div>
      <div class="relative z-10">
        <div class="w-10 h-10 bg-primary/20 rounded-xl flex items-center justify-center text-xl mb-3 border border-primary/30">👑</div>
        <h4 class="font-display font-bold text-white mb-1">Jadi Member</h4>
        <p class="text-xs text-white/70 mb-4 leading-relaxed">Kumpulkan poin & nikmati diskon eksklusif.</p>
        <a href="register.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="block text-center w-full bg-primary text-navy-bg font-bold text-sm py-2.5 rounded-xl hover:bg-primary-hover transition-colors shadow-[0_0_15px_rgba(245,189,88,0.2)]">
          Daftar Gratis
        </a>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ── MAIN CONTENT AREA ─────────────────────────────────────── -->
  <main class="flex-1 min-w-0 pb-10">
    
    <!-- Mobile Search (hidden md+) -->
    <div class="md:hidden mb-8 relative group">
      <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-white/40 transition-colors group-focus-within:text-primary text-[22px]">search</span>
      <input id="search-mob" class="w-full glass-input rounded-2xl py-4 pl-12 pr-4 text-sm font-medium tracking-wide shadow-sm" placeholder="Cari kopi atau cemilan..." type="text">
    </div>

    <!-- Personalized Hero Greeting (Mobile/Tablet prominent) -->
    <div class="lg:hidden glass rounded-3xl p-6 mb-8 relative overflow-hidden">
      <div class="absolute top-0 right-0 w-32 h-32 bg-primary/10 rounded-full blur-[40px]"></div>
      <div class="flex items-center justify-between relative z-10">
        <div>
          <p class="text-white/60 text-sm font-medium mb-0.5 flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">waving_hand</span> <?= $isLoggedIn ? 'Selamat datang,' : 'Halo, Penikmat Kopi!' ?>
          </p>
          <h1 class="text-2xl font-display font-bold text-white tracking-wide mb-2.5">
            <?= $isLoggedIn ? htmlspecialchars($user['name'] ?? '') : 'Mau ngopi apa hari ini?' ?>
          </h1>
          <?php if ($displayTableNumber): ?>
          <div class="inline-flex items-center gap-2 bg-primary/10 border border-primary/30 rounded-full px-3 py-1.5 shadow-[0_0_15px_rgba(245,189,88,0.15)] backdrop-blur-sm">
            <span class="material-symbols-outlined text-primary text-[16px]">table_restaurant</span>
            <span class="text-primary text-xs font-bold tracking-wide uppercase">Meja <?= htmlspecialchars($displayTableNumber ?? '') ?></span>
          </div>
          <?php endif; ?>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary to-amber-600 flex items-center justify-center text-navy-bg shadow-[0_0_20px_rgba(245,189,88,0.4)]">
          <span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;"><?= $isLoggedIn ? 'coffee' : 'waving_hand' ?></span>
        </div>
      </div>
    </div>

    <!-- Member Promo Mobile -->
    <?php if (!$isMember): ?>
    <a href="register.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="lg:hidden block mb-8 relative rounded-3xl overflow-hidden p-5 border border-primary/20 bg-gradient-to-r from-primary/10 to-transparent">
      <div class="flex items-center gap-4 relative z-10">
        <div class="w-12 h-12 bg-primary/20 rounded-2xl flex items-center justify-center text-2xl border border-primary/30 flex-shrink-0">👑</div>
        <div class="flex-1">
          <h4 class="font-display font-bold text-white text-base">Member Padud Coffee</h4>
          <p class="text-xs text-white/70 mt-0.5">Diskon eksklusif menantimu!</p>
        </div>
        <span class="bg-primary text-navy-bg text-[10px] font-bold px-3 py-1.5 rounded-full">DAFTAR</span>
      </div>
    </a>
    <?php endif; ?>

    <!-- Horizontal Category Pills -->
    <div class="mb-8">
      <div id="cat-chips" class="flex gap-3 overflow-x-auto no-scrollbar pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 scroll-smooth">
        <div class="chip chip-idle w-20 h-10 animate-pulse flex-shrink-0"></div>
        <div class="chip chip-idle w-24 h-10 animate-pulse flex-shrink-0"></div>
        <div class="chip chip-idle w-16 h-10 animate-pulse flex-shrink-0"></div>
      </div>
    </div>

    <!-- Toolbar: Filter & Sort -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5 mb-8 bg-white/5 border border-white/10 p-4 rounded-3xl backdrop-blur-md">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-primary/20 text-primary flex items-center justify-center border border-primary/30 shadow-[0_0_15px_rgba(245,189,88,0.2)]">
          <span class="material-symbols-outlined text-[24px]">filter_list</span>
        </div>
        <div>
          <p class="text-[11px] text-white/50 font-bold uppercase tracking-widest mb-0.5">Kategori Aktif</p>
          <h2 class="font-display font-bold text-xl text-white tracking-wide" id="section-heading">Semua Menu</h2>
        </div>
      </div>
      
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <div class="flex-1 sm:flex-none relative glass-strong rounded-xl border border-white/20 hover:border-primary/50 transition-colors shadow-lg">
          <label class="absolute -top-2.5 left-4 bg-[#050B14] px-1.5 text-[10px] text-white/60 font-bold tracking-widest uppercase rounded">Urutkan</label>
          <select id="sort-select" class="appearance-none bg-transparent text-white text-sm font-semibold w-full pl-5 pr-12 py-3 focus:outline-none cursor-pointer">
            <option value="default" class="bg-navy-bg">Sesuai Kategori</option>
            <option value="price-asc" class="bg-navy-bg">Harga Termurah ↑</option>
            <option value="price-desc" class="bg-navy-bg">Harga Termahal ↓</option>
            <option value="rating" class="bg-navy-bg">Rating Tertinggi ⭐</option>
          </select>
          <span class="material-symbols-outlined text-primary text-[20px] absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none">swap_vert</span>
        </div>
        <div class="hidden sm:flex glass-strong rounded-xl p-1.5 gap-1.5 border border-white/20 shadow-lg">
          <button id="grid-btn" class="w-10 h-10 rounded-lg bg-primary/20 text-primary flex items-center justify-center transition-colors" title="Grid View">
            <span class="material-symbols-outlined text-[20px]">grid_view</span>
          </button>
          <button id="list-btn" class="w-10 h-10 rounded-lg text-white/50 hover:bg-white/10 hover:text-white flex items-center justify-center transition-colors" title="List View">
            <span class="material-symbols-outlined text-[20px]">view_list</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Menu Grid -->
    <div id="menu-grid" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4" role="list" aria-live="polite">
      <!-- Loading Skeleton -->
      <div class="glass rounded-3xl p-2 animate-pulse"><div class="w-full aspect-square bg-white/5 rounded-2xl mb-3"></div><div class="h-4 bg-white/5 rounded w-3/4 mb-2 mx-2"></div><div class="h-3 bg-white/5 rounded w-1/2 mx-2 mb-3"></div><div class="h-6 bg-white/5 rounded w-1/3 mx-2 mb-2"></div></div>
      <div class="glass rounded-3xl p-2 animate-pulse"><div class="w-full aspect-square bg-white/5 rounded-2xl mb-3"></div><div class="h-4 bg-white/5 rounded w-3/4 mb-2 mx-2"></div><div class="h-3 bg-white/5 rounded w-1/2 mx-2 mb-3"></div><div class="h-6 bg-white/5 rounded w-1/3 mx-2 mb-2"></div></div>
    </div>
    
  </main>
</div>

<!-- ════════════════════════════════════════════════════════════════
     MOBILE FLOATING PILL NAV (iOS Style)
════════════════════════════════════════════════════════════════ -->
<div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 w-[90%] max-w-[360px] lg:hidden">
  <div class="pill-nav-container rounded-[2rem] p-2 flex justify-between items-center px-4">
    <button onclick="scrollToTop()" class="flex flex-col items-center justify-center w-14 h-12 text-primary">
      <span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;">home</span>
    </button>
    
    <a href="history.php<?= $tableToken ? "?token={$tableToken}&table={$tableNum}" : '' ?>" class="flex flex-col items-center justify-center w-14 h-12 text-white/50 hover:text-white transition-colors">
      <span class="material-symbols-outlined text-[24px]">history</span>
    </a>
    
    <div class="relative -top-6">
      <button onclick="openCartDrawer()" class="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-amber-600 flex items-center justify-center text-navy-bg shadow-[0_8px_20px_rgba(245,189,88,0.4)] hover:scale-105 active:scale-95 transition-transform border-4 border-[#050B14]">
        <span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;">shopping_bag</span>
        <span id="mob-cart-badge" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full border-2 border-[#050B14] flex items-center justify-center">0</span>
      </button>
    </div>
    
    <button class="flex flex-col items-center justify-center w-14 h-12 text-white/50 hover:text-white transition-colors" onclick="toggleFavOnly()">
      <span class="material-symbols-outlined text-[24px]">favorite</span>
    </button>
    
    <a href="<?= $isLoggedIn ? '#' : "login.php" . ($tableToken ? "?token={$tableToken}&table={$tableNum}" : '') ?>" class="flex flex-col items-center justify-center w-14 h-12 text-white/50 hover:text-white transition-colors">
      <span class="material-symbols-outlined text-[24px]"><?= $isLoggedIn ? 'person' : 'login' ?></span>
    </a>
  </div>
</div>

<!-- Desktop Floating Cart Button (shows only when cart has items) -->
<div id="cart-float" class="hidden fixed bottom-8 right-8 z-40 lg:block">
  <button id="cart-float-btn" class="glass-strong rounded-2xl p-4 flex items-center gap-4 hover:bg-white/10 transition-colors shadow-[0_10px_40px_rgba(0,0,0,0.5)] group border border-primary/20">
    <div class="w-12 h-12 rounded-xl bg-primary/20 flex items-center justify-center relative border border-primary/30 group-hover:scale-105 transition-transform">
      <span class="material-symbols-outlined text-[24px] text-primary">shopping_bag</span>
      <span id="cart-float-count" class="absolute -top-2 -right-2 w-6 h-6 bg-primary text-navy-bg text-[12px] font-bold rounded-full flex items-center justify-center shadow-lg">0</span>
    </div>
    <div class="flex flex-col items-start pr-4">
      <span class="font-display font-bold text-white text-sm">Keranjang</span>
      <span id="cart-float-total" class="text-lg font-bold text-primary">Rp 0</span>
    </div>
  </button>
</div>

<!-- ════════════════════════════════════════════════════════════════
     SWIPE-UP CART DRAWER (Bottom Sheet)
════════════════════════════════════════════════════════════════ -->
<div id="cart-overlay" class="overlay fixed inset-0 z-[300] bg-black/60 backdrop-blur-sm" onclick="closeCartDrawer()"></div>
<div id="cart-drawer" class="slide-up fixed left-0 right-0 bottom-0 z-[301] glass-strong rounded-t-[2.5rem] max-h-[90vh] flex flex-col shadow-[0_-20px_60px_rgba(0,0,0,0.5)] max-w-3xl mx-auto border-b-0 border-x border-t border-white/10 bg-[#0A1220]/90">
  
  <div class="w-12 h-1.5 bg-white/20 rounded-full mx-auto mt-4 flex-shrink-0"></div>
  
  <div class="flex items-center justify-between px-6 py-5 border-b border-white/5 flex-shrink-0">
    <h3 class="font-display font-bold text-xl text-white flex items-center gap-3">
      Keranjang Pesanan
    </h3>
    <button onclick="closeCartDrawer()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-colors text-white">
      <span class="material-symbols-outlined text-[18px]">close</span>
    </button>
  </div>
  
  <div id="cart-body" class="flex-1 overflow-y-auto no-scrollbar p-6">
    <div id="cart-empty" class="flex flex-col items-center justify-center py-16 text-center h-full opacity-50">
      <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center text-5xl mb-6">🛒</div>
      <p class="font-display font-bold text-xl text-white mb-2">Keranjang Kosong</p>
      <p class="text-sm text-white/60">Yuk pilih minuman favoritmu sekarang!</p>
    </div>
    <div id="cart-items" class="flex flex-col gap-4"></div>
  </div>
  
  <div id="cart-footer" class="hidden p-6 glass-strong border-t border-white/5 flex-shrink-0 rounded-t-3xl mt-[-20px] relative z-10 bg-[#0A1220]/95">
    <div class="mb-4">
      <input id="cart-notes" type="text" placeholder="📝 Catatan pesanan (opsional)" class="w-full glass-input rounded-xl px-4 py-3 text-sm">
    </div>
    
    <div id="cd-summary" class="mb-5 bg-white/5 rounded-2xl p-4 border border-white/5"></div>
    
    <?php if (!$tableNum): ?>
      <input id="customer-name" type="text" placeholder="Nama Pemesan (Wajib)" class="w-full glass-input rounded-xl px-4 py-3 text-sm mb-5 font-bold" value="<?= $isLoggedIn ? htmlspecialchars($user['name'] ?? '') : '' ?>">
    <?php endif; ?>
    
    <button id="checkout-btn" class="w-full bg-primary text-navy-bg font-bold text-lg py-4 rounded-2xl flex items-center justify-center gap-2 shadow-[0_0_20px_rgba(245,189,88,0.3)] hover:bg-primary-hover active:scale-[.98] transition-all relative overflow-hidden group">
      <div class="absolute inset-0 bg-white/20 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></div>
      Lanjut ke Pembayaran <span class="material-symbols-outlined">arrow_forward</span>
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     CHECKOUT MODAL (Receipt Style)
════════════════════════════════════════════════════════════════ -->
<div id="payment-overlay" class="overlay fixed inset-0 z-[400] bg-black/70 backdrop-blur-md flex items-center justify-center p-4 sm:p-6" onclick="e=>{if(e.target===this)closePayment();}">
  <div id="payment-box" class="scale-in w-full max-w-lg bg-[#0A1220] rounded-[2rem] border border-white/10 flex flex-col shadow-[0_20px_60px_rgba(0,0,0,0.6)] overflow-hidden max-h-[90vh]">
    
    <!-- Header Back -->
    <div class="flex items-center justify-between p-5 border-b border-white/5 bg-white/5" id="pay-back-bar">
      <button onclick="closePayment()" class="flex items-center gap-2 text-sm text-white/70 hover:text-white transition-colors font-medium">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span> Kembali
      </button>
      <span class="text-xs font-bold tracking-widest uppercase text-primary/80" id="pay-step-label">Step 1</span>
    </div>

    <!-- Stepper Graphic -->
    <div class="flex items-center justify-center gap-2 p-6 pb-2">
      <div id="step1-dot" class="w-8 h-8 rounded-full bg-primary text-navy-bg flex items-center justify-center font-bold text-sm shadow-[0_0_15px_rgba(245,189,88,0.3)]">1</div>
      <div id="step-line-1" class="w-12 h-1 rounded-full bg-white/10"></div>
      <div id="step2-dot" class="w-8 h-8 rounded-full bg-white/10 text-white/50 flex items-center justify-center font-bold text-sm">2</div>
      <div id="step-line-2" class="w-12 h-1 rounded-full bg-white/10"></div>
      <div id="step3-dot" class="w-8 h-8 rounded-full bg-white/10 text-white/50 flex items-center justify-center font-bold text-sm">3</div>
    </div>

    <div class="flex-1 overflow-y-auto no-scrollbar p-6 pt-4 relative">
      
      <!-- STEP 1: Order Summary (Receipt look) -->
      <div id="pay-step-1" class="space-y-6">
        <div class="bg-white/5 rounded-2xl p-5 border border-white/5 border-dashed relative">
          <div class="absolute -left-2 top-1/2 w-4 h-4 rounded-full bg-[#0A1220] border-r border-white/5"></div>
          <div class="absolute -right-2 top-1/2 w-4 h-4 rounded-full bg-[#0A1220] border-l border-white/5"></div>
          
          <h3 class="font-display font-bold text-white text-lg mb-4 text-center">Ringkasan Pesanan</h3>
          <div id="pay-order-items" class="space-y-3 mb-4 border-b border-white/10 border-dashed pb-4"></div>
          <div id="pay-summary" class="space-y-2"></div>
        </div>
        
        <?php if ($tableNum && $displayTableNumber): ?>
        <div class="flex items-center gap-3 bg-primary/10 border border-primary/20 rounded-xl px-5 py-4">
          <span class="material-symbols-outlined text-primary text-[24px]">table_restaurant</span>
          <div>
            <p class="text-xs text-primary/70 uppercase tracking-wider">Makan di Tempat</p>
            <p class="font-bold text-white text-lg">Meja <?= htmlspecialchars($displayTableNumber ?? '') ?></p>
          </div>
        </div>
        <?php else: ?>
        <div>
          <label class="block text-xs text-white/50 uppercase tracking-wider mb-2 pl-1">Nama Pengambil</label>
          <input id="pay-customer-name" type="text" class="w-full glass-input rounded-xl px-4 py-3 font-bold" value="<?= $isLoggedIn ? htmlspecialchars($user['name'] ?? '') : '' ?>">
        </div>
        <?php endif; ?>
        
        <button onclick="placeOrder()" id="place-order-btn" class="w-full bg-primary text-navy-bg font-bold text-lg py-4 rounded-2xl shadow-[0_0_20px_rgba(245,189,88,0.3)] hover:bg-primary-hover active:scale-[.98] transition-all">
          Buat Pesanan
        </button>
      </div>

      <!-- STEP 2: Payment Selection -->
      <div id="pay-step-2" class="hidden space-y-6">
        <div class="text-center">
          <h3 class="font-display font-bold text-white text-2xl mb-1">Pilih Pembayaran</h3>
          <p class="text-sm text-white/50">ID: <span id="pay-order-number" class="text-white font-mono"></span></p>
        </div>

        <div class="bg-primary/10 border border-primary/20 rounded-2xl p-5 text-center">
          <p class="text-xs text-primary/70 uppercase tracking-wider mb-1">Total Tagihan</p>
          <p id="pay-final-total" class="font-display font-bold text-3xl text-primary">Rp 0</p>
        </div>

        <div class="space-y-3">
          <label class="pay-method-card flex items-center gap-4 glass rounded-2xl p-4 cursor-pointer border border-white/5 hover:bg-white/10">
            <input type="radio" name="pay_method" value="cash" class="hidden" checked>
            <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center text-2xl">💵</div>
            <div class="flex-1">
              <p class="font-bold text-white">Bayar Tunai</p>
              <p class="text-xs text-white/50">Bayar di Kasir</p>
            </div>
            <div class="pay-check w-6 h-6 rounded-full border-2 border-white/20 flex items-center justify-center">
              <div class="w-3 h-3 rounded-full bg-primary hidden pay-check-inner"></div>
            </div>
          </label>

          <label class="pay-method-card flex items-center gap-4 glass rounded-2xl p-4 cursor-pointer border border-white/5 hover:bg-white/10">
            <input type="radio" name="pay_method" value="midtrans" class="hidden">
            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center text-2xl">💳</div>
            <div class="flex-1">
              <p class="font-bold text-white">Bayar Online</p>
              <p class="text-xs text-white/50">QRIS, e-Wallet, Card</p>
            </div>
            <div class="pay-check w-6 h-6 rounded-full border-2 border-white/20 flex items-center justify-center">
              <div class="w-3 h-3 rounded-full bg-primary hidden pay-check-inner"></div>
            </div>
          </label>
          
          <label class="pay-method-card flex items-center gap-4 glass rounded-2xl p-4 cursor-pointer border border-white/5 hover:bg-white/10">
            <input type="radio" name="pay_method" value="transfer" class="hidden">
            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-2xl">🏦</div>
            <div class="flex-1">
              <p class="font-bold text-white">Transfer Manual</p>
              <p class="text-xs text-white/50">Upload Bukti</p>
            </div>
            <div class="pay-check w-6 h-6 rounded-full border-2 border-white/20 flex items-center justify-center">
              <div class="w-3 h-3 rounded-full bg-primary hidden pay-check-inner"></div>
            </div>
          </label>
        </div>

        <div id="upload-proof-section" class="hidden">
          <label class="flex flex-col items-center gap-3 border-2 border-dashed border-white/20 rounded-2xl p-6 cursor-pointer hover:border-primary/50 transition-colors bg-white/5">
            <span class="material-symbols-outlined text-4xl text-white/40">cloud_upload</span>
            <span class="text-sm font-medium text-white/70">Tap untuk upload bukti transfer</span>
            <input type="file" id="proof-file" accept="image/*" class="hidden">
          </label>
          <div id="proof-preview" class="hidden mt-3">
            <img id="proof-img" class="w-full rounded-xl h-40 object-cover border border-white/10" src="">
          </div>
        </div>

        <button id="confirm-pay-btn" onclick="confirmPayment()" class="w-full bg-primary text-navy-bg font-bold text-lg py-4 rounded-2xl shadow-[0_0_20px_rgba(245,189,88,0.3)] hover:bg-primary-hover active:scale-[.98] transition-all">
          Konfirmasi Bayar
        </button>
      </div>

      <!-- STEP 3: Done -->
      <div id="pay-step-3" class="hidden text-center py-8 space-y-6">
        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center text-5xl mx-auto shadow-[0_0_30px_rgba(52,211,153,0.4)]">
          <span class="material-symbols-outlined text-white text-[48px]">check</span>
        </div>
        <div>
          <h3 class="font-display font-bold text-white text-3xl mb-2">Berhasil!</h3>
          <p class="text-white/60 mb-1">Nomor Pesanan</p>
          <p id="done-order-number" class="font-mono text-xl font-bold text-primary tracking-widest bg-primary/10 inline-block px-4 py-1 rounded-lg"></p>
          <p id="done-pay-method" class="text-sm text-white/40 mt-4 uppercase tracking-widest"></p>
        </div>
        
        <div class="space-y-3 pt-4 border-t border-white/10">
          <a id="done-receipt-link" href="#" class="block w-full glass-strong text-white font-bold py-4 rounded-xl border border-white/20 hover:bg-white/10 transition-colors">
            Lihat Struk
          </a>
          <button onclick="closePayment(); clearCartUI();" class="block w-full text-white/50 hover:text-white font-medium py-3 transition-colors">
            Tutup & Kembali
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════
     DETAIL MENU MODAL (Glassmorphism)
════════════════════════════════════════════════════════════════ -->
<div id="modal-overlay" class="overlay fixed inset-0 z-[500] bg-black/60 backdrop-blur-md flex items-center justify-center p-4" onclick="e=>{if(e.target===this)closeModal();}">
  <div id="modal-box" class="scale-in w-full max-w-sm bg-[#0A1220] rounded-[2rem] border border-white/10 shadow-[0_20px_60px_rgba(0,0,0,0.6)] overflow-hidden">
    
    <div class="relative w-full aspect-square bg-white/5">
      <div id="modal-ph" class="w-full h-full flex items-center justify-center text-8xl opacity-50">☕</div>
      <img id="modal-img" class="hidden w-full h-full object-cover" alt="">
      
      <!-- Gradient overlay for text readability -->
      <div class="absolute inset-0 bg-gradient-to-t from-[#0A1220] via-transparent to-transparent"></div>
      
      <button onclick="closeModal()" class="absolute top-4 right-4 w-10 h-10 bg-black/50 backdrop-blur-md rounded-full flex items-center justify-center text-white hover:bg-black/70 transition-colors border border-white/20">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
      
      <div id="modal-rating-badge" class="hidden absolute bottom-4 right-4 bg-black/60 backdrop-blur-md rounded-full px-3 py-1.5 flex items-center gap-1.5 border border-white/10">
        <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings:'FILL' 1;">star</span>
        <span id="modal-rating-val" class="text-sm font-bold text-white"></span>
      </div>
    </div>

    <div class="p-6 relative -mt-6 bg-[#0A1220] rounded-t-3xl">
      <div class="w-10 h-1.5 bg-white/20 rounded-full mx-auto mb-5"></div>
      
      <p id="modal-cat" class="text-[10px] font-bold text-primary/80 uppercase tracking-widest mb-1"></p>
      <h2 id="modal-name" class="font-display font-bold text-2xl text-white mb-2 leading-tight"></h2>
      <p id="modal-price" class="font-display font-bold text-xl text-primary mb-4"></p>
      <p id="modal-desc" class="text-sm text-white/60 leading-relaxed mb-6"></p>
      
      <div class="space-y-4 mb-6">
        <label class="block text-xs font-bold text-white/50 uppercase tracking-widest pl-1">Catatan</label>
        <textarea id="modal-notes" rows="2" placeholder="Contoh: less sugar..." class="w-full glass-input rounded-xl px-4 py-3 text-sm resize-none"></textarea>
      </div>
      
      <div class="flex items-center gap-4">
        <div class="flex items-center bg-white/5 border border-white/10 rounded-2xl p-1.5">
          <button id="mqb-minus" class="w-10 h-10 rounded-xl bg-white/5 text-white flex items-center justify-center font-bold text-xl hover:bg-white/10 active:scale-95 transition-all">−</button>
          <span id="mqv" class="font-bold text-white w-10 text-center">1</span>
          <button id="mqb-plus" class="w-10 h-10 rounded-xl bg-primary/20 text-primary flex items-center justify-center font-bold text-xl hover:bg-primary/30 active:scale-95 transition-all">+</button>
        </div>
        <button id="modal-add-btn" class="flex-1 bg-primary text-navy-bg font-bold py-4 rounded-2xl shadow-[0_0_20px_rgba(245,189,88,0.3)] hover:bg-primary-hover active:scale-[.98] transition-all">
          Tambah
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Notification Modal (Glassmorphism) -->
<div id="notif-bg" class="overlay fixed inset-0 z-[600] bg-black/40 backdrop-blur-sm" onclick="closeNotif()"></div>
<div id="notif-panel" class="slide-right fixed top-0 right-0 bottom-0 z-[601] w-full sm:w-96 glass-strong border-l border-white/10 flex flex-col bg-[#050B14]/80">
  <div class="flex items-center justify-between p-6 border-b border-white/10 bg-white/5">
    <h3 class="font-display font-bold text-xl text-white flex items-center gap-2">
      <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1;">notifications</span> Notifikasi
    </h3>
    <button onclick="closeNotif()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 text-white transition-colors">
      <span class="material-symbols-outlined text-[18px]">close</span>
    </button>
  </div>
  <div id="notif-body" class="flex-1 overflow-y-auto no-scrollbar p-4 space-y-3"></div>
</div>

<!-- Toast Container -->
<div id="toast-wrap" class="fixed z-[700] top-24 right-4 sm:right-8 flex flex-col gap-3 pointer-events-none items-end"></div>

<!-- ════════════════════════════════════════════════════════════════
     JAVASCRIPT LOGIC
════════════════════════════════════════════════════════════════ -->
<script>
const API   = '../../api';
const IMGS  = '../../assets/menu';
const TOKEN = '<?= $tableToken ?>';
const TABLE = '<?= $tableNum ?>';
const LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const USER_NAME = '<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>';

let allMenus=[], allCats=[], filtered=[];
let activeCat=0, sortMode='default', searchQ='', viewMode='grid';
let cart={items:[],item_count:0,subtotal:0};
let favs=JSON.parse(localStorage.getItem('pc_favs')||'{}');
let modalMenu=null, modalQty=1;
let currentOrderId=null, currentOrderNumber=null, currentTrxId=null;

/* Helpers */
const CAT_ICONS={kopi:'☕',coffee:'☕','non-kopi':'🥤',tea:'🍵',teh:'🍵',makanan:'🍽️',food:'🍽️',snack:'🍿',minuman:'🧃',drink:'🧃',dessert:'🍮',pastry:'🥐',cake:'🎂',es:'🧊',juice:'🍹'};
function catIcon(n=''){const k=n.toLowerCase();for(const[w,v]of Object.entries(CAT_ICONS))if(k.includes(w))return v;return'✨';}
function fmt(n){return Number(n).toLocaleString('id-ID');}
function esc(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

/* Init */
document.addEventListener('DOMContentLoaded', async ()=>{
  await Promise.all([loadMenu(), loadCart()]);
  bindAll();
  renderNotifs();
});

/* API Data */
async function loadMenu(){
  try{
    const j=await(await fetch(`${API}/menu.php`)).json();
    if(j.status!=='success')throw new Error(j.message);
    allMenus=j.data.menus||[];allCats=j.data.categories||[];
    buildCatChips();applyFilter();
  }catch(e){
    document.getElementById('menu-grid').innerHTML=`
      <div class="col-span-full flex flex-col items-center py-20 text-center opacity-70">
        <span class="material-symbols-outlined text-6xl text-white/30 mb-4">wifi_off</span>
        <p class="font-display text-xl text-white mb-2">Gagal Memuat Menu</p>
        <button onclick="location.reload()" class="bg-primary/20 text-primary px-6 py-2 rounded-full font-bold hover:bg-primary/30 transition-colors">Coba Lagi</button>
      </div>`;
  }
}
async function loadCart(){
  try{const j=await(await fetch(`${API}/cart.php`)).json();if(j.status==='success')setCart(j.data);}catch(e){}
}
async function apiAdd(menuId,qty,notes){
  const j=await(await fetch(`${API}/cart.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',menu_id:menuId,quantity:qty,notes:notes||''})})).json();
  if(j.status!=='success')throw new Error(j.message);setCart(j.data);
}
async function apiUpdate(key,qty){
  const j=await(await fetch(`${API}/cart.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update',key,quantity:qty})})).json();
  if(j.status==='success')setCart(j.data);
}
async function apiRemove(key){
  const j=await(await fetch(`${API}/cart.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove',key})})).json();
  if(j.status==='success'){setCart(j.data);toast('Dihapus dari keranjang','error');}
}

/* Render Chips */
function buildCatChips(){
  const cnt={};allMenus.forEach(m=>{cnt[m.category_id]=(cnt[m.category_id]||0)+1;});
  let html=`<button class="chip chip-active" data-cid="0">Semua Menu</button>`;
  html+=allCats.map(c=>`<button class="chip chip-idle" data-cid="${c.id}">${catIcon(c.name)} ${esc(c.name)}</button>`).join('');
  document.getElementById('cat-chips').innerHTML=html;
}
function selectCat(id){
  activeCat=parseInt(id);
  document.querySelectorAll('#cat-chips button').forEach(el=>{
    const a=parseInt(el.dataset.cid)===activeCat;
    el.className=`chip ${a?'chip-active':'chip-idle'}`;
  });
  applyFilter();
}

/* Filter & Sort */
function applyFilter(){
  let m=[...allMenus];
  if(activeCat)m=m.filter(x=>x.category_id==activeCat);
  if(searchQ.trim()){const q=searchQ.toLowerCase();m=m.filter(x=>x.name.toLowerCase().includes(q)||(x.description||'').toLowerCase().includes(q));}
  switch(sortMode){
    case'price-asc':m.sort((a,b)=>a.price-b.price);break;
    case'price-desc':m.sort((a,b)=>b.price-a.price);break;
    case'rating':m.sort((a,b)=>b.avg_rating-a.avg_rating);break;
    case'name':m.sort((a,b)=>a.name.localeCompare(b.name));break;
  }
  filtered=m;renderMenus();
}

/* Render Menu Grid/List */
function renderMenus(){
  const grid=document.getElementById('menu-grid');
  const catName=activeCat===0?'Semua Menu':(allCats.find(c=>c.id===activeCat)?.name||'Menu');
  document.getElementById('section-heading').textContent=catName;
  
  if(!filtered.length){
    grid.className='col-span-full';
    grid.innerHTML=`<div class="flex flex-col items-center justify-center py-20 text-center opacity-50">
      <div class="text-6xl mb-4">☕</div>
      <p class="font-display text-xl text-white mb-2">Menu tidak ditemukan</p>
    </div>`;
    return;
  }

  grid.className=viewMode==='grid'?'grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4':'flex flex-col gap-4';
  grid.innerHTML=filtered.map((m,i)=>viewMode==='grid'?gridCard(m,i):listCard(m,i)).join('');
  document.querySelectorAll('.card-anim').forEach((el,i)=>{el.style.animationDelay=`${i*30}ms`;});
  bindCards();
}

function gridCard(m){
  const isFav=!!favs[m.id];
  const img=m.image?`<img class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" src="${IMGS}/${esc(m.image)}" loading="lazy">`:`<div class="w-full h-full flex items-center justify-center text-5xl opacity-50">${catIcon(m.category)}</div>`;
  
  return `<article class="card-anim menu-card glass rounded-[2rem] p-2 flex flex-col cursor-pointer group relative overflow-hidden" data-mid="${m.id}">
    <div class="w-full aspect-[4/5] rounded-3xl overflow-hidden bg-white/5 relative mb-3">
      ${img}
      <div class="absolute inset-0 bg-gradient-to-t from-[#050B14] via-transparent to-transparent opacity-80"></div>
      
      <div class="absolute top-3 right-3 flex flex-col gap-2">
        <button class="card-fav w-9 h-9 rounded-full bg-black/40 backdrop-blur-md flex items-center justify-center border border-white/10 hover:bg-black/60 transition-colors" data-mid="${m.id}">
          <span class="material-symbols-outlined text-[18px] ${isFav?'text-red-500':'text-white'}" style="${isFav?`font-variation-settings:'FILL' 1`:''}">favorite</span>
        </button>
      </div>

      ${m.avg_rating>0?`<div class="absolute top-3 left-3 bg-black/40 backdrop-blur-md rounded-full px-2.5 py-1 flex items-center gap-1 border border-white/10"><span class="material-symbols-outlined text-primary text-[14px]" style="font-variation-settings:'FILL' 1;">star</span><span class="text-xs font-bold text-white">${m.avg_rating}</span></div>`:''}
      
      <div class="absolute bottom-3 left-3 right-3">
        <span class="font-display font-bold text-lg text-primary drop-shadow-md">Rp ${fmt(m.price)}</span>
      </div>
    </div>
    <div class="px-2 pb-2">
      <h3 class="font-display font-bold text-white text-base leading-tight mb-1 truncate">${esc(m.name)}</h3>
      <p class="text-xs text-white/50 truncate">${esc(m.description||m.category)}</p>
    </div>
    <button class="card-add absolute bottom-3 right-3 w-10 h-10 rounded-2xl bg-primary text-navy-bg flex items-center justify-center opacity-0 translate-y-4 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 shadow-[0_0_15px_rgba(245,189,88,0.4)]" data-mid="${m.id}">
      <span class="material-symbols-outlined text-[20px]">add</span>
    </button>
  </article>`;
}

function listCard(m){
  const isFav=!!favs[m.id];
  const img=m.image?`<img class="w-full h-full object-cover" src="${IMGS}/${esc(m.image)}" loading="lazy">`:`<div class="w-full h-full flex items-center justify-center text-4xl opacity-50">${catIcon(m.category)}</div>`;
  
  return `<article class="card-anim menu-card glass rounded-3xl p-3 flex gap-4 cursor-pointer relative" data-mid="${m.id}">
    <div class="w-28 h-28 flex-shrink-0 rounded-2xl overflow-hidden bg-white/5 relative">
      ${img}
    </div>
    <div class="flex-1 min-w-0 flex flex-col justify-center py-1">
      <p class="text-[10px] font-bold text-primary/80 uppercase tracking-widest mb-1">${esc(m.category)}</p>
      <h3 class="font-display font-bold text-white text-lg leading-tight mb-1 truncate">${esc(m.name)}</h3>
      <p class="text-xs text-white/50 truncate mb-3">${esc(m.description||'')}</p>
      <span class="font-display font-bold text-primary">Rp ${fmt(m.price)}</span>
    </div>
    <div class="flex flex-col justify-between items-end">
      <button class="card-fav w-8 h-8 rounded-full bg-white/5 flex items-center justify-center" data-mid="${m.id}">
        <span class="material-symbols-outlined text-[16px] ${isFav?'text-red-500':'text-white/50'}" style="${isFav?`font-variation-settings:'FILL' 1`:''}">favorite</span>
      </button>
      <button class="card-add w-10 h-10 rounded-xl bg-primary/20 text-primary flex items-center justify-center hover:bg-primary hover:text-navy-bg transition-colors" data-mid="${m.id}">
        <span class="material-symbols-outlined text-[20px]">add</span>
      </button>
    </div>
  </article>`;
}

/* Cart UI */
function setCart(data){cart=data;renderCartDrawer();updateFloatBar();}

function renderCartDrawer(){
  const empty=document.getElementById('cart-empty');
  const list=document.getElementById('cart-items');
  const footer=document.getElementById('cart-footer');
  if(!cart.items?.length){empty.classList.remove('hidden');list.innerHTML='';footer.classList.add('hidden');return;}
  empty.classList.add('hidden');footer.classList.remove('hidden');

  list.innerHTML=cart.items.map(item=>{
    const m=allMenus.find(x=>x.id==item.menu_id);
    const img=m?.image?`<img src="${IMGS}/${esc(m.image)}" class="w-full h-full object-cover rounded-xl">`:`<div class="w-full h-full flex items-center justify-center text-2xl opacity-50">${catIcon(m?.category)}</div>`;
    return `<div class="flex gap-4 glass rounded-2xl p-3 items-center">
      <div class="w-16 h-16 flex-shrink-0 bg-white/5 rounded-xl border border-white/5">
        ${img}
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-bold text-white truncate">${esc(item.name)}</p>
        ${item.notes?`<p class="text-[11px] text-white/50 truncate mb-1">Catatan: ${esc(item.notes)}</p>`:''}
        <p class="text-sm font-bold text-primary">Rp ${fmt(item.price*item.quantity)}</p>
      </div>
      <div class="flex items-center gap-2 bg-[#050B14] rounded-xl p-1 border border-white/5">
        <button class="qty-m w-8 h-8 rounded-lg ${item.quantity===1?'text-red-400 hover:bg-red-500/20':'text-white hover:bg-white/10'} flex items-center justify-center font-bold" data-key="${esc(item.key)}" data-qty="${item.quantity-1}">
          ${item.quantity===1?'<span class="material-symbols-outlined text-[16px]">delete</span>':'−'}
        </button>
        <span class="font-bold text-white w-6 text-center text-sm">${item.quantity}</span>
        <button class="qty-p w-8 h-8 rounded-lg bg-white/10 text-white flex items-center justify-center font-bold hover:bg-white/20" data-key="${esc(item.key)}" data-qty="${item.quantity+1}">+</button>
      </div>
    </div>`;
  }).join('');

  const sub=cart.subtotal||0,tax=sub*.1,total=sub+tax;
  document.getElementById('cd-summary').innerHTML=`
    <div class="flex justify-between text-xs text-white/60 mb-2"><span>Subtotal (${cart.item_count})</span><span>Rp ${fmt(sub)}</span></div>
    <div class="flex justify-between text-xs text-white/60 mb-3"><span>Pajak (10%)</span><span>Rp ${fmt(tax)}</span></div>
    <div class="flex justify-between font-display font-bold text-white text-lg pt-3 border-t border-white/10"><span>Total</span><span class="text-primary">Rp ${fmt(total)}</span></div>`;

  bindCartQty();
}

function updateFloatBar(){
  const count=cart.item_count||0,sub=cart.subtotal||0;
  
  // Desktop Float
  const dFloat=document.getElementById('cart-float');
  if(dFloat){
    if(count>0){
      dFloat.classList.remove('hidden');
      document.getElementById('cart-float-count').textContent=count;
      document.getElementById('cart-float-total').textContent=`Rp ${fmt(sub+sub*.1)}`;
    } else {
      dFloat.classList.add('hidden');
    }
  }

  // Mobile badges
  ['mob-cart-badge','desk-cart-badge'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){if(count>0){el.classList.remove('hidden');el.textContent=count;}else el.classList.add('hidden');}
  });
}

/* Modals */
function openModal(menuId){
  const m=allMenus.find(x=>x.id==menuId);if(!m)return;
  modalMenu=m;modalQty=1;
  document.getElementById('modal-cat').textContent=m.category;
  document.getElementById('modal-name').textContent=m.name;
  document.getElementById('modal-desc').textContent=m.description||'Kualitas premium racikan Padud Coffee.';
  document.getElementById('modal-price').textContent=`Rp ${fmt(m.price)}`;
  document.getElementById('mqv').textContent=1;
  document.getElementById('modal-notes').value='';
  
  const img=document.getElementById('modal-img'),ph=document.getElementById('modal-ph');
  if(m.image){img.src=`${IMGS}/${m.image}`;img.classList.remove('hidden');ph.classList.add('hidden');img.onerror=()=>{img.classList.add('hidden');ph.classList.remove('hidden');};}
  else{img.classList.add('hidden');ph.classList.remove('hidden');ph.innerHTML=catIcon(m.category);}
  
  const rb=document.getElementById('modal-rating-badge');
  if(m.avg_rating>0){rb.classList.remove('hidden');document.getElementById('modal-rating-val').textContent=m.avg_rating;}else rb.classList.add('hidden');
  
  document.getElementById('modal-overlay').classList.add('open');
  document.getElementById('modal-box').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('modal-overlay').classList.remove('open');
  document.getElementById('modal-box').classList.remove('open');
  document.body.style.overflow='';modalMenu=null;
}

function openCartDrawer(){
  document.getElementById('cart-overlay').classList.add('open');
  document.getElementById('cart-drawer').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeCartDrawer(){
  document.getElementById('cart-overlay').classList.remove('open');
  document.getElementById('cart-drawer').classList.remove('open');
  document.body.style.overflow='';
}

/* Checkout Logic */
async function placeOrder(){
  const nameEl=document.getElementById('pay-customer-name')||document.getElementById('customer-name');
  const customerName=(nameEl?.value||USER_NAME||'Guest').trim();
  if(!customerName){toast('Isi nama pemesan','error');nameEl?.focus();return;}
  const notes=document.getElementById('cart-notes')?.value||'';
  const btn=document.getElementById('place-order-btn');
  btn.disabled=true;btn.innerHTML='Memproses...';

  try{
    const body={customer_name:customerName,notes};
    if(TABLE)body.table_id=parseInt(TABLE)||null;
    const j=await(await fetch(`${API}/orders.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(j.status!=='success')throw new Error(j.message);

    currentOrderId=j.data.order_id;
    currentOrderNumber=j.data.order_number;
    currentTrxId=j.data.transaction_id;

    document.getElementById('pay-order-number').textContent=currentOrderNumber;
    document.getElementById('pay-final-total').textContent=`Rp ${fmt(j.data.total)}`;

    goPayStep(2);
  }catch(e){toast(e.message||'Gagal buat pesanan','error');}
  finally{btn.disabled=false;btn.innerHTML='Buat Pesanan';}
}

async function confirmPayment(){
  const method=document.querySelector('input[name="pay_method"]:checked')?.value||'cash';
  const btn=document.getElementById('confirm-pay-btn');
  btn.disabled=true;btn.innerHTML='Memproses...';

  try{
    if(method==='midtrans'){
      const j=await(await fetch(`${API}/payment.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'snap_token',order_id:currentOrderId})})).json();
      if(j.status!=='success')throw new Error(j.message);
      if(typeof window.snap==='undefined'){toast('Midtrans tidak tersedia.','error');btn.disabled=false;btn.innerHTML='Konfirmasi Bayar';return;}
      closePayment();
      window.snap.pay(j.data.snap_token,{
        onSuccess:()=>{showDoneStep('Bayar Online');},
        onPending:()=>{toast('Pembayaran pending.');closePayment();},
        onError:()=>{toast('Pembayaran gagal','error');},
        onClose:()=>{toast('Dibatalkan');}
      });
      return;
    }
    if(method==='transfer'){
      const f=document.getElementById('proof-file');
      if(f?.files?.length){
        const fd=new FormData();fd.append('action','upload_proof');fd.append('transaction_id',currentTrxId);fd.append('proof',f.files[0]);
        await fetch(`${API}/payment.php`,{method:'POST',body:fd});
      }
    }
    goPayStep(3);
    const ml={cash:'Bayar Tunai di Kasir',transfer:'Transfer Manual (Pending)'};
    showDoneStep(ml[method]||method);
  }catch(e){toast('Gagal bayar','error');}
  finally{btn.disabled=false;btn.innerHTML='Konfirmasi Bayar';}
}

function showDoneStep(mLabel){
  document.getElementById('done-order-number').textContent=currentOrderNumber||'–';
  document.getElementById('done-pay-method').textContent=mLabel;
  if(currentOrderNumber) document.getElementById('done-receipt-link').href=`receipt.php?order_number=${encodeURIComponent(currentOrderNumber)}${TABLE?'&table='+TABLE:''}`;
  goPayStep(3);
}

function goPayStep(n){
  [1,2,3].forEach(i=>{
    document.getElementById(`pay-step-${i}`).classList.toggle('hidden',i!==n);
    const dot=document.getElementById(`step${i}-dot`);
    if(dot){dot.className=`w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm ${i<=n?'bg-primary text-navy-bg shadow-[0_0_15px_rgba(245,189,88,0.3)]':'bg-white/10 text-white/50'}`;}
  });
  document.getElementById('step-line-1').className=`w-12 h-1 rounded-full ${n>=2?'bg-primary':'bg-white/10'}`;
  document.getElementById('step-line-2').className=`w-12 h-1 rounded-full ${n>=3?'bg-primary':'bg-white/10'}`;
  document.getElementById('pay-step-label').textContent=n===3?'SELESAI':`STEP ${n}`;
  if(n===3) document.getElementById('pay-back-bar').classList.add('hidden');
  else document.getElementById('pay-back-bar').classList.remove('hidden');
}

function openPaymentModal(){
  const items=document.getElementById('pay-order-items');
  items.innerHTML=cart.items.map(i=>`
    <div class="flex justify-between items-start">
      <div>
        <p class="font-bold text-white">${esc(i.name)} <span class="text-primary">×${i.quantity}</span></p>
        ${i.notes?`<p class="text-xs text-white/50">${esc(i.notes)}</p>`:''}
      </div>
      <span class="font-bold text-white">Rp ${fmt(i.price*i.quantity)}</span>
    </div>`).join('');
  const sub=cart.subtotal||0,tax=sub*.1,total=sub+tax;
  document.getElementById('pay-summary').innerHTML=`
    <div class="flex justify-between text-sm text-white/50"><span>Subtotal</span><span>Rp ${fmt(sub)}</span></div>
    <div class="flex justify-between text-sm text-white/50"><span>Pajak (10%)</span><span>Rp ${fmt(tax)}</span></div>
    <div class="flex justify-between text-lg font-bold text-primary pt-2 border-t border-white/5 mt-2"><span>Total</span><span>Rp ${fmt(total)}</span></div>`;
  
  const nameEl=document.getElementById('pay-customer-name');
  if(nameEl && !nameEl.value) nameEl.value=USER_NAME||document.getElementById('customer-name')?.value||'';

  goPayStep(1);
  document.getElementById('payment-overlay').classList.add('open');
  document.getElementById('payment-box').classList.add('open');
  document.body.style.overflow='hidden';
}
function closePayment(){
  document.getElementById('payment-overlay').classList.remove('open');
  document.getElementById('payment-box').classList.remove('open');
  document.body.style.overflow='';
}
function clearCartUI(){cart={items:[],item_count:0,subtotal:0};renderCartDrawer();updateFloatBar();closeCartDrawer();}

/* Fav Only Toggle */
function toggleFavOnly(){
  toast('Fitur Favorites Only dipanggil (Demo)');
}

/* Notif & Toast */
const DEMO_NOTIFS=[{title:'Pesanan Diterima',msg:'Barista kami sedang menyiapkan pesananmu.',time:'Baru saja',unread:true}];
function renderNotifs(){
  document.getElementById('notif-body').innerHTML=DEMO_NOTIFS.map(n=>`
    <div class="glass rounded-2xl p-4 border border-white/5 relative">
      ${n.unread?'<div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-primary"></div>':''}
      <p class="font-bold text-white mb-1">${esc(n.title)}</p>
      <p class="text-sm text-white/60 mb-2">${esc(n.msg)}</p>
      <p class="text-[10px] text-white/30 uppercase tracking-widest">${n.time}</p>
    </div>`).join('');
}
function openNotif(){document.getElementById('notif-bg').classList.add('open');document.getElementById('notif-panel').classList.add('open');document.getElementById('notif-dot').classList.add('hidden');document.body.style.overflow='hidden';}
function closeNotif(){document.getElementById('notif-bg').classList.remove('open');document.getElementById('notif-panel').classList.remove('open');document.body.style.overflow='';}

function toast(msg,type='success'){
  const w=document.getElementById('toast-wrap');
  const el=document.createElement('div');
  const color=type==='success'?'bg-emerald-500':'bg-red-500';
  el.className=`glass-strong rounded-2xl px-5 py-3 border-l-4 border-${type==='success'?'emerald-500':'red-500'} flex items-center gap-3 text-white font-medium shadow-2xl transform transition-all duration-300 translate-x-full`;
  el.innerHTML=`<span class="material-symbols-outlined text-[20px] ${type==='success'?'text-emerald-400':'text-red-400'}">${type==='success'?'check_circle':'error'}</span> ${msg}`;
  w.appendChild(el);
  requestAnimationFrame(()=>{el.classList.remove('translate-x-full');});
  setTimeout(()=>{el.classList.add('translate-x-full');setTimeout(()=>el.remove(),300);},3000);
}

function toggleFav(mid){
  if(favs[mid]){delete favs[mid];toast('Dihapus dari favorit','error');}
  else{favs[mid]=true;toast('Disimpan ke favorit ❤️');}
  localStorage.setItem('pc_favs',JSON.stringify(favs));
  document.querySelectorAll(`.card-fav[data-mid="${mid}"]`).forEach(b=>{
    const i=b.querySelector('span');
    if(i){i.className=`material-symbols-outlined text-[18px] ${favs[mid]?'text-red-500':'text-white'}`;i.style.fontVariationSettings=favs[mid]?`'FILL' 1`:'';}
  });
}

function scrollToTop(){window.scrollTo({top:0,behavior:'smooth'});}

/* Events */
function bindAll(){
  document.getElementById('checkout-btn').addEventListener('click',()=>{closeCartDrawer();openPaymentModal();});
  document.getElementById('mqb-minus').addEventListener('click',()=>{if(modalQty>1){modalQty--;document.getElementById('mqv').textContent=modalQty;}});
  document.getElementById('mqb-plus').addEventListener('click',()=>{modalQty++;document.getElementById('mqv').textContent=modalQty;});
  document.getElementById('modal-add-btn').addEventListener('click',async()=>{
    if(!modalMenu)return;
    const notes=document.getElementById('modal-notes').value.trim();
    const btn=document.getElementById('modal-add-btn');btn.disabled=true;
    try{await apiAdd(modalMenu.id,modalQty,notes);toast(`${modalMenu.name} ditambahkan!`);closeModal();}
    catch(e){toast(e.message||'Gagal','error');}finally{btn.disabled=false;}
  });
  
  document.getElementById('sort-select').addEventListener('change',e=>{sortMode=e.target.value;applyFilter();});
  
  document.getElementById('grid-btn').addEventListener('click',()=>{
    viewMode='grid';
    document.getElementById('grid-btn').className='w-10 h-10 rounded-lg bg-primary/20 text-primary flex items-center justify-center transition-colors';
    document.getElementById('list-btn').className='w-10 h-10 rounded-lg text-white/50 hover:bg-white/10 hover:text-white flex items-center justify-center transition-colors';
    applyFilter();
  });
  document.getElementById('list-btn').addEventListener('click',()=>{
    viewMode='list';
    document.getElementById('list-btn').className='w-10 h-10 rounded-lg bg-primary/20 text-primary flex items-center justify-center transition-colors';
    document.getElementById('grid-btn').className='w-10 h-10 rounded-lg text-white/50 hover:bg-white/10 hover:text-white flex items-center justify-center transition-colors';
    applyFilter();
  });
  
  document.getElementById('cat-chips').addEventListener('click',e=>{const b=e.target.closest('button');if(b?.dataset.cid!==undefined)selectCat(b.dataset.cid);});
  
  const syncSearch=()=>{searchQ=document.getElementById('search-desk')?.value||document.getElementById('search-mob')?.value||'';clearTimeout(window._st);window._st=setTimeout(applyFilter,300);};
  ['search-desk','search-mob'].forEach(id=>document.getElementById(id)?.addEventListener('input',syncSearch));
  
  document.querySelectorAll('input[name="pay_method"]').forEach(r=>{
    r.addEventListener('change',()=>{document.getElementById('upload-proof-section').classList.toggle('hidden',r.value!=='transfer');});
  });
  
  document.getElementById('proof-file')?.addEventListener('change',e=>{
    const file=e.target.files[0];if(!file)return;
    const reader=new FileReader();reader.onload=ev=>{document.getElementById('proof-img').src=ev.target.result;document.getElementById('proof-preview').classList.remove('hidden');};
    reader.readAsDataURL(file);
  });
  
  document.getElementById('cart-float-btn')?.addEventListener('click',openCartDrawer);
  
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeCartDrawer();closeNotif();closePayment();}});
}

function bindCards(){
  document.querySelectorAll('.card-anim').forEach(c=>{
    c.addEventListener('click',e=>{if(e.target.closest('.card-fav')||e.target.closest('.card-add'))return;openModal(c.dataset.mid);});
  });
  document.querySelectorAll('.card-add').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();openModal(b.dataset.mid);}));
  document.querySelectorAll('.card-fav').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();toggleFav(b.dataset.mid);}));
}
function bindCartQty(){
  document.querySelectorAll('.qty-m').forEach(b=>b.addEventListener('click',()=>{const q=parseInt(b.dataset.qty);q<=0?apiRemove(b.dataset.key):apiUpdate(b.dataset.key,q);}));
  document.querySelectorAll('.qty-p').forEach(b=>b.addEventListener('click',()=>apiUpdate(b.dataset.key,parseInt(b.dataset.qty))));
}
</script>
</body>
</html>
