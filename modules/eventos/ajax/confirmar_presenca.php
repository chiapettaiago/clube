<?php
require_once '../../../config.php';

$inscricao_id = $_POST['inscricao_id'] ?? 0;

if($inscricao_id) {
    $stmt = $pdo->prepare("
        UPDATE inscricoes_eventos 
        SET presenca_confirmada = 1, data_presenca = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$inscricao_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>