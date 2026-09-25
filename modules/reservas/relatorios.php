<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$espaco_id = $_GET['espaco'] ?? 0;
$status = $_GET['status'] ?? 'todos';

// Buscar espaços para o filtro
$espacos = $pdo->query("SELECT id, nome FROM espacos ORDER BY nome")->fetchAll();

// Construir query
$sql = "
    SELECT r.*, e.nome as espaco_nome, e.tipo, s.nome as socio_nome, s.numero_titulo
    FROM reservas r
    JOIN espacos e ON r.espaco_id = e.id
    JOIN socios s ON r.socio_id = s.id
    WHERE DATE(r.data_reserva) BETWEEN ? AND ?
";

$params = [$data_inicio, $data_fim];

if($espaco_id > 0) {
    $sql .= " AND r.espaco_id = ?";
    $params[] = $espaco_id;
}

if($status != 'todos') {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY r.data_reserva DESC, r.hora_inicio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

// Estatísticas
$totalReservas = count($reservas);
$totalValor = array_sum(array_column($reservas, 'valor_total'));
$totalConfirmadas = count(array_filter($reservas, fn($r) => $r['status'] == 'confirmada'));
$totalCanceladas = count(array_filter($reservas, fn($r) => $r['status'] == 'cancelada'));
$totalHoras = 0;

foreach($reservas as $r) {
    $inicio = strtotime($r['hora_inicio']);
    $fim = strtotime($r['hora_fim']);
    $totalHoras += ($fim - $inicio) / 3600;
}

// Reservas por espaço
$reservasPorEspaco = [];
foreach($reservas as $r) {
    if(!isset($reservasPorEspaco[$r['espaco_nome']])) {
        $reservasPorEspaco[$r['espaco_nome']] = 0;
    }
    $reservasPorEspaco[$r['espaco_nome']]++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-chart-bar"></i> Relatórios de Reservas</h4>
            </div>
            <div class="card-body">
                
                <!-- Filtros -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Espaço</label>
                        <select name="espaco" class="form-select">
                            <option value="0">Todos os espaços</option>
                            <?php foreach($espacos as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= $espaco_id == $e['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="todos" <?= $status == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="confirmada" <?= $status == 'confirmada' ? 'selected' : '' ?>>Confirmadas</option>
                            <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                            <option value="cancelada" <?= $status == 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
                            <option value="finalizada" <?= $status == 'finalizada' ? 'selected' : '' ?>>Finalizadas</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="exportar_relatorio.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>&espaco=<?= $espaco_id ?>&status=<?= $status ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </form>
                
                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-info text-center">
                            <h6>Total de Reservas</h6>
                            <h3><?= $totalReservas ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-success text-center">
                            <h6>Valor Total</h6>
                            <h3>R$ <?= number_format($totalValor, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-primary text-center">
                            <h6>Total de Horas</h6>
                            <h3><?= number_format($totalHoras, 1) ?>h</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning text-center">
                            <h6>Média por Reserva</h6>
                            <h3>R$ <?= number_format($totalReservas > 0 ? $totalValor / $totalReservas : 0, 2, ',', '.') ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <canvas id="graficoStatus" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="graficoEspacos" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Tabela de Reservas -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Espaço</th>
                                <th>Sócio</th>
                                <th>Horário</th>
                                <th>Horas</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reservas as $r): 
                                $inicio = strtotime($r['hora_inicio']);
                                $fim = strtotime($r['hora_fim']);
                                $horas = ($fim - $inicio) / 3600;
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['data_reserva'])) ?></td>
                                <td><?= htmlspecialchars($r['espaco_nome']) ?> (<?= ucfirst($r['tipo']) ?>)</td>
                                <td><?= htmlspecialchars($r['socio_nome']) ?> <br><small><?= $r['numero_titulo'] ?></small></td>
                                <td><?= substr($r['hora_inicio'], 0, 5) ?> - <?= substr($r['hora_fim'], 0, 5) ?></td>
                                <td><?= number_format($horas, 1) ?>h</td>
                                <td>R$ <?= number_format($r['valor_total'], 2, ',', '.') ?> </td>
                                <td>
                                    <?php
                                    $badgeClass = match($r['status']) {
                                        'confirmada' => 'bg-success',
                                        'pendente' => 'bg-warning',
                                        'cancelada' => 'bg-danger',
                                        'finalizada' => 'bg-secondary',
                                        default => 'bg-info'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($r['status']) ?></span>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                            <?php if(empty($reservas)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                                    Nenhuma reserva encontrada no período
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Gráfico de Status
        const statusData = {
            confirmadas: <?= $totalConfirmadas ?>,
            pendentes: <?= $totalReservas - $totalConfirmadas - $totalCanceladas ?>,
            canceladas: <?= $totalCanceladas ?>
        };
        
        new Chart(document.getElementById('graficoStatus'), {
            type: 'doughnut',
            data: {
                labels: ['Confirmadas', 'Pendentes', 'Canceladas'],
                datasets: [{
                    data: [statusData.confirmadas, statusData.pendentes, statusData.canceladas],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
        
        // Gráfico de Espaços
        const espacosNomes = <?= json_encode(array_keys($reservasPorEspaco)) ?>;
        const espacosQuantidades = <?= json_encode(array_values($reservasPorEspaco)) ?>;
        
        new Chart(document.getElementById('graficoEspacos'), {
            type: 'bar',
            data: {
                labels: espacosNomes,
                datasets: [{
                    label: 'Número de Reservas',
                    data: espacosQuantidades,
                    backgroundColor: '#3498db'
                }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
    </script>
</body>
</html>