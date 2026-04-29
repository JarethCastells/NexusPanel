// =============================================
// NEXUSPANEL — LOGIN v2 — Validación mejorada
// =============================================

document.addEventListener('DOMContentLoaded', () => {
    createParticles();
    animateCounters();
    initForm();
    initInputEffects();
    initRealTimeValidation();
});

// ── Particles ─────────────────────────────────
function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `
            left: ${Math.random()*100}%;
            animation-duration: ${8+Math.random()*15}s;
            animation-delay: -${Math.random()*12}s;
            --drift: ${(Math.random()-0.5)*100}px;
            opacity: ${Math.random()*0.5+0.2};
        `;
        container.appendChild(p);
    }
}

// ── Counters ───────────────────────────────────
function animateCounters() {
    document.querySelectorAll('.stat-number').forEach(el => {
        const target = parseInt(el.dataset.target);
        let current = 0;
        const step = target / (1800 / 16);
        const t = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = Math.floor(current);
            if (current >= target) clearInterval(t);
        }, 16);
    });
}

// ── Validación en tiempo real ──────────────────
function initRealTimeValidation() {
    const emailInput = document.getElementById('email');
    const passInput  = document.getElementById('password');

    emailInput?.addEventListener('blur', () => {
        const v = emailInput.value.trim();
        if (v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
            setFieldError(emailInput, 'Formato de correo inválido');
        } else {
            clearFieldError(emailInput);
        }
    });

    emailInput?.addEventListener('input', () => clearFieldError(emailInput));
    passInput?.addEventListener('input',  () => clearFieldError(passInput));
}

function setFieldError(input, msg) {
    input.style.borderColor = 'rgba(239,68,68,0.6)';
    let hint = input.closest('.input-wrapper')?.parentElement?.querySelector('.field-hint');
    if (!hint) {
        hint = document.createElement('div');
        hint.className = 'field-hint';
        hint.style.cssText = 'color:#fca5a5;font-size:11px;margin-top:5px;display:flex;align-items:center;gap:4px;';
        input.closest('.input-wrapper')?.insertAdjacentElement('afterend', hint);
    }
    hint.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${msg}`;
}

function clearFieldError(input) {
    input.style.borderColor = '';
    const hint = input.closest('.input-wrapper')?.parentElement?.querySelector('.field-hint');
    if (hint) hint.remove();
}

// ── Form submit ────────────────────────────────
function initForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', e => {
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const emailRe  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        let valid = true;

        // Validar email
        if (!email) {
            setFieldError(document.getElementById('email'), 'El correo es obligatorio');
            valid = false;
        } else if (!emailRe.test(email)) {
            setFieldError(document.getElementById('email'), 'Formato de correo inválido');
            valid = false;
        }

        // Validar contraseña
        if (!password) {
            setFieldError(document.getElementById('password'), 'La contraseña es obligatoria');
            valid = false;
        } else if (password.length < 4) {
            setFieldError(document.getElementById('password'), 'La contraseña es muy corta');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            shakeForm(form);
            return;
        }

        // Loading state
        const btn = document.getElementById('submitBtn');
        if (btn && !btn.disabled) {
            btn.querySelector('.btn-text').style.display    = 'none';
            btn.querySelector('.btn-loading').style.display = 'inline-flex';
            btn.disabled = true;
        }
    });
}

function shakeForm(form) {
    form.style.animation = 'none';
    form.offsetHeight; // reflow
    form.style.animation = 'shake 0.4s ease';
    form.addEventListener('animationend', () => { form.style.animation = ''; }, { once: true });
    if (!document.getElementById('shakeCSS')) {
        const s = document.createElement('style');
        s.id = 'shakeCSS';
        s.textContent = `@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}40%{transform:translateX(8px)}60%{transform:translateX(-6px)}80%{transform:translateX(6px)}}`;
        document.head.appendChild(s);
    }
}

// ── Input focus effects ────────────────────────
function initInputEffects() {
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', () => input.closest('.input-wrapper').style.transform = 'scale(1.01)');
        input.addEventListener('blur',  () => input.closest('.input-wrapper').style.transform = 'scale(1)');
    });
}

// ── Toggle password ────────────────────────────
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}

// ── Demo credentials ───────────────────────────
function fillCredentials(email, password) {
    typeWriter(document.getElementById('email'), email, () =>
        typeWriter(document.getElementById('password'), password)
    );
}

function typeWriter(input, text, cb) {
    input.value = '';
    input.dispatchEvent(new Event('focus'));
    let i = 0;
    const t = setInterval(() => {
        input.value += text[i++];
        if (i >= text.length) {
            clearInterval(t);
            input.dispatchEvent(new Event('blur'));
            if (cb) setTimeout(cb, 150);
        }
    }, 40);
}

// ── Dismiss alert ──────────────────────────────
function dismissAlert() {
    const alert = document.getElementById('alertBox');
    if (alert) {
        alert.style.transition = 'all 0.3s ease';
        alert.style.opacity    = '0';
        alert.style.transform  = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }
}
