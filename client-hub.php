<?php
/**
 * Plugin Name: Client Hub
 * Plugin URI: https://github.com/gitimmhub/client-hub
 * Description: Portal do cliente integrado ao CSP para acesso a orçamentos e estudos.
 * Version: 1.3.1.1
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

define('CLIENT_HUB_VERSION', '1.3.1.1');
define('CLIENT_HUB_FILE', __FILE__);
define('CLIENT_HUB_PATH', plugin_dir_path(__FILE__));
define('CLIENT_HUB_URL', plugin_dir_url(__FILE__));


require_once CLIENT_HUB_PATH . 'includes/class-client-hub.php';

function client_hub()
{
    return Client_Hub::getInstance();
}

client_hub()->init();

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
function client_hub_login(): void
{
    check_ajax_referer(
        'client_hub_login',
        'nonce'
    );

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $login = isset($_POST['login'])
        ? sanitize_text_field(
            wp_unslash($_POST['login'])
        )
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
     * Em produção, trocar pela URL pública do CSP.
     */
    $api_url = 'https://wgb.csp.app.br/api/client-hub/login';

    /*
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = explode(':', $host)[0];
    if ($host === 'wordpress.local') {
        $api_url = 'https://wgbdev.maestro.local/api/client-hub/login';
    } else {
        $partes = explode('.', $host);
        $subdominio = (count($partes) > 2) ? $partes[0] : 'default';

        $api_url = "https://{$subdominio}.csp.app.br/api/client-hub/login";
    }
    */
    $response = wp_remote_post($api_url, [
        'timeout' => 20,

        /*
         * Mantenha false somente enquanto estiver usando
         * certificado local autoassinado.
         *
         * Em produção, altere para true.
         */
        'sslverify' => false,

        'body' => [
            'login' => $login,
            'senha' => $senha,
        ],
    ]);

    if (is_wp_error($response)) {
        wp_send_json([
            'success' => false,
            'message' => 'Não foi possível conectar ao CSP.',
            'error'   => $response->get_error_message(),
        ], 502);
    }

    $status_code = wp_remote_retrieve_response_code(
        $response
    );

    $body = wp_remote_retrieve_body(
        $response
    );

    $data = json_decode(
        $body,
        true
    );

    if (!is_array($data)) {
        error_log(
            '[Client Hub] Resposta inválida recebida do CSP: '
            . $body
        );

        wp_send_json([
            'success'     => false,
            'message'     => 'O CSP retornou uma resposta inválida.',
            'status_code' => $status_code,
        ], 502);
    }

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
     * E-mail do responsável retornado pelo CSP.
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
            ? sanitize_text_field(
                $data['orcamento']['numero']
            )
            : '',

        'cliente' => isset($data['orcamento']['cliente'])
            ? sanitize_text_field(
                $data['orcamento']['cliente']
            )
            : '',

        'cpf_cnpj' => isset($data['orcamento']['cpf_cnpj'])
            ? sanitize_text_field(
                $data['orcamento']['cpf_cnpj']
            )
            : '',

        'email' => isset($data['orcamento']['email'])
            ? sanitize_email(
                $data['orcamento']['email']
            )
            : '',

        'pdf_url' => !empty($data['orcamento']['pdf_url'])
            ? esc_url_raw(
                $data['orcamento']['pdf_url']
            )
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
                ? sanitize_text_field(
                    $estudo['nome']
                )
                : '';

            $view_url = !empty($estudo['view_url'])
                ? esc_url_raw(
                    $estudo['view_url']
                )
                : '';

            $download_url = !empty(
                $estudo['download_url']
            )
                ? esc_url_raw(
                    $estudo['download_url']
                )
                : '';

            if (
                $estudo_id <= 0
                || $nome === ''
                || $download_url === ''
            ) {
                continue;
            }

            $estudos[] = [
                'id'   => $estudo_id,
                'nome' => $nome,

                'created_at' => isset(
                    $estudo['created_at']
                )
                    ? sanitize_text_field(
                        $estudo['created_at']
                    )
                    : '',

                'view_url'     => $view_url,
                'download_url' => $download_url,
            ];
        }
    }

    /*
     * Regenera o ID da sessão após autenticar.
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
     * Envia aviso para o responsável em todo login bem-sucedido.
     */
    $email_enviado = client_hub_send_login_notification(
        $email_responsavel,
        $orcamento,
        $login
    );

    session_write_close();

    wp_send_json([
        'success'   => true,
        'message'   => 'Login realizado com sucesso.',
        'orcamento' => $orcamento,
        'estudos'   => $estudos,

        /*
         * Informações úteis durante os testes.
         */
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