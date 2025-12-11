// Sayfa tamamen yüklendiğinde çalışacak kodlar
document.addEventListener('DOMContentLoaded', () => {
    
    // 1. İkonları Oluştur (Görünmeme sorununu çözer)
    lucide.createIcons();

    // 2. Kutulara Tıklama Özelliği Ekle (Tıklanmama sorununu çözer)
    const cards = document.querySelectorAll('.app-card');
    cards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Kartın içindeki başlığı al (HTML'deki span'dan)
            const titleElement = card.querySelector('span');
            if (titleElement) {
                const appName = titleElement.innerText.trim();
                openMenu(appName, e);
            }
        });
    });

    // 3. Login Formu Dinleme
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const msgDiv = document.getElementById('loginMessage');
            const spinner = document.getElementById('loginSpinner');
            const btnText = document.querySelector('#loginBtn span');
            const btn = document.getElementById('loginBtn');

            msgDiv.classList.add('hidden');
            spinner.classList.remove('hidden');
            btnText.textContent = 'Giriş Yapılıyor...';
            btn.disabled = true;

            try {
                const response = await fetch('index.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    msgDiv.textContent = result.message;
                    msgDiv.className = 'bg-red-500/10 border border-red-500/50 text-red-200 text-sm p-3 rounded-lg mb-4 text-center block';
                    spinner.classList.add('hidden');
                    btnText.textContent = 'Giriş Yap';
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Login error:', error);
                msgDiv.textContent = 'Bir bağlantı hatası oluştu.';
                msgDiv.className = 'bg-red-500/10 border border-red-500/50 text-red-200 text-sm p-3 rounded-lg mb-4 text-center block';
                spinner.classList.add('hidden');
                btnText.textContent = 'Giriş Yap';
                btn.disabled = false;
            }
        });
    }

    // 4. Logout Butonu Dinleme
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            if(!confirm('Çıkış yapmak istediğinize emin misiniz?')) return;
            const formData = new FormData();
            formData.append('action', 'logout');
            try {
                const response = await fetch('index.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) window.location.reload();
            } catch (error) {
                console.error('Logout error:', error);
            }
        });
    }
});

// --- MENÜ VERİLERİ ---
const subMenus = {
    'Finans': [
        { title: 'Ödeme Planı', icon: 'chevron-right', url: 'finansal-rapor.html' },
        { title: 'Market Stok', icon: 'chevron-right' },
        { title: 'Aylık Giderler', icon: 'chevron-right' },
        { title: 'Borçlar Tablosu', icon: 'chevron-right' }
    ],
    'Alışveriş': [
        { title: 'Favori Ürünler', icon: 'heart' },
        { title: 'Selecting Stars', icon: 'star' },
        { title: 'Tüm Ürünler', icon: 'shopping-bag' }
    ],
    'Diğer': [
        { title: 'PC Build', icon: 'cpu', url: 'pc-build.html' },
        { title: 'Genel Bakış', icon: 'activity' },
        { title: 'Ayarlar', icon: 'settings' }
    ],
    'default': [
        { title: 'Genel Bakış', icon: 'activity' },
        { title: 'Detaylar', icon: 'list' },
        { title: 'Seçenekler', icon: 'settings' }
    ]
};

// --- MENÜ AÇMA MANTIĞI ---
function openMenu(appName, event) {
    event.stopPropagation(); // Tıklamanın yayılmasını engelle

    const overlay = document.getElementById('menuOverlay');
    const menu = document.getElementById('floatingMenu');
    const title = document.getElementById('menuTitle');
    const list = document.getElementById('menuItems');

    if (!menu || !overlay) return;

    title.textContent = appName;
    list.innerHTML = '';

    const items = subMenus[appName] || subMenus['default'];

    items.forEach(item => {
        const button = document.createElement('button');
        button.className = "w-full flex items-center justify-between p-3 md:p-2.5 bg-[#1f242e] hover:bg-[#2a303d] text-gray-300 hover:text-white rounded-lg transition-all duration-200 group text-sm";
        button.innerHTML = `
            <span class="font-medium">${item.title}</span>
            <i data-lucide="${item.icon}" class="w-4 h-4 text-gray-500 group-hover:text-white transition-colors"></i>
        `;
        if (item.url) {
            button.onclick = () => window.location.href = item.url;
        } else {
            button.onclick = () => alert(item.title + ' sayfası açılıyor...');
        }
        list.appendChild(button);
    });

    // Yeni eklenen ikonları oluştur
    lucide.createIcons();

    // Mobil Kontrolü
    const isMobile = window.innerWidth < 768;

    // Önce menüyü temizle
    menu.style.top = '';
    menu.style.left = '';
    menu.style.transform = '';
    
    if (isMobile) {
        // --- MOBİL MODU (ORTADA POP-UP) ---
        menu.style.position = 'fixed';
        menu.style.top = '50%';
        menu.style.left = '50%';
        menu.style.transform = 'translate(-50%, -50%) scale(0.95)';
        
        setTimeout(() => {
            menu.style.transform = 'translate(-50%, -50%) scale(1)';
        }, 10);

        menu.style.width = '90%';
        menu.style.maxWidth = '350px';
    } else {
        // --- MASAÜSTÜ MODU (MOUSE YANINDA) ---
        // rect bilgisini alırken hata olmaması için kontrol
        const target = event.currentTarget || event.target; 
        // target bir element olmayabilir, en yakın .app-card'ı bulalım
        const card = target.closest('.app-card');
        
        if (card) {
            const rect = card.getBoundingClientRect();
            const menuWidth = 320;
            const gap = 12;
            const scrollX = window.scrollX || 0;
            const scrollY = window.scrollY || 0;

            let left = rect.right + gap + scrollX;
            let top = rect.top + scrollY;

            menu.style.position = 'absolute';
            menu.style.width = '320px';

            // Sağa sığmıyorsa sola al
            if (rect.right + gap + menuWidth > window.innerWidth) {
                left = rect.left - menuWidth - gap + scrollX;
                menu.style.transformOrigin = 'top right';
            } else {
                menu.style.transformOrigin = 'top left';
            }

            menu.style.top = `${top}px`;
            menu.style.left = `${left}px`;
            menu.style.transform = 'scale(1)';
        }
    }

    overlay.classList.add('active');
    menu.classList.add('active');
}

function closeMenu() {
    const overlay = document.getElementById('menuOverlay');
    const menu = document.getElementById('floatingMenu');
    if (overlay && menu) {
        overlay.classList.remove('active');
        menu.classList.remove('active');
        setTimeout(() => {
             menu.style.transform = '';
        }, 200);
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === "Escape") closeMenu();
});

window.addEventListener('resize', closeMenu);