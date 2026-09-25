<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$referenciaAtual = date('Y-m-01');
$mesRef = date('m/Y', strtotime($referenciaAtual));

$totalLancamentos = 0;
$totalPendentes = 0;
$totalPagos = 0;
$totalAReceber = 0.0;

try {
    $totalLancamentos = (int)$pdo->query("SELECT COUNT(*) FROM lancamentos")->fetchColumn();
    $totalPendentes = (int)$pdo->query("SELECT COUNT(*) FROM lancamentos WHERE status = 'pendente'")->fetchColumn();
    $totalPagos = (int)$pdo->query("SELECT COUNT(*) FROM lancamentos WHERE status = 'pago'")->fetchColumn();
    $totalAReceber = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM lancamentos WHERE status = 'pendente'")->fetchColumn();
} catch (PDOException $e) {}

$inadimplentes = 0;
try {
    $inadimplentes = (int)$pdo->query("
        SELECT COUNT(DISTINCT socio_id)
        FROM lancamentos
        WHERE status = 'pendente'
          AND data_vencimento < CURRENT_DATE()
    ")->fetchColumn();
} catch (PDOException $e) {}

$ultimasMovimentacoes = [];
try {
    $stmt = $pdo->query("
        SELECT l.id, l.descricao, l.valor, l.status, l.data_vencimento, s.nome AS socio_nome
        FROM lancamentos l
        LEFT JOIN socios s ON s.id = l.socio_id
        ORDER BY l.id DESC
        LIMIT 8
    ");
    $ultimasMovimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color:#fff; border-radius:24px; padding:24px; box-shadow:0 18px 40px rgba(15,23,42,.18); }
        .finance-card { border:0; border-radius:20px; box-shadow:0 10px 28px rgba(15,23,42,.08); }
        .action-card { border:1px solid #e5e7eb; border-radius:18px; text-decoration:none; color:#111827; background:#fff; display:block; padding:18px; transition:.2s; height:100%; }
        .action-card:hover { transform: translateY(-3px); box-shadow:0 10px 24px rgba(0,0,0,.08); color:#111827; }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid py-4">
    <div class="hero mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-2"><i class="fas fa-dollar-sign me-2"></i>Financeiro</h1>
            <div>Painel de lançamentos, inadimplência, mensalidades e relatórios.</div>
        </div>
        <div class="text-end">
            <div class="small opacity-75">Referência atual</div>
            <div class="h4 mb-0"><?= htmlspecialchars($mesRef) ?></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card finance-card bg-primary text-white">
                <div class="card-body position-relative">
                    <div class="text-uppercase small">Lançamentos</div>
                    <div class="display-6 fw-bold"><?= number_format($totalLancamentos) ?></div>
                    <small>Total cadastrado</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card finance-card bg-warning text-dark">
                <div class="card-body position-relative">
                    <div class="text-uppercase small">Pendentes</div>
                    <div class="display-6 fw-bold"><?= number_format($totalPendentes) ?></div>
                    <small>Em aberto</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card finance-card bg-success text-white">
                <div class="card-body position-relative">
                    <div class="text-uppercase small">Pagos</div>
                    <div class="display-6 fw-bold"><?= number_format($totalPagos) ?></div>
                    <small>Quitados</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card finance-card bg-danger text-white">
                <div class="card-body position-relative">
                    <div class="text-uppercase small">Inadimplentes</div>
                    <div class="display-6 fw-bold"><?= number_format($inadimplentes) ?></div>
                    <small>Sócios em atraso</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a class="action-card" href="mensalidades.php">
                <i class="fas fa-file-invoice-dollar fa-2x text-primary mb-3"></i>
                <h5>Novo lançamento</h5>
                <p class="mb-0 text-muted">Gerar, baixar e conferir mensalidades do mês.</p>
            </a>
        </div>
        <div class="col-md-3">
            <a class="action-card" href="mensalidades.php?filtro_tipo=pendente">
                <i class="fas fa-user-slash fa-2x text-danger mb-3"></i>
                <h5>Inadimplentes</h5>
                <p class="mb-0 text-muted">Ver e tratar lançamentos em atraso.</p>
            </a>
        </div>
        <div class="col-md-3">
            <a class="action-card" href="historico.php">
                <i class="fas fa-clipboard-list fa-2x text-dark mb-3"></i>
                <h5>Relatórios</h5>
                <p class="mb-0 text-muted">Acessar histórico financeiro e exportações.</p>
            </a>
        </div>
        <div class="col-md-3">
            <a class="action-card" href="configurar.php">
                <i class="fas fa-cog fa-2x text-success mb-3"></i>
                <h5>Configurações</h5>
                <p class="mb-0 text-muted">Ajustar vencimento, alertas e valores.</p>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card finance-card h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-list-ul me-2"></i>Últimas movimentações
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Socio</th>
                                    <th>Descrição</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimasMovimentacoes as $mov): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mov['socio_nome'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($mov['descricao'] ?: '-') ?></td>
                                        <td><?= !empty($mov['data_vencimento']) ? date('d/m/Y', strtotime($mov['data_vencimento'])) : '-' ?></td>
                                        <td>R$ <?= number_format((float)$mov['valor'], 2, ',', '.') ?></td>
                                        <td><span class="badge bg-<?= $mov['status'] === 'pago' ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars(ucfirst($mov['status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card finance-card h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-bolt me-2"></i>Ações rápidas
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="mensalidades.php" class="btn btn-primary">Abrir mensalidades</a>
                    <a href="historico.php" class="btn btn-outline-dark">Abrir histórico</a>
                    <a href="configurar.php" class="btn btn-outline-success">Abrir configurações</a>
                    <a href="agendamento.php" class="btn btn-outline-secondary">Ver agendamento</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
