let activeTab = 'Genel';
let mainSearchTerm = '';
let detailSearchTerm = '';
let selectedItemForPayment = null;
let paymentAmount = 0;
let currentDetailType = null;
let currentDetailMainId = null;

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1e232d',
    color: '#fff'
});

function init() {
    updateSummaryCards();
    setupEventListeners();
    renderDashboard();
    initSortable();
    setupEventDelegation();
}

function updateSummaryCards() {
    const marketItem = transactions.find(t => t.type === 'Market');
    const trendyolItem = transactions.find(t => t.type === 'Alışveriş' && t.category === 'Trendyol');
    
    if(marketItem) {
        $('#market-count').text(marketItem.items ? marketItem.items.length : 0);
        $('#market-total-display').text(formatMoney(marketItem.amount));
    }
    if(trendyolItem) {
        $('#trendyol-count').text(trendyolItem.items ? trendyolItem.items.length : 0);
        $('#trendyol-total-display').text(formatMoney(trendyolItem.amount));
    }
}

function setupEventListeners() {
    $('#main-search').on('input', function() { mainSearchTerm = $(this).val().toLowerCase(); renderTable(); });
    $('#detail-search').on('input', function() { detailSearchTerm = $(this).val().toLowerCase(); renderDetailModalContent(); });
    $('#payment-amount-input').on('input', function() { paymentAmount = parseFloat($(this).val()) || 0; updatePaymentModalUI(); });
}

function setupEventDelegation() {
    // Metin Alanları: Çift Tıklama
    $(document).on('dblclick', '.editable-sub-text', function() {
        handleEdit($(this), $(this).data('field'), $(this).data('id'), false);
    });

    // Sayısal Alanlar: Tek Tıklama
    $(document).on('click', '.editable-sub-number', function() {
        handleEdit($(this), $(this).data('field'), $(this).data('id'), true);
    });
}

function handleEdit(element, field, subId, isMoney) {
    if (element.find('input').length > 0) return; // Zaten input ise çık

    let mainItem = transactions.find(t => t.id === currentDetailMainId);
    let subItem = mainItem ? mainItem.items.find(i => i.id === subId) : null;
    if(!subItem) return;

    let currentVal = (field === 'price') ? subItem.price : (field === 'quantity') ? subItem.quantity : (field === 'name') ? subItem.name : subItem.detail;

    let input = $('<input>', {
        val: currentVal,
        type: (field === 'price' || field === 'quantity') ? 'number' : 'text',
        step: '0.01',
        class: 'bg-[#0f111a] text-white border border-blue-500 rounded px-2 py-1 text-xs w-full focus:outline-none'
    });

    element.html(input);
    input.focus();

    // Tıklama input içine hapsolsun (bubbling engelle)
    input.on('click', function(e) { e.stopPropagation(); });

    input.on('blur', function() {
        let newVal = $(this).val();
        if (newVal == currentVal) { renderDetailModalContent(); return; }

        fetch('finansal-rapor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_sub_item_field', id: subId, field: field, value: newVal })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                if (field === 'price' || field === 'quantity') subItem[field] = parseFloat(newVal);
                else subItem[field] = newVal;
                
                // Lokal güncelleme sonrası totali de güncellemek için mainItem tutarını yeniden hesapla
                if (mainItem.type === 'Market') {
                    mainItem.amount = mainItem.items.reduce((acc, i) => acc + (i.price * i.quantity), 0);
                } else {
                    mainItem.amount = mainItem.items.reduce((acc, i) => acc + i.price, 0);
                }

                renderDetailModalContent();
                renderDashboard();
                updateSummaryCards();
                Toast.fire({ icon: 'success', title: 'Güncellendi' });
            } else {
                Swal.fire('Hata', data.error || 'İşlem başarısız', 'error');
                renderDetailModalContent();
            }
        });
    });

    input.on('keypress', function(e) { if(e.which == 13) $(this).blur(); });
}

