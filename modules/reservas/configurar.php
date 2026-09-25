<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

// Buscar configurações gerais
$config = [];
$cfg = $pdo->query("SELECT chave, valor FROM config_reservas");
foreach($cfg as $c) {
    $config[$c['chave']] = $c['valor'];
}

// Buscar tipos de espaço
$tipos = $pdo->query("SELECT * FROM tipos_espaco ORDER BY nome")->fetchAll();

// Buscar tipos de sócio para descontos
$tipos_socio = $pdo->query("SELECT * FROM tipos_socio ORDER BY nome")->fetchAll();

// Processar configurações gerais
if($_POST && isset($_POST['salvar_config'])) {
    $chaves = ['antecedencia_minima', 'cancelamento_maximo', 'multa_cancelamento', 'maximo_reservas_ativas'];
    foreach($chaves as $chave) {
        $valor = $_POST[$chave];
        $stmt = $pdo->prepare("UPDATE config_reservas SET valor = ? WHERE chave = ?");
        $stmt->execute([$valor, $chave]);
    }
    $msg = "Configurações salvas com sucesso!";
}

// Processar novo espaço
if($_POST && isset($_POST['salvar_espaco'])) {
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $capacidade = $_POST['capacidade'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_fim = $_POST['horario_fim'];
    $descricao = $_POST['descricao'];
    $tipo_cobranca = $_POST['tipo_cobranca'];
    
    // Preços
    $valor_hora = 0;
    $valor_hora_socio = 0;
    $valor_hora_externo = 0;
    
    if($tipo_cobranca == 'unico') {
        $valor_hora = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora']));
        $valor_hora_socio = $valor_hora;
        $valor_hora_externo = $valor_hora;
    } elseif($tipo_cobranca == 'diferenciado') {
        $valor_hora_socio = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora_socio']));
        $valor_hora_externo = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora_externo']));
        $valor_hora = $valor_hora_socio;
    }
    
    // Descontos
    $tem_desconto = isset($_POST['tem_desconto']) ? 1 : 0;
    $desconto_percentual = intval($_POST['desconto_percentual'] ?? 0);
    $desconto_tipo_socio_id = !empty($_POST['desconto_tipo_socio_id']) ? $_POST['desconto_tipo_socio_id'] : null;
    
    // Promoção
    $tem_promocao = isset($_POST['tem_promocao']) ? 1 : 0;
    $valor_promocional = 0;
    $data_promocao_inicio = null;
    $data_promocao_fim = null;
    
    if($tem_promocao) {
        $valor_promocional = str_replace(',', '.', str_replace('.', '', $_POST['valor_promocional']));
        $data_promocao_inicio = $_POST['data_promocao_inicio'];
        $data_promocao_fim = $_POST['data_promocao_fim'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO espacos (nome, tipo, capacidade, valor_hora, valor_hora_socio, valor_hora_externo, 
                            horario_inicio, horario_fim, descricao, ativo,
                            tem_desconto, desconto_percentual, desconto_tipo_socio_id,
                            valor_promocional, data_promocao_inicio, data_promocao_fim)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nome, $tipo, $capacidade, $valor_hora, $valor_hora_socio, $valor_hora_externo,
        $horario_inicio, $horario_fim, $descricao,
        $tem_desconto, $desconto_percentual, $desconto_tipo_socio_id,
        $valor_promocional, $data_promocao_inicio, $data_promocao_fim
    ]);
    
    $espaco_id = $pdo->lastInsertId();
    
    // Salvar descontos adicionais por tipo de sócio
    if(isset($_POST['desconto_tipo']) && is_array($_POST['desconto_tipo'])) {
        for($i = 0; $i < count($_POST['desconto_tipo']); $i++) {
            $tipo_id = $_POST['desconto_tipo'][$i];
            $valor_desc = intval($_POST['desconto_valor'][$i]);
            if($tipo_id && $valor_desc > 0) {
                $stmt = $pdo->prepare("INSERT INTO descontos_espacos (espaco_id, tipo_socio_id, desconto_percentual) VALUES (?, ?, ?)");
                $stmt->execute([$espaco_id, $tipo_id, $valor_desc]);
            }
        }
    }
    
    $msgEspaco = "Espaço adicionado com sucesso!";
}

// Processar editar espaço
if(isset($_GET['editar_espaco'])) {
    $id = $_GET['editar_espaco'];
    $espacoEdit = $pdo->prepare("SELECT * FROM espacos WHERE id = ?");
    $espacoEdit->execute([$id]);
    $espacoEdit = $espacoEdit->fetch();
    
    // Buscar descontos adicionais
    $descontosAdd = $pdo->prepare("SELECT * FROM descontos_espacos WHERE espaco_id = ?");
    $descontosAdd->execute([$id]);
    $descontosAdd = $descontosAdd->fetchAll();
}

// Processar atualizar espaço
if($_POST && isset($_POST['atualizar_espaco'])) {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $capacidade = $_POST['capacidade'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_fim = $_POST['horario_fim'];
    $descricao = $_POST['descricao'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $tipo_cobranca = $_POST['tipo_cobranca'];
    
    // Preços
    $valor_hora = 0;
    $valor_hora_socio = 0;
    $valor_hora_externo = 0;
    
    if($tipo_cobranca == 'unico') {
        $valor_hora = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora']));
        $valor_hora_socio = $valor_hora;
        $valor_hora_externo = $valor_hora;
    } elseif($tipo_cobranca == 'diferenciado') {
        $valor_hora_socio = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora_socio']));
        $valor_hora_externo = str_replace(',', '.', str_replace('.', '', $_POST['valor_hora_externo']));
        $valor_hora = $valor_hora_socio;
    }
    
    // Descontos
    $tem_desconto = isset($_POST['tem_desconto']) ? 1 : 0;
    $desconto_percentual = intval($_POST['desconto_percentual'] ?? 0);
    $desconto_tipo_socio_id = !empty($_POST['desconto_tipo_socio_id']) ? $_POST['desconto_tipo_socio_id'] : null;
    
    // Promoção
    $tem_promocao = isset($_POST['tem_promocao']) ? 1 : 0;
    $valor_promocional = 0;
    $data_promocao_inicio = null;
    $data_promocao_fim = null;
    
    if($tem_promocao) {
        $valor_promocional = str_replace(',', '.', str_replace('.', '', $_POST['valor_promocional']));
        $data_promocao_inicio = $_POST['data_promocao_inicio'];
        $data_promocao_fim = $_POST['data_promocao_fim'];
    }
    
    $stmt = $pdo->prepare("
        UPDATE espacos SET 
            nome = ?, tipo = ?, capacidade = ?, 
            valor_hora = ?, valor_hora_socio = ?, valor_hora_externo = ?,
            horario_inicio = ?, horario_fim = ?, descricao = ?, ativo = ?,
            tem_desconto = ?, desconto_percentual = ?, desconto_tipo_socio_id = ?,
            valor_promocional = ?, data_promocao_inicio = ?, data_promocao_fim = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $nome, $tipo, $capacidade,
        $valor_hora, $valor_hora_socio, $valor_hora_externo,
        $horario_inicio, $horario_fim, $descricao, $ativo,
        $tem_desconto, $desconto_percentual, $desconto_tipo_socio_id,
        $valor_promocional, $data_promocao_inicio, $data_promocao_fim, $id
    ]);
    
    // Remover descontos antigos e adicionar novos
    $pdo->prepare("DELETE FROM descontos_espacos WHERE espaco_id = ?")->execute([$id]);
    
    if(isset($_POST['desconto_tipo']) && is_array($_POST['desconto_tipo'])) {
        for($i = 0; $i < count($_POST['desconto_tipo']); $i++) {
            $tipo_id = $_POST['desconto_tipo'][$i];
            $valor_desc = intval($_POST['desconto_valor'][$i]);
            if($tipo_id && $valor_desc > 0) {
                $stmt = $pdo->prepare("INSERT INTO descontos_espacos (espaco_id, tipo_socio_id, desconto_percentual) VALUES (?, ?, ?)");
                $stmt->execute([$id, $tipo_id, $valor_desc]);
            }
        }
    }
    
    $msgEspaco = "Espaço atualizado com sucesso!";
    header('Location: configurar.php?msg=editado');
    exit;
}

// Buscar espaços
$espacos = $pdo->query("SELECT * FROM espacos ORDER BY nome")->fetchAll();

// Processar exclusão
if(isset($_GET['excluir_espaco'])) {
    $id = $_GET['excluir_espaco'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE espaco_id = ?");
    $stmt->execute([$id]);
    $totalReservas = $stmt->fetchColumn();
    
    if($totalReservas > 0) {
        $pdo->prepare("UPDATE espacos SET ativo = 0 WHERE id = ?")->execute([$id]);
        $msg = "Espaço desativado pois possui reservas vinculadas.";
    } else {
        $pdo->prepare("DELETE FROM espacos WHERE id = ?")->execute([$id]);
        $msg = "Espaço excluído com sucesso!";
    }
    header('Location: configurar.php?msg=' . urlencode($msg));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-modern { border: none; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header-modern { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border-radius: 20px 20px 0 0 !important; padding: 15px 20px; }
        .form-section { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .form-section-title { font-size: 1rem; font-weight: 600; margin-bottom: 15px; color: #2c3e50; border-left: 4px solid #f39c12; padding-left: 12px; }
        .btn-gradient { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border: none; color: white; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); color: white; }
        .preco-box { background: white; border-radius: 10px; padding: 15px; margin-top: 10px; border: 1px solid #e9ecef; }
        .desconto-item { background: #fff3cd; border-radius: 10px; padding: 10px; margin-bottom: 10px; }
        .money { text-align: right; }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Configurações Gerais -->
            <div class="col-md-5">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configurações Gerais</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($msg)): ?>
                            <div class="alert alert-success"><?= $msg ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="salvar_config" value="1">
                            <div class="mb-3">
                                <label>Antecedência Mínima (horas)</label>
                                <input type="number" name="antecedencia_minima" class="form-control" value="<?= $config['antecedencia_minima'] ?? '24' ?>" required>
                                <small>Horas mínimas de antecedência para fazer uma reserva</small>
                            </div>
                            <div class="mb-3">
                                <label>Cancelamento Máximo (horas)</label>
                                <input type="number" name="cancelamento_maximo" class="form-control" value="<?= $config['cancelamento_maximo'] ?? '12' ?>" required>
                                <small>Horas máximas para cancelamento sem multa</small>
                            </div>
                            <div class="mb-3">
                                <label>Multa por Cancelamento (%)</label>
                                <input type="number" name="multa_cancelamento" class="form-control" value="<?= $config['multa_cancelamento'] ?? '50' ?>" required>
                                <small>Percentual do valor cobrado por cancelamento tardio</small>
                            </div>
                            <div class="mb-3">
                                <label>Máximo de Reservas Ativas por Sócio</label>
                                <input type="number" name="maximo_reservas_ativas" class="form-control" value="<?= $config['maximo_reservas_ativas'] ?? '3' ?>" required>
                            </div>
                            <button type="submit" class="btn btn-gradient w-100">Salvar Configurações</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Adicionar/Editar Espaço -->
            <div class="col-md-7">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0">
                            <?php if(isset($espacoEdit)): ?>
                                <i class="fas fa-edit"></i> Editar Espaço
                            <?php else: ?>
                                <i class="fas fa-plus-circle"></i> Adicionar Espaço
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($msgEspaco)): ?>
                            <div class="alert alert-success"><?= $msgEspaco ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <?php if(isset($espacoEdit)): ?>
                                <input type="hidden" name="atualizar_espaco" value="1">
                                <input type="hidden" name="id" value="<?= $espacoEdit['id'] ?>">
                            <?php else: ?>
                                <input type="hidden" name="salvar_espaco" value="1">
                            <?php endif; ?>
                            
                            <!-- Dados Básicos -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-info-circle"></i> Dados Básicos</div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="required-field">Nome do Espaço</label>
                                        <input type="text" name="nome" class="form-control" value="<?= $espacoEdit['nome'] ?? '' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Tipo</label>
                                        <select name="tipo" class="form-select">
                                            <?php foreach($tipos as $t): ?>
                                                <option value="<?= $t['nome'] ?>" <?= (isset($espacoEdit) && $espacoEdit['tipo'] == $t['nome']) ? 'selected' : '' ?>>
                                                    <?= ucfirst($t['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Capacidade (pessoas)</label>
                                        <input type="number" name="capacidade" class="form-control" value="<?= $espacoEdit['capacidade'] ?? 0 ?>">
                                        <small>0 = ilimitado</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Status</label>
                                        <select name="ativo" class="form-select">
                                            <option value="1" <?= (isset($espacoEdit) && $espacoEdit['ativo']) ? 'selected' : '' ?>>Ativo</option>
                                            <option value="0" <?= (isset($espacoEdit) && !$espacoEdit['ativo']) ? 'selected' : '' ?>>Inativo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Horário de Abertura</label>
                                        <input type="time" name="horario_inicio" class="form-control" value="<?= $espacoEdit['horario_inicio'] ?? '08:00' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Horário de Fechamento</label>
                                        <input type="time" name="horario_fim" class="form-control" value="<?= $espacoEdit['horario_fim'] ?? '22:00' ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label>Descrição</label>
                                        <textarea name="descricao" class="form-control" rows="2"><?= htmlspecialchars($espacoEdit['descricao'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preços -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-dollar-sign"></i> Configuração de Preços</div>
                                
                                <div class="mb-3">
                                    <label>Tipo de Cobrança</label>
                                    <select name="tipo_cobranca" id="tipo_cobranca" class="form-select">
                                        <option value="gratuito" <?= (isset($espacoEdit) && $espacoEdit['valor_hora'] == 0 && $espacoEdit['valor_hora_socio'] == 0) ? 'selected' : '' ?>>Gratuito (sem custo)</option>
                                        <option value="unico" <?= (isset($espacoEdit) && $espacoEdit['valor_hora'] > 0 && $espacoEdit['valor_hora_socio'] == $espacoEdit['valor_hora_externo']) ? 'selected' : '' ?>>Valor único (igual para todos)</option>
                                        <option value="diferenciado" <?= (isset($espacoEdit) && $espacoEdit['valor_hora_socio'] != $espacoEdit['valor_hora_externo']) ? 'selected' : '' ?>>Diferenciado (sócio x externo)</option>
                                    </select>
                                </div>
                                
                                <div id="campos_preco_unico" style="display: none;">
                                    <div class="preco-box">
                                        <label>Valor por Hora (R$)</label>
                                        <input type="text" name="valor_hora" class="form-control money" value="<?= number_format($espacoEdit['valor_hora'] ?? 0, 2, ',', '.') ?>">
                                    </div>
                                </div>
                                
                                <div id="campos_preco_diferenciado" style="display: none;">
                                    <div class="preco-box">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <label><i class="fas fa-users text-primary"></i> Valor para SÓCIOS (R$/hora)</label>
                                                <input type="text" name="valor_hora_socio" class="form-control money" value="<?= number_format($espacoEdit['valor_hora_socio'] ?? 0, 2, ',', '.') ?>">
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label><i class="fas fa-user-friends text-success"></i> Valor para EXTERNOS (R$/hora)</label>
                                                <input type="text" name="valor_hora_externo" class="form-control money" value="<?= number_format($espacoEdit['valor_hora_externo'] ?? 0, 2, ',', '.') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Descontos -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-tags"></i> Descontos Especiais</div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="tem_desconto" id="tem_desconto" value="1" <?= (isset($espacoEdit) && $espacoEdit['tem_desconto']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="tem_desconto">
                                        <i class="fas fa-percent text-warning"></i> Aplicar descontos especiais
                                    </label>
                                </div>
                                
                                <div id="campos_desconto" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Desconto Geral (%)</label>
                                            <input type="number" name="desconto_percentual" class="form-control" value="<?= $espacoEdit['desconto_percentual'] ?? 0 ?>" min="0" max="100">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Aplicar para</label>
                                            <select name="desconto_tipo_socio_id" class="form-select">
                                                <option value="">Todos os sócios</option>
                                                <?php foreach($tipos_socio as $t): ?>
                                                    <option value="<?= $t['id'] ?>" <?= (isset($espacoEdit) && $espacoEdit['desconto_tipo_socio_id'] == $t['id']) ? 'selected' : '' ?>>
                                                        <?= $t['nome'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label>Descontos Adicionais por Tipo de Sócio</label>
                                        <div id="descontos_adicionais">
                                            <?php if(isset($descontosAdd)): ?>
                                                <?php foreach($descontosAdd as $d): ?>
                                                <div class="input-group mb-2">
                                                    <select name="desconto_tipo[]" class="form-select">
                                                        <option value="">Selecione</option>
                                                        <?php foreach($tipos_socio as $t): ?>
                                                            <option value="<?= $t['id'] ?>" <?= $d['tipo_socio_id'] == $t['id'] ? 'selected' : '' ?>><?= $t['nome'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="number" name="desconto_valor[]" class="form-control" placeholder="%" value="<?= $d['desconto_percentual'] ?>">
                                                    <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="adicionarDesconto()">
                                            <i class="fas fa-plus"></i> Adicionar Desconto por Tipo
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Promoção -->
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-star"></i> Promoção Especial</div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="tem_promocao" id="tem_promocao" value="1" <?= (isset($espacoEdit) && $espacoEdit['valor_promocional'] > 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="tem_promocao">
                                        <i class="fas fa-percent text-success"></i> Valor Promocional
                                    </label>
                                </div>
                                
                                <div id="campos_promocao" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Valor Promocional (R$/hora)</label>
                                            <input type="text" name="valor_promocional" class="form-control money" value="<?= number_format($espacoEdit['valor_promocional'] ?? 0, 2, ',', '.') ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Data Início</label>
                                            <input type="date" name="data_promocao_inicio" class="form-control" value="<?= $espacoEdit['data_promocao_inicio'] ?? '' ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Data Fim</label>
                                            <input type="date" name="data_promocao_fim" class="form-control" value="<?= $espacoEdit['data_promocao_fim'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-gradient px-5">
                                    <i class="fas fa-save"></i> <?= isset($espacoEdit) ? 'Atualizar Espaço' : 'Adicionar Espaço' ?>
                                </button>
                                <?php if(isset($espacoEdit)): ?>
                                    <a href="configurar.php" class="btn btn-secondary">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Espaços -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-building"></i> Espaços Cadastrados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Capacidade</th>
                                        <th>Preço Sócio</th>
                                        <th>Preço Externo</th>
                                        <th>Desconto</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($espacos as $e): ?>
                                    <tr>
                                        <td><?= $e['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($e['nome']) ?></strong></td>
                                        <td><?= ucfirst($e['tipo']) ?></td>
                                        <td><?= $e['capacidade'] ?: '∞' ?>人</td>
                                        <td class="text-success">
                                            <?php if($e['valor_hora_socio'] > 0): ?>
                                                R$ <?= number_format($e['valor_hora_socio'], 2, ',', '.') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Gratuito</span>
                                            <?php endif; ?>
                                            <?php if($e['valor_promocional'] > 0 && (!$e['data_promocao_fim'] || $e['data_promocao_fim'] >= date('Y-m-d'))): ?>
                                                <br><small class="text-warning">Promoção!</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-info">
                                            <?php if($e['valor_hora_externo'] > 0): ?>
                                                R$ <?= number_format($e['valor_hora_externo'], 2, ',', '.') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Gratuito</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($e['tem_desconto'] && $e['desconto_percentual'] > 0): ?>
                                                <span class="badge bg-warning text-dark">-<?= $e['desconto_percentual'] ?>%</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <?php if($e['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inativo</span>
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <a href="?editar_espaco=<?= $e['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?excluir_espaco=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este espaço?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                             </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        // Máscara para dinheiro
        $('.money').mask('000.000.000.000.000,00', { reverse: true });
        
        // Controle de exibição dos campos
        function togglePrecos() {
            var tipo = $('#tipo_cobranca').val();
            $('#campos_preco_unico').hide();
            $('#campos_preco_diferenciado').hide();
            
            if(tipo == 'unico') {
                $('#campos_preco_unico').show();
            } else if(tipo == 'diferenciado') {
                $('#campos_preco_diferenciado').show();
            }
        }
        
        function toggleDesconto() {
            $('#campos_desconto').toggle($('#tem_desconto').is(':checked'));
        }
        
        function togglePromocao() {
            $('#campos_promocao').toggle($('#tem_promocao').is(':checked'));
        }
        
        function adicionarDesconto() {
            var html = `<div class="input-group mb-2">
                <select name="desconto_tipo[]" class="form-select">
                    <option value="">Selecione</option>
                    <?php foreach($tipos_socio as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= $t['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="desconto_valor[]" class="form-control" placeholder="%">
                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i>
                </button>
            </div>`;
            $('#descontos_adicionais').append(html);
        }
        
        // Eventos
        $('#tipo_cobranca').change(togglePrecos);
        $('#tem_desconto').change(toggleDesconto);
        $('#tem_promocao').change(togglePromocao);
        
        // Inicializar
        togglePrecos();
        toggleDesconto();
        togglePromocao();
    </script>
</body>
</html>