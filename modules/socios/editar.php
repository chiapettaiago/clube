<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');
garantirTabelaSuspensoesSocios($pdo);

// Buscar Sócio para editar
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT s.*, ts.nome as tipo_socio_nome, ts.cor_carteirinha
    FROM socios s
    JOIN tipos_socio ts ON s.tipo_socio_id = ts.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$socio = $stmt->fetch();

if(!$socio) {
    header('Location: index.php');
    exit;
}

// Buscar dados para os selects
$tipos_socio = $pdo->query("SELECT * FROM tipos_socio ORDER BY nome")->fetchAll();
$parentescos = $pdo->query("SELECT * FROM parentescos ORDER BY nome")->fetchAll();

// Buscar Sócios Proprietários (excluindo o próprio)
$sociosPrincipais = $pdo->prepare("
    SELECT id, nome, numero_titulo, cpf, convites_por_mes
    FROM socios 
    WHERE ativo = 1 
    AND tipo_socio_id = (SELECT id FROM tipos_socio WHERE nome = 'Proprietário' LIMIT 1)
    AND id != ?
    ORDER BY nome
");
$sociosPrincipais->execute([$id]);
$sociosPrincipais = $sociosPrincipais->fetchAll();

function syncFamiliaConvites(PDO $pdo, string $familiaId, int $convites, ?int $principalId = null): void {
    if ($familiaId === '') {
        return;
    }

    $stmt = $pdo->prepare("UPDATE socios SET convites_por_mes = ? WHERE familia_id = ?");
    $stmt->execute([$convites, $familiaId]);

    $mesAtual = date('Y-m-01');
    if ($principalId) {
        $check = $pdo->prepare("SELECT id FROM convites_familia WHERE familia_id = ? AND mes_referencia = ?");
        $check->execute([$familiaId, $mesAtual]);
        if ($check->rowCount() > 0) {
            $upd = $pdo->prepare("UPDATE convites_familia SET total_convites = ?, socio_principal_id = ? WHERE familia_id = ? AND mes_referencia = ?");
            $upd->execute([$convites, $principalId, $familiaId, $mesAtual]);
        } else {
            $ins = $pdo->prepare("INSERT INTO convites_familia (familia_id, socio_principal_id, mes_referencia, total_convites, convites_utilizados) VALUES (?, ?, ?, ?, 0)");
            $ins->execute([$familiaId, $principalId, $mesAtual, $convites]);
        }
    }
}

// processar formulário
$erro = '';
$sucesso = '';

if ($_POST) {
    // Dados básicos
    $nome = trim($_POST['nome']);
    $numero_titulo = trim($_POST['numero_titulo']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $telefone = $_POST['telefone'] ?? '';
    $email_socio = trim($_POST['email_socio'] ?? '');
    $sexo = $_POST['sexo'] ?? 'M';
    $tipo_sanguineo = $_POST['tipo_sanguineo'] ?? '';
    $tipo_socio_id = $_POST['tipo_socio_id'];
    $socio_principal_id = !empty($_POST['socio_principal_id']) ? $_POST['socio_principal_id'] : null;
    $parentesco = $_POST['parentesco'] ?? null;
    $convites_por_mes = intval($_POST['convites_por_mes'] ?? 5);
    $data_validade = $_POST['data_validade'] ?? null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $status_acesso = $_POST['status_acesso'] ?? 'ativo';
    $motivo_suspensao = trim($_POST['motivo_suspensao'] ?? '');
    $suspenso_ate = !empty($_POST['suspenso_ate']) ? $_POST['suspenso_ate'] : null;
    if ($status_acesso !== 'suspenso') {
        $motivo_suspensao = '';
        $suspenso_ate = null;
    }
    
    // endereço separado
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero_endereco = trim($_POST['numero_endereco'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    
    // Montar endereço completo
    $endereco = "$logradouro, $numero_endereco";
    if(!empty($complemento)) $endereco .= " - $complemento";
    if(!empty($bairro)) $endereco .= " - $bairro";
    if(!empty($cidade)) $endereco .= " - $cidade";
    if(!empty($estado)) $endereco .= " - $estado";
    if(!empty($cep)) $endereco .= " - CEP: $cep";
    
    // ============================================
    // VALIDAÇÕES
    // ============================================
    
    if(empty($nome)) {
        $erro = '❌ O nome do Sócio é obrigatório!';
    } elseif(empty($cpf)) {
        $erro = '❌ O CPF é obrigatório!';
    }
    
    // Buscar nome do tipo de Sócio
    $tipoInfo = $pdo->prepare("SELECT nome FROM tipos_socio WHERE id = ?");
    $tipoInfo->execute([$tipo_socio_id]);
    $tipoNome = $tipoInfo->fetchColumn();
    
    if(!$tipoNome) {
        $erro = '❌ Tipo de Sócio inválido!';
    }
    
    // Validação para DEPENDENTE
    if(empty($erro) && $tipoNome == 'Dependente') {
        if(empty($socio_principal_id)) {
            $erro = '❌ Para dependentes, é obrigatório selecionar o Sócio principal!';
        } elseif(empty($parentesco)) {
            $erro = '❌ Informe o grau de parentesco!';
        } else {
            // Verificar se o Sócio principal existe
            $checkPrincipal = $pdo->prepare("SELECT id FROM socios WHERE id = ? AND ativo = 1");
            $checkPrincipal->execute([$socio_principal_id]);
            if($checkPrincipal->rowCount() == 0) {
                $erro = '❌ Sócio principal não encontrado ou inativo!';
            }
        }
    }
    
    // Verificar duplicidade de CPF (excluindo o próprio)
    if(empty($erro)) {
        $checkCpf = $pdo->prepare("SELECT id, nome FROM socios WHERE cpf = ? AND id != ?");
        $checkCpf->execute([$cpf, $id]);
        if($checkCpf->rowCount() > 0) {
            $existente = $checkCpf->fetch();
            $erro = "❌ Já existe um Sócio com o CPF '<strong>$cpf</strong>'!<br>
                     <small>Já cadastrado como: {$existente['nome']}</small>";
        }
    }
    
    // Verificar duplicidade de título apenas para proprietário
    if(empty($erro) && $tipoNome == 'Proprietário' && !empty($numero_titulo)) {
        $checkTitulo = $pdo->prepare("SELECT id, nome FROM socios WHERE numero_titulo = ? AND id != ?");
        $checkTitulo->execute([$numero_titulo, $id]);
        if($checkTitulo->rowCount() > 0) {
            $existente = $checkTitulo->fetch();
            $erro = "❌ Já existe um Sócio com o número de Título '<strong>$numero_titulo</strong>'!<br>
                     <small>Já cadastrado como: {$existente['nome']}</small>";
        }
    }
    
    // ============================================
    // PROCESSAR DEPENDENTE
    // ============================================
    
    $familia_id = $socio['familia_id'];
    $convites_familia = $convites_por_mes;
    
    if(empty($erro) && $tipoNome == 'Dependente' && !empty($socio_principal_id)) {
        $stmtPrincipal = $pdo->prepare("SELECT numero_titulo, familia_id, convites_por_mes FROM socios WHERE id = ?");
        $stmtPrincipal->execute([$socio_principal_id]);
        $principal = $stmtPrincipal->fetch();
        
        if($principal) {
            $numero_titulo = $principal['numero_titulo'];
            $familia_id = $principal['familia_id'];
            $convites_familia = $principal['convites_por_mes'];
        }
    }
    
    // ============================================
    // UPLOAD DA FOTO (no final do formulário)
    // ============================================
    
    $foto = $socio['foto'];
    if(empty($erro) && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if(in_array($ext, $allowed)) {
            // Remover foto antiga
            if($foto && file_exists('../../assets/uploads/' . $foto)) {
                unlink('../../assets/uploads/' . $foto);
            }
            $foto = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], '../../assets/uploads/' . $foto);
        } else {
            $erro = '❌ Formato de imagem não permitido! Use JPG, PNG ou GIF.';
        }
    }
    
    // ============================================
    // ATUALIZAR BANCO
    // ============================================
    
    if(empty($erro)) {
        if ($tipoNome === 'Proprietário') {
            $familia_id = $familia_id ?: $socio['familia_id'] ?: ('FAM_' . strtoupper(uniqid()));
            syncFamiliaConvites($pdo, $familia_id, $convites_por_mes, (int)$id);
        } elseif ($tipoNome === 'Dependente' && !empty($familia_id)) {
            $convites_familia = (int)$convites_por_mes;
        }

        $sql = "UPDATE socios SET 
                    nome = ?, numero_titulo = ?, cpf = ?, data_nascimento = ?, 
                    telefone = ?, email_socio = ?, endereco = ?, sexo = ?, 
                    tipo_sanguineo = ?, tipo_socio_id = ?, socio_principal_id = ?, 
                    parentesco = ?, convites_por_mes = ?, data_validade = ?, 
                    familia_id = ?, foto = ?, ativo = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome, $numero_titulo, $cpf, $data_nascimento,
            $telefone, $email_socio, $endereco, $sexo,
            $tipo_sanguineo, $tipo_socio_id, $socio_principal_id,
            $parentesco, $convites_familia, $data_validade,
            $familia_id, $foto, $ativo, $id
        ]);

        $qrPath = 'assets/uploads/carteirinha_qr_' . $id . '.png';
        $barPath = 'assets/uploads/carteirinha_bar_' . $id . '.png';
        $fullQrPath = __DIR__ . '/../../' . $qrPath;
        $fullBarPath = __DIR__ . '/../../' . $barPath;
        $qrConteudo = $base_url . 'modules/carteirinha/validar.php?id=' . $id;
        gerarQRCode($qrConteudo, $fullQrPath);
        gerarCodigoBarras($id, $fullBarPath);

        $stmtAssets = $pdo->prepare("UPDATE socios SET qrcode = ?, codigo_barras = ? WHERE id = ?");
            $stmtAssets->execute([$qrPath, $barPath, $id]);

        $stmtSusp = $pdo->prepare("UPDATE socios SET status_acesso = ?, motivo_suspensao = ?, suspenso_ate = ?, suspenso_em = ?, suspenso_por = ? WHERE id = ?");
        $stmtSusp->execute([
            $status_acesso,
            $motivo_suspensao ?: null,
            $suspenso_ate,
            $status_acesso === 'suspenso' ? date('Y-m-d H:i:s') : null,
            $_SESSION['usuario_id'] ?? null,
            $id
        ]);
        
        $sucesso = '✅ Sócio atualizado com sucesso!';
        
        if(isset($_POST['save_and_continue'])) {
            echo "<script>window.location.href = 'editar.php?id=$id&msg=sucesso';</script>";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Sócio - Sistema de Clube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #7209b7;
            --success: #4c9f70;
            --warning: #f8961e;
            --danger: #f72585;
            --info: #4895ef;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card-modern {
            border: none;
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.15);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 25px 30px;
            color: white;
        }
        
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            color: var(--primary);
        }
        
        .form-section-title i {
            margin-right: 10px;
            color: var(--warning);
        }
        
        .required-field:after {
            content: " *";
            color: var(--danger);
            font-weight: bold;
        }
        
        .foto-destaque {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 25px;
            color: white;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .foto-perfil {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin-bottom: 15px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
        }
        
        .dependente-card {
            background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%);
            border-left: 4px solid var(--warning);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .preview-vinculo {
            background: #e8f4fd;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--info);
        }
        
        @media (max-width: 768px) {
            .form-section { padding: 18px; }
            .card-header-modern { padding: 18px 20px; }
            .foto-perfil { width: 100px; height: 100px; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-edit fa-3x me-3 opacity-50"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold">Editar Sócio</h3>
                                <p class="mb-0 opacity-75 mt-1">Atualize os dados do Sócio</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 p-lg-5">
                        
                        <!-- Alertas -->
                        <?php if($erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show rounded-3">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?= $erro ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($sucesso || isset($_GET['msg'])): ?>
                            <div class="alert alert-success alert-dismissible fade show rounded-3">
                                <i class="fas fa-check-circle me-2"></i> <?= $sucesso ?: 'Sócio atualizado com sucesso!' ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ==================== FOTO EM DESTAQUE (TOPO) ==================== -->
                        <div class="foto-destaque">
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <?php if($socio['foto'] && file_exists('../../assets/uploads/' . $socio['foto'])): ?>
                                        <img src="../../assets/uploads/<?= $socio['foto'] ?>" class="foto-perfil" alt="Foto do Sócio">
                                    <?php else: ?>
                                        <div class="foto-perfil bg-white d-flex align-items-center justify-content-center mx-auto">
                                            <i class="fas fa-user fa-4x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8 text-center text-md-start">
                                    <h4><?= htmlspecialchars($socio['nome']) ?></h4>
                                    <p class="mb-1">
                                        <i class="fas fa-id-card me-2"></i> Título: <strong><?= $socio['numero_titulo'] ?></strong>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-tag me-2"></i> Tipo: <strong><?= $socio['tipo_socio_nome'] ?></strong>
                                        <span class="badge ms-2" style="background-color: <?= $socio['cor_carteirinha'] ?>; color: #000;">
                                            <?= $socio['tipo_socio_nome'] ?>
                                        </span>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar me-2"></i> Cadastrado em: <?= date('d/m/Y', strtotime($socio['data_cadastro'] ?? $socio['id'] . ' days ago')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="formSocio">
                            
                            <!-- ==================== TIPO DE SÓCIO ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-tag"></i> Tipo de Sócio
                                </div>
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="required-field fw-bold">Selecione o Tipo</label>
                                        <select name="tipo_socio_id" id="tipo_socio_id" class="form-select form-select-lg" required>
                                            <option value="">-- Selecione o tipo de Sócio --</option>
                                            <?php foreach($tipos_socio as $tipo): ?>
                                                <option value="<?= $tipo['id'] ?>" <?= ($socio['tipo_socio_id'] == $tipo['id']) ? 'selected' : '' ?>
                                                        data-nome="<?= $tipo['nome'] ?>">
                                                    <?= $tipo['nome'] ?>
                                                    <?php if($tipo['nome'] == 'Proprietário'): ?> 🏠 (Cria nova família)
                                                    <?php elseif($tipo['nome'] == 'Dependente'): ?> 👨‍👧 (Vincula a um Proprietário)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch mt-4 pt-2">
                                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" <?= $socio['ativo'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="ativo">
                                                <i class="fas fa-check-circle text-success"></i> Sócio Ativo
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-ban"></i> Acesso do Sócio
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="fw-bold">Status de Acesso</label>
                                        <select name="status_acesso" class="form-select">
                                            <option value="ativo" <?= (($socio['status_acesso'] ?? 'ativo') === 'ativo') ? 'selected' : '' ?>>Ativo</option>
                                            <option value="suspenso" <?= (($socio['status_acesso'] ?? '') === 'suspenso') ? 'selected' : '' ?>>Suspenso</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="fw-bold">Suspenso até</label>
                                        <input type="date" name="suspenso_ate" class="form-control" value="<?= htmlspecialchars($socio['suspenso_ate'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="fw-bold">Motivo da suspensão</label>
                                        <textarea name="motivo_suspensao" class="form-control" rows="2" placeholder="Motivo que aparecerá na validação"><?= htmlspecialchars($socio['motivo_suspensao'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== VÍNCULO (DEPENDENTE) ==================== -->
                            <div id="dependenteSection" style="display: <?= $socio['tipo_socio_nome'] == 'Dependente' ? 'block' : 'none' ?>;">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fas fa-link"></i> Vínculo Familiar
                                    </div>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-users me-2"></i>
                                        <strong>Informação importante:</strong>
                                        O dependente receberá o mesmo número de Título e compartilhará os convites do sócio principal.
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="required-field fw-bold">Sócio Principal</label>
                                            <select name="socio_principal_id" id="socio_principal_id" class="form-select" style="width: 100%;">
                                                <option value="">-- Buscar Sócio principal --</option>
                                                <?php foreach($sociosPrincipais as $sp): ?>
                                                    <option value="<?= $sp['id'] ?>" 
                                                            data-titulo="<?= $sp['numero_titulo'] ?>"
                                                            data-convites="<?= $sp['convites_por_mes'] ?>"
                                                            <?= ($socio['socio_principal_id'] == $sp['id']) ? 'selected' : '' ?>>
                                                        👤 <?= htmlspecialchars($sp['nome']) ?> (Título: <?= $sp['numero_titulo'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Digite para buscar</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="required-field fw-bold">Parentesco</label>
                                            <select name="parentesco" id="parentesco" class="form-select">
                                                <option value="">-- Selecione --</option>
                                                <?php foreach($parentescos as $p): ?>
                                                    <option value="<?= $p['nome'] ?>" <?= ($socio['parentesco'] == $p['nome']) ? 'selected' : '' ?>>
                                                        <?= $p['nome'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="previewVinculo" class="preview-vinculo" style="display: <?= $socio['socio_principal_id'] ? 'block' : 'none' ?>;">
                                        <i class="fas fa-handshake me-2"></i>
                                        <span id="previewTexto"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== DADOS PESSOAIS ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-user-circle"></i> Dados Pessoais
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="required-field fw-bold">Nome Completo</label>
                                        <input type="text" name="nome" class="form-control" 
                                               value="<?= htmlspecialchars($socio['nome']) ?>" required>
                                    </div>
                                    <div class="col-md-3" id="campoTitulo">
                                        <label class="required-field fw-bold">Nº do Título</label>
                                        <input type="text" name="numero_titulo" id="numero_titulo" class="form-control" 
                                               value="<?= htmlspecialchars($socio['numero_titulo']) ?>">
                                        <small class="text-muted" id="msgTituloAuto"></small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="required-field fw-bold">CPF</label>
                                        <input type="text" name="cpf" class="form-control cpf" 
                                               value="<?= $socio['cpf'] ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Data Nascimento</label>
                                        <input type="date" name="data_nascimento" class="form-control" 
                                               value="<?= $socio['data_nascimento'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Validade Carteirinha</label>
                                        <input type="date" name="data_validade" class="form-control" 
                                               value="<?= $socio['data_validade'] ?>">
                                        <small>Deixe em branco = 1 ano</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Sexo</label>
                                        <select name="sexo" class="form-select">
                                            <option value="M" <?= $socio['sexo'] == 'M' ? 'selected' : '' ?>>Masculino</option>
                                            <option value="F" <?= $socio['sexo'] == 'F' ? 'selected' : '' ?>>Feminino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Tipo Sanguíneo</label>
                                        <select name="tipo_sanguineo" class="form-select">
                                            <option value="">Selecione</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'A+' ? 'selected' : '' ?>>A+</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'A-' ? 'selected' : '' ?>>A-</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'B+' ? 'selected' : '' ?>>B+</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'B-' ? 'selected' : '' ?>>B-</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'O+' ? 'selected' : '' ?>>O+</option>
                                            <option <?= $socio['tipo_sanguineo'] == 'O-' ? 'selected' : '' ?>>O-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">Telefone / WhatsApp</label>
                                        <input type="text" name="telefone" class="form-control telefone" 
                                               value="<?= $socio['telefone'] ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">E-mail</label>
                                        <input type="email" name="email_socio" class="form-control" 
                                               value="<?= htmlspecialchars($socio['email_socio']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== ENDEREÇO ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-map-marker-alt"></i> endereço
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="fw-bold">CEP</label>
                                        <div class="input-group">
                                            <input type="text" name="cep" id="cep" class="form-control cep" 
                                                   value="<?= $socio['cep'] ?? '' ?>" placeholder="00000-000">
                                            <button type="button" id="buscarCep" class="btn btn-info">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div id="cepStatus" class="small mt-1"></div>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="fw-bold">Logradouro</label>
                                        <input type="text" name="logradouro" id="logradouro" class="form-control" 
                                               value="<?= $socio['logradouro'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="fw-bold">número</label>
                                        <input type="text" name="numero_endereco" id="numero" class="form-control" 
                                               value="<?= $socio['numero_endereco'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="fw-bold">Complemento</label>
                                        <input type="text" name="complemento" id="complemento" class="form-control" 
                                               value="<?= $socio['complemento'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fw-bold">Bairro</label>
                                        <input type="text" name="bairro" id="bairro" class="form-control" 
                                               value="<?= $socio['bairro'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="fw-bold">Cidade</label>
                                        <input type="text" name="cidade" id="cidade" class="form-control" 
                                               value="<?= $socio['cidade'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Estado</label>
                                        <select name="estado" id="estado" class="form-select">
                                            <option value="">Selecione</option>
                                            <option value="AC" <?= ($socio['estado'] ?? '') == 'AC' ? 'selected' : '' ?>>AC</option>
                                            <option value="AL" <?= ($socio['estado'] ?? '') == 'AL' ? 'selected' : '' ?>>AL</option>
                                            <option value="AP" <?= ($socio['estado'] ?? '') == 'AP' ? 'selected' : '' ?>>AP</option>
                                            <option value="AM" <?= ($socio['estado'] ?? '') == 'AM' ? 'selected' : '' ?>>AM</option>
                                            <option value="BA" <?= ($socio['estado'] ?? '') == 'BA' ? 'selected' : '' ?>>BA</option>
                                            <option value="CE" <?= ($socio['estado'] ?? '') == 'CE' ? 'selected' : '' ?>>CE</option>
                                            <option value="DF" <?= ($socio['estado'] ?? '') == 'DF' ? 'selected' : '' ?>>DF</option>
                                            <option value="ES" <?= ($socio['estado'] ?? '') == 'ES' ? 'selected' : '' ?>>ES</option>
                                            <option value="GO" <?= ($socio['estado'] ?? '') == 'GO' ? 'selected' : '' ?>>GO</option>
                                            <option value="MA" <?= ($socio['estado'] ?? '') == 'MA' ? 'selected' : '' ?>>MA</option>
                                            <option value="MT" <?= ($socio['estado'] ?? '') == 'MT' ? 'selected' : '' ?>>MT</option>
                                            <option value="MS" <?= ($socio['estado'] ?? '') == 'MS' ? 'selected' : '' ?>>MS</option>
                                            <option value="MG" <?= ($socio['estado'] ?? '') == 'MG' ? 'selected' : '' ?>>MG</option>
                                            <option value="PA" <?= ($socio['estado'] ?? '') == 'PA' ? 'selected' : '' ?>>PA</option>
                                            <option value="PB" <?= ($socio['estado'] ?? '') == 'PB' ? 'selected' : '' ?>>PB</option>
                                            <option value="PR" <?= ($socio['estado'] ?? '') == 'PR' ? 'selected' : '' ?>>PR</option>
                                            <option value="PE" <?= ($socio['estado'] ?? '') == 'PE' ? 'selected' : '' ?>>PE</option>
                                            <option value="PI" <?= ($socio['estado'] ?? '') == 'PI' ? 'selected' : '' ?>>PI</option>
                                            <option value="RJ" <?= ($socio['estado'] ?? '') == 'RJ' ? 'selected' : '' ?>>RJ</option>
                                            <option value="RN" <?= ($socio['estado'] ?? '') == 'RN' ? 'selected' : '' ?>>RN</option>
                                            <option value="RS" <?= ($socio['estado'] ?? '') == 'RS' ? 'selected' : '' ?>>RS</option>
                                            <option value="RO" <?= ($socio['estado'] ?? '') == 'RO' ? 'selected' : '' ?>>RO</option>
                                            <option value="RR" <?= ($socio['estado'] ?? '') == 'RR' ? 'selected' : '' ?>>RR</option>
                                            <option value="SC" <?= ($socio['estado'] ?? '') == 'SC' ? 'selected' : '' ?>>SC</option>
                                            <option value="SP" <?= ($socio['estado'] ?? '') == 'SP' ? 'selected' : '' ?>>SP</option>
                                            <option value="SE" <?= ($socio['estado'] ?? '') == 'SE' ? 'selected' : '' ?>>SE</option>
                                            <option value="TO" <?= ($socio['estado'] ?? '') == 'TO' ? 'selected' : '' ?>>TO</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== CARTEIRINHA ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-id-card"></i> Configurações da Carteirinha
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4" id="campoConvites">
                                        <label class="fw-bold">
                                            <i class="fas fa-ticket-alt text-success"></i> Convites por Mês
                                        </label>
                                        <input type="number" name="convites_por_mes" id="convites_por_mes" 
                                               class="form-control" value="<?= $socio['convites_por_mes'] ?>" min="0" max="50">
                                        <small class="text-muted" id="msgConvites">Para toda a família</small>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="fw-bold"><i class="fas fa-qrcode"></i> Tipo de Identificação</label>
                                        <select name="tipo_identificacao" class="form-select">
                                            <option value="qrcode">📱 QR Code</option>
                                            <option value="barras">📊 código de Barras</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== UPLOAD DA FOTO (FINAL) ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-camera"></i> Alterar Foto
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Envie uma nova foto para substituir a atual. Formatos permitidos: JPG, PNG, GIF (max 5MB)
                                        </div>
                                        <input type="file" name="foto" class="form-control" accept="image/*">
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-upload"></i> Clique para escolher uma nova foto do Sócio
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== BOTÕES ==================== -->
                            <div class="text-center mt-4">
                                <button type="submit" name="save" class="btn btn-gradient btn-lg px-5">
                                    <i class="fas fa-save me-2"></i> Salvar Alterações
                                </button>
                                <button type="submit" name="save_and_continue" class="btn btn-outline-success btn-lg px-4 mx-2">
                                    <i class="fas fa-sync-alt me-2"></i> Salvar e Continuar
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="fas fa-times me-2"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Máscaras
            $('.cpf').mask('000.000.000-00');
            $('.telefone').mask('(00) 00000-0000');
            $('.cep').mask('00000-000');
            
            // Select2
            $('#socio_principal_id').select2({
                placeholder: "🔍 Digite o nome do Sócio principal...",
                allowClear: true,
                theme: "bootstrap-5",
                width: '100%'
            });
            
            // Busca de CEP
            $('#buscarCep').click(function() {
                var cep = $('#cep').val().replace(/\D/g, '');
                if(cep.length !== 8) {
                    $('#cepStatus').html('<span class="text-danger">❌ CEP inválido! Digite 8 números.</span>');
                    return;
                }
                $('#cepStatus').html('<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Buscando endereço...</span>');
                $.getJSON(`https://viacep.com.br/ws/${cep}/json/`, function(data) {
                    if(data.erro) {
                        $('#cepStatus').html('<span class="text-danger">❌ CEP não encontrado!</span>');
                        return;
                    }
                    $('#logradouro').val(data.logradouro || '');
                    $('#bairro').val(data.bairro || '');
                    $('#cidade').val(data.localidade || '');
                    $('#estado').val(data.uf || '');
                    $('#cepStatus').html('<span class="text-success">✅ endereço preenchido automaticamente!</span>');
                    $('#numero').focus();
                }).fail(function() {
                    $('#cepStatus').html('<span class="text-danger">❌ Erro ao buscar CEP. Tente novamente.</span>');
                });
            });
            
            // Buscar ao digitar Enter no CEP
            $('#cep').keypress(function(e) {
                if(e.which === 13) {
                    e.preventDefault();
                    $('#buscarCep').click();
                }
            });
            
            // Dependente
            function toggleDependenteFields() {
                var tipoTexto = $('#tipo_socio_id option:selected').text();
                
                if(tipoTexto.includes('Dependente')) {
                    $('#dependenteSection').slideDown();
                    $('#campoTitulo').hide();
                    $('#numero_titulo').prop('required', false);
                    $('#msgTituloAuto').html('<i class="fas fa-info-circle text-info"></i> O Título será copiado do Sócio principal');
                    $('#campoConvites').hide();
                    $('#msgConvites').html('<i class="fas fa-share-alt text-success"></i> Herdará os convites do sócio principal');
                    $('#cep').prop('readonly', true);
                    $('#logradouro, #numero, #complemento, #bairro, #cidade, #estado').prop('disabled', true);
                } else {
                    $('#dependenteSection').slideUp();
                    $('#campoTitulo').show();
                    $('#numero_titulo').prop('required', true);
                    $('#msgTituloAuto').html('');
                    $('#campoConvites').show();
                    $('#msgConvites').html('Quantidade de convites mensais para esta família');
                    $('#cep').prop('readonly', false);
                    $('#logradouro, #numero, #complemento, #bairro, #cidade, #estado').prop('disabled', false);
                }
            }
            
            $('#tipo_socio_id').change(toggleDependenteFields);
            toggleDependenteFields();
            
            // Preview do vínculo
            $('#socio_principal_id').change(function() {
                var option = $(this).find('option:selected');
                var socioNome = option.text();
                var titulo = option.data('titulo');
                var convites = option.data('convites');
                
                if($(this).val()) {
                    $('#numero_titulo').val(titulo);
                    $('#convites_por_mes').val(convites);
                    $('#previewVinculo').html(`
                        <i class="fas fa-handshake me-2"></i>
                        <strong>🔗 Vínculo estabelecido:</strong><br>
                        Dependente de: ${socioNome}<br>
                        📌 Título: <strong>${titulo}</strong><br>
                        🎟️ Convites: <strong>${convites}/Mês</strong> (compartilhados)
                    `).slideDown();
                } else {
                    $('#previewVinculo').slideUp();
                    if($('#tipo_socio_id option:selected').text().includes('Dependente')) {
                        $('#numero_titulo').val('');
                    }
                }
            });
            
            // Inicializar preview se já houver vínculo
            <?php if($socio['socio_principal_id'] && $socio['tipo_socio_nome'] == 'Dependente'): ?>
                var option = $('#socio_principal_id option:selected');
                $('#previewVinculo').html(`
                    <i class="fas fa-handshake me-2"></i>
                    <strong>🔗 Vínculo atual:</strong><br>
                    Dependente de: ${option.text()}<br>
                    📌 Título: <strong>${option.data('titulo')}</strong><br>
                    🎟️ Convites: <strong>${option.data('convites')}/Mês</strong> (compartilhados)
                `).show();
            <?php endif; ?>
            
            // Validação de data
            $('input[name="data_nascimento"]').on('change', function() {
                var dataNasc = new Date($(this).val());
                var hoje = new Date();
                if(dataNasc > hoje) {
                    alert('❌ A data de nascimento não pode ser futura!');
                    $(this).val('');
                }
            });
            
            // Preview da nova foto
            $('input[name="foto"]').on('change', function() {
                var file = this.files[0];
                if(file && file.type.startsWith('image/')) {
                    if(file.size > 5 * 1024 * 1024) {
                        alert('❌ A foto não pode ter mais que 5MB!');
                        $(this).val('');
                    } else {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            $('.foto-perfil').attr('src', e.target.result);
                        };
                        reader.readAsDataURL(file);
                    }
                }
            });
        });
    </script>
</body>
</html>


