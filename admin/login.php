<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';

// Se já estiver autenticado, redireciona para o painel principal ou lista de produtos
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: produtos.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha'] ?? '');

    if (!empty($usuario) && !empty($senha)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE usuario = :usuario LIMIT 1");
            $stmt->execute(['usuario' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica senha encriptada OU fallback para senha em texto simples
            if ($user && (password_verify($senha, $user['senha']) || $senha === $user['senha'])) {
                
                // Se a senha no banco ainda estava em texto puro, atualiza automaticamente para hash seguro
                if ($senha === $user['senha']) {
                    $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt_up = $pdo->prepare("UPDATE usuarios_admin SET senha = :hash WHERE id = :id");
                    $stmt_up->execute(['hash' => $novo_hash, 'id' => $user['id']]);
                }

                // Proteção de sessão contra Session Fixation
                session_regenerate_id(true);

                $_SESSION['admin_logado']  = true;
                $_SESSION['admin_usuario'] = $user['usuario'];
                $_SESSION['admin_nome']    = $user['nome'] ?? $user['usuario'];

                header('Location: produtos.php');
                exit;
            } else {
                $erro = 'Utilizador ou palavra-passe incorretos.';
            }
        } catch (\PDOException $e) {
            $erro = 'Erro na base de dados. Verifique a tabela usuarios_admin.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier Paranista - Login Admin</title>
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background-color: #080808;
            color: #ffffff;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-box {
            background: #0D0D0D;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            text-align: center;
        }
        .login-box h1 {
            font-size: 1.1rem;
            letter-spacing: 3px;
            color: #C5A059;
            margin-bottom: 25px;
            text-transform: uppercase;
            font-weight: 400;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 0.65rem;
            letter-spacing: 2px;
            color: #888888;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .form-group input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 8px 0;
            font-size: 0.9rem;
            outline: none;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-bottom-color: #C5A059;
        }
        .btn-login {
            background: #ffffff;
            color: #080808;
            border: none;
            width: 100%;
            padding: 12px;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #C5A059;
            color: #ffffff;
        }
        .error-msg {
            color: #f87171;
            font-size: 0.75rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>ATELIER PARANISTA</h1>
        
        <?php if (!empty($erro)): ?>
            <div class="error-msg"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="usuario">UTILIZADOR</label>
                <input type="text" id="usuario" name="usuario" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="senha">PALAVRA-PASSE</label>
                <input type="password" id="senha" name="senha" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">ENTRAR</button>
        </form>
    </div>
</body>
</html>