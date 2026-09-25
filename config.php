<?php
session_start();

// Configuração do banco de dados
$host = '192.185.176.160';
$dbname = 'appcas29_clubedov_bancodedados';
$username = 'appcas29_clube';
$password = 'S?BqPwWoB%f^e^Uc';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// URL base do sistema. O Clube pode estar instalado tanto na raiz de um
// subdomínio quanto em /clube; não mantenha esse caminho fixo.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = preg_split('#/(?:modules|api|cron)/#', $scriptName, 2)[0];
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
$basePath = rtrim($basePath, '/');
$base_url = $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');

// Função para verificar permissões
function verificarPermissao($permissao) {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['permissoes'])) {
        return false;
    }
    return in_array($permissao, $_SESSION['permissoes']);
}

function usuarioPodeVerMotivoSuspensao(): bool {
    $permissoes = $_SESSION['permissoes'] ?? [];
    $permissoesPrivilegiadas = [
        'diretoria',
        'diretor',
        'secretaria',
        'cadastros',
        'financeiro',
        'admin',
    ];

    foreach ($permissoesPrivilegiadas as $permissao) {
        if (in_array($permissao, $permissoes, true)) {
            return true;
        }
    }

    return false;
}

// ============================================
// FUNÇÃO GERAR QR CODE - INSIRA AQUI!
// ============================================
function gerarQRCode($dados, $arquivo) {
    $dir = dirname($arquivo);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Tentar usar biblioteca local
    if (file_exists(__DIR__ . '/vendor/phpqrcode/qrlib.php')) {
        require_once __DIR__ . '/vendor/phpqrcode/qrlib.php';
        QRcode::png($dados, $arquivo, QR_ECLEVEL_L, 10);
        return $arquivo;
    }
    
    // Fallback: API do QuickChart.io
    $qrUrl = "https://quickchart.io/qr?text=" . urlencode($dados) . "&size=300&margin=2";
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $qrUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $qrContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if($httpCode == 200 && $qrContent !== false) {
            file_put_contents($arquivo, $qrContent);
            return $arquivo;
        }
    }
    
    // Fallback final: criar QR Code simples com GD
    $image = imagecreate(300, 300);
    $bg = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 255);
    
    imagefilledrectangle($image, 0, 0, 299, 299, $bg);
    
    // Cantos de posicionamento
    imagefilledrectangle($image, 10, 10, 50, 50, $black);
    imagefilledrectangle($image, 12, 12, 48, 48, $bg);
    imagefilledrectangle($image, 240, 10, 280, 50, $black);
    imagefilledrectangle($image, 242, 12, 278, 48, $bg);
    imagefilledrectangle($image, 10, 240, 50, 280, $black);
    imagefilledrectangle($image, 12, 242, 48, 278, $bg);
    
    // Texto informativo
    imagestring($image, 5, 70, 140, "CARTEIRINHA", $blue);
    imagestring($image, 3, 70, 165, substr($dados, 0, 45), $black);
    
    imagepng($image, $arquivo);
    imagedestroy($image);
    
    return $arquivo;
}
// ============================================

// Função para gerar código de barras
function gerarCodigoBarras($numero, $arquivo) {
    $dir = dirname($arquivo);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $image = imagecreate(400, 100);
    $bg = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefilledrectangle($image, 0, 0, 399, 99, $bg);
    
    // Gerar barras simples
    $numStr = (string)$numero;
    $pos = 20;
    for($i = 0; $i < strlen($numStr); $i++) {
        $code = ord($numStr[$i]);
        for($b = 0; $b < 8; $b++) {
            if($code & (1 << $b)) {
                $height = 40 + ($b % 3) * 15;
                imagefilledrectangle($image, $pos, 20, $pos + 4, $height, $black);
            }
            $pos += 5;
        }
    }
    
    imagestring($image, 5, 150, 75, $numero, $black);
    imagepng($image, $arquivo);
    imagedestroy($image);
    
    return $arquivo;
}