function renderDashboard() {
    calculateAndRenderStats();
    renderTable();
    renderRoadmap();
}

function calculateAndRenderStats() {
    const dollarRate = 42.44;
    const rentUSD = 2215;
    const totalIncome = rentUSD * dollarRate;
    const activeItems = transactions.filter(t => t.type !== 'Ötelenen');
    const grandTotalExpense = activeItems.reduce((acc, item) => acc + item.amount, 0);
    const deferredTotal = transactions.filter(t => t.type === 'Ötelenen').reduce((acc, item) => acc + item.amount, 0);
    const netStatus = totalIncome - grandTotalExpense;

    $('#sidebar-summary-list').html(`
        <div class="flex justify-between items-center p-3 bg-[#161b22] rounded-lg border border-gray-800"><div><div class="text-[10px] uppercase font-bold text-gray-500">Toplam Gelir</div><div class="text-sm font-bold text-emerald-400 font-mono">${formatMoney(totalIncome)}</div></div><i class="fa-solid fa-arrow-trend-up text-emerald-500/50"></i></div>
        <div class="flex justify-between items-center p-3 bg-[#161b22] rounded-lg border border-gray-800"><div><div class="text-[10px] uppercase font-bold text-gray-500">Toplam Gider</div><div class="text-sm font-bold text-red-400 font-mono">${formatMoney(grandTotalExpense)}</div></div><i class="fa-solid fa-arrow-trend-down text-red-500/50"></i></div>
        <div class="flex justify-between items-center p-3 bg-[#161b22] rounded-lg border border-gray-800"><div><div class="text-[10px] uppercase font-bold text-gray-500">Net Durum</div><div class="text-sm font-bold ${netStatus >= 0 ? 'text-emerald-400' : 'text-red-400'} font-mono">${formatMoney(netStatus)}</div></div><i class="fa-solid fa-piggy-bank text-blue-500/50"></i></div>
    `);
    $('#top-summary-cards').html(`
        ${createSummaryCard('TOPLAM GELİR', totalIncome, 'text-emerald-400', 'fa-arrow-trend-up')}
        ${createSummaryCard('TOPLAM GİDER', grandTotalExpense, 'text-red-400', 'fa-arrow-trend-down')}
        ${createSummaryCard('NET DURUM', netStatus, 'text-blue-400', 'fa-piggy-bank')}
        ${createSummaryCard('ÖTELENEN BORÇLAR', deferredTotal, 'text-orange-400', 'fa-clock')}
    `);
    
    const fixedTotal = transactions.filter(t => t.category === 'Fatura' || t.category === 'Abonelik' || t.category === 'Kira').reduce((acc, item) => acc + item.amount, 0);
    const marketTotal = transactions.filter(t => t.type === 'Market').reduce((acc, item) => acc + item.amount, 0);
    const renderProgressBar = (label, val, color) => {
        const percent = grandTotalExpense > 0 ? Math.min(100, (val / grandTotalExpense) * 100) : 0;
        return `<div><div class="flex justify-between text-xs mb-1 text-gray-400"><span>${label}</span><span class="font-mono">${formatMoney(val)}</span></div><div class="w-full bg-gray-800 rounded-full h-1.5"><div class="h-1.5 rounded-full ${color}" style="width: ${percent}%"></div></div></div>`;
    };
    $('#expense-breakdown').html(`
        ${renderProgressBar('Sabit (Fatura/Kira)', fixedTotal, 'bg-red-500')}
        ${renderProgressBar('Market / Gıda', marketTotal, 'bg-blue-500')}
        ${renderProgressBar('Ötelenenler', deferredTotal, 'bg-gray-500')}
    `);
}

function createSummaryCard(title, value, colorClass, icon) {
    return `<div class="bg-[#1e232d] rounded-xl p-5 border border-gray-800 flex items-center justify-between relative overflow-hidden"><div><div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">${title}</div><div class="text-xl font-bold font-mono ${colorClass}">${formatMoney(value)}</div></div><div class="p-2 bg-[#161b22] rounded-lg text-gray-600"><i class="fa-solid ${icon}"></i></div></div>`;
}

