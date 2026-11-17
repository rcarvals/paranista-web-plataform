<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conexao.php';

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$nome = trim($_POST['nome'] ?? '');
$interesses = $_POST['interesses'] ?? [];
$str_interesses = implode(', ', array_map('htmlspecialchars', $interesses));

if ($email && !empty($nome)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO clientes_flyer (nome, email, interesses) VALUES (:nome, :email, :interesses) 
                               ON DUPLICATE KEY UPDATE nome = :nome, interesses = :interesses");
        $stmt->execute([
            'nome' => $nome, 
            'email' => $email,
            'interesses' => $str_interesses
        ]);

        echo json_encode([
            'sucesso' => true, 
            'mensagem' => 'Obrigado por se juntar ao Atelier Paranista!'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Erro ao salvar suas informações.'
        ]);
    }
} else {
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Por favor, preencha o nome e um e-mail válido.'
    ]);
}