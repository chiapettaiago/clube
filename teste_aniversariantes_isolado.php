<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

// EXATAMENTE O MESMO CÓDIGO QUE FUNCIONOU NO TESTE
$sql = "SELECT id, nome, numero_titulo, data_nascimento, telefone, email_socio, foto 
        FROM socios 
        WHERE ativo = 1 
        AND data_nascimento IS NOT NULL 
        AND MONTH(data_nascimento) = $mes
        ORDER BY DAY(data_nascimento)";

$result = $pdo->query($sql);
$aniversariantes = $result->fetchAll(PDO::FETCH_ASSOC);

// Garantir que é array
if(!is_array($aniversariantes)) {
    $aniversariantes = array();
}

$totalAniversariantes = count($aniversariantes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aniversariantes - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-modern { border: none; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header-modern { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; border-radius: 20px 20px 0 0 !important; padding: 15px 20px; }
        .aniversariante-item { transition: all 0.2s; }
        .aniversariante-item:hover { background-color: #fff3cd; transform: translateX(5px); }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0"><i class="fas fa-birthday-cake"></i> Aniversariantes</h5>
                <div class="float-end">
                    <form method="GET" class="d-inline">
                        <select name="mes" class="form-select form-select-sm d-inline-block w-auto" style="width: auto;" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> Total de aniversariantes em <?= date('F', mktime(0, 0, 0, $mes, 1)) ?>: <strong><?= $totalAniversariantes ?></strong>
                </div>
                
                <?php if($totalAniversariantes == 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3 d-block"></i>
                        <p>Nenhum aniversariante neste mês</p>
                        <small class="text-muted">Cadastre datas de nascimento nos sócios para ver os aniversariantes</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>Nº Título</th>
                                    <th>Data</th>
                                    <th>Idade</th>
                                    <th>Telefone</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($aniversariantes as $aniv): 
                                    // Calcular idade
                                    $dataNasc = new DateTime($aniv['data_nascimento']);
                                    $hoje = new DateTime();
                                    $idade = $hoje->diff($dataNasc)->y;
                                ?>
                                <tr class="aniversariante-item">
                                    <td>
                                        <?php if(!empty($aniv['foto']) && file_exists('../../assets/uploads/' . $aniv['foto'])): ?>
                                            <img src="../../assets/uploads/<?= $aniv['foto'] ?>" width="40" height="40" class="rounded-circle">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                        <?php endif; ?>
                                      </td>
                                    <td><strong><?= htmlspecialchars($aniv['nome']) ?></strong> </td>
                                    <td><?= $aniv['numero_titulo'] ?> </td>
                                    <td><?= date('d/m', strtotime($aniv['data_nascimento'])) ?> </td>
                                    <td><?= $idade ?> anos </td>
                                    <td><?= !empty($aniv['telefone']) ? $aniv['telefone'] : '-' ?> </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if(!empty($aniv['telefone'])): ?>
                                            <a href="tel:<?= $aniv['telefone'] ?>" class="btn btn-success" title="Ligar">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                            <?php endif; ?>
                                            <button onclick="alert('🎂 Parabéns para <?= addslashes($aniv['nome']) ?>!')" class="btn btn-info" title="Parabéns">
                                                <i class="fas fa-gift"></i>
                                            </button>
                                        </div>
                                       </div>
                                  </td>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
