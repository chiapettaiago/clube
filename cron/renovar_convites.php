<?php
// Arquivo para rodar no cron job todo dia 1º do mês
// URL: http://localhost/clube/cron/renovar_convites.php

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config.php';

// Criar pasta de logs se não existir
$logDir = $rootPath . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/cron_convites.log';

function escreverLog($mensagem) {
    global $logFile;
    $dataHora = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$dataHora] $mensagem\n", FILE_APPEND);
}

escreverLog("=== INICIANDO RENOVAÇÃO DE CONVITES ===");

$mesAtual = date('Y-m-01');
$proximoMes = date('Y-m-01', strtotime('+1 month'));

escreverLog("Mês atual: $mesAtual");
escreverLog("Próximo mês: $proximoMes");

// Buscar todas as famílias ativas
$familias = $pdo->query("
    SELECT DISTINCT s.familia_id, s.id as socio_principal_id, s.convites_por_mes, s.nome
    FROM socios s
    WHERE s.ativo = 1 
    AND s.familia_id IS NOT NULL
    AND s.tipo_socio_id = (SELECT id FROM tipos_socio WHERE nome = 'Proprietário' LIMIT 1)
")->fetchAll();

escreverLog("Total de famílias encontradas: " . count($familias));

$renovados = 0;
$erros = 0;

foreach ($familias as $familia) {
    try {
        // Verificar se já existe registro para o próximo mês
        $check = $pdo->prepare("
            SELECT id FROM convites_familia 
            WHERE familia_id = ? AND mes_referencia = ?
        ");
        $check->execute([$familia['familia_id'], $proximoMes]);

        if ($check->rowCount() == 0) {
            // Criar novo registro de convites para o próximo mês
            $stmt = $pdo->prepare("
                INSERT INTO convites_familia (familia_id, socio_principal_id, mes_referencia, total_convites, convites_utilizados) 
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $familia['familia_id'],
                $familia['socio_principal_id'],
                $proximoMes,
                $familia['convites_por_mes']
            ]);
            $renovados++;
            escreverLog("... Convites renovados para família: {$familia['nome']} - {$familia['convites_por_mes']} convites");
        } else {
            escreverLog("⏭️ Família já possui convites para próximo mês: {$familia['nome']}");
        }
    } catch (Exception $e) {
        $erros++;
        escreverLog("❌ ERRO ao renovar família {$familia['nome']}: " . $e->getMessage());
    }
}

escreverLog("RESUMO: $renovados famílias renovadas, $erros erros");
escreverLog("=== FINALIZADO ===\n");

if (isset($_GET['format']) && $_GET['format'] == 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => date('Y-m-d H:i:s'),
        'familias_renovadas' => $renovados,
        'total_familias' => count($familias),
        'erros' => $erros
    ]);
} else {
    echo "... Convites renovados com sucesso!\n";
    echo "Data: " . date('d/m/Y H:i:s') . "\n";
    echo "Famílias renovadas: $renovados\n";
    echo "Total de famílias: " . count($familias) . "\n";
    echo "Erros: $erros\n";
}
?>
