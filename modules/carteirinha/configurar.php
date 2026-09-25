<?php
require_once '../../config.php';
require_once '../../includes/auth.php';

$tipos_socio = $pdo->query("SELECT * FROM tipos_socio ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cores'])) {
    foreach ($_POST['cores'] as $id => $cor) {
        $stmt = $pdo->prepare("UPDATE tipos_socio SET cor_carteirinha = ? WHERE id = ?");
        $stmt->execute([$cor, $id]);
    }
    $sucesso = "Cores atualizadas com sucesso!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_identificacao'])) {
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('tipo_identificacao', ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$_POST['tipo_identificacao'], $_POST['tipo_identificacao']]);
    $sucesso = "Configuração salva!";
}

$config_tipo = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'tipo_identificacao'")->fetchColumn();
if (!$config_tipo) {
    $config_tipo = 'qrcode';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Carteirinhas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>

    <div class="container mt-4 mb-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-palette me-2"></i> Cores das Carteirinhas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($sucesso) && isset($_POST['cores'])): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?php foreach ($tipos_socio as $tipo): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?= htmlspecialchars($tipo['nome']) ?></label>
                                    <div class="input-group">
                                        <input type="color" name="cores[<?= (int)$tipo['id'] ?>]" value="<?= htmlspecialchars($tipo['cor_carteirinha'] ?: '#0d6efd') ?>" class="form-control form-control-color" style="width: 80px;">
                                        <span class="input-group-text">Código: <?= htmlspecialchars($tipo['cor_carteirinha'] ?: '#0d6efd') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Salvar Cores</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i> Configurações Gerais</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($sucesso) && isset($_POST['tipo_identificacao'])): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipo de Identificação Padrão</label>
                                <select name="tipo_identificacao" class="form-select">
                                    <option value="qrcode" <?= $config_tipo === 'qrcode' ? 'selected' : '' ?>>QR Code</option>
                                    <option value="barras" <?= $config_tipo === 'barras' ? 'selected' : '' ?>>Código de Barras</option>
                                </select>
                                <small class="text-muted">Este será o padrão para novas carteirinhas.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Salvar Configuração</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
