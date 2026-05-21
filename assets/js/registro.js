// NEXUSPANEL — REGISTRO JS con Mapa Leaflet
let map = null, marker = null, geocodeTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    const passInput = document.getElementById('password');
    const confInput = document.getElementById('confirmar');
    if (passInput) passInput.addEventListener('input', checkStrength);
    if (confInput) confInput.addEventListener('input', checkMatch);
    ['nombre','edad','telefono','email','domicilio'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateResumen);
    });
    const domInput = document.getElementById('domicilio');
    if (domInput) {
        domInput.addEventListener('input', () => {
            clearTimeout(geocodeTimer);
            geocodeTimer = setTimeout(() => {
                const val = domInput.value.trim();
                if (val.length > 8) geocodeAddress(val);
            }, 1500);
        });
    }
    addExtraCSS();
});

function addExtraCSS() {
    if (document.getElementById('extraCSS')) return;
    const s = document.createElement('style');
    s.id = 'extraCSS';
    s.textContent = `
        @keyframes stepInBack { from{opacity:0;transform:translateX(-20px)} to{opacity:1;transform:translateX(0)} }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
    `;
    document.head.appendChild(s);
}

// ---- MAPA ----
function initMap(lat, lng) {
    if (!map) {
        map = L.map('miniMapa', { zoomControl: true, scrollWheelZoom: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap', maxZoom: 19,
        }).addTo(map);
    }
    map.setView([lat, lng], 16);
    const icon = L.divIcon({
        html: `<div style="width:34px;height:34px;background:linear-gradient(135deg,#00d4ff,#7c3aed);border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 15px rgba(0,212,255,0.5);"></div>`,
        iconSize:[34,34], iconAnchor:[17,34], popupAnchor:[0,-34], className:'',
    });
    if (!marker) {
        marker = L.marker([lat, lng], { icon, draggable: true }).addTo(map);
        marker.on('dragend', e => {
            const { lat, lng } = e.target.getLatLng();
            setCoords(lat, lng);
            reverseGeocode(lat, lng);
        });
    } else {
        marker.setLatLng([lat, lng]);
    }
    setCoords(lat, lng);
    document.getElementById('mapaPlaceholder').classList.add('hidden');
    setTimeout(() => { if (map) map.invalidateSize(); }, 150);
}

function setCoords(lat, lng) {
    document.getElementById('inputLat').value = lat;
    document.getElementById('inputLng').value = lng;
    const c = document.getElementById('mapaCoords');
    c.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    c.classList.remove('hidden');
    document.getElementById('mapaTip').classList.remove('hidden');
    updateResumen();
}

// ---- GPS ----
function detectarUbicacion() {
    if (!navigator.geolocation) {
        showGeoStatus('error', '<i class="fa-solid fa-circle-exclamation"></i> Tu navegador no soporta geolocalización.');
        return;
    }
    const btn = document.getElementById('btnGPS');
    const icon = document.getElementById('gpsIcon');
    const text = document.getElementById('gpsText');
    btn.classList.add('loading');
    icon.className = 'fa-solid fa-spinner fa-spin';
    text.textContent = 'Detectando...';
    showGeoStatus('loading', '<i class="fa-solid fa-spinner fa-spin"></i> Obteniendo tu ubicación GPS...');

    navigator.geolocation.getCurrentPosition(
        pos => {
            btn.classList.remove('loading');
            icon.className = 'fa-solid fa-crosshairs';
            text.textContent = 'Mi ubicación';
            showGeoStatus('success', '<i class="fa-solid fa-circle-check"></i> Ubicación detectada correctamente');
            initMap(pos.coords.latitude, pos.coords.longitude);
            reverseGeocode(pos.coords.latitude, pos.coords.longitude);
            setTimeout(hideGeoStatus, 4000);
        },
        err => {
            btn.classList.remove('loading');
            icon.className = 'fa-solid fa-crosshairs';
            text.textContent = 'Mi ubicación';
            const msgs = { 1:'Permiso de ubicación denegado.', 2:'Ubicación no disponible.', 3:'Tiempo de espera agotado.' };
            showGeoStatus('error', `<i class="fa-solid fa-triangle-exclamation"></i> ${msgs[err.code] || 'Error desconocido.'}`);
        },
        { timeout: 10000, enableHighAccuracy: true }
    );
}

// ---- GEOCODE (texto -> coords) ----
async function geocodeManual() {
    const val = document.getElementById('domicilio').value.trim();
    if (!val) return;
    geocodeAddress(val);
}

