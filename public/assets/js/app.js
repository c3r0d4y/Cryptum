'use strict';

/* ─── Configuración inyectada por el servidor vía data-attributes ─────── */
const APP_BASE  = document.body.dataset.base  || '';
const APP_TOKEN = document.body.dataset.token || '';

/* ═══════════════════════════════════════════════════════════════
   CONSTANTES CRIPTOGRÁFICAS
   ═══════════════════════════════════════════════════════════════ */
const K = {
    MAGIC:    new Uint8Array([0x43, 0x33, 0x56, 0x4C]), // "C3VL"
    // v3: el nombre del archivo viaja DENTRO del contenido cifrado.
    // v1 (legado, solo lectura) lo guardaba en claro en el encabezado
    // y filtraba metadatos a cualquiera que interceptara el archivo.
    VER:        0x03,
    VER_LEGADO: 0x01,
    SALT_LEN: 32,    // 256 bits
    IV_LEN:   12,    // 96 bits (óptimo para AES-GCM)
    TAG_BITS: 128,   // Tag de autenticación completo
    KDF_ITER: 210000, // OWASP min para PBKDF2-SHA-512 (2024)
    KEY_BITS: 256,
    MAX_MB:   100,
    EXT:      '.c3v',
};

/* ═══════════════════════════════════════════════════════════════
   UTILIDADES
   ═══════════════════════════════════════════════════════════════ */
const $ = id => document.getElementById(id);

// Convierte texto en HTML inofensivo — obligatorio antes de insertar con
// innerHTML cualquier dato externo (nombres de archivo, datos de lsblk, etc.)
function esc(s) {
    return String(s).replace(/[&<>"']/g, c => (
        { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]
    ));
}

function fmtBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(2) + ' MB';
}

function fmtSecs(s) {
    const m = Math.floor(s / 60), r = s % 60;
    return String(m).padStart(2,'0') + ':' + String(r).padStart(2,'0');
}

function show(id) { const e = $(id); if (e) e.style.display = ''; }
function hide(id) { const e = $(id); if (e) e.style.display = 'none'; }

function alertHtml(type, msg) {
    const icons = {
        err:  '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        ok:   '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        warn: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    };
    return `<div class="alert alert-${type}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18">${icons[type]||icons.info}</svg><span>${esc(msg)}</span></div>`;
}

function setAlert(id, type, msg) {
    const el = $(id);
    if (!el) return;
    el.innerHTML = alertHtml(type, msg);
    el.style.display = '';
}
function clearAlert(id) { const e=$(id); if(e){e.innerHTML='';e.style.display='none';} }

// Muestra un mensaje flotante temporal en la parte inferior de la pantalla
function showToast(msg, ms = 2500) {
    let t = document.getElementById('c3-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'c3-toast';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.className = 'c3-toast visible';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.classList.remove('visible'), ms);
}

/* ═══════════════════════════════════════════════════════════════
   NAVEGACIÓN DE VISTAS
   ═══════════════════════════════════════════════════════════════ */
const Nav = {
    views: ['v-home','v-encrypt','v-result','v-decrypt-link','v-decrypt-file','v-folder','v-usb'],
    go(name) {
        this.views.forEach(id => {
            const el = $(id);
            if (el) el.hidden = (id !== 'v-' + name);
        });
        window.scrollTo(0, 0);
        // Mover el foco al contenido para que los lectores de pantalla
        // anuncien el cambio de vista (el <main> tiene tabindex="-1")
        const main = $('app-main');
        if (main) main.focus({ preventScroll: true });
    }
};

/* ═══════════════════════════════════════════════════════════════
   INTERFAZ COMPARTIDA
   ═══════════════════════════════════════════════════════════════ */
const UI = {
    toggleEye(inputId, svgId) {
        const inp = $(inputId);
        const isPwd = inp.type === 'password';
        inp.type = isPwd ? 'text' : 'password';
        $(svgId).innerHTML = isPwd
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    },
    toggleSec() {
        const btn = $('sec-toggle'), body = $('sec-body');
        const open = body.classList.toggle('visible');
        btn.classList.toggle('open', open);
    },
    setupDrop(zoneId, inputId, onFile) {
        const zone  = $(zoneId);
        const input = $(inputId);
        if (!zone || !input) return;

        zone.addEventListener('click', e => {
            if (e.target !== input) input.click();
        });
        zone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') input.click();
        });
        input.addEventListener('change', () => { if (input.files[0]) onFile(input.files[0]); });

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('over');
            const f = e.dataTransfer?.files[0];
            if (f) onFile(f);
        });
    }
};

/* ═══════════════════════════════════════════════════════════════
   NÚCLEO CRIPTOGRÁFICO (Web Crypto API)
   ═══════════════════════════════════════════════════════════════ */

// Une [NOMLEN(4)][NOMBRE][DATOS] en un solo buffer que se cifra completo.
// Así el nombre del archivo queda protegido igual que el contenido.
function packNamePlusData(filename, buffer) {
    const nom = new TextEncoder().encode(filename);
    const out = new Uint8Array(4 + nom.length + buffer.byteLength);
    new DataView(out.buffer).setUint32(0, nom.length, true);
    out.set(nom, 4);
    out.set(new Uint8Array(buffer), 4 + nom.length);
    return out;
}

// Separa el buffer ya descifrado en { data, filename }
function unpackNamePlusData(plainBuf) {
    const dv     = new DataView(plainBuf);
    const nomLen = dv.getUint32(0, true);
    if (nomLen > 4096 || nomLen + 4 > plainBuf.byteLength) {
        throw new Error('Encabezado corrupto.');
    }
    const filename = new TextDecoder().decode(new Uint8Array(plainBuf, 4, nomLen));
    return { data: plainBuf.slice(4 + nomLen), filename };
}

const Crypto = {

    async deriveKey(password, salt) {
        const enc = new TextEncoder();
        const raw = await crypto.subtle.importKey(
            'raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            { name:'PBKDF2', salt, iterations: K.KDF_ITER, hash:'SHA-512' },
            raw,
            { name:'AES-GCM', length: K.KEY_BITS },
            false,
            ['encrypt','decrypt']
        );
    },

    // Cifrar → Uint8Array con formato C3VL v3
    async encrypt(buffer, filename, password) {
        const salt  = crypto.getRandomValues(new Uint8Array(K.SALT_LEN));
        const iv    = crypto.getRandomValues(new Uint8Array(K.IV_LEN));
        const key   = await this.deriveKey(password, salt);

        // El nombre se cifra junto con los datos (en v1 iba en claro)
        const plano = packNamePlusData(filename, buffer);
        const ct    = await crypto.subtle.encrypt(
            { name:'AES-GCM', iv, tagLength: K.TAG_BITS }, key, plano
        );

        // Formato v3: [C3VL][0x03][SALT(32)][IV(12)][CIPHERTEXT+TAG]
        const partes = [K.MAGIC, new Uint8Array([K.VER]), salt, iv, new Uint8Array(ct)];
        const total  = partes.reduce((s, p) => s + p.length, 0);
        const out    = new Uint8Array(total);
        let pos = 0;
        for (const p of partes) { out.set(p, pos); pos += p.length; }
        return out;
    },

    // Descifrar → { data: ArrayBuffer, filename: string }
    // Acepta v3 (actual) y v1 (legado, con el nombre en claro en el encabezado)
    async decrypt(buffer, password) {
        const bytes = new Uint8Array(buffer);
        // Verificar firma C3VL
        if (bytes[0]!==0x43||bytes[1]!==0x33||bytes[2]!==0x56||bytes[3]!==0x4C) {
            throw new Error('El archivo no es un vault Cryptum válido o está corrupto.');
        }
        let pos = 4;
        const ver = bytes[pos++];
        if (ver !== K.VER && ver !== K.VER_LEGADO) {
            throw new Error(`Versión de formato ${ver} no soportada.`);
        }

        const salt = buffer.slice(pos, pos + K.SALT_LEN); pos += K.SALT_LEN;
        const iv   = buffer.slice(pos, pos + K.IV_LEN);   pos += K.IV_LEN;

        // Solo v1 lleva el nombre en claro antes del texto cifrado
        let nombreLegado = '';
        if (ver === K.VER_LEGADO) {
            const dv      = new DataView(buffer);
            const nomLen  = dv.getUint32(pos, true); pos += 4;
            if (nomLen > 4096) throw new Error('Encabezado corrupto.');
            nombreLegado = new TextDecoder().decode(bytes.slice(pos, pos + nomLen));
            pos += nomLen;
        }

        const ct  = buffer.slice(pos);
        const key = await this.deriveKey(password, new Uint8Array(salt));
        let plain;
        try {
            plain = await crypto.subtle.decrypt(
                { name:'AES-GCM', iv: new Uint8Array(iv), tagLength: K.TAG_BITS }, key, ct
            );
        } catch {
            throw new Error('Contraseña incorrecta o archivo dañado. Verifica la contraseña e inténtalo de nuevo.');
        }

        if (ver === K.VER_LEGADO) return { data: plain, filename: nombreLegado };
        return unpackNamePlusData(plain);
    }
};

