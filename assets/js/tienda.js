// =============================================
// TIENDA JS - Cliente (catalogo, carrito y checkout)
// =============================================
let carrito = JSON.parse(localStorage.getItem('nexus_carrito') || '{}');
let productoPendiente = null;
let modalCantidadRef = null;
let modalZoomProductoRef = null;
const tiendaConfig = window.tiendaConfig || {};

document.addEventListener('DOMContentLoaded', () => {
    renderCarrito();
    updateClock();
    setInterval(updateClock, 1000);
    initSearch();
    initModalCantidad();
    initFilterDropdowns();
    initPedidoNotifications();
    initFeaturedCarousel();
    initAutoProductoCatalogo();

    document.querySelectorAll('.pago-option').forEach(opt => {
        opt.addEventListener('click', () => {
            document.querySelectorAll('.pago-option').forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
        });
    });
});

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizarTexto(v) {
    const raw = String(v || '').toLowerCase().trim();
    if (!raw) return '';
    try {
        return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (_) {
        return raw;
    }
}

function normalizarFiltro(v) {
    const base = normalizarTexto(v);
    if (!base) return '';
    return base.replace(/\s+/g, '-');
}

function initSearch() {
    const input = document.getElementById('searchInput');

    if (input) {
        input.addEventListener('input', filtrarProductos);
        input.addEventListener('keyup', filtrarProductos);
    }
    initPillFilters();
    filtrarProductos();
}

function initPillFilters() {
    document.querySelectorAll('.tienda-pill-group').forEach((group) => {
        const pills = group.querySelectorAll('.filtro-pill');
        pills.forEach((pill) => {
            pill.addEventListener('click', () => {
                pills.forEach((p) => p.classList.remove('active'));
                pill.classList.add('active');
                actualizarEtiquetasFiltros();
                const dropdown = pill.closest('[data-filter-dropdown]');
                if (dropdown) dropdown.classList.remove('open');
                filtrarProductos();
            });
        });
    });
    actualizarEtiquetasFiltros();
}

function getActivePillValue(groupId) {
    const active = document.querySelector(`#${groupId} .filtro-pill.active`);
    return normalizarFiltro(active?.dataset?.value || '');
}

function initModalCantidad() {
    const modalEl = document.getElementById('modalCantidadProducto');
    if (modalEl && typeof bootstrap !== 'undefined') {
        modalCantidadRef = new bootstrap.Modal(modalEl);
    }
    const zoomModalEl = document.getElementById('modalZoomProducto');
    if (zoomModalEl && typeof bootstrap !== 'undefined') {
        modalZoomProductoRef = new bootstrap.Modal(zoomModalEl);
    }
}

function abrirModalCantidad(btn) {
    const card = btn?.closest('.ml-card, .producto-card');
    if (!card) return;

    const id = Number(card.dataset.id || 0);
    const nombre = card.dataset.nombreRaw || card.dataset.nombre || '';
    const imagen = card.dataset.imagen || '';
    if (!id || !nombre) return;

    productoPendiente = {
        id,
        nombre,
        imagen
    };

    const nombreEl = document.getElementById('cantidadProductoNombre');
    const qtyEl = document.getElementById('cantidadProductoInput');
    const imgEl = document.getElementById('cantidadProductoImagen');
    const placeholderEl = document.getElementById('cantidadProductoPlaceholder');
    const zoomBtn = document.getElementById('cantidadProductoZoomBtn');
    if (nombreEl) nombreEl.textContent = nombre;
    if (qtyEl) qtyEl.value = '1';
    if (imgEl && placeholderEl && zoomBtn) {
        if (imagen) {
            imgEl.src = imagen;
            imgEl.alt = nombre;
            imgEl.style.display = 'block';
            placeholderEl.style.display = 'none';
            zoomBtn.style.display = 'inline-flex';
        } else {
            imgEl.removeAttribute('src');
            imgEl.alt = '';
            imgEl.style.display = 'none';
            placeholderEl.style.display = 'flex';
            zoomBtn.style.display = 'none';
        }
    }

    if (modalCantidadRef) {
        modalCantidadRef.show();
    } else {
        document.getElementById('modalCantidadProducto')?.classList.add('show');
    }
}

function cambiarCantidadModal(delta) {
    const qtyEl = document.getElementById('cantidadProductoInput');
    if (!qtyEl) return;
    const actual = Number(qtyEl.value || 1);
    const siguiente = Math.max(1, actual + delta);
    qtyEl.value = String(siguiente);
}

function confirmarCantidadProducto() {
    const qtyEl = document.getElementById('cantidadProductoInput');
    const qty = Math.max(1, Number(qtyEl?.value || 1));
    if (!productoPendiente) return;

    agregarCarrito(productoPendiente, qty);

    if (modalCantidadRef) {
        modalCantidadRef.hide();
    }
}

function abrirZoomProducto() {
    if (!productoPendiente?.imagen) return;
    const zoomImg = document.getElementById('zoomProductoImagen');
    if (!zoomImg) return;
    zoomImg.src = productoPendiente.imagen;
    zoomImg.alt = productoPendiente.nombre || 'Producto';
    if (modalZoomProductoRef) modalZoomProductoRef.show();
}

function agregarCarrito(producto, qty = 1) {
    const id = Number(producto?.id || 0);
    if (!id) return;
    const nombre = String(producto?.nombre || 'Producto');
    const image = String(producto?.imagen || '');

    if (carrito[id]) {
        carrito[id].qty += qty;
        if (!carrito[id].image && image) carrito[id].image = image;
    } else {
        carrito[id] = { id, nombre, qty, image };
    }

    guardarCarrito();
    renderCarrito();
}

function quitarItem(id) {
    delete carrito[id];
    guardarCarrito();
    renderCarrito();
}

function cambiarQtyCarrito(id, delta) {
    if (!carrito[id]) return;
    carrito[id].qty = Math.max(1, carrito[id].qty + delta);
    guardarCarrito();
    renderCarrito();
}

function guardarCarrito() {
    localStorage.setItem('nexus_carrito', JSON.stringify(carrito));
}

function renderCarrito() {
    const items = Object.values(carrito);
    const emptyEl = document.getElementById('carritoEmpty');
    const footerEl = document.getElementById('carritoFooter');
    const listEl = document.getElementById('carritoItems');
    const badge = document.getElementById('cartBadge');
    const floatingBadge = document.getElementById('floatingCartBadge');
    if (!emptyEl || !footerEl || !listEl) return;

    Array.from(listEl.querySelectorAll('.carrito-item')).forEach(el => el.remove());

    if (items.length === 0) {
        emptyEl.style.display = 'flex';
        footerEl.style.display = 'none';
        if (badge) badge.style.display = 'none';
        if (floatingBadge) floatingBadge.style.display = 'none';
        return;
    }

    emptyEl.style.display = 'none';
    footerEl.style.display = 'block';

    let actualizadoDesdeCards = false;
    items.forEach(item => {
        if (!item.image) {
            const cardRef = document.querySelector(`.ml-card[data-id="${item.id}"]`);
            if (cardRef?.dataset?.imagen) {
                item.image = cardRef.dataset.imagen;
                carrito[item.id].image = item.image;
                actualizadoDesdeCards = true;
            }
        }
        const imgHtml = item.image
            ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.nombre)}" class="ci-thumb">`
            : `<div class="ci-icon"><i class="fa-solid fa-box-open"></i></div>`;

        const div = document.createElement('div');
        div.className = 'carrito-item';
        div.innerHTML = `
            ${imgHtml}
            <div class="ci-info">
                <div class="ci-name">${escapeHtml(item.nombre)}</div>
                <div class="ci-meta">${item.qty} unidad(es)</div>
            </div>
            <div class="ci-qty">
                <button type="button" onclick="cambiarQtyCarrito(${item.id},-1)">-</button>
                <span>${item.qty}</span>
                <button type="button" onclick="cambiarQtyCarrito(${item.id},1)">+</button>
            </div>
            <button type="button" class="ci-remove" onclick="quitarItem(${item.id})"><i class="fa-solid fa-trash"></i></button>
        `;
        listEl.appendChild(div);
    });
    if (actualizadoDesdeCards) guardarCarrito();

    const totalItems = items.reduce((s, i) => s + i.qty, 0);
    document.getElementById('carritoTotal').textContent = `${totalItems} producto(s)`;
    if (badge) {
        badge.textContent = totalItems;
        badge.style.display = 'flex';
    }
    if (floatingBadge) {
        floatingBadge.textContent = String(totalItems);
        floatingBadge.style.display = 'flex';
    }
}

