<?php
/*
 * Vista: layouts/footer.php
 * Autor: C3r0d4y
 */
?>
</main>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DIAGRAMA DE CIFRADO
     ══════════════════════════════════════════════════════════ -->
<div class="diag-overlay" id="diag-overlay" role="dialog" aria-modal="true" aria-label="Diagrama de cifrado">
    <div class="diag-modal">

        <button class="diag-close" id="diag-close-btn" aria-label="Cerrar">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="diag-title">¿Cómo protegemos tu información?</div>
        <div class="diag-sub">Todo el proceso ocurre dentro de tu propio dispositivo. Ninguna contraseña sale de tu pantalla.</div>

        <div class="diag-flow">

            <div class="fc-box">
                <div class="fc-icon">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                    <div class="fc-label">Seleccionas tu archivo</div>
                    <div class="fc-desc">Foto, documento, video — cualquier formato. Nunca sale de tu dispositivo.</div>
                </div>
            </div>
            <div class="fc-arrow"></div>

            <div class="fc-box gold">
                <div class="fc-icon gold">
                    <svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                </div>
                <div>
                    <div class="fc-label">Ingresas una contraseña</div>
                    <div class="fc-desc">Solo tú la conoces. Nunca se guarda ni se envía al servidor.</div>
                </div>
            </div>
            <div class="fc-arrow gold"></div>

            <div class="fc-box">
                <div class="fc-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07M8.46 8.46a5 5 0 0 0 0 7.07"/></svg>
                </div>
                <div>
                    <div class="fc-label">PBKDF2-SHA-512 · 210 000 iteraciones</div>
                    <div class="fc-desc">La contraseña se transforma en una llave de 256 bits. Resistente a fuerza bruta.</div>
                </div>
            </div>
            <div class="fc-arrow"></div>

            <div class="fc-box">
                <div class="fc-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <div class="fc-label">AES-256-GCM — en tu navegador</div>
                    <div class="fc-desc">El archivo se cifra con sal aleatoria e IV único. Ningún dato sale del dispositivo.</div>
                </div>
            </div>
            <div class="fc-arrow ok"></div>

            <div class="fc-box ok">
                <div class="fc-icon ok">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div>
                    <div class="fc-label">Archivo cifrado <code style="color:var(--ok);font:inherit">.c3v</code> listo</div>
                    <div class="fc-desc">Solo quien tenga la contraseña exacta podrá abrirlo.</div>
                </div>
            </div>
            <div class="fc-arrow gold"></div>

            <div class="fc-diamond-wrap">
                <div class="fc-diamond">
                    <div class="fc-diamond-text">¿Compartir<br>por enlace?</div>
                </div>
                <div class="fc-branch-labels">
                    <span class="fc-branch-label">No → descarga</span>
                    <span class="fc-branch-label">Sí → enlace</span>
                </div>
            </div>

            <div class="fc-split">
                <div class="fc-split-col">
                    <div class="fc-split-line-h" style="flex:none;width:50%"></div>
                    <div class="fc-split-line-v"></div>
                    <div class="fc-split-label">Descarga directa</div>
                    <div class="fc-mini-box">Guardas o compartes el .c3v por cualquier medio</div>
                </div>
                <div class="fc-split-col">
                    <div class="fc-split-line-h" style="flex:none;width:50%"></div>
                    <div class="fc-split-line-v"></div>
                    <div class="fc-split-label">Enlace seguro</div>
                    <div class="fc-mini-box">El blob cifrado sube al servidor · 1 descarga · expira en 5 min</div>
                </div>
            </div>

        </div>

        <div class="diag-divider"><span>Modo dispositivo USB</span></div>
        <div class="diag-usb-note">
            <div class="diag-usb-icon">
                <svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/><rect x="7" y="16" width="10" height="4" rx="1"/></svg>
            </div>
            <div>
                <div class="diag-usb-title">Cifra todos los archivos de una USB</div>
                <div class="diag-usb-desc">El proceso es idéntico, con una diferencia: se genera una sola llave para todo el dispositivo. Cada archivo se cifra uno a uno y el original se elimina tras verificar que su copia cifrada quedó escrita. La sobreescritura con ceros es un mejor esfuerzo: en memorias USB y SSD el controlador puede conservar copias internas, por lo que no debe considerarse un borrado forense garantizado.</div>
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: APLICACIÓN PORTÁTIL (descarga + diagrama de uso)
     Pensado para el personal que se despliega y no tendrá acceso
     al servidor en su destino.
     ══════════════════════════════════════════════════════════ -->