/* ═══════════════════════════════════════════════════════════════
   API — Comunicación con el servidor (solo para función de enlace)
   ═══════════════════════════════════════════════════════════════ */
const API = {
    async upload(blob) {
        // El blob cifrado viaja como binario crudo: sin base64 el cuerpo pesa
        // un 33% menos y el servidor lo escribe a disco por bloques sin
        // cargarlo completo en memoria.
        const resp = await fetch(APP_BASE + '/api/upload', {
            method: 'POST',
            headers: { 'Content-Type': 'application/octet-stream' },
            body: blob
        });
        const data = await resp.json();
        if (!data.ok) throw new Error(data.error || 'Error al subir a bóveda');
        return data;
    },

    async download(token) {
        const resp = await fetch(`${APP_BASE}/api/download?t=${token}`);
        if (!resp.ok) {
            let msg = 'Error al descargar';
            try { msg = (await resp.json()).error || msg; } catch {}
            throw new Error(msg);
        }
        return resp.arrayBuffer();
    },

    async status(token) {
        const resp = await fetch(`${APP_BASE}/api/status?t=${token}`);
        return resp.json();
    }
};

/* ═══════════════════════════════════════════════════════════════
   COMPARTIR
   ═══════════════════════════════════════════════════════════════ */
const Share = {
    // Abre un enlace con <a> temporal — más fiable que window.open en móvil y escritorio
    openLink(url) {
        const a = document.createElement('a');
        a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer';
        a.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => document.body.removeChild(a), 100);
    },

    // Abre la app de escritorio y la versión web como respaldo en la misma acción del usuario.
    // Ambas llamadas son síncronas dentro del click handler → el popup blocker no las bloquea.
    // Si la app está instalada, el SO la activa al recibir el scheme custom;
    // la pestaña web queda abierta de respaldo para el caso en que no esté instalada.
    openAppWithFallback(appUrl, webUrl) {
        this.openLink(appUrl);   // intenta app de escritorio (whatsapp://, tg://, etc.)
        this.openLink(webUrl);   // abre versión web — garantizado, nunca bloqueado
    },

    // Share API nativa del sistema — en móvil muestra hoja con todas las apps instaladas
    async native(payload) {
        if (!navigator.share) return false;

        if (payload instanceof File) {
            // Intenta compartir el archivo real (.c3v)
            if (navigator.canShare?.({ files: [payload] })) {
                try {
                    await navigator.share({ files: [payload], title: 'Archivo cifrado — C3r0d4y' });
                    return true;
                } catch(e) {
                    if (e.name === 'AbortError') return true;
                    // El SO no permite compartir este tipo de archivo;
                    // cae en texto para que la hoja de apps se abra de todas formas
                }
            }
            // Fallback: abre la hoja del sistema con el nombre del archivo como texto
            try {
                await navigator.share({
                    title: 'Archivo cifrado — C3r0d4y',
                    text: payload.name + '\nDescifra con C3r0d4y. Comparte la contraseña por separado.'
                });
            } catch(e) { /* AbortError u otro — el usuario canceló o hay error de contexto */ }
            return true;
        }

        // Enlace seguro — comparte la URL directamente
        try {
            await navigator.share({ title: 'Enlace seguro — C3r0d4y', url: payload });
        } catch(e) { /* AbortError */ }
        return true;
    },

    // Intenta compartir el archivo real; devuelve true si lo logró (o usuario canceló)
    async tryShareFile(file, text) {
        if (!navigator.share || !navigator.canShare?.({ files: [file] })) return false;
        try {
            await navigator.share({ files: [file], title: 'Archivo cifrado — C3r0d4y', text });
            return true;
        } catch(e) {
            return e.name === 'AbortError'; // cancelado por usuario = manejado
        }
    },

    whatsapp(text) {
        const t = encodeURIComponent(text);
        // whatsapp://send → abre WhatsApp Desktop si está instalado
        // api.whatsapp.com/send → URL oficial para compartir texto sin destinatario fijo
        this.openAppWithFallback(
            'whatsapp://send?text=' + t,
            'https://api.whatsapp.com/send?text=' + t
        );
    },
    telegram(text, url) {
        const t = encodeURIComponent(text);
        const u = url ? encodeURIComponent(url) : '';
        const appQ = u ? 'url=' + u + '&text=' + t : 'text=' + t;
        const webQ = u ? 'url=' + u + '&text=' + t : 'url=&text=' + t;
        // tg://msg_url → abre Telegram Desktop si está instalado
        // t.me/share/url → URL oficial de compartir de Telegram Web
        this.openAppWithFallback(
            'tg://msg_url?' + appQ,
            'https://t.me/share/url?' + webQ
        );
    },
    async signal(text) {
        // Signal no tiene URL de compartir público; se copia y se pega en la app
        const ok = await this.clipboard(text);
        showToast(ok ? '✓ Copiado — pega en Signal' : 'No se pudo copiar');
    },
    email(subject, body) {
        window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    },
    async clipboard(text) {
        try { await navigator.clipboard.writeText(text); return true; }
        catch { return false; }
    },

    buildGrid(containerId, url, isFile = false, blob = null) {
        const c = $(containerId);
        if (!c) return;
        c.innerHTML = '';

        // Móvil / navegadores con Web Share API — un solo botón ancho completo
        if (navigator.share) {
            c.classList.add('share-grid-solo');
            const btn = document.createElement('button');
            btn.className = 'share-btn share-btn-solo';
            btn.innerHTML =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
                '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>' +
                '<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>' +
                '</svg>Compartir';
            btn.addEventListener('click', () => this.native(isFile && blob ? blob : url));
            c.appendChild(btn);
            return;
        }
        c.classList.remove('share-grid-solo');

        // Escritorio sin Web Share API — botones individuales por plataforma
        const fileText = '🔒 Archivo cifrado con C3r0d4y. La contraseña te la envío por separado.';
        const linkText = '🔒 Enlace de descarga única (expira en 5 min):\n' + url + '\n\nDescifra con C3r0d4y. La contraseña va por un canal separado.';
        const shareText = isFile ? fileText : linkText;

        const emailSubj = isFile ? 'Archivo cifrado — C3r0d4y' : 'Enlace seguro de descarga — C3r0d4y';
        const emailBody = isFile
            ? 'Te envío un archivo cifrado con AES-256-GCM (C3r0d4y).\n\nNecesitas C3r0d4y para descifrarlo. La contraseña te la envío por un canal separado.'
            : 'Enlace de descarga única (expira en 5 min):\n' + url + '\n\nNecesitas C3r0d4y para descifrarlo. La contraseña te la envío por un canal separado.';

        const btns = [
            {
                label: 'WhatsApp', cls: 'wa',
                icon: '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
                fn: () => this.whatsapp(shareText)
            },
            {
                label: 'Telegram', cls: 'tg',
                icon: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
                fn: () => isFile
                    ? this.telegram(fileText)
                    : this.telegram('C3r0d4y · archivo cifrado AES-256-GCM · la contraseña va por separado', url)
            },
            {
                label: 'Signal', cls: 'sg',
                icon: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                fn: () => this.signal(shareText)
            },
            {
                label: 'Correo', cls: 'em',
                icon: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
                fn: () => this.email(emailSubj, emailBody)
            },
        ];

        btns.forEach(b => {
            const btn = document.createElement('button');
            btn.className = 'share-btn ' + b.cls;
            btn.innerHTML =
                `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${b.icon}</svg>` +
                b.label;
            btn.addEventListener('click', b.fn);
            c.appendChild(btn);
        });
    }
};

/* ═══════════════════════════════════════════════════════════════
   FLUJO: CIFRADO
   ═══════════════════════════════════════════════════════════════ */
