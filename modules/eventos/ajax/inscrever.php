<?php
require_once '../../../config.php';
session_start();

$evento_id = $_POST['evento_id'] ?? 0;

// Buscar sócio pelo email do usuário logado
$stmt = $pdo->prepare("SELECT id FROM socios WHERE email = ? OR cpf = ?");
$stmt->execute([$_SESSION['usuario_email'] ?? '', '']);
$socio = $stmt->fetch();

if(!$socio) {
    echo json_encode(['success' => false, 'message' => 'Sócio não encontrado']);
    exit;
}

$socio_id = $socio['id'];

// Verificar se já está inscrito
$check = $pdo->prepare("SELECT id FROM inscricoes_eventos WHERE evento_id = ? AND socio_id = ?");
$check->execute([$evento_id, $socio_id]);

if($check->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Você já está inscrito neste evento']);
    exit;
}

// Verificar vagas
$evento = $pdo->prepare("SELECT capacidade, vagas_disponiveis FROM eventos WHERE id = ?");
$evento->execute([$evento_id]);
$evento = $evento->fetch();

if($evento['capacidade'] > 0 && $evento['vagas_disponiveis'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Evento lotado!']);
    exit;
}

// Inserir inscrição
$codigo = uniqid('INS-');
$stmt = $pdo->prepare("
    INSERT INTO inscricoes_eventos (evento_id, socio_id, codigo_inscricao, status) 
    VALUES (?, ?, ?, 'confirmada')
");
$stmt->execute([$evento_id, $socio_id, $codigo]);

// Atualizar vagas
$pdo->prepare("UPDATE eventos SET vagas_disponiveis = vagas_disponiveis - 1 WHERE id = ?")->execute([$evento_id]);

echo json_encode(['success' => true]);
?>