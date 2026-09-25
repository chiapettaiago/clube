<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$socio_id = (int)($_GET['socio_id'] ?? 0);
$stmtSocio = $pdo->prepare("
    SELECT s.id, s.nome, s.numero_titulo, s.socio_principal_id, ts.nome as tipo_socio
    FROM socios s
    LEFT JOIN tipos_socio ts ON ts.id = s.tipo_socio_id
    WHERE s.id = ?
");
$stmtSocio->execute([$socio_id]);
$socio = $stmtSocio->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT cu.*, s.nome as socio_nome
    FROM convites_uso cu
    JOIN socios s ON cu.socio_id = s.id
    WHERE cu.socio_id = ?
    ORDER BY cu.data_uso DESC
    LIMIT 20
");
$stmt->execute([$socio_id]);
$usos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("
    SELECT mes_referencia, total_convites, convites_utilizados
    FROM convites
    WHERE socio_id = ?
    ORDER BY mes_referencia DESC
    LIMIT 6
");
$stmt2->execute([$socio_id]);
$mensais = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Convites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Histórico de Convites</h1>
            <div class="text-muted"><?= htmlspecialchars($socio['nome'] ?? 'Sócio não localizado') ?></div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Perfil</div>
                        <div class="fw-semibold"><?= !empty($socio['socio_principal_id']) ? 'Dependente' : 'Sócio' ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Título</div>
                        <div class="fw-semibold"><?= htmlspecialchars($socio['numero_titulo'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Tipo</div>
                        <div class="fw-semibold"><?= htmlspecialchars($socio['tipo_socio'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5">Últimos usos</h2>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-striped align-middle">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Convite usado para</th>
                    <th>Regra</th>
                    <th>Anexo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usos)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Nenhum convite utilizado ainda</td></tr>
                <?php else: ?>
                    <?php foreach ($usos as $uso): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($uso['data_uso'])) ?></td>
                            <td><?= htmlspecialchars($uso['tipo_convite'] ?? $uso['tipo_uso'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($uso['convite_usado_para'] ?: 'Entrada') ?></td>
                            <td><?= !empty($uso['regra_id']) ? '#' . (int)$uso['regra_id'] : '-' ?></td>
                            <td>
                                <?php if (!empty($uso['anexo_path'])): ?>
                                    <a href="../../<?= htmlspecialchars($uso['anexo_path']) ?>" target="_blank">Ver anexo</a>
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

    <h2 class="h5">Convites por mês</h2>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Total</th>
                    <th>Usados</th>
                    <th>Restantes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mensais as $mensal): ?>
                    <tr>
                        <td><?= date('m/Y', strtotime($mensal['mes_referencia'])) ?></td>
                        <td><?= (int)$mensal['total_convites'] ?></td>
                        <td><?= (int)$mensal['convites_utilizados'] ?></td>
                        <td><?= (int)$mensal['total_convites'] - (int)$mensal['convites_utilizados'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
