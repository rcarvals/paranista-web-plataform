<?php
// Configurações do Banco de Dados na HostGator
$host    = 'localhost'; // Mantenha 'localhost'
$db      = 'cccccccccccccuser'; // Nome completo do banco no cPanel
$user    = 'ccccccccccccccccccc'; // Nome completo do usuário no cPanel
$pass    = 'rrrrrrrrrrrrrrrrr';    // Senha criada no cPanel
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>