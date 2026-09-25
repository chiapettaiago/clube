<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

if($_POST) {
    $nome_servico = $_POST['nome_servico'];
    
    $stmt = $pdo->prepare("INSERT INTO terceiros (nome_servico) VALUES (?)");
    $stmt->execute([$nome_servico]);
    
    header('Location: index.php?msg=Cadastrado com sucesso!');
    exit;
}
?>