function renderTable() {
    let data = activeTab === 'Ötelenenler' ? transactions.filter(t => t.type === 'Ötelenen') : transactions.filter(t => t.type !== 'Ötelenen');
    if (mainSearchTerm) data = data.filter(item => item.title.toLowerCase().includes(mainSearchTerm) || item.category.toLowerCase().includes(mainSearchTerm));
    data.sort((a, b) => a.order - b.order);

    const html = data.map((item, idx) => `
        <tr class="hover-row transition-colors group border-b border-gray-800/50 last:border-0 ${item.status === 'Ödendi' ? 'opacity-50' : ''}" data-id="${item.id}">
            <td class="px-4 py-4 text-center text-gray-600 cursor-grab drag-handle"><i class="fa-solid fa-grip-lines"></i></td>
            <td class="px-6 py-4 text-center text-gray-600 font-mono text-xs">${idx + 1}</td>
            <td class="px-6 py-4 editable-cell" ondblclick="makeEditable(this, 'category', ${item.id})">${renderCategoryBadge(item.category)}</td>
            <td class="px-6 py-4">
                <div class="font-medium text-white text-sm flex items-center gap-2">
                    ${item.status === 'Ödendi' ? '<i class="fa-solid fa-check text-emerald-500 text-xs"></i>' : ''}
                    <span class="${item.status === 'Ödendi' ? 'line-through text-gray-500' : ''} editable-cell" ondblclick="makeEditable(this, 'title', ${item.id})">${item.title}</span>
                </div>
                ${(item.type === 'Market' || (item.type === 'Alışveriş' && item.category === 'Trendyol')) ? '<span class="text-[10px] text-blue-400 cursor-pointer hover:underline mt-1 block" onclick="openDetailModal(\'' + (item.type === 'Market' ? 'market' : 'trendyol') + '\')">Listeyi Görüntüle</span>' : ''}
            </td>
            <td class="px-6 py-4 text-gray-500 text-xs editable-cell" ondblclick="makeEditable(this, 'detail', ${item.id})">${item.detail || '-'}</td>
            <td class="px-6 py-4 cursor-pointer" onclick="changeStatus(${item.id}, '${item.status}')">${renderStatusBadge(item.status)}</td>
            <td class="px-6 py-4 text-right font-mono font-bold text-sm text-white ${item.status === 'Ödendi' ? 'text-emerald-500' : ''} editable-cell" onclick="makeEditable(this, 'amount', ${item.id}, true)">${formatMoney(item.amount)}</td>
            <td class="px-4 py-4 text-center"><button onclick="deleteExpense(${item.id})" class="btn-delete text-gray-500 hover:text-red-500 transition-colors p-2"><i class="fa-solid fa-trash-can"></i></button></td>
        </tr>
    `).join('');
    $('#main-table-body').html(data.length ? html : '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-500 text-sm">Kayıt bulunamadı.</td></tr>');
}

function initSortable() {
    new Sortable(document.getElementById('main-table-body'), {
        animation: 150, handle: '.drag-handle',
        onEnd: function (evt) {
            var order = [];
            $('#main-table-body tr').each(function() { order.push($(this).data('id')); });
            fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'update_order_batch', order: order }) })
            .then(res => res.json()).then(data => { if(data.success) { order.forEach((id, index) => { let item = transactions.find(t => t.id == id); if(item) item.order = index + 1; }); renderRoadmap(); Toast.fire({ icon: 'success', title: 'Sıralama güncellendi' }); } else { Swal.fire('Hata', data.error || 'Yetkisiz', 'error'); }});
        }
    });
}

