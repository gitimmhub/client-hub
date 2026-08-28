<?php
/**
 * Plugin Name: Client Hub
 * Plugin URI: https://github.com/gitimmhub/client-hub
 * Description: Portal do cliente integrado ao CSP para acesso a orçamentos e estudos.
 * Version: 1.5.1
 * Author: Matheus Barbiéri
 * Author URI: https://github.com/gitimmhub
 * Text Domain: client-hub
 */

if (!defined('ABSPATH')) {
    exit;
}

require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/gitimmhub/client-hub/',
    __FILE__,
    'client-hub'
);

$updateChecker->setBranch('main');

define('CLIENT_HUB_VERSION', '1.5.1');
define('CLIENT_HUB_FILE', __FILE__);
define('CLIENT_HUB_PATH', plugin_dir_path(__FILE__));
define('CLIENT_HUB_URL', plugin_dir_url(__FILE__));


require_once CLIENT_HUB_PATH . 'includes/class-client-hub.php';

function client_hub()
{
    return Client_Hub::getInstance();
}

client_hub()->init();

add_action('template_redirect', function () {
    if (is_page('estudo-viabilidade-acesso')) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        nocache_headers();
    }
}, 0);

/*
 * Inicia a sessão utilizada pelo portal.
 */
add_action('init', function () {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    if (!empty($_SESSION['client_hub']['autenticado'])) {
        $ultimaAtividade = $_SESSION['client_hub']['ultima_atividade'] ?? 0;

        if (
            $ultimaAtividade > 0
            && time() - $ultimaAtividade > 3600
        ) {
            unset($_SESSION['client_hub']);
        } else {
            $_SESSION['client_hub']['ultima_atividade'] = time();
        }
    }
}, 1);

/*
 * Login via AJAX.
 */
add_action(
    'wp_ajax_client_hub_login',
    'client_hub_login'
);

add_action(
    'wp_ajax_nopriv_client_hub_login',
    'client_hub_login'
);

/*
 * Logout via admin-post.php.
 */
add_action(
    'admin_post_client_hub_logout',
    'client_hub_handle_logout'
);

add_action(
    'admin_post_nopriv_client_hub_logout',
    'client_hub_handle_logout'
);

/**
 * Envia um aviso por e-mail quando o cliente realiza login.
 */
function client_hub_send_login_notification(
    string $email_responsavel,
    array $orcamento,
    string $login
): bool {
    if (
        $email_responsavel === ''
        || !is_email($email_responsavel)
    ) {
        return false;
    }

    $cliente = !empty($orcamento['cliente'])
        ? $orcamento['cliente']
        : 'Não identificado';

    $numero_orcamento = !empty($orcamento['numero'])
        ? $orcamento['numero']
        : 'Não identificado';

    $data_hora = wp_date(
        'd/m/Y \à\s H:i:s'
    );

    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(
            wp_unslash($_SERVER['REMOTE_ADDR'])
        )
        : 'Não identificado';

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? sanitize_text_field(
            wp_unslash($_SERVER['HTTP_USER_AGENT'])
        )
        : 'Não identificado';

    $assunto = sprintf(
        'Acesso à Central do Cliente - Orçamento %s',
        $numero_orcamento
    );

    $mensagem = implode("\n", [
        'Olá!',
        '',
        'Um login foi realizado na Central do Cliente da WGB Engenharia.',
        '',
        'Cliente: ' . $cliente,
        'Orçamento: ' . $numero_orcamento,
        'Login utilizado: ' . $login,
        'Data e horário: ' . $data_hora,
        'Endereço IP: ' . $ip,
        'Dispositivo/Navegador: ' . $user_agent,
        '',
        'Este é um aviso automático. Nenhuma ação é necessária.',
        '',
        'Caso você não reconheça este acesso, recomendamos redefinir a senha do portal.',
    ]);

    $enviado = wp_mail(
        $email_responsavel,
        $assunto,
        $mensagem,
        [
            'Content-Type: text/plain; charset=UTF-8',
        ]
    );

    if (!$enviado) {
        error_log(
            '[Client Hub] Não foi possível enviar o aviso de login para: '
            . $email_responsavel
        );
    }

    return $enviado;
}

/**
 * Realiza o login consultando a API do CSP.
 */
/**
 * Realiza o login consultando a API do CSP via AJAX.
 */
