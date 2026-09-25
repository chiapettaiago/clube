<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$totalSocios = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1")->fetchColumn();
$totalAniversariantesHoje = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1 AND data_nascimento IS NOT NULL AND MONTH(data_nascimento)=MONTH(CURRENT_DATE()) AND DAY(data_nascimento)=DAY(CURRENT_DATE())")->fetchColumn();
$totalAniversariantesMes = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1 AND data_nascimento IS NOT NULL AND MONTH(data_nascimento)=MONTH(CURRENT_DATE())")->fetchColumn();

$inadimplentes = 0;
try {
    $inadimplentes = $pdo->query("SELECT COUNT(DISTINCT socio_id) FROM lancamentos WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE()")->fetchColumn();
} catch (PDOException $e) {}

$mesAtual = date('Y-m-01');
$convitesBaixos = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM convites_familia cf
        JOIN socios s ON cf.familia_id = s.familia_id
        WHERE cf.mes_referencia = ?
        AND (cf.total_convites - cf.convites_utilizados) <= 2
        AND (cf.total_convites - cf.convites_utilizados) > 0
    ");
    $stmt->execute([$mesAtual]);
    $convitesBaixos = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

$proximosAniversariantes = [];
try {
    $stmt = $pdo->query("
        SELECT id, nome, data_nascimento, telefone, foto,
               DAY(data_nascimento) as dia,
               DATE_FORMAT(data_nascimento, '%d/%m') as data_aniversario
        FROM socios
        WHERE ativo = 1
          AND data_nascimento IS NOT NULL
          AND DATE_FORMAT(data_nascimento, '%m-%d') BETWEEN DATE_FORMAT(CURRENT_DATE(), '%m-%d')
          AND DATE_FORMAT(DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY), '%m-%d')
        ORDER BY DATE_FORMAT(data_nascimento, '%m-%d')
        LIMIT 10
    ");
    $proximosAniversariantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$ultimosSocios = $pdo->query("
    SELECT s.*, ts.nome as tipo_socio
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.ativo = 1
    ORDER BY s.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretaria - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#ecf0f1; font-family:'Segoe UI',sans-serif; }
        .hero { background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%); color:#fff; border-radius:22px; padding:24px; }
        .stat-card { border:none; border-radius:18px; overflow:hidden; }
        .panel { border:none; border-radius:18px; box-shadow:0 8px 24px rgba(0,0,0,.08); }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container-fluid py-4">
    <div class="hero mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-2"><i class="fas fa-building me-2"></i>Secretaria</h1>
            <div>Atendimentos, aniversariantes, documentos e comunicados.</div>
        </div>
        <div class="text-end"><i class="fas fa-calendar-alt fa-3x opacity-50"></i><div><?= date('d/m/Y H:i') ?></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="card stat-card bg-primary text-white"><div class="card-body"><h6>Total de Sócios</h6><h2><?= (int)$totalSocios ?></h2><small>Ativos no clube</small></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card stat-card bg-warning text-white"><div class="card-body"><h6>Aniversariantes</h6><h2><?= (int)$totalAniversariantesHoje ?> hoje</h2><small><?= (int)$totalAniversariantesMes ?> no mês</small></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card stat-card bg-danger text-white"><div class="card-body"><h6>Inadimplentes</h6><h2><?= (int)$inadimplentes ?></h2><small>Em débito com o clube</small></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card stat-card bg-info text-white"><div class="card-body"><h6>Convites Baixos</h6><h2><?= (int)$convitesBaixos ?></h2><small>Famílias com até 2 convites</small></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card panel h-100">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-birthday-cake me-2"></i>Próximos Aniversariantes</div>
                <div class="card-body">
                    <?php if (empty($proximosAniversariantes)): ?>
                        <div class="text-muted">Nenhum aniversariante nos próximos dias.</div>
                    <?php else: foreach ($proximosAniversariantes as $aniv): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <strong><?= htmlspecialchars($aniv['nome']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($aniv['data_aniversario']) ?></small>
                            </div>
                            <span class="badge bg-secondary"><?= htmlspecialchars($aniv['telefone'] ?: '-') ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card panel h-100">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-user-plus me-2"></i>Últimos Sócios Cadastrados</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light"><tr><th>Nome</th><th>Título</th><th>Tipo</th><th>Ações</th></tr></thead>
                            <tbody>
                            <?php foreach ($ultimosSocios as $socio): ?>
                                <tr>
                                    <td><?= htmlspecialchars($socio['nome']) ?></td>
                                    <td><?= htmlspecialchars($socio['numero_titulo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($socio['tipo_socio']) ?></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="../socios/editar.php?id=<?= (int)$socio['id'] ?>">Abrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
