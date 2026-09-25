<?php
require_once 'config.php';
require_once 'includes/auth.php';

// ============================================
// ESTATÍSTICAS GERAIS
// ============================================

$totalSocios = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1")->fetchColumn();
$totalSociosInativos = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 0")->fetchColumn();
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
$totalTerceiros = $pdo->query("SELECT COUNT(*) FROM terceiros WHERE ativo = 1")->fetchColumn();

// Sócios por tipo (para o gráfico)
$resultSociosPorTipo = $pdo->query("
    SELECT ts.nome, ts.cor_carteirinha, COUNT(s.id) as total
    FROM tipos_socio ts
    LEFT JOIN socios s ON s.tipo_socio_id = ts.id AND s.ativo = 1
    GROUP BY ts.id
    ORDER BY total DESC
");
$sociosPorTipo = $resultSociosPorTipo instanceof PDOStatement ? $resultSociosPorTipo->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================
// CONVITES DO MÊS
// ============================================
$mesAtual = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(c.total_convites), 0) as total, 
           COALESCE(SUM(c.convites_utilizados), 0) as usados
    FROM convites_familia c
    WHERE c.mes_referencia = ?
");
$stmt->execute([$mesAtual]);
$convites = $stmt->fetch(PDO::FETCH_ASSOC);
$totalConvites = $convites['total'] ?? 0;
$convitesUsados = $convites['usados'] ?? 0;
$convitesDisponiveis = $totalConvites - $convitesUsados;

// ============================================
// ANIVERSARIANTES DO MÊS (CORRIGIDO)
// ============================================
$aniversariantes = [];
$proximosAniversariantes = [];

try {
    // Verificar se a coluna data_nascimento existe
    $checkColumn = $pdo->query("SHOW COLUMNS FROM socios LIKE 'data_nascimento'");
    if($checkColumn->rowCount() > 0) {
        
        // Aniversariantes do mês atual
        $stmtAniversario = $pdo->prepare("
            SELECT id, nome, data_nascimento, foto, telefone, email_socio,
                   DAY(data_nascimento) as dia,
                   TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) as idade,
                   DATE_FORMAT(data_nascimento, '%d/%m') as data_aniversario
            FROM socios
            WHERE ativo = 1 
            AND data_nascimento IS NOT NULL
            AND data_nascimento != '0000-00-00'
            AND MONTH(data_nascimento) = MONTH(CURRENT_DATE())
            ORDER BY DAY(data_nascimento)
            LIMIT 10
        ");
        $stmtAniversario->execute();
        $aniversariantes = $stmtAniversario->fetchAll(PDO::FETCH_ASSOC);
        
        // Próximos aniversariantes (próximos 30 dias)
        $stmtProximos = $pdo->prepare("
            SELECT id, nome, data_nascimento, foto,
                   DAY(data_nascimento) as dia,
                   DATE_FORMAT(data_nascimento, '%d/%m') as data_aniversario
            FROM socios
            WHERE ativo = 1 
            AND data_nascimento IS NOT NULL
            AND data_nascimento != '0000-00-00'
            AND DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN DATE_FORMAT(CURRENT_DATE, '%m-%d') 
                AND DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), '%m-%d')
            ORDER BY DATE_FORMAT(data_nascimento, '%m-%d')
            LIMIT 10
        ");
        $stmtProximos->execute();
        $proximosAniversariantes = $stmtProximos->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Criar a coluna se não existir
        $pdo->exec("ALTER TABLE socios ADD COLUMN data_nascimento DATE NULL");
    }
} catch(PDOException $e) {
    $aniversariantes = [];
    $proximosAniversariantes = [];
}

