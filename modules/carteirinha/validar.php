<?php
require_once '../../config.php';

function obterSocioParaValidacao(PDO $pdo, string $codigo): ?array {
    $codigo = trim($codigo);
    if ($codigo === '') return null;

    if (ctype_digit($codigo)) {
        $stmt = $pdo->prepare("\n            SELECT s.*, ts.nome as tipo_socio\n            FROM socios s\n            LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id\n            WHERE s.id = ? AND s.ativo = 1\n            LIMIT 1\n        ");
        $stmt->execute([(int)$codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    $stmt = $pdo->prepare("\n        SELECT s.*, ts.nome as tipo_socio\n        FROM socios s\n        LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id\n        WHERE s.numero_titulo = ? AND s.ativo = 1\n        LIMIT 1\n    ");
    $stmt->execute([$codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    if (str_starts_with($codigo, 'FAM_')) {
        $stmt = $pdo->prepare("\n            SELECT s.*, ts.nome as tipo_socio\n            FROM socios s\n            LEFT JOIN tipos_socio ts ON s.tipo_socio_id = ts.id\n            WHERE s.familia_id = ? AND s.ativo = 1\n            ORDER BY s.socio_principal_id IS NOT NULL, s.id ASC\n            LIMIT 1\n        ");
        $stmt->execute([$codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    return null;
}

function renderPage(array $dados, string $tipo = 'info'): void {
    $nome = $dados['nome'] ?? 'Validação de carteirinha';
    $statusText = $dados['status_text'] ?? 'Pronto para leitura';
    $mensagem = $dados['mensagem'] ?? 'Digite ou leia o código da carteirinha';
    $foto = $dados['foto'] ?? '../../assets/img/avatar.png';
    $tipoSocio = $dados['tipo_socio'] ?? 'Sócio';
    $tipoRegistro = $dados['tipo_registro'] ?? 'socio';
    $mostrarMotivo = !empty($dados['mostrar_motivo']);
    $motivoSuspensao = $dados['motivo_suspensao'] ?? null;
    $suspensoAte = $dados['suspenso_ate'] ?? null;
    $statusClass = $tipo === 'error' ? 'bad' : ($tipo === 'warning' ? 'warn' : 'good');
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Validar Carteirinha</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%); min-height: 100vh; }
            .wrap { max-width: 820px; margin: 0 auto; padding: 18px; }
            .panel { border: 0; border-radius: 28px; box-shadow: 0 20px 60px rgba(15,23,42,.12); overflow: hidden; }
            .hero { background: linear-gradient(135deg, #2563eb 0%, #111827 100%); color: #fff; padding: 22px; }
            .avatar { width: 120px; height: 120px; object-fit: cover; border-radius: 24px; border: 4px solid rgba(255,255,255,.65); background:#fff; }
            .status-badge { display:inline-flex; align-items:center; gap:8px; padding:.55rem .85rem; border-radius:999px; font-weight:700; }
            .status-badge.good { background:#16a34a; color:#fff; }
            .status-badge.warn { background:#f59e0b; color:#111827; }
            .status-badge.bad { background:#ef4444; color:#fff; }
            .info-band { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-top:12px; }
            .info-pill { background: rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.14); border-radius:16px; padding:10px 12px; }
            .info-pill small { display:block; opacity:.75; margin-bottom:2px; }
            .info-pill strong { font-size:1rem; }
            @media (max-width: 576px) { .info-band { grid-template-columns:1fr; } .wrap { padding:12px; } }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="text-uppercase small text-muted">Controle de Acesso</div>
                    <h1 class="h3 mb-0">Validar Carteirinha</h1>
                </div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill">Voltar</a>
            </div>
            <div class="panel">
                <div class="hero">
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <img src="<?= htmlspecialchars($foto) ?>" class="avatar" alt="Foto">
                        <div class="flex-grow-1">
                            <div class="status-badge <?= $statusClass ?>">
                                <i class="fas <?= $statusClass === 'bad' ? 'fa-times-circle' : ($statusClass === 'warn' ? 'fa-triangle-exclamation' : 'fa-check-circle') ?>"></i>
                                <?= htmlspecialchars($statusText) ?>
                            </div>
                            <h2 class="mt-3 mb-1"><?= htmlspecialchars($nome) ?></h2>
                            <div class="opacity-75"><?= htmlspecialchars($tipoSocio) ?></div>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark text-uppercase"><?= htmlspecialchars($tipoRegistro) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="info-band">
                        <div class="info-pill"><small>Status</small><strong><?= htmlspecialchars($statusText) ?></strong></div>
                        <div class="info-pill"><small>Título</small><strong><?= htmlspecialchars((string)($dados['numero_titulo'] ?? '')) ?></strong></div>
                        <div class="info-pill"><small>Convites</small><strong><?= (int)($dados['convites_familia_disponiveis'] ?? 0) ?> disponíveis</strong></div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="alert alert-<?= $statusClass === 'good' ? 'success' : ($statusClass === 'warn' ? 'warning' : 'danger') ?> mb-0">
                        <?= htmlspecialchars($mensagem) ?>
                    </div>
                    <?php if ($mostrarMotivo && ($motivoSuspensao || $suspensoAte)): ?>
                        <div class="alert alert-secondary mt-3 mb-0">
                            <?php if ($motivoSuspensao): ?>
                                <div><strong>Motivo:</strong> <?= htmlspecialchars($motivoSuspensao) ?></div>
                            <?php endif; ?>
                            <?php if ($suspensoAte): ?>
                                <div><strong>Suspenso até:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($suspensoAte))) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

$codigo = trim($_GET['codigo'] ?? $_GET['id'] ?? '');
$isJson = isset($_GET['format']) && $_GET['format'] === 'json';

$socio = obterSocioParaValidacao($pdo, $codigo);
if (!$socio) {
    $erro = [
        'status' => 'error',
        'mensagem' => 'Sócio não encontrado ou inativo!',
        'codigo_recebido' => $codigo,
        'status_text' => 'NÃO ENCONTRADO',
        'cor' => '#ef4444',
    ];
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode($erro);
        exit;
    }
    renderPage($erro, 'error');
    exit;
}

$mesAtual = date('Y-m-01');
$stmtConv = $pdo->prepare("SELECT total_convites, convites_utilizados FROM convites_familia WHERE familia_id = ? AND mes_referencia = ?");
$stmtConv->execute([$socio['familia_id'], $mesAtual]);
$convitesFamilia = $stmtConv->fetch(PDO::FETCH_ASSOC) ?: [];

$totalConvitesFamilia = (int)($convitesFamilia['total_convites'] ?? ($socio['convites_por_mes'] ?? 0));
$convitesUsadosFamilia = (int)($convitesFamilia['convites_utilizados'] ?? 0);
$extrasAtivos = function_exists('calcularConvitesExtrasAtivos') ? calcularConvitesExtrasAtivos($pdo, date('Y-m-d')) : ['total' => 0, 'regras' => []];
$convitesDisponiveis = ($totalConvitesFamilia - $convitesUsadosFamilia) + (int)$extrasAtivos['total'];

$temSuspensao = function_exists('socioEstaSuspenso') ? socioEstaSuspenso($socio) : false;
$mostrarMotivo = function_exists('usuarioPodeVerMotivoSuspensao') ? usuarioPodeVerMotivoSuspensao() : false;
if ($temSuspensao) {
    $payload = [
        'status' => 'negado',
        'mensagem' => 'Acesso suspenso',
        'status_text' => 'SUSPENSO',
        'nome' => $socio['nome'],
        'tipo_socio' => $socio['tipo_socio'] ?? 'Sócio',
        'tipo_registro' => $socio['socio_principal_id'] ? 'dependente' : 'socio',
        'numero_titulo' => $socio['numero_titulo'],
        'foto' => $socio['foto'] ? '../../assets/uploads/' . $socio['foto'] : null,
        'convites_familia_disponiveis' => $convitesDisponiveis,
        'convites_extras_ativos' => (int)$extrasAtivos['total'],
        'mostrar_motivo' => $mostrarMotivo,
        'motivo_suspensao' => $mostrarMotivo ? ($socio['motivo_suspensao'] ?? null) : null,
        'suspenso_ate' => $mostrarMotivo ? ($socio['suspenso_ate'] ?? null) : null,
    ];
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
    renderPage($payload, 'error');
    exit;
}

$response = [
    'status' => 'ok',
    'status_text' => 'LIBERADO',
    'mensagem' => 'ACESSO LIBERADO! Convites disponíveis para a família: ' . $convitesDisponiveis,
    'id' => $socio['id'],
    'nome' => $socio['nome'],
    'tipo_socio' => $socio['tipo_socio'] ?? 'Sócio',
    'tipo_registro' => $socio['socio_principal_id'] ? 'dependente' : 'socio',
    'numero_titulo' => $socio['numero_titulo'],
    'familia_id' => $socio['familia_id'],
    'convites_familia_total' => $totalConvitesFamilia,
    'convites_familia_usados' => $convitesUsadosFamilia,
    'convites_familia_disponiveis' => $convitesDisponiveis,
    'convites_extras_ativos' => (int)$extrasAtivos['total'],
    'foto' => $socio['foto'] ? '../../assets/uploads/' . $socio['foto'] : null,
    'mostrar_motivo' => $mostrarMotivo,
    'motivo_suspensao' => $mostrarMotivo ? ($socio['motivo_suspensao'] ?? null) : null,
    'suspenso_ate' => $mostrarMotivo ? ($socio['suspenso_ate'] ?? null) : null,
];

if ($isJson) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

renderPage($response, 'info');
