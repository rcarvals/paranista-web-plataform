<?php
require_once __DIR__ . '/auth.php'; // Garante acesso restrito
require_once __DIR__ . '/../config/conexao.php'; // Inclusão única da conexão

$mensagem = '';
$diretorio_uploads = __DIR__ . '/../uploads/';

// Cria a pasta uploads/ na raiz se não existir
if (!is_dir($diretorio_uploads)) {
    mkdir($diretorio_uploads, 0755, true);
}

// Função para formatar o caminho relativo para a raiz do domínio (HostGator)
function formatar_caminho_upload($nome_arquivo) {
    return 'uploads/' . ltrim($nome_arquivo, '/');
}

// Identifica qual seção estamos editando ('home' por padrão ou 'iluminacao', 'mobiliario', 'objetos')
$secao_ativa = $_GET['secao'] ?? 'home';

// PROCESSA O ENVIO DO FORMULÁRIO DE GESTÃO DA CAPA / CATEGORIA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_hero = $_POST['tipo_hero'] ?? 'video';
    $secao_post = $_POST['secao_alvo'] ?? 'home';

    if ($secao_post === 'home') {
        // =========================================================================
        // LÓGICA DA HOMEPAGE
        // =========================================================================
        
        // 1. Atualiza o tipo de mídia ativo na tabela 'configuracoes'
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('tipo_hero', :v1) ON DUPLICATE KEY UPDATE valor = :v2");
        $stmt->execute(['v1' => $tipo_hero, 'v2' => $tipo_hero]);

        // 2. Processa vídeo da Homepage (Upload local OU URL externa)
        if (!empty($_FILES['video_file']['name'])) {
            $extensao = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['mp4', 'webm', 'ogg'];

            if (in_array($extensao, $extensoes_permitidas)) {
                $novo_nome_video = 'hero_video_' . time() . '.' . $extensao;
                $caminho_destino = $diretorio_uploads . $novo_nome_video;

                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $caminho_destino)) {
                    $url_video_db = formatar_caminho_upload($novo_nome_video);
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('video_hero_url', :v1) ON DUPLICATE KEY UPDATE valor = :v2");
                    $stmt->execute(['v1' => $url_video_db, 'v2' => $url_video_db]);
                }
            }
        } elseif (!empty($_POST['video_hero_url'])) {
            $url_input = trim($_POST['video_hero_url']);
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('video_hero_url', :v1) ON DUPLICATE KEY UPDATE valor = :v2");
            $stmt->execute(['v1' => $url_input, 'v2' => $url_input]);
        }

        // 3. Processa imagens do carrossel da Homepage
        if (isset($_FILES['carrossel_files'])) {
            $has_new_files = false;
            foreach ($_FILES['carrossel_files']['name'] as $name) {
                if (!empty($name)) {
                    $has_new_files = true;
                    break;
                }
            }

            if ($has_new_files) {
                $pdo->query("DELETE FROM hero_carrossel");
                $stmt_insert = $pdo->prepare("INSERT INTO hero_carrossel (imagem_url, ordem) VALUES (:url, :ordem)");

                foreach ($_FILES['carrossel_files']['name'] as $index => $nome_original) {
                    if (!empty($nome_original)) {
                        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'webp'];

                        if (in_array($extensao, $extensoes_permitidas)) {
                            $novo_nome_img = 'carrossel_' . ($index + 1) . '_' . time() . '.' . $extensao;
                            $caminho_destino = $diretorio_uploads . $novo_nome_img;

                            if (move_uploaded_file($_FILES['carrossel_files']['tmp_name'][$index], $caminho_destino)) {
                                $url_img_db = formatar_caminho_upload($novo_nome_img);
                                $stmt_insert->execute(['url' => $url_img_db, 'ordem' => $index + 1]);
                            }
                        }
                    }
                }
            }
        }

    } else {
        // =========================================================================
        // LÓGICA DAS CATEGORIAS (ILUMINAÇÃO, MOBILIÁRIO, OBJETOS)
        // =========================================================================
        
        $stmt_exist = $pdo->prepare("SELECT video_url FROM categorias_midia WHERE categoria = :cat LIMIT 1");
        $stmt_exist->execute(['cat' => $secao_post]);
        $url_video_cat = $stmt_exist->fetchColumn() ?: '';

        // Upload de novo vídeo da categoria
        if (!empty($_FILES['video_file']['name'])) {
            $extensao = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (in_array($extensao, ['mp4', 'webm', 'ogg'])) {
                $novo_nome_video = 'cat_video_' . $secao_post . '_' . time() . '.' . $extensao;
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $diretorio_uploads . $novo_nome_video)) {
                    $url_video_cat = formatar_caminho_upload($novo_nome_video);
                }
            }
        } elseif (!empty($_POST['video_hero_url'])) {
            $url_video_cat = trim($_POST['video_hero_url']);
        }

        // Grava tipo de hero e URL de vídeo na tabela categorias_midia
        $stmt = $pdo->prepare("INSERT INTO categorias_midia (categoria, tipo_hero, video_url) 
                               VALUES (:c1, :t1, :u1) 
                               ON DUPLICATE KEY UPDATE 
                                   tipo_hero = :t2, 
                                   video_url = :u2");
        $stmt->execute([
            'c1' => $secao_post,
            't1' => $tipo_hero,
            'u1' => $url_video_cat,
            't2' => $tipo_hero,
            'u2' => $url_video_cat
        ]);

        // Upload das fotos do carrossel da Categoria
        if (isset($_FILES['carrossel_files'])) {
            $has_new_files = false;
            foreach ($_FILES['carrossel_files']['name'] as $name) {
                if (!empty($name)) { 
                    $has_new_files = true; 
                    break; 
                }
            }

            if ($has_new_files) {
                $stmt_del = $pdo->prepare("DELETE FROM categorias_carrossel WHERE categoria = :cat");
                $stmt_del->execute(['cat' => $secao_post]);

                $stmt_insert = $pdo->prepare("INSERT INTO categorias_carrossel (categoria, imagem_url, ordem) VALUES (:cat, :url, :ordem)");

                foreach ($_FILES['carrossel_files']['name'] as $index => $nome_original) {
                    if (!empty($nome_original)) {
                        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                        if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $novo_nome_img = 'cat_' . $secao_post . '_' . ($index + 1) . '_' . time() . '.' . $extensao;
                            if (move_uploaded_file($_FILES['carrossel_files']['tmp_name'][$index], $diretorio_uploads . $novo_nome_img)) {
                                $url_img_db = formatar_caminho_upload($novo_nome_img);
                                $stmt_insert->execute(['cat' => $secao_post, 'url' => $url_img_db, 'ordem' => $index + 1]);
                            }
                        }
                    }
                }
            }
        }
    }

    $mensagem = 'Configurações de mídia salvas com sucesso na base de dados!';
}

