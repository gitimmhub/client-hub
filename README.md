# Client Hub

Plugin WordPress desenvolvido para integrar a **Central do Cliente** ao sistema **CSP**.

O Client Hub permite que clientes acessem seus orçamentos e estudos diretamente pelo site da empresa, utilizando as credenciais de acesso geradas pelo CSP.

---

## Funcionalidades

- Autenticação de clientes utilizando login e senha do CSP.
- Consulta dos dados do orçamento.
- Acesso ao PDF do orçamento.
- Listagem dos estudos disponibilizados para o cliente.
- Visualização dos estudos em PDF.
- Controle de sessão do cliente.
- Logout da Central do Cliente.
- Notificação por e-mail quando um acesso é realizado.
- Integração com a API do CSP.
- Atualizações do plugin através do GitHub.

---

## Como funciona

O WordPress não realiza a autenticação diretamente no banco de dados do CSP.

O fluxo de autenticação funciona da seguinte forma:

1. O cliente informa seu login e senha na Central do Cliente.
2. O plugin Client Hub envia os dados para a API do CSP.
3. O CSP valida as credenciais.
4. Se o acesso for válido, o CSP retorna os dados do orçamento e os estudos disponíveis.
5. O Client Hub cria uma sessão no WordPress.
6. A página é atualizada.
7. O formulário de login é substituído pela área do cliente.
8. O cliente pode visualizar seu orçamento e os estudos disponibilizados.

### Fluxo simplificado

```text
Cliente
   |
   v
WordPress
   |
   v
Client Hub
   |
   | HTTPS
   v
API do CSP
   |
   v
Banco de dados CSP
   |
   v
Orçamento + Estudos
   |
   v
Client Hub
   |
   v
Central do Cliente
```

---

## Requisitos

Antes da instalação, certifique-se de que o ambiente possui:

- WordPress instalado e funcionando.
- PHP compatível com a instalação do WordPress.
- Acesso HTTPS ao CSP.
- Endpoint da Central do Cliente configurado no CSP.
- Credenciais de cliente cadastradas no CSP.
- Permissão para instalação de plugins no WordPress.
- Acesso ao GitHub para instalação e atualização do plugin.

---

## Instalação

### 1. Acesse a pasta de plugins do WordPress

No servidor onde o WordPress está instalado:

```bash
cd /opt/apps/wordpress/wp-content/plugins
```

> O caminho pode ser diferente dependendo da instalação do WordPress.

### 2. Clone o repositório

```bash
git clone https://github.com/gitimmhub/client-hub.git
```

Após o clone, será criada a pasta:

```text
wp-content/plugins/client-hub/
```

### 3. Ative o plugin

Acesse o painel administrativo do WordPress:

```text
Plugins
→ Plugins instalados
→ Client Hub
→ Ativar
```

---

## Configuração da página

Crie uma nova página no WordPress que será utilizada como **Central do Cliente**.

Adicione o seguinte shortcode ao conteúdo da página:

```text
[client_hub]
```

Publique a página.

Ao acessar a página publicada, o formulário de autenticação do Client Hub deverá ser exibido.

O cliente deverá utilizar o login e a senha fornecidos pelo CSP.

---

## Utilização

Ao acessar a Central do Cliente, será exibido inicialmente um formulário contendo:

- Login de acesso.
- Senha.
- Botão para acessar.

Após uma autenticação válida, a mesma página passa a apresentar a área do cliente.

A área autenticada pode apresentar:

- Nome do cliente.
- Número do orçamento.
- Opção para visualizar o orçamento.
- Estudos disponíveis.
- Data de disponibilização dos estudos.
- Opção para visualizar os documentos.
- Opção para encerrar a sessão.

Não é necessário acessar outra página após o login. O próprio Client Hub atualiza a página e exibe a área autenticada.

---

## Comunicação com o CSP

O Client Hub utiliza a API do CSP para realizar a autenticação e obter os dados necessários.

O endpoint utilizado para autenticação possui o seguinte formato:

```text
/api/client-hub/login
```

Em produção, a comunicação deve ser realizada através de HTTPS.

O CSP é responsável por:

