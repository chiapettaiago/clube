<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$msg_erro = '';
$msg_sucesso = '';

if($_POST) {
    $tipo_cliente = $_POST['tipo_cliente'];
    $socio_id = !empty($_POST['socio_id']) ? $_POST['socio_id'] : null;
    $nome_visitante = $_POST['nome_visitante'] ?? '';
    $telefone_visitante = $_POST['telefone_visitante'] ?? '';
    $tipo = $_POST['tipo'];
    $assunto = $_POST['assunto'];
    $descricao = $_POST['descricao'];
    $prioridade = $_POST['prioridade'];
    
    if(empty($assunto) || empty($descricao)) {
        $msg_erro = "❌ Assunto e descrição são obrigatórios!";
    } elseif($tipo_cliente == 'socio' && empty($socio_id)) {
        $msg_erro = "❌ Selecione um sócio!";
    } elseif($tipo_cliente == 'visitante' && empty($nome_visitante)) {
        $msg_erro = "❌ Informe o nome do visitante!";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO atendimentos (socio_id, nome_visitante, telefone_visitante, tipo, assunto, descricao, prioridade, status, atendido_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?)
        ");
        $stmt->execute([$socio_id, $nome_visitante, $telefone_visitante, $tipo, $assunto, $descricao, $prioridade, $_SESSION['usuario_id']]);
        $msg_sucesso = "✅ Atendimento registrado com sucesso!";
        
        if(!isset($_POST['save_and_new'])) {
            echo "<script>setTimeout(function() { window.location.href = 'atendimentos.php'; }, 1500);</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Atendimento - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; font-family: 'Segoe UI', sans-serif; }
        
        .card-modern { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); 
            background: #16213e;
        }
        
        .card-header-modern { 
            background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%); 
            color: white; 
            border-radius: 20px 20px 0 0 !important; 
            padding: 15px 20px;
            border-bottom: 1px solid #2c3e50;
        }
        
        .required-field:after { content: " *"; color: #e74c3c; }
        
        .form-label, label {
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            background-color: #0f3460;
            color: white;
            border: 1px solid #2c3e50;
            border-radius: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #1a3a6e;
            color: white;
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .form-select option {
            background-color: #0f3460;
            color: white;
        }
        
        .form-check-label {
            color: #e0e0e0;
        }
        
        .alert-success { background-color: #1a5a3a; color: #d4edda; border: none; }
        .alert-danger { background-color: #7a2e2e; color: #f8d7da; border: none; }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1a5276 100%);
            transform: translateY(-2px);
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            background-color: #0f3460;
            border: 1px solid #2c3e50;
            min-height: 38px;
        }
        .select2-container--bootstrap-5 .select2-selection__rendered {
            color: white;
        }
        .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: #aaa;
        }
        .select2-dropdown {
            background-color: #0f3460;
            border-color: #2c3e50;
        }
        .select2-results__option {
            color: white;
        }
        .select2-results__option--highlighted {
            background-color: #3498db;
        }
        .socio-info small {
            color: #aaa;
        }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-modern">
                    <div class="card-header-modern">
                        <h4 class="mb-0"><i class="fas fa-headset"></i> Novo Atendimento</h4>
                    </div>
                    <div class="card-body">
                        <?php if($msg_erro): ?>
                            <div class="alert alert-danger"><?= $msg_erro ?></div>
                        <?php endif; ?>
                        <?php if($msg_sucesso): ?>
                            <div class="alert alert-success"><?= $msg_sucesso ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="formAtendimento">
                            <!-- Tipo de Cliente -->
                            <div class="mb-3">
                                <label class="fw-bold">Tipo de Cliente</label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipo_socio" value="socio" checked>
                                        <label class="form-check-label" for="tipo_socio">
                                            <i class="fas fa-users text-primary"></i> Sócio
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipo_visitante" value="visitante">
                                        <label class="form-check-label" for="tipo_visitante">
                                            <i class="fas fa-user-friends text-success"></i> Visitante / Não sócio
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dados do Sócio -->
                            <div id="campos_socio" class="mb-3">
                                <label class="fw-bold required-field">Buscar Sócio</label>
                                <select name="socio_id" id="socio_id" class="form-select" style="width: 100%;">
                                    <option value="">-- Digite para buscar --</option>
                                </select>
                                <small class="text-muted">Digite o nome, título ou CPF para buscar</small>
                            </div>
                            
                            <!-- Dados do Visitante -->
                            <div id="campos_visitante" style="display: none;">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="fw-bold required-field">Nome do Visitante</label>
                                        <input type="text" name="nome_visitante" id="nome_visitante" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="fw-bold">Telefone</label>
                                        <input type="text" name="telefone_visitante" id="telefone_visitante" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tipo e Prioridade -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold required-field">Tipo de Atendimento</label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="informacao">ℹ️ Informação</option>
                                        <option value="reclamacao">⚠️ Reclamação</option>
                                        <option value="sugestao">💡 Sugestão</option>
                                        <option value="solicitacao">📋 Solicitação</option>
                                        <option value="documento">📄 Documento</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold required-field">Prioridade</label>
                                    <select name="prioridade" class="form-select" required>
                                        <option value="baixa">🟢 Baixa</option>
                                        <option value="media">🟡 Média</option>
                                        <option value="alta">🟠 Alta</option>
                                        <option value="urgente">🔴 Urgente</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Assunto -->
                            <div class="mb-3">
                                <label class="fw-bold required-field">Assunto</label>
                                <input type="text" name="assunto" class="form-control" required>
                            </div>
                            
                            <!-- Descrição -->
                            <div class="mb-3">
                                <label class="fw-bold required-field">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="5" required></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="save" class="btn btn-primary px-5">
                                    <i class="fas fa-save"></i> Salvar Atendimento
                                </button>
                                <button type="submit" name="save_and_new" class="btn btn-success px-4">
                                    <i class="fas fa-plus-circle"></i> Salvar e Novo
                                </button>
                                <a href="atendimentos.php" class="btn btn-secondary px-4">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Select2 com AJAX para buscar sócios
            $('#socio_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "🔍 Digite nome, título ou CPF...",
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: 'ajax/buscar_socio.php',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) {
                        return { termo: params.term };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.text,
                                    nome: item.nome,
                                    numero_titulo: item.numero_titulo,
                                    cpf: item.cpf,
                                    telefone: item.telefone,
                                    tipo_socio: item.tipo_socio
                                };
                            })
                        };
                    },
                    cache: true
                },
                templateResult: formatarSocio,
                templateSelection: formatarSocioSelecionado
            });
            
            function formatarSocio(socio) {
                if (socio.loading) return socio.text;
                
                var markup = '<div class="socio-info">' +
                    '<strong>' + socio.nome + '</strong><br>' +
                    '<small>📋 Título: ' + socio.numero_titulo + ' | 👤 ' + socio.tipo_socio + '</small>';
                
                if (socio.cpf) {
                    markup += '<br><small>📄 CPF: ' + socio.cpf + '</small>';
                }
                if (socio.telefone) {
                    markup += ' | 📞 ' + socio.telefone;
                }
                markup += '</div>';
                
                return $(markup);
            }
            
            function formatarSocioSelecionado(socio) {
                if (!socio.id) return socio.text;
                return socio.nome + ' (Título: ' + socio.numero_titulo + ')';
            }
            
            // Alternar entre sócio e visitante
            function toggleCampos() {
                if ($('input[name="tipo_cliente"]:checked').val() == 'socio') {
                    $('#campos_socio').show();
                    $('#campos_visitante').hide();
                    $('#socio_id').prop('required', true);
                    $('#nome_visitante').prop('required', false);
                } else {
                    $('#campos_socio').hide();
                    $('#campos_visitante').show();
                    $('#socio_id').prop('required', false);
                    $('#nome_visitante').prop('required', true);
                }
            }
            
            toggleCampos();
            $('input[name="tipo_cliente"]').change(toggleCampos);
        });
    </script>
</body>
</html>