<?php
session_start();

// --- AYARLAR ---
$admin_pass = 'admin123';
$host = 'localhost'; 
$db = 'timdesig_os'; 
$user = 'timdesig_xtreme'; 
$pass = 'Ayaz343591'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
    PDO::ATTR_EMULATE_PREPARES => false
];

try { 
    $pdo = new PDO($dsn, $user, $pass, $options); 
} catch (\PDOException $e) { 
    die("Veritabanı hatası: " . $e->getMessage()); 
}

// --- OTURUM ---
if (isset($_POST['login'])) { 
    if ($_POST['password'] === $admin_pass) { 
        $_SESSION['admin_logged_in'] = true; 
        header("Location: finans-edit.php"); 
        exit; 
    } else {
        $error = "Hatalı şifre!";
    }
}

if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: finans-edit.php"); 
    exit; 
}

// Login Formu
if (!isset($_SESSION['admin_logged_in'])) { 
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Giriş</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#0f111a] text-white flex items-center justify-center h-screen">
        <div class="bg-[#1e232d] p-8 rounded-xl border border-gray-800 w-96 shadow-2xl">
            <h2 class="text-2xl font-bold mb-6 text-center text-emerald-500">FinansOS Admin</h2>
            <?php if(isset($error)) echo "<div class='bg-red-500/10 text-red-400 p-3 rounded mb-4 text-xs'>$error</div>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Şifre" class="w-full bg-[#0f111a] border border-gray-700 rounded p-3 text-white mb-4 focus:border-emerald-500 outline-none">
                <button type="submit" name="login" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white p-3 rounded font-bold transition-colors">Giriş Yap</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit; 
}

// --- FONKSİYONLAR ---
function recalculateTotal($pdo, $expense_id) {
    $stmt = $pdo->prepare("SELECT SUM(price * quantity) as total FROM expense_items WHERE expense_id = ?");
    $stmt->execute([$expense_id]);
    $total = $stmt->fetch()['total'] ?? 0;
    if ($total > 0) { 
        $pdo->prepare("UPDATE expenses SET amount = ? WHERE id = ?")->execute([$total, $expense_id]); 
    }
}

// --- POST İŞLEMLERİ ---

// 1. Sıralama Güncelleme (Sidebar)
if (isset($_POST['update_order'])) {
    $pdo->prepare("UPDATE expenses SET sort_order = ? WHERE id = ?")->execute([$_POST['sort_order'], $_POST['id']]);
    header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit;
}

// 2. Ana Gider Kaydetme
if (isset($_POST['save_main'])) {
    $id = $_POST['id'] ?? null;
    $sort_order = $_POST['sort_order'] ?? 999; // Sıralama verisini al

    // Boş değerleri filtrele ve JSON yap
    $extra_data = json_encode(array_filter([
        'iban' => $_POST['iban'] ?? null, 
        'account_name' => $_POST['account_name'] ?? null, 
        'sub_no' => $_POST['sub_no'] ?? null
    ]), JSON_UNESCAPED_UNICODE);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE expenses SET title=?, category=?, type=?, amount=?, status=?, detail=?, extra_data=?, sort_order=? WHERE id=?");
        $stmt->execute([$_POST['title'], $_POST['category'], $_POST['type'], $_POST['amount'], $_POST['status'], $_POST['detail'], $extra_data, $sort_order, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (title, category, type, amount, status, detail, extra_data, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['category'], $_POST['type'], $_POST['amount'], $_POST['status'], $_POST['detail'], $extra_data, $sort_order]);
        $id = $pdo->lastInsertId();
    }
    header("Location: finans-edit.php?edit_id=$id"); 
    exit;
}

// 3. Ana Gider Silme
if (isset($_POST['delete_main'])) {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM expense_items WHERE expense_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    header("Location: finans-edit.php");
    exit;
}

