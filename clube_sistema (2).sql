-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 13/09/2026 às 00:50
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `clube_sistema`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `api_logs`
--

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL,
  `endpoint` varchar(100) DEFAULT NULL,
  `metodo` varchar(10) DEFAULT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_data` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `data_requisicao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `device_uuid` varchar(100) DEFAULT NULL,
  `ultimo_acesso` datetime DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas_notificacoes`
--

CREATE TABLE `assinaturas_notificacoes` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `tipo_notificacao` enum('aniversario','vencimento','evento','reserva','comunicado') NOT NULL,
  `canal` enum('email','sms','ambos') DEFAULT 'email',
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `atendimentos`
--

CREATE TABLE `atendimentos` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('pendente','concluido','cancelado') DEFAULT 'pendente',
  `data_atendimento` datetime DEFAULT current_timestamp(),
  `atendido_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `boletos`
--

CREATE TABLE `boletos` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `numero_boleto` varchar(50) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  -- MySQL 5.7 não permite CURDATE() como valor DEFAULT; a aplicação informa
  -- essa data ao criar o boleto.
  `data_emissao` date DEFAULT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','cancelado','vencido') DEFAULT 'pendente',
  `nosso_numero` varchar(50) DEFAULT NULL,
  `linha_digitavel` text DEFAULT NULL,
  `codigo_barras` varchar(100) DEFAULT NULL,
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa`
--

CREATE TABLE `caixa` (
  `id` int(11) NOT NULL,
  `data_movimento` date NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `forma_pagamento` enum('dinheiro','cartao','pix','cheque','transferencia','boleto') DEFAULT 'dinheiro',
  `documento` varchar(50) DEFAULT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `status` enum('pendente','confirmado','cancelado') DEFAULT 'confirmado',
  `created_at` datetime DEFAULT current_timestamp(),
  `lancado_por` int(11) DEFAULT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_financeiras`
--

CREATE TABLE `categorias_financeiras` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('receita','despesa') DEFAULT 'receita',
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias_financeiras`
--

INSERT INTO `categorias_financeiras` (`id`, `nome`, `tipo`, `ativo`) VALUES
(1, 'Mensalidade', 'receita', 1),
(2, 'Taxa de Admissão', 'receita', 1),
(3, 'Multa', 'receita', 1),
(4, 'Doação', 'receita', 1),
(5, 'Aluguel de Espaços', 'receita', 1),
(6, 'Manutenção', 'despesa', 1),
(7, 'Salários', 'despesa', 1),
(8, 'Água/Luz', 'despesa', 1),
(9, 'Material de Limpeza', 'despesa', 1),
(10, 'Eventos', 'despesa', 1),
(11, 'Mensalidade', 'receita', 1),
(12, 'Taxa de Admissão', 'receita', 1),
(13, 'Multa', 'receita', 1),
(14, 'Doação', 'receita', 1),
(15, 'Aluguel de Espaços', 'receita', 1),
(16, 'Manutenção', 'despesa', 1),
(17, 'Salários', 'despesa', 1),
(18, 'Água/Luz', 'despesa', 1),
(19, 'Material de Limpeza', 'despesa', 1),
(20, 'Eventos', 'despesa', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `comunicados`
--

CREATE TABLE `comunicados` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `data_publicacao` datetime DEFAULT current_timestamp(),
  `data_validade` date DEFAULT NULL,
  `publicado_por` int(11) DEFAULT NULL,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comunicados`
--