function makeEditable(element, field, id, isMoney = false) {
    if ($(element).find('input').length > 0) return;
    let currentVal = $(element).text().trim();
    if (isMoney) { currentVal = currentVal.replace(/[^0-9,-]/g, '').replace(',', '.'); let item = transactions.find(t => t.id == id); if(item) currentVal = item.amount; }
    let input = $('<input>', { val: currentVal, type: isMoney ? 'number' : 'text', step: '0.01', class: 'bg-[#0f111a] text-white border border-blue-500 rounded px-2 py-1 text-xs w-full focus:outline-none' });
    $(element).html(input); input.focus();
    input.on('blur', function() {
        let newVal = $(this).val();
        if (newVal == currentVal) { renderTable(); return; }
        fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'update_field', id: id, field: field, value: newVal }) })
        .then(res => res.json()).then(data => { if(data.success) { let item = transactions.find(t => t.id == id); if(item) { if(field === 'amount') item.amount = parseFloat(newVal); else item[field] = newVal; } renderDashboard(); Toast.fire({ icon: 'success', title: 'Güncellendi' }); } else { Swal.fire('Hata', data.error || 'İşlem başarısız', 'error'); renderTable(); }});
    });
    input.on('keypress', function(e) { if(e.which == 13) $(this).blur(); });
}

function changeStatus(id, currentStatus) {
    Swal.fire({
        title: 'Durum Değiştir', input: 'select', inputOptions: { 'Bekliyor': 'Bekliyor', 'Ödendi': 'Ödendi', 'Kısmi Ödeme': 'Kısmi Ödeme', 'Alınacak': 'Alınacak' }, inputValue: currentStatus,
        showCancelButton: true, confirmButtonText: 'Kaydet', confirmButtonColor: '#3b82f6', background: '#1e232d', color: '#fff'
    }).then((result) => {
        if (result.isConfirmed && result.value !== currentStatus) {
            fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'update_field', id: id, field: 'status', value: result.value }) })
            .then(res => res.json()).then(data => { if(data.success) { let item = transactions.find(t => t.id == id); if(item) item.status = result.value; renderDashboard(); Toast.fire({ icon: 'success', title: 'Durum güncellendi' }); } else { Swal.fire('Hata', data.error || 'İşlem başarısız', 'error'); }});
        }
    });
}

function openDetailModal(type) {
    currentDetailType = type;
    const isMarket = type === 'market';
    $('#detail-search').val(''); detailSearchTerm = '';
    let mainItem = isMarket ? transactions.find(t => t.type === 'Market') : transactions.find(t => t.type === 'Alışveriş' && t.category === 'Trendyol');
    currentDetailMainId = mainItem ? mainItem.id : null;
    const icon = isMarket ? 'fa-basket-shopping' : 'fa-truck';
    const colorClass = isMarket ? 'bg-blue-500/10 text-blue-500' : 'bg-orange-500/10 text-orange-500';
    $('#detail-modal-icon-container').attr('class', `w-10 h-10 rounded-lg flex items-center justify-center text-xl shrink-0 ${colorClass}`).html(`<i class="fa-solid ${icon}"></i>`);
    $('#detail-modal-title').text(isMarket ? 'Market Alışveriş Listesi' : 'Trendyol Sepeti');
    if (isMarket) { $('#th-extra').text('Marka (Çift Tık)').show(); $('#th-qty').show(); } else { $('#th-extra').hide(); $('#th-qty').hide(); }
    renderDetailModalContent();
    $('#detail-modal').removeClass('hidden').addClass('flex');
}

