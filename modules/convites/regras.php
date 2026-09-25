<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

garantirTabelaRegrasConvites($pdo);

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_regra'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $tipo_convite = trim($_POST['tipo_convite'] ?? 'geral');
    $quantidade = max(0, (int)($_POST['quantidade'] ?? 1));
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';
    $aplica_familia = isset($_POST['aplica_familia']) ? 1 : 0;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $observacao = trim($_POST['observacao'] ?? '');

    if ($nome === '' || $data_inicio === '' || $data_fim === '') {
        $erro = 'Preencha nome, início e fim da regra.';
    } else {
        $anexoPath = null;
        $anexoNome = null;

        if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/uploads/convites_regras/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION));
            $anexoNome = basename($_FILES['anexo']['name']);
            $anexoPath = 'assets/uploads/convites_regras/' . uniqid('convite_', true) . '.' . $ext;
            move_uploaded_file($_FILES['anexo']['tmp_name'], __DIR__ . '/../../' . $anexoPath);
        }

        if ($id > 0) {
            $sql = "UPDATE convites_regras SET nome = ?, tipo_convite = ?, quantidade = ?, data_inicio = ?, data_fim = ?, aplica_familia = ?, ativo = ?, observacao = ?, updated_at = NOW()";
            $params = [$nome, $tipo_convite, $quantidade, $data_inicio, $data_fim, $aplica_familia, $ativo, $observacao];
            if ($anexoPath) {
                $sql .= ", anexo_path = ?, anexo_nome = ?";
                $params[] = $anexoPath;
                $params[] = $anexoNome;
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO convites_regras (nome, tipo_convite, quantidade, data_inicio, data_fim, aplica_familia, ativo, observacao, anexo_path, anexo_nome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $tipo_convite, $quantidade, $data_inicio, $data_fim, $aplica_familia, $ativo, $observacao, $anexoPath, $anexoNome]);
        }
        $sucesso = 'Regra salva com sucesso.';
    }
}

if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare('DELETE FROM convites_regras WHERE id = ?');
    $stmt->execute([(int)$_GET['excluir']]);
    $sucesso = 'Regra excluída.';
}

$edit = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM convites_regras WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$regras = $pdo->query('SELECT * FROM convites_regras ORDER BY data_inicio DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
$extrasAtivos = calcularConvitesExtrasAtivos($pdo, date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regras de Convites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Regras de Convites</h1>
            <div class="text-muted">Crie períodos sazonais, convites extras e anexos de comprovação.</div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    </div>

    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

    <div class="alert alert-info">
        Convites extras ativos hoje: <strong><?= (int)$extrasAtivos['total'] ?></strong>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                <div class="col-md-6">
                    <label class="form-label">Nome da regra</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($edit['nome'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo_convite" class="form-select">
                        <?php foreach (['geral' => 'Geral', 'casal' => 'Casal', 'solteiro' => 'Solteiro', 'extra' => 'Extra'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= (($edit['tipo_convite'] ?? 'geral') === $valor) ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade</label>
                    <input type="number" name="quantidade" class="form-control" min="0" value="<?= htmlspecialchars((string)($edit['quantidade'] ?? 1)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($edit['data_inicio'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($edit['data_fim'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="aplica_familia" value="1" <?= !empty($edit) ? (!empty($edit['aplica_familia']) ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label">Aplica à família</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" value="1" <?= !empty($edit) ? (!empty($edit['ativo']) ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label">Ativa</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Observação</label>
                    <textarea name="observacao" class="form-control" rows="2"><?= htmlspecialchars($edit['observacao'] ?? '') ?></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Anexo do comprovante</label>
                    <input type="file" name="anexo" class="form-control" accept="image/*,.pdf">
                    <?php if (!empty($edit['anexo_path'])): ?>
                        <div class="mt-2 small text-muted">Arquivo atual: <a href="../../<?= htmlspecialchars($edit['anexo_path']) ?>" target="_blank"><?= htmlspecialchars($edit['anexo_nome'] ?? basename($edit['anexo_path'])) ?></a></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" name="salvar_regra" value="1">Salvar regra</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Qtd</th>
                        <th>Período</th>
                        <th>Ativa</th>
                        <th>Anexo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regras as $regra): ?>
                        <tr>
                            <td><?= htmlspecialchars($regra['nome']) ?></td>
                            <td><?= htmlspecialchars($regra['tipo_convite']) ?></td>
                            <td><?= (int)$regra['quantidade'] ?></td>
                            <td><?= htmlspecialchars($regra['data_inicio']) ?> até <?= htmlspecialchars($regra['data_fim']) ?></td>
                            <td><?= !empty($regra['ativo']) ? 'Sim' : 'Não' ?></td>
                            <td><?= !empty($regra['anexo_path']) ? '<a target="_blank" href="../../' . htmlspecialchars($regra['anexo_path']) . '">Ver</a>' : '-' ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int)$regra['id'] ?>">Editar</a>
                                <a class="btn btn-sm btn-outline-danger" href="?excluir=<?= (int)$regra['id'] ?>" onclick="return confirm('Excluir regra?')">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
