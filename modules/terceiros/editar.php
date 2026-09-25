<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

if($_POST) {
    $id = $_POST['id'];
    $nome_servico = $_POST['nome_servico'];
    
    $stmt = $pdo->prepare("UPDATE terceiros SET nome_servico = ? WHERE id = ?");
    $stmt->execute([$nome_servico, $id]);
    
    header('Location: index.php?msg=Atualizado com sucesso!');
    exit;
}
?>