// ============================================
// ÚLTIMOS SÓCIOS CADASTRADOS
// ============================================
$resultUltimosSocios = $pdo->query("
    SELECT s.*, ts.nome as tipo_socio, ts.cor_carteirinha
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.ativo = 1
    ORDER BY s.id DESC
    LIMIT 8
");
$ultimosSocios = $resultUltimosSocios instanceof PDOStatement ? $resultUltimosSocios->fetchAll(PDO::FETCH_ASSOC) : [];

// ============================================
// FINANCEIRO (se existir)
// ============================================
$inadimplentes = 0;
$totalMensalidades = 0;
try {
    $inadimplentes = $pdo->query("
        SELECT COUNT(DISTINCT socio_id) 
        FROM lancamentos 
        WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE()
    ")->fetchColumn();
    
    $totalMensalidades = $pdo->query("
        SELECT COALESCE(SUM(valor), 0)
        FROM lancamentos 
        WHERE status = 'pago' 
        AND MONTH(data_pagamento) = MONTH(CURRENT_DATE())
    ")->fetchColumn();
} catch(PDOException $e) {
    $inadimplentes = 0;
    $totalMensalidades = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --info: #4299e1;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-icon {
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 3.5rem;
            opacity: 0.15;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        .card-modern {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .card-modern:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .card-header-modern {
            background: transparent;
            border-bottom: 2px solid #e9ecef;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 20px;
            padding: 1.8rem;
            color: white;
            margin-bottom: 1.5rem;
        }
        
        .quick-action-btn {
            background: white;
            border: none;
            border-radius: 15px;
            padding: 1rem;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .quick-action-btn i {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .socio-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .socio-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .birthday-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .aniversariante-item {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .aniversariante-item:hover {
            background-color: #fff3cd;
            border-left-color: #f39c12;
            transform: translateX(3px);
        }
        
        .alert-custom {
            border-radius: 15px;
            border: none;
        }
        
        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }
            .stat-icon {
                font-size: 2rem;
            }
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/menu.php'; ?>
    
    <div class="container-fluid px-4 py-3">
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-circle fa-3x"></i>
                        </div>
                        <div>
                            <h2 class="mb-1">Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!</h2>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                <?= date('l, d \\d\\e F \\d\\e Y', strtotime('today')) ?>
                                | <i class="fas fa-clock me-1"></i> 
                                <span id="relogio"></span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end d-none d-md-block">
                    <i class="fas fa-trophy fa-4x opacity-50"></i>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-primary text-white" onclick="window.location='modules/socios/index.php'">
                    <div class="card-body">
                        <div class="stat-label">Total de Sócios</div>
                        <div class="stat-value"><?= number_format($totalSocios, 0, ',', '.') ?></div>
                        <small><i class="fas fa-user-plus"></i> Ativos</small>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-success text-white" onclick="window.location='modules/convites/index.php'">
                    <div class="card-body">
                        <div class="stat-label">Convites Disponíveis</div>
                        <div class="stat-value"><?= number_format($convitesDisponiveis, 0, ',', '.') ?></div>
                        <small><i class="fas fa-ticket-alt"></i> Este mês</small>
                        <i class="fas fa-ticket-alt stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white" onclick="window.location='modules/financeiro/index.php'">
                    <div class="card-body">
                        <div class="stat-label">Inadimplentes</div>
                        <div class="stat-value"><?= number_format($inadimplentes, 0, ',', '.') ?></div>
                        <small><i class="fas fa-exclamation-triangle"></i> Em débito</small>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white" onclick="window.location='modules/terceiros/index.php'">
                    <div class="card-body">
                        <div class="stat-label">Serviços Ativos</div>
                        <div class="stat-value"><?= number_format($totalTerceiros, 0, ',', '.') ?></div>
                        <small><i class="fas fa-building"></i> Parceiros</small>
                        <i class="fas fa-building stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Main Content -->
        <div class="row g-3 mb-4">
            <!-- Gráfico de Sócios -->
            <div class="col-xl-5 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-chart-pie text-primary me-2"></i> 
                        Sócios por Tipo
                    </div>
                    <div class="card-body">
                        <canvas id="graficoSocios" height="250"></canvas>
                        <div class="mt-3">
                            <?php foreach($sociosPorTipo as $tipo): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span style="display: inline-block; width: 12px; height: 12px; background-color: <?= $tipo['cor_carteirinha'] ?>; border-radius: 3px; margin-right: 8px;"></span>
                                    <span><?= htmlspecialchars($tipo['nome']) ?></span>
                                </div>
                                <span class="badge bg-secondary rounded-pill"><?= $tipo['total'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Leitor de Carteirinha -->
            <div class="col-xl-7 col-lg-12">
                <div class="card card-modern h-100">
                    <div class="card-header-modern">
                        <i class="fas fa-id-card text-success me-2"></i> 
                        Validação de Carteirinha
                        <small class="text-muted float-end">Leia o QR Code ou digite o código</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-qrcode"></i></span>
                                    <input type="text" id="leitorCodigo" class="form-control" placeholder="Digite o número do título ou leia o QR Code" autocomplete="off">
                                    <button class="btn btn-primary" onclick="lerCarteirinha()">
                                        <i class="fas fa-search"></i> Validar
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <div id="resultadoValidacao" style="display: none;"></div>
                            </div>
                            <div class="col-12 text-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Teste com o número do título do sócio (ex: 001, 99999)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-3 mb-4">
            <!-- Últimos Sócios -->
            <div class="col-xl-7 col-lg-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-users text-primary me-2"></i> 
                        Últimos Sócios Cadastrados
                        <a href="/modules/socios/index.php" class="float-end text-decoration-none">Ver todos <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Foto</th>
                                        <th>Nome</th>
                                        <th>Nº Título</th>
                                        <th>Tipo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ultimosSocios as $socio): ?>
                                    <tr class="socio-item" onclick="window.location='modules/socios/editar.php?id=<?= $socio['id'] ?>'">
                                        <td style="width: 60px;">
                                            <?php if($socio['foto'] && file_exists('assets/uploads/' . $socio['foto'])): ?>
                                                <img src="assets/uploads/<?= $socio['foto'] ?>" width="40" height="40" class="rounded-circle">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($socio['nome']) ?></strong></td>
                                        <td><?= $socio['numero_titulo'] ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= $socio['cor_carteirinha'] ?>; color: #000;">
                                                <?= $socio['tipo_socio'] ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation()">
                                            <a href="/modules/carteirinha/gerar.php?id=<?= $socio['id'] ?>" class="btn btn-sm btn-info" target="_blank" title="Carteirinha">
                                                <i class="fas fa-id-card"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($ultimosSocios)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                                            Nenhum sócio cadastrado ainda.
                                            <a href="/modules/socios/cadastrar.php" class="d-block mt-2">Cadastrar primeiro sócio</a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Aniversariantes e Ações Rápidas -->
            <div class="col-xl-5 col-lg-12">
                
                <!-- Aniversariantes do Mês -->
                <div class="card card-modern mb-3 <?= !empty($aniversariantes) ? 'birthday-card' : '' ?>">
                    <div class="card-header-modern <?= !empty($aniversariantes) ? 'border-light' : '' ?>">
                        <i class="fas fa-birthday-cake me-2"></i> 
                        Aniversariantes do Mês
                        <span class="float-end badge bg-light text-dark"><?= date('F', strtotime('today')) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if(empty($aniversariantes)): ?>
                            <p class="text-center mb-0">
                                <i class="fas fa-calendar-alt fa-2x mb-2 d-block opacity-50"></i>
                                Nenhum aniversariante neste mês
                            </p>
                        <?php else: ?>
                            <div class="list-group list-group-flush bg-transparent">
                                <?php foreach($aniversariantes as $aniv): ?>
                                <div class="list-group-item bg-transparent border-0 ps-0 d-flex justify-content-between align-items-center aniversariante-item">
                                    <div>
                                        <strong><?= htmlspecialchars($aniv['nome']) ?></strong>
                                        <br>
                                        <small>
                                            <i class="fas fa-calendar"></i> <?= $aniv['data_aniversario'] ?>
                                            <?php if($aniv['idade']): ?> | <?= $aniv['idade'] ?> anos<?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if(!empty($aniv['telefone'])): ?>
                                    <a href="tel:<?= $aniv['telefone'] ?>" class="btn btn-sm btn-light rounded-circle">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($proximosAniversariantes)): ?>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Próximos aniversariantes:
                            <?php 
                            $nomes = [];
                            foreach($proximosAniversariantes as $p) {
                                $nomes[] = htmlspecialchars($p['nome']) . " ({$p['data_aniversario']})";
                            }
                            echo implode(', ', $nomes);
                            ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <i class="fas fa-bolt text-warning me-2"></i> 
                        Ações Rápidas
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="/modules/socios/cadastrar.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-user-plus text-primary fa-2x"></i>
                                    <small>Novo Sócio</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/modules/carteirinha/gerar.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-id-card text-success fa-2x"></i>
                                    <small>Carteirinha</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/modules/convites/index.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-ticket-alt text-warning fa-2x"></i>
                                    <small>Convites</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/modules/relatorios/index.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-chart-bar text-info fa-2x"></i>
                                    <small>Relatórios</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/modules/eventos/index.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-calendar-alt text-danger fa-2x"></i>
                                    <small>Eventos</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="/modules/reservas/nova.php" class="quick-action-btn d-block text-decoration-none text-dark">
                                    <i class="fas fa-calendar-check text-success fa-2x"></i>
                                    <small>Reservas</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rodapé com informações -->
        <footer class="text-center text-muted py-3 mt-3 border-top">
            <small>
                <i class="fas fa-code-branch"></i> Sistema de Clube v2.0 | 
                <i class="fas fa-database"></i> <?= date('Y') ?> | 
                <i class="fas fa-users"></i> Total de sócios: <?= $totalSocios ?>
            </small>
        </footer>
    </div>
    
    <script>
    // ============================================
    // RELÓGIO EM TEMPO REAL
    // ============================================
    function atualizarRelogio() {
        const agora = new Date();
        const horas = String(agora.getHours()).padStart(2, '0');
        const minutos = String(agora.getMinutes()).padStart(2, '0');
        const segundos = String(agora.getSeconds()).padStart(2, '0');
        const relogio = document.getElementById('relogio');
        if(relogio) relogio.innerHTML = `${horas}:${minutos}:${segundos}`;
    }
    setInterval(atualizarRelogio, 1000);
    atualizarRelogio();
    
    // ============================================
    // GRÁFICO DE SÓCIOS POR TIPO
    // ============================================
    const tipos = <?= json_encode(array_column($sociosPorTipo, 'nome')) ?>;
    const totais = <?= json_encode(array_column($sociosPorTipo, 'total')) ?>;
    const cores = <?= json_encode(array_column($sociosPorTipo, 'cor_carteirinha')) ?>;
    
    const ctx = document.getElementById('graficoSocios');
    if(ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: tipos,
                datasets: [{
                    data: totais,
                    backgroundColor: cores,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ============================================
    // VALIDAÇÃO DE CARTEIRINHA
    // ============================================
    function lerCarteirinha() {
        const codigo = document.getElementById('leitorCodigo').value.trim();
        if(!codigo) {
            mostrarAlerta('Digite ou leia o código da carteirinha', 'warning');
            return;
        }
        
        const div = document.getElementById('resultadoValidacao');
        div.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Validando...</div>';
        div.style.display = 'block';
        
        fetch(`modules/carteirinha/validar.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'ok') {
                    div.innerHTML = `
                        <div class="alert alert-success alert-custom">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle fa-3x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <strong class="fs-5">${data.nome}</strong><br>
                                    <span class="badge bg-success mt-1">${data.status_text}</span><br>
                                    <small class="mt-2 d-block">${data.mensagem}</small>
                                    ${data.convites !== undefined ? `<small><i class="fas fa-ticket-alt"></i> Convites: ${data.convites}</small>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    div.innerHTML = `
                        <div class="alert alert-danger alert-custom">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-times-circle fa-3x"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <strong class="fs-5">Acesso Negado!</strong><br>
                                    ${data.mensagem}
                                    ${data.codigo_recebido ? `<br><small>Código: ${data.codigo_recebido}</small>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                setTimeout(() => {
                    div.style.display = 'none';
                    document.getElementById('leitorCodigo').value = '';
                }, 5000);
            })
            .catch(error => {
                div.innerHTML = `<div class="alert alert-danger">Erro na validação: ${error.message}</div>`;
                setTimeout(() => div.style.display = 'none', 3000);
            });
    }
    
    function mostrarAlerta(mensagem, tipo) {
        const div = document.getElementById('resultadoValidacao');
        div.innerHTML = `<div class="alert alert-${tipo}">${mensagem}</div>`;
        div.style.display = 'block';
        setTimeout(() => div.style.display = 'none', 3000);
    }
    
    // Permitir Enter no campo de leitura
    document.getElementById('leitorCodigo').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            lerCarteirinha();
        }
    });
    
    // Auto-foco no campo de leitura
    document.getElementById('leitorCodigo').focus();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
