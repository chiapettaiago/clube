<?php
require_once '../../config.php';

$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');

if($cpf) {
    $stmt = $pdo->prepare("SELECT id, nome FROM socios WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $socio = $stmt->fetch();
    
    echo json_encode([
        'existe' => $socio ? true : false,
        'nome' => $socio['nome'] ?? ''
    ]);
} else {
    echo json_encode(['existe' => false]);
}
?>