// 4. Alt Kalem Kaydetme
if (isset($_POST['save_item'])) {
    $item_id = $_POST['item_id'] ?? null;
    $expense_id = $_POST['expense_id'];
    $sort_order = $_POST['sort_order'] ?? 999;
    
    if ($item_id) {
        $stmt = $pdo->prepare("UPDATE expense_items SET name=?, detail=?, quantity=?, price=?, unit=?, status=?, icon=?, sort_order=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['detail'], $_POST['quantity'], $_POST['price'], $_POST['unit'], $_POST['status'], $_POST['icon'], $sort_order, $item_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO expense_items (expense_id, name, detail, quantity, price, unit, status, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$expense_id, $_POST['name'], $_POST['detail'], $_POST['quantity'], $_POST['price'], $_POST['unit'], $_POST['status'], $_POST['icon'], $sort_order]);
    }
    recalculateTotal($pdo, $expense_id);
    header("Location: finans-edit.php?edit_id=$expense_id"); 
    exit;
}

// 5. Alt Kalem Silme
if (isset($_POST['delete_item'])) {
    $pdo->prepare("DELETE FROM expense_items WHERE id = ?")->execute([$_POST['item_id']]);
    recalculateTotal($pdo, $_POST['expense_id']);
    header("Location: finans-edit.php?edit_id=" . $_POST['expense_id']); 
    exit;
}

// 6. Öteleme İşlemi
if (isset($_POST['move_to_deferred'])) {
    $stmt = $pdo->prepare("SELECT * FROM expense_items WHERE id = ?"); 
    $stmt->execute([$_POST['item_id']]); 
    $item = $stmt->fetch();
    
    if ($item) {
        $total = $item['price'] * $item['quantity'];
        $title = $item['name'] . ($item['quantity'] > 1 ? " ({$item['quantity']} {$item['unit']})" : "");
        
        $pdo->prepare("INSERT INTO expenses (title, type, category, status, detail, amount, sort_order) VALUES (?, 'Ötelenen', 'Ötelenen', 'Planlanan', 'Listeden Taşındı', ?, 999)")
            ->execute([$title, $total]);
            
        $pdo->prepare("DELETE FROM expense_items WHERE id = ?")->execute([$_POST['item_id']]);
        recalculateTotal($pdo, $_POST['expense_id']);
    }
    header("Location: finans-edit.php?edit_id=" . $_POST['expense_id']); 
    exit;
}

// --- VERİ ÇEKME ---
$expenses = $pdo->query("SELECT * FROM expenses ORDER BY sort_order ASC, id DESC")->fetchAll();

// Kategorileri Veritabanından Çek (Autocomplete için)
$categories = $pdo->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$editExpense = null; 
$editItems = []; 
$extraData = [];

if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?"); 
    $stmt->execute([$_GET['edit_id']]); 
    $editExpense = $stmt->fetch();
    
    if ($editExpense && $editExpense['extra_data']) {
        $extraData = json_decode($editExpense['extra_data'], true) ?? [];
    }
    
    $stmtItems = $pdo->prepare("SELECT * FROM expense_items WHERE expense_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtItems->execute([$_GET['edit_id']]); 
    $editItems = $stmtItems->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinansOS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0f111a; color: #e5e7eb; font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; background: #0f111a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
        
        /* Genel Input Stili */
        input, select { 
            background-color: #0f111a; 
            border: 1px solid #374151; 
            color: white; 
            padding: 0.6rem 0.75rem; /* Padding arttırıldı */
            border-radius: 0.5rem; /* Radius arttırıldı */
            outline: none; 
            transition: all 0.2s;
            font-size: 0.875rem; /* 14px */
        }
        input:focus, select:focus { 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); /* Focus efekti eklendi */
        }
        
        /* Tablo inputlarını özelleştir */
        .table-input { background: transparent; border: none; padding: 0; height: 1.5rem; width: 100%; outline: none; box-shadow: none; border-radius: 0; }
        .table-input:focus { border-bottom: 1px solid #3b82f6; box-shadow: none; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">
    
    <!-- Navbar -->
    <header class="bg-[#161b22] border-b border-gray-800 p-4 flex justify-between items-center shrink-0">
        <h1 class="text-xl font-bold text-white">Finans<span class="text-emerald-400">OS</span> <span class="text-gray-500 font-normal text-sm">Admin</span></h1>
        <div class="flex gap-4">
            <a href="finans-edit.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-sm font-bold transition-colors">+ Yeni</a>
            <a href="?logout=true" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm transition-colors">Çıkış</a>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        
        <!-- Sidebar: List -->
        <aside class="w-80 bg-[#0f111a] border-r border-gray-800 flex flex-col overflow-hidden">
            <div class="p-4 border-b border-gray-800">
                <input type="text" placeholder="Gider Ara..." class="w-full text-sm bg-[#161b22] border-gray-700">
            </div>
            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1">
                <?php foreach($expenses as $ex): ?>
                    <div class="flex items-center p-2 rounded hover:bg-[#1e232d] group transition-colors <?php echo (isset($_GET['edit_id']) && $_GET['edit_id'] == $ex['id']) ? 'bg-[#1e232d] border-l-2 border-blue-500' : ''; ?>">
                        <!-- Sidebar Sıralama Input -->
                        <form method="POST" class="mr-2">
                            <input type="hidden" name="id" value="<?php echo $ex['id']; ?>">
                            <input type="hidden" name="update_order" value="1">
                            <input type="text" name="sort_order" value="<?php echo $ex['sort_order']; ?>" 
                                   class="w-8 h-6 text-center text-xs p-0 bg-transparent border-gray-700 text-gray-500 focus:text-white" 
                                   onchange="this.form.submit()">
                        </form>
                        <a href="?edit_id=<?php echo $ex['id']; ?>" class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline">
                                <span class="truncate text-sm font-medium text-gray-200"><?php echo $ex['title']; ?></span>
                                <span class="text-xs text-emerald-500 font-mono"><?php echo number_format($ex['amount'], 0); ?></span>
                            </div>
                            <div class="text-[10px] text-gray-500 flex justify-between">
                                <span><?php echo $ex['category']; ?></span>
                                <span class="<?php echo $ex['status'] == 'Ödendi' ? 'text-green-600' : 'text-orange-600'; ?>"><?php echo $ex['status']; ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 bg-[#0f111a] overflow-y-auto custom-scrollbar p-8">
            <div class="max-w-6xl mx-auto space-y-8">
                
                <!-- Ana Gider Formu -->
                <div class="bg-[#1e232d] rounded-xl border border-gray-800 p-6 shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-pen-to-square text-blue-500 mr-2"></i> Gider Detayı</h2>
                        <?php if($editExpense): ?>
                            <form method="POST" onsubmit="return confirm('Bu kaydı tamamen silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="id" value="<?php echo $editExpense['id']; ?>">
                                <button type="submit" name="delete_main" class="text-red-400 hover:text-red-300 text-xs uppercase font-bold tracking-wider"><i class="fa-solid fa-trash"></i> Sil</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <?php if($editExpense): ?><input type="hidden" name="id" value="<?php echo $editExpense['id']; ?>"><?php endif; ?>
                        
                        <div class="md:col-span-8">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Başlık</label>
                            <input type="text" name="title" value="<?php echo $editExpense['title']??''; ?>" required class="w-full">
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Sıralama</label>
                            <input type="number" name="sort_order" value="<?php echo $editExpense['sort_order'] ?? '999'; ?>" class="w-full">
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Kategori</label>
                            <input type="text" name="category" value="<?php echo $editExpense['category']??''; ?>" list="cats" class="w-full" autocomplete="off">
                            <datalist id="cats">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Tür</label>
                            <select name="type" class="w-full">
                                <option value="Gider">Gider</option>
                                <option value="Market" <?php if(($editExpense['type']??'')=='Market') echo 'selected'; ?>>Market</option>
                                <option value="Alışveriş" <?php if(($editExpense['type']??'')=='Alışveriş') echo 'selected'; ?>>Alışveriş</option>
                                <option value="Ötelenen" <?php if(($editExpense['type']??'')=='Ötelenen') echo 'selected'; ?>>Ötelenen</option>
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Tutar</label>
                            <input type="number" step="0.01" name="amount" value="<?php echo $editExpense['amount']??'0'; ?>" class="w-full font-mono text-emerald-400 font-bold">
                        </div>

                        <div class="md:col-span-4">
                             <label class="block text-xs font-medium text-gray-400 mb-1.5">Durum</label>
                             <select name="status" class="w-full">
                                <option value="Bekliyor" <?php echo ($editExpense['status'] ?? '') == 'Bekliyor' ? 'selected' : ''; ?>>Bekliyor</option>
                                <option value="Alınacak" <?php echo ($editExpense['status'] ?? '') == 'Alınacak' ? 'selected' : ''; ?>>Alınacak</option>
                                <option value="Ödendi" <?php echo ($editExpense['status'] ?? '') == 'Ödendi' ? 'selected' : ''; ?>>Ödendi</option>
                                <option value="Planlanan" <?php echo ($editExpense['status'] ?? '') == 'Planlanan' ? 'selected' : ''; ?>>Planlanan</option>
                            </select>
                        </div>
                        <div class="md:col-span-8">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Detay / Alt Başlık</label>
                            <input type="text" name="detail" value="<?php echo $editExpense['detail']??''; ?>" class="w-full">
                        </div>
                        
                        <!-- JSON Ekstra Veriler -->
                        <div class="md:col-span-12 grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-gray-700 pt-5 mt-2 bg-[#161b22]/50 p-4 rounded-lg">
                            <div>
                                <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">IBAN</label>
                                <input type="text" name="iban" placeholder="TR..." value="<?php echo $extraData['iban']??''; ?>" class="w-full text-xs font-mono bg-[#161b22]">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">Hesap Sahibi</label>
                                <input type="text" name="account_name" placeholder="Ad Soyad" value="<?php echo $extraData['account_name']??''; ?>" class="w-full text-xs bg-[#161b22]">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase font-bold text-gray-500 mb-1">Abone No</label>
                                <input type="text" name="sub_no" placeholder="No" value="<?php echo $extraData['sub_no']??''; ?>" class="w-full text-xs bg-[#161b22]">
                            </div>
                        </div>

                        <div class="md:col-span-12">
                             <button type="submit" name="save_main" class="w-full bg-blue-600 hover:bg-blue-500 rounded-lg py-3 text-sm font-bold transition-colors shadow-lg shadow-blue-500/20">Kaydet</button>
                        </div>
                    </form>
                </div>

                <!-- Alt Kalemler -->
                <?php if($editExpense): ?>
                <div class="bg-[#1e232d] rounded-xl border border-gray-800 p-6 shadow-lg">
                    <h3 class="font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-list-check text-orange-500"></i> Alt Kalemler</h3>
                    
                    <!-- Yeni Ekleme Formu -->
                    <form method="POST" class="flex flex-wrap gap-2 mb-4 bg-[#161b22] p-3 rounded items-center border border-gray-800">
                        <input type="hidden" name="expense_id" value="<?php echo $editExpense['id']; ?>">
                        
                        <div class="w-12">
                            <label class="text-[9px] text-gray-500 block mb-1">Sıra</label>
                            <input type="number" name="sort_order" value="999" class="w-full text-center text-xs p-1 h-8">
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="text-[9px] text-gray-500 block mb-1">Ürün Adı</label>
                            <input type="text" name="name" required class="w-full text-sm p-1 h-8">
                        </div>
                        <div class="w-24">
                            <label class="text-[9px] text-gray-500 block mb-1">Marka</label>
                            <input type="text" name="detail" class="w-full text-sm p-1 h-8">
                        </div>
                        <div class="w-16">
                            <label class="text-[9px] text-gray-500 block mb-1">Adet</label>
                            <input type="number" step="0.01" name="quantity" value="1" class="w-full text-center text-sm p-1 h-8">
                        </div>
                        <div class="w-16">
                            <label class="text-[9px] text-gray-500 block mb-1">Birim</label>
                            <input type="text" name="unit" value="Adet" class="w-full text-center text-sm p-1 h-8">
                        </div>
                        <div class="w-20">
                            <label class="text-[9px] text-gray-500 block mb-1">Fiyat</label>
                            <input type="number" step="0.01" name="price" required class="w-full text-right text-sm p-1 h-8">
                        </div>
                         <div class="w-24">
                            <label class="text-[9px] text-gray-500 block mb-1">Durum</label>
                            <select name="status" class="w-full text-xs p-1 h-8">
                                <option value="Alınacak">Alınacak</option>
                                <option value="Alındı">Alındı</option>
                            </select>
                        </div>
                        <div class="w-8 pt-4">
                            <button type="submit" name="save_item" class="bg-emerald-600 w-8 h-8 rounded flex items-center justify-center hover:bg-emerald-500 transition-colors"><i class="fa-solid fa-plus text-white"></i></button>
                        </div>
                    </form>

                    <!-- Alt Kalem Tablosu -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-400">
                            <thead class="bg-[#161b22] text-xs uppercase font-semibold text-gray-500">
                                <tr>
                                    <th class="p-2 w-12 text-center">Sıra</th>
                                    <th class="p-2">Ürün</th>
                                    <th class="p-2">Detay</th>
                                    <th class="p-2 w-16 text-center">Adet</th>
                                    <th class="p-2 w-16 text-center">Birim</th>
                                    <th class="p-2 w-24 text-right">Birim Fiyat</th>
                                    <th class="p-2 w-24 text-right">Toplam</th>
                                    <th class="p-2 w-24">Durum</th>
                                    <th class="p-2 w-24 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <?php if(empty($editItems)): ?>
                                    <tr><td colspan="9" class="p-4 text-center text-gray-600 italic">Kayıtlı alt kalem yok.</td></tr>
                                <?php endif; ?>
                                <?php foreach($editItems as $item): ?>
                                <tr class="hover:bg-[#161b22] group transition-colors">
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="expense_id" value="<?php echo $editExpense['id']; ?>">
                                        <input type="hidden" name="icon" value="<?php echo $item['icon']; ?>">
                                        
                                        <td class="p-1"><input type="number" name="sort_order" value="<?php echo $item['sort_order']; ?>" class="table-input text-center text-gray-500"></td>
                                        <td class="p-1"><input type="text" name="name" value="<?php echo $item['name']; ?>" class="table-input text-white font-medium"></td>
                                        <td class="p-1"><input type="text" name="detail" value="<?php echo $item['detail']; ?>" class="table-input"></td>
                                        <td class="p-1"><input type="number" step="0.01" name="quantity" value="<?php echo $item['quantity']; ?>" class="table-input text-center"></td>
                                        <td class="p-1"><input type="text" name="unit" value="<?php echo $item['unit']; ?>" class="table-input text-center text-xs"></td>
                                        <td class="p-1"><input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>" class="table-input text-right text-emerald-400 font-mono"></td>
                                        <td class="p-2 text-right text-gray-500 font-mono"><?php echo number_format($item['price']*$item['quantity'], 2); ?></td>
                                        <td class="p-1"><input type="text" name="status" value="<?php echo $item['status']; ?>" class="table-input text-xs"></td>
                                        
                                        <td class="p-1 text-right">
                                            <div class="flex justify-end gap-2 opacity-20 group-hover:opacity-100 transition-opacity">
                                                <button type="submit" name="save_item" title="Güncelle" class="text-blue-400 hover:text-white"><i class="fa-solid fa-check"></i></button>
                                                <button type="submit" name="move_to_deferred" title="Ötele ve Taşı" onclick="return confirm('Bu kalemi ana listeye taşımak istiyor musunuz?')" class="text-orange-400 hover:text-white"><i class="fa-solid fa-share-from-square"></i></button>
                                                <button type="submit" name="delete_item" title="Sil" onclick="return confirm('Silmek istediğinize emin misiniz?')" class="text-red-400 hover:text-white"><i class="fa-solid fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </main>
    </div>
</body>
</html>