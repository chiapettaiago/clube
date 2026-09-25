<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.*, ts.nome as tipo_socio, ts.cor_carteirinha,
        (SELECT COUNT(*) FROM socios WHERE socio_principal_id = s.id AND ativo = 1) as total_dependentes
        FROM socios s
        JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
        WHERE s.ativo = 1 AND (s.socio_principal_id IS NULL OR s.socio_principal_id = 0)";

$params = [];
if ($search !== '') {
    $sql .= " AND (s.nome LIKE ? OR s.numero_titulo LIKE ? OR s.cpf LIKE ? OR s.id = ?)";
    $params = ["%$search%", "%$search%", "%$search%", (int)$search];
}
$sql .= " ORDER BY s.nome";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sociosPrincipais = $stmt->fetchAll();

function getDependentes($pdo, $socioId) {
    $stmt = $pdo->prepare("
        SELECT s.*, ts.nome as tipo_socio, ts.cor_carteirinha, p.nome as parentesco_nome
        FROM socios s
        JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
        LEFT JOIN parentescos p ON s.parentesco = p.nome
        WHERE s.socio_principal_id = ? AND s.ativo = 1
        ORDER BY s.nome
    ");
    $stmt->execute([$socioId]);
    return $stmt->fetchAll();
}

function calcularIdade($dataNascimento) {
    if (empty($dataNascimento)) return '-';
    $dataNasc = new DateTime($dataNascimento);
    $hoje = new DateTime();
    return $hoje->diff($dataNasc)->y . ' anos';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Sócios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7fb; }
        .page-hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color:#fff; border-radius:24px; padding:24px; box-shadow:0 18px 40px rgba(15,23,42,.18); }
        .table-socios .socio-principal { background-color:#f8f9fa; font-weight:bold; }
        .table-socios .dependente { background-color:#fff; }
        .badge-dependente { background-color:#ffc107; color:#856404; }
    </style>
</head>
<body>
<?php include '../../includes/menu.php'; ?>

<div class="container-fluid py-4">
    <div class="page-hero mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-2"><i class="fas fa-users me-2"></i>Gerenciar Sócios</h1>
            <div>Cadastro, dependentes, carteirinhas e exportação.</div>
        </div>
        <a href="cadastrar.php" class="btn btn-light"><i class="fas fa-plus me-2"></i>Novo Sócio</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row mb-3 g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nome, título ou CPF..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" onclick="buscar()"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-info btn-sm" onclick="exportarExcel()"><i class="fas fa-file-excel"></i> Exportar</button>
                    <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-socios align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Foto</th>
                            <th>Nome</th>
                            <th>Nº Título</th>
                            <th>CPF</th>
                            <th>Idade</th>
                            <th>Tipo</th>
                            <th>Dependentes</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sociosPrincipais as $socio): ?>
                        <tr class="socio-principal">
                            <td>
                                <?php if (!empty($socio['foto']) && file_exists('../../assets/uploads/' . $socio['foto'])): ?>
                                    <img src="../../assets/uploads/<?= htmlspecialchars($socio['foto']) ?>" width="40" height="40" class="rounded-circle">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($socio['nome']) ?></strong><br>
                                <small class="text-muted">ID: #<?= (int)$socio['id'] ?></small>
                            </td>
                            <td><?= htmlspecialchars($socio['numero_titulo'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($socio['cpf'] ?? '-') ?></td>
                            <td><?= calcularIdade($socio['data_nascimento']) ?></td>
                            <td><span class="badge" style="background-color: <?= htmlspecialchars($socio['cor_carteirinha'] ?: '#6c757d') ?>; color:#fff;"><?= htmlspecialchars($socio['tipo_socio']) ?></span></td>
                            <td>
                                <?php if ((int)$socio['total_dependentes'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-users"></i> <?= (int)$socio['total_dependentes'] ?> dependente(s)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nenhum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="editar.php?id=<?= (int)$socio['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="../carteirinha/gerar.php?id=<?= (int)$socio['id'] ?>" class="btn btn-info" target="_blank"><i class="fas fa-id-card"></i></a>
                                    <a href="dependentes.php?id=<?= (int)$socio['id'] ?>" class="btn btn-success"><i class="fas fa-user-plus"></i></a>
                                </div>
                            </td>
                        </tr>

                        <?php foreach (getDependentes($pdo, $socio['id']) as $dep): ?>
                        <tr class="dependente">
                            <td style="padding-left:50px;">
                                <?php if (!empty($dep['foto']) && file_exists('../../assets/uploads/' . $dep['foto'])): ?>
                                    <img src="../../assets/uploads/<?= htmlspecialchars($dep['foto']) ?>" width="35" height="35" class="rounded-circle">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-level-down-alt fa-rotate-90 text-warning me-1"></i>
                                <?= htmlspecialchars($dep['nome']) ?><br>
                                <small class="text-muted"><i class="fas fa-heart"></i> <?= htmlspecialchars($dep['parentesco'] ?: 'Familiar') ?></small>
                            </td>
                            <td><?= htmlspecialchars($dep['numero_titulo'] ?? '-') ?> <small class="text-muted">(vinculado ao responsável)</small></td>
                            <td><?= htmlspecialchars($dep['cpf'] ?? '-') ?></td>
                            <td><?= calcularIdade($dep['data_nascimento']) ?></td>
                            <td><span class="badge" style="background-color: <?= htmlspecialchars($dep['cor_carteirinha'] ?: '#6c757d') ?>; color:#fff;"><?= htmlspecialchars($dep['tipo_socio']) ?></span></td>
                            <td>-</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="editar.php?id=<?= (int)$dep['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="../carteirinha/gerar.php?id=<?= (int)$dep['id'] ?>" class="btn btn-info" target="_blank"><i class="fas fa-id-card"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function buscar() {
    const termo = document.getElementById('searchInput').value;
    window.location.href = 'index.php?search=' + encodeURIComponent(termo);
}
function exportarExcel() {
    window.location.href = 'exportar.php';
}
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') buscar();
});
</script>
</body>
</html>
