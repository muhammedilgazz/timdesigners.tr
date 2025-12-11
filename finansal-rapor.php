<?php
session_start();

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    session_destroy();
    header("Location: index.php");
    exit;
}

$isAdmin = true;

// --- VERİTABANI AYARLARI ---
$host = 'localhost';
$db   = 'timdesig_os';
$user = 'timdesig_xtreme';
$pass = 'Ayaz343591';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// --- AJAX İŞLEMLERİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action'])) { echo json_encode(['success' => false]); exit; }
    
    $action = $input['action'];

    try {
        if ($action === 'payment') {
            $id = $input['id'];
            $amount = (float)$input['amount'];
            $currentTotal = (float)$input['total'];
            $remaining = $currentTotal - $amount;
            $status = ($remaining <= 0.5) ? 'Ödendi' : 'Kısmi Ödeme';
            
            if ($remaining > 0.5) {
                $stmt = $pdo->prepare("UPDATE expenses SET amount = ?, status = ? WHERE id = ?");
                $stmt->execute([$remaining, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE expenses SET status = ?, paid_amount = ? WHERE id = ?");
                $stmt->execute([$status, $amount, $id]);
            }
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'update_order_batch') {
            $orderList = $input['order'];
            $pdo->beginTransaction();
            foreach ($orderList as $index => $id) {
                $stmt = $pdo->prepare("UPDATE expenses SET sort_order = ? WHERE id = ?");
                $stmt->execute([$index + 1, $id]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'update_field') {
            $id = $input['id'];
            $field = $input['field'];
            $value = $input['value'];
            $allowedFields = ['amount', 'status', 'title', 'detail', 'category'];
            if (in_array($field, $allowedFields)) {
                $stmt = $pdo->prepare("UPDATE expenses SET $field = ? WHERE id = ?");
                $stmt->execute([$value, $id]);
                echo json_encode(['success' => true]);
            }
        }
        elseif ($action === 'update_sub_item_field') {
            $id = $input['id'];
            $field = $input['field'];
            $value = $input['value'];
            $allowedFields = ['name', 'detail', 'quantity', 'price']; 
            if (in_array($field, $allowedFields)) {
                $stmt = $pdo->prepare("UPDATE expense_items SET $field = ? WHERE id = ?");
                $stmt->execute([$value, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Geçersiz alan']);
            }
        }
        elseif ($action === 'delete_expense') {
            $id = $input['id'];
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM expense_items WHERE expense_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'add_expense') {
            $title = $input['title'];
            $amount = (float)$input['amount'];
            $category = $input['category'];
            $status = 'Bekliyor';
            $type = 'Genel';
            $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM expenses");
            $row = $stmt->fetch();
            $newOrder = ($row['max_order'] ?? 0) + 1;
            $stmt = $pdo->prepare("INSERT INTO expenses (title, amount, category, status, type, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $amount, $category, $status, $type, $newOrder]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'delete_sub_item') {
            $id = $input['id'];
            $stmt = $pdo->prepare("DELETE FROM expense_items WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'add_sub_item') {
            $expense_id = $input['expense_id'];
            $name = $input['name'];
            $price = (float)$input['price'];
            $qty = (float)$input['quantity'];
            $stmt = $pdo->prepare("INSERT INTO expense_items (expense_id, name, price, quantity, unit, icon) VALUES (?, ?, ?, ?, 'Adet', 'fa-box')");
            $stmt->execute([$expense_id, $name, $price, $qty]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- VERİLERİ ÇEKME (GET) ---
$stmt = $pdo->query("SELECT * FROM expenses ORDER BY sort_order ASC");
$dbExpenses = $stmt->fetchAll();

$stmtItems = $pdo->query("SELECT * FROM expense_items ORDER BY id ASC");
$allSubItems = $stmtItems->fetchAll();

$itemsByExpenseId = [];
foreach ($allSubItems as $subItem) {
    $eid = $subItem['expense_id'];
    if (!isset($itemsByExpenseId[$eid])) $itemsByExpenseId[$eid] = [];
    
    $mappedItem = [
        'id' => $subItem['id'], 
        'urun' => $subItem['name'],
        'name' => $subItem['name'],
        'marka' => $subItem['detail'], 
        'detail' => $subItem['detail'],
        'miktar' => $subItem['unit'],
        'adet' => (float)$subItem['quantity'],
        'quantity' => (float)$subItem['quantity'],
        'fiyat' => (float)$subItem['price'],
        'price' => (float)$subItem['price'],
        'icon' => $subItem['icon']
    ];
    $itemsByExpenseId[$eid][] = $mappedItem;
}

$transactions = [];
foreach ($dbExpenses as $row) {
    $extra = json_decode($row['extra_data'], true) ?? [];
    $subItems = isset($itemsByExpenseId[$row['id']]) ? $itemsByExpenseId[$row['id']] : [];
    
    $item = [
        'id' => $row['id'],
        'title' => $row['title'], 
        'name' => $row['title'],
        'category' => $row['category'],
        'type' => $row['type'],
        'detail' => $row['detail'],
        'amount' => (float)$row['amount'],
        'status' => $row['status'],
        'order' => (int)$row['sort_order'],
        'items' => $subItems, 
        'iban' => isset($extra['iban']) ? $extra['iban'] : null,
        'accountName' => isset($extra['account_name']) ? $extra['account_name'] : null,
        'subNo' => isset($extra['sub_no']) ? $extra['sub_no'] : null,
    ];
    $transactions[] = $item;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FinansOS - Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SortableJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bgDark: '#0f111a',
                        panelDark: '#1e232d',
                        borderDark: '#2d3748',
                        accentBlue: '#3b82f6',
                        accentGreen: '#10b981',
                        accentOrange: '#f59e0b',
                        accentRed: '#ef4444',
                        textGray: '#9ca3af',
                        textLight: '#e5e7eb'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans antialiased flex flex-col min-h-screen">

    <!-- HEADER -->
    <header class="bg-[#0f111a] border-b border-gray-800 py-3 px-4 sticky top-0 z-40 no-print">
        <div class="max-w-[1600px] mx-auto flex flex-col gap-3">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-bold text-lg">F</div>
                    <span class="text-xl font-semibold tracking-tight text-white">Finans<span class="text-emerald-400">OS</span></span>
                </div>
                <!-- Mobile Actions -->
                 <div class="flex items-center gap-2">
                     <!-- LOGOUT BUTTON -->
                     <a href="?logout=1" class="w-9 h-9 rounded flex items-center justify-center text-white font-bold cursor-pointer transition-colors bg-red-600 hover:bg-red-700 md:hidden" title="Çıkış Yap">
                        <i class="fa-solid fa-right-from-bracket text-sm"></i>
                    </a>
                 </div>
            </div>
            
             <!-- Search & Actions (Desktop) -->
            <div class="hidden md:flex flex-row items-center justify-between gap-4 w-full">
                <div class="relative w-96">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-500 text-sm"></i>
                    <input type="text" id="header-search" placeholder="Harcama kalemi ara..." 
                        class="w-full bg-[#161b22] border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-blue-500 transition-colors">
                </div>
                 <div class="flex items-center gap-4 justify-end">
                    <div class="flex items-center bg-[#161b22] rounded-lg border border-gray-800 px-4 py-1.5 gap-6">
                        <div class="text-right">
                            <div class="text-[10px] text-gray-500 uppercase font-bold">Kur (USD/TL)</div>
                            <div class="text-sm font-bold text-white">42.44</div>
                        </div>
                        <div class="w-px h-8 bg-gray-800"></div>
                        <div class="text-right">
                            <div class="text-[10px] text-gray-500 uppercase font-bold">Kira Geliri</div>
                            <div class="text-sm font-bold text-blue-400">$2,215</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pl-4 border-l border-gray-800">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-bold text-white">Admin Paneli</div>
                            <div class="text-xs text-emerald-500">Aktif</div>
                        </div>
                        <a href="?logout=1" class="w-10 h-10 rounded flex items-center justify-center text-white font-bold cursor-pointer transition-colors bg-red-600 hover:bg-red-700" title="Çıkış Yap">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div id="app" class="flex-grow p-4 md:p-6 max-w-[1600px] mx-auto w-full grid grid-cols-12 gap-4 md:gap-6 no-print">
        <!-- SIDEBAR - HIDDEN ON MOBILE -->
        <div class="hidden lg:block col-span-12 lg:col-span-3 space-y-6">
             <!-- Summary Card -->
             <div class="bg-[#1e232d] rounded-xl border border-gray-800 overflow-hidden relative shadow-lg">
                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-emerald-500"></div>
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-lg bg-gray-700/50 flex items-center justify-center text-gray-300">
                            <i class="fa-solid fa-wallet text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white leading-tight">Finans Özeti</h2>
                            <p class="text-xs text-gray-500 mt-1"><?php echo date('d F Y'); ?></p>
                        </div>
                    </div>
                    <div class="space-y-3" id="sidebar-summary-list"></div>
                </div>
            </div>
             <!-- Expense Breakdown -->
            <div class="bg-[#1e232d] rounded-xl p-6 border border-gray-800 shadow-lg">
                <h3 class="text-sm font-bold text-white uppercase flex items-center gap-2 mb-4">
                    <i class="fa-solid fa-credit-card text-blue-500"></i> Gider Dağılımı
                </h3>
                <div id="expense-breakdown" class="space-y-4"></div>
            </div>
             <!-- Notes -->
            <div class="bg-[#1e232d] rounded-xl p-6 border border-gray-800 shadow-xl min-h-[150px] flex flex-col">
                 <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-white flex items-center gap-2">
                      <i class="fa-solid fa-clipboard text-yellow-500"></i>
                      DURUM
                    </h3>
                  </div>
                  <div class="bg-[#161b22] border border-gray-700 rounded-lg p-3 text-sm font-mono leading-relaxed text-emerald-400/80 border-emerald-900/30">
                        <i class="fa-solid fa-check"></i> Admin girişi yapıldı. Düzenlemeler kaydedilecek.
                  </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-span-12 lg:col-span-9 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold text-white flex items-center gap-3">
                        <i class="fa-solid fa-file-invoice text-blue-500"></i>
                        GİDER KALEMLERİ
                    </h2>
                    <p class="text-xs md:text-sm text-gray-500 mt-1 hidden sm:block">Veriler 'expense_items' tablosundan dinamik çekiliyor. Sürükle bırak sıralama aktiftir.</p>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button onclick="window.print()" class="flex-1 sm:flex-none justify-center bg-[#161b22] hover:bg-gray-800 text-white px-4 py-2 rounded-lg border border-gray-700 text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Yazdır / PDF</span>
                    </button>
                    <button onclick="openAddExpenseModal()" class="flex-1 sm:flex-none justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors border border-emerald-500/50 shadow-sm flex items-center gap-2 text-sm font-medium md:hidden">
                        <i class="fa-solid fa-plus"></i> Yeni
                    </button>
                </div>
            </div>

            <div id="top-summary-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Market Card -->
                <button onclick="openDetailModal('market')" class="bg-[#1e232d] hover:border-blue-500/50 border border-gray-800 rounded-xl p-5 flex items-center justify-between group transition-all cursor-pointer text-left relative overflow-hidden shadow-lg">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-center gap-4 z-10">
                        <div class="w-12 h-12 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-basket-shopping"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg">Market Listesi</h3>
                            <p class="text-gray-500 text-xs mt-0.5"><span id="market-count">0</span> Ürün Kalemi</p>
                        </div>
                    </div>
                    <div class="text-right z-10">
                        <div id="market-total-display" class="text-xl font-bold text-blue-400 font-mono">0,00 ₺</div>
                        <div class="text-xs text-gray-500 mt-1 group-hover:text-blue-400 transition-colors flex items-center justify-end gap-1">
                            Detay Gör <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </div>
                </button>
                <!-- Trendyol Card -->
                <button onclick="openDetailModal('trendyol')" class="bg-[#1e232d] hover:border-orange-500/50 border border-gray-800 rounded-xl p-5 flex items-center justify-between group transition-all cursor-pointer text-left relative overflow-hidden shadow-lg">
                    <div class="absolute inset-0 bg-gradient-to-r from-orange-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-center gap-4 z-10">
                        <div class="w-12 h-12 rounded-lg bg-orange-500/10 text-orange-500 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg">Trendyol Sepeti</h3>
                            <p class="text-gray-500 text-xs mt-0.5"><span id="trendyol-count">0</span> Sipariş</p>
                        </div>
                    </div>
                    <div class="text-right z-10">
                        <div id="trendyol-total-display" class="text-xl font-bold text-orange-400 font-mono">0,00 ₺</div>
                        <div class="text-xs text-gray-500 mt-1 group-hover:text-orange-400 transition-colors flex items-center justify-end gap-1">
                            Detay Gör <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </div>
                </button>
            </div>

            <!-- Main Table -->
            <div class="bg-[#1e232d] border border-gray-800 rounded-xl overflow-hidden shadow-lg">
                <div class="p-4 flex flex-col md:flex-row justify-between items-center gap-4 border-b border-gray-800">
                    <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <div class="flex bg-[#161b22] rounded-lg p-1 border border-gray-800 shrink-0">
                            <button onclick="setActiveTab('Genel')" id="tab-genel" class="px-4 md:px-6 py-2 rounded-md text-xs md:text-sm font-medium transition-all text-white bg-gray-700 shadow-sm whitespace-nowrap">Genel</button>
                            <button onclick="setActiveTab('Ötelenenler')" id="tab-deferred" class="px-4 md:px-6 py-2 rounded-md text-xs md:text-sm font-medium transition-all text-gray-400 hover:text-white whitespace-nowrap">Ötelenen</button>
                        </div>
                        <button onclick="openAddExpenseModal()" class="hidden md:flex px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors border border-emerald-500/50 shadow-sm items-center gap-2 text-sm font-medium shrink-0">
                            <i class="fa-solid fa-plus"></i> Yeni
                        </button>
                    </div>
                    <div class="relative w-full md:w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-500 text-xs"></i>
                        <input type="text" id="main-search" placeholder="Ara..." class="w-full bg-[#161b22] border border-gray-700 rounded-lg pl-9 pr-4 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>
                <!-- TABLE CONTAINER MODIFIED FOR MOBILE -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-[#161b22] text-gray-400 text-xs uppercase font-semibold border-b border-gray-800">
                            <tr>
                                <th class="px-4 py-4 w-10"></th>
                                <th class="px-6 py-4 w-12 text-center">#</th>
                                <th class="px-6 py-4">Kategori</th>
                                <th class="px-6 py-4">Açıklama / İsim</th>
                                <th class="px-6 py-4">Detay</th>
                                <th class="px-6 py-4">Durum</th>
                                <th class="px-6 py-4 text-right">Tutar</th>
                                <th class="px-4 py-4 w-10 text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="main-table-body" class="divide-y divide-gray-800/50 text-gray-300"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Roadmap Area -->
        <div class="col-span-12 bg-[#1e232d] border border-gray-800 rounded-xl p-6 animate-zoom-in shadow-lg">
            <div class="flex items-center gap-3 mb-6">
                <i class="fa-solid fa-map text-blue-400 text-xl"></i>
                <h2 class="text-xl font-bold text-white">Ödeme Roadmap</h2>
            </div>
            <div id="roadmap-stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8"></div>
            <div id="roadmap-timeline" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
        </div>
    </div>

    <!-- MODALS -->
    <!-- ADD EXPENSE MODAL -->
    <div id="add-expense-modal" class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden animate-fade-in no-print">
        <div class="bg-[#1e232d] w-full max-w-sm rounded-xl shadow-2xl border border-gray-800 flex flex-col overflow-hidden animate-zoom-in">
            <div class="bg-[#161b22] p-4 border-b border-gray-800">
                <h3 class="text-lg font-bold text-white text-center">Yeni Harcama Ekle</h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-400 block mb-1">Başlık / İsim</label>
                    <input type="text" id="add-title" class="w-full bg-[#0d1117] border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-400 block mb-1">Tutar</label>
                    <input type="number" step="0.01" id="add-amount" class="w-full bg-[#0d1117] border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-400 block mb-1">Kategori</label>
                    <select id="add-category" class="w-full bg-[#0d1117] border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                        <option value="Fatura">Fatura</option>
                        <option value="Abonelik">Abonelik</option>
                        <option value="Market">Market</option>
                        <option value="Gıda">Gıda</option>
                        <option value="Diğer">Diğer</option>
                        <option value="Borç">Borç</option>
                    </select>
                </div>
            </div>
            <div class="p-4 bg-[#161b22] border-t border-gray-800 flex gap-3">
                <button onclick="document.getElementById('add-expense-modal').classList.add('hidden'); document.getElementById('add-expense-modal').classList.remove('flex');" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">İptal</button>
                <button onclick="saveNewExpense()" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-lg transition-colors">Kaydet</button>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detail-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-0 md:p-4 bg-black/80 backdrop-blur-sm hidden animate-fade-in">
        <div class="bg-[#1e232d] w-full md:max-w-4xl rounded-none md:rounded-xl shadow-2xl border-0 md:border border-gray-800 flex flex-col h-full md:h-[90vh] animate-zoom-in">
            <div class="p-4 md:p-5 border-b border-gray-700/50 flex items-center justify-between bg-[#161b22] rounded-t-none md:rounded-t-xl no-print">
                <div class="flex items-center gap-3 md:gap-4">
                    <div id="detail-modal-icon-container" class="w-10 h-10 rounded-lg flex items-center justify-center text-xl shrink-0"></div>
                    <div>
                        <h3 id="detail-modal-title" class="text-base md:text-lg font-bold text-white">Liste Detayı</h3>
                        <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                            <span id="detail-modal-count">0 Kalem</span>
                            <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
                            <span id="detail-modal-total" class="text-emerald-400 font-mono">0,00 ₺</span>
                        </p>
                    </div>
                </div>
                <button onclick="closeDetailModal()" class="text-gray-400 hover:text-white transition-colors p-2"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-3 md:p-4 bg-[#1e232d] border-b border-gray-800 no-print">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-500 text-sm"></i>
                    <input type="text" id="detail-search" placeholder="Ara..." class="w-full bg-[#0d1117] border border-gray-700 rounded-lg pl-9 pr-4 py-2 text-sm text-white focus:outline-none focus:border-blue-500 transition-colors">
                </div>
            </div>
            <div class="overflow-y-auto flex-1 bg-[#0d1117] custom-scrollbar">
                <!-- MODAL TABLE MODIFIED FOR MOBILE -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="bg-[#161b22] text-gray-400 font-semibold border-b border-gray-800 sticky top-0 shadow-lg z-10 text-xs uppercase">
                            <tr>
                                <th class="px-6 py-3 w-12 text-center">#</th>
                                <th class="px-6 py-3">Ürün (Çift Tık)</th>
                                <th id="th-extra" class="px-6 py-3">Marka (Çift Tık)</th>
                                <th id="th-qty" class="px-6 py-3 text-center">Adet (Tek Tık)</th>
                                <th class="px-6 py-3 text-right">Tutar (Tek Tık)</th>
                                <th class="px-6 py-3 w-10 text-center">Sil</th>
                            </tr>
                        </thead>
                        <tbody id="detail-modal-body" class="divide-y divide-gray-800/50 text-gray-300"></tbody>
                    </table>
                </div>
            </div>
            <!-- QUICK ADD ROW FOR SUB ITEMS - RESPONSIVE STACK -->
            <div class="p-3 bg-[#13171f] border-t border-gray-800 no-print">
                <div class="flex flex-col sm:grid sm:grid-cols-12 gap-3 items-center">
                    <div class="w-full sm:col-span-4">
                        <input type="text" id="new-sub-name" placeholder="Ürün Adı" class="w-full bg-[#0d1117] border border-gray-700 rounded px-3 py-2 text-sm text-white focus:border-blue-500">
                    </div>
                    <div class="w-full sm:col-span-2">
                         <input type="number" id="new-sub-qty" value="1" placeholder="Adet" class="w-full bg-[#0d1117] border border-gray-700 rounded px-3 py-2 text-sm text-white focus:border-blue-500 text-center">
                    </div>
                    <div class="w-full sm:col-span-3">
                         <input type="number" step="0.01" id="new-sub-price" placeholder="Fiyat" class="w-full bg-[#0d1117] border border-gray-700 rounded px-3 py-2 text-sm text-white focus:border-blue-500">
                    </div>
                    <div class="w-full sm:col-span-3 text-right">
                        <button onclick="addSubItem()" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium py-2.5 rounded transition-colors"><i class="fa-solid fa-plus"></i> Ekle</button>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-700/50 flex justify-between items-center bg-[#1e232d] rounded-b-none md:rounded-b-xl no-print">
                <div class="text-gray-500 text-xs hidden sm:block">FinansOS Raporu</div>
                <div class="flex gap-3 w-full sm:w-auto">
                    <button onclick="window.print()" class="flex-1 sm:flex-none px-4 py-3 sm:py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Yazdır</span>
                    </button>
                    <button onclick="closeDetailModal()" class="flex-1 sm:flex-none px-6 py-3 sm:py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden animate-fade-in no-print">
        <div class="bg-[#1e232d] w-full max-w-md rounded-xl shadow-2xl border border-gray-800 flex flex-col overflow-hidden animate-zoom-in">
            <div class="bg-[#161b22] p-6 border-b border-gray-800 flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-500/10 rounded-full flex items-center justify-center text-blue-500 text-xl">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Ödeme Yap</h3>
                    <div id="pay-item-name" class="text-gray-400 text-sm mt-0.5">--</div>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div id="pay-info-box" class="bg-[#0d1117] rounded-lg p-4 border border-gray-700 space-y-3 hidden">
                    <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Ödeme Bilgileri</h4>
                    <div id="pay-subno-row" class="flex justify-between items-center hidden text-sm"><span class="text-gray-400">Abone No</span><span id="pay-subno-val" class="text-white font-mono">--</span></div>
                    <div id="pay-account-row" class="flex justify-between items-center hidden text-sm"><span class="text-gray-400">Hesap Sahibi</span><span id="pay-account-val" class="text-white">--</span></div>
                    <div id="pay-iban-row" class="flex flex-col gap-1 hidden text-sm"><span class="text-gray-400">IBAN</span><span id="pay-iban-val" class="text-emerald-400 font-mono bg-emerald-500/5 p-1.5 rounded border border-emerald-500/10 break-all">--</span></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-400 block mb-2">Ödenen Miktar</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-gray-400 font-sans font-bold">₺</span>
                        <input type="number" id="payment-amount-input" class="w-full bg-[#0d1117] border border-gray-700 rounded-lg pl-8 pr-4 py-3 text-white font-mono focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>
                <div class="flex justify-between items-center text-sm p-3 bg-gray-800/30 rounded-lg">
                    <span class="text-gray-400">Kalan Tutar:</span>
                    <span id="payment-remaining-display" class="font-mono font-bold text-emerald-400">0,00 ₺</span>
                </div>
                <div id="partial-warning" class="text-xs text-orange-400 bg-orange-400/10 p-3 rounded border border-orange-400/20 hidden flex items-center gap-2"><i class="fa-solid fa-circle-exclamation"></i> Kısmi ödeme yapılacak.</div>
            </div>
            <div class="p-4 bg-[#161b22] border-t border-gray-800 flex gap-3">
                <button onclick="closePaymentModal()" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">İptal</button>
                <button onclick="confirmPayment()" id="btn-confirm-payment" class="flex-1 py-3 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">Tamamını Öde</button>
            </div>
        </div>
    </div>

    <!-- PHP Verisini JS'e Aktar -->
    <script>
        const transactions = <?php echo json_encode($transactions); ?>;
    </script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>