async function geocodeAddress(direccion) {
    showGeoStatus('loading', '<i class="fa-solid fa-spinner fa-spin"></i> Buscando dirección en el mapa...');
    try {
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(direccion)}&limit=1`;
        const res  = await fetch(url, { headers:{'Accept-Language':'es','User-Agent':'NexusPanelDemo/1.0'} });
        const data = await res.json();
        if (data && data.length > 0) {
            const lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
            showGeoStatus('success', `<i class="fa-solid fa-circle-check"></i> Dirección encontrada`);
            initMap(lat, lng);
            setTimeout(hideGeoStatus, 4000);
        } else {
            showGeoStatus('error', '<i class="fa-solid fa-triangle-exclamation"></i> No se encontró la dirección. Sé más específico.');
        }
    } catch(e) {
        showGeoStatus('error', '<i class="fa-solid fa-triangle-exclamation"></i> Error de conexión al buscar dirección.');
    }
}

// ---- REVERSE GEOCODE (coords -> texto) ----
async function reverseGeocode(lat, lng) {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`;
        const res  = await fetch(url, { headers:{'Accept-Language':'es','User-Agent':'NexusPanelDemo/1.0'} });
        const data = await res.json();
        if (data && data.address) {
            const a = data.address;
            const partes = [a.road||a.pedestrian||'', a.house_number||'', a.suburb||a.neighbourhood||'', a.city||a.town||a.village||'', a.state||''].filter(Boolean);
            document.getElementById('domicilio').value = partes.join(', ');
            updateResumen();
        }
    } catch(e) { /* silencioso */ }
}

// ---- GEO STATUS UI ----
function showGeoStatus(type, html) {
    const el = document.getElementById('geoStatus');
    el.className = `geo-status ${type}`;
    el.innerHTML = html;
}
function hideGeoStatus() {
    document.getElementById('geoStatus').className = 'geo-status hidden';
}

// ---- STEPS ----
function nextStep(from) {
    if (!validateStep(from)) return;
    const dot = document.getElementById(`step-dot-${from}`);
    if (dot) { dot.classList.remove('active'); dot.classList.add('done'); dot.querySelector('.step-dot').innerHTML = '<i class="fa-solid fa-check"></i>'; }
    const line = document.getElementById(`line-${from}`);
    if (line) line.classList.add('filled');
    const nextDot = document.getElementById(`step-dot-${from+1}`);
    if (nextDot) nextDot.classList.add('active');
    document.getElementById(`step-${from}`).classList.add('hidden');
    const nextEl = document.getElementById(`step-${from+1}`);
    nextEl.classList.remove('hidden');
    nextEl.style.animation = 'none'; nextEl.offsetHeight; nextEl.style.animation = 'stepIn 0.4s cubic-bezier(0.22,1,0.36,1)';
    if (from+1 === 2) {
        setTimeout(() => {
            if (!map) { initMap(19.4326,-99.1332); document.getElementById('mapaPlaceholder').classList.remove('hidden'); document.getElementById('mapaCoords').classList.add('hidden'); document.getElementById('mapaTip').classList.add('hidden'); }
            else map.invalidateSize();
        }, 200);
    }
    if (from+1 === 3) updateResumen();
    window.scrollTo({top:0,behavior:'smooth'});
}

function prevStep(from) {
    const dot = document.getElementById(`step-dot-${from}`);
    if (dot) dot.classList.remove('active');
    const prevDot = document.getElementById(`step-dot-${from-1}`);
    if (prevDot) {
        prevDot.classList.remove('done'); prevDot.classList.add('active');
        const icons=['','fa-user','fa-location-dot','fa-lock'];
        prevDot.querySelector('.step-dot').innerHTML = `<i class="fa-solid ${icons[from-1]}"></i>`;
    }
    const line = document.getElementById(`line-${from-1}`);
    if (line) line.classList.remove('filled');
    document.getElementById(`step-${from}`).classList.add('hidden');
    const prevEl = document.getElementById(`step-${from-1}`);
    prevEl.classList.remove('hidden');
    prevEl.style.animation='none'; prevEl.offsetHeight; prevEl.style.animation='stepInBack 0.4s cubic-bezier(0.22,1,0.36,1)';
    if (from-1 === 2 && map) setTimeout(() => map.invalidateSize(), 200);
    window.scrollTo({top:0,behavior:'smooth'});
}

