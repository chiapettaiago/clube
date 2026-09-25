<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

$config = obterConfiguracaoFinanceira($pdo);

if ($_POST) {
    $dia = (int)($_POST['financeiro_dia_vencimento'] ?? 12);
    $qtd = (int)($_POST['financeiro_qtd_parcelas_alerta'] ?? 2);
    $valorMensalidade = str_replace(',', '.', trim($_POST['financeiro_valor_mensalidade'] ?? '0'));
    $responsavelEmail = trim($_POST['financeiro_responsavel_email'] ?? '');
    $modo = $_POST['financeiro_modo_alerta'] ?? 'alerta';
    $mensagem = trim($_POST['financeiro_mensagem_alerta'] ?? '');
    $reajusteAtivo = isset($_POST['financeiro_reajuste_dependente_ativo']) ? 1 : 0;
    $idadeMaxMasc = (int)($_POST['financeiro_reajuste_idade_max_masculino'] ?? 0);
    $idadeMaxFem = (int)($_POST['financeiro_reajuste_idade_max_feminino'] ?? 0);
    $valorExtraMasc = str_replace(',', '.', trim($_POST['financeiro_reajuste_valor_dependente_masculino'] ?? '0'));
    $valorExtraFem = str_replace(',', '.', trim($_POST['financeiro_reajuste_valor_dependente_feminino'] ?? '0'));
    $parentescos = trim($_POST['financeiro_reajuste_parentescos'] ?? 'filho,filha,enteado,enteada');

    $dia = max(1, min(31, $dia));
    $qtd = max(1, $qtd);
    if ($modo !== 'alerta' && $modo !== 'bloqueio') {
        $modo = 'alerta';
    }
    if ($mensagem === '') {
        $mensagem = 'O responsável deve verificar as mensalidades em atraso.';
    }

    $stmt = $pdo->prepare("INSERT INTO config_financeiro (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute(['financeiro_dia_vencimento', (string)$dia]);
    $stmt->execute(['financeiro_qtd_parcelas_alerta', (string)$qtd]);
    $stmt->execute(['financeiro_modo_alerta', $modo]);
    $stmt->execute(['financeiro_mensagem_alerta', $mensagem]);
    $stmt->execute(['financeiro_valor_mensalidade', $valorMensalidade]);
    $stmt->execute(['financeiro_responsavel_email', $responsavelEmail]);
    $stmt->execute(['financeiro_reajuste_dependente_ativo', (string)$reajusteAtivo]);
    $stmt->execute(['financeiro_reajuste_idade_max_masculino', (string)max(0, $idadeMaxMasc)]);
    $stmt->execute(['financeiro_reajuste_idade_max_feminino', (string)max(0, $idadeMaxFem)]);
    $stmt->execute(['financeiro_reajuste_valor_dependente_masculino', $valorExtraMasc]);
    $stmt->execute(['financeiro_reajuste_valor_dependente_feminino', $valorExtraFem]);
    $stmt->execute(['financeiro_reajuste_parentescos', $parentescos]);

    $config = obterConfiguracaoFinanceira($pdo);
    $msg = 'Configurações financeiras salvas com sucesso.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração Financeira</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../includes/menu.php'; ?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Configuração Financeira</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($msg)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Dia limite do vencimento</label>
                                <input type="number" name="financeiro_dia_vencimento" class="form-control" min="1" max="31" value="<?= htmlspecialchars($config['financeiro_dia_vencimento'] ?? '12') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Parcelas em atraso para alerta</label>
                                <input type="number" name="financeiro_qtd_parcelas_alerta" class="form-control" min="1" value="<?= htmlspecialchars($config['financeiro_qtd_parcelas_alerta'] ?? '2') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Modo</label>
                                <select name="financeiro_modo_alerta" class="form-select">
                                    <option value="alerta" <?= (($config['financeiro_modo_alerta'] ?? 'alerta') === 'alerta') ? 'selected' : '' ?>>Apenas alertar</option>
                                    <option value="bloqueio" <?= (($config['financeiro_modo_alerta'] ?? 'alerta') === 'bloqueio') ? 'selected' : '' ?>>Alertar e bloquear</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor da mensalidade padrão</label>
                                <input type="text" name="financeiro_valor_mensalidade" class="form-control" value="<?= htmlspecialchars($config['financeiro_valor_mensalidade'] ?? '0.00') ?>" placeholder="0,00">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Mensagem do alerta</label>
                                <textarea name="financeiro_mensagem_alerta" class="form-control" rows="3"><?= htmlspecialchars($config['financeiro_mensagem_alerta'] ?? 'O responsável deve verificar as mensalidades em atraso.') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">E-mail do responsável para notificações</label>
                                <input type="email" name="financeiro_responsavel_email" class="form-control" value="<?= htmlspecialchars($config['financeiro_responsavel_email'] ?? '') ?>" placeholder="responsavel@exemplo.com">
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="financeiro_reajuste_dependente_ativo" name="financeiro_reajuste_dependente_ativo" value="1" <?= (!empty($config['financeiro_reajuste_dependente_ativo']) && (int)$config['financeiro_reajuste_dependente_ativo'] === 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="financeiro_reajuste_dependente_ativo">
                                            Preparar reajuste por dependente e faixa etária
                                        </label>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Idade mínima masculina para cobrar extra</label>
                                            <input type="number" name="financeiro_reajuste_idade_max_masculino" class="form-control" min="0" max="99" value="<?= htmlspecialchars($config['financeiro_reajuste_idade_max_masculino'] ?? '0') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Valor extra masculino</label>
                                            <input type="text" name="financeiro_reajuste_valor_dependente_masculino" class="form-control" value="<?= htmlspecialchars($config['financeiro_reajuste_valor_dependente_masculino'] ?? '0.00') ?>" placeholder="0,00">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Idade mínima feminina para cobrar extra</label>
                                            <input type="number" name="financeiro_reajuste_idade_max_feminino" class="form-control" min="0" max="99" value="<?= htmlspecialchars($config['financeiro_reajuste_idade_max_feminino'] ?? '0') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Valor extra feminino</label>
                                            <input type="text" name="financeiro_reajuste_valor_dependente_feminino" class="form-control" value="<?= htmlspecialchars($config['financeiro_reajuste_valor_dependente_feminino'] ?? '0.00') ?>" placeholder="0,00">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Parentescos com taxa extra</label>
                                            <input type="text" name="financeiro_reajuste_parentescos" class="form-control" value="<?= htmlspecialchars($config['financeiro_reajuste_parentescos'] ?? 'filho,filha,enteado,enteada') ?>" placeholder="filho,filha,enteado,enteada">
                                            <small class="text-muted">Separe por vírgula. Exemplo padrão: filho, filha, enteado e enteada.</small>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Essa base só prepara a regra. A cobrança continua única por família até a ativação do cálculo no backend. Até 18 anos não deve haver taxa extra e só os parentescos permitidos entram no adicional.</small>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            Exemplo: se o dia limite for <strong>12</strong> e o sócio estiver com <strong>2 parcelas</strong> em atraso, a leitura da carteirinha vai disparar o alerta configurado.
                        </div>

                        <div class="d-grid d-md-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Salvar configurações
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Voltar ao financeiro</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