- Validar o login.
- Validar a senha.
- Identificar o orçamento associado ao acesso.
- Retornar os dados do cliente.
- Retornar os dados do orçamento.
- Informar a disponibilidade do PDF do orçamento.
- Retornar os estudos disponíveis.
- Retornar os links necessários para visualização dos documentos.
- Informar o responsável associado ao acesso.

O WordPress **não deve acessar diretamente o banco de dados do CSP**.

Toda a comunicação entre os dois sistemas deve ocorrer através da API.

---

## Fluxo de autenticação

Quando o formulário é enviado, o Client Hub realiza uma requisição AJAX para o próprio WordPress.

```text
Formulário
    |
    v
admin-ajax.php
    |
    v
Client Hub
    |
    v
API CSP
```

Após uma autenticação bem-sucedida, o plugin armazena na sessão do WordPress:

- Estado da autenticação.
- Login utilizado.
- Horário do login.
- Última atividade.
- Dados do orçamento.
- Estudos disponíveis.

Depois disso, a página é recarregada.

O shortcode identifica que existe uma sessão autenticada e passa a renderizar a área do cliente em vez do formulário de login.

---

## Sessão

O Client Hub utiliza sessão PHP para manter o cliente autenticado.

A sessão permite que o usuário navegue pela Central do Cliente sem precisar informar novamente suas credenciais a cada requisição.

Por esse motivo, páginas que utilizam o Client Hub não devem possuir cache de página que ignore a sessão do usuário.

Caso a página de login continue sendo exibida mesmo após uma autenticação válida, verifique as configurações de cache do WordPress, servidor ou CDN.

---

## Testando a comunicação com o CSP

Caso seja necessário verificar se o CSP está respondendo corretamente, o endpoint pode ser testado utilizando `curl`.

Exemplo:

```bash
curl -k -i -X POST \
  -d "login=SEU_LOGIN" \
  -d "senha=SUA_SENHA" \
  https://SEU-CSP/api/client-hub/login
```

Uma autenticação válida deverá retornar uma resposta JSON indicando sucesso e contendo os dados disponibilizados pelo CSP.

Exemplo simplificado:

```json
{
    "success": true,
    "orcamento": {},
    "estudos": []
}
```

Nunca adicione credenciais reais à documentação ou ao repositório.

---

## Testando a partir de um container Docker

Caso o WordPress esteja sendo executado através de Docker, pode ser necessário testar a comunicação diretamente de dentro do container.

Exemplo:

```bash
docker exec wordpress_php curl -k -i -X POST \
  -d "login=SEU_LOGIN" \
  -d "senha=SUA_SENHA" \
  https://SEU-CSP/api/client-hub/login
```

Esse teste é útil quando:

- O endpoint funciona no servidor.
- O CSP está online.
- Mas o WordPress informa que não conseguiu conectar ao CSP.

Se o `curl` funcionar no servidor e falhar dentro do container, o problema provavelmente está relacionado à rede, DNS ou configuração do Docker.

---

## Atualizações

O Client Hub possui integração com o GitHub para verificação de novas versões.

O sistema utiliza o **Plugin Update Checker** para consultar o repositório do projeto.

Repositório:

```text
https://github.com/gitimmhub/client-hub
```

A branch utilizada para verificação de atualizações é:

```text
main
```

Quando uma versão mais recente estiver disponível, o WordPress poderá informar que existe uma atualização do Client Hub.

A atualização poderá então ser realizada através do próprio painel administrativo do WordPress.

---

## Estrutura básica do plugin

A estrutura do projeto é semelhante a:

```text
client-hub/
│
├── assets/
│   ├── css/
│   └── js/
│
├── includes/
│
├── plugin-update-checker/
│
├── client-hub.php
├── README.md
└── DEVELOPMENT.md
```

### `client-hub.php`

Arquivo principal do plugin.

Responsável por inicializar o Client Hub, configurar a integração com o WordPress, controlar a autenticação e configurar o sistema de atualizações.

### `includes/`

Contém as classes responsáveis pela estrutura e funcionamento do plugin.

### `assets/`

Contém os arquivos utilizados na interface.

Exemplos:

```text
assets/css/
assets/js/
```

### `plugin-update-checker/`

Biblioteca utilizada para verificar novas versões do Client Hub através do GitHub.

---

## Problemas comuns

### Não foi possível conectar ao CSP

Verifique:

