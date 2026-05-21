// =============================================
// NEXUSPANEL - DASHBOARD JS
// =============================================
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    setInterval(updateClock, 1000);
    animateBars();
    initSidebar();
    initSidebarHotspot();
    annotateResponsiveTables();
});

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

function animateBars() {
    setTimeout(() => {
        document.querySelectorAll('.bar-fill').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => { bar.style.width = width; }, 100);
        });
    }, 400);
}

function initSidebar() {
    const mobileBtn = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    const isMobileViewport = () => window.matchMedia('(max-width: 768px)').matches;
    const getSidebar = () => document.getElementById('sidebar') || document.querySelector('.sidebar');
    const setSidebarOpen = (open) => {
        const currentSidebar = getSidebar();
        if (!currentSidebar) return;
        currentSidebar.classList.toggle('open', open);
        document.body.classList.toggle('sidebar-open', open);
    };

    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('touchstart', (e) => {
            e.preventDefault();
        }, { passive: false });

        mobileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const currentSidebar = getSidebar();
            setSidebarOpen(!currentSidebar?.classList.contains('open'));
        });

        mobileBtn.addEventListener('touchend', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const currentSidebar = getSidebar();
            setSidebarOpen(!currentSidebar?.classList.contains('open'));
        }, { passive: false });

        document.addEventListener('click', (e) => {
            const currentSidebar = getSidebar();
            if (
                isMobileViewport() &&
                currentSidebar?.classList.contains('open') &&
                !currentSidebar.contains(e.target) &&
                !mobileBtn.contains(e.target)
            ) {
                setSidebarOpen(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                setSidebarOpen(false);
            }
        });

        window.addEventListener('resize', () => {
            if (!isMobileViewport()) {
                setSidebarOpen(false);
            }
        });

        const navLinks = sidebar.querySelectorAll('.nav-item');
        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobileViewport()) {
                    setSidebarOpen(false);
                }
            });
        });
    }

    // Fallback global: ensures the hamburger works even on pages with inline custom scripts.
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.mobile-menu-btn, #mobileMenu');
        if (!trigger || !isMobileViewport()) return;
        e.preventDefault();
        e.stopPropagation();
        const currentSidebar = getSidebar();
        if (!currentSidebar) return;
        setSidebarOpen(!currentSidebar.classList.contains('open'));
    });

    // Row hover animation
    document.querySelectorAll('.table-row-animate').forEach((row, i) => {
        row.style.animation = `fadeInUp 0.3s ${i * 0.05 + 0.4}s ease both`;
    });
}

function initSidebarHotspot() {
    const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
    if (!sidebar) return;

    let hotspot = document.getElementById('nexusSidebarHotspot');
    if (!hotspot) {
        hotspot = document.createElement('button');
        hotspot.type = 'button';
        hotspot.id = 'nexusSidebarHotspot';
        hotspot.className = 'nexus-sidebar-hotspot';
        hotspot.setAttribute('aria-label', 'Abrir menu lateral');
        hotspot.textContent = 'NexusPanel';
        document.body.appendChild(hotspot);
    }

    const isMobileViewport = () => window.matchMedia('(max-width: 768px)').matches;
    const setSidebarOpen = (open) => {
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('sidebar-open', open);
    };
    const toggleSidebar = (e) => {
        if (!isMobileViewport()) return;
        e.preventDefault();
        e.stopPropagation();
        setSidebarOpen(!sidebar.classList.contains('open'));
    };

    hotspot.addEventListener('click', toggleSidebar);
    hotspot.addEventListener('touchend', toggleSidebar, { passive: false });
}


function annotateResponsiveTables() {
    document.querySelectorAll('table.data-table, table.prod-table').forEach((table) => {
        const headers = Array.from(table.querySelectorAll('thead th')).map((th) =>
            String(th.textContent || '').trim()
        );
        if (!headers.length) return;
        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, idx) => {
                if (!cell.getAttribute('data-label')) {
                    cell.setAttribute('data-label', headers[idx] || '');
                }
            });
        });
    });
}
