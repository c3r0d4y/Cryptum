    <!-- ─── Vista: Inicio ─────────────────────────────────── -->
    <div id="v-home" class="view" hidden>
        <div class="hero">
            <div class="eyebrow">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Cifrado de grado operacional
            </div>
            <h1>Crypt<span>um</span></h1>
            <p class="hero-sub">Cifra y descifra un archivo, una carpeta completa o una unidad USB con AES-256-GCM. Todo ocurre en tu navegador: ningún dato sale de tu equipo.</p>
        </div>

        <div class="vault-card">
            <div class="card-body home-actions">
                <!-- Tres funciones, tres puertas de entrada. Cada una cifra
                     Y descifra: el modo se elige con pestañas dentro. -->
                <button class="home-btn" id="btn-go-encrypt">
                    <span class="home-btn-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                    <span class="home-btn-text"><strong>Cifrar / descifrar archivo</strong><span>Un archivo suelto, protegido con contraseña y AES-256-GCM</span></span>
                    <span class="home-btn-arrow"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></span>
                </button>
                <button class="home-btn" id="btn-go-folder">
                    <span class="home-btn-icon" style="color:var(--gold);background:var(--gold-bg);border-color:var(--gold-bd)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h5l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/></svg>
                    </span>
                    <span class="home-btn-text"><strong>Cifrar / descifrar carpeta</strong><span>Todos los archivos de una carpeta, con una sola contraseña</span></span>
                    <span class="home-btn-arrow"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></span>
                </button>
                <button class="home-btn" id="btn-go-usb">
                    <span class="home-btn-icon" style="color:#7ab8d4;background:rgba(122,184,212,.08);border-color:rgba(122,184,212,.28)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/><rect x="7" y="16" width="10" height="4" rx="1"/></svg>
                    </span>
                    <span class="home-btn-text"><strong>Cifrar / descifrar USB</strong><span>Una unidad removible completa, con detección de dispositivos</span></span>
                    <span class="home-btn-arrow"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ─── Vista: Cifrar ─────────────────────────────────── -->
    <div id="v-encrypt" class="view" hidden>
        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="enc-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Volver
                </button>

                <!-- Pestañas: cifrar y descifrar archivo son la misma función
                     vista desde dos lados; cambian de vista sin volver al inicio -->
                <div class="tabs">
                    <button class="tab active" id="file-tab-enc">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Cifrar archivo
                    </button>
                    <button class="tab" id="file-tab-dec">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                        Descifrar archivo
                    </button>
                </div>

                <div class="drop-zone" id="enc-drop" role="button" tabindex="0" aria-label="Zona de carga de archivo">
                    <input type="file" id="enc-file-input" tabindex="-1" aria-hidden="true">
                    <div class="drop-icon" id="enc-drop-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="drop-title" id="enc-drop-title">Arrastra tu archivo aquí</div>
                    <div class="drop-hint"  id="enc-drop-hint">o haz clic — cualquier formato · máx 100 MB</div>
                </div>

                <div class="field">
                    <div class="field-head">
                        <label for="enc-pwd">Contraseña</label>
                        <button type="button" class="btn-gen" id="enc-gen-btn">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            Generar
                        </button>
                    </div>
                    <div class="input-row">
                        <input type="password" id="enc-pwd" placeholder="Crea una contraseña segura" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="enc-eye1-btn">
                            <svg id="enc-eye1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="strength-bar" id="enc-sbar"><div class="seg" id="sg0"></div><div class="seg" id="sg1"></div><div class="seg" id="sg2"></div><div class="seg" id="sg3"></div></div>
                    <div class="strength-txt" id="enc-stxt">–</div>
                    <div class="gen-strip" id="enc-gen-strip" style="display:none">
                        <code id="enc-gen-val"></code>
                        <button type="button" id="enc-gen-copy">Copiar</button>
                    </div>
                </div>

                <div class="field">
                    <label for="enc-pwd2">Confirmar contraseña</label>
                    <div class="input-row">
                        <input type="password" id="enc-pwd2" placeholder="Repite la contraseña" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="enc-eye2-btn">
                            <svg id="enc-eye2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="match-txt" id="enc-match"></div>
                </div>

                <div id="enc-alert" style="display:none"></div>

                <button class="btn btn-primary" id="enc-btn" disabled>
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Cifrar archivo
                </button>

                <div class="loading" id="enc-loading" style="display:none">
                    <div class="spinner"></div>
                    <div class="loading-msg" id="enc-loading-msg">Derivando clave criptográfica…<br><small style="opacity:.6">Esto tarda unos segundos por diseño</small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Vista: Resultado cifrado ──────────────────────── -->
    <div id="v-result" class="view" hidden>
        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="res-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Inicio
                </button>

                <div class="alert alert-ok">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    <span>Archivo cifrado correctamente con AES-256-GCM</span>
                </div>

                <div>
                    <div class="section-label">Archivo cifrado</div>
                    <div style="font:700 13px/1.4 ui-monospace,monospace;color:var(--text);word-break:break-all" id="res-name"></div>
                    <div style="font:500 11px/1 ui-monospace,monospace;color:var(--muted);margin-top:3px" id="res-size"></div>
                </div>

                <div class="section-label">¿Cómo quieres compartirlo?</div>
                <div class="output-opts">
                    <div class="opt-card selected" id="opt-dl">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <strong>Descarga directa</strong>
                        <small>Descarga el .c3v y compártelo por cualquier medio</small>
                    </div>
                    <div class="opt-card" id="opt-lnk">
                        <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <strong>Enlace seguro</strong>
                        <small>Genera un link de 1 descarga · expira en 5 min</small>
                    </div>
                </div>

                <div id="panel-dl">
                    <button class="btn btn-primary" id="dl-download-btn">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Descargar archivo cifrado
                    </button>
                    <div class="alert alert-warn" style="margin-top:12px">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Comparte el archivo y la contraseña por <strong>canales separados</strong> para mayor seguridad.</span>
                    </div>
                </div>

                <div id="panel-lnk" style="display:none">
                    <div class="loading" id="lnk-loading">
                        <div class="spinner"></div>
                        <div class="loading-msg">Subiendo a bóveda segura…</div>
                    </div>
                    <div id="lnk-ready" style="display:none;display:flex;flex-direction:column;gap:12px">
                        <div>
                            <div class="section-label">Enlace de descarga única</div>
                            <div class="link-box">
                                <span id="lnk-url"></span>
                                <button class="btn-copy" id="lnk-copy-btn">Copiar</button>
                            </div>
                        </div>
                        <div class="timer-ring" id="lnk-timer">
                            <div class="timer-value" id="lnk-seconds">05:00</div>
                            <div class="timer-label">Expira en</div>
                        </div>
                        <div class="share-grid" id="lnk-share-grid"></div>
                        <div class="alert alert-warn">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>El enlace permite <strong>una sola descarga</strong> y expira en <strong>5 minutos</strong>. Comparte la contraseña por un canal distinto al enlace.</span>
                        </div>
                    </div>
                    <div id="lnk-error" style="display:none"></div>
                </div>

                <button class="btn btn-ghost" id="enc-reset-btn" style="margin-top:6px">Cifrar otro archivo</button>
            </div>
        </div>
    </div>

    <!-- ─── Vista: Descifrar por enlace ───────────────────── -->
    <div id="v-decrypt-link" class="view" hidden>
        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="declink-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Inicio
                </button>

                <div class="loading" id="dl-checking">
                    <div class="spinner"></div>
                    <div class="loading-msg">Verificando enlace…</div>
                </div>

                <div id="dl-invalid" style="display:none">
                    <div class="alert alert-err">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span id="dl-invalid-msg">Enlace inválido o expirado.</span>
                    </div>
                    <button class="btn btn-secondary btn-sm" id="declink-home-btn" style="margin-top:12px">Ir al inicio</button>
                </div>

                <div id="dl-form" style="display:none;display:flex;flex-direction:column;gap:14px">
                    <div class="alert alert-info">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                        <span>Introduce la contraseña para descifrar y descargar el archivo. <strong>Esta será la única oportunidad.</strong></span>
                    </div>

                    <div class="timer-ring" id="dl-timer">
                        <div class="timer-value" id="dl-seconds">05:00</div>
                        <div class="timer-label">Tiempo restante</div>
                    </div>

                    <div class="field">
                        <label for="dl-pwd">Contraseña</label>
                        <div class="input-row">
                            <input type="password" id="dl-pwd" placeholder="Contraseña del remitente" autocomplete="current-password">
                            <button class="btn-eye" type="button" id="dl-eye-btn">
                                <svg id="dl-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div id="dl-alert" style="display:none"></div>

                    <button class="btn btn-primary" id="dl-btn" disabled>
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                        Descifrar y descargar
                    </button>

                    <div class="loading" id="dl-loading" style="display:none">
                        <div class="spinner"></div>
                        <div class="loading-msg" id="dl-loading-msg">Descargando archivo cifrado…</div>
                    </div>
                </div>

                <div id="dl-success" style="display:none">
                    <div class="alert alert-ok">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>Archivo descifrado y descargado. El enlace ha sido eliminado del servidor.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Vista: Descifrar archivo local ────────────────── -->
    <div id="v-decrypt-file" class="view" hidden>
        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="decfile-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Volver
                </button>

                <!-- Mismas pestañas que la vista de cifrado (ver arriba) -->
                <div class="tabs">
                    <button class="tab" id="fdec-tab-enc">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Cifrar archivo
                    </button>
                    <button class="tab active" id="fdec-tab-dec">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                        Descifrar archivo
                    </button>
                </div>

                <div class="drop-zone" id="dec-drop" role="button" tabindex="0" aria-label="Zona de carga de archivo cifrado">
                    <input type="file" id="dec-file-input" accept=".c3v" tabindex="-1" aria-hidden="true">
                    <div class="drop-icon" id="dec-drop-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="drop-title" id="dec-drop-title">Carga tu archivo .c3v</div>
                    <div class="drop-hint"  id="dec-drop-hint">Archivos cifrados con Cryptum</div>
                </div>

                <div class="field">
                    <label for="dec-pwd">Contraseña</label>
                    <div class="input-row">
                        <input type="password" id="dec-pwd" placeholder="Contraseña con la que fue cifrado" autocomplete="current-password">
                        <button class="btn-eye" type="button" id="dec-eye-btn">
                            <svg id="dec-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div id="dec-alert" style="display:none"></div>

                <button class="btn btn-primary" id="dec-btn" disabled>
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                    Descifrar archivo
                </button>

                <div class="loading" id="dec-loading" style="display:none">
                    <div class="spinner"></div>
                    <div class="loading-msg">Derivando clave y descifrando…</div>
                </div>

                <div id="dec-success" style="display:none">
                    <div class="alert alert-ok">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>Archivo descifrado. Toda la memoria de trabajo ha sido borrada.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Vista: Cifrado de dispositivo USB ────────────────── -->
    <div id="v-usb" class="view" hidden>

        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="usb-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Volver
                </button>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                    <div>
                        <div class="section-label">Dispositivos detectados</div>
                        <div class="section-sub">Solo USB y unidades removibles</div>
                    </div>
                    <button class="btn btn-secondary btn-sm" id="usb-refresh-btn">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        Actualizar
                    </button>
                </div>

                <div id="usb-device-list">
                    <div class="loading"><div class="spinner"></div><div class="loading-msg">Escaneando dispositivos…</div></div>
                </div>

                <div id="usb-mp-row" style="display:none">
                    <div class="section-label">Punto de acceso sugerido</div>
                    <div style="font:600 12px/1.4 ui-monospace,monospace;color:var(--accent);padding:6px 10px;border:1px solid var(--accent-bd);background:var(--accent-bg)" id="usb-mp-val">–</div>
                    <div style="font:10.5px/1.4 ui-monospace,monospace;color:var(--sub);margin-top:4px">El selector de carpetas del navegador se abrirá. Navega a esta ruta y selecciónala.</div>
                </div>

                <div class="tabs">
                    <button class="tab active" id="usb-tab-enc">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Cifrar dispositivo
                    </button>
                    <button class="tab" id="usb-tab-dec">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                        Descifrar dispositivo
                    </button>
                </div>

                <div class="field">
                    <div class="field-head">
                        <label for="usb-pwd">Contraseña del dispositivo</label>
                        <button type="button" class="btn-gen" id="usb-gen-btn">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            Generar
                        </button>
                    </div>
                    <div class="input-row">
                        <input type="password" id="usb-pwd" placeholder="Contraseña para cifrar / descifrar" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="usb-eye1-btn">
                            <svg id="usb-eye1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="strength-bar" id="usb-sbar" style="display:none"><div class="seg" id="usb-sg0"></div><div class="seg" id="usb-sg1"></div><div class="seg" id="usb-sg2"></div><div class="seg" id="usb-sg3"></div></div>
                    <div class="strength-txt" id="usb-stxt" style="display:none">–</div>
                    <div class="gen-strip" id="usb-gen-strip" style="display:none">
                        <code id="usb-gen-val"></code>
                        <button type="button" id="usb-gen-copy">Copiar</button>
                    </div>
                </div>

                <div class="field" id="usb-pwd2-field">
                    <label for="usb-pwd2">Confirmar contraseña</label>
                    <div class="input-row">
                        <input type="password" id="usb-pwd2" placeholder="Repite la contraseña" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="usb-eye2-btn">
                            <svg id="usb-eye2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="match-txt" id="usb-match"></div>
                </div>

                <div id="usb-compat-warn" class="alert alert-warn" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Tu navegador no soporta escritura directa en dispositivos USB (File System Access API). Puedes usar el <strong>modo alternativo</strong>: selecciona la carpeta, cifra los archivos y descárgalos como <code>.c3v</code>.</span>
                </div>

                <div id="usb-fallback-section" style="display:none;flex-direction:column;gap:10px">
                    <input type="file" id="usb-fallback-input" webkitdirectory multiple style="display:none">
                    <button class="btn btn-secondary" id="usb-fallback-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h4l2-3h6l2 3h4a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3"/></svg>
                        Seleccionar carpeta y cifrar (modo alternativo)
                    </button>
                    <div style="font:10.5px/1.4 ui-monospace,monospace;color:var(--sub);text-align:center">Los archivos cifrados se descargarán individualmente — los originales no se modifican</div>
                </div>

                <div id="usb-warn-enc" class="alert alert-err">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong>Operación irreversible:</strong> Cada archivo será cifrado y el original eliminado (sobrescrito con ceros). Si pierdes la contraseña, los datos son irrecuperables. Haz una copia de seguridad antes de continuar.</span>
                </div>
                <div id="usb-warn-dec" class="alert alert-info" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    <span>Se descifrarán todos los archivos <code>.c3v</code> del dispositivo usando la contraseña del vault. Los archivos cifrados serán eliminados una vez restaurados.</span>
                </div>

                <div id="usb-alert" style="display:none"></div>

                <button class="btn btn-primary" id="usb-btn" disabled>
                    <svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/></svg>
                    <span id="usb-btn-label">Seleccionar directorio USB y cifrar</span>
                </button>
                <!-- Explica por qué el botón está deshabilitado (lo muestra
                     y oculta validate() según haya unidades detectadas) -->
                <div id="usb-need-device" class="usb-need-device">
                    Conecta una unidad USB y pulsa «Actualizar» para habilitar el cifrado.
                </div>
                <div style="font:10.5px/1.4 ui-monospace,monospace;color:var(--sub);text-align:center">El navegador pedirá confirmar el acceso al directorio seleccionado</div>

                <div id="usb-loading" style="display:none">
                    <div class="loading" style="padding:4px 0 10px">
                        <div class="spinner"></div>
                        <div class="loading-msg" id="usb-loading-msg">Derivando clave maestra…</div>
                    </div>
                    <div class="prog-wrap">
                        <div class="prog-track"><div class="prog-fill" id="usb-prog-fill"></div></div>
                        <div class="prog-stats">
                            <span class="prog-pct" id="usb-prog-pct">0%</span>
                            <span class="prog-count" id="usb-prog-count">0 / 0 archivos</span>
                        </div>
                        <div class="prog-file" id="usb-prog-file">Iniciando…</div>
                    </div>
                </div>

                <div id="usb-result" style="display:none">
                    <div class="alert alert-ok" id="usb-result-msg">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span id="usb-result-txt">Operación completada.</span>
                    </div>
                    <button class="btn btn-ghost" id="usb-reset-btn" style="margin-top:8px">Nueva operación</button>
                </div>
            </div>
        </div>

        <!-- Sin ancho fijo: el .sec-notice interior ya usa los mismos
             anchos responsivos que las tarjetas (520/640/760px) -->
        <div style="width:100%;margin:12px 0">
            <div class="sec-notice" style="border-color:rgba(74,130,190,.28)">
                <button class="sec-toggle" id="luks-toggle" style="color:#7ab8d4">
                    <span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:7px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Modo avanzado — Cifrado LUKS de disco completo (terminal)
                    </span>
                    <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="sec-body" id="luks-body" style="color:#7ab8d4">
                    <p><strong>¿Qué es LUKS?</strong><br>
                    Linux Unified Key Setup (LUKS) cifra el dispositivo a nivel de bloque — todo el disco, incluyendo metadatos del sistema de archivos. Es el estándar de cifrado de disco en Linux (usado por GNOME Disks, VeraCrypt, etc.).</p>
                    <p><strong>Requisitos:</strong> <code>cryptsetup</code> instalado · acceso root/sudo · dispositivo desmontado.</p>
                    <p><strong>1. Formatear (BORRA TODOS LOS DATOS):</strong></p>
                    <code style="display:block;padding:8px;background:rgba(0,0,0,.4);font-size:11px;word-break:break-all;margin-bottom:8px">sudo cryptsetup luksFormat --batch-mode --hash=sha512 --key-size=512 --iter-time=5000 /dev/sdX</code>
                    <p><strong>2. Abrir vault:</strong></p>
                    <code style="display:block;padding:8px;background:rgba(0,0,0,.4);font-size:11px;word-break:break-all;margin-bottom:8px">sudo cryptsetup luksOpen /dev/sdX cryptum_usb<br>sudo mkfs.ext4 /dev/mapper/cryptum_usb<br>sudo mount /dev/mapper/cryptum_usb /mnt/usb</code>
                    <p><strong>3. Cerrar vault:</strong></p>
                    <code style="display:block;padding:8px;background:rgba(0,0,0,.4);font-size:11px;word-break:break-all;margin-bottom:8px">sudo umount /mnt/usb<br>sudo cryptsetup luksClose cryptum_usb</code>
                    <p style="color:var(--sub)">El comando LUKS usa AES-512 con PBKDF2-SHA512 y 5000ms de tiempo de iteración para la derivación de clave — equivalente a ~8M iteraciones. Reemplaza <code>/dev/sdX</code> con el dispositivo real (verifica con <code>lsblk</code>).</p>
                </div>
            </div>
        </div>

    </div>
    <!-- ─ fin v-usb ───────────────────────────────────────── -->

    <!-- ─── Vista: Cifrar / descifrar carpeta ─────────────── -->
    <div id="v-folder" class="view" hidden>

        <div class="vault-card">
            <div class="card-body">
                <button class="back-btn" id="fld-back">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    Volver
                </button>

                <div class="tabs">
                    <button class="tab active" id="fld-tab-enc">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Cifrar carpeta
                    </button>
                    <button class="tab" id="fld-tab-dec">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:5px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/><path d="M17 11V7"/></svg>
                        Descifrar carpeta
                    </button>
                </div>

                <div class="field">
                    <div class="field-head">
                        <label for="fld-pwd">Contraseña de la carpeta</label>
                        <button type="button" class="btn-gen" id="fld-gen-btn">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                            Generar
                        </button>
                    </div>
                    <div class="input-row">
                        <input type="password" id="fld-pwd" placeholder="Contraseña para cifrar / descifrar" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="fld-eye1-btn">
                            <svg id="fld-eye1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="strength-bar" id="fld-sbar" style="display:none"><div class="seg" id="fld-sg0"></div><div class="seg" id="fld-sg1"></div><div class="seg" id="fld-sg2"></div><div class="seg" id="fld-sg3"></div></div>
                    <div class="strength-txt" id="fld-stxt" style="display:none">–</div>
                    <div class="gen-strip" id="fld-gen-strip" style="display:none">
                        <code id="fld-gen-val"></code>
                        <button type="button" id="fld-gen-copy">Copiar</button>
                    </div>
                </div>

                <div class="field" id="fld-pwd2-field">
                    <label for="fld-pwd2">Confirmar contraseña</label>
                    <div class="input-row">
                        <input type="password" id="fld-pwd2" placeholder="Repite la contraseña" autocomplete="new-password">
                        <button class="btn-eye" type="button" id="fld-eye2-btn">
                            <svg id="fld-eye2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="match-txt" id="fld-match"></div>
                </div>

                <div id="fld-compat-warn" class="alert alert-warn" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Tu navegador no soporta escritura directa en carpetas (File System Access API). Puedes usar el <strong>modo alternativo</strong>: selecciona la carpeta, cifra los archivos y descárgalos como <code>.c3v</code>.</span>
                </div>

                <div id="fld-fallback-section" style="display:none;flex-direction:column;gap:10px">
                    <input type="file" id="fld-fallback-input" webkitdirectory multiple style="display:none">
                    <button class="btn btn-secondary" id="fld-fallback-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h4l2-3h6l2 3h4a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3"/></svg>
                        Seleccionar carpeta y cifrar (modo alternativo)
                    </button>
                    <div style="font:10.5px/1.4 ui-monospace,monospace;color:var(--sub);text-align:center">Los archivos cifrados se descargarán individualmente — los originales no se modifican</div>
                </div>

                <div id="fld-warn-enc" class="alert alert-err">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong>Operación irreversible:</strong> Cada archivo será cifrado y el original eliminado (sobrescrito con ceros). Si pierdes la contraseña, los datos son irrecuperables. Haz una copia de seguridad antes de continuar.</span>
                </div>
                <div id="fld-warn-dec" class="alert alert-info" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    <span>Se descifrarán todos los archivos <code>.c3v</code> de la carpeta usando la contraseña del vault. Los archivos cifrados serán eliminados una vez restaurados.</span>
                </div>

                <div id="fld-alert" style="display:none"></div>

                <button class="btn btn-primary" id="fld-btn" disabled>
                    <svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/></svg>
                    <span id="fld-btn-label">Seleccionar carpeta y cifrar</span>
                </button>
                <div style="font:10.5px/1.4 ui-monospace,monospace;color:var(--sub);text-align:center">El navegador pedirá confirmar el acceso a la carpeta seleccionada</div>

                <div id="fld-loading" style="display:none">
                    <div class="loading" style="padding:4px 0 10px">
                        <div class="spinner"></div>
                        <div class="loading-msg" id="fld-loading-msg">Derivando clave maestra…</div>
                    </div>
                    <div class="prog-wrap">
                        <div class="prog-track"><div class="prog-fill" id="fld-prog-fill"></div></div>
                        <div class="prog-stats">
                            <span class="prog-pct" id="fld-prog-pct">0%</span>
                            <span class="prog-count" id="fld-prog-count">0 / 0 archivos</span>
                        </div>
                        <div class="prog-file" id="fld-prog-file">Iniciando…</div>
                    </div>
                </div>

                <div id="fld-result" style="display:none">
                    <div class="alert alert-ok" id="fld-result-msg">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <span id="fld-result-txt">Operación completada.</span>
                    </div>
                    <button class="btn btn-ghost" id="fld-reset-btn" style="margin-top:8px">Nueva operación</button>
                </div>
            </div>
        </div>

        </div>
    <!-- ─ fin v-folder ────────────────────────────────────── -->
