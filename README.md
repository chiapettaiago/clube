# Sistema de Clube

Aplicação web em PHP para a administração de clubes, associações e entidades recreativas. Centraliza o cadastro de sócios e dependentes, controle financeiro, convites, reservas de espaços, eventos, carteirinhas com QR Code, atendimento e comunicação.

O sistema foi desenvolvido como uma aplicação PHP tradicional, com páginas renderizadas no servidor e banco de dados MySQL/MariaDB. Há também uma API em JSON voltada ao aplicativo do sócio.

## Principais recursos

- **Sócios e famílias:** cadastro, edição, busca, exportação, tipos de sócio, dependentes, aniversariantes e controle de status de acesso.
- **Carteirinha digital:** geração, visualização e validação de carteirinhas, com QR Code, código de barras, foto e marca d'água configurável.
- **Financeiro:** mensalidades automáticas, lançamentos, baixas, histórico, inadimplência e relatórios/exportações.
- **Tesouraria:** fluxo de caixa, contas bancárias, boletos, extrato, conciliação e relatórios.
- **Convites:** regras, saldo mensal por família, baixa manual e histórico de utilização.
- **Reservas:** cadastro de espaços, preços por perfil, disponibilidade, reservas, cancelamentos e relatórios.
- **Eventos:** criação e edição de eventos, inscrições, presença, certificados e relatórios.
- **Secretaria:** atendimentos, comunicados, documentos, aniversariantes e visão de inadimplentes.
- **Notificações:** templates, assinaturas, lembretes e uma fila de envio para e-mail/SMS.
- **Acesso administrativo:** autenticação por sessão e permissões por usuário.
- **API mobile:** autenticação de sócios, dados cadastrais, carteirinha, convites e eventos. Consulte `api/index.php` para a documentação exposta pela aplicação.

## Tecnologias

- PHP 7.4+ (recomendado PHP 8.1 ou superior)
- MySQL 5.7+ ou MariaDB 10.4+
- PDO com driver `pdo_mysql`
- Extensões PHP: `gd`, `curl` e `mbstring` recomendadas
- Bootstrap 5 e Font Awesome carregados por CDN
- [phpqrcode](vendor/phpqrcode/README) incluído em `vendor/phpqrcode`

## Estrutura do projeto

```text
.
├── api/                 # Endpoints JSON para o aplicativo do sócio
├── assets/              # CSS, uploads e arquivos de marca d'água
├── cron/                # Rotinas agendadas
├── database/            # Esquema inicial do banco
├── includes/            # Autenticação e componentes compartilhados de layout
├── modules/             # Módulos administrativos da aplicação
│   ├── socios/
│   ├── financeiro/
│   ├── tesouraria/
│   ├── reservas/
│   ├── eventos/
│   ├── convites/
│   ├── secretaria/
│   ├── carteirinha/
│   └── notificacoes/
├── vendor/phpqrcode/    # Biblioteca local de QR Code
├── config.php           # Conexão, bootstrap e funções de domínio
├── index.php            # Tela de login
└── dashboard.php        # Painel administrativo
```

> Há uma pasta legada `modules/fianceiro/` (com grafia diferente de `financeiro/`). O módulo ativo no menu e nas rotas atuais é `modules/financeiro/`.

## Instalação

1. Publique os arquivos em um servidor com PHP e configure o diretório público para esta pasta.
2. Crie um banco MySQL/MariaDB em UTF-8 (`utf8mb4`).
3. Importe o esquema inicial:

   ```bash
   mysql -u SEU_USUARIO -p NOME_DO_BANCO < database/schema_clube.sql
   ```

4. Edite `config.php` e informe os dados do banco no bloco de configuração:

   ```php
   $host = 'localhost';
   $dbname = 'clube';
   $username = 'usuario_do_banco';
   $password = 'uma_senha_segura';
   ```

5. Garanta que o usuário do servidor web possa escrever em `assets/uploads/`, `assets/watermarks/` e `logs/`.
6. Acesse `https://seu-dominio/` e entre no sistema.

Em uma instalação nova, o bootstrap em `config.php` cria as tabelas de usuários/permissões que faltarem e prepara o usuário inicial. As credenciais padrão presentes no código são `admin@admin.com` e `123456`.

## Primeiro acesso e segurança

Troque imediatamente a senha padrão e remova ou proteja o acesso a `criar_admin.php` após configurar o administrador. Esse arquivo recria/atualiza a conta administrativa padrão.

Antes de publicar em produção, também é essencial:

- substituir quaisquer credenciais de banco já presentes em `config.php` e mantê-las fora do controle de versão;
- usar HTTPS;
- restringir acesso direto a scripts de diagnóstico e teste (`teste_*.php`, `diagnostico*.php`, `corrigir_*.php`);
- restringir a execução web dos scripts em `cron/`, permitindo apenas CLI ou um agendador autenticado;
- revisar a autenticação da API mobile antes de expô-la publicamente.

## Rotinas agendadas

As tarefas abaixo devem ser executadas pelo agendador do servidor. Ajuste o caminho do PHP e o caminho absoluto do projeto.

```cron
# Gera mensalidades no primeiro dia de cada mês, às 00:05
5 0 1 * * /usr/bin/php /caminho/para/o/projeto/cron/gerar_mensalidades.php >> /var/log/clube-mensalidades.log 2>&1

# Prepara os convites do próximo mês no primeiro dia, às 00:10
10 0 1 * * /usr/bin/php /caminho/para/o/projeto/cron/renovar_convites.php >> /var/log/clube-convites.log 2>&1

# Processa lembretes e a fila de notificações diariamente, às 08:00
0 8 * * * /usr/bin/php /caminho/para/o/projeto/cron/disparar_notificacoes.php >> /var/log/clube-notificacoes.log 2>&1
```

Também é possível gerar mensalidades manualmente para uma referência específica:

```bash
php cron/gerar_mensalidades.php 2026-09-01
```

## Banco de dados

O arquivo [database/schema_clube.sql](database/schema_clube.sql) contém a estrutura principal e dados iniciais, incluindo tipos de sócio, parentescos, categorias financeiras e configurações de notificação.

As tabelas de usuários e permissões são garantidas automaticamente na inicialização por `config.php`. O usuário do banco precisa, portanto, ter permissão para criar tabelas durante a primeira execução.

## API mobile

O catálogo de endpoints fica disponível em `/api/index.php`. As rotas autenticadas utilizam token Bearer:

```http
Authorization: Bearer SEU_TOKEN
```

Os endpoints implementados estão nos arquivos em `api/`. Ajuste a URL base retornada pela API e implemente uma política de senha apropriada para sócios antes de integrar um aplicativo em produção.

## Desenvolvimento e verificação

Não há processo de build ou suíte automatizada configurada. Para uma verificação rápida de sintaxe após alterações em PHP, execute:

```bash
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Os arquivos `teste_*.php` são utilitários locais de diagnóstico e não substituem testes automatizados.

## Licença

Este repositório não contém uma licença declarada. Defina uma licença antes de redistribuir o código.
