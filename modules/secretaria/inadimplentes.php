<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('financeiro') or die('Acesso negado');

// Buscar inadimplentes
$inadimplentes = [];
$totalGeral = 0;

try {
    // Verificar se a tabela lancamentos existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'lancamentos'");
    if($checkTable->rowCount() > 0) {
        $result = $pdo->query("
            SELECT
                sp.id AS socio_financeiro_id,
                sp.nome AS socio_financeiro_nome,
                sp.numero_titulo AS socio_financeiro_titulo,
                MAX(sp.telefone) AS telefone,
                MAX(sp.email_socio) AS email_socio,
                MAX(sp.foto) AS foto,
                COUNT(DISTINCT s.id) AS membros_envolvidos,
                SUM(l.valor) as total_debito,
                MIN(l.data_vencimento) as primeira_vencimento,
                COUNT(l.id) as qtde_parcelas
            FROM lancamentos l
            JOIN socios s ON l.socio_id = s.id
            LEFT JOIN socios sp ON sp.id = COALESCE(NULLIF(s.socio_principal_id, 0), s.id)
            WHERE l.status = 'pendente' AND l.data_vencimento < CURRENT_DATE()
            GROUP BY sp.id, sp.nome, sp.numero_titulo
            ORDER BY primeira_vencimento ASC, sp.nome ASC
        ");

        if ($result instanceof PDOStatement) {
            $inadimplentes = $result->fetchAll(PDO::FETCH_ASSOC);
            $totalGeral = array_sum(array_map('floatval', array_column($inadimplentes, 'total_debito')));
        }
    }
} catch(PDOException $e) {
    $inadimplentes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inadimplentes - Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-modern { border: none; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header-modern { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; border-radius: 20px 20px 0 0 !important; padding: 15px 20px; }
        .inadimplente-item { transition: all 0.2s; }
        .inadimplente-item:hover { background-color: #f8d7da; transform: translateX(5px); }
        .total-debito { font-size: 1.2rem; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/menu.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="card card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Sócios Inadimplentes</h5>
                <div class="float-end">
                    <span class="badge bg-danger fs-6">Total: R$ <?= number_format($totalGeral, 2, ',', '.') ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if(empty($inadimplentes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                        <p>Nenhum sócio inadimplente</p>
                        <small class="text-muted">Todas as mensalidades estão em dia!</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Foto</th>
                                    <th>Sócio</th>
                                    <th>Nº Título</th>
                                    <th>Total Débito</th>
                                    <th>Parcelas</th>
                                    <th>Vencimento</th>
                                    <th>Telefone</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($inadimplentes as $inc): ?>
                                <tr class="inadimplente-item">
                                    <td>
                                        <?php if($inc['foto'] && file_exists('../../assets/uploads/' . $inc['foto'])): ?>
                                            <img src="../../assets/uploads/<?= $inc['foto'] ?>" width="40" height="40" class="rounded-circle">
                                        <?php else: ?>
                                            <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($inc['socio_financeiro_nome']) ?></strong>
                                        <?php if ((int)$inc['membros_envolvidos'] > 1): ?>
                                            <br><small class="text-muted">Mensalidade do titular com dependentes vinculados</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($inc['socio_financeiro_titulo']) ?> </td>
                                    <td class="text-danger total-debito">R$ <?= number_format($inc['total_debito'], 2, ',', '.') ?> </td>
                                    <td><?= $inc['qtde_parcelas'] ?> </td>
                                    <td><?= date('d/m/Y', strtotime($inc['primeira_vencimento'])) ?> </td>
                                    <td>
                                        <?php if(!empty($inc['telefone'])): ?>
                                            <a href="tel:<?= $inc['telefone'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="tel:<?= $inc['telefone'] ?>" class="btn btn-success" title="Ligar">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                            <a href="../fianceiro/lancamentos.php?socio=<?= $inc['socio_financeiro_id'] ?>" class="btn btn-info" title="Ver Lançamentos">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button onclick="enviarCobranca(<?= $inc['socio_financeiro_id'] ?>, '<?= addslashes($inc['socio_financeiro_nome']) ?>')" class="btn btn-warning" title="Enviar Cobrança">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function enviarCobranca(id, nome) {
            if(confirm(`💰 Enviar cobrança para ${nome}?`)) {
                alert(`✅ Cobrança enviada para ${nome}!`);
                // Aqui você pode implementar o envio real via AJAX
            }
        }
    </script>
</body>
</html>