function garantirConfigFinanceiraPadrao(PDO $pdo) {
    $defaults = [
        'financeiro_dia_vencimento' => '12',
        'financeiro_qtd_parcelas_alerta' => '2',
        'financeiro_modo_alerta' => 'alerta',
        'financeiro_mensagem_alerta' => 'O responsavel deve regularizar as mensalidades em atraso.',
        'financeiro_valor_mensalidade' => '0.00',
        'financeiro_responsavel_email' => '',
        'financeiro_reajuste_dependente_ativo' => '0',
        'financeiro_reajuste_idade_max_masculino' => '19',
        'financeiro_reajuste_idade_max_feminino' => '19',
        'financeiro_reajuste_valor_dependente_masculino' => '0.00',
        'financeiro_reajuste_valor_dependente_feminino' => '0.00',
        'financeiro_reajuste_parentescos' => 'filho,filha,enteado,enteada'
    ];

    $check = $pdo->prepare("SELECT COUNT(*) FROM config_financeiro WHERE chave = ?");
    $insert = $pdo->prepare("INSERT INTO config_financeiro (chave, valor) VALUES (?, ?)");

    foreach ($defaults as $chave => $valor) {
        $check->execute([$chave]);
        if ((int)$check->fetchColumn() === 0) {
            $insert->execute([$chave, $valor]);
        }
    }
}

