<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Processar execução manual
if(isset($_GET['executar'])) {
    $tipo = $_GET['executar'];
    $resultado = processarLembrete($tipo);
    $msg = $resultado;
}

// Função para processar lembretes
function processarLembrete($tipo) {
    global $pdo;
    
    $config = [];
    $cfg = $pdo->query("SELECT chave, valor FROM config_notificacoes");
    foreach($cfg as $c) {
        $config[$c['chave']] = $c['valor'];
    }
    
    $totalEnviados = 0;
    
    if($tipo == 'aniversario') {
        $dias = intval($config['lembrete_aniversario']);
        if($dias > 0) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.nome, s.email_socio, s.telefone, s.data_nascimento,
                       a.canal
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
                        $totalEnviados++;
                    }
                }
                if($canal == 'sms' || $canal == 'ambos') {
                    if($aniv['telefone']) {
                        $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Aniversário', ?)");
                        $stmt->execute([$aniv['telefone'], $mensagem]);
                        $totalEnviados++;
                    }
                }
            }
            return "✅ Processado: $totalEnviados lembretes de aniversário adicionados à fila.";
        }
    }
    
    if($tipo == 'vencimento') {
        $dias = intval($config['lembrete_vencimento']);
        if($dias > 0) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.nome, s.email_socio, s.telefone, l.valor, l.data_vencimento,
                       a.canal
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
                        $totalEnviados++;
                    }
                }
                if($canal == 'sms' || $canal == 'ambos') {
                    if($v['telefone']) {
                        $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Mensalidade', ?)");
                        $stmt->execute([$v['telefone'], $mensagem]);
                        $totalEnviados++;
                    }
                }
            }
            return "✅ Processado: $totalEnviados lembretes de vencimento adicionados à fila.";
        }
    }
    
    if($tipo == 'evento') {
        $dias = intval($config['lembrete_evento']);
        if($dias > 0) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.id, s.nome, s.email_socio, s.telefone, e.titulo, e.data_inicio,
                       a.canal
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
                        $totalEnviados++;
                    }
                }
                if($canal == 'sms' || $canal == 'ambos') {
                    if($ev['telefone']) {
                        $stmt = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'sms', 'Evento', ?)");
                        $stmt->execute([$ev['telefone'], $mensagem]);
                        $totalEnviados++;
                    }
                }
            }
            return "✅ Processado: $totalEnviados lembretes de evento adicionados à fila.";
        }
    }
    
    return "ℹ️ Nenhum lembrete processado para o tipo selecionado.";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembretes Automáticos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5><i class="fas fa-clock"></i> Lembretes Automáticos</h5>
            </div>
            <div class="card-body">
                <?php if(isset($msg)): ?>
                    <div class="alert alert-info"><?= $msg ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-birthday-cake fa-3x text-primary mb-3"></i>
                                <h5>Aniversariantes</h5>
                                <p>Envia lembretes de aniversário conforme configuração</p>
                                <a href="?executar=aniversario" class="btn btn-primary" onclick="return confirm('Executar lembretes de aniversário agora?')">
                                    <i class="fas fa-play"></i> Executar Agora
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                                <h5>Vencimento de Mensalidades</h5>
                                <p>Envia lembretes de contas a vencer</p>
                                <a href="?executar=vencimento" class="btn btn-success" onclick="return confirm('Executar lembretes de vencimento agora?')">
                                    <i class="fas fa-play"></i> Executar Agora
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-calendar-alt fa-3x text-info mb-3"></i>
                                <h5>Próximos Eventos</h5>
                                <p>Envia lembretes de eventos inscritos</p>
                                <a href="?executar=evento" class="btn btn-info" onclick="return confirm('Executar lembretes de eventos agora?')">
                                    <i class="fas fa-play"></i> Executar Agora
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-secondary mt-4">
                    <i class="fas fa-info-circle"></i>
                    <strong>Informação:</strong> Os lembretes são executados automaticamente pelo sistema diariamente. 
                    Você também pode executá-los manualmente clicando nos botões acima.
                </div>
            </div>
        </div>
    </div>
</body>
</html>