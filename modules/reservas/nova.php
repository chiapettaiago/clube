<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Buscar espaços
$espacos = $pdo->query("SELECT * FROM espacos WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Buscar sócios - CORRIGIDO
$socios = $pdo->query("
    SELECT s.id, s.nome, s.numero_titulo 
    FROM socios s 
    WHERE s.ativo = 1 
    ORDER BY s.nome
")->fetchAll();

// DEBUG - Verificar se encontrou sócios
// echo "Total de sócios: " . count($socios); // Descomente para testar

$msg_erro = '';
$msg_sucesso = '';

if ($_POST) {
    $espaco_id = $_POST['espaco_id'] ?? 0;
    $socio_id = $_POST['socio_id'] ?? 0;
    $data_reserva = $_POST['data_reserva'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    $quantidade_pessoas = $_POST['quantidade_pessoas'] ?? 1;
    $observacao = $_POST['observacao'] ?? '';
    
    if($espaco_id && $socio_id && $data_reserva && $hora_inicio && $hora_fim) {
        // Buscar valor do espaço
        $stmtEspaco = $pdo->prepare("SELECT valor_hora FROM espacos WHERE id = ?");
        $stmtEspaco->execute([$espaco_id]);
        $valor_hora = $stmtEspaco->fetchColumn();
        
        // Calcular valor
        $inicio = strtotime($hora_inicio);
        $fim = strtotime($hora_fim);
        $horas = ($fim - $inicio) / 3600;
        $valor_total = $horas * $valor_hora;
        $codigo = 'RES-' . date('Ymd') . '-' . rand(100, 999);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reservas (espaco_id, socio_id, data_reserva, hora_inicio, hora_fim, 
                                     quantidade_pessoas, valor_total, codigo_reserva, observacao, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada')
            ");
            $stmt->execute([
                $espaco_id, 
                $socio_id, 
                $data_reserva, 
                $hora_inicio, 
                $hora_fim, 
                $quantidade_pessoas, 
                $valor_total, 
                $codigo, 
                $observacao
            ]);
            
            $msg_sucesso = "✅ Reserva realizada!<br>Código: $codigo<br>Valor: R$ " . number_format($valor_total, 2, ',', '.');
            
            if(!isset($_POST['save_and_new'])) {
                echo "<script>setTimeout(function() { window.location.href = 'index.php'; }, 2000);</script>";
            }
        } catch(PDOException $e) {
            $msg_erro = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $msg_erro = "Preencha todos os campos obrigatórios";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Reserva - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-calendar-plus"></i> Nova Reserva</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if($msg_erro): ?>
                            <div class="alert alert-danger"><?= $msg_erro ?></div>
                        <?php endif; ?>
                        
                        <?php if($msg_sucesso): ?>
                            <div class="alert alert-success"><?= $msg_sucesso ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="fw-bold">Espaço</label>
                                <select name="espaco_id" class="form-select" required>
                                    <option value="">-- Selecione o espaço --</option>
                                    <?php foreach($espacos as $e): ?>
                                        <option value="<?= $e['id'] ?>">
                                            <?= htmlspecialchars($e['nome']) ?> - R$ <?= number_format($e['valor_hora'], 2, ',', '.') ?>/hora
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Sócio</label>
                                <select name="socio_id" class="form-select" required>
                                    <option value="">-- Selecione o sócio --</option>
                                    <?php foreach($socios as $s): ?>
                                        <option value="<?= $s['id'] ?>">
                                            <?= htmlspecialchars($s['nome']) ?> (Título: <?= $s['numero_titulo'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Data</label>
                                <input type="date" name="data_reserva" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Hora Início</label>
                                    <input type="time" name="hora_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Hora Fim</label>
                                    <input type="time" name="hora_fim" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Quantidade de Pessoas</label>
                                <input type="number" name="quantidade_pessoas" class="form-control" value="1" min="1">
                            </div>
                            
                            <div class="mb-3">
                                <label>Observação</label>
                                <textarea name="observacao" class="form-control" rows="2" placeholder="Informações adicionais..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Confirmar Reserva
                                </button>
                                <button type="submit" name="save_and_new" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> Salvar e Nova
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if(empty($socios)): ?>
    <div class="container mt-3">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            Nenhum sócio encontrado. <a href="/modules/socios/cadastrar.php">Cadastre um sócio</a> primeiro.
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
