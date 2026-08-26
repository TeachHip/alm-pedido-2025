// Generic drag-and-drop row reordering for admin tables, backed by SortableJS
// (load https://cdn.jsdelivr.net/npm/sortablejs before this file). Shared by
// product and section reordering.
//
// Usage: initSortableList(tbodyElement, { dataKey: 'productId', saveUrl: 'update-order.php' })
// dataKey matches the row's data-* attribute in camelCase (data-product-id -> 'productId').
function initSortableList(tbody, options) {
    if (!tbody) return;
    const dataKey = options.dataKey;
    const saveUrl = options.saveUrl;

    new Sortable(tbody, {
        animation: 150,
        // Only the ⋮⋮ handle starts a drag -- 'tr' made the WHOLE row
        // draggable, which hijacked vertical scroll gestures on touch
        // devices (any swipe on a row reordered products instead of
        // scrolling the page). The .drag-handle markup already existed in
        // both products.php/sections.php; it just wasn't wired up as the
        // actual handle selector.
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function() {
            saveSortOrder(tbody, dataKey, saveUrl);
        }
    });
}

function saveSortOrder(tbody, dataKey, saveUrl) {
    const rows = tbody.querySelectorAll('tr');
    const orders = [];

    rows.forEach(function(row, index) {
        orders.push({ id: row.dataset[dataKey], order: index + 1 });
    });

    fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orders: orders })
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showSaveOrderNotice();
            } else {
                alert('Error al guardar el orden');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error al guardar el orden');
        });
}

function showSaveOrderNotice() {
    const notice = document.querySelector('.save-order-notice');
    if (!notice) return;
    notice.style.display = 'block';
    setTimeout(function() {
        notice.style.display = 'none';
    }, 2000);
}
