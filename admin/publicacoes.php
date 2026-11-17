<?php
// 1. Ativação de depuração de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/conexao.php';

$mensagem = '';
$tipo_mensagem = '';

// Diretórios físicos na hospedagem
$dir_midia = __DIR__ . '/../uploads/';
$dir_docs  = __DIR__ . '/../uploads/documentos/';

if (!is_dir($dir_midia)) { @mkdir($dir_midia, 0755, true); }
if (!is_dir($dir_docs))  { @mkdir($dir_docs, 0755, true); }

function formatar_caminho_relativo($prefixo, $nome_arquivo) {
    return $prefixo . ltrim($nome_arquivo, '/');
}

function gerar_slug($texto) {
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = preg_replace('/[áàãâä]/u', 'a', $texto);
    $texto = preg_replace('/[éèêë]/u', 'e', $texto);
    $texto = preg_replace('/[íìîï]/u', 'i', $texto);
    $texto = preg_replace('/[óòõôö]/u', 'o', $texto);
    $texto = preg_replace('/[úùûü]/u', 'u', $texto);
    $texto = preg_replace('/[ç]/u', 'c', $texto);
    $texto = preg_replace('/[^a-z0-9]/', '-', $texto);
    return trim(preg_replace('/-+/', '-', $texto), '-');
}

// PROCESSAMENTO DO FORMULÁRIO DE CADASTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cadastrar') {
    $titulo          = trim($_POST['titulo'] ?? '');
    $resumo          = trim($_POST['resumo'] ?? '');
    $conteudo        = trim($_POST['conteudo'] ?? '');
    $categoria       = trim($_POST['categoria'] ?? 'Arquitetura');
    $tags            = trim($_POST['tags'] ?? 'design, iluminação');
    $autor           = trim($_POST['autor'] ?? 'Auta Arquitetura');
    $notificar_lista = isset($_POST['disparar_email']) && $_POST['disparar_email'] == '1';
    $slug            = gerar_slug($titulo) . '-' . time();

    $imagem_capa = '';
    $arquivo_doc = '';

    // Upload da capa
    if (isset($_FILES['capa_file']) && $_FILES['capa_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['capa_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm'])) {
            $novo_nome_capa = 'post_capa_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['capa_file']['tmp_name'], $dir_midia . $novo_nome_capa)) {
                $imagem_capa = formatar_caminho_relativo('uploads/', $novo_nome_capa);
            }
        }
    }

    // Upload do documento (.pdf, .doc, .docx)
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $ext_doc = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext_doc, ['pdf', 'doc', 'docx'])) {
            $novo_nome_doc = 'doc_' . time() . '_' . uniqid() . '.' . $ext_doc;
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $dir_docs . $novo_nome_doc)) {
                $arquivo_doc = formatar_caminho_relativo('uploads/documentos/', $novo_nome_doc);
            }
        }
    }

    if (!empty($titulo)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts (titulo, slug, resumo, conteudo, imagem_capa, arquivo_doc_url, categoria, tags, autor, ativo)
                VALUES (:titulo, :slug, :resumo, :conteudo, :capa, :doc, :cat, :tags, :autor, 1)
            ");

            $sucesso = $stmt->execute([
                'titulo'   => $titulo,
                'slug'     => $slug,
                'resumo'   => $resumo,
                'conteudo' => $conteudo,
                'capa'     => $imagem_capa,
                'doc'      => $arquivo_doc,
                'cat'      => $categoria,
                'tags'     => $tags,
                'autor'    => $autor
            ]);

            if ($sucesso) {
                $total_enviados = 0;

                if ($notificar_lista) {
                    $stmt_subs = $pdo->query("SELECT nome, email FROM newsletter_subscribers");
                    $inscritos = $stmt_subs ? $stmt_subs->fetchAll(PDO::FETCH_ASSOC) : [];

                    $url_post = 'https://www.paranista.com.br/post.php?slug=' . urlencode($slug);
                    $assunto  = 'Nova Publicação: ' . $titulo . ' | Atelier Paranista';

                    // E-mail oficial autenticado no cPanel da HostGator
                    $remetente_email = 'contato@paranista.com.br';

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Atelier Paranista <{$remetente_email}>\r\n";
                    $headers .= "Reply-To: {$remetente_email}\r\n";
                    $headers .= "Return-Path: {$remetente_email}\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                    $param_adicional = "-f" . $remetente_email;

                    foreach ($inscritos as $ins) {
                        $corpo = "
                        <html>
                        <body style='font-family: Arial, sans-serif; background-color: #080808; color: #ffffff; padding: 30px;'>
                            <div style='max-width: 600px; margin: 0 auto; background: #0d0d0d; padding: 30px; border: 1px solid #C5A059;'>
                                <h2 style='color: #C5A059; text-transform: uppercase;'>ATELIER PARANISTA</h2>
                                <p style='font-size: 14px; color: #aaaaaa;'>Olá, " . htmlspecialchars($ins['nome']) . "</p>
                                <h3 style='color: #ffffff;'>" . htmlspecialchars($titulo) . "</h3>
                                <p style='font-size: 13px; color: #cccccc; line-height: 1.6;'>" . htmlspecialchars($resumo) . "</p>
                                <br>
                                <a href='{$url_post}' style='background: #C5A059; color: #000000; padding: 12px 20px; text-decoration: none; font-weight: bold; font-size: 12px; display: inline-block; text-transform: uppercase;'>Acessar Publicação</a>
                            </div>
                        </body>
                        </html>";

                        if (@mail($ins['email'], $assunto, $corpo, $headers, $param_adicional)) {
                            $total_enviados++;
                        }
                    }
                }

                $mensagem = 'Publicação salva com sucesso!';
                if ($notificar_lista) {
                    $mensagem .= " Disparo autenticado realizado para {$total_enviados} inscrito(s).";
                }
                $tipo_mensagem = 'sucesso';
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro no banco de dados: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }
}

