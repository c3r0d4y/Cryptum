<?php
/*
 * Vista: layouts/header.php
 * Autor: C3r0d4y
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <!-- Sin maximum-scale: bloquear el zoom perjudica la accesibilidad en móvil -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#06090f">
    <title>Cryptum</title>
    <!-- ?v= fuerza la recarga del asset cuando cambia la versión de la app -->
    <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body data-base="<?= BASE_URL ?>" data-token="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">

<!-- ── Topbar ─────────────────────────────────────────────────────────── -->
<div class="topbar">
    <a class="topbar-brand" href="<?= BASE_URL ?>/">C3r0d4y</a>
    <div class="topbar-right">
        <button class="diag-link" id="diag-open-btn">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M17.5 14v6M14.5 17h6"/></svg>
            <span>Diagrama de cifrado</span>
        </button>
        <!-- Acceso a la aplicación portátil: el personal que se despliega la
             descarga desde aquí antes de salir, para poder abrir su material
             en destino sin conexión al servidor. -->
        <button class="diag-link portable-link" id="portable-open-btn">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>App portátil</span>
        </button>
        <span class="pill blue"><span class="dot"></span>CIFRADO LOCAL</span>
        <span class="topbar-ver">CRYPTUM v1.0</span>
    </div>
</div>

<!-- ── Contenido principal ───────────────────────────────────────────── -->
<!-- tabindex="-1" permite mover el foco aquí al cambiar de vista (lectores de pantalla) -->
<main id="app-main" tabindex="-1">