// BUSCA DADOS DEPENDENDO DA SEÇÃO SELECIONADA PARA PREENCHER O FORMULÁRIO
if ($secao_ativa === 'home') {
    $config_raw = $pdo->query("SELECT * FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tipo_hero_atual = $config_raw['tipo_hero'] ?? 'video';
    $video_url_atual = $config_raw['video_hero_url'] ?? '';
    $imagens_carrossel = $pdo->query("SELECT * FROM hero_carrossel ORDER BY ordem ASC")->fetchAll();
} else {
    $stmt_cat = $pdo->prepare("SELECT * FROM categorias_midia WHERE categoria = :cat LIMIT 1");
    $stmt_cat->execute(['cat' => $secao_ativa]);
    $config_cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);

    $tipo_hero_atual = $config_cat['tipo_hero'] ?? 'video';
    $video_url_atual = $config_cat['video_url'] ?? '';

    $stmt_car = $pdo->prepare("SELECT * FROM categorias_carrossel WHERE categoria = :cat ORDER BY ordem ASC");
    $stmt_car->execute(['cat' => $secao_ativa]);
    $imagens_carrossel = $stmt_car->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin | Gestão de Mídia de Capa &amp; Categorias</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background-color: #0a0a0a; color: #fff; font-family: 'Montserrat', sans-serif; margin: 0; padding: 20px; }
        .admin-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 40px;
            background: #111;
            border: 1px solid #222;
            color: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .admin-title {
            font-family: var(--font-serif, serif);
            font-size: 2rem;
            margin-bottom: 25px;
            color: #C5A059;
            font-weight: 300;
        }
        .tabs-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid #222;
            padding-bottom: 15px;
        }
        .tab-btn {
            background: #1a1a1a;
            color: #888;
            padding: 10px 20px;
            text-decoration: none;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            border: 1px solid #333;
            transition: all 0.3s;
        }
        .tab-btn:hover, .tab-btn.active {
            background: #C5A059;
            color: #000;
            border-color: #C5A059;
            font-weight: 600;
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #222;
        }
        .form-section label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #aaa;
        }
        .form-section input[type="text"], 
        .form-section input[type="file"], 
        .form-section select {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            outline: none;
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        .file-hint {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 15px;
            display: block;
        }
        .btn-salvar {
            background: #C5A059;
            color: #000;
            padding: 14px 30px;
            border: none;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-salvar:hover {
            background: #fff;
        }
        .alert-success {
            padding: 12px;
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #4ade80;
            margin-bottom: 20px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .back-link {
            color: #888;
            text-decoration: none;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .back-link:hover {
            color: #C5A059;
        }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header-actions">
        <a href="index.php" class="back-link">&larr; Voltar ao Painel</a>
        <a href="logout.php" class="back-link" style="color: #f87171;">Sair</a>
    </div>

    <h1 class="admin-title">Gerenciador de Mídia de Capa</h1>

    <!-- TABS DE NAVEGAÇÃO DA MÍDIA DA HOME E DAS CATEGORIAS -->
    <div class="tabs-nav">
        <a href="capa.php?secao=home" class="tab-btn <?= $secao_ativa === 'home' ? 'active' : '' ?>">HOMEPAGE</a>
        <a href="capa.php?secao=iluminacao" class="tab-btn <?= $secao_ativa === 'iluminacao' ? 'active' : '' ?>">ILUMINAÇÃO</a>
        <a href="capa.php?secao=mobiliario" class="tab-btn <?= $secao_ativa === 'mobiliario' ? 'active' : '' ?>">MOBILIÁRIO</a>
        <a href="capa.php?secao=objetos" class="tab-btn <?= $secao_ativa === 'objetos' ? 'active' : '' ?>">OBJETOS</a>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="secao_alvo" value="<?= htmlspecialchars($secao_ativa) ?>">

        <!-- 1. SELEÇÃO DA MÍDIA ATIVA -->
        <div class="form-section">
            <label for="tipo_hero">1. SELECIONE A MÍDIA ATIVA EM (<?= strtoupper($secao_ativa) ?>):</label>
            <select name="tipo_hero" id="tipo_hero">
                <option value="video" <?= $tipo_hero_atual === 'video' ? 'selected' : '' ?>>Vídeo em Loop (Atmosférico)</option>
                <option value="carrossel" <?= $tipo_hero_atual === 'carrossel' ? 'selected' : '' ?>>Carrossel de Imagens (Fotos)</option>
            </select>
        </div>

        <!-- 2. UPLOAD DO VÍDEO -->
        <div class="form-section">
            <label for="video_file">2. UPLOAD DO VÍDEO (MP4 / WEBM):</label>
            <input type="file" name="video_file" id="video_file" accept="video/mp4,video/webm">
            
            <label for="video_hero_url" style="margin-top: 15px;">OU INSIRA A URL DO VÍDEO (OPCIONAL):</label>
            <input type="text" name="video_hero_url" id="video_hero_url" value="<?= htmlspecialchars($video_url_atual) ?>" placeholder="uploads/hero_video.mp4">
            
            <?php if (!empty($video_url_atual)): ?>
                <span class="file-hint">Vídeo atual salvo: <?= htmlspecialchars($video_url_atual) ?></span>
            <?php endif; ?>
        </div>

        <!-- 3. UPLOAD DAS IMAGENS DO CARROSSEL -->
        <div class="form-section">
            <label>3. SELECIONE FOTOS DO SEU COMPUTADOR PARA O CARROSSEL:</label>
            <?php for ($i = 0; $i < 5; $i++): 
                $img_atual = $imagens_carrossel[$i]['imagem_url'] ?? '';
                $src_preview = !empty($img_atual) ? '../' . ltrim(str_replace('/meu-atelie/', '', $img_atual), '/') : '';
            ?>
                <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <span style="font-size: 0.7rem; color: #888;">FOTO <?= $i + 1 ?>:</span>
                        <input type="file" name="carrossel_files[]" accept="image/*">
                    </div>
                    <?php if (!empty($src_preview)): ?>
                        <div style="text-align: center;">
                            <img src="<?= htmlspecialchars($src_preview) ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #333;">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn-salvar">Salvar na Base de Dados</button>
        <a href="<?= $secao_ativa === 'home' ? '../index.php' : '../categoria.php?cat=' . $secao_ativa ?>" target="_blank" style="color: #888; margin-left: 20px; text-decoration: none; font-size: 0.85rem;">Visualizar no Site &rarr;</a>

    </form>
</div>

</body>
</html>