<div class="diag-overlay" id="portable-overlay" role="dialog" aria-modal="true" aria-label="Aplicación portátil Cryptum">
    <div class="diag-modal">

        <button class="diag-close" id="portable-close-btn" aria-label="Cerrar">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="diag-title">Cryptum Portable — para el despliegue</div>
        <div class="diag-sub">Cifra aquí antes de salir. Descifra allá sin internet, sin servidor y sin instalar nada. Un solo archivo: no necesita Python.</div>

        <!-- ── Diagrama de uso: los tres momentos de la operación ── -->
        <div class="port-flow">

            <div class="port-step">
                <div class="port-step-num">1</div>
                <div class="port-step-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </div>
                <div class="port-step-title">En la base</div>
                <div class="port-step-place">Aplicación web Cryptum</div>
                <div class="port-step-desc">Cifras tu archivo o tu USB completa con tu contraseña. El material queda ilegible.</div>
            </div>

            <div class="port-arrow" aria-hidden="true"><span></span></div>

            <div class="port-step gold">
                <div class="port-step-num gold">2</div>
                <div class="port-step-icon gold">
                    <svg viewBox="0 0 24 24"><path d="M10 2h4v8h4l-6 6-6-6h4z"/><path d="M3 20h18"/><rect x="7" y="16" width="10" height="4" rx="1"/></svg>
                </div>
                <div class="port-step-title">En el traslado</div>
                <div class="port-step-place">Memoria USB</div>
                <div class="port-step-desc">Solo viajan archivos <code>.c3v</code>. La contraseña no va en la USB: la llevas tú.</div>
            </div>

            <div class="port-arrow" aria-hidden="true"><span></span></div>

            <div class="port-step ok">
                <div class="port-step-num ok">3</div>
                <div class="port-step-icon ok">
                    <svg viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 9.4 8 10 4.6-.6 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="port-step-title">En el destino</div>
                <div class="port-step-place">Cryptum Portable</div>
                <div class="port-step-desc">Abres el programa en Linux o Windows, escribes tu contraseña y recuperas todo.</div>
            </div>

        </div>

        <div class="port-warn">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>Si la USB se pierde o es capturada, el contenido es indescifrable sin tu contraseña. Si olvidas la contraseña, nadie —ni el servidor— puede recuperar el material.</span>
        </div>

        <div class="diag-divider"><span>Descarga</span></div>

        <!--
             Lista de descargas. Cada renglón sale del modelo Descargas:
             si el archivo ya está copiado en el servidor, el enlace apunta
             aquí mismo (sirve sin internet); si todavía no, apunta al
             Release oficial de GitHub para que el botón nunca quede muerto.
        -->
        <div class="port-dl-list">
<?php foreach (($descargas ?? []) as $d): ?>
            <a class="port-download<?= $d['destaca'] ? '' : ' alt' ?>"
               href="<?= htmlspecialchars($d['url'], ENT_QUOTES) ?>"
               <?= $d['local'] ? 'download' : 'rel="noopener noreferrer"' ?>>
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>
                    <strong><?= htmlspecialchars($d['titulo']) ?></strong>
                    <em><?= htmlspecialchars($d['detalle']) ?></em>
                </span>
                <span class="port-dl-meta">
                    <span class="port-dl-size"><?= htmlspecialchars($d['peso']) ?></span>
<?php if (!$d['local']): ?>
                    <span class="port-dl-src">GitHub</span>
<?php endif; ?>
                </span>
            </a>
<?php endforeach; ?>
        </div>

        <div class="port-hash">
            Verifica la descarga antes de usarla. En Linux
            <code>sha256sum cryptum</code>, en Windows
            <code>certutil -hashfile cryptum.exe SHA256</code>
            — compara el resultado con el archivo <code>.sha256</code> que se publica
            junto a cada descarga. Si no coincide, no lo ejecutes.
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     AVISO DE SEGURIDAD
     ══════════════════════════════════════════════════════════ -->
<div class="sec-notice-wrap">
    <div class="sec-notice" id="sec-notice">
        <button class="sec-toggle" id="sec-toggle">
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;vertical-align:middle;margin-right:7px"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Aviso de seguridad — Léelo antes de usar
            </span>
            <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="sec-body" id="sec-body">
            <p><strong>¿Cómo funciona el cifrado?</strong><br>
            Cryptum utiliza <strong>AES-256-GCM</strong> con claves de 256 bits. La clave se deriva de tu contraseña mediante <strong>PBKDF2-SHA-512 con 210,000 iteraciones</strong>, un proceso intencionalmente lento para resistir ataques de fuerza bruta.</p>
            <p><strong>¿Qué almacena el servidor?</strong><br>
            Para la función de enlace seguro, el servidor almacena <em>únicamente el blob ya cifrado</em> — nunca la contraseña, nunca el archivo original. El servidor no puede leer el contenido. Los archivos se eliminan tras la primera descarga o a los 5 minutos.</p>
            <p><strong>Riesgos que debes conocer:</strong><br>
            · Si pierdes la contraseña, el archivo es <strong>matemáticamente irrecuperable</strong>.<br>
            · La seguridad depende de la fortaleza de tu contraseña. Usa al menos 16 caracteres con símbolos.<br>
            · En dispositivos con malware, la contraseña podría ser capturada antes del cifrado.</p>
            <p><strong>Uso autorizado:</strong><br>
            Herramienta destinada a contextos de seguridad nacional y protección de información sensible. Su uso para actividades ilícitas está prohibido. El usuario asume plena responsabilidad del uso y custodia de las contraseñas.</p>
            <p style="color:var(--sub)">Implementación: Web Crypto API · AES-256-GCM · PBKDF2-SHA-512 · Sin dependencias externas · Código ejecutado localmente en el navegador.</p>
        </div>
    </div>
</div>

<!-- ── Footer ──────────────────────────────────────────────────────────── -->
<footer>
    <div class="badge-row">
        <span class="fbadge a">AES-256-GCM</span>
        <span class="fbadge a">PBKDF2-SHA-512</span>
        <span class="fbadge">Sin almacenamiento</span>
        <span class="fbadge">Sin cookies</span>
        <span class="fbadge">Cifrado local</span>
        <span class="fbadge" style="color:var(--gold);border-color:var(--gold-bd)">C3r0d4y</span>
    </div>
</footer>

<!-- ?v= fuerza la recarga del asset cuando cambia la versión de la app -->
<script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