function toggleCarrito() {
    const panel = document.getElementById('carritoPanel');
    const overlay = document.getElementById('carritoOverlay');
    const isOpen = panel.classList.contains('open');
    panel.classList.toggle('open', !isOpen);
    overlay.classList.toggle('open', !isOpen);
}

function initFilterDropdowns() {
    const dropdowns = document.querySelectorAll('[data-filter-dropdown]');
    dropdowns.forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-filter-trigger]');
        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            dropdowns.forEach((other) => {
                if (other !== dropdown) other.classList.remove('open');
            });
            dropdown.classList.toggle('open');
        });
    });

    document.addEventListener('click', (event) => {
        dropdowns.forEach((dropdown) => {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('open');
            }
        });
    });
}

function actualizarEtiquetasFiltros() {
    const categoriaActive = document.querySelector('#filtroCategoriaPills .filtro-pill.active');
    const subcategoriaActive = document.querySelector('#filtroSubcategoriaPills .filtro-pill.active');
    const catLabel = document.getElementById('categoriaSelectedLabel');
    const subLabel = document.getElementById('subcategoriaSelectedLabel');
    if (catLabel) catLabel.textContent = (categoriaActive?.textContent || 'Todas').trim();
    if (subLabel) subLabel.textContent = (subcategoriaActive?.textContent || 'Todas').trim();
}

