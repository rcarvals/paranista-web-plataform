<?php
// 1. Ativação de erros e controle de sessão DEVEM vir antes de qualquer HTML
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php'; // Garante acesso restrito (redireciona se não estiver logado)
require_once __DIR__ . '/../config/conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Atelier Paranista</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #0d0d0d; 
            color: #ffffff; 
            font-family: 'Montserrat', sans-serif; 
            padding: 60px 20px; 
            margin: 0;
        }
        .admin-menu { 
            max-width: 600px; 
            margin: 0 auto; 
            text-align: center; 
        }
        h1 { 
            font-size: 1.6rem; 
            letter-spacing: 4px; 
            color: #C5A059; 
            margin-bottom: 40px; 
            text-transform: uppercase; 
            font-weight: 400;
        }
        .menu-grid { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        .menu-btn { 
            background: #141414; 
            border: 1px solid rgba(255, 255, 255, 0.15); 
            color: #ffffff; 
            padding: 20px; 
            text-decoration: none; 
            font-size: 0.8rem; 
            letter-spacing: 2.5px; 
            text-transform: uppercase; 
            transition: all 0.3s ease; 
            display: block;
        }
        .menu-btn:hover { 
            border-color: #C5A059; 
            background: rgba(197, 160, 89, 0.1); 
            color: #C5A059; 
        }
        .logout-link {
            display: inline-block;
            margin-top: 30px;
            color: #888;
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-decoration: none;
            text-transform: uppercase;
            transition: color 0.3s ease;
        }
        .logout-link:hover {
            color: #f87171;
        }
    </style>
</head>
<body>

<div class="admin-menu">
    <h1>PAINEL DE CONTROLE</h1>
    <div class="menu-grid">
        <a href="produtos.php" class="menu-btn">Gerenciar Acervo &amp; Produtos (Objetos, Iluminação, Mobiliário)</a>
        <a href="publicacoes.php" class="menu-btn">Gerenciar Publicações do Blog &amp; Documentos (PDF/DOC)</a>
        <a href="capa.php" class="menu-btn">Gerenciar Capa &amp; Mídias do Hero</a>
    </div>
    
    <a href="logout.php" class="logout-link">Encerrar Sessão (Sair)</a>
</div>

</body>
</html>
