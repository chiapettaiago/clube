<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$erro = '';
$sucesso = '';
$dadosFamilia = null;

function buscarSocioOuFamilia(PDO $pdo, string $codigo) {
    $codigo = trim($codigo);

    if ($codigo === '') {
        return null;
    }

    if (ctype_digit($codigo)) {
        $stmt = $pdo->prepare("
            SELECT s.*, ts.nome as tipo_socio
            FROM socios s
            LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
            WHERE s.id = ? AND s.ativo = 1
        ");
        $stmt->execute([(int)$codigo]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row;
        }
    }

    $stmt = $pdo->prepare("
        SELECT s.*, ts.nome as tipo_socio
        FROM socios s
        LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
        WHERE s.numero_titulo = ? AND s.ativo = 1
    ");
    $stmt->execute([$codigo]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return $row;
    }

    if (str_starts_with($codigo, 'FAM_')) {
        $stmt = $pdo->prepare("
            SELECT s.*, ts.nome as tipo_socio
            FROM socios s
            LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
            WHERE s.familia_id = ? AND s.ativo = 1
            ORDER BY s.socio_principal_id IS NOT NULL, s.id ASC
            LIMIT 1
        ");
        $stmt->execute([$codigo]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row;
        }
    }

    return null;
}

function classificarSocio(array $socio): string {
    return !empty($socio['socio_principal_id']) ? 'dependente' : 'socio';
}

function salvarAnexoConvite(array $arquivo): array {
    if (empty($arquivo['name']) || (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, null];
    }

    $uploadDir = __DIR__ . '/../../assets/uploads/convites_anexos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $nomeOriginal = basename((string)$arquivo['name']);
    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $arquivoRelativo = 'assets/uploads/convites_anexos/' . uniqid('convite_', true) . ($ext !== '' ? '.' . $ext : '');
    move_uploaded_file($arquivo['tmp_name'], __DIR__ . '/../../' . $arquivoRelativo);

    return [$arquivoRelativo, $nomeOriginal];
}

if ($_POST) {
    $codigo = trim($_POST['codigo'] ?? '');
    $quantidade = max(1, (int)($_POST['quantidade'] ?? 1));
    $observacao = trim($_POST['observacao'] ?? '');
    $tipoConvite = trim($_POST['tipo_convite'] ?? 'geral');
    $dataBaixa = date('Y-m-d');
    $mesAtual = date('Y-m-01');
    $usaExtra = in_array($tipoConvite, ['casal', 'solteiro', 'extra'], true);

    $socio = buscarSocioOuFamilia($pdo, $codigo);

    if (!$socio) {
        $erro = 'Sócio, título ou família não encontrados.';
    } elseif (!convitesLiberadosFinanceiramente($pdo, (int)$socio['id'], $dataBaixa)) {
        $erro = 'Este sócio está sem liberação financeira para convites no período atual.';
    } else {
        $familiaId = $socio['familia_id'];
        $principalId = $socio['socio_principal_id'] ?: $socio['id'];
        $perfil = classificarSocio($socio);
        $saldo = obterSaldoConvitesFamilia($pdo, (int)$familiaId, $mesAtual);
        $extrasAtivos = calcularConvitesExtrasDisponiveis($pdo, $dataBaixa);
        $regrasAtivas = $extrasAtivos['regras'] ?? [];
        $anexoInfo = salvarAnexoConvite($_FILES['anexo'] ?? []);
        $anexoPath = $anexoInfo[0];
        $anexoNome = $anexoInfo[1];

        if ($saldo['disponiveis_base'] + ($extrasAtivos['disponiveis'] ?? 0) < $quantidade) {
            $erro = 'Não há convites suficientes disponíveis para esta família, considerando as regras ativas.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmtConv = $pdo->prepare("
                    SELECT total_convites, convites_utilizados
                    FROM convites_familia
                    WHERE familia_id = ? AND mes_referencia = ?
                ");
                $stmtConv->execute([$familiaId, $mesAtual]);
                $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

                if (!$conv) {
                    $totalBase = (int)($socio['convites_por_mes'] ?? 0);
                    $stmtIns = $pdo->prepare("
                        INSERT INTO convites_familia (familia_id, socio_principal_id, mes_referencia, total_convites, convites_utilizados)
                        VALUES (?, ?, ?, ?, 0)
                    ");
                    $stmtIns->execute([$familiaId, $principalId, $mesAtual, $totalBase]);
                    $conv = ['total_convites' => $totalBase, 'convites_utilizados' => 0];
                }

                $baseDisponiveis = max(0, (int)$conv['total_convites'] - (int)$conv['convites_utilizados']);
                $qtdBaseUsada = min($quantidade, $baseDisponiveis);
                $qtdExtrasUsada = max(0, $quantidade - $qtdBaseUsada);

                if ($usaExtra && $qtdExtrasUsada > 0 && (int)($extrasAtivos['disponiveis'] ?? 0) <= 0) {
                    throw new RuntimeException('Não existem convites extras ativos para o tipo selecionado.');
                }

                if ($qtdBaseUsada > 0) {
                    $upd = $pdo->prepare("
                        UPDATE convites_familia
                        SET convites_utilizados = convites_utilizados + ?
                        WHERE familia_id = ? AND mes_referencia = ?
                    ");
                    $upd->execute([$qtdBaseUsada, $familiaId, $mesAtual]);
                }

                $extrasConsumidos = 0;
                $regraIndex = 0;
                for ($i = 0; $i < $quantidade; $i++) {
                    $regraId = null;
                    if ($usaExtra) {
                        while ($regraIndex < count($regrasAtivas)) {
                            $regra = $regrasAtivas[$regraIndex];
                            $regraIndex++;
                            if ((int)($regra['disponiveis'] ?? 0) > 0) {
                                $regraId = (int)$regra['id'];
                                $regrasAtivas[$regraIndex - 1]['disponiveis'] = (int)$regra['disponiveis'] - 1;
                                $extrasConsumidos++;
                                break;
                            }
                        }
                    }

                    $uso = $pdo->prepare("
                        INSERT INTO convites_uso
                        (socio_id, familia_id, convite_usado_para, tipo_uso, regra_id, tipo_convite, anexo_path, anexo_nome)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $uso->execute([
                        (int)$socio['id'],
                        $familiaId,
                        $observacao ?: 'Baixa manual de convites',
                        $usaExtra ? 'extra' : 'manual',
                        $regraId,
                        $tipoConvite,
                        $anexoPath,
                        $anexoNome
                    ]);
                }

                $pdo->commit();
                $sucesso = "Baixa realizada com sucesso. {$quantidade} convite(s) lançado(s) para a família.";
                $dadosFamilia = [
                    'nome' => $socio['nome'],
                    'tipo_socio' => $socio['tipo_socio'] ?? '',
                    'perfil' => $perfil,
                    'familia_id' => $familiaId,
                    'total' => (int)$conv['total_convites'],
                    'usados' => (int)$conv['convites_utilizados'] + $qtdBaseUsada,
                    'disponiveis' => max(0, (int)$conv['total_convites'] - ((int)$conv['convites_utilizados'] + $qtdBaseUsada)),
                    'quantidade' => $quantidade,
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baixa de Convites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    <div class="container py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-minus-circle me-2"></i>Baixa de Convites</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    Esta baixa é feita no grupo familiar. Proprietário e dependentes passam a refletir o mesmo saldo da família.
                </div>
                <div class="alert alert-secondary">
                    Convites do tipo <strong>casal</strong>, <strong>solteiro</strong> e <strong>extra</strong> respeitam as regras ativas por data.
                </div>
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
                <?php endif; ?>
                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
                    <?php if ($dadosFamilia): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="small text-muted">Responsável</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($dadosFamilia['nome']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($dadosFamilia['familia_id']) ?></div>
                                    <div class="mt-2">
                                        <span class="badge text-bg-secondary text-uppercase"><?= htmlspecialchars($dadosFamilia['perfil'] ?? 'socio') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="border rounded p-3 bg-light">
                                    <div class="small text-muted mb-2">Saldo da família neste mês</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary">Total: <?= (int)$dadosFamilia['total'] ?></span>
                                        <span class="badge bg-warning text-dark">Usados: <?= (int)$dadosFamilia['usados'] ?></span>
                                        <span class="badge bg-success">Disponíveis: <?= (int)$dadosFamilia['disponiveis'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="post" class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Código, título ou família</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Ex.: 10, 061 ou FAM_XXXX" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantidade</label>
                        <input type="number" name="quantidade" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de convite</label>
                        <select name="tipo_convite" class="form-select">
                            <option value="geral">Geral</option>
                            <option value="casal">Casal</option>
                            <option value="solteiro">Solteiro</option>
                            <option value="extra">Extra</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Observação</label>
                        <input type="text" name="observacao" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Anexo do comprovante</label>
                        <input type="file" name="anexo" class="form-control" accept="image/*,.pdf">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> Confirmar baixa
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

