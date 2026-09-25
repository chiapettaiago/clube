<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');

$aniversariantes = [];
$sql = "SELECT id, nome, numero_titulo, data_nascimento, foto, telefone, email_socio
        FROM socios
        WHERE ativo = 1
        AND data_nascimento IS NOT NULL
        AND MONTH(data_nascimento) = $mes
        ORDER BY DAY(data_nascimento)";

$result = $pdo->query($sql);
if ($result instanceof PDOStatement) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        if (is_array($row)) {
            $aniversariantes[] = $row;
        }
    }
}

$totalAniversariantes = count($aniversariantes);
$listaAniversariantes = is_array($aniversariantes) ? $aniversariantes : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aniversariantes - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-birthday-cake"></i> Aniversariantes</h5>
                <div class="float-end">
                    <form method="GET" class="d-inline">
                        <select name="mes" class="form-select form-select-sm d-inline-block w-auto" style="width:auto;" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> Total de aniversariantes: <strong><?= $totalAniversariantes ?></strong>
                </div>

                <?php if ($totalAniversariantes === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3 d-block"></i>
                        <p>Nenhum aniversariante neste mês</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Nº Título</th>
                                    <th>Data Nascimento</th>
                                    <th>Telefone</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaAniversariantes as $aniv): ?>
                                    <?php if (!is_array($aniv)) continue; ?>
                                    <?php
                                        $nome = $aniv['nome'] ?? '';
                                        $titulo = $aniv['numero_titulo'] ?? '';
                                        $nascimento = $aniv['data_nascimento'] ?? '';
                                        $telefone = $aniv['telefone'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($nome) ?></td>
                                        <td><?= htmlspecialchars($titulo) ?></td>
                                        <td><?= !empty($nascimento) ? date('d/m', strtotime($nascimento)) : '-' ?></td>
                                        <td><?= !empty($telefone) ? htmlspecialchars($telefone) : '-' ?></td>
                                        <td>
                                            <button onclick="alert('Parabéns <?= addslashes($nome) ?>!')" class="btn btn-sm btn-info">
                                                <i class="fas fa-gift"></i> Parabéns
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
