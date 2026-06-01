/**
 * TelegramWA – Personal Telegram account gateway
 * Uses GramJS (MTProto) to link a real phone number, just like Telethon.
 *
 * Start:  node server.js
 * Port:   3785 (configurable via PORT env var)
 *
 * You need API credentials from https://my.telegram.org/apps
 * Set them in .env or pass as env vars:
 *   TG_API_ID=12345
 *   TG_API_HASH=abcdef1234567890abcdef1234567890
 */

require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const express    = require('express');
const cors       = require('cors');
const path       = require('path');
const fs         = require('fs');
const { TelegramClient } = require('telegram');
const { StringSession }  = require('telegram/sessions');
const { Api }            = require('telegram');

const PORT     = parseInt(process.env.TG_PORT || '3785', 10);
const API_ID   = parseInt(process.env.TG_API_ID   || '0', 10);
const API_HASH = process.env.TG_API_HASH || '';

const SESSION_PATH = path.join(__dirname, 'data', 'session.json');
const DATA_DIR     = path.join(__dirname, 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

// ── Session store ────────────────────────────────────────────────────────────
function loadSessionData() {
    try {
        const raw = fs.readFileSync(SESSION_PATH, 'utf8');
        return JSON.parse(raw);
    } catch (_) {
        return { sessionString: '', phone: '', status: 'disconnected' };
    }
}

function saveSessionData(data) {
    const current = loadSessionData();
    fs.writeFileSync(SESSION_PATH, JSON.stringify({ ...current, ...data }, null, 2));
}

// ── GramJS client (singleton) ─────────────────────────────────────────────────
let client     = null;
let phoneHash  = '';   // from sendCode
let pendingPhone = '';

async function getClient(sessionStr = '') {
    if (client && client.connected) return client;
    const session = new StringSession(sessionStr || '');
    client = new TelegramClient(session, API_ID, API_HASH, {
        connectionRetries: 5,
        baseLogger: { log: () => {} },
    });
    await client.connect();
    return client;
}

// ── Express app ───────────────────────────────────────────────────────────────
const app = express();
app.use(cors());
app.use(express.json({ limit: '10mb' }));

// Health
app.get('/health', (_, res) => res.json({ ok: true, service: 'TelegramWA' }));

// ── Status ────────────────────────────────────────────────────────────────────
app.get('/api/status', async (_, res) => {
    try {
        const data   = loadSessionData();
        const ready  = data.status === 'ready' && data.sessionString;
        if (ready) {
            const c = await getClient(data.sessionString);
            const me = await c.getMe();
            return res.json({
                ok: true,
                status: 'ready',
                phone: data.phone,
                name: [me.firstName, me.lastName].filter(Boolean).join(' '),
                username: me.username || '',
                id: me.id?.toString(),
            });
        }
        return res.json({ ok: true, status: data.status || 'disconnected' });
    } catch (e) {
        return res.json({ ok: false, status: 'disconnected', error: e.message });
    }
});

// ── Step 1: send code ─────────────────────────────────────────────────────────
app.post('/api/send-code', async (req, res) => {
    const { phone } = req.body;
    if (!phone) return res.json({ ok: false, error: 'Falta el número de teléfono.' });
    if (!API_ID || !API_HASH) {
        return res.json({ ok: false, error: 'Configura TG_API_ID y TG_API_HASH en el .env primero.' });
    }
    try {
        const c = await getClient();
        const result = await c.sendCode({ apiId: API_ID, apiHash: API_HASH }, phone);
        phoneHash    = result.phoneCodeHash;
        pendingPhone = phone;
        saveSessionData({ status: 'code_sent', phone });
        return res.json({ ok: true, message: `Código enviado al ${phone}.` });
    } catch (e) {
        return res.json({ ok: false, error: e.message });
    }
});

// ── Step 2: verify code ───────────────────────────────────────────────────────
app.post('/api/verify-code', async (req, res) => {
    const { code, password } = req.body;
    if (!code) return res.json({ ok: false, error: 'Falta el código.' });
    try {
        const c = await getClient();
        let me;
        try {
            me = await c.signIn(
                { apiId: API_ID, apiHash: API_HASH },
                { phoneNumber: pendingPhone, phoneCode: () => Promise.resolve(code), phoneCodeHash: phoneHash }
            );
        } catch (err) {
            if (err.errorMessage === 'SESSION_PASSWORD_NEEDED') {
                if (!password) {
                    return res.json({ ok: false, needPassword: true, error: 'Se requiere contraseña de verificación en dos pasos.' });
                }
                const { computePasswordSrpParams } = require('telegram/Password');
                const passwordResult = await c.invoke(new Api.auth.GetPassword());
                const srpParams = await computePasswordSrpParams(passwordResult, password);
                me = await c.invoke(new Api.auth.CheckPassword({ password: srpParams }));
                me = me.user;
            } else throw err;
        }
        const sessionString = c.session.save();
        saveSessionData({ sessionString, status: 'ready', phone: pendingPhone });
        return res.json({
            ok: true,
            name: [me.firstName, me.lastName].filter(Boolean).join(' '),
            username: me.username || '',
            id: me.id?.toString(),
        });
    } catch (e) {
        return res.json({ ok: false, error: e.message });
    }
});

// ── Logout ────────────────────────────────────────────────────────────────────
app.post('/api/logout', async (_, res) => {
    try {
        if (client && client.connected) {
            await client.invoke(new Api.auth.LogOut());
            await client.disconnect();
        }
        client = null;
    } catch (_) {}
    saveSessionData({ sessionString: '', status: 'disconnected', phone: '' });
    res.json({ ok: true });
});

// ── Get dialogs (chats) ───────────────────────────────────────────────────────
app.get('/api/chats', async (_, res) => {
    try {
        const data = loadSessionData();
        if (!data.sessionString) return res.json({ ok: false, error: 'No autenticado.' });
        const c = await getClient(data.sessionString);
        const dialogs = await c.getDialogs({ limit: 50 });
        const result  = dialogs.map(d => ({
            id:       d.id?.toString(),
            name:     d.title || d.name || 'Sin nombre',
            username: d.entity?.username || '',
            type:     d.isChannel ? 'channel' : d.isGroup ? 'group' : 'private',
            unread:   d.unreadCount || 0,
            lastDate: d.date || 0,
            lastMsg:  d.message?.message || '',
        }));
        res.json({ ok: true, data: result });
    } catch (e) {
        res.json({ ok: false, error: e.message });
    }
});

// ── Get messages ──────────────────────────────────────────────────────────────
app.get('/api/messages', async (req, res) => {
    const { chatId, limit = 40 } = req.query;
    if (!chatId) return res.json({ ok: false, error: 'Falta chatId.' });
    try {
        const data = loadSessionData();
        if (!data.sessionString) return res.json({ ok: false, error: 'No autenticado.' });
        const c    = await getClient(data.sessionString);
        const msgs = await c.getMessages(chatId, { limit: parseInt(limit) });
        const result = msgs.map(m => ({
            id:       m.id,
            text:     m.message || (m.media ? '[media]' : ''),
            date:     m.date,
            out:      m.out || false,
            fromId:   m.fromId?.userId?.toString() || m.fromId?.channelId?.toString() || '',
            fromName: m.sender ? [m.sender.firstName, m.sender.lastName].filter(Boolean).join(' ') || m.sender.username || '' : '',
        }));
        res.json({ ok: true, data: result });
    } catch (e) {
        res.json({ ok: false, error: e.message });
    }
});

// ── Send message ──────────────────────────────────────────────────────────────
app.post('/api/send-message', async (req, res) => {
    const { chatId, text } = req.body;
    if (!chatId || !text) return res.json({ ok: false, error: 'Faltan chatId y text.' });
    try {
        const data = loadSessionData();
        if (!data.sessionString) return res.json({ ok: false, error: 'No autenticado.' });
        const c = await getClient(data.sessionString);
        await c.sendMessage(chatId, { message: text });
        res.json({ ok: true });
    } catch (e) {
        res.json({ ok: false, error: e.message });
    }
});

// ── Serve static (optional) ───────────────────────────────────────────────────
app.listen(PORT, () => {
    console.log(`\n🚀 TelegramWA corriendo en http://localhost:${PORT}`);
    console.log(`📖 Endpoints: /health, /api/status, /api/chats, /api/messages, /api/send-message`);
    console.log(`\n   TG_API_ID   = ${API_ID || '❌ No configurado'}`);
    console.log(`   TG_API_HASH = ${API_HASH ? '✓ Configurado' : '❌ No configurado'}\n`);

    // Auto-reconnect if session exists
    const data = loadSessionData();
    if (data.sessionString && data.status === 'ready') {
        getClient(data.sessionString)
            .then(() => console.log('✅ Sesión de Telegram restaurada automáticamente.'))
            .catch(e => console.log('⚠️  No se pudo restaurar sesión:', e.message));
    }
});