// ---- VALIDACIONES ----
function validateStep(step) {
    let valid = true;
    if (step === 1) {
        const nombre=document.getElementById('nombre').value.trim(), edad=document.getElementById('edad').value.trim(), telefono=document.getElementById('telefono').value.trim(), email=document.getElementById('email').value.trim();
        if (!nombre) { highlightError('nombre','Campo requerido'); valid=false; } else clearError('nombre');
        if (!edad||isNaN(edad)||edad<1||edad>120) { highlightError('edad','Edad inválida'); valid=false; } else clearError('edad');
        if (!telefono||!/^[0-9+\-\s()]{7,15}$/.test(telefono)) { highlightError('telefono','Teléfono inválido'); valid=false; } else clearError('telefono');
        if (!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { highlightError('email','Correo inválido'); valid=false; } else clearError('email');
    }
    if (step === 2) {
        const dom=document.getElementById('domicilio').value.trim();
        if (!dom) { highlightError('domicilio','Campo requerido'); valid=false; } else clearError('domicilio');
    }
    if (!valid) shakeStep(step);
    return valid;
}

function highlightError(id, msg) {
    const input=document.getElementById(id); if(!input)return;
    input.style.borderColor='rgba(239,68,68,0.6)'; input.style.background='rgba(239,68,68,0.04)';
    let e=input.parentElement.nextElementSibling;
    if(!e||!e.classList.contains('field-error')){ e=document.createElement('div'); e.className='field-error'; e.style.cssText='font-size:11px;color:#fca5a5;margin-top:5px;font-family:var(--font-mono);'; input.parentElement.after(e); }
    e.textContent='⚠ '+msg;
}
function clearError(id) {
    const input=document.getElementById(id); if(!input)return;
    input.style.borderColor=''; input.style.background='';
    const e=input.parentElement.nextElementSibling;
    if(e&&e.classList.contains('field-error')) e.remove();
}
function shakeStep(step) {
    const el=document.getElementById(`step-${step}`); if(!el)return;
    el.style.animation='shake 0.4s ease';
    el.addEventListener('animationend',()=>el.style.animation='',{once:true});
}

// ---- PASSWORD ----
function togglePass(inputId, iconId) {
    const input=document.getElementById(inputId), icon=document.getElementById(iconId);
    input.type=input.type==='password'?'text':'password';
    icon.className=input.type==='password'?'fa-solid fa-eye':'fa-solid fa-eye-slash';
}
function checkStrength() {
    const val=document.getElementById('password').value, fill=document.getElementById('strengthFill'), label=document.getElementById('strengthLabel');
    if(!fill)return;
    let s=0; if(val.length>=6)s++; if(val.length>=10)s++; if(/[A-Z]/.test(val))s++; if(/[0-9]/.test(val))s++; if(/[^A-Za-z0-9]/.test(val))s++;
    const lvls=[{pct:'0%',c:'',t:''},{pct:'25%',c:'#ef4444',t:'Muy débil'},{pct:'50%',c:'#f59e0b',t:'Débil'},{pct:'65%',c:'#eab308',t:'Regular'},{pct:'82%',c:'#22c55e',t:'Fuerte'},{pct:'100%',c:'#10b981',t:'Muy fuerte'}];
    const l=val.length===0?0:Math.max(1,s);
    fill.style.width=lvls[l].pct; fill.style.background=lvls[l].c; label.textContent=lvls[l].t; label.style.color=lvls[l].c;
    checkMatch();
}
function checkMatch() {
    const pass=document.getElementById('password').value, conf=document.getElementById('confirmar').value, label=document.getElementById('matchLabel');
    if(!label||!conf)return;
    if(pass===conf&&conf.length>0){label.textContent='✓ Las contraseñas coinciden';label.style.color='#10b981';}
    else if(conf.length>0){label.textContent='✗ No coinciden';label.style.color='#ef4444';}
    else label.textContent='';
}

// ---- RESUMEN ----
function updateResumen() {
    const get=id=>{const el=document.getElementById(id);return el?el.value.trim():''};
    const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=val||'—'};
    set('r-nombre',get('nombre')); set('r-edad',get('edad')?get('edad')+' años':''); set('r-tel',get('telefono')); set('r-email',get('email')); set('r-dom',get('domicilio'));
    const lat=document.getElementById('inputLat').value, lng=document.getElementById('inputLng').value;
    const u=document.getElementById('r-ubic');
    if(u){u.textContent=lat&&lng?`${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`:'Sin ubicación GPS'; u.style.color=lat&&lng?'var(--primary)':'var(--text-dim)';}
}

document.addEventListener('DOMContentLoaded',()=>{
    const form=document.getElementById('registroForm'); if(!form)return;
    form.addEventListener('submit',e=>{
        const pass=document.getElementById('password')?.value||'', conf=document.getElementById('confirmar')?.value||'';
        if(pass!==conf){e.preventDefault();shakeStep(3);return;}
        const btn=document.getElementById('submitBtn');
        if(btn){btn.querySelector('.btn-text').style.display='none';btn.querySelector('.btn-loading').style.display='inline-flex';btn.disabled=true;}
    });
});