INSERT INTO `comunicados` (`id`, `titulo`, `conteudo`, `data_publicacao`, `data_validade`, `publicado_por`, `ativo`) VALUES
(1, 'Campo pequeno em manutenção', 'Campo pequeno fechado para manutenção anual, sem data para abertura do mesmo', '2026-08-10 15:02:22', NULL, 1, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `chave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes`
--

INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('tipo_identificacao', 'qrcode');

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_financeiro`
--

CREATE TABLE `config_financeiro` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `config_financeiro`
--

INSERT INTO `config_financeiro` (`id`, `chave`, `valor`, `descricao`) VALUES
(1, 'valor_mensalidade', '150.00', 'Valor padrão da mensalidade'),
(2, 'dia_vencimento', '10', 'Dia de vencimento das mensalidades'),
(3, 'multa_atraso', '2.00', 'Multa por atraso em percentual'),
(4, 'juros_mora', '0.33', 'Juros de mora ao dia em percentual'),
(5, 'desconto_anual', '10.00', 'Desconto para pagamento anual em percentual'),
(11, 'caixa_inicial', '0.00', 'Saldo inicial do caixa'),
(12, 'limite_saque', '5000.00', 'Limite para saque em dinheiro'),
(13, 'boleto_instrucoes', 'Após o vencimento, cobrar multa de 2% e juros de 0,33% ao dia.', 'Instruções do boleto'),
(14, 'boleto_beneficiario', 'Clube Esportivo', 'Nome do beneficiário no boleto'),
(15, 'boleto_cnpj', '00.000.000/0001-00', 'CNPJ do clube'),
(16, 'boleto_banco', '001', 'Código do banco para boleto'),
(17, 'financeiro_dia_vencimento', '12', NULL),
(18, 'financeiro_qtd_parcelas_alerta', '2', NULL),
(19, 'financeiro_modo_alerta', 'bloqueio', NULL),
(20, 'financeiro_mensagem_alerta', 'O responsavel deve regularizar as mensalidades em atraso.', NULL),
(21, 'financeiro_valor_mensalidade', '295.00', NULL),
(22, 'financeiro_responsavel_email', '', NULL),
(41, 'financeiro_reajuste_dependente_ativo', '0', NULL),
(42, 'financeiro_reajuste_idade_max_masculino', '19', NULL),
(43, 'financeiro_reajuste_idade_max_feminino', '19', NULL),
(44, 'financeiro_reajuste_valor_dependente_masculino', '0.00', NULL),
(45, 'financeiro_reajuste_valor_dependente_feminino', '0.00', NULL),
(46, 'financeiro_reajuste_parentescos', 'filho,filha,enteado,enteada', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_notificacoes`
--

CREATE TABLE `config_notificacoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `config_notificacoes`
--

INSERT INTO `config_notificacoes` (`id`, `chave`, `valor`, `descricao`) VALUES
(1, 'smtp_host', 'smtp.gmail.com', 'Servidor SMTP'),
(2, 'smtp_port', '587', 'Porta SMTP'),
(3, 'smtp_user', '', 'Usuário SMTP (e-mail)'),
(4, 'smtp_pass', '', 'Senha SMTP'),
(5, 'smtp_secure', 'tls', 'Tipo de segurança (tls/ssl)'),
(6, 'email_remetente', 'clube@seudominio.com', 'E-mail remetente'),
(7, 'nome_remetente', 'Clube Manager', 'Nome do remetente'),
(8, 'sms_api_key', '', 'Chave da API de SMS'),
(9, 'sms_api_secret', '', 'Segredo da API de SMS'),
(10, 'sms_remetente', 'Clube', 'Nome do remetente SMS'),
(11, 'lembrete_aniversario', '1', 'Enviar lembrete de aniversário (dias antes)'),
(12, 'lembrete_vencimento', '3', 'Enviar lembrete de vencimento (dias antes)'),
(13, 'lembrete_evento', '1', 'Enviar lembrete de evento (dias antes)');

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_reservas`
--

CREATE TABLE `config_reservas` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `config_reservas`
--

INSERT INTO `config_reservas` (`id`, `chave`, `valor`, `descricao`) VALUES
(1, 'antecedencia_minima', '24', 'Horas mínimas de antecedência para reserva'),
(2, 'cancelamento_maximo', '12', 'Horas máximas para cancelamento sem multa'),
(3, 'multa_cancelamento', '50', 'Percentual de multa para cancelamento tardio'),
(4, 'maximo_reservas_ativas', '3', 'Máximo de reservas ativas por sócio');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_bancarias`
--

CREATE TABLE `contas_bancarias` (
  `id` int(11) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `agencia` varchar(20) NOT NULL,
  `conta` varchar(20) NOT NULL,
  `tipo_conta` enum('corrente','poupanca','salario') DEFAULT 'corrente',
  `saldo_inicial` decimal(10,2) DEFAULT 0.00,
  `saldo_atual` decimal(10,2) DEFAULT 0.00,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `contas_bancarias`
--

INSERT INTO `contas_bancarias` (`id`, `banco`, `agencia`, `conta`, `tipo_conta`, `saldo_inicial`, `saldo_atual`, `ativo`) VALUES
(1, 'Banco do Brasil', '0001', '12345-6', 'corrente', 0.00, 0.00, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `convites`
--

CREATE TABLE `convites` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `mes_referencia` date NOT NULL,
  `total_convites` int(11) DEFAULT 0,
  `convites_utilizados` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `convites`
--

INSERT INTO `convites` (`id`, `socio_id`, `mes_referencia`, `total_convites`, `convites_utilizados`) VALUES
(4, 15, '2026-08-01', 5, 0),
(5, 13, '2026-08-01', 5, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `convites_familia`
--

CREATE TABLE `convites_familia` (
  `id` int(11) NOT NULL,
  `familia_id` varchar(50) NOT NULL,
  `socio_principal_id` int(11) NOT NULL,
  `mes_referencia` date NOT NULL,
  `total_convites` int(11) DEFAULT 0,
  `convites_utilizados` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `convites_familia`
--

INSERT INTO `convites_familia` (`id`, `familia_id`, `socio_principal_id`, `mes_referencia`, `total_convites`, `convites_utilizados`) VALUES
(1, 'FAM_69D7C77DAEF2B', 10, '2026-04-01', 5, 0),
(2, 'FAM_69D7C77DAEF2B', 10, '2026-05-01', 5, 0),
(3, 'FAM_69DE3F1012AC2', 14, '2026-04-01', 5, 0),
(4, 'FAM_69D7C77DAEF2B', 10, '2026-08-01', 3, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `convites_regras`
--

CREATE TABLE `convites_regras` (
  `id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo_convite` varchar(50) NOT NULL DEFAULT 'geral',
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `aplica_familia` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` text DEFAULT NULL,
  `anexo_path` varchar(255) DEFAULT NULL,
  `anexo_nome` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `convites_uso`
--

CREATE TABLE `convites_uso` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `familia_id` varchar(50) DEFAULT NULL,
  `convite_usado_para` varchar(100) DEFAULT NULL,
  `data_uso` datetime DEFAULT current_timestamp(),
  `tipo_uso` enum('socio','dependente') DEFAULT 'socio',
  `regra_id` int(11) DEFAULT NULL,
  `tipo_convite` varchar(50) DEFAULT NULL,
  `anexo_path` varchar(255) DEFAULT NULL,
  `anexo_nome` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `convites_uso`
--

INSERT INTO `convites_uso` (`id`, `socio_id`, `familia_id`, `convite_usado_para`, `data_uso`, `tipo_uso`, `regra_id`, `tipo_convite`, `anexo_path`, `anexo_nome`) VALUES
(1, 10, 'FAM_69D7C77DAEF2B', 'Baixa manual de convites', '2026-08-11 12:04:58', '', NULL, NULL, NULL, NULL),
(2, 13, 'FAM_69D7C77DAEF2B', 'Baixa refletida no grupo familiar', '2026-08-11 12:04:58', '', NULL, NULL, NULL, NULL),
(3, 10, 'FAM_69D7C77DAEF2B', 'Baixa manual de convites', '2026-08-11 21:43:29', '', NULL, NULL, NULL, NULL),
(4, 13, 'FAM_69D7C77DAEF2B', 'Baixa refletida no grupo familiar', '2026-08-11 21:43:29', '', NULL, NULL, NULL, NULL),
(5, 10, 'FAM_69D7C77DAEF2B', 'Teste de baixa', '2026-08-12 11:17:15', '', NULL, NULL, NULL, NULL),
(6, 13, 'FAM_69D7C77DAEF2B', 'Baixa refletida no grupo familiar', '2026-08-12 11:17:15', '', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `descontos_espacos`
--

CREATE TABLE `descontos_espacos` (
  `id` int(11) NOT NULL,
  `espaco_id` int(11) NOT NULL,
  `tipo_socio_id` int(11) NOT NULL,
  `desconto_percentual` int(11) DEFAULT 0,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `dispositivos_push`
--

CREATE TABLE `dispositivos_push` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `device_token` varchar(255) NOT NULL,
  `platform` enum('ios','android') DEFAULT 'android',
  `device_model` varchar(100) DEFAULT NULL,
  `app_version` varchar(20) DEFAULT NULL,
  `ativo` tinyint(4) DEFAULT 1,
  `ultimo_acesso` datetime DEFAULT NULL,
  `data_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `arquivo` varchar(255) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `data_upload` datetime DEFAULT current_timestamp(),
  `upload_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `espacos`
--

CREATE TABLE `espacos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('salao','churrasqueira','quadra','piscina','salao_jogos','outro') DEFAULT 'outro',
  `capacidade` int(11) DEFAULT 0,
  `valor_hora` decimal(10,2) DEFAULT 0.00,
  `valor_hora_socio` decimal(10,2) DEFAULT NULL,
  `valor_hora_externo` decimal(10,2) DEFAULT NULL,
  `tem_desconto` tinyint(4) DEFAULT 0,
  `desconto_percentual` int(11) DEFAULT 0,
  `desconto_tipo_socio_id` int(11) DEFAULT NULL,
  `valor_promocional` decimal(10,2) DEFAULT NULL,
  `data_promocao_inicio` date DEFAULT NULL,
  `data_promocao_fim` date DEFAULT NULL,
  `valor_diaria` decimal(10,2) DEFAULT 0.00,
  `imagem` varchar(255) DEFAULT NULL,
  `horario_inicio` time DEFAULT '08:00:00',
  `horario_fim` time DEFAULT '22:00:00',
  `intervalo_minimo` int(11) DEFAULT 2,
  `ativo` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `espacos`
--

INSERT INTO `espacos` (`id`, `nome`, `descricao`, `tipo`, `capacidade`, `valor_hora`, `valor_hora_socio`, `valor_hora_externo`, `tem_desconto`, `desconto_percentual`, `desconto_tipo_socio_id`, `valor_promocional`, `data_promocao_inicio`, `data_promocao_fim`, `valor_diaria`, `imagem`, `horario_inicio`, `horario_fim`, `intervalo_minimo`, `ativo`, `created_at`) VALUES
(1, 'Salão de Festas', NULL, 'salao', 100, 150.00, 150.00, 150.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(2, 'Churrasqueira 1', NULL, 'churrasqueira', 30, 80.00, 80.00, 80.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(3, 'Churrasqueira 2', NULL, 'churrasqueira', 30, 80.00, 80.00, 80.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(4, 'Quadra de Futebol', NULL, 'quadra', 22, 100.00, 100.00, 100.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(5, 'Quadra de Tênis', NULL, 'quadra', 4, 60.00, 60.00, 60.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(6, 'Piscina', NULL, 'piscina', 50, 0.00, 0.00, 0.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '18:00:00', 2, 1, '2026-04-10 11:41:35'),
(7, 'Salão de Jogos', NULL, 'salao_jogos', 20, 40.00, 40.00, 40.00, 0, 0, NULL, NULL, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-04-10 11:41:35'),
(8, 'Churrasqueira 5', '', 'churrasqueira', 60, 0.00, 0.00, 0.00, 0, 0, NULL, 0.00, NULL, NULL, 0.00, NULL, '08:00:00', '22:00:00', 2, 1, '2026-08-27 12:21:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `eventos`
--

CREATE TABLE `eventos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('festa','campeonato','palestra','curso','reuniao','outro') DEFAULT 'outro',
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `local` varchar(200) DEFAULT NULL,
  `carga_horaria` int(11) DEFAULT 0,
  `endereco` text DEFAULT NULL,
  `capacidade` int(11) DEFAULT 0,
  `vagas_disponiveis` int(11) DEFAULT 0,
  `valor` decimal(10,2) DEFAULT 0.00,
  `imagem` varchar(255) DEFAULT NULL,
  `certificado_modelo` text DEFAULT NULL,
  `status` enum('ativo','cancelado','finalizado') DEFAULT 'ativo',
  `criado_por` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `extrato_bancario`
--

CREATE TABLE `extrato_bancario` (
  `id` int(11) NOT NULL,
  `conta_id` int(11) DEFAULT NULL,
  `data_lancamento` date NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('credito','debito') NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `conciliado` tinyint(4) DEFAULT 0,
  `data_conciliacao` date DEFAULT NULL,
  `lancamento_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fila_notificacoes`
--

CREATE TABLE `fila_notificacoes` (
  `id` int(11) NOT NULL,
  `destinatario` varchar(200) NOT NULL,
  `tipo` enum('email','sms') NOT NULL,
  `assunto` varchar(200) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `status` enum('pendente','enviado','erro') DEFAULT 'pendente',
  `data_envio` datetime DEFAULT NULL,
  `erro` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `financeiro`
--

CREATE TABLE `financeiro` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `data_lancamento` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','cancelado') DEFAULT 'pendente',
  `tipo` enum('mensalidade','taxa','multa','desconto') DEFAULT 'mensalidade'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_financeiro`
--

CREATE TABLE `historico_financeiro` (
  `id` int(11) NOT NULL,
  `lancamento_id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(20) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_evento` datetime NOT NULL DEFAULT current_timestamp(),
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_notificacoes`
--

CREATE TABLE `historico_notificacoes` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `tipo` enum('email','sms') NOT NULL,
  `assunto` varchar(200) DEFAULT NULL,
  `mensagem` text DEFAULT NULL,
  `status` enum('enviado','falhou') DEFAULT 'enviado',
  `data_envio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `inscricoes_eventos`
--

CREATE TABLE `inscricoes_eventos` (
  `id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `data_inscricao` datetime DEFAULT current_timestamp(),
  `status` enum('confirmada','cancelada','pendente') DEFAULT 'confirmada',
  `valor_pago` decimal(10,2) DEFAULT 0.00,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `presenca_confirmada` tinyint(4) DEFAULT 0,
  `data_presenca` datetime DEFAULT NULL,
  `codigo_inscricao` varchar(50) DEFAULT NULL,
  `certificado_emitido` tinyint(4) DEFAULT 0,
  `certificado_path` varchar(255) DEFAULT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lancamentos`
--

CREATE TABLE `lancamentos` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `data_lancamento` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','cancelado','vencido') DEFAULT 'pendente',
  `forma_pagamento` enum('dinheiro','cartao','pix','boleto','transferencia') DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `lancado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lancamentos`
--

INSERT INTO `lancamentos` (`id`, `socio_id`, `descricao`, `valor`, `tipo`, `categoria_id`, `data_lancamento`, `data_vencimento`, `data_pagamento`, `status`, `forma_pagamento`, `observacao`, `created_at`, `updated_at`, `lancado_por`) VALUES
(1, 10, 'Mensalidade 08/2026 - Claudenir da Silveira', 6.00, 'receita', 1, '2026-08-10', '2026-08-12', '2026-08-27', 'pago', 'dinheiro', ' ', '2026-08-10 11:23:37', '2026-08-26 19:27:01', NULL),
(2, 13, 'Mensalidade 08/2026 - Simone Leite de Freitas', 295.00, 'receita', 1, '2026-08-10', '2026-08-12', '2026-08-27', 'pago', 'pix', ' Pago teste mantivemos o valor normal', '2026-08-10 11:23:37', '2026-08-27 12:26:01', NULL),
(3, 14, 'Mensalidade 08/2026 - Teste João', 0.00, 'receita', 1, '2026-08-10', '2026-08-12', NULL, 'pendente', NULL, NULL, '2026-08-10 11:23:37', NULL, NULL),
(4, 15, 'Mensalidade 08/2026 - João Neto', 0.00, 'receita', 1, '2026-08-10', '2026-08-12', NULL, 'pendente', NULL, NULL, '2026-08-10 11:23:37', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_certificado`
--

CREATE TABLE `modelos_certificado` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `template` text NOT NULL,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `parentescos`
--

CREATE TABLE `parentescos` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `parentescos`
--

INSERT INTO `parentescos` (`id`, `nome`) VALUES
(1, 'Cônjuge'),
(4, 'Enteado(a)'),
(3, 'Filha'),
(2, 'Filho(a)'),
(8, 'Irmão(ã)'),
(7, 'Mãe'),
(5, 'Neto(a)'),
(6, 'Pai');

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `nome`) VALUES
(1, 'cadastros'),
(3, 'financeiro'),
(2, 'relatorios'),
(4, 'usuarios');

-- --------------------------------------------------------

--
-- Estrutura para tabela `push_notifications`
--

CREATE TABLE `push_notifications` (
  `id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `dados` text DEFAULT NULL,
  `lida` tinyint(4) DEFAULT 0,
  `data_envio` datetime DEFAULT current_timestamp(),
  `data_leitura` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `espaco_id` int(11) NOT NULL,
  `socio_id` int(11) NOT NULL,
  `data_reserva` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `quantidade_pessoas` int(11) DEFAULT 0,
  `valor_total` decimal(10,2) DEFAULT 0.00,
  `status` enum('pendente','confirmada','cancelada','finalizada') DEFAULT 'pendente',
  `observacao` text DEFAULT NULL,
  `codigo_reserva` varchar(50) DEFAULT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_confirmacao` datetime DEFAULT NULL,
  `confirmado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `reservas`
--

INSERT INTO `reservas` (`id`, `espaco_id`, `socio_id`, `data_reserva`, `hora_inicio`, `hora_fim`, `quantidade_pessoas`, `valor_total`, `status`, `observacao`, `codigo_reserva`, `data_solicitacao`, `data_confirmacao`, `confirmado_por`) VALUES
(1, 2, 10, '2026-08-28', '13:00:00', '18:00:00', 28, 400.00, 'confirmada', '', 'RES-20260827-437', '2026-08-27 12:18:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `socios`
--

CREATE TABLE `socios` (
  `id` int(11) NOT NULL,
  `familia_id` varchar(50) DEFAULT NULL,
  `socio_principal_id` int(11) DEFAULT NULL,
  `parentesco` varchar(50) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `numero_titulo` varchar(20) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email_socio` varchar(100) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL,
  `tipo_sanguineo` varchar(5) DEFAULT NULL,
  `tipo_socio_id` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `qrcode` text DEFAULT NULL,
  `codigo_barras` text DEFAULT NULL,
  `ativo` tinyint(4) DEFAULT 1,
  `data_validade` date DEFAULT NULL,
  `convites_por_mes` int(11) DEFAULT 5,
  `status_acesso` varchar(20) NOT NULL DEFAULT 'ativo',
  `motivo_suspensao` varchar(255) DEFAULT NULL,
  `suspenso_ate` date DEFAULT NULL,
  `suspenso_em` datetime DEFAULT NULL,
  `suspenso_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `socios`
--

INSERT INTO `socios` (`id`, `familia_id`, `socio_principal_id`, `parentesco`, `nome`, `numero_titulo`, `cpf`, `data_nascimento`, `telefone`, `email_socio`, `endereco`, `sexo`, `tipo_sanguineo`, `tipo_socio_id`, `foto`, `qrcode`, `codigo_barras`, `ativo`, `data_validade`, `convites_por_mes`, `status_acesso`, `motivo_suspensao`, `suspenso_ate`, `suspenso_em`, `suspenso_por`) VALUES
(10, 'FAM_69D7C77DAEF2B', NULL, '', 'Claudenir da Silveira', '061', '01551403706', '1969-12-12', '(21) 99297-0513', 'csilveira2010@gmail.com', ', ', 'M', 'O+', 1, '69d7c77daef38.png', 'assets/uploads/carteirinha_qr_10.png', 'assets/uploads/carteirinha_bar_10.png', 1, '2032-12-09', 3, 'ativo', NULL, NULL, NULL, NULL),
(13, 'FAM_69D7C77DAEF2B', 10, 'Cônjuge', 'Simone Leite de Freitas', '061', '03310198766', '1972-11-10', '(21) 99303-5049', 'monibyco@gmail.com', ', ', 'F', 'A+', 7, '69d7fc2cf22ba.png', 'assets/uploads/carteirinha_qr_13.png', 'assets/uploads/carteirinha_bar_13.png', 1, '2032-11-10', 5, 'ativo', NULL, NULL, NULL, NULL),
(14, 'FAM_69DE3F1012AC2', NULL, '', 'Teste João', '001', '21184928711', '2001-04-14', '(21) 99999-9999', 'teste@teste.com.br', ', ', 'M', 'B+', 1, '', 'assets/uploads/qrcode_001.png', 'assets/uploads/barcode_001.png', 1, '2030-04-14', 5, 'ativo', NULL, NULL, NULL, NULL),
(15, 'FAM_69DE3F1012AC2', 14, 'Filho(a)', 'João Neto', '001', '95072128768', '2007-07-07', '(21) 99303-5049', 'monibyco@gmail.com', 'Rua Estado do Rio de Janeiro, 330 - Casa 11 - Araras - Teresópolis - CEP: 25958230', 'M', 'A+', 7, '', 'assets/uploads/carteirinha_qr_15.png', 'assets/uploads/carteirinha_bar_15.png', 1, '2032-07-07', 5, 'ativo', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `templates_notificacao`
--

CREATE TABLE `templates_notificacao` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `assunto` varchar(200) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('email','sms','ambos') DEFAULT 'email',
  `ativo` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `templates_notificacao`
--

INSERT INTO `templates_notificacao` (`id`, `nome`, `assunto`, `mensagem`, `tipo`, `ativo`, `created_at`) VALUES
(1, 'aniversario', 'Feliz Aniversário!', 'Olá {NOME}, parabéns pelo seu aniversário! O Clube deseja um dia especial para você! 🎂🎉', 'ambos', 1, '2026-04-10 14:47:41'),
(2, 'vencimento_mensalidade', 'Mensalidade em Vencimento', 'Prezado(a) {NOME}, sua mensalidade do mês de {MES} vence em {DIAS} dias. Valor: R$ {VALOR}.', 'ambos', 1, '2026-04-10 14:47:41'),
(3, 'confirmacao_reserva', 'Reserva Confirmada', 'Sua reserva do espaço {ESPACO} para o dia {DATA} às {HORA} foi confirmada. Código: {CODIGO}', 'ambos', 1, '2026-04-10 14:47:41'),
(4, 'novo_evento', 'Novo Evento no Clube', 'Confira o novo evento: {EVENTO} no dia {DATA}. Inscreva-se já!', 'email', 1, '2026-04-10 14:47:41');

-- --------------------------------------------------------

--
-- Estrutura para tabela `terceiros`
--

CREATE TABLE `terceiros` (
  `id` int(11) NOT NULL,
  `nome_servico` varchar(100) NOT NULL,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `terceiros`
--

INSERT INTO `terceiros` (`id`, `nome_servico`, `ativo`) VALUES
(1, 'Academia', 1),
(2, 'Capoeira', 1),
(3, 'Natação', 1),
(4, 'Teste', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_espaco`
--

CREATE TABLE `tipos_espaco` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `cor` varchar(7) DEFAULT '#3498db'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tipos_espaco`
--

INSERT INTO `tipos_espaco` (`id`, `nome`, `icone`, `cor`) VALUES
(1, 'salao', 'fa-champagne-glasses', '#e74c3c'),
(2, 'churrasqueira', 'fa-utensils', '#e67e22'),
(3, 'quadra', 'fa-futbol', '#2ecc71'),
(4, 'piscina', 'fa-swimmer', '#3498db'),
(5, 'salao_jogos', 'fa-dice', '#9b59b6'),
(6, 'outro', 'fa-building', '#95a5a6');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_evento`
--

CREATE TABLE `tipos_evento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `cor` varchar(7) DEFAULT '#3498db'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tipos_evento`
--

INSERT INTO `tipos_evento` (`id`, `nome`, `icone`, `cor`) VALUES
(1, 'festa', 'fa-music', '#e74c3c'),
(2, 'campeonato', 'fa-trophy', '#f1c40f'),
(3, 'palestra', 'fa-chalkboard-user', '#3498db'),
(4, 'curso', 'fa-graduation-cap', '#2ecc71'),
(5, 'reuniao', 'fa-users', '#9b59b6'),
(6, 'outro', 'fa-calendar', '#95a5a6');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_socio`
--

CREATE TABLE `tipos_socio` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cor_carteirinha` varchar(7) DEFAULT '#FFFFFF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tipos_socio`
--

INSERT INTO `tipos_socio` (`id`, `nome`, `cor_carteirinha`) VALUES
(1, 'Proprietário', '#ffd700'),
(2, 'Dependente Esposa', '#add8e6'),
(3, 'Contribuinte', '#c0c0c0'),
(4, 'Sócio Atleta', '#90ee90'),
(5, 'Diretoria', '#f90101'),
(7, 'Dependente', '#add8e6'),
(11, 'Remido', '#adbb3e');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `ativo` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `ativo`) VALUES
(1, 'Administrador', 'admin@admin.com', '$2y$10$Ocf2jGjMy2Lssr0fThybk.wW04OEyEiiXx.c/DluRxH4qlhEqSdmq', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_permissoes`
--

CREATE TABLE `usuario_permissoes` (
  `usuario_id` int(11) NOT NULL,
  `permissao_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario_permissoes`
--

INSERT INTO `usuario_permissoes` (`usuario_id`, `permissao_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_socio` (`socio_id`),
  ADD KEY `idx_data` (`data_requisicao`);

--
-- Índices de tabela `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_socio` (`socio_id`);

--
-- Índices de tabela `assinaturas_notificacoes`
--
ALTER TABLE `assinaturas_notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assinatura` (`socio_id`,`tipo_notificacao`);

--
-- Índices de tabela `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `socio_id` (`socio_id`),
  ADD KEY `atendido_por` (`atendido_por`);

--
-- Índices de tabela `boletos`
--
ALTER TABLE `boletos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_boleto` (`numero_boleto`),
  ADD KEY `socio_id` (`socio_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_vencimento` (`data_vencimento`);

--
-- Índices de tabela `caixa`
--
ALTER TABLE `caixa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `socio_id` (`socio_id`),
  ADD KEY `lancado_por` (`lancado_por`),
  ADD KEY `idx_data` (`data_movimento`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Índices de tabela `categorias_financeiras`
--
ALTER TABLE `categorias_financeiras`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `comunicados`
--
ALTER TABLE `comunicados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `publicado_por` (`publicado_por`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `config_financeiro`
--
ALTER TABLE `config_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `config_notificacoes`
--
ALTER TABLE `config_notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `config_reservas`
--
ALTER TABLE `config_reservas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `contas_bancarias`
--
ALTER TABLE `contas_bancarias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `convites`
--
ALTER TABLE `convites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mes_socio` (`socio_id`,`mes_referencia`);

--
-- Índices de tabela `convites_familia`
--
ALTER TABLE `convites_familia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_familia_mes` (`familia_id`,`mes_referencia`),
  ADD KEY `socio_principal_id` (`socio_principal_id`);

--
-- Índices de tabela `convites_regras`
--
ALTER TABLE `convites_regras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_datas` (`data_inicio`,`data_fim`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `convites_uso`
--
ALTER TABLE `convites_uso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `socio_id` (`socio_id`);

--
-- Índices de tabela `descontos_espacos`
--
ALTER TABLE `descontos_espacos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_desconto` (`espaco_id`,`tipo_socio_id`),
  ADD KEY `tipo_socio_id` (`tipo_socio_id`);

--
-- Índices de tabela `dispositivos_push`
--
ALTER TABLE `dispositivos_push`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`socio_id`,`device_token`);

--
-- Índices de tabela `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `upload_por` (`upload_por`);

--
-- Índices de tabela `espacos`
--
ALTER TABLE `espacos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `criado_por` (`criado_por`),
  ADD KEY `idx_data` (`data_inicio`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `extrato_bancario`
--
ALTER TABLE `extrato_bancario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conta_id` (`conta_id`),
  ADD KEY `lancamento_id` (`lancamento_id`);

--
-- Índices de tabela `fila_notificacoes`
--
ALTER TABLE `fila_notificacoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `financeiro`
--
ALTER TABLE `financeiro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `socio_id` (`socio_id`);

--
-- Índices de tabela `historico_financeiro`
--
ALTER TABLE `historico_financeiro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lancamento` (`lancamento_id`),
  ADD KEY `idx_socio` (`socio_id`),
  ADD KEY `idx_acao` (`acao`),
  ADD KEY `idx_data` (`data_evento`);

--
-- Índices de tabela `historico_notificacoes`
--
ALTER TABLE `historico_notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_socio` (`socio_id`),
  ADD KEY `idx_data` (`data_envio`);

--
-- Índices de tabela `inscricoes_eventos`
--
ALTER TABLE `inscricoes_eventos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inscricao` (`evento_id`,`socio_id`),
  ADD UNIQUE KEY `codigo_inscricao` (`codigo_inscricao`),
  ADD KEY `socio_id` (`socio_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_presenca` (`presenca_confirmada`);

--
-- Índices de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `lancado_por` (`lancado_por`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data_vencimento` (`data_vencimento`),
  ADD KEY `idx_socio` (`socio_id`);

--
-- Índices de tabela `modelos_certificado`
--
ALTER TABLE `modelos_certificado`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `parentescos`
--
ALTER TABLE `parentescos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `push_notifications`
--
ALTER TABLE `push_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_socio` (`socio_id`),
  ADD KEY `idx_lida` (`lida`);

--
-- Índices de tabela `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reserva` (`espaco_id`,`data_reserva`,`hora_inicio`),
  ADD UNIQUE KEY `codigo_reserva` (`codigo_reserva`),
  ADD KEY `confirmado_por` (`confirmado_por`),
  ADD KEY `idx_data` (`data_reserva`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_socio` (`socio_id`);

--
-- Índices de tabela `socios`
--
ALTER TABLE `socios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `tipo_socio_id` (`tipo_socio_id`),
  ADD KEY `idx_socio_principal` (`socio_principal_id`),
  ADD KEY `idx_familia` (`familia_id`),
  ADD KEY `idx_numero_titulo` (`numero_titulo`);

--
-- Índices de tabela `templates_notificacao`
--
ALTER TABLE `templates_notificacao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `terceiros`
--
ALTER TABLE `terceiros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_servico` (`nome_servico`);

--
-- Índices de tabela `tipos_espaco`
--
ALTER TABLE `tipos_espaco`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `tipos_evento`
--
ALTER TABLE `tipos_evento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `tipos_socio`
--
ALTER TABLE `tipos_socio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD PRIMARY KEY (`usuario_id`,`permissao_id`),
  ADD KEY `permissao_id` (`permissao_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assinaturas_notificacoes`
--
ALTER TABLE `assinaturas_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `atendimentos`
--
ALTER TABLE `atendimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `boletos`
--
ALTER TABLE `boletos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `caixa`
--
ALTER TABLE `caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categorias_financeiras`
--
ALTER TABLE `categorias_financeiras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `comunicados`
--
ALTER TABLE `comunicados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `config_financeiro`
--
ALTER TABLE `config_financeiro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de tabela `config_notificacoes`
--
ALTER TABLE `config_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `config_reservas`
--
ALTER TABLE `config_reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `contas_bancarias`
--
ALTER TABLE `contas_bancarias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `convites`
--
ALTER TABLE `convites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `convites_familia`
--
ALTER TABLE `convites_familia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `convites_regras`
--
ALTER TABLE `convites_regras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `convites_uso`
--
ALTER TABLE `convites_uso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `descontos_espacos`
--
ALTER TABLE `descontos_espacos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dispositivos_push`
--
ALTER TABLE `dispositivos_push`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `espacos`
--
ALTER TABLE `espacos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `extrato_bancario`
--
ALTER TABLE `extrato_bancario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fila_notificacoes`
--
ALTER TABLE `fila_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `financeiro`
--
ALTER TABLE `financeiro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `historico_financeiro`
--
ALTER TABLE `historico_financeiro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_notificacoes`
--
ALTER TABLE `historico_notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `inscricoes_eventos`
--
ALTER TABLE `inscricoes_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `modelos_certificado`
--
ALTER TABLE `modelos_certificado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `parentescos`
--
ALTER TABLE `parentescos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `push_notifications`
--
ALTER TABLE `push_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `socios`
--
ALTER TABLE `socios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `templates_notificacao`
--
ALTER TABLE `templates_notificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `terceiros`
--
ALTER TABLE `terceiros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `tipos_espaco`
--
ALTER TABLE `tipos_espaco`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `tipos_evento`
--
ALTER TABLE `tipos_evento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `tipos_socio`
--
ALTER TABLE `tipos_socio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `assinaturas_notificacoes`
--
ALTER TABLE `assinaturas_notificacoes`
  ADD CONSTRAINT `assinaturas_notificacoes_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD CONSTRAINT `atendimentos_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `atendimentos_ibfk_2` FOREIGN KEY (`atendido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `boletos`
--
ALTER TABLE `boletos`
  ADD CONSTRAINT `boletos_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `caixa`
--
ALTER TABLE `caixa`
  ADD CONSTRAINT `caixa_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `caixa_ibfk_2` FOREIGN KEY (`lancado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `comunicados`
--
ALTER TABLE `comunicados`
  ADD CONSTRAINT `comunicados_ibfk_1` FOREIGN KEY (`publicado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `convites`
--
ALTER TABLE `convites`
  ADD CONSTRAINT `convites_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `convites_familia`
--
ALTER TABLE `convites_familia`
  ADD CONSTRAINT `convites_familia_ibfk_1` FOREIGN KEY (`socio_principal_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `convites_uso`
--
ALTER TABLE `convites_uso`
  ADD CONSTRAINT `convites_uso_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `descontos_espacos`
--
ALTER TABLE `descontos_espacos`
  ADD CONSTRAINT `descontos_espacos_ibfk_1` FOREIGN KEY (`espaco_id`) REFERENCES `espacos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `descontos_espacos_ibfk_2` FOREIGN KEY (`tipo_socio_id`) REFERENCES `tipos_socio` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `dispositivos_push`
--
ALTER TABLE `dispositivos_push`
  ADD CONSTRAINT `dispositivos_push_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`upload_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `extrato_bancario`
--
ALTER TABLE `extrato_bancario`
  ADD CONSTRAINT `extrato_bancario_ibfk_1` FOREIGN KEY (`conta_id`) REFERENCES `contas_bancarias` (`id`),
  ADD CONSTRAINT `extrato_bancario_ibfk_2` FOREIGN KEY (`lancamento_id`) REFERENCES `lancamentos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `financeiro`
--
ALTER TABLE `financeiro`
  ADD CONSTRAINT `financeiro_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `historico_notificacoes`
--
ALTER TABLE `historico_notificacoes`
  ADD CONSTRAINT `historico_notificacoes_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `inscricoes_eventos`
--
ALTER TABLE `inscricoes_eventos`
  ADD CONSTRAINT `inscricoes_eventos_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscricoes_eventos_ibfk_2` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD CONSTRAINT `lancamentos_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lancamentos_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_financeiras` (`id`),
  ADD CONSTRAINT `lancamentos_ibfk_3` FOREIGN KEY (`lancado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `push_notifications`
--
ALTER TABLE `push_notifications`
  ADD CONSTRAINT `push_notifications_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `reservas`
--
ALTER TABLE `reservas`
  ADD CONSTRAINT `reservas_ibfk_1` FOREIGN KEY (`espaco_id`) REFERENCES `espacos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservas_ibfk_2` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservas_ibfk_3` FOREIGN KEY (`confirmado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `socios`
--
ALTER TABLE `socios`
  ADD CONSTRAINT `socios_ibfk_1` FOREIGN KEY (`tipo_socio_id`) REFERENCES `tipos_socio` (`id`),
  ADD CONSTRAINT `socios_ibfk_2` FOREIGN KEY (`socio_principal_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD CONSTRAINT `usuario_permissoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_permissoes_ibfk_2` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