function client_hub_login(): void
{
    /*
     * Valida o nonce sem encerrar com o "-1" padrão do WordPress.
     */
    //if (!check_ajax_referer('client_hub_login', 'nonce', false)) {
    //    wp_send_json([
    //        'success' => false,
    //        'message' => 'Sessão ou token de segurança expirado. Atualize a página e tente novamente.',
    //    ], 403);
    //}

    /*
     * Garante que a sessão PHP esteja ativa.
     */
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    /*
     * Recebe e sanitiza os dados do formulário.
     */
    $login = isset($_POST['login'])
        ? sanitize_text_field(wp_unslash($_POST['login']))
        : '';

    $senha = isset($_POST['senha'])
        ? (string) wp_unslash($_POST['senha'])
        : '';

    if ($login === '' || $senha === '') {
        wp_send_json([
            'success' => false,
            'message' => 'Informe o login e a senha.',
        ], 422);
    }

    /*
     * Endpoint de produção do CSP.
     */
    $api_url = 'https://wgb.csp.app.br/api/client-hub/login';

    /*
     * Realiza a requisição para o CSP.
     */
    $response = wp_remote_post($api_url, [
        'timeout'   => 20,
        'sslverify' => true,
        'body'      => [
            'login' => $login,
            'senha' => $senha,
        ],
    ]);

    /*
     * Trata erros de conexão.
     */
    if (is_wp_error($response)) {
        wp_send_json([
            'success' => false,
            'message' => 'Não foi possível conectar ao servidor de autenticação.',
            'error'   => $response->get_error_message(),
        ], 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    /*
     * Verifica se o CSP retornou um JSON válido.
     */
    if (!is_array($data)) {
        error_log(
            '[Client Hub] Resposta inválida recebida do CSP: ' . $body
        );

        wp_send_json([
            'success'     => false,
            'message'     => 'O servidor de autenticação retornou uma resposta inválida.',
            'status_code' => $status_code,
        ], 502);
    }

    /*
     * Trata credenciais inválidas ou outros erros da API.
     */
    if (
        $status_code < 200
        || $status_code >= 300
        || empty($data['success'])
    ) {
        wp_send_json([
            'success' => false,
            'message' => $data['message']
                ?? 'Login ou senha inválidos.',
        ], 401);
    }

    /*
     * Verifica se o orçamento foi retornado.
     */
    if (
        empty($data['orcamento'])
        || !is_array($data['orcamento'])
    ) {
        wp_send_json([
            'success' => false,
            'message' => 'Os dados do orçamento não foram retornados.',
        ], 502);
    }

    /*
     * Obtém o e-mail do responsável retornado pelo CSP.
     */
    $email_responsavel = '';

    if (
        !empty($data['acesso'])
        && is_array($data['acesso'])
        && !empty($data['acesso']['email_responsavel'])
    ) {
        $email_responsavel = sanitize_email(
            $data['acesso']['email_responsavel']
        );
    }

    /*
     * Normaliza os dados do orçamento.
     */
    $orcamento = [
        'id' => isset($data['orcamento']['id'])
            ? absint($data['orcamento']['id'])
            : 0,

        'numero' => isset($data['orcamento']['numero'])
            ? sanitize_text_field($data['orcamento']['numero'])
            : '',

        'cliente' => isset($data['orcamento']['cliente'])
            ? sanitize_text_field($data['orcamento']['cliente'])
            : '',

        'cpf_cnpj' => isset($data['orcamento']['cpf_cnpj'])
            ? sanitize_text_field($data['orcamento']['cpf_cnpj'])
            : '',

        'email' => isset($data['orcamento']['email'])
            ? sanitize_email($data['orcamento']['email'])
            : '',

        'pdf_url' => !empty($data['orcamento']['pdf_url'])
            ? esc_url_raw($data['orcamento']['pdf_url'])
            : '',

        'pdf_disponivel' => !empty(
            $data['orcamento']['pdf_disponivel']
        ),
    ];

    /*
     * Normaliza os estudos retornados pelo CSP.
     */
    $estudos = [];

    if (
        isset($data['estudos'])
        && is_array($data['estudos'])
    ) {
        foreach ($data['estudos'] as $estudo) {
            if (!is_array($estudo)) {
                continue;
            }

            $estudo_id = isset($estudo['id'])
                ? absint($estudo['id'])
                : 0;

            $nome = isset($estudo['nome'])
                ? sanitize_text_field($estudo['nome'])
                : '';

            $view_url = !empty($estudo['view_url'])
                ? esc_url_raw($estudo['view_url'])
                : '';

            $download_url = !empty($estudo['download_url'])
                ? esc_url_raw($estudo['download_url'])
                : '';

            if (
                $estudo_id <= 0
                || $nome === ''
                || $download_url === ''
            ) {
                continue;
            }

            $estudos[] = [
                'id' => $estudo_id,
                'nome' => $nome,

                'created_at' => isset($estudo['created_at'])
                    ? sanitize_text_field($estudo['created_at'])
                    : '',

                'view_url' => $view_url,
                'download_url' => $download_url,
            ];
        }
    }

    /*
     * Regenera e salva a sessão autenticada.
     */
    session_regenerate_id(true);

    $_SESSION['client_hub'] = [
        'autenticado'      => true,
        'login'            => $login,
        'login_em'         => time(),
        'ultima_atividade' => time(),
        'orcamento'        => $orcamento,
        'estudos'          => $estudos,
    ];

    /*
     * Envia o aviso de login para o responsável.
     */
    $email_enviado = client_hub_send_login_notification(
        $email_responsavel,
        $orcamento,
        $login
    );

    session_write_close();

    /*
     * Retorna o resultado para o JavaScript.
     */
    wp_send_json([
        'success'   => true,
        'message'   => 'Login realizado com sucesso.',
        'orcamento' => $orcamento,
        'estudos'   => $estudos,

        'notificacao' => [
            'email_configurado' => $email_responsavel !== '',
            'email_enviado'     => $email_enviado,
        ],
    ]);
}

/**
 * Encerra a sessão do cliente.
 */
function client_hub_handle_logout(): void
{
    check_admin_referer(
        'client_hub_logout'
    );

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    unset($_SESSION['client_hub']);

    session_regenerate_id(true);
    session_write_close();

    nocache_headers();

    $redirect_url = wp_get_referer();

    if (!$redirect_url) {
        $redirect_url = home_url('/');
    }

    $redirect_url = remove_query_arg(
        [
            'client_hub_logout',
            '_wpnonce',
        ],
        $redirect_url
    );

    wp_safe_redirect($redirect_url);
    exit;
}