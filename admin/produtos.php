<?php 
require_once __DIR__ . '/auth.php'; // Garante acesso restrito
require_once __DIR__ . '/../config/conexao.php'; // Chamada única da conexão

// Função auxiliar para formatar caminhos de imagens de forma relativa
function formatar_caminho_imagem($nome_arquivo) {
    return 'assets/produtos/' . ltrim($nome_arquivo, '/');
}

// Busca todas as coleções cadastradas sem duplicidade
$stmt_colecoes = $pdo->query("
    SELECT DISTINCT TRIM(colecao) AS colecao_nome 
    FROM produtos 
    WHERE colecao IS NOT NULL AND colecao != '' 
    ORDER BY colecao_nome ASC
");
$colecoes_existentes = $stmt_colecoes->fetchAll(PDO::FETCH_COLUMN);

// Busca todos os subtipos cadastrados sem duplicidade
$stmt_subtipos = $pdo->query("
    SELECT DISTINCT TRIM(subtipo) AS subtipo_nome 
    FROM produtos 
    WHERE subtipo IS NOT NULL AND subtipo != '' 
    ORDER BY subtipo_nome ASC
");
$subtipos_existentes = $stmt_subtipos->fetchAll(PDO::FETCH_COLUMN);

// Subtipos padrão de sugestão por categoria caso o banco ainda não tenha registros
$subtipos_sugestao = [
    'iluminacao' => ['pendentes', 'arandelas', 'mesa', 'piso'],
    'mobiliario' => ['cadeiras', 'mesas-de-centro', 'mesas-de-jantar', 'poltronas', 'sofas', 'buffets', 'estantes'],
    'objetos'    => ['vasos', 'centros-de-mesa', 'esculturas', 'espelhos', 'bandejas']
];

$mensagem = '';
$tipo_mensagem = '';

// PROCESSAMENTO DO FORMULÁRIO DE CADASTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cadastrar') {
    $nome = trim($_POST['nome'] ?? '');
    $categoria = $_POST['categoria'] ?? 'iluminacao';
    $colecao = trim($_POST['colecao'] ?? '');
    $colecao_descricao = trim($_POST['colecao_descricao'] ?? '');
    $subtipo = strtolower(trim($_POST['subtipo'] ?? ''));
    $material_dimensoes = trim($_POST['material_dimensoes'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = !empty($_POST['preco']) ? str_replace(',', '.', $_POST['preco']) : null;
    
    // Diretório físico de destino do upload
    $diretorio_upload = __DIR__ . '/../assets/produtos/';
    if (!is_dir($diretorio_upload)) {
        mkdir($diretorio_upload, 0755, true);
    }

    $imagem_url = '';
    $imagem_hover_url = '';

    // UPLOAD DA IMAGEM PRINCIPAL
    if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['imagem_principal']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extensao, $extensoes_permitidas)) {
            $novo_nome = uniqid('prod_') . '.' . $extensao;
            if (move_uploaded_file($_FILES['imagem_principal']['tmp_name'], $diretorio_upload . $novo_nome)) {
                $imagem_url = formatar_caminho_imagem($novo_nome);
            }
        }
    }

    // UPLOAD DA IMAGEM HOVER (OPCIONAL)
    if (isset($_FILES['imagem_hover']) && $_FILES['imagem_hover']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['imagem_hover']['name'], PATHINFO_EXTENSION));
        if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
            $novo_nome_hover = uniqid('prod_hover_') . '.' . $extensao;
            if (move_uploaded_file($_FILES['imagem_hover']['tmp_name'], $diretorio_upload . $novo_nome_hover)) {
                $imagem_hover_url = formatar_caminho_imagem($novo_nome_hover);
            }
        }
    }

    if (!empty($nome) && !empty($imagem_url)) {
        $stmt = $pdo->prepare("INSERT INTO produtos (nome, categoria, colecao, colecao_descricao, subtipo, material_dimensoes, descricao, preco, imagem_url, imagem_hover_url, ativo) VALUES (:nome, :categoria, :colecao, :colecao_descricao, :subtipo, :material_dimensoes, :descricao, :preco, :imagem_url, :imagem_hover_url, 1)");
        
        $sucesso = $stmt->execute([
            'nome'               => $nome,
            'categoria'          => $categoria,
            'colecao'            => $colecao,
            'colecao_descricao'  => $colecao_descricao,
            'subtipo'            => $subtipo,
            'material_dimensoes' => $material_dimensoes,
            'descricao'          => $descricao,
            'preco'              => $preco,
            'imagem_url'         => $imagem_url,
            'imagem_hover_url'   => $imagem_hover_url
        ]);

        if ($sucesso) {
            if (!empty($colecao) && !empty($colecao_descricao)) {
                $stmt_update_col = $pdo->prepare("UPDATE produtos SET colecao_descricao = :desc WHERE LOWER(colecao) = LOWER(:col)");
                $stmt_update_col->execute(['desc' => $colecao_descricao, 'col' => $colecao]);
            }

            $mensagem = 'Produto cadastrado com sucesso!';
            $tipo_mensagem = 'sucesso';
        } else {
            $mensagem = 'Erro ao salvar no banco de dados.';
            $tipo_mensagem = 'erro';
        }
    } else {
        $mensagem = 'Por favor, preencha o nome do produto e selecione a imagem principal.';
        $tipo_mensagem = 'erro';
    }
}

