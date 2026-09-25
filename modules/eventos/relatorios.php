<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$tipo = $_GET['tipo'] ?? 'todos';

// Buscar eventos no período
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id AND status = 'confirmada') as inscritos,
        (SELECT COUNT(*) FROM inscricoes_eventos WHERE evento_id = e.id AND presenca_confirmada = 1) as presentes
        FROM eventos e
        WHERE DATE(e.data_inicio) BETWEEN ? AND ?";

if($tipo != 'todos') {
    $sql .= " AND e.tipo = ?";
}

$sql .= " ORDER BY e.data_inicio DESC";

$stmt = $pdo->prepare($sql);
if($tipo != 'todos') {
    $stmt->execute([$data_inicio, $data_fim, $tipo]);
} else {
    $stmt->execute([$data_inicio, $data_fim]);
}
$eventos = $stmt->fetchAll();

// Estatísticas
$totalEventos = count($eventos);
$totalInscricoes = array_sum(array_column($eventos, 'inscritos'));
$totalPresentes = array_sum(array_column($eventos, 'presentes'));
$taxaPresenca = $totalInscricoes > 0 ? ($totalPresentes / $totalInscricoes) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-chart-bar"></i> Relatórios de Eventos</h4>
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
                        <label>Tipo de Evento</label>
                        <select name="tipo" class="form-select">
                            <option value="todos" <?= $tipo == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="festa" <?= $tipo == 'festa' ? 'selected' : '' ?>>Festa</option>
                            <option value="campeonato" <?= $tipo == 'campeonato' ? 'selected' : '' ?>>Campeonato</option>
                            <option value="palestra" <?= $tipo == 'palestra' ? 'selected' : '' ?>>Palestra</option>
                            <option value="curso" <?= $tipo == 'curso' ? 'selected' : '' ?>>Curso</option>
                            <option value="reuniao" <?= $tipo == 'reuniao' ? 'selected' : '' ?>>Reunião</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
                
                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="alert alert-info text-center">
                            <h6>Total de Eventos</h6>
                            <h3><?= $totalEventos ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-success text-center">
                            <h6>Total de Inscrições</h6>
                            <h3><?= $totalInscricoes ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-primary text-center">
                            <h6>Total de Presentes</h6>
                            <h3><?= $totalPresentes ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning text-center">
                            <h6>Taxa de Presença</h6>
                            <h3><?= number_format($taxaPresenca, 1) ?>%</h3>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <canvas id="graficoInscricoes" height="250"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="graficoPorTipo" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Tabela de Eventos -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Evento</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Inscritos</th>
                                <th>Presentes</th>
                                <th>Taxa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($eventos as $evento): 
                                $taxa = $evento['inscritos'] > 0 ? ($evento['presentes'] / $evento['inscritos']) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($evento['titulo']) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($evento['data_inicio'])) ?></td>
                                <td><?= ucfirst($evento['tipo']) ?></td>
                                <td class="text-center"><?= $evento['inscritos'] ?> / <?= $evento['capacidade'] ?: '∞' ?></td>
                                <td class="text-center"><?= $evento['presentes'] ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" style="width: <?= $taxa ?>%">
                                            <?= number_format($taxa, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match($evento['status']) {
                                        'ativo' => 'bg-success',
                                        'finalizado' => 'bg-secondary',
                                        'cancelado' => 'bg-danger',
                                        default => 'bg-warning'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($evento['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botões de Exportação -->
                <div class="text-center mt-4">
                    <a href="exportar_relatorio.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>&tipo=<?= $tipo ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Exportar para Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Gráfico de Inscrições vs Presentes
        const eventosNomes = <?= json_encode(array_column($eventos, 'titulo')) ?>;
        const inscritos = <?= json_encode(array_column($eventos, 'inscritos')) ?>;
        const presentes = <?= json_encode(array_column($eventos, 'presentes')) ?>;
        
        new Chart(document.getElementById('graficoInscricoes'), {
            type: 'bar',
            data: {
                labels: eventosNomes,
                datasets: [
                    { label: 'Inscritos', data: inscritos, backgroundColor: '#3498db' },
                    { label: 'Presentes', data: presentes, backgroundColor: '#2ecc71' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
        
        // Gráfico por Tipo
        const tipos = ['festa', 'campeonato', 'palestra', 'curso', 'reuniao'];
        const totalPorTipo = tipos.map(tipo => 
            <?= json_encode(array_count_values(array_column($eventos, 'tipo'))) ?>[tipo] || 0
        );
        
        new Chart(document.getElementById('graficoPorTipo'), {
            type: 'pie',
            data: {
                labels: ['Festa', 'Campeonato', 'Palestra', 'Curso', 'Reunião'],
                datasets: [{ data: totalPorTipo, backgroundColor: ['#e74c3c', '#f1c40f', '#3498db', '#2ecc71', '#9b59b6'] }]
            },
            options: { responsive: true, maintainAspectRatio: true }
        });
    </script>
</body>
</html>