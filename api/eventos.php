<?php
require_once 'config.php';

$tokenData = validarToken();
$socio_id = $tokenData['socio_id'];
$method = $_SERVER['REQUEST_METHOD'];

// GET - Listar eventos
if($method == 'GET') {
    $status = $_GET['status'] ?? 'ativos';
    $limite = intval($_GET['limite'] ?? 20);
    
    $sql = "
        SELECT e.*, 
               (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id AND status = 'confirmada') as inscritos
        FROM eventos e
        WHERE e.status = 'ativo'
    ";
    
    if($status == 'inscritos') {
        $sql .= " AND e.id IN (SELECT evento_id FROM inscricoes_eventos WHERE socio_id = ? AND status = 'confirmada')";
        $stmt = $pdo->prepare($sql . " ORDER BY e.data_inicio ASC LIMIT ?");
        $stmt->execute([$socio_id, $limite]);
    } else {
        $sql .= " AND e.data_inicio > NOW()";
        $stmt = $pdo->prepare($sql . " ORDER BY e.data_inicio ASC LIMIT ?");
        $stmt->execute([$limite]);
    }
    
    $eventos = $stmt->fetchAll();
    
    foreach($eventos as &$e) {
        $e['imagem'] = $e['imagem'] ? 'http://localhost/clube/assets/uploads/eventos/' . $e['imagem'] : null;
        $e['inscrito'] = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = ? AND socio_id = ? AND status = 'confirmada'")
                            ->execute([$e['id'], $socio_id]) ? $pdo->fetchColumn() > 0 : false;
    }
    
    logAPI('eventos.php', 'GET', $socio_id, 200);
    respostaJSON($eventos);
}

// POST - Inscrever-se em evento
elseif($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $evento_id = $input['evento_id'] ?? $_POST['evento_id'] ?? 0;
    
    // Verificar se já está inscrito
    $check = $pdo->prepare("SELECT id FROM inscricoes_eventos WHERE evento_id = ? AND socio_id = ?");
    $check->execute([$evento_id, $socio_id]);
    
    if($check->rowCount() > 0) {
        erroJSON('Você já está inscrito neste evento', 400);
    }
    
    // Verificar vagas
    $evento = $pdo->prepare("SELECT capacidade, vagas_disponiveis FROM eventos WHERE id = ?");
    $evento->execute([$evento_id]);
    $evento = $evento->fetch();
    
    if($evento['capacidade'] > 0 && $evento['vagas_disponiveis'] <= 0) {
        erroJSON('Evento lotado', 400);
    }
    
    $codigo = uniqid('INS-');
    $stmt = $pdo->prepare("
        INSERT INTO inscricoes_eventos (evento_id, socio_id, codigo_inscricao, status) 
        VALUES (?, ?, ?, 'confirmada')
    ");
    $stmt->execute([$evento_id, $socio_id, $codigo]);
    
    // Atualizar vagas
    $pdo->prepare("UPDATE eventos SET vagas_disponiveis = vagas_disponiveis - 1 WHERE id = ?")->execute([$evento_id]);
    
    logAPI('eventos.php', 'POST', $socio_id, 200, $input);
    respostaJSON(['inscrito' => true, 'evento_id' => $evento_id], 201, 'Inscrição realizada com sucesso');
}
?>