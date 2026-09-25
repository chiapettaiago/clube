<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');
$tipo = trim($_GET['tipo_convite'] ?? '');
$socioFiltro = trim($_GET['socio'] ?? '');
$apenasAnexos = isset($_GET['anexos']) && $_GET['anexos'] === '1';
$exportar = isset($_GET['exportar']) && $_GET['exportar'] === 'csv';

$params = [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'];
$filtroTipo = '';
$filtroSocio = '';
$filtroAnexo = '';

if ($tipo !== '') {
    $filtroTipo = " AND cu.tipo_convite = ? ";
    $params[] = $tipo;
}
if ($socioFiltro !== '') {
    $filtroSocio = " AND (s.nome LIKE ? OR s.numero_titulo LIKE ?) ";
    $params[] = '%' . $socioFiltro . '%';
    $params[] = '%' . $socioFiltro . '%';
}
if ($apenasAnexos) {
    $filtroAnexo = " AND cu.anexo_path IS NOT NULL AND cu.anexo_path <> '' ";
}

$sql = "
    SELECT cu.*, s.nome as socio_nome, s.numero_titulo, ts.nome as tipo_socio, cr.nome as regra_nome
    FROM convites_uso cu
    JOIN socios s ON s.id = cu.socio_id
    LEFT JOIN tipos_socio ts ON ts.id = s.tipo_socio_id
    LEFT JOIN convites_regras cr ON cr.id = cu.regra_id
    WHERE cu.data_uso BETWEEN ? AND ?
    $filtroTipo
    $filtroSocio
    $filtroAnexo
    ORDER BY cu.data_uso DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resumo = [
    'total' => count($usos),
    'extras' => 0,
    'anexos' => 0,
];
foreach ($usos as $uso) {
    if (($uso['tipo_uso'] ?? '') === 'extra') {
        $resumo['extras']++;
    }
    if (!empty($uso['anexo_path'])) {
        $resumo['anexos']++;
    }
}

if ($exportar) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_convites_' . date('Y-m-d_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Data', 'Socio', 'Titulo', 'Perfil', 'Tipo convite', 'Regra', 'Observacao', 'Anexo']);
    foreach ($usos as $uso) {
        fputcsv($out, [
            date('d/m/Y H:i', strtotime($uso['data_uso'])),
            $uso['socio_nome'],
            $uso['numero_titulo'] ?? '-',
            $uso['tipo_socio'] ?? '-',
            $uso['tipo_convite'] ?? $uso['tipo_uso'] ?? '-',
            $uso['regra_nome'] ?? ($uso['regra_id'] ? '#' . $uso['regra_id'] : '-'),
            $uso['convite_usado_para'] ?? '-',
            $uso['anexo_path'] ?? '-',
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Convites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Relatório de Convites</h1>
            <div class="text-muted">Consumo, regras utilizadas e comprovantes anexados.</div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <form class="row g-3 mb-4" method="get">
        <div class="col-md-3">
            <label class="form-label">Data início</label>
            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($dataInicio) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Data fim</label>
            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($dataFim) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo_convite" class="form-select">
                <option value="">Todos</option>
                <?php foreach (['geral', 'casal', 'solteiro', 'extra'] as $opcao): ?>
                    <option value="<?= $opcao ?>" <?= $tipo === $opcao ? 'selected' : '' ?>><?= ucfirst($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sócio</label>
            <input type="text" name="socio" class="form-control" value="<?= htmlspecialchars($socioFiltro) ?>" placeholder="Nome ou título">
        </div>
        <div class="col-md-2">
            <label class="form-label">Anexos</label>
            <select name="anexos" class="form-select">
                <option value="0" <?= !$apenasAnexos ? 'selected' : '' ?>>Todos</option>
                <option value="1" <?= $apenasAnexos ? 'selected' : '' ?>>Somente com anexo</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <button class="btn btn-outline-success" name="exportar" value="csv">Exportar CSV</button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total de usos</div>
                    <div class="display-6"><?= (int)$resumo['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Usos extras</div>
                    <div class="display-6"><?= (int)$resumo['extras'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Com anexos</div>
                    <div class="display-6"><?= (int)$resumo['anexos'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Sócio</th>
                    <th>Perfil</th>
                    <th>Tipo</th>
                    <th>Regra</th>
                    <th>Observação</th>
                    <th>Anexo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usos)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Nenhum registro encontrado</td></tr>
                <?php else: ?>
                    <?php foreach ($usos as $uso): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($uso['data_uso'])) ?></td>
                            <td><?= htmlspecialchars($uso['socio_nome']) ?> <span class="text-muted small">#<?= htmlspecialchars($uso['numero_titulo'] ?? '-') ?></span></td>
                            <td><?= htmlspecialchars($uso['tipo_socio'] ?? '-') ?></td>
                            <td><span class="badge text-bg-secondary"><?= htmlspecialchars($uso['tipo_convite'] ?? $uso['tipo_uso'] ?? '-') ?></span></td>
                            <td><?= htmlspecialchars($uso['regra_nome'] ?? ($uso['regra_id'] ? '#' . $uso['regra_id'] : '-')) ?></td>
                            <td><?= htmlspecialchars($uso['convite_usado_para'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($uso['anexo_path'])): ?>
                                    <a href="../../<?= htmlspecialchars($uso['anexo_path']) ?>" target="_blank"><?= htmlspecialchars($uso['anexo_nome'] ?? 'Abrir') ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
