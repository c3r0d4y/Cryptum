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
        <span class="pill blue"><span class="dot"></span>CIFRADO LOCAL</span>
        <span class="topbar-ver">CRYPTUM v1.0</span>
    </div>
</div>

<!-- ── Contenido principal ───────────────────────────────────────────── -->
<!-- tabindex="-1" permite mover el foco aquí al cambiar de vista (lectores de pantalla) -->
<main id="app-main" tabindex="-1">