// PROCESSAMENTO DE EXCLUSÃO
if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id");
    $stmt->execute(['id' => $id_excluir]);
    header('Location: produtos.php');
    exit;
}

// FILTROS DE BUSCA
$filtro_categoria = $_GET['filtro_categoria'] ?? '';
$filtro_colecao = $_GET['filtro_colecao'] ?? '';

$sql = "SELECT * FROM produtos WHERE 1=1";
$params = [];

if (!empty($filtro_categoria)) {
    $sql .= " AND categoria = :categoria";
    $params['categoria'] = $filtro_categoria;
}

if (!empty($filtro_colecao)) {
    $sql .= " AND colecao = :colecao";
    $params['colecao'] = $filtro_colecao;
}

$sql .= " ORDER BY id DESC";

$stmt_list = $pdo->prepare($sql);
$stmt_list->execute($params);
$produtos_cadastrados = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

$colecoes_filtro = $pdo->query("SELECT DISTINCT colecao FROM produtos WHERE colecao IS NOT NULL AND colecao != '' ORDER BY colecao ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Gerenciar Produtos | Atelier Paranista</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0d0d0d; color: #ffffff; font-family: 'Montserrat', sans-serif; padding: 40px; margin: 0; }
        .admin-container { max-width: 1000px; margin: 0 auto; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { font-size: 1.5rem; letter-spacing: 3px; color: #C5A059; margin: 0; text-transform: uppercase; font-weight: 400; }
        .nav-link { color: #888888; text-decoration: none; font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; transition: color 0.3s; }
        .nav-link:hover { color: #C5A059; }
        .form-card { background: #141414; border: 1px solid rgba(255, 255, 255, 0.1); padding: 30px; margin-bottom: 40px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }
        label { font-size: 0.65rem; letter-spacing: 2px; color: #888888; text-transform: uppercase; }
        input[type="text"], select, textarea { background: #000; border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 12px; font-size: 0.85rem; font-family: inherit; outline: none; }
        textarea { resize: vertical; min-height: 80px; }
        input[type="file"] { background: transparent; color: #aaa; font-size: 0.8rem; }
        .btn-submit { background: #C5A059; color: #000; border: none; padding: 14px 28px; font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #ffffff; }
        .msg { padding: 12px; margin-bottom: 20px; font-size: 0.8rem; letter-spacing: 1px; }
        .msg.sucesso { background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid #4ade80; }
        .msg.erro { background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid #f87171; }
        .form-hint { font-size: 0.7rem; color: #666; margin-top: 4px; }
        
        .list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; margin-top: 50px; flex-wrap: wrap; gap: 15px; }
        .list-header h2 { font-size: 1.1rem; letter-spacing: 2.5px; color: #ffffff; text-transform: uppercase; margin: 0; font-weight: 400; }
        .filter-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .btn-filter { background: transparent; border: 1px solid #C5A059; color: #C5A059; padding: 10px 18px; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; }
        .btn-filter:hover { background: #C5A059; color: #000; }
        .btn-reset { color: #888888; text-decoration: none; font-size: 0.65rem; letter-spacing: 1.5px; text-transform: uppercase; }
        .btn-reset:hover { color: #ffffff; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.75rem; }
        th { color: #C5A059; letter-spacing: 2px; text-transform: uppercase; font-weight: 500; }
        .thumb-img { width: 45px; height: 55px; object-fit: cover; background: #222; }
        .btn-del { color: #f87171; text-decoration: none; font-size: 0.7rem; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header-top">
        <h1>ADMINISTRAÇÃO DE ACERVO</h1>
        <div>
            <a href="index.php" class="nav-link" style="margin-right: 15px;">&larr; Painel</a>
            <a href="logout.php" class="nav-link" style="color: #f87171;">Sair</a>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="msg <?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <!-- FORMULÁRIO DE CADASTRO -->
    <div class="form-card">
        <form action="produtos.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="cadastrar">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nome da Peça *</label>
                    <input type="text" name="nome" required placeholder="Ex: Mesa de Jantar Paranista">
                </div>

                <div class="form-group">
                    <label>Categoria (Tipologia) *</label>
                    <select name="categoria" id="categoria_select" required onchange="atualizarSugestoesSubtipo()">
                        <option value="iluminacao">Iluminação</option>
                        <option value="mobiliario">Mobiliário</option>
                        <option value="objetos">Objetos</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="colecao">Coleção:</label>
                    <input type="text" 
                           name="colecao" 
                           id="colecao" 
                           list="lista_colecoes" 
                           placeholder="Selecione ou digite uma nova coleção..."
                           autocomplete="off">

                    <datalist id="lista_colecoes">
                        <?php foreach ($colecoes_existentes as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <small class="form-hint">Escolha uma coleção existente para manter a padronização ou digite um novo nome.</small>
                </div>

                <!-- CAMPO DINÂMICO DE SUBTIPO -->
                <div class="form-group">
                    <label for="subtipo">Subtipo (Tipologia):</label>
                    <input type="text" 
                           name="subtipo" 
                           id="subtipo" 
                           list="lista_subtipos" 
                           placeholder="Selecione ou digite um novo subtipo (ex: cadeiras, mesas)..."
                           autocomplete="off"
                           required>

                    <datalist id="lista_subtipos">
                        <!-- Preenchido dinamicamente via JS e dados do PHP -->
                    </datalist>

                    <small class="form-hint">Escolha uma sugestão existente ou digite um novo subtipo para esta categoria.</small>
                </div>

                <!-- CAMPO HISTÓRIA / CONCEITO DA COLEÇÃO -->
                <div class="form-group full-width">
                    <label>Conceito / História da Coleção (Opcional)</label>
                    <textarea name="colecao_descricao" placeholder="Escreva o conceito, inspiração ou contexto editorial desta coleção..."></textarea>
                </div>

                <div class="form-group">
                    <label>Preço (R$) (Opcional)</label>
                    <input type="text" name="preco" placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Material &amp; Dimensões</label>
                    <input type="text" name="material_dimensoes" placeholder="Ex: Madeira Nobre & Vidro | 180cm x 90cm">
                </div>

                <div class="form-group full-width">
                    <label>Descrição Editorial do Produto</label>
                    <textarea name="descricao" placeholder="Escreva sobre o conceito, processo artesanal, acabamento ou inspiração da peça..."></textarea>
                </div>

                <div class="form-group">
                    <label>Imagem Principal *</label>
                    <input type="file" name="imagem_principal" accept="image/*" required>
                </div>

                <div class="form-group">
                    <label>Imagem Hover (Detalhe/Acesa)</label>
                    <input type="file" name="imagem_hover" accept="image/*">
                </div>
            </div>

            <button type="submit" class="btn-submit">Cadastrar Produto</button>
        </form>
    </div>

    <!-- TABELA DE PRODUTOS CADASTRADOS -->
    <div class="list-header">
        <h2>PRODUTOS CADASTRADOS</h2>
        
        <form action="produtos.php" method="GET" class="filter-form">
            <select name="filtro_categoria">
                <option value="">TODAS AS CATEGORIAS</option>
                <option value="iluminacao" <?= $filtro_categoria === 'iluminacao' ? 'selected' : '' ?>>ILUMINAÇÃO</option>
                <option value="mobiliario" <?= $filtro_categoria === 'mobiliario' ? 'selected' : '' ?>>MOBILIÁRIO</option>
                <option value="objetos" <?= $filtro_categoria === 'objetos' ? 'selected' : '' ?>>OBJETOS</option>
            </select>

            <select name="filtro_colecao">
                <option value="">TODAS AS COLEÇÕES</option>
                <?php foreach ($colecoes_filtro as $col): ?>
                    <option value="<?= htmlspecialchars($col) ?>" <?= $filtro_colecao === $col ? 'selected' : '' ?>>
                        <?= strtoupper(htmlspecialchars($col)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-filter">FILTRAR</button>
            <?php if (!empty($filtro_categoria) || !empty($filtro_colecao)): ?>
                <a href="produtos.php" class="btn-reset">LIMPAR</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Coleção</th>
                <th>Subtipo</th>
                <th>Especificação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($produtos_cadastrados)): ?>
                <?php foreach ($produtos_cadastrados as $prod): 
                    // Remove '/meu-atelie/' do caminho para a miniatura carregar no admin
                    $img_thumb = '../' . ltrim(str_replace('/meu-atelie/', '', $prod['imagem_url']), '/');
                ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($img_thumb) ?>" class="thumb-img" alt=""></td>
                        <td><?= htmlspecialchars($prod['nome']) ?></td>
                        <td><?= strtoupper(htmlspecialchars($prod['categoria'])) ?></td>
                        <td><?= !empty($prod['colecao']) ? strtoupper(htmlspecialchars($prod['colecao'])) : '—' ?></td>
                        <td><?= strtoupper(htmlspecialchars($prod['subtipo'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($prod['material_dimensoes']) ?></td>
                        <td><a href="produtos.php?excluir=<?= $prod['id'] ?>" class="btn-del" onclick="return confirm('Excluir este produto?')">Remover</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #666; padding: 30px;">Nenhum produto encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Dados de sugestão vindos do PHP (existentes + padronizados)
const sugestoesSubtipos = <?= json_encode($subtipos_sugestao) ?>;
const subtiposExistentesBD = <?= json_encode($subtipos_existentes) ?>;

function atualizarSugestoesSubtipo() {
    const catSelect = document.getElementById('categoria_select').value;
    const datalist = document.getElementById('lista_subtipos');
    datalist.innerHTML = '';

    // Unifica sugestões padrão da categoria com os cadastrados no banco de dados
    const listaCombinada = new Set([...(sugestoesSubtipos[catSelect] || []), ...subtiposExistentesBD]);

    listaCombinada.forEach(item => {
        if (item) {
            const opt = document.createElement('option');
            opt.value = item;
            datalist.appendChild(opt);
        }
    });
}

// Inicializa a lista de sugestões ao carregar a página
document.addEventListener('DOMContentLoaded', atualizarSugestoesSubtipo);
</script>

</body>
</html>