function renderDetailModalContent() {
    const isMarket = currentDetailType === 'market';
    let mainItem = transactions.find(t => t.id === currentDetailMainId);
    let data = mainItem && mainItem.items ? mainItem.items : [];
    if (detailSearchTerm) { data = data.filter(item => { const term = detailSearchTerm.toLowerCase(); const name = isMarket ? item.urun : item.name; return name && name.toLowerCase().includes(term); }); }
    const totalAmount = isMarket ? data.reduce((acc, item) => acc + (item.fiyat * item.adet), 0) : data.reduce((acc, item) => acc + item.price, 0);
    $('#detail-modal-count').text(data.length + ' Kalem'); $('#detail-modal-total').text(formatMoney(totalAmount));

    const html = data.map((item, idx) => {
        const totalRow = isMarket ? item.fiyat * item.adet : item.price;
        const name = isMarket ? item.urun : item.name;
        return `
            <tr class="hover:bg-[#161b22] transition-colors border-b border-gray-800/50 last:border-0 group">
                <td class="px-6 py-3 text-gray-600 text-center font-mono text-xs">${idx + 1}</td>
                <td class="px-6 py-3 text-sm text-white editable-sub-text" data-field="name" data-id="${item.id}">${name}</td>
                ${isMarket ? `<td class="px-6 py-3 text-xs text-gray-500 editable-sub-text" data-field="detail" data-id="${item.id}">${item.marka || '-'}</td><td class="px-6 py-3 text-center editable-sub-number" data-field="quantity" data-id="${item.id}"><span class="bg-gray-800 text-gray-300 px-2 py-0.5 rounded text-xs">${item.adet}</span></td>` : ''}
                <td class="px-6 py-3 text-right font-mono text-sm text-emerald-400 editable-sub-number" data-field="price" data-id="${item.id}">${formatMoney(isMarket ? item.fiyat : item.price)}</td>
                <td class="px-6 py-3 text-center"><button onclick="deleteSubItem(${item.id})" class="text-gray-600 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"><i class="fa-solid fa-trash-can"></i></button></td>
            </tr>
        `;
    }).join('');
    $('#detail-modal-body').html(data.length ? html : '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Kayıt bulunamadı.</td></tr>');
}

function addSubItem() {
    const name = $('#new-sub-name').val(); const qty = $('#new-sub-qty').val(); const price = $('#new-sub-price').val();
    if(!name || !price || !currentDetailMainId) return;
    fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add_sub_item', expense_id: currentDetailMainId, name, quantity: qty, price }) })
    .then(res => res.json()).then(data => { if(data.success) { location.reload(); } else { Swal.fire('Hata', data.error || 'Yetkisiz', 'error'); }});
}

