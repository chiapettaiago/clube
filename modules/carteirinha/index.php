<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
verificarPermissao('cadastros') or die('Acesso negado');

header('Location: /modules/socios/index.php?mensagem=selecione_um_socio_para_gerar_a_carteirinha');
exit;
