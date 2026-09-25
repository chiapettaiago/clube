<?php
// Arquivo: C:/xampp/htdocs/clube/cron/disparar_notificacoes.php
// Executar este arquivo diariamente via cron job

require_once '../config.php';

// Processar lembretes de aniversário
$dias = $pdo->query("SELECT valor FROM config_notificacoes WHERE chave = 'lembrete_aniversario'")->fetchColumn();
if($dias > 0) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nome, s.email_socio, s.telefone, a.canal
        FROM socios s
        LEFT JOIN assinaturas_notificacoes a ON s.id = a.socio_id AND a.tipo_notificacao = 'aniversario' AND a.ativo = 1
        WHERE s.ativo = 1 
        AND s.data_nascimento IS NOT NULL
        AND DATE_FORMAT(s.data_nascimento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL ? DAY), '%m-%d')
    ");
    $stmt->execute([$dias]);
    $aniversariantes = $stmt->fetchAll();
    
    foreach($aniversariantes as $aniv) {
        $canal = $aniv['canal'] ?? 'email';
        $mensagem = "🎂 Feliz Aniversário {$aniv['nome']}! O Clube deseja um dia especial!";
        
        if($canal == 'email' || $canal == 'ambos') {
            if($aniv['email_socio']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', 'Feliz Aniversário!', ?)");
                $stmt->execute([$aniv['email_socio'], $mensagem]);
            }
        }
        if($canal == 'sms' || $canal == 'ambos') {
            if($aniv['telefone']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Aniversário', ?)");
                $stmt->execute([$aniv['telefone'], $mensagem]);
            }
        }
    }
}

// Processar vencimentos
$dias = $pdo->query("SELECT valor FROM config_notificacoes WHERE chave = 'lembrete_vencimento'")->fetchColumn();
if($dias > 0) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nome, s.email_socio, s.telefone, l.valor, a.canal
        FROM lancamentos l
        JOIN socios s ON l.socio_id = s.id
        LEFT JOIN assinaturas_notificacoes a ON s.id = a.socio_id AND a.tipo_notificacao = 'vencimento' AND a.ativo = 1
        WHERE l.status = 'pendente' 
        AND DATEDIFF(l.data_vencimento, CURRENT_DATE) = ?
        AND l.tipo = 'receita'
    ");
    $stmt->execute([$dias]);
    $vencimentos = $stmt->fetchAll();
    
    foreach($vencimentos as $v) {
        $canal = $v['canal'] ?? 'email';
        $mensagem = "Prezado(a) {$v['nome']}, sua mensalidade de R$ " . number_format($v['valor'], 2, ',', '.') . " vence em {$dias} dias.";
        
        if($canal == 'email' || $canal == 'ambos') {
            if($v['email_socio']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', 'Mensalidade em Vencimento', ?)");
                $stmt->execute([$v['email_socio'], $mensagem]);
            }
        }
        if($canal == 'sms' || $canal == 'ambos') {
            if($v['telefone']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Mensalidade', ?)");
                $stmt->execute([$v['telefone'], $mensagem]);
            }
        }
    }
}

// Processar eventos
$dias = $pdo->query("SELECT valor FROM config_notificacoes WHERE chave = 'lembrete_evento'")->fetchColumn();
if($dias > 0) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.nome, s.email_socio, s.telefone, e.titulo, a.canal
        FROM inscricoes_eventos i
        JOIN socios s ON i.socio_id = s.id
        JOIN eventos e ON i.evento_id = e.id
        LEFT JOIN assinaturas_notificacoes a ON s.id = a.socio_id AND a.tipo_notificacao = 'evento' AND a.ativo = 1
        WHERE i.status = 'confirmada'
        AND DATEDIFF(e.data_inicio, CURRENT_DATE) = ?
    ");
    $stmt->execute([$dias]);
    $eventos = $stmt->fetchAll();
    
    foreach($eventos as $ev) {
        $canal = $ev['canal'] ?? 'email';
        $mensagem = "Lembrete: O evento '{$ev['titulo']}' acontecerá em {$dias} dias!";
        
        if($canal == 'email' || $canal == 'ambos') {
            if($ev['email_socio']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', 'Lembrete de Evento', ?)");
                $stmt->execute([$ev['email_socio'], $mensagem]);
            }
        }
        if($canal == 'sms' || $canal == 'ambos') {
            if($ev['telefone']) {
                $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Evento', ?)");
                $stmt->execute([$ev['telefone'], $mensagem]);
            }
        }
    }
}

// Processar fila
$stmt = $pdo->prepare("SELECT * FROM fila_notificacoes WHERE status = 'pendente' ORDER BY created_at ASC LIMIT 50");
$stmt->execute();
$pendentes = $stmt->fetchAll();

foreach($pendentes as $p) {
    if($p['tipo'] == 'email') {
        $enviado = true; // Aqui você integra com o envio real
    } else {
        $enviado = true; // Aqui você integra com o envio real
    }
    
    $status = $enviado ? 'enviado' : 'erro';
    $stmt = $pdo->prepare("UPDATE fila_notificacoes SET status = ?, data_envio = NOW() WHERE id = ?");
    $stmt->execute([$status, $p['id']]);
}

echo "Notificações processadas em " . date('Y-m-d H:i:s') . "\n";
echo "Aniversariantes: " . count($aniversariantes ?? []) . "\n";
echo "Vencimentos: " . count($vencimentos ?? []) . "\n";
echo "Eventos: " . count($eventos ?? []) . "\n";
echo "Fila processada: " . count($pendentes) . "\n";
?>