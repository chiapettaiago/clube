<?php
require_once '../../../config.php';

$tipo = $_GET['tipo'] ?? 'todos';
$limite = 10;

// Processar e-mails
if($tipo == 'email' || $tipo == 'todos') {
    $stmt = $pdo->prepare("
        SELECT * FROM fila_notificacoes 
        WHERE tipo = 'email' AND status = 'pendente' 
        ORDER BY created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    $emails = $stmt->fetchAll();
    
    foreach($emails as $email) {
        // Simular envio (aqui você integra com biblioteca de e-mail real)
        $enviado = enviarEmail($email['destinatario'], $email['assunto'], $email['mensagem']);
        
        $status = $enviado ? 'enviado' : 'erro';
        $stmt = $pdo->prepare("UPDATE fila_notificacoes SET status = ?, data_envio = NOW() WHERE id = ?");
        $stmt->execute([$status, $email['id']]);
    }
}

// Processar SMS
if($tipo == 'sms' || $tipo == 'todos') {
    $stmt = $pdo->prepare("
        SELECT * FROM fila_notificacoes 
        WHERE tipo = 'sms' AND status = 'pendente' 
        ORDER BY created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    $sms = $stmt->fetchAll();
    
    foreach($sms as $s) {
        // Simular envio (aqui você integra com API de SMS real)
        $enviado = enviarSMS($s['destinatario'], $s['mensagem']);
        
        $status = $enviado ? 'enviado' : 'erro';
        $stmt = $pdo->prepare("UPDATE fila_notificacoes SET status = ?, data_envio = NOW() WHERE id = ?");
        $stmt->execute([$status, $s['id']]);
    }
}

echo json_encode(['success' => true, 'message' => 'Fila processada']);

function enviarEmail($destinatario, $assunto, $mensagem) {
    // Aqui você implementa o envio real com PHPMailer ou SwiftMailer
    // Por enquanto, retorna true para simular
    return true;
}

function enviarSMS($destinatario, $mensagem) {
    // Aqui você implementa o envio real com API (Twilio, Zenvia, etc)
    // Por enquanto, retorna true para simular
    return true;
}
?>