- Se o CSP está online.
- Se a URL configurada está correta.
- Se o servidor WordPress consegue acessar o domínio do CSP.
- Se HTTPS está funcionando.
- Se existem regras de firewall impedindo a comunicação.
- Se o container Docker consegue resolver o domínio do CSP.
- Se a porta utilizada pelo CSP está acessível.

Um teste com `curl` ajuda a identificar onde está o problema.

---

### Login ou senha inválidos

Verifique:

- Se o login informado está correto.
- Se a senha informada está correta.
- Se o acesso está ativo no CSP.
- Se o orçamento correto está associado ao acesso.
- Se o CSP está utilizando o banco de dados correto.
- Se o WordPress está utilizando o ambiente correto do CSP.

---

### O endpoint funciona, mas o WordPress não conecta

Primeiro teste diretamente no servidor:

```bash
curl -k -i -X POST \
  -d "login=SEU_LOGIN" \
  -d "senha=SUA_SENHA" \
  https://SEU-CSP/api/client-hub/login
```

Se estiver utilizando Docker, teste também dentro do container:

```bash
docker exec wordpress_php curl -k -i -X POST \
  -d "login=SEU_LOGIN" \
  -d "senha=SUA_SENHA" \
  https://SEU-CSP/api/client-hub/login
```

Se funcionar fora do container e falhar dentro dele, verifique a configuração de rede do Docker.

---

### Login funciona, mas a área do cliente não aparece

Se a API retornar sucesso, mas após atualizar a página o formulário de login continuar aparecendo, verifique:

- Se a sessão PHP está funcionando.
- Se os cookies estão habilitados.
- Se o navegador recebeu o cookie de sessão.
- Se a página está sendo armazenada em cache.
- Se existe cache no WordPress.
- Se existe cache no servidor.
- Se existe CDN realizando cache da página.

A página da Central do Cliente depende de sessão e não deve ser entregue como uma página estática em cache para todos os usuários.

---

### O AJAX funciona, mas a página não muda

O JavaScript do Client Hub atualiza a página após uma autenticação válida.

O fluxo esperado é:

```text
AJAX
  ↓
Login válido
  ↓
Sessão criada
  ↓
Resposta success
  ↓
Reload da página
  ↓
Shortcode verifica sessão
  ↓
Dashboard
```

Caso o AJAX retorne sucesso, mas o dashboard não seja exibido após o reload, investigue primeiro a sessão e o cache da página.

---

### Plugin não encontra uma atualização

Verifique:

- Se o servidor possui acesso ao GitHub.
- Se o repositório está acessível.
- Se a branch `main` está correta.
- Se a nova versão é superior à versão instalada.
- Se a versão do cabeçalho do plugin foi atualizada.
- Se o Plugin Update Checker está presente no plugin.

---

## Segurança

Algumas regras devem ser seguidas ao utilizar ou distribuir o Client Hub:

- Nunca coloque senhas de clientes no GitHub.
- Nunca coloque API Keys no GitHub.
- Nunca coloque tokens privados no repositório.
- Não armazene a senha informada pelo cliente na sessão.
- Utilize HTTPS em produção.
- Mantenha o WordPress atualizado.
- Mantenha o PHP atualizado.
- Mantenha o Client Hub atualizado.
- O CSP deve continuar sendo responsável pela validação das credenciais.
- O WordPress não deve possuir acesso direto ao banco do CSP.

---

## Repositório

O código-fonte do Client Hub está disponível no GitHub:

```text
https://github.com/gitimmhub/client-hub
```

---

## Documentação para desenvolvedores

Este README é destinado principalmente à instalação, configuração e utilização do Client Hub.

Informações relacionadas ao desenvolvimento e manutenção do projeto serão documentadas separadamente no arquivo:

```text
DEVELOPMENT.md
```

A documentação de desenvolvimento inclui informações como:

- Arquitetura completa.
- Configuração do ambiente de desenvolvimento.
- Integração CSP ↔ WordPress.
- Estrutura necessária no CSP.
- Banco de dados e migrations.
- Endpoint da API.
- Ambientes de desenvolvimento e produção.
- Docker.
- Git e GitHub.
- Versionamento.
- Publicação de novas versões.
- Plugin Update Checker.
- Testes.
- Diagnóstico de problemas.
- Checklist de publicação.