function initPedidoNotifications() {
    const btn = document.getElementById('pedidoNotiBtn');
    const badge = document.getElementById('pedidoNotiBadge');
    const panel = document.getElementById('pedidoNotiPanel');
    const wrap = document.getElementById('pedidoNotiWrap');
    if (!btn || !badge || !panel || !wrap) return;

    const count = Number(btn.dataset.count || 0);
    const stateKey = btn.dataset.stateKey || tiendaConfig.notificationStateKey || '';
    const seenKey = localStorage.getItem('nexus_cliente_pedido_seen') || '';
    const shouldShowBadge = count > 0 && stateKey && seenKey !== stateKey;
    badge.style.display = shouldShowBadge ? 'flex' : 'none';
    if (count > 0) badge.textContent = String(count);

    const setPanelOpen = (open) => {
        panel.classList.toggle('open', open);
        panel.style.display = open ? 'block' : 'none';
    };
    setPanelOpen(false);

    btn.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = !panel.classList.contains('open');
        setPanelOpen(open);
        if (open && stateKey) {
            localStorage.setItem('nexus_cliente_pedido_seen', stateKey);
            badge.style.display = 'none';
        }
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
            setPanelOpen(false);
        }
    });
}

function initFeaturedCarousel() {
    const carousel = document.getElementById('featuredCarousel');
    const prev = document.getElementById('featuredPrevBtn');
    const next = document.getElementById('featuredNextBtn');
    if (!carousel) return;

    const scrollByAmount = () => Math.max(240, Math.round(carousel.clientWidth * 0.72));
    prev?.addEventListener('click', () => {
        carousel.scrollBy({ left: -scrollByAmount(), behavior: 'smooth' });
    });
    next?.addEventListener('click', () => {
        carousel.scrollBy({ left: scrollByAmount(), behavior: 'smooth' });
    });
}

function initAutoProductoCatalogo() {
    if (tiendaConfig.currentView !== 'catalogo') return;
    const productId = Number(tiendaConfig.openProductId || 0);
    if (!productId) return;
    const card = document.getElementById(`producto-card-${productId}`);
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.classList.add('producto-focus-pulse');
    const button = card.querySelector('.ml-btn-agregar');
    window.setTimeout(() => abrirModalCantidad(button), 650);
    window.setTimeout(() => card.classList.remove('producto-focus-pulse'), 2600);
}

function abrirCheckout() {
    const items = Object.values(carrito);
    if (items.length === 0) return;

    let html = '';
    items.forEach(item => {
        html += `<div class="checkout-resumen-item">
            <span>${escapeHtml(item.nombre)} x ${item.qty}</span>
            <strong>${item.qty} unidad(es)</strong>
        </div>`;
    });

    const totalItems = items.reduce((s, i) => s + i.qty, 0);
    document.getElementById('checkoutResumen').innerHTML = html;
    document.getElementById('checkoutTotal').textContent = `${totalItems} producto(s)`;

    toggleCarrito();
    const modal = new bootstrap.Modal(document.getElementById('modalCheckout'));
    modal.show();
}

