-- Esquema do Sistema de Clube. Todas as tabelas abaixo pertencem ao Clube;
-- nenhuma tabela WordPress (divon_*) é alterada por este arquivo.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_socio (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL,
  descricao TEXT NULL, valor_mensalidade DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_convites INT NOT NULL DEFAULT 0, cor_carteirinha VARCHAR(20) NOT NULL DEFAULT '#1a73e8', ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tipos_socio_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parentescos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_parentescos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS socios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(200) NOT NULL, numero_titulo VARCHAR(50) NULL,
  cpf VARCHAR(20) NULL, rg VARCHAR(30) NULL, data_nascimento DATE NULL, sexo VARCHAR(20) NULL, estado_civil VARCHAR(30) NULL,
  email VARCHAR(190) NULL, email_socio VARCHAR(190) NULL, telefone VARCHAR(30) NULL, celular VARCHAR(30) NULL,
  endereco VARCHAR(255) NULL, numero VARCHAR(30) NULL, complemento VARCHAR(100) NULL, bairro VARCHAR(100) NULL, cidade VARCHAR(100) NULL, estado VARCHAR(10) NULL, cep VARCHAR(15) NULL,
  tipo_socio_id INT UNSIGNED NOT NULL, socio_principal_id INT UNSIGNED NULL, familia_id VARCHAR(50) NULL, parentesco VARCHAR(100) NULL,
  foto VARCHAR(255) NULL, qrcode VARCHAR(255) NULL, codigo_barras VARCHAR(255) NULL, tipo_sanguineo VARCHAR(10) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1, status_acesso VARCHAR(20) NOT NULL DEFAULT 'ativo', motivo_suspensao VARCHAR(255) NULL, suspenso_ate DATE NULL, suspenso_em DATETIME NULL, suspenso_por INT NULL,
  convites_por_mes INT NOT NULL DEFAULT 0, data_validade DATE NULL, logradouro VARCHAR(255) NULL, numero_endereco VARCHAR(30) NULL,
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, observacoes TEXT NULL,
  KEY idx_socios_titulo (numero_titulo), KEY idx_socios_cpf (cpf), KEY idx_socios_tipo (tipo_socio_id), KEY idx_socios_familia (familia_id), KEY idx_socios_principal (socio_principal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracoes (chave VARCHAR(100) PRIMARY KEY, valor TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS config_financeiro (chave VARCHAR(100) PRIMARY KEY, valor TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS config_reservas (chave VARCHAR(100) PRIMARY KEY, valor TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS config_notificacoes (chave VARCHAR(100) PRIMARY KEY, valor TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias_financeiras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(120) NOT NULL, tipo VARCHAR(20) NOT NULL, descricao TEXT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lancamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NULL, descricao VARCHAR(255) NOT NULL, valor DECIMAL(12,2) NOT NULL,
  tipo VARCHAR(20) NOT NULL, categoria_id INT UNSIGNED NULL, data_lancamento DATE NOT NULL, data_vencimento DATE NULL, data_pagamento DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pendente', forma_pagamento VARCHAR(50) NULL, lancado_por INT UNSIGNED NULL, observacao TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_lancamentos_socio (socio_id), KEY idx_lancamentos_status_data (status,data_vencimento), KEY idx_lancamentos_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS historico_financeiro (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, lancamento_id INT UNSIGNED NOT NULL, socio_id INT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NULL,
  acao VARCHAR(50) NOT NULL, descricao VARCHAR(255) NULL, valor DECIMAL(12,2) NULL, observacao TEXT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_historico_lancamento (lancamento_id), KEY idx_historico_socio (socio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convites_familia (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, familia_id VARCHAR(50) NOT NULL, socio_principal_id INT UNSIGNED NULL, mes_referencia DATE NOT NULL,
  total_convites INT NOT NULL DEFAULT 0, convites_utilizados INT NOT NULL DEFAULT 0, UNIQUE KEY uq_convites_familia_mes (familia_id,mes_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS convites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NOT NULL, mes_referencia DATE NOT NULL, total_convites INT NOT NULL DEFAULT 0, convites_utilizados INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_convites_socio_mes (socio_id,mes_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS convites_regras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL, tipo_convite VARCHAR(50) NOT NULL, quantidade INT NOT NULL DEFAULT 0,
  data_inicio DATE NULL, data_fim DATE NULL, aplica_familia TINYINT(1) NOT NULL DEFAULT 1, ativo TINYINT(1) NOT NULL DEFAULT 1, observacao TEXT NULL,
  anexo_path VARCHAR(255) NULL, anexo_nome VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS convites_uso (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NOT NULL, familia_id VARCHAR(50) NULL, convite_usado_para VARCHAR(200) NULL,
  tipo_uso VARCHAR(50) NULL, regra_id INT UNSIGNED NULL, tipo_convite VARCHAR(50) NULL, anexo_path VARCHAR(255) NULL, anexo_nome VARCHAR(255) NULL,
  data_uso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_convites_uso_socio (socio_id), KEY idx_convites_uso_regra (regra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_espaco (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, descricao TEXT NULL, UNIQUE KEY uq_tipos_espaco_nome(nome)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS espacos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL, tipo VARCHAR(100) NULL, capacidade INT NULL, valor_hora DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_hora_socio DECIMAL(12,2) NOT NULL DEFAULT 0, valor_hora_externo DECIMAL(12,2) NOT NULL DEFAULT 0, horario_inicio TIME NULL, horario_fim TIME NULL,
  intervalo_minimo INT NOT NULL DEFAULT 60, descricao TEXT NULL, observacoes TEXT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS descontos_espacos (espaco_id INT UNSIGNED NOT NULL, tipo_socio_id INT UNSIGNED NOT NULL, desconto_percentual DECIMAL(5,2) NOT NULL DEFAULT 0, PRIMARY KEY(espaco_id,tipo_socio_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reservas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, espaco_id INT UNSIGNED NOT NULL, socio_id INT UNSIGNED NOT NULL, data_reserva DATE NOT NULL,
  hora_inicio TIME NOT NULL, hora_fim TIME NOT NULL, valor DECIMAL(12,2) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'pendente', observacao TEXT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reservas_espaco_data (espaco_id,data_reserva), KEY idx_reservas_socio(socio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipos_evento (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, descricao TEXT NULL, UNIQUE KEY uq_tipos_evento_nome(nome)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS eventos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(200) NOT NULL, descricao TEXT NULL, tipo VARCHAR(100) NULL, data_inicio DATETIME NOT NULL, data_fim DATETIME NULL,
  local VARCHAR(200) NULL, endereco VARCHAR(255) NULL, capacidade INT NULL, vagas_disponiveis INT NULL, carga_horaria DECIMAL(6,2) NULL, valor DECIMAL(12,2) NOT NULL DEFAULT 0,
  imagem VARCHAR(255) NULL, criado_por INT UNSIGNED NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS inscricoes_eventos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evento_id INT UNSIGNED NOT NULL, socio_id INT UNSIGNED NOT NULL, codigo_inscricao VARCHAR(100) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'confirmada', presenca_confirmada TINYINT(1) NOT NULL DEFAULT 0, certificado_gerado TINYINT(1) NOT NULL DEFAULT 0, certificado_path VARCHAR(255) NULL, data_inscricao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inscricao_evento_socio(evento_id,socio_id), KEY idx_inscricao_socio(socio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS terceiros (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(200) NOT NULL, cpf VARCHAR(20) NULL, email VARCHAR(190) NULL, telefone VARCHAR(30) NULL, tipo VARCHAR(100) NULL, observacoes TEXT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS atendimentos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NULL, nome_visitante VARCHAR(200) NULL, telefone_visitante VARCHAR(30) NULL, tipo VARCHAR(100) NULL, assunto VARCHAR(255) NOT NULL, descricao TEXT NULL, prioridade VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(30) NOT NULL DEFAULT 'pendente', atendido_por INT UNSIGNED NULL, data_atendimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, data_conclusao DATETIME NULL, observacoes TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS comunicados (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(255) NOT NULL, conteudo TEXT NOT NULL, publicado_por INT UNSIGNED NULL, data_publicacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS documentos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(255) NOT NULL, descricao TEXT NULL, arquivo VARCHAR(255) NOT NULL, categoria VARCHAR(100) NULL, upload_por INT UNSIGNED NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates_notificacao (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL, assunto VARCHAR(255) NULL, mensagem TEXT NOT NULL, tipo VARCHAR(20) NOT NULL DEFAULT 'email', ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS assinaturas_notificacoes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NOT NULL, tipo_notificacao VARCHAR(50) NOT NULL, canal VARCHAR(20) NOT NULL DEFAULT 'email', ativo TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY uq_assinatura(socio_id,tipo_notificacao)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fila_notificacoes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, destinatario VARCHAR(190) NOT NULL, tipo VARCHAR(20) NOT NULL, assunto VARCHAR(255) NULL, mensagem TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pendente', data_envio DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_fila_status(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS historico_notificacoes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NULL, tipo VARCHAR(20) NOT NULL, assunto VARCHAR(255) NULL, mensagem TEXT NOT NULL, status VARCHAR(20) NOT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contas_bancarias (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, banco VARCHAR(100) NOT NULL, agencia VARCHAR(30) NULL, conta VARCHAR(50) NULL, titular VARCHAR(150) NULL, saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS caixa (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NULL, descricao VARCHAR(255) NOT NULL, valor DECIMAL(12,2) NOT NULL, tipo VARCHAR(20) NOT NULL, data_movimento DATE NOT NULL, categoria VARCHAR(100) NULL, conta_bancaria_id INT UNSIGNED NULL, forma_pagamento VARCHAR(50) NULL, documento VARCHAR(100) NULL, lancado_por INT UNSIGNED NULL, observacao TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'confirmado', criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS boletos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NOT NULL, descricao VARCHAR(255) NULL, valor DECIMAL(12,2) NOT NULL, data_vencimento DATE NOT NULL, data_pagamento DATE NULL, status VARCHAR(20) NOT NULL DEFAULT 'pendente', codigo_barras VARCHAR(255) NULL, nosso_numero VARCHAR(100) NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS extrato_bancario (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conta_id INT UNSIGNED NULL, data_lancamento DATE NOT NULL, descricao VARCHAR(255) NULL, valor DECIMAL(12,2) NOT NULL, tipo VARCHAR(20) NULL, documento VARCHAR(100) NULL, lancamento_id INT UNSIGNED NULL, conciliado TINYINT(1) NOT NULL DEFAULT 0, data_conciliacao DATE NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS api_tokens (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, socio_id INT UNSIGNED NOT NULL, token VARCHAR(255) NOT NULL, device_name VARCHAR(150) NULL, device_uuid VARCHAR(150) NULL, data_expiracao DATETIME NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_api_token(token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tipos_socio (nome, total_convites) VALUES ('Proprietário', 4), ('Dependente', 0), ('Convidado', 0);
INSERT IGNORE INTO parentescos (nome) VALUES ('Cônjuge'), ('Filho(a)'), ('Pai/Mãe'), ('Outro');
INSERT IGNORE INTO categorias_financeiras (nome,tipo) VALUES ('Mensalidades','receita'),('Outras receitas','receita'),('Despesas gerais','despesa');
INSERT IGNORE INTO config_notificacoes (chave,valor) VALUES ('lembrete_aniversario','0'),('lembrete_vencimento','3'),('lembrete_evento','3');
