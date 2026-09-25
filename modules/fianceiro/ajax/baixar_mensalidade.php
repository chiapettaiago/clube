<?php
require_once '../../../config.php';
require_once '../../includes/auth.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$id = $_POST['lancamento_id'] ?? $_GET['id'] ?? 0;
$forma_pagamento = $_POST['forma_pagamento'] ?? null;
$data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
$observacao = $_POST['observacao'] ?? '';
$valorPago = isset($_POST['valor_pago']) ? (float)str_replace(',', '.', trim((string)$_POST['valor_pago'])) : null;
$usuarioId = $_SESSION['usuario_id'] ?? null;

function registrarHistoricoFinanceiro(PDO $pdo, int $lancamentoId, string $acao, ?int $usuarioId, string $descricao = null, string $observacao = null): void {
    $stmt = $pdo->prepare("SELECT socio_id, valor, descricao FROM lancamentos WHERE id = ?");
    $stmt->execute([$lancamentoId]);
    $lancamento = $stmt->fetch();
    if (!$lancamento) {
        return;
    }

    $socioFinanceiroId = obterSocioFinanceiroPrincipal($pdo, (int)$lancamento['socio_id']);

    $ins = $pdo->prepare("
        INSERT INTO historico_financeiro (lancamento_id, socio_id, usuario_id, acao, descricao, valor, observacao)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
        $lancamentoId,
        $socioFinanceiroId,
        $usuarioId,
        $acao,
        $descricao ?: $lancamento['descricao'],
        (float)$lancamento['valor'],
        $observacao
    ]);
}

function notificarFinanceiroAlteracao(PDO $pdo, int $lancamentoId, string $acao): void {
    $config = obterConfiguracaoFinanceira($pdo);
    $destinatario = trim($config['financeiro_responsavel_email'] ?? '');
    if ($destinatario === '') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT l.descricao, l.valor, s.nome
        FROM lancamentos l
        JOIN socios s ON s.id = l.socio_id
        WHERE l.id = ?
    ");
    $stmt->execute([$lancamentoId]);
    $dados = $stmt->fetch();
    if (!$dados) {
        return;
    }

    $assunto = $acao === 'estorno' ? 'Estorno de mensalidade' : 'Baixa de mensalidade';
    $mensagem = sprintf(
        '%s: %s - %s - R$ %s',
        $assunto,
        $dados['nome'],
        $dados['descricao'],
        number_format((float)$dados['valor'], 2, ',', '.')
    );

    $ins = $pdo->prepare("INSERT INTO fila_notificacoes (destinatario, tipo, assunto, mensagem) VALUES (?, 'email', ?, ?)");
    $ins->execute([$destinatario, $assunto, $mensagem]);
}

if (isset($_POST['estornar_lancamento']) && $id) {
    $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pendente', data_pagamento = NULL, forma_pagamento = NULL, lancado_por = ?, observacao = CONCAT(COALESCE(observacao, ''), ' | Estornado em ', NOW()) WHERE id = ?");
    $stmt->execute([$usuarioId, $id]);
    registrarHistoricoFinanceiro($pdo, (int)$id, 'estorno', $usuarioId, null, 'Pagamento estornado');
    notificarFinanceiroAlteracao($pdo, (int)$id, 'estorno');

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Pagamento estornado com sucesso.']);
        exit;
    }

    header('Location: ../index.php?msg=estornado');
    exit;
}

if ($id) {
    $stmtAtual = $pdo->prepare("SELECT valor FROM lancamentos WHERE id = ?");
    $stmtAtual->execute([$id]);
    $valorAtual = (float)$stmtAtual->fetchColumn();
    $ajusteTexto = '';
    $observacaoFinal = trim((string)$observacao);

    if ($valorPago !== null && abs($valorPago - $valorAtual) >= 0.01) {
        $dif = $valorPago - $valorAtual;
        $ajusteTexto = $dif > 0
            ? ' | Acréscimo/Juros de R$ ' . number_format($dif, 2, ',', '.')
            : ' | Desconto de R$ ' . number_format(abs($dif), 2, ',', '.');
        $observacaoFinal = trim($observacao . ' ' . trim($ajusteTexto));
    }

    if ($valorPago !== null) {
        $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pago', valor = ?, data_pagamento = ?, forma_pagamento = ?, lancado_por = ?, observacao = CONCAT(COALESCE(observacao, ''), ' ', ?, ?) WHERE id = ?");
        $stmt->execute([$valorPago, $data_pagamento, $forma_pagamento, $usuarioId, $observacaoFinal, $ajusteTexto, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE lancamentos SET status = 'pago', data_pagamento = ?, forma_pagamento = ?, lancado_por = ?, observacao = CONCAT(COALESCE(observacao, ''), ' ', ?, '') WHERE id = ?");
        $stmt->execute([$data_pagamento, $forma_pagamento, $usuarioId, $observacaoFinal, $id]);
    }

    registrarHistoricoFinanceiro($pdo, (int)$id, 'baixa', $usuarioId, null, $observacaoFinal ?: 'Pagamento registrado');
    notificarFinanceiroAlteracao($pdo, (int)$id, 'baixa');

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Mensalidade baixada com sucesso.']);
        exit;
    }

    header('Location: ../index.php?msg=baixado');
    exit;
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Lancamento nao informado.']);
    exit;
}

header('Location: ../index.php?msg=erro');
exit;
?>
