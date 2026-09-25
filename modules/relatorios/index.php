<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('relatorios') or die('Acesso negado');

$totalSocios = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1")->fetchColumn();
$totalSociosPorTipo = $pdo->query("
    SELECT ts.nome, COUNT(*) as total
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.ativo = 1
    GROUP BY ts.nome
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$temDataNascimento = false;
try {
    $pdo->query("SELECT data_nascimento FROM socios LIMIT 1");
    $temDataNascimento = true;
} catch (PDOException $e) {}

$aniversariantesMes = [];
if ($temDataNascimento) {
    $stmt = $pdo->query("
        SELECT nome, cpf, DAY(data_nascimento) as dia
        FROM socios
        WHERE ativo = 1 AND data_nascimento IS NOT NULL AND MONTH(data_nascimento) = MONTH(CURRENT_DATE())
        ORDER BY DAY(data_nascimento)
    ");
    $aniversariantesMes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:#f4f7fb; }
        .hero { background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%); color:#fff; border-radius:22px; padding:24px; }
        .panel { border:none; border-radius:18px; box-shadow:0 8px 24px rgba(0,0,0,.08); }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid py-4">
    <div class="hero mb-4">
        <h1 class="h3 mb-1"><i class="fas fa-chart-bar me-2"></i>Relatórios do Clube</h1>
        <div>Visão geral de sócios, aniversariantes e exportações.</div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card panel h-100">
                <div class="card-header bg-white fw-semibold">Sócios por Tipo</div>
                <div class="card-body">
                    <canvas id="graficoSocios" height="200"></canvas>
                    <table class="table table-sm mt-3 mb-0">
                        <tbody>
                        <?php foreach ($totalSociosPorTipo as $tipo): ?>
                            <tr><td><?= htmlspecialchars($tipo['nome']) ?></td><td class="text-end"><?= (int)$tipo['total'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card panel h-100">
                <div class="card-header bg-white fw-semibold">Resumo Geral</div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6"><div class="border rounded p-3"><h3><?= (int)$totalSocios ?></h3><small>Total de Sócios</small></div></div>
                        <div class="col-6"><div class="border rounded p-3"><h3><?= count($totalSociosPorTipo) ?></h3><small>Tipos de Sócio</small></div></div>
                    </div>
                </div>
            </div>
            <?php if (!empty($aniversariantesMes)): ?>
            <div class="card panel mt-3">
                <div class="card-header bg-white fw-semibold">Aniversariantes do Mês</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($aniversariantesMes as $aniv): ?>
                            <li class="list-group-item d-flex justify-content-between"><span><?= htmlspecialchars($aniv['nome']) ?></span><span>Dia <?= (int)$aniv['dia'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card panel mt-4">
        <div class="card-header bg-white fw-semibold">Exportar Relatórios</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><a href="exportar.php?tipo=socios" class="btn btn-outline-primary w-100"><i class="fas fa-users"></i> Exportar Sócios</a></div>
                <div class="col-md-3"><a href="exportar.php?tipo=financeiro" class="btn btn-outline-success w-100"><i class="fas fa-dollar-sign"></i> Financeiro</a></div>
                <div class="col-md-3"><a href="exportar.php?tipo=historico_financeiro" class="btn btn-outline-dark w-100"><i class="fas fa-clipboard-list"></i> Histórico Financeiro</a></div>
                <div class="col-md-3"><a href="exportar.php?tipo=convites" class="btn btn-outline-info w-100"><i class="fas fa-ticket-alt"></i> Convites</a></div>
            </div>
        </div>
    </div>
</div>
<script>
const ctx = document.getElementById('graficoSocios').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($totalSociosPorTipo, 'nome')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($totalSociosPorTipo, 'total')) ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
        }]
    }
});
</script>
</body>
</html>
