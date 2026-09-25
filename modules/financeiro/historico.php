<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$acao = $_GET['acao'] ?? '';
$busca = trim($_GET['busca'] ?? '');
$periodo = $_GET['periodo'] ?? '';

$sql = "
    SELECT
        hf.*,
        s.nome AS socio_nome,
        s.numero_titulo AS socio_titulo,
        COALESCE(sp.nome, s.nome) AS socio_financeiro_nome,
        COALESCE(sp.numero_titulo, s.numero_titulo) AS socio_financeiro_titulo,
        u.nome AS usuario_nome
    FROM historico_financeiro hf
    JOIN socios s ON s.id = hf.socio_id
    LEFT JOIN socios sp ON sp.id = COALESCE(NULLIF(s.socio_principal_id, 0), s.id)
    LEFT JOIN usuarios u ON u.id = hf.usuario_id
    WHERE 1=1
";
$params = [];

if ($acao !== '') {
    $sql .= " AND hf.acao = ?";
    $params[] = $acao;
}
if ($busca !== '') {
    $sql .= " AND (s.nome LIKE ? OR s.numero_titulo LIKE ? OR hf.descricao LIKE ? OR hf.observacao LIKE ?)";
    $like = "%{$busca}%";
    array_push($params, $like, $like, $like, $like);
}
if ($periodo !== '') {
    $sql .= " AND DATE_FORMAT(hf.data_evento, '%Y-%m') = ?";
    $params[] = $periodo;
}

$sql .= " ORDER BY hf.data_evento DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historico = $stmt->fetchAll();

$totalBaixas = $pdo->query("
    SELECT COUNT(DISTINCT hf.socio_id)
    FROM historico_financeiro hf
    JOIN socios s ON s.id = hf.socio_id
    WHERE hf.acao = 'baixa'
")->fetchColumn();
$totalEstornos = $pdo->query("
    SELECT COUNT(DISTINCT hf.socio_id)
    FROM historico_financeiro hf
    JOIN socios s ON s.id = hf.socio_id
    WHERE hf.acao = 'estorno'
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Histórico Financeiro</h4>
                <small>Auditoria de baixas, estornos e eventos financeiros por titular financeiro</small>
            </div>
            <a href="exportar.php?tipo=historico_financeiro" class="btn btn-light btn-sm">Exportar CSV</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-3">
                A mensalidade continua sendo única por família. Dependentes são consolidados no titular financeiro, sem cobrança duplicada.
            </div>
            <div class="alert alert-secondary mb-3">
                <strong>Reajuste futuro:</strong> a base por dependente e faixa etária já está configurada no sistema, mas a soma automática ainda não foi ativada.
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded"><small>Titulares com baixa</small><h5 class="mb-0"><?= (int)$totalBaixas ?></h5></div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded"><small>Titulares com estorno</small><h5 class="mb-0"><?= (int)$totalEstornos ?></h5></div>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5"><input type="text" name="busca" class="form-control" placeholder="Buscar titular, título ou descrição" value="<?= htmlspecialchars($busca) ?>"></div>
                        <div class="col-md-3">
                            <select name="acao" class="form-select">
                                <option value="">Todas as ações</option>
                                <option value="baixa" <?= $acao === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                                <option value="estorno" <?= $acao === 'estorno' ? 'selected' : '' ?>>Estorno</option>
                            </select>
                        </div>
                        <div class="col-md-2"><input type="month" name="periodo" class="form-control" value="<?= htmlspecialchars($periodo) ?>"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Titular</th>
                            <th>Título</th>
                            <th>Ação</th>
                            <th>Operador</th>
                            <th>Valor</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($h['data_evento'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($h['socio_financeiro_nome']) ?>
                                    <?php if ($h['socio_financeiro_nome'] !== $h['socio_nome']): ?>
                                        <br><small class="text-muted">Registro original: <?= htmlspecialchars($h['socio_nome']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($h['socio_financeiro_titulo']) ?></td>
                                <td>
                                    <?php if ($h['acao'] === 'baixa'): ?>
                                        <span class="badge bg-success">Baixa</span>
                                    <?php elseif ($h['acao'] === 'estorno'): ?>
                                        <span class="badge bg-warning text-dark">Estorno</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($h['acao']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($h['usuario_nome'] ?? 'Sistema') ?></td>
                                <td>R$ <?= number_format((float)$h['valor'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($h['observacao'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($historico)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">Nenhum registro encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
