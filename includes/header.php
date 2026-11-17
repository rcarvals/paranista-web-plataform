<?php
require_once __DIR__ . '/../config/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier Paranista</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300&amp;family=Montserrat:wght@200;300;400;500;600&amp;display=swap" rel="stylesheet">

    <!-- FAVICON / ÍCONE DA PÁGINA (Aba do Navegador) -->

    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/favicon.png">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="global-header">
    <a href="/index.php" class="header-brand">
        <svg class="brand-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <line x1="50" y1="10" x2="50" y2="90" stroke="#C5A059" stroke-width="6"/>
            <polygon points="50,25 75,50 50,75 25,50" fill="none" stroke="#C5A059" stroke-width="5"/>
        </svg>
        <span class="brand-text-logo">ATELIER PARANISTA</span>
    </a>

    <nav>
        <ul class="nav-main">
            <li><a href="nos.php" class="nav-link-ws">NÓS</a></li>
            <li><a href="blog.php" class="nav-link-ws">BLOG</a></li>
            <li><a href="/#inscricao" class="nav-link-ws">CONTATO</a></li>
        </ul>
    </nav>
</header>