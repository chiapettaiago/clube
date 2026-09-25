<?php
require_once 'config.php';
require_once 'includes/auth.php';

$totalSocios = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1")->fetchColumn();
$totalSociosInativos = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 0")->fetchColumn();
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
$totalTerceiros = $pdo->query("SELECT COUNT(*) FROM terceiros WHERE ativo = 1")->fetchColumn();

$sociosPorTipo = $pdo->query("
    SELECT ts.nome, ts.cor_carteirinha, COUNT(s.id) as total
    FROM tipos_socio ts
    LEFT JOIN socios s ON s.tipo_socio_id = ts.id AND s.ativo = 1
    GROUP BY ts.id
    ORDER BY total DESC
")->fetchAll();

$mesAtual = date('Y-m-01');
$convitesDisponiveis = 0;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'convites_familia'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_convites), 0) as total,
                   COALESCE(SUM(convites_utilizados), 0) as usados
            FROM convites_familia
            WHERE mes_referencia = ?
        ");
        $stmt->execute([$mesAtual]);
        $convites = $stmt->fetch();
        $convitesDisponiveis = (int)$convites['total'] - (int)$convites['usados'];
    }
} catch (PDOException $e) {
    $convitesDisponiveis = 0;
}

$aniversariantes = [];
$proximosAniversariantes = [];
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM socios LIKE 'data_nascimento'");
    if ($checkColumn->rowCount() > 0) {
        $mesAtualNum = date('m');
        $queryAniversariantes = $pdo->prepare("
            SELECT id, nome, data_nascimento, foto, telefone, email_socio,
                   DAY(data_nascimento) as dia,
                   DATE_FORMAT(data_nascimento, '%d/%m') as data_aniversario,
                   TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) as idade
            FROM socios
            WHERE ativo = 1
              AND data_nascimento IS NOT NULL
              AND data_nascimento != '0000-00-00'
              AND MONTH(data_nascimento) = ?
            ORDER BY DAY(data_nascimento)
            LIMIT 10
        ");
        $queryAniversariantes->execute([$mesAtualNum]);
        $aniversariantes = $queryAniversariantes->fetchAll() ?: [];
    }
} catch (PDOException $e) {
    $aniversariantes = [];
}