const Enc = {
    file: null,
    resultBytes: null,
    resultName: '',

    init() {
        UI.setupDrop('enc-drop', 'enc-file-input', f => this.setFile(f));
    },

    setFile(f) {
        if (f.size > K.MAX_MB * 1048576) {
            setAlert('enc-alert','err',`Archivo demasiado grande (máx ${K.MAX_MB} MB).`);
            return;
        }
        this.file = f;
        const zone = $('enc-drop');
        zone.classList.add('has-file');
        $('enc-drop-icon').innerHTML = `<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
        // esc(): el nombre del archivo es dato externo, nunca va crudo a innerHTML
        $('enc-drop-title').innerHTML = `<div class="drop-file-name">${esc(f.name)}</div>`;
        $('enc-drop-hint').innerHTML  = `<div class="drop-file-size">${fmtBytes(f.size)}</div>`;
        clearAlert('enc-alert');
        this.validate();
    },

    onPwd() {
        const v = $('enc-pwd').value;
        const f = v ? this.strength(v) : -1;
        const cls = ['l1','l1','l2','l3','l4'];
        const lbl = ['Muy débil','Débil','Regular','Buena','Fuerte'];
        for (let i=0;i<4;i++) { const s=$('sg'+i); s.className='seg'; if(f>=0&&i<=f) s.classList.add(cls[f]); }
        $('enc-stxt').textContent = v ? lbl[f] : '–';
        this.onPwd2(); this.validate();
    },

    onPwd2() {
        const p1=$('enc-pwd').value, p2=$('enc-pwd2').value;
        const el=$('enc-match');
        if (!p2)      { el.textContent=''; el.className='match-txt'; }
        else if(p1===p2) { el.textContent='✓ Las contraseñas coinciden'; el.className='match-txt ok'; }
        else          { el.textContent='✗ No coinciden'; el.className='match-txt err'; }
        this.validate();
    },

    strength(p) {
        let s=0;
        if(p.length>=8)s++; if(p.length>=12)s++; if(p.length>=16)s++;
        if(/[A-Z]/.test(p))s++; if(/[a-z]/.test(p))s++;
        if(/[0-9]/.test(p))s++; if(/[^A-Za-z0-9]/.test(p))s++;
        return Math.min(4, Math.floor(s/1.75));
    },

    validate() {
        const ok = !!this.file && $('enc-pwd').value.length>0 && $('enc-pwd').value===$('enc-pwd2').value;
        $('enc-btn').disabled = !ok;
    },

    async run() {
        const pwd = $('enc-pwd').value;
        clearAlert('enc-alert');
        hide('enc-btn'); show('enc-loading');
        try {
            $('enc-loading-msg').innerHTML = 'Derivando clave criptográfica…<br><small style="opacity:.6">Esto tarda unos segundos por diseño de seguridad</small>';
            const buf = await this.file.arrayBuffer();
            $('enc-loading-msg').innerHTML = 'Cifrando con AES-256-GCM…';
            this.resultBytes = await Crypto.encrypt(buf, this.file.name, pwd);
            this.resultName  = this.file.name + K.EXT;

            // Limpiar contraseña de memoria
            $('enc-pwd').value = ''; $('enc-pwd2').value = '';

            hide('enc-loading');
            Res.show(this.resultBytes, this.resultName);
        } catch(e) {
            hide('enc-loading'); show('enc-btn');
            setAlert('enc-alert','err', e.message || 'Error inesperado durante el cifrado.');
        }
    },

    reset() {
        this.file=null; this.resultBytes=null; this.resultName='';
        $('enc-file-input').value='';
        $('enc-drop').classList.remove('has-file','over');
        $('enc-drop-icon').innerHTML='<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
        $('enc-drop-title').textContent='Arrastra tu archivo aquí';
        $('enc-drop-hint').textContent='o haz clic — cualquier formato · máx 100 MB';
        $('enc-pwd').value=''; $('enc-pwd2').value='';
        clearAlert('enc-alert'); show('enc-btn'); hide('enc-loading');
        $('enc-btn').disabled=true;
        for(let i=0;i<4;i++){const s=$('sg'+i);s.className='seg';}
        $('enc-stxt').textContent='–'; $('enc-match').textContent='';
        Nav.go('encrypt');
    }
};

/* ═══════════════════════════════════════════════════════════════
   FLUJO: RESULTADO DEL CIFRADO
   ═══════════════════════════════════════════════════════════════ */
const Res = {
    blob: null,
    name: '',
    url:  '',
    timerInterval: null,

    show(bytes, name) {
        this.blob = new Blob([bytes], { type:'application/octet-stream' });
        this.name = name;
        $('res-name').textContent = name;
        $('res-size').textContent = fmtBytes(bytes.byteLength) + ' · AES-256-GCM cifrado';
        // Mostrar panel descarga por defecto
        this.selectOpt('dl');
        Nav.go('result');
    },

    selectOpt(opt) {
        $('opt-dl').classList.toggle('selected', opt==='dl');
        $('opt-lnk').classList.toggle('selected', opt==='lnk');
        if (opt==='dl') { show('panel-dl'); hide('panel-lnk'); }
        else            { hide('panel-dl'); this.initLink(); }
    },

    download() {
        if (!this.blob) return;
        const url = URL.createObjectURL(this.blob);
        const a = document.createElement('a'); a.href=url; a.download=this.name; a.click();
        setTimeout(()=>URL.revokeObjectURL(url), 30000);
    },

    async initLink() {
        show('panel-lnk');
        show('lnk-loading'); hide('lnk-ready'); hide('lnk-error');
        clearInterval(this.timerInterval);
        try {
            const result = await API.upload(this.blob);
            this.url = result.url;
            hide('lnk-loading');
            $('lnk-url').textContent = result.url;
            show('lnk-ready');
            Share.buildGrid('lnk-share-grid', result.url);

            // Temporizador de expiración
            let secs = result.expiry_sec || 300;
            const update = () => {
                $('lnk-seconds').textContent = fmtSecs(secs);
                const tr = $('lnk-timer');
                tr.className = 'timer-ring' + (secs<=60?' crit':secs<=120?' warn':'');
                if (secs<=0) { clearInterval(this.timerInterval); $('lnk-seconds').textContent='00:00'; }
                secs--;
            };
            update();
            this.timerInterval = setInterval(update, 1000);
        } catch(e) {
            hide('lnk-loading');
            $('lnk-error').innerHTML = alertHtml('err', e.message || 'Error al subir archivo a bóveda.');
            show('lnk-error');
        }
    },

    async copyLink() {
        const ok = await Share.clipboard(this.url);
        const btn = $('lnk-copy-btn');
        btn.textContent = ok ? 'Copiado ✓' : 'Error';
        btn.classList.toggle('copied', ok);
        setTimeout(()=>{ btn.textContent='Copiar'; btn.classList.remove('copied'); }, 2000);
    }
};

/* ═══════════════════════════════════════════════════════════════
   FLUJO: DESCIFRADO POR ENLACE
   ═══════════════════════════════════════════════════════════════ */
const DecLink = {
    token: '',
    encBuffer: null,
    timerInterval: null,

    async init(token) {
        this.token = token;
        Nav.go('decrypt-link');
        show('dl-checking'); hide('dl-form'); hide('dl-invalid'); hide('dl-success');
        try {
            const status = await API.status(token);
            hide('dl-checking');
            if (!status.valid) {
                $('dl-invalid-msg').textContent = {
                    not_found: 'Este enlace no existe o ya fue eliminado.',
                    expired:   'El enlace ha expirado. Los enlaces tienen una validez de 5 minutos.',
                    used:      'Este enlace ya fue utilizado. Solo permite una descarga.',
                }[status.reason] || 'Enlace inválido o expirado.';
                show('dl-invalid');
                return;
            }
            // Mostrar formulario con temporizador
            show('dl-form');
            let secs = status.remaining;
            const update = () => {
                $('dl-seconds').textContent = fmtSecs(secs);
                const tr = $('dl-timer');
                tr.className = 'timer-ring' + (secs<=60?' crit':secs<=120?' warn':'');
                if (secs<=0) {
                    clearInterval(this.timerInterval);
                    hide('dl-form');
                    $('dl-invalid-msg').textContent = 'El enlace expiró mientras esperabas. Solicita uno nuevo.';
                    show('dl-invalid');
                }
                secs--;
            };
            update();
            this.timerInterval = setInterval(update, 1000);
        } catch(e) {
            hide('dl-checking');
            $('dl-invalid-msg').textContent = 'Error al verificar el enlace: ' + (e.message||'');
            show('dl-invalid');
        }
    },

    onInput() { $('dl-btn').disabled = !$('dl-pwd').value; },

    async run() {
        const pwd = $('dl-pwd').value;
        clearAlert('dl-alert');
        hide('dl-btn'); show('dl-loading'); $('dl-loading-msg').textContent='Descargando archivo cifrado…';
        try {
            $('dl-loading-msg').textContent = 'Descargando archivo cifrado…';
            const buf = await API.download(this.token);
            $('dl-loading-msg').textContent = 'Derivando clave y descifrando…';
            clearInterval(this.timerInterval);

            const { data, filename } = await Crypto.decrypt(buf, pwd);
            $('dl-loading-msg').textContent = 'Preparando descarga…';

            // Descarga automática del archivo descifrado
            const blob = new Blob([data], { type:'application/octet-stream' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a'); a.href=url; a.download=filename; a.click();
            setTimeout(()=>URL.revokeObjectURL(url),30000);

            // Limpiar datos sensibles de memoria
            $('dl-pwd').value='';
            hide('dl-loading'); hide('dl-form');
            show('dl-success');
        } catch(e) {
            hide('dl-loading'); show('dl-btn');
            setAlert('dl-alert','err', e.message||'Error al descifrar.');
        }
    }
};

/* ═══════════════════════════════════════════════════════════════
   FLUJO: DESCIFRADO DE ARCHIVO LOCAL
   ═══════════════════════════════════════════════════════════════ */
const DecFile = {
    file: null,

    init() {
        UI.setupDrop('dec-drop','dec-file-input', f => this.setFile(f));
    },

    setFile(f) {
        if (!f.name.endsWith(K.EXT) && !confirm(`El archivo "${f.name}" no tiene extensión .c3v. ¿Intentar de todos modos?`)) return;
        this.file = f;
        const zone = $('dec-drop');
        zone.classList.add('has-file');
        $('dec-drop-icon').innerHTML='<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        // esc(): el nombre del archivo es dato externo, nunca va crudo a innerHTML
        $('dec-drop-title').innerHTML=`<div class="drop-file-name">${esc(f.name)}</div>`;
        $('dec-drop-hint').innerHTML=`<div class="drop-file-size">${fmtBytes(f.size)}</div>`;
        clearAlert('dec-alert'); hide('dec-success');
        this.validate();
    },

    onInput() { this.validate(); },

    validate() {
        $('dec-btn').disabled = !this.file || !$('dec-pwd').value;
    },

    async run() {
        const pwd = $('dec-pwd').value;
        clearAlert('dec-alert');
        hide('dec-btn'); show('dec-loading'); hide('dec-success');
        try {
            const buf = await this.file.arrayBuffer();
            const { data, filename } = await Crypto.decrypt(buf, pwd);

            const blob = new Blob([data], { type:'application/octet-stream' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a'); a.href=url; a.download=filename; a.click();
            setTimeout(()=>URL.revokeObjectURL(url),30000);

            // Limpiar datos sensibles
            $('dec-pwd').value='';
            this.file=null; $('dec-file-input').value='';
            hide('dec-loading'); show('dec-success');
            show('dec-btn'); $('dec-btn').disabled=true;
        } catch(e) {
            hide('dec-loading'); show('dec-btn');
            setAlert('dec-alert','err', e.message||'Error al descifrar. Verifica la contraseña.');
        }
    }
};

/* ═══════════════════════════════════════════════════════════════
   CIFRADO USB — ITERADOR DE ARCHIVOS (recursivo)
   ═══════════════════════════════════════════════════════════════ */

// Recorre todos los archivos de un directorio de forma recursiva
async function* walkFiles(dirHandle, relPath = '') {
    for await (const [name, handle] of dirHandle.entries()) {
        if (name.startsWith('.')) continue; // saltar ocultos/meta
        const rp = relPath ? relPath + '/' + name : name;
        if (handle.kind === 'file') {
            yield { handle, name, relPath: rp, parentHandle: dirHandle };
        } else if (handle.kind === 'directory') {
            yield* walkFiles(handle, rp);
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
   CIFRADO USB — CRYPTO CON CLAVE MAESTRA ÚNICA
   Derivación PBKDF2 se ejecuta UNA sola vez por todo el dispositivo.
   ─────────────────────────────────────────────────────────────────
   Formato archivo USB v4 (actual — nombre dentro del cifrado):
   [C3VL][0x04][IV(12)][CIPHERTEXT+TAG]
   Formato v2 (legado, solo lectura — nombre en claro):
   [C3VL][0x02][IV(12)][NOMLEN(4)][NOM(N)][CIPHERTEXT+TAG]
   ─────────────────────────────────────────────────────────────────
   Meta vault (salt del dispositivo):
   [C3VM][SALT(32)]
   ═══════════════════════════════════════════════════════════════ */
const USB_VER        = 0x04;
const USB_VER_LEGADO = 0x02;
const USB_META       = '.cryptum_meta.bin';
const META_MAGIC     = new Uint8Array([0x43, 0x33, 0x56, 0x4D]); // "C3VM"

const USBCrypto = {

    // Deriva la clave maestra una sola vez (PBKDF2-SHA-512)
    async deriveMasterKey(password, salt) {
        const enc = new TextEncoder();
        const raw = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name:'PBKDF2', salt, iterations: K.KDF_ITER, hash:'SHA-512' },
            raw,
            { name:'AES-GCM', length: K.KEY_BITS },
            false,
            ['encrypt','decrypt']
        );
    },

    // Cifra un buffer con la clave maestra ya derivada (IV aleatorio único por archivo).
    // v4: el nombre del archivo se cifra junto con los datos (en v2 iba en claro).
    async encryptBuf(buffer, filename, masterKey) {
        const iv     = crypto.getRandomValues(new Uint8Array(K.IV_LEN));
        const plano  = packNamePlusData(filename, buffer);
        const ct     = await crypto.subtle.encrypt({ name:'AES-GCM', iv, tagLength:K.TAG_BITS }, masterKey, plano);
        const partes = [K.MAGIC, new Uint8Array([USB_VER]), iv, new Uint8Array(ct)];
        const total  = partes.reduce((s,p)=>s+p.length,0);
        const out    = new Uint8Array(total);
        let pos=0; for(const p of partes){out.set(p,pos);pos+=p.length;}
        return out;
    },

    // Descifra un buffer usando la clave maestra (acepta v4 actual y v2 legado)
    async decryptBuf(buffer, masterKey) {
        const b = new Uint8Array(buffer);
        if (b[0]!==0x43||b[1]!==0x33||b[2]!==0x56||b[3]!==0x4C) throw new Error('No es un archivo Cryptum.');
        const ver = b[4];
        // Archivos individuales (v1/v3) usan sal propia — el caller usa Crypto.decrypt
        if (ver === K.VER || ver === K.VER_LEGADO) throw new Error('VER_V1');
        if (ver !== USB_VER && ver !== USB_VER_LEGADO) throw new Error(`Versión ${ver} no soportada.`);
        let pos = 5;
        const iv = buffer.slice(pos, pos+K.IV_LEN); pos+=K.IV_LEN;

        // Solo v2 lleva el nombre en claro antes del texto cifrado
        let nombreLegado = '';
        if (ver === USB_VER_LEGADO) {
            const dv     = new DataView(buffer);
            const nomLen = dv.getUint32(pos,true); pos+=4;
            if(nomLen>4096) throw new Error('Encabezado corrupto.');
            nombreLegado = new TextDecoder().decode(new Uint8Array(buffer,pos,nomLen));
            pos+=nomLen;
        }

        const ct = buffer.slice(pos);
        let plain;
        try {
            plain = await crypto.subtle.decrypt({name:'AES-GCM',iv:new Uint8Array(iv),tagLength:K.TAG_BITS},masterKey,ct);
        } catch { throw new Error('Contraseña incorrecta o archivo dañado.'); }

        if (ver === USB_VER_LEGADO) return { data:plain, filename:nombreLegado };
        return unpackNamePlusData(plain);
    },

    // Escribe el archivo de metadatos con la sal del vault
    async writeMeta(rootHandle, salt) {
        const buf = new Uint8Array(META_MAGIC.length + salt.length);
        buf.set(META_MAGIC); buf.set(salt, META_MAGIC.length);
        const h  = await rootHandle.getFileHandle(USB_META, {create:true});
        const wr = await h.createWritable();
        await wr.write(buf); await wr.close();
    },

    // Lee la sal del vault desde el archivo de metadatos
    async readMeta(rootHandle) {
        let h;
        try { h = await rootHandle.getFileHandle(USB_META); }
        catch { throw new Error('No se encontraron metadatos Cryptum en este dispositivo. ¿Fue cifrado con Cryptum?'); }
        const buf = await (await h.getFile()).arrayBuffer();
        const b   = new Uint8Array(buf);
        if(b[0]!==0x43||b[1]!==0x33||b[2]!==0x56||b[3]!==0x4D) throw new Error('Metadatos de vault corruptos.');
        return b.slice(4, 4+K.SALT_LEN);
    },

    /*
     * Elimina un archivo sobrescribiéndolo con ceros — MEJOR ESFUERZO.
     * keepExistingData evita que el navegador trunque el archivo antes de
     * escribir. Aun así, la File System Access API escribe en un archivo
     * temporal que reemplaza al original al cerrar, y en memorias USB/SSD
     * el controlador puede conservar copias internas (wear leveling).
     * NO debe presentarse al usuario como borrado forense garantizado.
     */
    async secureDelete(parentHandle, name, size) {
        try {
            const fh = await parentHandle.getFileHandle(name);
            const wr = await fh.createWritable({ keepExistingData: true });
            await wr.write({ type: 'write', position: 0, data: new Uint8Array(size).fill(0) });
            await wr.close();
            await parentHandle.removeEntry(name);
        } catch { /* mejor esfuerzo */ }
    }
};

/* ═══════════════════════════════════════════════════════════════
   FLUJO USB — CIFRADO DEL DISPOSITIVO
   ═══════════════════════════════════════════════════════════════ */
/* Fábrica de controladores de directorio.
   USB y Carpeta comparten exactamente el mismo motor de cifrado (ambos
   operan sobre un directorio elegido con el selector del navegador); lo
   único que cambia son los IDs del DOM y algunos textos. En vez de
   duplicar 400 líneas, se crea una instancia por vista:
     p  → prefijo de los IDs en el HTML ('usb' o 'fld')
     op → textos y opciones propias de cada modo
   ══════════════════════════════════════════════════════════════ */
function crearDirUI(p, op) {
  return {
    mode: 'enc',
    selectedMountpoint: '',
    // Solo aplica a la vista USB: mientras no se detecte ninguna unidad
    // removible, el botón de cifrar permanece deshabilitado.
    hayDispositivos: false,

    setMode(m) {
        this.mode = m;
        $(p + '-tab-enc').classList.toggle('active', m==='enc');
        $(p + '-tab-dec').classList.toggle('active', m==='dec');
        $(p + '-pwd2-field').style.display = m==='enc' ? '' : 'none';
        $(p + '-sbar').style.display  = m==='enc' ? '' : 'none';
        $(p + '-stxt').style.display  = m==='enc' ? '' : 'none';
        $(p + '-warn-enc').style.display = m==='enc' ? '' : 'none';
        $(p + '-warn-dec').style.display = m==='dec' ? '' : 'none';
        // Las etiquetas del botón las define cada instancia (USB o carpeta)
        $(p + '-btn-label').textContent = m==='enc' ? op.btnEnc : op.btnDec;
        this.validate();
    },

    async loadDevices() {
        // Solo la vista USB tiene panel de dispositivos
        if (!op.listaDispositivos || !$(p + '-device-list')) return;
        $(p + '-device-list').innerHTML = '<div class="loading"><div class="spinner"></div><div class="loading-msg">Escaneando dispositivos…</div></div>';
        try {
            const resp = await fetch(APP_BASE + '/api/list-usb');
            const data = await resp.json();

            /* El listado de dispositivos lo arma el servidor con lsblk, así que
               solo tiene sentido cuando Cryptum corre en la propia máquina. Si
               la app está en un servidor remoto (403) o el hosting no permite
               ejecutar lsblk (note), no hay lista que mostrar — pero cifrar SÍ
               funciona igual, porque el selector de carpetas lo abre el propio
               navegador sobre el equipo del visitante. Se explica en vez de
               dejar un panel vacío que parece un error. */
            if (data.ok === false || data.note) {
                this.renderRemoteNotice();
                return;
            }
            this.renderDevices(data);
        } catch(e) {
            this.hayDispositivos = false;
            this.validate();
            $(p + '-device-list').innerHTML = alertHtml('err', 'No se pudo obtener la lista de dispositivos: ' + (e.message||''));
        }
    },

    /* Aviso para cuando la app corre en un servidor remoto: el listado no
       está disponible, pero el cifrado sí. Incluye el enlace al repositorio
       para quien prefiera ejecutar Cryptum en su propia máquina. */
    renderRemoteNotice() {
        $(p + '-device-list').innerHTML =
            '<div class="usb-empty usb-notice">' +
            '<strong>El listado de dispositivos solo funciona en modo local.</strong><br>' +
            'Esta copia de Cryptum corre en un servidor, y un servidor no puede ver las unidades conectadas a tu equipo.<br><br>' +
            'Para cifrar los archivos de tu USB desde aquí, usa <strong>Cifrar / descifrar carpeta</strong> ' +
            'y elige la carpeta de la unidad: todo el cifrado ocurre en tu equipo, ningún archivo ni contraseña sale de él.<br><br>' +
            '¿Prefieres el listado automático y el cifrado de disco completo? Ejecuta Cryptum en tu máquina: ' +
            '<a href="https://github.com/c3r0d4y/cryptum" target="_blank" rel="noopener">github.com/c3r0d4y/cryptum</a>' +
            '</div>';
        hide((p + '-mp-row'));
        this.selectedMountpoint = '';
        // Sin listado no hay dispositivo que cifrar desde aquí
        this.hayDispositivos = false;
        this.validate();
    },

    renderDevices(data) {
        const devices   = data.devices || [];
        const extraMnts = data.extra_mounts || [];
        const container = $(p + '-device-list');
        let html = '';

        // El botón de cifrar depende de que haya al menos una unidad
        this.hayDispositivos = devices.length > 0 || extraMnts.length > 0;

        if (!this.hayDispositivos) {
            html = '<div class="usb-empty usb-empty-warn">No se detectaron dispositivos USB o removibles.<br>Conecta una unidad y pulsa «Actualizar».</div>';
        } else {
            // esc() en todos los datos: vendor/model/label los reporta el propio
            // dispositivo USB, y un dispositivo malicioso podría inyectar HTML
            for (const dev of devices) {
                const label = [dev.vendor, dev.model].filter(Boolean).join(' ').trim() || dev.name;
                // data-mp guarda el mountpoint; el click se enlaza con addEventListener tras insertar el HTML
                html += `<div class="usb-card" data-mp="">
                    <span class="usb-card-icon"><svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/><rect x="7" y="16" width="10" height="4" rx="1"/></svg></span>
                    <span class="usb-card-info">
                        <span class="usb-card-name">${esc(label)}</span>
                        <span class="usb-card-meta">/dev/${esc(dev.name)} · ${esc(dev.size)} · ${esc((dev.tran||'USB').toUpperCase())}</span>
                    </span></div>`;
                // La variable se llama "part" y no "p": "p" es el prefijo de
                // los IDs de esta instancia y taparlo aquí sería una trampa
                for (const part of dev.parts) {
                    const mpInfo = part.mounted ? `<span class="mp">${esc(part.mountpoint)}</span>` : 'no montada';
                    html += `<div class="usb-part" data-mp="${esc(part.mountpoint||'')}">
                        └ /dev/${esc(part.name)} · ${esc(part.size)} · ${esc(part.fstype||'?')} · ${mpInfo}</div>`;
                }
            }
            for (const m of extraMnts) {
                html += `<div class="usb-card" data-mp="${esc(m.mountpoint)}">
                    <span class="usb-card-icon"><svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/><rect x="7" y="16" width="10" height="4" rx="1"/></svg></span>
                    <span class="usb-card-info">
                        <span class="usb-card-name">${esc(m.device)}</span>
                        <span class="usb-card-meta"><span class="mp">${esc(m.mountpoint)}</span> · ${esc(m.fstype)}</span>
                    </span></div>`;
            }
        }
        container.innerHTML = html;
        // Listener sin inline handler para cumplir la política CSP script-src 'self'
        container.querySelectorAll('.usb-card, .usb-part').forEach(el => {
            el.addEventListener('click', () => this.selectDevice(el, el.dataset.mp));
        });
        // Refrescar el botón: se habilita solo si se detectó alguna unidad
        this.validate();
    },

    selectDevice(el, mountpoint) {
        if (!op.listaDispositivos) return;
        document.querySelectorAll('.usb-card,.usb-part').forEach(e=>e.classList.remove('active'));
        el.classList.add('active');
        this.selectedMountpoint = mountpoint;
        if (mountpoint) {
            $(p + '-mp-val').textContent = mountpoint;
            show((p + '-mp-row'));
        } else {
            hide((p + '-mp-row'));
        }
        this.validate();
    },

    onPwd() {
        if (this.mode !== 'enc') { this.validate(); return; }
        const v = $(p + '-pwd').value;
        const f = v ? Enc.strength(v) : -1;
        const cls=['l1','l1','l2','l3','l4'], lbl=['Muy débil','Débil','Regular','Buena','Fuerte'];
        for(let i=0;i<4;i++){const s=$(p + '-sg'+i);s.className='seg';if(f>=0&&i<=f)s.classList.add(cls[f]);}
        $(p + '-stxt').textContent = v ? lbl[f] : '–';
        this.onPwd2(); this.validate();
    },

    onPwd2() {
        if(this.mode!=='enc') return;
        const p1=$(p + '-pwd').value, p2=$(p + '-pwd2').value;
        const el=$(p + '-match');
        if(!p2){el.textContent='';el.className='match-txt';}
        else if(p1===p2){el.textContent='✓ Las contraseñas coinciden';el.className='match-txt ok';}
        else{el.textContent='✗ No coinciden';el.className='match-txt err';}
        this.validate();
    },

    validate() {
        const hasCompat = !!window.showDirectoryPicker;
        // Modo alternativo visible solo cuando no hay File System Access API
        $(p + '-compat-warn').style.display     = hasCompat ? 'none' : '';
        $(p + '-fallback-section').style.display = hasCompat ? 'none' : 'flex';
        $(p + '-btn').style.display             = hasCompat ? '' : 'none';

        if (!hasCompat) { $(p + '-btn').disabled = true; return; }

        const pwd = $(p + '-pwd').value;
        let ok = pwd.length > 0;
        if (this.mode === 'enc') ok = ok && pwd === $(p + '-pwd2').value;

        /* En la vista USB no tiene sentido operar sin una unidad conectada:
           el botón se mantiene deshabilitado hasta que se detecte alguna.
           La vista de carpeta no pasa por aquí (no lista dispositivos). */
        if (op.listaDispositivos) {
            ok = ok && this.hayDispositivos;
            const aviso = $(p + '-need-device');
            if (aviso) aviso.style.display = this.hayDispositivos ? 'none' : '';
        }

        $(p + '-btn').disabled = !ok;
    },

    async run() {
        const hasCompat = !!window.showDirectoryPicker;
        if (!hasCompat) return; // el fallback se activa por su propio botón

        const pwd = $(p + '-pwd').value;
        clearAlert((p + '-alert')); hide((p + '-result'));

        // Abrir selector de directorio (nativo del SO)
        let rootHandle;
        try {
            rootHandle = await window.showDirectoryPicker({ mode: 'readwrite' });
        } catch(e) {
            if (e.name !== 'AbortError') setAlert((p + '-alert'),'err','No se pudo abrir el selector: ' + e.message);
            return;
        }

        hide((p + '-btn')); show((p + '-loading'));

        try {
            if (this.mode === 'enc') {
                await this.encryptDevice(rootHandle, pwd);
            } else {
                await this.decryptDevice(rootHandle, pwd);
            }
        } catch(e) {
            hide((p + '-loading')); show((p + '-btn'));
            setAlert((p + '-alert'),'err', e.message || 'Error inesperado.');
        } finally {
            // Limpiar contraseñas de memoria
            $(p + '-pwd').value=''; $(p + '-pwd2').value='';
        }
    },

    async encryptDevice(rootHandle, password) {
        // Verificar si ya está cifrado
        try {
            await rootHandle.getFileHandle(USB_META);
            throw new Error(`${op.det} ${op.ent} ya tiene un vault Cryptum activo. Descífralo primero antes de volver a cifrarlo.`);
        } catch(e) {
            if (e.message.includes('vault Cryptum')) throw e;
            // No existe meta → continuar
        }

        // Generar sal única para este dispositivo
        const salt = crypto.getRandomValues(new Uint8Array(K.SALT_LEN));

        // La sal se guarda ANTES de cifrar el primer archivo. Antes se
        // escribía al final: si el proceso se interrumpía a mitad (pestaña
        // cerrada, USB desconectada), los archivos ya cifrados quedaban
        // irrecuperables para siempre porque la sal nunca llegaba al disco.
        await USBCrypto.writeMeta(rootHandle, salt);

        // Derivar clave maestra UNA sola vez (PBKDF2)
        this.setProgress(-1,-1,'Derivando clave maestra (PBKDF2-SHA-512)…');
        const masterKey = await USBCrypto.deriveMasterKey(password, salt);

        // Recopilar todos los archivos a cifrar
        const files = [];
        this.setProgress(-1,-1,'Escaneando archivos…');
        for await (const f of walkFiles(rootHandle)) {
            if (!f.name.endsWith(K.EXT) && f.name !== USB_META) files.push(f);
        }

        if (files.length === 0) {
            // Sin archivos no hay vault: se retira la sal recién escrita
            try { await rootHandle.removeEntry(USB_META); } catch {}
            throw new Error('No se encontraron archivos para cifrar en el directorio seleccionado.');
        }

        // Cifrar archivo por archivo
        let done = 0;
        for (const { handle, name, relPath, parentHandle } of files) {
            this.setProgress(done, files.length, relPath);
            const file = await handle.getFile();
            const buf  = await file.arrayBuffer();
            const enc  = await USBCrypto.encryptBuf(buf, name, masterKey);

            // Escribir archivo cifrado (.c3v)
            const newH = await parentHandle.getFileHandle(name + K.EXT, {create:true});
            const wr   = await newH.createWritable();
            await wr.write(enc); await wr.close();

            // Verificar que la copia cifrada quedó completa ANTES de tocar
            // el original: si la escritura falló, el original se conserva.
            const check = await newH.getFile();
            if (check.size !== enc.length) {
                throw new Error(`No se pudo verificar la escritura de "${relPath}". Proceso detenido; el archivo original NO fue borrado.`);
            }

            // Eliminar original (sobrescritura con ceros de mejor esfuerzo)
            await USBCrypto.secureDelete(parentHandle, name, file.size);

            done++;
        }

        hide((p + '-loading')); show((p + '-btn'));
        $(p + '-result-txt').textContent = `✓ ${done} archivo${done!==1?'s':''} cifrado${done!==1?'s':''} con AES-256-GCM. El vault Cryptum está activo en ${op.laEnt}.`;
        show((p + '-result'));
    },

    async decryptDevice(rootHandle, password) {
        // Leer sal del dispositivo
        this.setProgress(-1,-1,'Leyendo metadatos del vault…');
        const salt = await USBCrypto.readMeta(rootHandle);

        // Derivar clave maestra UNA sola vez
        this.setProgress(-1,-1,'Derivando clave maestra (PBKDF2-SHA-512)…');
        const masterKey = await USBCrypto.deriveMasterKey(password, new Uint8Array(salt));

        // Recopilar archivos cifrados
        const files = [];
        this.setProgress(-1,-1,'Escaneando archivos cifrados…');
        for await (const f of walkFiles(rootHandle)) {
            if (f.name.endsWith(K.EXT)) files.push(f);
        }

        if (files.length === 0) {
            // Vault sin archivos cifrados (p. ej. un cifrado que se interrumpió
            // antes del primer archivo): se retira el meta para desbloquear
            // el dispositivo y poder cifrarlo de nuevo.
            try { await rootHandle.removeEntry(USB_META); } catch {}
            throw new Error('No se encontraron archivos .c3v; el vault estaba vacío y fue desactivado. Ya puedes cifrar el dispositivo de nuevo.');
        }

        // Descifrar archivo por archivo
        let done = 0, errors = 0;
        for (const { handle, name, relPath, parentHandle } of files) {
            this.setProgress(done, files.length, relPath);
            try {
                const file = await handle.getFile();
                const buf  = await file.arrayBuffer();
                let result;
                try {
                    result = await USBCrypto.decryptBuf(buf, masterKey);
                } catch(e) {
                    if (e.message === 'VER_V1') {
                        // Archivo cifrado en modo individual (v1) — usa Crypto.decrypt con password
                        result = await Crypto.decrypt(buf, password);
                    } else throw e;
                }
                const { data, filename } = result;

                // Escribir archivo descifrado
                const newH = await parentHandle.getFileHandle(filename, {create:true});
                const wr   = await newH.createWritable();
                await wr.write(data); await wr.close();

                // Eliminar archivo cifrado de forma segura
                await USBCrypto.secureDelete(parentHandle, name, file.size);
                done++;
            } catch(e) {
                errors++;
                console.error('Error descifrando', relPath, e.message);
            }
        }

        // Eliminar metadatos del vault
        try { await rootHandle.removeEntry(USB_META); } catch {}

        hide((p + '-loading')); show((p + '-btn'));
        let msg = `✓ ${done} archivo${done!==1?'s':''} descifrado${done!==1?'s':''}.`;
        if (errors > 0) msg += ` ⚠ ${errors} archivo${errors!==1?'s':''} con errores (contraseña incorrecta o dañados).`;
        $(p + '-result-txt').textContent = msg;
        show((p + '-result'));
    },

    // Modo alternativo: cifra archivos seleccionados con webkitdirectory y descarga cada .c3v
    async runFallback(files) {
        if (!files || files.length === 0) return;

        const pwd = $(p + '-pwd').value;
        if (!pwd) { setAlert((p + '-alert'),'warn','Ingresa una contraseña antes de seleccionar la carpeta.'); return; }
        if (this.mode === 'enc' && pwd !== $(p + '-pwd2').value) {
            setAlert((p + '-alert'),'err','Las contraseñas no coinciden.'); return;
        }

        clearAlert((p + '-alert')); hide((p + '-result'));
        const section = $(p + '-fallback-section');
        section.style.display = 'none';
        show((p + '-loading'));

        try {
            if (this.mode === 'enc') {
                // Genera sal única para esta sesión
                const salt = crypto.getRandomValues(new Uint8Array(K.SALT_LEN));
                this.setProgress(-1,-1,'Derivando clave maestra (PBKDF2-SHA-512)…');
                const masterKey = await USBCrypto.deriveMasterKey(pwd, salt);

                let done = 0;
                const total = files.length;
                for (const file of files) {
                    this.setProgress(done, total, file.name);
                    const buf = await file.arrayBuffer();
                    const enc = await USBCrypto.encryptBuf(buf, file.name, masterKey);
                    // Descargar el archivo cifrado
                    const blob = new Blob([enc], {type:'application/octet-stream'});
                    const url  = URL.createObjectURL(blob);
                    const a    = document.createElement('a');
                    a.href = url; a.download = file.name + K.EXT; a.click();
                    setTimeout(()=>URL.revokeObjectURL(url), 10000);
                    done++;
                    // Pequeña pausa para no saturar el navegador con muchas descargas
                    await new Promise(r=>setTimeout(r,120));
                }
                $(p + '-result-txt').textContent = `✓ ${done} archivo${done!==1?'s':''} cifrado${done!==1?'s':''} descargados como .c3v. Guárdalos en tu dispositivo.`;

            } else {
                // Modo descifrado fallback
                const total = Array.from(files).filter(f=>f.name.endsWith(K.EXT)).length;
                if (total === 0) throw new Error('No se encontraron archivos .c3v en la selección.');
                let done = 0, errors = 0;
                // Sin sal almacenada en meta — intentar con contraseña directa (v1)
                for (const file of files) {
                    if (!file.name.endsWith(K.EXT)) continue;
                    this.setProgress(done, total, file.name);
                    try {
                        const buf = await file.arrayBuffer();
                        const { data, filename } = await Crypto.decrypt(buf, pwd);
                        const blob = new Blob([data],{type:'application/octet-stream'});
                        const url  = URL.createObjectURL(blob);
                        const a    = document.createElement('a'); a.href=url; a.download=filename; a.click();
                        setTimeout(()=>URL.revokeObjectURL(url),10000);
                        done++;
                        await new Promise(r=>setTimeout(r,120));
                    } catch { errors++; }
                }
                let msg = `✓ ${done} archivo${done!==1?'s':''} descifrado${done!==1?'s':''}.`;
                if (errors) msg += ` ⚠ ${errors} archivo${errors!==1?'s':''} no pudieron descifrarse.`;
                $(p + '-result-txt').textContent = msg;
            }
        } catch(e) {
            setAlert((p + '-alert'),'err', e.message || 'Error inesperado.');
            section.style.display = 'flex';
            hide((p + '-loading')); return;
        } finally {
            $(p + '-pwd').value=''; $(p + '-pwd2').value='';
            $(p + '-fallback-input').value='';
        }

        hide((p + '-loading'));
        section.style.display = 'flex';
        show((p + '-result'));
    },

    setProgress(done, total, file) {
        if (done < 0) {
            $(p + '-prog-pct').textContent   = '…';
            $(p + '-prog-count').textContent = '';
            $(p + '-prog-fill').style.width  = '0%';
        } else {
            const pct = total > 0 ? Math.round((done / total) * 100) : 0;
            $(p + '-prog-fill').style.width  = pct + '%';
            $(p + '-prog-pct').textContent   = pct + '%';
            $(p + '-prog-count').textContent = done + ' / ' + total + ' archivos';
        }
        $(p + '-loading-msg').textContent = done < 0 ? file : 'Procesando archivos…';
        $(p + '-prog-file').textContent   = file;
    },

    // El bloque LUKS solo existe en la vista USB
    toggleLuks() {
        const btn = $('luks-toggle'), body = $('luks-body');
        if (!btn || !body) return;
        const open = body.classList.toggle('visible');
        btn.classList.toggle('open', open);
    },

    reset() {
        hide((p + '-result')); hide((p + '-loading'));
        show((p + '-btn')); $(p + '-btn').disabled=true;
        $(p + '-pwd').value=''; $(p + '-pwd2').value='';
        clearAlert((p + '-alert'));
        $(p + '-match').textContent='';
        hide((p + '-mp-row'));
        this.selectedMountpoint='';
        document.querySelectorAll('.usb-card,.usb-part').forEach(e=>e.classList.remove('active'));
        this.setMode('enc');
    }
  }
}

/* Instancia para dispositivos USB: incluye el panel de dispositivos
   detectados (solo útil en modo local) y el bloque LUKS. */
const USB_UI = crearDirUI('usb', {
    listaDispositivos: true,
    ent:   'dispositivo',
    laEnt: 'el dispositivo',
    det:   'Este',
    btnEnc: 'Seleccionar directorio USB y cifrar',
    btnDec: 'Seleccionar directorio USB y descifrar',
});

/* Instancia para carpetas normales: mismo motor, sin panel de
   dispositivos — funciona igual en local y en producción, porque el
   selector de carpetas lo abre el navegador en el equipo del visitante. */
const FLD_UI = crearDirUI('fld', {
    listaDispositivos: false,
    ent:   'carpeta',
    laEnt: 'la carpeta',
    det:   'Esta',
    btnEnc: 'Seleccionar carpeta y cifrar',
    btnDec: 'Seleccionar carpeta y descifrar',
});

/* ═══════════════════════════════════════════════════════════════
   MODAL: DIAGRAMA DE CIFRADO
   ═══════════════════════════════════════════════════════════════ */
const DiagModal = {
    open() {
        const o = $('diag-overlay');
        o.classList.add('open');
        document.body.style.overflow = 'hidden';
        // Enfocar para accesibilidad (cierre con Escape)
        o.focus?.();
    },
    close() {
        $('diag-overlay').classList.remove('open');
        document.body.style.overflow = '';
    },
    onOverlayClick(e) {
        // Cerrar solo si se hace clic en el fondo oscuro, no en el modal
        if (e.target === $('diag-overlay')) this.close();
    }
};
// Cierre con tecla Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') DiagModal.close(); });

/* ═══════════════════════════════════════════════════════════════
   GENERADOR DE CONTRASEÑAS SEGURAS
   ═══════════════════════════════════════════════════════════════ */
const PwdGen = {
    // Caracteres que evitan ambigüedad visual (sin 0/O, 1/l/I)
    chars: 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*-_=+?',

    generate(len = 22) {
        const buf = new Uint8Array(len * 2);
        crypto.getRandomValues(buf);
        let out = '';
        for (let i = 0; i < buf.length && out.length < len; i++) {
            const c = this.chars[buf[i] % this.chars.length];
            out += c;
        }
        return out;
    },

    // Aplica contraseña a un par de campos y muestra el strip
    apply(id1, id2, stripId, valId, triggerFn1, triggerFn2) {
        const pwd = this.generate();
        const f1 = $(id1), f2 = $(id2 || '');
        if (!f1) return;
        f1.value = pwd; f1.type = 'text';
        if (f2) { f2.value = pwd; f2.type = 'text'; }
        triggerFn1();
        if (triggerFn2) triggerFn2();
        const strip = $(stripId), val = $(valId);
        if (strip && val) { val.textContent = pwd; strip.style.display = 'flex'; }
    },

    copy(valId, btnId) {
        const val = $(valId), btn = $(btnId);
        if (!val) return;
        navigator.clipboard.writeText(val.textContent).then(() => {
            if (btn) {
                const prev = btn.textContent;
                btn.textContent = '¡Copiada!';
                setTimeout(() => { btn.textContent = prev; }, 1800);
            }
        });
    }
};

/* ═══════════════════════════════════════════════════════════════
   INICIALIZACIÓN
   ═══════════════════════════════════════════════════════════════ */
(function init() {
    // Comprobar soporte de Web Crypto API
    if (!window.crypto?.subtle) {
        document.body.innerHTML = '<div style="padding:40px;text-align:center;color:#c07070;font-family:monospace">Tu navegador no soporta la API de Criptografía Web requerida.<br>Usa Chrome 60+, Firefox 57+, Safari 11+ o Edge 79+.</div>';
        return;
    }

    Enc.init();
    DecFile.init();

    // Preparar las vistas de directorio al entrar en ellas
    const origGo = Nav.go.bind(Nav);
    Nav.go = function(name) {
        origGo(name);
        if (name === 'usb') {
            USB_UI.setMode('enc');
            USB_UI.loadDevices();   // panel de dispositivos (solo modo local)
            USB_UI.validate();
        } else if (name === 'folder') {
            FLD_UI.setMode('enc');
            FLD_UI.validate();      // la carpeta no necesita panel de dispositivos
        }
    };

    // Token de descifrado leído del atributo data-token del body
    if (APP_TOKEN && /^[0-9a-f]{32}$/.test(APP_TOKEN)) {
        DecLink.init(APP_TOKEN);
    } else {
        Nav.go('home');
    }

    bindEvents();
})();

/* Enlaza todos los eventos desde JS — sin onclick/oninput inline en el HTML */
function bindEvents() {
    const on = (id, ev, fn) => { const e = $(id); if (e) e.addEventListener(ev, fn); };

    // Modal de diagrama
    on('diag-open-btn',  'click', () => DiagModal.open());
    on('diag-close-btn', 'click', () => DiagModal.close());
    on('diag-overlay',   'click', e  => DiagModal.onOverlayClick(e));

    // Aviso de seguridad
    on('sec-toggle', 'click', () => UI.toggleSec());

    // Inicio — navegación a las tres funciones
    on('btn-go-encrypt', 'click', () => Nav.go('encrypt'));
    on('btn-go-folder',  'click', () => Nav.go('folder'));
    on('btn-go-usb',     'click', () => Nav.go('usb'));

    // Pestañas de la vista de archivo: cifrar ⇄ descifrar sin volver al inicio
    on('file-tab-dec', 'click', () => Nav.go('decrypt-file'));
    on('fdec-tab-enc', 'click', () => Nav.go('encrypt'));

    // Vista: cifrar
    on('enc-back',     'click', () => Nav.go('home'));
    on('enc-pwd',      'input', () => Enc.onPwd());
    on('enc-eye1-btn', 'click', () => UI.toggleEye('enc-pwd','enc-eye1'));
    on('enc-pwd2',     'input', () => Enc.onPwd2());
    on('enc-eye2-btn', 'click', () => UI.toggleEye('enc-pwd2','enc-eye2'));
    on('enc-btn',      'click', () => Enc.run());
    // Generador de contraseña — cifrar archivo
    on('enc-gen-btn',  'click', () => PwdGen.apply('enc-pwd','enc-pwd2','enc-gen-strip','enc-gen-val', () => Enc.onPwd(), () => Enc.onPwd2()));
    on('enc-gen-copy', 'click', () => PwdGen.copy('enc-gen-val','enc-gen-copy'));

    // Vista: resultado
    on('res-back',        'click', () => Nav.go('home'));
    on('opt-dl',          'click', () => Res.selectOpt('dl'));
    on('opt-lnk',         'click', () => Res.selectOpt('lnk'));
    on('dl-download-btn', 'click', () => Res.download());
    on('lnk-copy-btn',    'click', () => Res.copyLink());
    on('enc-reset-btn',   'click', () => Enc.reset());

    // Vista: descifrar por enlace
    on('declink-back',     'click', () => Nav.go('home'));
    on('declink-home-btn', 'click', () => Nav.go('home'));
    on('dl-pwd',     'input', () => DecLink.onInput());
    on('dl-eye-btn', 'click', () => UI.toggleEye('dl-pwd','dl-eye'));
    on('dl-btn',     'click', () => DecLink.run());

    // Vista: descifrar archivo local
    on('decfile-back', 'click', () => Nav.go('home'));
    on('dec-pwd',      'input', () => DecFile.onInput());
    on('dec-eye-btn',  'click', () => UI.toggleEye('dec-pwd','dec-eye'));
    on('dec-btn',      'click', () => DecFile.run());

    // Vista: USB
    on('usb-back',          'click',  () => Nav.go('home'));
    on('usb-refresh-btn',   'click',  () => USB_UI.loadDevices());
    on('usb-tab-enc',       'click',  () => USB_UI.setMode('enc'));
    on('usb-tab-dec',       'click',  () => USB_UI.setMode('dec'));
    on('usb-pwd',           'input',  () => USB_UI.onPwd());
    on('usb-eye1-btn',      'click',  () => UI.toggleEye('usb-pwd','usb-eye1'));
    on('usb-pwd2',          'input',  () => USB_UI.onPwd2());
    on('usb-eye2-btn',      'click',  () => UI.toggleEye('usb-pwd2','usb-eye2'));
    on('usb-fallback-btn',  'click',  () => $('usb-fallback-input').click());
    on('usb-fallback-input','change', function() { USB_UI.runFallback(this.files); });
    on('usb-btn',           'click',  () => USB_UI.run());
    on('usb-reset-btn',     'click',  () => USB_UI.reset());
    on('luks-toggle',       'click',  () => USB_UI.toggleLuks());
    // Generador de contraseña — cifrar USB
    on('usb-gen-btn',  'click', () => PwdGen.apply('usb-pwd','usb-pwd2','usb-gen-strip','usb-gen-val', () => USB_UI.onPwd(), () => USB_UI.onPwd2()));
    on('usb-gen-copy', 'click', () => PwdGen.copy('usb-gen-val','usb-gen-copy'));

    // Vista: cifrar / descifrar carpeta — mismos eventos que la de USB,
    // pero apuntando a la instancia FLD_UI y a los IDs con prefijo fld-
    on('fld-back',          'click',  () => Nav.go('home'));
    on('fld-tab-enc',       'click',  () => FLD_UI.setMode('enc'));
    on('fld-tab-dec',       'click',  () => FLD_UI.setMode('dec'));
    on('fld-pwd',           'input',  () => FLD_UI.onPwd());
    on('fld-eye1-btn',      'click',  () => UI.toggleEye('fld-pwd','fld-eye1'));
    on('fld-pwd2',          'input',  () => FLD_UI.onPwd2());
    on('fld-eye2-btn',      'click',  () => UI.toggleEye('fld-pwd2','fld-eye2'));
    on('fld-fallback-btn',  'click',  () => $('fld-fallback-input').click());
    on('fld-fallback-input','change', function() { FLD_UI.runFallback(this.files); });
    on('fld-btn',           'click',  () => FLD_UI.run());
    on('fld-reset-btn',     'click',  () => FLD_UI.reset());
    on('fld-gen-btn',  'click', () => PwdGen.apply('fld-pwd','fld-pwd2','fld-gen-strip','fld-gen-val', () => FLD_UI.onPwd(), () => FLD_UI.onPwd2()));
    on('fld-gen-copy', 'click', () => PwdGen.copy('fld-gen-val','fld-gen-copy'));
}