// EXCLUSÃO DE ITEM
if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
    $stmt->execute(['id' => $id_excluir]);
    header('Location: publicacoes.php');
    exit;
}

// CONSULTA DE PUBLICAÇÕES CADASTRADAS
try {
    $posts = $pdo->query("SELECT * FROM blog_posts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $posts = [];
    $mensagem = 'Aviso: Tabela de publicações ainda não encontrada no MySQL (' . $e->getMessage() . ')';
    $tipo_mensagem = 'erro';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Gerenciar Publicações</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0d0d0d; color: #ffffff; font-family: 'Montserrat', sans-serif; padding: 40px; margin: 0; }
        .admin-container { max-width: 1000px; margin: 0 auto; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { font-size: 1.5rem; letter-spacing: 3px; color: #C5A059; margin: 0; text-transform: uppercase; font-weight: 400; }
        .nav-link { color: #888888; text-decoration: none; font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; }
        .nav-link:hover { color: #C5A059; }
        .form-card { background: #141414; border: 1px solid rgba(255, 255, 255, 0.1); padding: 30px; margin-bottom: 40px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }
        label { font-size: 0.65rem; letter-spacing: 2px; color: #888888; text-transform: uppercase; }
        input[type="text"], select, textarea { background: #000; border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 12px; font-size: 0.85rem; font-family: inherit; outline: none; }
        textarea { resize: vertical; min-height: 100px; }
        input[type="file"] { background: transparent; color: #aaa; font-size: 0.8rem; }
        .btn-submit { background: #C5A059; color: #000; border: none; padding: 14px 28px; font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #ffffff; }
        .msg { padding: 12px; margin-bottom: 20px; font-size: 0.8rem; }
        .msg.sucesso { background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid #4ade80; }
        .msg.erro { background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid #f87171; }
        .file-hint { font-size: 0.7rem; color: #666; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.75rem; }
        th { color: #C5A059; letter-spacing: 2px; text-transform: uppercase; }
        .doc-badge { display: inline-block; padding: 3px 8px; background: rgba(197, 160, 89, 0.15); color: #C5A059; border: 1px solid #C5A059; font-size: 0.65rem; text-decoration: none; }
        .btn-del { color: #f87171; text-decoration: none; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header-top">
        <h1>GESTÃO DE PUBLICAÇÕES &amp; DOCUMENTOS</h1>
        <div>
            <a href="index.php" class="nav-link" style="margin-right: 15px;">&larr; Painel</a>
            <a href="logout.php" class="nav-link" style="color: #f87171;">Sair</a>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="msg <?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form action="publicacoes.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="cadastrar">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Título da Publicação / Artigo *</label>
                    <input type="text" name="titulo" required placeholder="Ex: Caderno Técnico de Especificação Lumínica">
                </div>

                <div class="form-group">
                    <label>Categoria *</label>
                    <input type="text" name="categoria" required placeholder="Ex: Arquitetura, Iluminação">
                </div>

                <div class="form-group">
                    <label>Tags (Separadas por vírgula)</label>
                    <input type="text" name="tags" placeholder="Ex: pdf, lumínico, detalhes">
                </div>

                <div class="form-group full-width">
                    <label>Resumo Curto (Exibido no card do blog)</label>
                    <textarea name="resumo" style="min-height: 60px;"></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Conteúdo Completo do Artigo / Ensaio</label>
                    <textarea name="conteudo"></textarea>
                </div>

                <div class="form-group">
                    <label>Capa da Publicação (Imagem ou Vídeo MP4)</label>
                    <input type="file" name="capa_file" accept="image/*,video/mp4">
                </div>

                <div class="form-group">
                    <label>Arquivo Anexo (*.pdf, *.doc, *.docx)</label>
                    <input type="file" name="doc_file" accept=".pdf,.doc,.docx">
                </div>

                <div class="form-group full-width" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: #C5A059; text-transform: none; font-size: 0.8rem;">
                        <input type="checkbox" name="disparar_email" value="1" style="width: 18px; height: 18px; accent-color: #C5A059;">
                        <span>Disparar e-mail de notificação para todos os contatos cadastrados na newsletter</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-submit">Cadastrar e Publicar</button>
        </form>
    </div>

    <h2>PUBLICAÇÕES CADASTRADAS</h2>
    <table>
        <thead>
            <tr>
                <th>Título</th>
                <th>Categoria</th>
                <th>Tags</th>
                <th>Documento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['titulo']) ?></td>
                        <td><?= htmlspecialchars($p['categoria']) ?></td>
                        <td><?= htmlspecialchars($p['tags']) ?></td>
                        <td>
                            <?php if (!empty($p['arquivo_doc_url'])): ?>
                                <a href="../<?= htmlspecialchars($p['arquivo_doc_url']) ?>" target="_blank" class="doc-badge">DOWNLOAD DOC</a>
                            <?php else: ?>
                                <span style="color:#555;">Sem anexo</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="publicacoes.php?excluir=<?= $p['id'] ?>" class="btn-del" onclick="return confirm('Remover publicação?')">Remover</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; color:#666;">Nenhuma publicação encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>