function toggleAddrMode(mode) {
    const otraInput = document.getElementById('otraDireccion');
    if (!otraInput) return;
    otraInput.style.display = mode === 'otra' ? 'block' : 'none';
    document.getElementById('addrMiDir')?.classList.toggle('active', mode === 'perfil');
    document.getElementById('addrOtraDir')?.classList.toggle('active', mode === 'otra');
}

async function confirmarPedido() {
    const items = Object.values(carrito);
    if (items.length === 0) return;

    const addrMode = document.querySelector('input[name="addr"]:checked')?.value || 'perfil';
    const otraDireccion = document.getElementById('otraDireccion')?.value.trim() || '';
    const tipoPedido = document.querySelector('input[name="tipo_pedido"]')?.value || 'formal';
    const notaPedido = document.getElementById('notaPedido')?.value?.trim() || '';
    if (addrMode === 'otra' && !otraDireccion) {
        const el = document.getElementById('otraDireccion');
        if (el) el.style.borderColor = 'rgba(239,68,68,0.6)';
        return;
    }

    document.getElementById('confirmText').style.display = 'none';
    document.getElementById('confirmLoading').style.display = 'inline-flex';

    try {
        const res = await fetch('../api/crear_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items,
                addr_mode: addrMode,
                otra_direccion: otraDireccion,
                tipo_pedido: tipoPedido,
                notas: notaPedido,
                pago: document.querySelector('input[name="pago"]:checked')?.value || 'efectivo',
            })
        });

        const data = await res.json();
        if (data.success) {
            carrito = {};
            guardarCarrito();
            renderCarrito();
            bootstrap.Modal.getInstance(document.getElementById('modalCheckout')).hide();
            mostrarToastPedidoExitoso();
            window.setTimeout(() => {
                window.location.href = `mis_pedidos.php?nuevo=${data.pedido_id}`;
            }, 1500);
        } else {
            alert('Error: ' + (data.error || 'No se pudo crear el pedido'));
            document.getElementById('confirmText').style.display = 'inline-flex';
            document.getElementById('confirmLoading').style.display = 'none';
        }
    } catch (_) {
        alert('Error de conexion');
        document.getElementById('confirmText').style.display = 'inline-flex';
        document.getElementById('confirmLoading').style.display = 'none';
    }
}

function mostrarToastPedidoExitoso() {
    const toast = document.getElementById('pedidoSuccessToast');
    if (!toast) return;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function filtrarProductos() {
    const input = document.getElementById('searchInput');
    const q = normalizarTexto(input ? input.value : '');
    const categoriaFiltro = getActivePillValue('filtroCategoriaPills');
    const subcategoriaFiltro = getActivePillValue('filtroSubcategoriaPills');
    let cards = document.querySelectorAll('.ml-card, .producto-card');
    if (!cards.length) {
        const grid = document.getElementById('productosGrid');
        if (grid) cards = grid.querySelectorAll(':scope > div');
    }

    cards.forEach(card => {
        const nombre = normalizarTexto(card.dataset.nombreRaw || card.dataset.nombre || card.querySelector('.ml-nombre, .producto-nombre')?.textContent || '');
        const codigo = normalizarTexto(card.querySelector('.ml-codigo, .producto-codigo')?.textContent || '');
        const categoriaCard = normalizarFiltro(card.dataset.categoria || '');
        const subcategoriaCard = normalizarFiltro(card.dataset.subcategoria || '');
        const matchTexto = !q || nombre.includes(q) || codigo.includes(q);
        const matchCategoria = !categoriaFiltro || categoriaCard === categoriaFiltro;
        const matchSubcategoria = !subcategoriaFiltro || subcategoriaCard === subcategoriaFiltro;
        const hayMatch = matchTexto && matchCategoria && matchSubcategoria;
        card.classList.toggle('is-hidden', !hayMatch);
    });
}

function updateClock() {
    const el = document.getElementById('topbarDate');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-MX', {
        weekday: 'short',
        day: '2-digit',
        month: 'short'
    }) + ' - ' + now.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit'
    });
}