$ultimosSociosRecentes = [];
try {
    $ultimosSociosRecentes = $pdo->query("
        SELECT s.id, s.nome, s.telefone, s.email_socio, s.data_cadastro, ts.nome AS tipo_socio, ts.cor_carteirinha
        FROM socios s
        LEFT JOIN tipos_socio ts ON ts.id = s.tipo_socio_id
        WHERE s.ativo = 1
        ORDER BY s.data_cadastro DESC, s.id DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $ultimosSociosRecentes = $pdo->query("
        SELECT s.id, s.nome, s.telefone, s.email_socio, ts.nome AS tipo_socio, ts.cor_carteirinha
        FROM socios s
        LEFT JOIN tipos_socio ts ON ts.id = s.tipo_socio_id
        WHERE s.ativo = 1
        ORDER BY s.id DESC
        LIMIT 5
    ")->fetchAll();
}

$inadimplentes = 0;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'lancamentos'");
    if ($checkTable->rowCount() > 0) {
        $inadimplentes = $pdo->query("
            SELECT COUNT(DISTINCT socio_id)
            FROM lancamentos
            WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE()
        ")->fetchColumn();
    }
} catch (PDOException $e) {
    $inadimplentes = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary:#667eea; --secondary:#764ba2; }
        body { background:#f0f2f5; font-family:'Segoe UI', sans-serif; }
        .stat-card { border:none; border-radius:20px; transition:.3s; overflow:hidden; cursor:pointer; }
        .stat-card:hover { transform:translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,.1); }
        .stat-icon { position:absolute; right:20px; bottom:20px; font-size:3.5rem; opacity:.15; }
        .stat-label { font-size:.85rem; text-transform:uppercase; letter-spacing:1px; opacity:.9; }
        .stat-value { font-size:2.2rem; font-weight:700; margin:0; }
        .welcome-banner { background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%); border-radius:20px; padding:1.8rem; color:#fff; margin-bottom:1.5rem; }
        .quick-action-btn { background:#fff; border-radius:15px; padding:1rem; transition:.3s; text-align:center; text-decoration:none; color:#333; display:block; border:1px solid #e9ecef; }
        .quick-action-btn:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(0,0,0,.1); }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    <div class="container-fluid px-4 py-3">
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!</h2>
                    <p class="mb-0 opacity-75"><?= date('d/m/Y') ?> | <span id="relogio"></span></p>
                </div>
                <div class="col-md-4 text-end"><i class="fas fa-trophy fa-4x opacity-50"></i></div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-primary text-white" onclick="window.location='modules/socios/index.php'">
                    <div class="card-body position-relative">
                        <div class="stat-label">Total de Sócios</div>
                        <div class="stat-value"><?= number_format($totalSocios, 0, ',', '.') ?></div>
                        <small>Ativos</small>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-success text-white" onclick="window.location='modules/convites/index.php'">
                    <div class="card-body position-relative">
                        <div class="stat-label">Convites Disponíveis</div>
                        <div class="stat-value"><?= number_format($convitesDisponiveis, 0, ',', '.') ?></div>
                        <small>Este mês</small>
                        <i class="fas fa-ticket-alt stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white" onclick="window.location='modules/financeiro/index.php'">
                    <div class="card-body position-relative">
                        <div class="stat-label">Inadimplentes</div>
                        <div class="stat-value"><?= number_format($inadimplentes, 0, ',', '.') ?></div>
                        <small>Em débito</small>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white" onclick="window.location='modules/terceiros/index.php'">
                    <div class="card-body position-relative">
                        <div class="stat-label">Serviços Ativos</div>
                        <div class="stat-value"><?= number_format($totalTerceiros, 0, ',', '.') ?></div>
                        <small>Parceiros</small>
                        <i class="fas fa-building stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-5 col-lg-12">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-chart-pie me-2"></i> Sócios por Tipo
                    </div>
                    <div class="card-body">
                        <canvas id="graficoSocios" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-7 col-lg-12">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-id-card me-2"></i> Leitor de Carteirinha
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" id="leitorCodigo" class="form-control form-control-lg" placeholder="Digite o ID, número do título ou leia o QR Code" autocomplete="off">
                            <button class="btn btn-primary btn-lg" onclick="lerCarteirinha()"><i class="fas fa-search"></i> Validar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-user-plus me-2"></i> Últimos Sócios Cadastrados
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Contato</th>
                                        <th>Cadastro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ultimosSociosRecentes as $socio): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($socio['nome']) ?></td>
                                            <td>
                                                <span class="badge" style="background:<?= htmlspecialchars($socio['cor_carteirinha'] ?: '#6c757d') ?>">
                                                    <?= htmlspecialchars($socio['tipo_socio'] ?? 'Sem tipo') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($socio['telefone'] ?: ($socio['email_socio'] ?: '-')) ?></td>
                                            <td><?= !empty($socio['data_cadastro']) ? date('d/m/Y', strtotime($socio['data_cadastro'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-bolt me-2"></i> Acesso Rápido
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6"><a class="quick-action-btn" href="modules/socios/cadastrar.php"><i class="fas fa-user-plus fa-2x mb-2 text-primary"></i><div>Novo Sócio</div></a></div>
                            <div class="col-6"><a class="quick-action-btn" href="modules/carteirinha/configurar.php"><i class="fas fa-palette fa-2x mb-2 text-success"></i><div>Configurar Carteirinhas</div></a></div>
                            <div class="col-6"><a class="quick-action-btn" href="modules/convites/index.php"><i class="fas fa-ticket-alt fa-2x mb-2 text-danger"></i><div>Convites</div></a></div>
                            <div class="col-6"><a class="quick-action-btn" href="modules/financeiro/index.php"><i class="fas fa-dollar-sign fa-2x mb-2 text-warning"></i><div>Financeiro</div></a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($aniversariantes)): ?>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-birthday-cake me-2"></i> Aniversariantes do Mês
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach(array_slice($aniversariantes, 0, 4) as $aniv): ?>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <strong><?= htmlspecialchars($aniv['nome']) ?></strong><br>
                                        <small><?= htmlspecialchars($aniv['data_aniversario']) ?> - <?= (int)$aniv['idade'] ?> anos</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    const tipos = <?= json_encode(array_column($sociosPorTipo, 'nome')) ?>;
    const totais = <?= json_encode(array_column($sociosPorTipo, 'total')) ?>;
    const cores = <?= json_encode(array_column($sociosPorTipo, 'cor_carteirinha')) ?>;
    const ctx = document.getElementById('graficoSocios').getContext('2d');
    new Chart(ctx, { type:'doughnut', data:{ labels:tipos, datasets:[{ data:totais, backgroundColor:cores, borderWidth:0 }] }, options:{ responsive:true, plugins:{ legend:{ position:'bottom' } } } });

    function atualizarRelogio() {
        const agora = new Date();
        document.getElementById('relogio').textContent = agora.toLocaleTimeString('pt-BR');
    }
    setInterval(atualizarRelogio, 1000);
    atualizarRelogio();

    function lerCarteirinha() {
        const codigo = document.getElementById('leitorCodigo').value;
        if (!codigo) { alert('Digite ou leia o código da carteirinha'); return; }
        window.location = 'modules/carteirinha/validar.php?codigo=' + encodeURIComponent(codigo);
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
