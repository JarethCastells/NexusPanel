// =============================================
// NEXUSPANEL - DASHBOARD JS
// =============================================
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    setInterval(updateClock, 1000);
    animateBars();
    initSidebar();
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
    const sidebar = document.getElementById('sidebar');
    const isMobileViewport = () => window.matchMedia('(max-width: 768px)').matches;
    const setSidebarOpen = (open) => {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        document.body.classList.toggle('sidebar-open', open);
    };

    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            setSidebarOpen(!sidebar.classList.contains('open'));
        });

        document.addEventListener('click', (e) => {
            if (
                isMobileViewport() &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
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

        sidebar.querySelectorAll('.nav-item').forEach((link) => {
            link.addEventListener('click', () => {
                if (isMobileViewport()) {
                    setSidebarOpen(false);
                }
            });
        });
    }

    // Row hover animation
    document.querySelectorAll('.table-row-animate').forEach((row, i) => {
        row.style.animation = `fadeInUp 0.3s ${i * 0.05 + 0.4}s ease both`;
    });
}

function toggleTheme() {
    const currentTheme = document.body.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'palette' : 'dark';
    
    // Optimistic UI update
    document.body.setAttribute('data-theme', newTheme);
    
    // Detect correct path to API
    const isSubdir = window.location.pathname.includes('/pages/');
    const apiPath = isSubdir ? '../api/tema.php' : 'api/tema.php';
    
    // Save to DB
    fetch(apiPath, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tema: newTheme })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.error('Error al guardar el tema:', data.error);
        }
    })
    .catch(err => {
        console.error('Network error:', err);
    });
}