function garantirTabelaHistoricoFinanceiro(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historico_financeiro (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lancamento_id INT NOT NULL,
            socio_id INT NOT NULL,
            usuario_id INT NULL,
            acao VARCHAR(20) NOT NULL,
            descricao VARCHAR(255) NULL,
            valor DECIMAL(10,2) NOT NULL DEFAULT 0,
            data_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            observacao TEXT NULL,
            INDEX idx_lancamento (lancamento_id),
            INDEX idx_socio (socio_id),
            INDEX idx_acao (acao),
            INDEX idx_data (data_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function obterSocioFinanceiroPrincipal(PDO $pdo, int $socioId): int {
    static $cache = [];

    if (isset($cache[$socioId])) {
        return $cache[$socioId];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(socio_principal_id, 0), id) AS socio_financeiro_id FROM socios WHERE id = ?");
    $stmt->execute([$socioId]);
    $alvo = (int)($stmt->fetchColumn() ?: $socioId);

    $cache[$socioId] = $alvo;
    return $alvo;
}

function calcularReajusteMensalidadeDependentes(PDO $pdo, int $socioPrincipalId, ?string $referencia = null): array {
    $config = obterConfiguracaoFinanceira($pdo);
    $ativo = (int)($config['financeiro_reajuste_dependente_ativo'] ?? 0) === 1;

    $referencia = $referencia ?: date('Y-m-01');
    $dataReferencia = new DateTimeImmutable($referencia);
    $dataLimite = $dataReferencia->format('Y-m-d');

    $resultado = [
        'ativo' => $ativo,
        'itens' => [],
        'total_extra' => 0.0,
        'descricao' => '',
    ];

    if (!$ativo) {
        return $resultado;
    }

    $stmt = $pdo->prepare("
        SELECT id, nome, sexo, data_nascimento
        FROM socios
        WHERE socio_principal_id = ? AND ativo = 1
        ORDER BY nome ASC
    ");
    $stmt->execute([$socioPrincipalId]);
    $dependentes = $stmt->fetchAll();
    $parentescosPermitidos = array_filter(array_map(
        static fn($item) => trim((string)$item),
        explode(',', (string)($config['financeiro_reajuste_parentescos'] ?? ''))
    ));

    foreach ($dependentes as $dep) {
        if (empty($dep['data_nascimento'])) {
            continue;
        }

        $parentesco = strtolower(trim((string)($dep['parentesco'] ?? '')));
        if ($parentesco === '') {
            continue;
        }

        $parentescoNormalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $parentesco);
        $parentescoNormalizado = strtolower($parentescoNormalizado ?: $parentesco);
        $parentescoNormalizado = preg_replace('/[^a-z]/', '', $parentescoNormalizado);

        $permitido = false;
        foreach ($parentescosPermitidos as $item) {
            $itemNormalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower($item));
            $itemNormalizado = preg_replace('/[^a-z]/', '', $itemNormalizado ?: strtolower($item));
            if ($itemNormalizado !== '' && str_contains($parentescoNormalizado, $itemNormalizado)) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            continue;
        }

        $idade = (new DateTimeImmutable($dep['data_nascimento']))->diff($dataReferencia)->y;
        $sexo = strtoupper(trim((string)($dep['sexo'] ?? '')));

        $idadeMinima = $sexo === 'F'
            ? (int)($config['financeiro_reajuste_idade_max_feminino'] ?? 0)
            : (int)($config['financeiro_reajuste_idade_max_masculino'] ?? 0);

        $valorExtra = $sexo === 'F'
            ? (float)str_replace(',', '.', $config['financeiro_reajuste_valor_dependente_feminino'] ?? '0')
            : (float)str_replace(',', '.', $config['financeiro_reajuste_valor_dependente_masculino'] ?? '0');

        if ($idadeMinima > 0 && $idade >= $idadeMinima && $valorExtra > 0) {
            $resultado['itens'][] = [
                'dependente_id' => (int)$dep['id'],
                'nome' => $dep['nome'],
                'sexo' => $sexo,
                'idade' => $idade,
                'idade_minima' => $idadeMinima,
                'valor_extra' => $valorExtra,
            ];
            $resultado['total_extra'] += $valorExtra;
        }
    }

    if (!empty($resultado['itens'])) {
        $partes = [];
        foreach ($resultado['itens'] as $item) {
            $partes[] = $item['nome'] . ' (' . $item['idade'] . 'a / mínimo ' . $item['idade_minima'] . 'a)';
        }
        $resultado['descricao'] = implode('; ', $partes);
    }

    return $resultado;
}

function obterConfiguracaoFinanceira(PDO $pdo): array {
    garantirConfigFinanceiraPadrao($pdo);
    garantirTabelaHistoricoFinanceiro($pdo);

    $config = [];
    $stmt = $pdo->query("SELECT chave, valor FROM config_financeiro");
    foreach ($stmt as $row) {
        $config[$row['chave']] = $row['valor'];
    }

    return $config;
}

function garantirTabelaSuspensoesSocios(PDO $pdo): void {
    static $executado = false;
    if ($executado) {
        return;
    }
    $executado = true;

    $colunas = [
        'status_acesso' => "ALTER TABLE socios ADD COLUMN status_acesso VARCHAR(20) NOT NULL DEFAULT 'ativo'",
        'motivo_suspensao' => "ALTER TABLE socios ADD COLUMN motivo_suspensao VARCHAR(255) NULL",
        'suspenso_ate' => "ALTER TABLE socios ADD COLUMN suspenso_ate DATE NULL",
        'suspenso_em' => "ALTER TABLE socios ADD COLUMN suspenso_em DATETIME NULL",
        'suspenso_por' => "ALTER TABLE socios ADD COLUMN suspenso_por INT NULL",
    ];

    foreach ($colunas as $coluna => $sql) {
        $check = $pdo->prepare("SHOW COLUMNS FROM socios LIKE ?");
        $check->execute([$coluna]);
        if ($check->rowCount() === 0) {
            $pdo->exec($sql);
        }
    }
}

function socioEstaSuspenso(array $socio): bool {
    $status = strtolower(trim((string)($socio['status_acesso'] ?? 'ativo')));
    if (in_array($status, ['suspenso', 'bloqueado', 'inativo'], true)) {
        return true;
    }
    if (!empty($socio['suspenso_ate']) && $socio['suspenso_ate'] >= date('Y-m-d')) {
        return true;
    }
    return false;
}

function garantirTabelaRegrasConvites(PDO $pdo): void {
    static $executado = false;
    if ($executado) {
        return;
    }
    $executado = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS convites_regras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            tipo_convite VARCHAR(50) NOT NULL DEFAULT 'geral',
            quantidade INT NOT NULL DEFAULT 1,
            data_inicio DATE NOT NULL,
            data_fim DATE NOT NULL,
            aplica_familia TINYINT(1) NOT NULL DEFAULT 1,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            observacao TEXT NULL,
            anexo_path VARCHAR(255) NULL,
            anexo_nome VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_datas (data_inicio, data_fim),
            INDEX idx_ativo (ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $colunasUso = [
        'regra_id' => "ALTER TABLE convites_uso ADD COLUMN regra_id INT NULL",
        'tipo_convite' => "ALTER TABLE convites_uso ADD COLUMN tipo_convite VARCHAR(50) NULL",
        'anexo_path' => "ALTER TABLE convites_uso ADD COLUMN anexo_path VARCHAR(255) NULL",
        'anexo_nome' => "ALTER TABLE convites_uso ADD COLUMN anexo_nome VARCHAR(255) NULL",
    ];

    foreach ($colunasUso as $coluna => $sql) {
        $check = $pdo->prepare("SHOW COLUMNS FROM convites_uso LIKE ?");
        try {
            $check->execute([$coluna]);
            if ($check->rowCount() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            // tabela pode ainda não existir; será criada pelos módulos de convites
        }
    }
}

function calcularConvitesExtrasAtivos(PDO $pdo, ?string $data = null): array {
    garantirTabelaRegrasConvites($pdo);

    $data = $data ?: date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT *
        FROM convites_regras
        WHERE ativo = 1
          AND data_inicio <= ?
          AND data_fim >= ?
        ORDER BY data_inicio ASC, id ASC
    ");
    $stmt->execute([$data, $data]);
    $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $porTipo = [];
    foreach ($regras as $regra) {
        $qtd = max(0, (int)($regra['quantidade'] ?? 0));
        $total += $qtd;
        $tipo = trim((string)($regra['tipo_convite'] ?? 'geral')) ?: 'geral';
        $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $qtd;
    }

    return [
        'regras' => $regras,
        'total' => $total,
        'por_tipo' => $porTipo,
    ];
}

function calcularConvitesExtrasDisponiveis(PDO $pdo, ?string $data = null): array {
    $ativos = calcularConvitesExtrasAtivos($pdo, $data);
    $disponiveis = 0;
    $regras = [];

    foreach ($ativos['regras'] as $regra) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM convites_uso WHERE regra_id = ?");
        $stmt->execute([(int)$regra['id']]);
        $usados = (int)$stmt->fetchColumn();
        $restante = max(0, (int)$regra['quantidade'] - $usados);
        $disponiveis += $restante;
        $regra['usados'] = $usados;
        $regra['disponiveis'] = $restante;
        $regras[] = $regra;
    }

    $ativos['regras'] = $regras;
    $ativos['disponiveis'] = $disponiveis;
    return $ativos;
}

function convitesLiberadosFinanceiramente(PDO $pdo, int $socioId, ?string $dataCorte = null): bool {
    $dataCorte = new DateTimeImmutable($dataCorte ?: date('Y-m-d'));
    $diaLimite = 12;
    $dataLimite = $dataCorte->format('Y-m') . '-' . str_pad((string)$diaLimite, 2, '0', STR_PAD_LEFT);
    $principal = obterSocioFinanceiroPrincipal($pdo, $socioId);

    if ((int)$dataCorte->format('d') <= $diaLimite) {
        $dataBuscaLimite = (new DateTimeImmutable($dataLimite))->modify('-1 day')->format('Y-m-d');
    } else {
        $dataBuscaLimite = $dataLimite;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM lancamentos
        WHERE socio_id = ?
          AND tipo = 'receita'
          AND status = 'pendente'
          AND data_vencimento <= ?
    ");
    $stmt->execute([$principal, $dataBuscaLimite]);

    return (int)$stmt->fetchColumn() === 0;
}

function obterSaldoConvitesFamilia(PDO $pdo, int $familiaId, ?string $dataRef = null): array {
    garantirTabelaRegrasConvites($pdo);

    $dataRef = $dataRef ?: date('Y-m-01');
    $inicioMes = (new DateTimeImmutable($dataRef))->format('Y-m-01');
    $fimMes = (new DateTimeImmutable($dataRef))->format('Y-m-t');

    $stmt = $pdo->prepare("
        SELECT total_convites, convites_utilizados
        FROM convites_familia
        WHERE familia_id = ? AND mes_referencia = ?
    ");
    $stmt->execute([$familiaId, $inicioMes]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_convites' => 0, 'convites_utilizados' => 0];

    $extras = calcularConvitesExtrasAtivos($pdo, $inicioMes);

    $stmtUso = $pdo->prepare("
        SELECT COUNT(*)
        FROM convites_uso
        WHERE familia_id = ?
          AND data_uso >= ?
          AND data_uso <= ?
    ");
    $stmtUso->execute([$familiaId, $inicioMes . ' 00:00:00', $fimMes . ' 23:59:59']);
    $usadosExtras = (int)$stmtUso->fetchColumn();

    $totalBase = (int)($base['total_convites'] ?? 0);
    $utilizadosBase = (int)($base['convites_utilizados'] ?? 0);
    $totalDisponivel = max(0, $totalBase - $utilizadosBase);
    $totalComExtras = $totalDisponivel + (int)$extras['total'];

    return [
        'mes_referencia' => $inicioMes,
        'total_base' => $totalBase,
        'utilizados_base' => $utilizadosBase,
        'disponiveis_base' => $totalDisponivel,
        'extras_ativos' => $extras['total'],
        'extras_usados' => $usadosExtras,
        'disponiveis_com_extras' => max(0, $totalComExtras - $usadosExtras),
        'regras' => $extras['regras'],
    ];
}

function calcularMensalidadesEmAtraso(PDO $pdo, int $socioId, ?int $diaVencimento = null): array {
    $config = obterConfiguracaoFinanceira($pdo);
    $socioId = obterSocioFinanceiroPrincipal($pdo, $socioId);
    $dia = $diaVencimento ?? (int)($config['financeiro_dia_vencimento'] ?? 12);
    $dia = max(1, min(31, $dia));

    $hoje = new DateTimeImmutable('today');
    $diasNoMes = (int)$hoje->format('t');
    $diaBase = min($dia, $diasNoMes);
    $dataBase = new DateTimeImmutable($hoje->format('Y-m') . '-' . str_pad((string)$diaBase, 2, '0', STR_PAD_LEFT));
    if ((int)$hoje->format('d') <= $diaBase) {
        $dataBase = $dataBase->modify('-1 month');
    }

    $inicioBusca = $dataBase->modify('-12 months')->format('Y-m-d');
    $fimBusca = $dataBase->modify('last day of this month')->format('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT id, descricao, valor, data_vencimento, status
        FROM lancamentos
        WHERE socio_id = ?
          AND tipo = 'receita'
          AND status = 'pendente'
          AND data_vencimento BETWEEN ? AND ?
          AND data_vencimento <= ?
        ORDER BY data_vencimento ASC
    ");
    $stmt->execute([$socioId, $inicioBusca, $fimBusca, $fimBusca]);

    $parcelas = $stmt->fetchAll();

    return [
        'parcelas' => $parcelas,
        'qtd_parcelas' => count($parcelas),
        'dia_vencimento' => $dia,
        'data_base' => $dataBase->format('Y-m-d'),
        'data_limite' => $fimBusca,
    ];
}

function gerarMensalidadesAutomaticas(PDO $pdo, ?string $referencia = null): array {
    $config = obterConfiguracaoFinanceira($pdo);
    $dia = (int)($config['financeiro_dia_vencimento'] ?? 12);
    $dia = max(1, min(31, $dia));
    $valorPadrao = (float)str_replace(',', '.', $config['financeiro_valor_mensalidade'] ?? '0');
    $referencia = $referencia ?: date('Y-m-01');
    $vencimentoBase = new DateTimeImmutable($referencia);
    $diaBase = min($dia, (int)$vencimentoBase->format('t'));
    $vencimento = $vencimentoBase->setDate((int)$vencimentoBase->format('Y'), (int)$vencimentoBase->format('m'), $diaBase)->format('Y-m-d');

    $categoriaId = null;
    $cat = $pdo->query("SELECT id FROM categorias_financeiras WHERE tipo = 'receita' AND ativo = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($cat) {
        $categoriaId = (int)$cat;
    }

    $stmtSocios = $pdo->query("
        SELECT s.id, s.nome, s.numero_titulo, s.tipo_socio_id, ts.nome as tipo_socio_nome
        FROM socios s
        JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
        WHERE s.ativo = 1
          AND (s.socio_principal_id IS NULL OR s.socio_principal_id = 0)
    ");

    $criados = 0;
    $ignorados = 0;
    foreach ($stmtSocios as $socio) {
        $descricao = 'Mensalidade ' . date('m/Y', strtotime($referencia)) . ' - ' . $socio['nome'];
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM lancamentos
            WHERE socio_id = ? AND tipo = 'receita' AND status = 'pendente'
            AND DATE_FORMAT(data_vencimento, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
            AND descricao LIKE ?
        ");
        $check->execute([$socio['id'], $vencimento, 'Mensalidade ' . date('m/Y', strtotime($referencia)) . '%']);
        if ((int)$check->fetchColumn() > 0) {
            $ignorados++;
            continue;
        }

        $valor = $valorPadrao > 0 ? $valorPadrao : 0.00;
        $stmt = $pdo->prepare("
            INSERT INTO lancamentos
            (socio_id, descricao, valor, tipo, categoria_id, data_lancamento, data_vencimento, status, lancado_por)
            VALUES (?, ?, ?, 'receita', ?, CURRENT_DATE(), ?, 'pendente', NULL)
        ");
        $stmt->execute([
            $socio['id'],
            $descricao,
            $valor,
            $categoriaId,
            $vencimento
        ]);
        $criados++;
    }

    return [
        'criados' => $criados,
        'ignorados' => $ignorados,
        'referencia' => $referencia,
        'vencimento' => $vencimento,
        'valor_padrao' => $valorPadrao,
    ];
}

// Garante a estrutura mínima de acesso em instalações novas. O banco pode já
// conter tabelas de outra aplicação (por exemplo, WordPress), portanto usamos
// CREATE TABLE IF NOT EXISTS e não alteramos nem removemos tabelas existentes.
function garantirEstruturaDeUsuarios(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            senha VARCHAR(255) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usuarios_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissoes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            UNIQUE KEY uq_permissoes_nome (nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuario_permissoes (
            usuario_id INT UNSIGNED NOT NULL,
            permissao_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (usuario_id, permissao_id),
            CONSTRAINT fk_usuario_permissoes_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_usuario_permissoes_permissao
                FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $permissao = $pdo->prepare("INSERT IGNORE INTO permissoes (nome) VALUES (?)");
    foreach (['admin', 'cadastros', 'financeiro', 'relatorios', 'secretaria', 'diretoria', 'diretor'] as $nome) {
        $permissao->execute([$nome]);
    }
}

try {
    garantirEstruturaDeUsuarios($pdo);
} catch (PDOException $e) {
    die('Não foi possível preparar as tabelas de acesso do sistema. Verifique se o usuário do banco possui permissão para criar tabelas.');
}

// Criar usuário admin padrão se não existir
$stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
if((int)$stmt->fetchColumn() === 0) {
    $senhaHash = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha, ativo) VALUES ('Administrador', 'admin@admin.com', ?, 1)")->execute([$senhaHash]);
}

// Em instalações recém-criadas, também garante que o administrador padrão
// tenha todas as permissões, mesmo se ele já existia antes desta atualização.
$pdo->exec("
    INSERT IGNORE INTO usuario_permissoes (usuario_id, permissao_id)
    SELECT u.id, p.id
    FROM usuarios u
    CROSS JOIN permissoes p
    WHERE u.email = 'admin@admin.com'
");
?>
