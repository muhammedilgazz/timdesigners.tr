<?php
ob_start(); // Tamponlamayı başlat (Olası boşluk/hata çıktılarını yakalamak için)
session_start();

// --- AJAX İSTEKLERİNİ YÖNETME (API KISMI) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // API yanıtı öncesi olası HTML/hata çıktılarını temizle (JSON bozulmasını önler)
    ob_clean();
    header('Content-Type: application/json');
    
    // Login İşlemi
    if ($_POST['action'] === 'login') {
        // Trim ile baştaki/sondaki boşlukları temizle
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === 'admin' && $password === '1234') {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Giriş başarılı!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hatalı kullanıcı adı veya şifre!']);
        }
        exit;
    }

    // Logout İşlemi
    if ($_POST['action'] === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Çıkış yapıldı.']);
        exit;
    }
}

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xtreme Super App Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons (Son sürüm) -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: {
                        dark: { 900: '#0f1014', 800: '#151921', 700: '#232732' },
                        brand: { teal: '#0f515e', light: '#ccfbf1' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $isLoggedIn ? 'p-3 md:p-8 bg-[#000000]' : 'bg-[#050505] flex items-center justify-center min-h-screen p-4'; ?>">

    <?php if (!$isLoggedIn): ?>
        <!-- GİRİŞ EKRANI -->
        <div class="w-full max-w-sm bg-[#151921] border border-white/10 rounded-2xl shadow-2xl p-6 md:p-8 relative overflow-hidden">
            <div class="absolute top-[-50px] right-[-50px] w-32 h-32 bg-teal-600/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-[-50px] left-[-50px] w-32 h-32 bg-purple-600/20 rounded-full blur-3xl"></div>

            <div class="relative z-10">
                <h2 class="text-2xl font-bold text-center mb-1 text-white tracking-wide">Xtreme Portal</h2>
                <p class="text-gray-500 text-center text-xs mb-8">Devam etmek için giriş yapın</p>

                <div id="loginMessage" class="hidden mb-4 p-3 rounded-lg text-sm text-center"></div>

                <form id="loginForm" class="flex flex-col gap-4">
                    <input type="hidden" name="action" value="login">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Kullanıcı Adı</label>
                        <input type="text" name="username" required 
                            class="w-full bg-[#0f1014] border border-gray-700 rounded-lg px-4 py-3 text-sm text-white focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors"
                            placeholder="admin">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Şifre</label>
                        <input type="password" name="password" required 
                            class="w-full bg-[#0f1014] border border-gray-700 rounded-lg px-4 py-3 text-sm text-white focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors"
                            placeholder="1234">
                    </div>
                    <button type="submit" id="loginBtn"
                        class="mt-4 w-full bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-500 hover:to-teal-400 text-white font-medium py-3 rounded-lg transition-all transform hover:scale-[1.02] shadow-lg shadow-teal-900/50 flex justify-center items-center gap-2">
                        <span>Giriş Yap</span>
                        <svg class="animate-spin h-4 w-4 text-white hidden" id="loginSpinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- DASHBOARD (PANEL) -->
        <div class="w-full max-w-[1050px] flex flex-col gap-4 relative pb-20 md:pb-0">
            <!-- Header -->
            <header class="flex justify-between items-center px-1">
                <div class="flex flex-col">
                    <h1 class="text-sm font-bold text-gray-300 tracking-wide uppercase opacity-90 pl-1">Xtreme Super App</h1>
                    <span class="text-[10px] text-teal-500 pl-1">Hoşgeldin, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <button id="logoutBtn" class="bg-[#1f2937] hover:bg-red-900/50 text-gray-400 hover:text-red-200 p-2 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </button>
            </header>

            <!-- Hero Bölümü -->
            <section class="relative rounded-2xl overflow-hidden shadow-2xl h-[240px] md:h-[380px] flex items-start border border-white/5 bg-[#0d3b46] bg-[url('src/media/hero.png')] bg-cover bg-center">
                <div class="absolute inset-0 bg-gradient-to-r from-[#0b333e]/90 to-[#126e63]/80"></div>
                <div class="absolute top-[20%] left-[35%] w-16 h-16 border-2 border-teal-300/10 rounded-full hidden md:block"></div>
                <div class="absolute bottom-[-10%] left-[5%] w-48 h-48 bg-teal-500/5 rounded-full blur-2xl"></div>

                <div class="container mx-auto px-6 md:px-12 flex flex-row relative z-20 h-full">
                    <div class="w-full md:w-1/2 flex flex-col justify-center md:justify-start pt-0 md:pt-12 h-full">
                        <h2 class="text-2xl md:text-3xl font-bold text-white mb-2 leading-tight tracking-wide drop-shadow-sm">Xtreme Super App</h2>
                        <p class="text-teal-100 text-xs md:text-sm font-light opacity-80 max-w-xs leading-relaxed">Her şey tek yerde — odaklanın, yönetin, ilerleyin.</p>
                    </div>
                </div>
            </section>

            <!-- Grid Alanı -->
            <main class="bg-dark-800 rounded-2xl p-3 md:p-5 shadow-xl border border-white/5 relative">
                <h3 class="text-base font-semibold text-white mb-4 pl-1 opacity-90">Keşfet</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2 md:gap-3 relative z-10">
                    
                    <!-- Mood Board -->
                    <div class="app-card block bg-gradient-to-br from-[#f43f5e] to-[#be123c] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="palette" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Mood Board</span></div>
                        <i data-lucide="palette" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Notlar -->
                    <div class="app-card block bg-gradient-to-br from-[#fbbf24] to-[#d97706] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="sticky-note" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Notlar</span></div>
                        <i data-lucide="pencil" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.15] transform rotate-12"></i>
                    </div>

                    <!-- Hukuk -->
                    <div class="app-card block bg-gradient-to-br from-[#3b82f6] to-[#1d4ed8] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="scale" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Hukuk</span></div>
                        <i data-lucide="gavel" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Finans -->
                    <div class="app-card block bg-gradient-to-br from-[#4ade80] to-[#15803d] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="credit-card" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Finans</span></div>
                        <i data-lucide="banknote" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Bahis Dünyası -->
                    <div class="app-card block bg-gradient-to-br from-[#a78bfa] to-[#6d28d9] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="target" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Bahis Dünyası</span></div>
                        <i data-lucide="dices" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Rehber -->
                    <div class="app-card block bg-gradient-to-br from-[#818cf8] to-[#4338ca] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="book-user" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Rehber</span></div>
                        <i data-lucide="calendar" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- DB & Liste -->
                    <div class="app-card block bg-gradient-to-br from-[#fb7185] to-[#be123c] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="database" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">DB & Liste</span></div>
                        <i data-lucide="server" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Sağlık -->
                    <div class="app-card block bg-gradient-to-br from-[#22d3ee] to-[#0891b2] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="pill" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Sağlık</span></div>
                        <i data-lucide="activity" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- AI Dünyası -->
                    <div class="app-card block bg-gradient-to-br from-[#e879f9] to-[#a21caf] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="bot" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">AI Dünyası</span></div>
                        <i data-lucide="cpu" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- İş Yaşamı -->
                    <div class="app-card block bg-gradient-to-br from-[#fdba74] to-[#c2410c] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="briefcase" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">İş Yaşamı</span></div>
                        <i data-lucide="building-2" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Alışveriş -->
                    <div class="app-card block bg-gradient-to-br from-[#64748b] to-[#334155] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="shopping-bag" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Alışveriş</span></div>
                        <i data-lucide="shopping-cart" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                    <!-- Diğer -->
                    <div class="app-card block bg-gradient-to-br from-[#9ca3af] to-[#4b5563] rounded-xl p-3 h-24 md:h-28 relative overflow-hidden group">
                        <div class="absolute top-3 left-3 text-white"><i data-lucide="sparkles" class="w-8 h-8 md:w-10 md:h-10 opacity-90 group-hover:scale-110 transition-transform"></i></div>
                        <div class="absolute bottom-2 left-3 right-3"><span class="font-bold text-white text-[11px] md:text-[13px] leading-none block">Diğer</span></div>
                        <i data-lucide="more-horizontal" class="absolute -right-3 -bottom-5 w-20 h-20 md:w-24 md:h-24 text-white opacity-[0.08] transform rotate-12"></i>
                    </div>

                </div>
            </main>

            <footer class="text-center text-gray-700 text-[10px] font-medium opacity-50 pb-4">
                &copy; 2025 Xtreme Super App
            </footer>
        </div>

        <div id="menuOverlay" onclick="closeMenu()"></div>
        <div id="floatingMenu" class="bg-[#151921] border border-white/10 rounded-2xl shadow-2xl p-4 flex flex-col gap-2 ring-1 ring-white/5 z-50">
            <div class="flex justify-between items-center mb-2 pb-2 border-b border-white/5">
                <h3 id="menuTitle" class="text-sm font-bold text-gray-200 tracking-wide uppercase">Menü</h3>
                <button onclick="closeMenu()" class="text-gray-500 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="menuItems" class="space-y-1"></div>
        </div>
    <?php endif; ?>

    <script src="script.js"></script>
</body>
</html>