function deleteSubItem(subId) {
    Swal.fire({ title: 'Silinsin mi?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Sil', background: '#1e232d', color: '#fff' }).then((result) => { if (result.isConfirmed) { fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_sub_item', id: subId }) }).then(res => res.json()).then(data => { if(data.success) { location.reload(); } else { Swal.fire('Hata', data.error || 'Yetkisiz', 'error'); }}); } });
}

function openAddExpenseModal() { $('#add-title').val(''); $('#add-amount').val(''); $('#add-expense-modal').removeClass('hidden').addClass('flex'); }

function saveNewExpense() { const title = $('#add-title').val(); const amount = $('#add-amount').val(); const category = $('#add-category').val(); if(!title || !amount) { Swal.fire('Hata', 'Lütfen alanları doldurun.', 'warning'); return; } fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add_expense', title, amount, category }) }).then(res => res.json()).then(data => { if(data.success) { location.reload(); } else { Swal.fire('Hata', data.error || 'Yetkisiz', 'error'); }}); }

function deleteExpense(id) { Swal.fire({ title: 'Emin misiniz?', text: "Bu kayıt kalıcı olarak silinecek!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Evet, sil!', cancelButtonText: 'İptal', background: '#1e232d', color: '#fff' }).then((result) => { if (result.isConfirmed) { fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_expense', id: id }) }).then(res => res.json()).then(data => { if(data.success) { location.reload(); } else { Swal.fire('Hata', data.error || 'Yetkisiz', 'error'); }}); } }); }

function closeDetailModal() { $('#detail-modal').addClass('hidden').removeClass('flex'); }

function openPaymentModal(id) { selectedItemForPayment = transactions.find(t => t.id == id); if (!selectedItemForPayment) return; paymentAmount = selectedItemForPayment.amount; $('#payment-amount-input').val(paymentAmount); $('#pay-item-name').text(selectedItemForPayment.title); const { subNo, iban, accountName } = selectedItemForPayment; if(subNo || iban || accountName) { $('#pay-info-box').removeClass('hidden'); $('#pay-subno-row').toggleClass('hidden', !subNo); if(subNo) $('#pay-subno-val').text(subNo); $('#pay-account-row').toggleClass('hidden', !accountName); if(accountName) $('#pay-account-val').text(accountName); $('#pay-iban-row').toggleClass('hidden', !iban); if(iban) $('#pay-iban-val').text(iban); } else { $('#pay-info-box').addClass('hidden'); } updatePaymentModalUI(); $('#payment-modal').removeClass('hidden').addClass('flex'); }

function updatePaymentModalUI() { if (!selectedItemForPayment) return; const remaining = Math.max(0, selectedItemForPayment.amount - paymentAmount); $('#payment-remaining-display').text(formatMoney(remaining)); if (remaining > 0.01 && paymentAmount > 0) { $('#payment-remaining-display').addClass('text-orange-400').removeClass('text-emerald-400'); $('#partial-warning').removeClass('hidden'); $('#btn-confirm-payment').text('Kısmi Öde'); } else { $('#payment-remaining-display').addClass('text-emerald-400').removeClass('text-orange-400'); $('#partial-warning').addClass('hidden'); $('#btn-confirm-payment').text('Tamamını Öde'); } }

function confirmPayment() { if (!selectedItemForPayment) return; fetch('finansal-rapor.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'payment', id: selectedItemForPayment.id, amount: paymentAmount, total: selectedItemForPayment.amount }) }).then(response => response.json()).then(data => { if(data.success) { Swal.fire({ icon: 'success', title: 'Ödeme Alındı', showConfirmButton: false, timer: 1500, background: '#1e232d', color: '#fff' }).then(() => location.reload()); } else { Swal.fire('Hata', data.error || 'İşlem başarısız', 'error'); } }); closePaymentModal(); }

function closePaymentModal() { $('#payment-modal').addClass('hidden').removeClass('flex'); selectedItemForPayment = null; }

function renderRoadmap() { const activeTransactions = transactions.filter(t => t.type !== 'Ötelenen').sort((a, b) => a.order - b.order); const roadmapCompleted = activeTransactions.filter(t => t.status === 'Ödendi').reduce((acc, t) => acc + t.amount, 0); const roadmapRemaining = activeTransactions.filter(t => t.status !== 'Ödendi').reduce((acc, t) => acc + t.amount, 0); const roadmapTotal = roadmapCompleted + roadmapRemaining; const totalIncome = 2215 * 42.44; const currentBalance = totalIncome - roadmapCompleted; const statsHtml = `<div class="bg-[#161b22] border border-gray-800 p-4 rounded-lg"><div class="text-gray-500 text-[10px] uppercase font-bold mb-1">Toplam Ödeme</div><div class="text-xl font-bold text-white font-mono">${formatMoney(roadmapTotal)}</div><div class="text-[10px] text-gray-600 mt-1">${activeTransactions.length} Adet</div></div><div class="bg-[#161b22] border border-gray-800 p-4 rounded-lg relative overflow-hidden"><div class="absolute right-0 top-0 w-16 h-16 bg-emerald-500/5 rounded-bl-full"></div><div class="text-emerald-500/70 text-[10px] uppercase font-bold mb-1">Tamamlanan</div><div class="text-xl font-bold text-emerald-400 font-mono">${formatMoney(roadmapCompleted)}</div><div class="text-[10px] text-emerald-600/70 mt-1">${activeTransactions.filter(t => t.status === 'Ödendi').length} Adet</div></div><div class="bg-[#161b22] border border-orange-900/20 p-4 rounded-lg border-l-2 border-l-orange-500/50"><div class="text-orange-500/70 text-[10px] uppercase font-bold mb-1">Kalan Ödeme</div><div class="text-xl font-bold text-orange-400 font-mono">${formatMoney(roadmapRemaining)}</div><div class="text-[10px] text-orange-600/70 mt-1">${activeTransactions.filter(t => t.status !== 'Ödendi').length} Adet</div></div><div class="bg-[#161b22] border border-blue-900/20 p-4 rounded-lg border-l-2 border-l-blue-500/50"><div class="text-blue-500/70 text-[10px] uppercase font-bold mb-1">Kalan Bakiye (Nakit)</div><div class="text-xl font-bold text-blue-400 font-mono">${formatMoney(currentBalance)}</div><div class="text-[10px] text-blue-600/70 mt-1">Gelir - Ödenen</div></div>`; $('#roadmap-stats').html(statsHtml); const timelineHtml = activeTransactions.map((item, idx) => `<div class="relative bg-[#161b22] p-4 rounded-lg border border-gray-800 group hover:border-gray-700 transition-all overflow-hidden ${item.status === 'Ödendi' ? 'opacity-60' : ''}"><div class="absolute left-2 bottom-[-15px] text-[80px] font-black text-gray-800/20 leading-none select-none pointer-events-none z-0 font-mono">${item.order}</div><div class="relative z-10 flex justify-between items-center h-full pl-6"><div class="pr-8 pl-4"><div class="text-sm font-bold text-white flex items-center gap-2 mb-1">${item.title} ${renderCategoryBadge(item.category)}</div><div class="text-xs text-gray-500 font-mono">${item.detail || '-'}</div>${(item.type === 'Market' || (item.type === 'Alışveriş' && item.category === 'Trendyol')) ? '<span class="text-[10px] text-blue-400 cursor-pointer hover:underline mt-1 block" onclick="openDetailModal(\'' + (item.type === 'Market' ? 'market' : 'trendyol') + '\')">Listeyi Görüntüle</span>' : ''}</div><div class="text-right flex flex-col items-end gap-2 pl-4 border-l border-gray-800 ml-4 min-w-[120px]"><div class="font-mono font-bold text-sm ${item.status === 'Ödendi' ? 'text-emerald-500' : 'text-white'}">${formatMoney(item.amount)}</div>${renderStatusBadge(item.status)}${item.status !== 'Ödendi' ? `<button onclick="openPaymentModal(${item.id})" class="px-4 py-1.5 border border-emerald-500/50 text-emerald-500 hover:bg-emerald-500/10 rounded text-xs font-medium transition-colors shadow-[0_0_10px_rgba(16,185,129,0.2)] w-full flex justify-center items-center gap-1">Ödeme Yap</button>` : ''}</div></div></div>`).join(''); $('#roadmap-timeline').html(timelineHtml); }

function renderCategoryBadge(c) { return `<span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border text-gray-400 bg-gray-400/10 border-gray-400/20">${c}</span>`; }
function renderStatusBadge(s) { return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium border text-gray-400 bg-gray-400/10 border-gray-400/20">${s}</span>`; }
function formatMoney(a) { return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(a); }

function setActiveTab(tab) {
    activeTab = tab;
    if(tab === 'Genel') {
        $('#tab-genel').removeClass('text-gray-400 hover:text-white').addClass('text-white bg-gray-700 shadow-sm');
        $('#tab-deferred').removeClass('text-white bg-gray-700 shadow-sm').addClass('text-gray-400 hover:text-white');
    } else {
        $('#tab-deferred').removeClass('text-gray-400 hover:text-white').addClass('text-white bg-gray-700 shadow-sm');
        $('#tab-genel').removeClass('text-white bg-gray-700 shadow-sm').addClass('text-gray-400 hover:text-white');
    }
    renderTable();
}

$(function() { init(); });