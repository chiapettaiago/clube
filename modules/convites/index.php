<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$ano = (int)($_GET['ano'] ?? date('Y'));
$mes = (int)($_GET['mes'] ?? date('m'));
$dataRef = sprintf('%04d-%02d-01', $ano, $mes);
$mensagem = $_GET['msg'] ?? '';

$sqlSocios = "
    SELECT s.id, s.nome, s.numero_titulo, s.convites_por_mes, s.socio_principal_id,
           COALESCE(c.total_convites, s.convites_por_mes) as total_convites,
           COALESCE(c.convites_utilizados, 0) as convites_utilizados,
           ts.nome as tipo_socio,
           ts.cor_carteirinha
    FROM socios s
    LEFT JOIN tipos_socio ts ON ts.id = s.tipo_socio_id
    LEFT JOIN convites c ON s.id = c.socio_id AND c.mes_referencia = ?
    WHERE s.ativo = 1
    ORDER BY s.nome
";
$stmt = $pdo->prepare($sqlSocios);
$stmt->execute([$dataRef]);
$socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$familiasStmt = $pdo->prepare("
    SELECT s.familia_id,
           MIN(s.nome) as responsavel_nome,
           MIN(s.numero_titulo) as numero_titulo,
           COALESCE(cf.total_convites, 0) as total_convites,
           COALESCE(cf.convites_utilizados, 0) as convites_utilizados
    FROM socios s
    LEFT JOIN convites_familia cf ON cf.familia_id = s.familia_id AND cf.mes_referencia = ?
    WHERE s.ativo = 1
    GROUP BY s.familia_id, cf.total_convites, cf.convites_utilizados
    ORDER BY responsavel_nome
");
$familiasStmt->execute([$dataRef]);
$familias = $familiasStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['renovar'])) {
    $socio_id = (int)$_POST['socio_id'];
    $total_convites = max(0, (int)$_POST['total_convites']);

    $check = $pdo->prepare("SELECT id FROM convites WHERE socio_id = ? AND mes_referencia = ?");
    $check->execute([$socio_id, $dataRef]);

    if ($check->rowCount() > 0) {
        $stmt = $pdo->prepare("UPDATE convites SET total_convites = ?, convites_utilizados = 0 WHERE socio_id = ? AND mes_referencia = ?");
        $stmt->execute([$total_convites, $socio_id, $dataRef]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO convites (socio_id, mes_referencia, total_convites, convites_utilizados) VALUES (?, ?, ?, 0)");
        $stmt->execute([$socio_id, $dataRef, $total_convites]);
    }

    header('Location: index.php?ano=' . $ano . '&mes=' . $mes . '&msg=' . urlencode('Convites atualizados com sucesso!'));
    exit;
}

$mesNome = date('F', mktime(0, 0, 0, $mes, 1));
$totais = [
    'socios' => count($socios),
    'familias' => count($familias),
    'total_convites' => array_sum(array_map(fn($s) => (int)$s['total_convites'], $socios)),
    'utilizados' => array_sum(array_map(fn($s) => (int)$s['convites_utilizados'], $socios)),
];
$totais['disponiveis'] = $totais['total_convites'] - $totais['utilizados'];
$extrasAtivos = calcularConvitesExtrasDisponiveis($pdo, date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: #fff; border-radius: 24px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,.18); }
        .card-soft { border: 0; border-radius: 20px; box-shadow: 0 10px 28px rgba(15,23,42,.08); }
        .family-card { border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; }
        .mini-stat { border-radius: 18px; padding: 16px; color: #fff; min-height: 100%; }
        .mini-stat h3 { font-size: 2rem; margin: 0; }
        .mini-stat small { opacity: .85; }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container py-4">
    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <div class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase small opacity-75 mb-1">Convites da família</div>
                <h1 class="h3 mb-2">Controle de Convites</h1>
                <div class="opacity-75">Saldo consolidado por família e ajuste mensal por sócio.</div>
            </div>
            <form class="d-flex gap-2 align-items-center" method="get">
                <select name="mes" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="ano" class="form-select">
                    <?php for ($a = date('Y') - 1; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?= $a ?>" <?= $a === $ano ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-light">Filtrar</button>
            </form>
        </div>
    </div>

    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Convites extras ativos hoje:</strong> <?= (int)$extrasAtivos['disponiveis'] ?>
            <span class="ms-2 text-muted">Esses convites somam ao saldo da família enquanto a regra estiver válida.</span>
        </div>
        <a href="regras.php" class="btn btn-sm btn-outline-primary">Gerenciar regras</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="mini-stat bg-primary"><small>Sócios</small><h3><?= number_format($totais['socios']) ?></h3></div></div>
        <div class="col-md-3"><div class="mini-stat bg-success"><small>Famílias</small><h3><?= number_format($totais['familias']) ?></h3></div></div>
        <div class="col-md-3"><div class="mini-stat bg-warning text-dark"><small>Disponíveis</small><h3><?= number_format($totais['disponiveis']) ?></h3></div></div>
        <div class="col-md-3"><div class="mini-stat bg-danger"><small>Utilizados</small><h3><?= number_format($totais['utilizados']) ?></h3></div></div>
    </div>

    <div class="card card-soft mb-4">
        <div class="card-body">
            <div class="alert alert-info mb-4">Use esta tela para ajustar os convites do mês selecionado e consultar o saldo por família.</div>
            <div class="row g-3">
                <?php foreach ($familias as $familia): ?>
                    <?php $restantes = (int)$familia['total_convites'] - (int)$familia['convites_utilizados']; ?>
                    <div class="col-md-4">
                        <div class="family-card p-3 h-100">
                            <div class="fw-semibold"><?= htmlspecialchars($familia['responsavel_nome']) ?></div>
                            <div class="text-muted small">Título <?= htmlspecialchars($familia['numero_titulo'] ?: '-') ?></div>
                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                <span class="badge text-bg-primary">Total: <?= (int)$familia['total_convites'] ?></span>
                                <span class="badge <?= $restantes > 0 ? 'text-bg-success' : 'text-bg-danger' ?>">Disponíveis: <?= $restantes ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card card-soft mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Relatório de consumo:</strong> acompanhe usos, regras e anexos em uma visão consolidada.
            </div>
            <a href="relatorio.php" class="btn btn-outline-dark">Abrir relatório</a>
        </div>
    </div>

    <div class="card card-soft">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h2 class="h5 mb-3"><i class="fas fa-ticket-alt me-2"></i>Convites por Sócio</h2>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Sócio</th>
                        <th>Tipo</th>
                        <th>Nº Título</th>
                        <th>Convites/Mês</th>
                        <th>Utilizados</th>
                        <th>Disponíveis</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($socios as $socio): ?>
                        <?php $disponiveis = (int)$socio['total_convites'] - (int)$socio['convites_utilizados']; ?>
                        <tr>
                            <td><?= htmlspecialchars($socio['nome']) ?></td>
                            <td>
                                <span class="badge" style="background:<?= htmlspecialchars($socio['cor_carteirinha'] ?: '#6c757d') ?>">
                                    <?= htmlspecialchars($socio['tipo_socio'] ?: 'Sem tipo') ?>
                                </span>
                                <span class="badge text-bg-light text-dark ms-1">
                                    <?= !empty($socio['socio_principal_id']) ? 'Dependente' : 'Sócio' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($socio['numero_titulo'] ?? '-') ?></td>
                            <td>
                                <form method="post" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="socio_id" value="<?= (int)$socio['id'] ?>">
                                    <input type="number" min="0" name="total_convites" value="<?= (int)$socio['total_convites'] ?>" class="form-control form-control-sm" style="max-width:120px">
                                    <button type="submit" name="renovar" class="btn btn-sm btn-primary">Atualizar</button>
                                </form>
                            </td>
                            <td><?= (int)$socio['convites_utilizados'] ?></td>
                            <td><span class="badge <?= $disponiveis > 0 ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $disponiveis ?></span></td>
                            <td>
                                <a href="historico.php?socio_id=<?= (int)$socio['id'] ?>" class="btn btn-sm btn-outline-secondary">Histórico</a>
                                <a href="baixa.php?codigo=<?= urlencode((string)$socio['id']) ?>" class="btn btn-sm btn-warning">Baixa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
