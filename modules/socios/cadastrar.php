<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// ============================================
// BUSCAR DADOS PARA OS SELECTS
// ============================================

$tipos_socio = $pdo->query("SELECT * FROM tipos_socio ORDER BY nome")->fetchAll();
$parentescos = $pdo->query("SELECT * FROM parentescos ORDER BY nome")->fetchAll();

$sociosPrincipais = $pdo->query("
    SELECT s.id, s.nome, s.numero_titulo, s.cpf, s.convites_por_mes, s.familia_id
    FROM socios s 
    WHERE s.ativo = 1 
    AND s.tipo_socio_id = (SELECT id FROM tipos_socio WHERE nome = 'Proprietário' LIMIT 1)
    ORDER BY s.nome
")->fetchAll();

$config_tipo_id = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'tipo_identificacao'")->fetchColumn();
if(!$config_tipo_id) $config_tipo_id = 'qrcode';

$erro = '';
$sucesso = '';
$tipo_socio_selecionado = $_POST['tipo_socio_id'] ?? '';

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function gerarFamiliaId() {
    return 'FAM_' . strtoupper(uniqid());
}

function limparCpf($cpf) {
    return preg_replace('/[^0-9]/', '', $cpf);
}

function validarCpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// ============================================
// PROCESSAR FORMULÁRIO
// ============================================

if ($_POST) {
    // Dados básicos
    $nome = trim($_POST['nome']);
    $numero_titulo = trim($_POST['numero_titulo']);
    $cpf = limparCpf($_POST['cpf']);
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
    
    // Endereço separado
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
        $erro = '❌ O nome do sócio é obrigatório!';
    } elseif(empty($cpf)) {
        $erro = '❌ O CPF é obrigatório!';
    } elseif(!validarCpf($cpf)) {
        $erro = '❌ CPF inválido! Digite um CPF válido.';
    }
    
    // Buscar nome do tipo de sócio
    $tipoInfo = $pdo->prepare("SELECT nome FROM tipos_socio WHERE id = ?");
    $tipoInfo->execute([$tipo_socio_id]);
    $tipoNome = $tipoInfo->fetchColumn();
    
    if(!$tipoNome) {
        $erro = '❌ Tipo de sócio inválido!';
    }
    
    // Validação para DEPENDENTE
    if(empty($erro) && $tipoNome == 'Dependente') {
        if(empty($socio_principal_id)) {
            $erro = '❌ Para dependentes, é obrigatório selecionar o sócio principal!';
        } elseif(empty($parentesco)) {
            $erro = '❌ Informe o grau de parentesco!';
        } else {
            // Verificar se o sócio principal existe
            $checkPrincipal = $pdo->prepare("SELECT id, numero_titulo FROM socios WHERE id = ? AND ativo = 1");
            $checkPrincipal->execute([$socio_principal_id]);
            if($checkPrincipal->rowCount() == 0) {
                $erro = '❌ Sócio principal não encontrado ou inativo!';
            }
        }
    }
    
    // Validação para PROPRIETÁRIO
    if(empty($erro) && $tipoNome == 'Proprietário' && empty($numero_titulo)) {
        $erro = '❌ O número do título é obrigatório para o sócio proprietário!';
    }
    
    // Verificar duplicidade de CPF
    if(empty($erro)) {
        $checkCpf = $pdo->prepare("SELECT id, nome FROM socios WHERE cpf = ?");
        $checkCpf->execute([$cpf]);
        if($checkCpf->rowCount() > 0) {
            $existente = $checkCpf->fetch();
            $erro = "❌ Já existe um sócio com o CPF '<strong>$cpf</strong>'!<br>
                     <small>Já cadastrado como: {$existente['nome']}</small>";
        }
    }
    
    // ============================================
    // VERIFICAÇÃO DE TÍTULO DUPLICADO (CORRIGIDA)
    // ============================================
    // Dependentes PODEM ter o mesmo título do proprietário
    if(empty($erro) && $tipoNome == 'Proprietário' && !empty($numero_titulo)) {
        $checkTitulo = $pdo->prepare("SELECT id, nome, tipo_socio_id FROM socios WHERE numero_titulo = ?");
        $checkTitulo->execute([$numero_titulo]);
        if($checkTitulo->rowCount() > 0) {
            $existente = $checkTitulo->fetch();
            $erro = "❌ Já existe um sócio com o número de título '<strong>$numero_titulo</strong>'!<br>
                     <small>Já cadastrado como: {$existente['nome']}</small>";
        }
    }
    
    // Validar data
    if(!empty($data_nascimento) && $data_nascimento > date('Y-m-d')) {
        $erro = '❌ A data de nascimento não pode ser futura!';
    }
    
    if(!empty($email_socio) && !filter_var($email_socio, FILTER_VALIDATE_EMAIL)) {
        $erro = '❌ E-mail inválido!';
    }
    
    // ============================================
    // PROCESSAR DEPENDENTE (herdar dados)
    // ============================================
    
    $familia_id = null;
    $convites_familia = $convites_por_mes;
    $titulo_usado = $numero_titulo;
    
    if(empty($erro)) {
        if($tipoNome == 'Dependente' && !empty($socio_principal_id)) {
            $stmtPrincipal = $pdo->prepare("SELECT numero_titulo, familia_id, convites_por_mes FROM socios WHERE id = ?");
            $stmtPrincipal->execute([$socio_principal_id]);
            $principal = $stmtPrincipal->fetch();
            
            if($principal) {
                $titulo_usado = $principal['numero_titulo'];
                $familia_id = $principal['familia_id'];
                $convites_familia = $principal['convites_por_mes'];
            }
        } 
        else if($tipoNome == 'Proprietário') {
            $familia_id = gerarFamiliaId();
            $titulo_usado = $numero_titulo;
        }
        else {
            $familia_id = gerarFamiliaId();
            $titulo_usado = $numero_titulo;
        }
    }
    
    // ============================================
    // UPLOAD DA FOTO
    // ============================================
    
    $foto = '';
    if(empty($erro) && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if(in_array($ext, $allowed)) {
            $foto = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], '../../assets/uploads/' . $foto);
        } else {
            $erro = '❌ Formato de imagem não permitido! Use JPG, PNG ou GIF.';
        }
    }
    
    // ============================================
    // GERAR QR CODE E CÓDIGO DE BARRAS
    // ============================================
    
    if(empty($erro)) {
        if(empty($data_validade)) {
            $data_validade = date('Y-m-d', strtotime('+1 year'));
        }

        // ============================================
        // INSERIR NO BANCO
        // ============================================

        $sql = "INSERT INTO socios (
                    nome, numero_titulo, cpf, data_nascimento, telefone, 
                    email_socio, endereco, sexo, tipo_sanguineo, tipo_socio_id, 
                    socio_principal_id, parentesco, convites_por_mes, data_validade, 
                    familia_id, foto, qrcode, codigo_barras, ativo
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome, $titulo_usado, $cpf, $data_nascimento, $telefone,
            $email_socio, $endereco, $sexo, $tipo_sanguineo, $tipo_socio_id,
            $socio_principal_id, $parentesco, $convites_familia, $data_validade,
            $familia_id, $foto, '', ''
        ]);
        
        $novoId = $pdo->lastInsertId();

        // ============================================
        // GERAR IDENTIFICAÇÃO ÚNICA POR PESSOA
        // ============================================
        $qrPath = 'assets/uploads/carteirinha_qr_' . $novoId . '.png';
        $fullPath = __DIR__ . '/../../' . $qrPath;
        $qrConteudo = $base_url . "modules/carteirinha/validar.php?id=" . $novoId;
        gerarQRCode($qrConteudo, $fullPath);

        $barcodePath = 'assets/uploads/carteirinha_bar_' . $novoId . '.png';
        $fullBarcodePath = __DIR__ . '/../../' . $barcodePath;
        gerarCodigoBarras($novoId, $fullBarcodePath);

        $stmtUpd = $pdo->prepare("UPDATE socios SET qrcode = ?, codigo_barras = ? WHERE id = ?");
        $stmtUpd->execute([$qrPath, $barcodePath, $novoId]);
        
        // Criar registro de convites para a família (apenas proprietários)
        if($tipoNome == 'Proprietário') {
            $mesAtual = date('Y-m-01');
            $stmtConv = $pdo->prepare("
                INSERT INTO convites_familia (familia_id, socio_principal_id, mes_referencia, total_convites, convites_utilizados) 
                VALUES (?, ?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE total_convites = ?
            ");
            $stmtConv->execute([$familia_id, $novoId, $mesAtual, $convites_familia, $convites_familia]);
        }
        
        $sucesso = '✅ Sócio cadastrado com sucesso!';
        
        if(isset($_POST['save_and_new'])) {
            echo "<script>window.location.href = 'cadastrar.php?msg=sucesso';</script>";
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
    <title>Cadastrar Sócio - Sistema de Clube</title>
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
        
        @media (max-width: 768px) {
            .form-section { padding: 18px; }
            .card-header-modern { padding: 18px 20px; }
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
                                <i class="fas fa-user-plus fa-3x me-3 opacity-50"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold">Cadastrar Sócio</h3>
                                <p class="mb-0 opacity-75 mt-1">Preencha os dados abaixo para cadastrar um novo sócio</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 p-lg-5">
                        
                        <?php if($erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show rounded-3">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?= $erro ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($sucesso): ?>
                            <div class="alert alert-success alert-dismissible fade show rounded-3">
                                <i class="fas fa-check-circle me-2"></i> <?= $sucesso ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'sucesso'): ?>
                            <div class="alert alert-success alert-dismissible fade show rounded-3">
                                <i class="fas fa-check-circle me-2"></i> ✅ Sócio cadastrado com sucesso! Continue cadastrando.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
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
                                            <option value="">-- Selecione o tipo de sócio --</option>
                                            <?php foreach($tipos_socio as $tipo): ?>
                                                <option value="<?= $tipo['id'] ?>" <?= ($tipo_socio_selecionado == $tipo['id']) ? 'selected' : '' ?>
                                                        data-nome="<?= $tipo['nome'] ?>">
                                                    <?= $tipo['nome'] ?>
                                                    <?php if($tipo['nome'] == 'Proprietário'): ?> 🏠 (Cria nova família)
                                                    <?php elseif($tipo['nome'] == 'Dependente'): ?> 👨‍👧 (Vincula a um proprietário)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== VÍNCULO (DEPENDENTE) ==================== -->
                            <div id="dependenteSection" style="display: none;">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fas fa-link"></i> Vínculo Familiar
                                    </div>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-users me-2"></i>
                                        <strong>Informação importante:</strong> 
                                        O dependente receberá o mesmo número de título e compartilhará os convites do sócio principal.
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="required-field fw-bold">Sócio Principal</label>
                                            <select name="socio_principal_id" id="socio_principal_id" class="form-select" style="width: 100%;">
                                                <option value="">-- Buscar sócio principal --</option>
                                                <?php foreach($sociosPrincipais as $sp): ?>
                                                    <option value="<?= $sp['id'] ?>" 
                                                            data-titulo="<?= $sp['numero_titulo'] ?>"
                                                            data-convites="<?= $sp['convites_por_mes'] ?>">
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
                                                    <option value="<?= $p['nome'] ?>"><?= $p['nome'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="previewVinculo" class="preview-vinculo" style="display: none;"></div>
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
                                               value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-3" id="campoTitulo">
                                        <label class="required-field fw-bold">Nº do Título</label>
                                        <input type="text" name="numero_titulo" id="numero_titulo" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['numero_titulo'] ?? '') ?>">
                                        <small class="text-muted" id="msgTituloAuto"></small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="required-field fw-bold">CPF</label>
                                        <input type="text" name="cpf" class="form-control cpf" 
                                               value="<?= $_POST['cpf'] ?? '' ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Data Nascimento</label>
                                        <input type="date" name="data_nascimento" class="form-control" 
                                               value="<?= $_POST['data_nascimento'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Validade Carteirinha</label>
                                        <input type="date" name="data_validade" class="form-control" 
                                               value="<?= $_POST['data_validade'] ?? '' ?>">
                                        <small>Deixe em branco = 1 ano</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Sexo</label>
                                        <select name="sexo" class="form-select">
                                            <option value="M" <?= ($_POST['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                                            <option value="F" <?= ($_POST['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Feminino</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Tipo Sanguíneo</label>
                                        <select name="tipo_sanguineo" class="form-select">
                                            <option value="">Selecione</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'A+' ? 'selected' : '' ?>>A+</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'A-' ? 'selected' : '' ?>>A-</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'B+' ? 'selected' : '' ?>>B+</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'B-' ? 'selected' : '' ?>>B-</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'O+' ? 'selected' : '' ?>>O+</option>
                                            <option <?= ($_POST['tipo_sanguineo'] ?? '') == 'O-' ? 'selected' : '' ?>>O-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">Telefone / WhatsApp</label>
                                        <input type="text" name="telefone" class="form-control telefone" 
                                               value="<?= $_POST['telefone'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">E-mail</label>
                                        <input type="email" name="email_socio" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['email_socio'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== ENDEREÇO ==================== -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-map-marker-alt"></i> Endereço
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="fw-bold">CEP</label>
                                        <div class="input-group">
                                            <input type="text" name="cep" id="cep" class="form-control cep" 
                                                   value="<?= $_POST['cep'] ?? '' ?>" placeholder="00000-000">
                                            <button type="button" id="buscarCep" class="btn btn-info">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div id="cepStatus" class="small mt-1"></div>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="fw-bold">Logradouro</label>
                                        <input type="text" name="logradouro" id="logradouro" class="form-control" 
                                               value="<?= $_POST['logradouro'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="fw-bold">Número</label>
                                        <input type="text" name="numero_endereco" id="numero" class="form-control" 
                                               value="<?= $_POST['numero_endereco'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="fw-bold">Complemento</label>
                                        <input type="text" name="complemento" id="complemento" class="form-control" 
                                               value="<?= $_POST['complemento'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fw-bold">Bairro</label>
                                        <input type="text" name="bairro" id="bairro" class="form-control" 
                                               value="<?= $_POST['bairro'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="fw-bold">Cidade</label>
                                        <input type="text" name="cidade" id="cidade" class="form-control" 
                                               value="<?= $_POST['cidade'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold">Estado</label>
                                        <select name="estado" id="estado" class="form-select">
                                            <option value="">Selecione</option>
                                            <option value="AC">AC</option><option value="AL">AL</option><option value="AP">AP</option>
                                            <option value="AM">AM</option><option value="BA">BA</option><option value="CE">CE</option>
                                            <option value="DF">DF</option><option value="ES">ES</option><option value="GO">GO</option>
                                            <option value="MA">MA</option><option value="MT">MT</option><option value="MS">MS</option>
                                            <option value="MG">MG</option><option value="PA">PA</option><option value="PB">PB</option>
                                            <option value="PR">PR</option><option value="PE">PE</option><option value="PI">PI</option>
                                            <option value="RJ">RJ</option><option value="RN">RN</option><option value="RS">RS</option>
                                            <option value="RO">RO</option><option value="RR">RR</option><option value="SC">SC</option>
                                            <option value="SP">SP</option><option value="SE">SE</option><option value="TO">TO</option>
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
                                        <label class="fw-bold">Convites por Mês</label>
                                        <input type="number" name="convites_por_mes" id="convites_por_mes" 
                                               class="form-control" value="<?= $_POST['convites_por_mes'] ?? 5 ?>" min="0" max="50">
                                        <small class="text-muted" id="msgConvites">Para toda a família</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fw-bold">Foto do Sócio</label>
                                        <input type="file" name="foto" class="form-control" accept="image/*">
                                        <small class="text-muted">JPG, PNG, GIF (max 5MB)</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fw-bold">Identificação</label>
                                        <select name="tipo_identificacao" class="form-select">
                                            <option value="qrcode" <?= $config_tipo_id == 'qrcode' ? 'selected' : '' ?>>📱 QR Code</option>
                                            <option value="barras" <?= $config_tipo_id == 'barras' ? 'selected' : '' ?>>📊 Código de Barras</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ==================== BOTÕES ==================== -->
                            <div class="text-center mt-4">
                                <button type="submit" name="save" class="btn btn-gradient btn-lg px-5">
                                    <i class="fas fa-save me-2"></i> Salvar Sócio
                                </button>
                                <button type="submit" name="save_and_new" class="btn btn-outline-success btn-lg px-4 mx-2">
                                    <i class="fas fa-plus-circle me-2"></i> Salvar e Novo
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
                placeholder: "🔍 Digite o nome do sócio principal...",
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
                    $('#cepStatus').html('<span class="text-success">✅ Endereço preenchido automaticamente!</span>');
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
                    $('#msgTituloAuto').html('<i class="fas fa-info-circle text-info"></i> O título será copiado do sócio principal');
                    $('#campoConvites').hide();
                    $('#msgConvites').html('<i class="fas fa-share-alt text-success"></i> Herdará os convites do sócio principal');
                } else {
                    $('#dependenteSection').slideUp();
                    $('#campoTitulo').show();
                    $('#numero_titulo').prop('required', true);
                    $('#msgTituloAuto').html('');
                    $('#campoConvites').show();
                    $('#msgConvites').html('Quantidade de convites mensais para esta família');
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
                        🎟️ Convites: <strong>${convites}/mês</strong> (compartilhados)
                    `).slideDown();
                } else {
                    $('#previewVinculo').slideUp();
                    $('#numero_titulo').val('');
                }
            });
            
            // Validação de data
            $('input[name="data_nascimento"]').on('change', function() {
                var dataNasc = new Date($(this).val());
                var hoje = new Date();
                if(dataNasc > hoje) {
                    alert('❌ A data de nascimento não pode ser futura!');
                    $(this).val('');
                }
            });
            
            // Preview da foto
            $('input[name="foto"]').on('change', function() {
                var file = this.files[0];
                if(file && file.type.startsWith('image/')) {
                    if(file.size > 5 * 1024 * 1024) {
                        alert('❌ A foto não pode ter mais que 5MB!');
                        $(this).val('');
                    }
                }
            });
        });
    </script>
</body>
</html>
