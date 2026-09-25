<?php
$notificacoes = [];
$totalNotificacoes = 0;

try {
    $inadimplentes = $pdo->query("SELECT COUNT(DISTINCT socio_id) FROM lancamentos WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE()")->fetchColumn();
    if ($inadimplentes > 0) {
        $notificacoes[] = ['tipo' => 'warning', 'icone' => 'exclamation-triangle', 'mensagem' => "$inadimplentes sócio(s) inadimplente(s)", 'link' => '/modules/financeiro/index.php'];
        $totalNotificacoes++;
    }
} catch (PDOException $e) {}

try {
    $aniversariantes = $pdo->query("SELECT COUNT(*) FROM socios WHERE ativo = 1 AND data_nascimento IS NOT NULL AND MONTH(data_nascimento) = MONTH(CURRENT_DATE())")->fetchColumn();
    if ($aniversariantes > 0) {
        $notificacoes[] = ['tipo' => 'info', 'icone' => 'birthday-cake', 'mensagem' => "$aniversariantes aniversariante(s) este mês", 'link' => '/modules/socios/index.php?aniversariantes=1'];
        $totalNotificacoes++;
    }
} catch (PDOException $e) {}

$badgeCount = $totalNotificacoes;
?>
<style>
    body { margin: 0; }
    .meu-menu { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; position: sticky; top: 0; z-index: 999; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
    .meu-menu-container { display:flex; justify-content:space-between; align-items:center; padding:0 16px; flex-wrap:wrap; }
    .meu-menu-logo { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:700; padding:12px 0; }
    .meu-menu-list { display:flex; flex-wrap:wrap; list-style:none; margin:0; padding:0; }
    .meu-menu-item { position:relative; }
    .meu-menu-item > a { display:flex; align-items:center; gap:8px; padding:14px 16px; color:rgba(255,255,255,.92); text-decoration:none; white-space:nowrap; }
    .meu-menu-item > a:hover { background:rgba(255,255,255,.08); color:#fff; }
    .meu-dropdown { display:none; position:absolute; top:100%; left:0; min-width:260px; background:#fff; color:#333; list-style:none; margin:0; padding:8px 0; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,.18); }
    .meu-menu-item:hover .meu-dropdown { display:block; }
    .meu-dropdown a { display:block; padding:10px 16px; color:#333; text-decoration:none; }
    .meu-dropdown a:hover { background:#f5f5f5; }
    .badge-menu { background:#f39c12; color:#1a1a2e; border-radius:999px; padding:2px 7px; font-size:11px; font-weight:700; }
    @media (max-width: 992px) {
        .meu-menu-container { flex-direction:column; align-items:flex-start; }
        .meu-dropdown { position:static; box-shadow:none; border-radius:8px; margin:0 0 8px 0; min-width:100%; }
    }
</style>
<nav class="meu-menu">
    <div class="meu-menu-container">
        <a href="/dashboard.php" class="meu-menu-logo"><i class="fas fa-trophy"></i><span>Administrar Clube</span></a>
        <ul class="meu-menu-list">
            <li class="meu-menu-item"><a href="/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="meu-menu-item">
                <a href="#"><i class="fas fa-building"></i> Secretaria <i class="fas fa-chevron-down"></i></a>
                <ul class="meu-dropdown">
                    <li><a href="/modules/secretaria/index.php">Dashboard Secretaria</a></li>
                    <li><a href="/modules/secretaria/atendimentos.php">Atendimentos</a></li>
                    <li><a href="/modules/secretaria/comunicados.php">Comunicados</a></li>
                    <li><a href="/modules/secretaria/documentos.php">Documentos</a></li>
                    <li><a href="/modules/secretaria/aniversariantes.php">Aniversariantes</a></li>
                    <li><a href="/modules/secretaria/inadimplentes.php">Inadimplentes</a></li>
                </ul>
            </li>
            <li class="meu-menu-item"><a href="/modules/reservas/index.php"><i class="fas fa-calendar-check"></i> Reservas</a></li>
            <li class="meu-menu-item"><a href="/modules/financeiro/index.php"><i class="fas fa-dollar-sign"></i> Financeiro</a></li>
            <li class="meu-menu-item"><a href="/modules/tesouraria/index.php"><i class="fas fa-coins"></i> Tesouraria</a></li>
            <li class="meu-menu-item">
                <a href="#"><i class="fas fa-users"></i> Sócios <i class="fas fa-chevron-down"></i></a>
                <ul class="meu-dropdown">
                    <li><a href="/modules/socios/index.php">Listar sócios</a></li>
                    <li><a href="/modules/socios/cadastrar.php">Novo sócio</a></li>
                    <li><a href="/modules/socios/tipos_socio.php">Tipos de sócio</a></li>
                </ul>
            </li>
            <li class="meu-menu-item">
                <a href="#"><i class="fas fa-id-card"></i> Carteirinhas <i class="fas fa-chevron-down"></i></a>
                <ul class="meu-dropdown">
                    <li><a href="/modules/carteirinha/index.php">Gerar carteirinha</a></li>
                    <li><a href="/modules/carteirinha/validar.php">Validar carteirinha</a></li>
                    <li><a href="/modules/carteirinha/configurar.php">Configurar cores</a></li>
                </ul>
            </li>
            <li class="meu-menu-item">
                <a href="#"><i class="fas fa-ticket-alt"></i> Convites <span class="badge-menu ms-1"><?= (int)$badgeCount ?></span> <i class="fas fa-chevron-down"></i></a>
                <ul class="meu-dropdown">
                    <li><a href="/modules/convites/index.php">Controle de convites</a></li>
                    <li><a href="/modules/convites/historico.php">Histórico</a></li>
                    <li><a href="/modules/convites/baixa.php">Baixa manual</a></li>
                </ul>
            </li>
            <li class="meu-menu-item"><a href="/modules/relatorios/index.php"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
            <li class="meu-menu-item">
                <a href="#"><i class="fas fa-cog"></i> Sistema <i class="fas fa-chevron-down"></i></a>
                <ul class="meu-dropdown">
                    <li><a href="/criar_admin.php">Usuários</a></li>
                    <li><a href="/modules/financeiro/configurar.php">Configurações</a></li>
                </ul>
            </li>
            <li class="meu-menu-item"><a href="/logout.php" style="color:#ffb3b3;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>
    </div>
</nav>
