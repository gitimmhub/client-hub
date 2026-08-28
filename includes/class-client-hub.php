<?php

if (!defined('ABSPATH')) {
    exit;
}

class Client_Hub
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init()
    {
        add_action(
            'wp_enqueue_scripts',
            [$this, 'enqueueAssets']
        );

        add_shortcode(
            'client_hub',
            [$this, 'renderLogin']
        );
    }

    public function enqueueAssets()
    {
        wp_enqueue_style(
            'client-hub',
            CLIENT_HUB_URL . 'assets/css/client-hub.css',
            [],
            CLIENT_HUB_VERSION
        );

        wp_enqueue_script(
            'client-hub',
            CLIENT_HUB_URL . 'assets/js/client-hub.js',
            ['jquery'],
            CLIENT_HUB_VERSION,
            true
        );

        wp_localize_script(
            'client-hub',
            'clientHub',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
            ]
        );
    }

    public function renderLogin()
    {
        ob_start();

        $sessao = $_SESSION['client_hub'] ?? [];

        if (
            !empty($sessao['autenticado'])
            && !empty($sessao['orcamento'])
            && is_array($sessao['orcamento'])
        ) {
            $orcamento = $sessao['orcamento'];

            $estudos = (
                !empty($sessao['estudos'])
                && is_array($sessao['estudos'])
            )
                ? $sessao['estudos']
                : [];
            ?>

            <div class="client-hub client-hub-dashboard">

                <h2>Central do Cliente</h2>

                <p class="client-hub-welcome">
                    Olá,
                    <strong>
                        <?= esc_html(
                            $orcamento['cliente'] ?? ''
                        ) ?>
                    </strong>
                </p>

                <div class="client-hub-orcamento">

                    <h3>Orçamento</h3>

                    <p>
                        Número:
                        <strong>
                            <?= esc_html(
                                $orcamento['numero'] ?? ''
                            ) ?>
                        </strong>
                    </p>

                    <?php if (!empty($orcamento['pdf_url'])): ?>

                        <button
                        type="button"
                        class="client-hub-open-pdf client-hub-view-pdf"
                        data-pdf-url="<?= esc_url(
                            $orcamento['pdf_url']
                        ) ?>"
                        data-pdf-title="<?= esc_attr(
                            'Orçamento ' . ($orcamento['numero'] ?? '')
                        ) ?>"
                    >
                        Abrir orçamento
                    </button>

                    <?php else: ?>

                        <p class="client-hub-empty">
                            O orçamento ainda não está disponível.
                        </p>

                    <?php endif; ?>

                </div>

                <hr>

                <div class="client-hub-estudos">

                    <h3>Estudos disponíveis</h3>

                    <?php if (!empty($estudos)): ?>

                        <div class="client-hub-estudos-lista">

                            <?php foreach ($estudos as $estudo): ?>

                                <?php
                                $nome = !empty($estudo['nome'])
                                    ? str_replace(
                                        '_',
                                        ' ',
                                        $estudo['nome']
                                    )
                                    : 'Estudo';

                                $view_url = !empty(
                                    $estudo['view_url']
                                )
                                    ? $estudo['view_url']
                                    : '';

                                ?>

                                <div class="client-hub-estudo-item">

                                    <div class="client-hub-estudo-info">

                                        <strong class="client-hub-estudo-nome">
                                            <?= esc_html($nome) ?>
                                        </strong>

                                        <?php if (
                                            !empty($estudo['created_at'])
                                        ): ?>

                                            <small class="client-hub-estudo-data">
                                                Enviado em:
                                                <?= esc_html(
                                                    date_i18n(
                                                        'd/m/Y H:i',
                                                        strtotime(
                                                            $estudo['created_at']
                                                        )
                                                    )
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                    <div class="client-hub-estudo-acoes">

                                        <?php if ($view_url !== ''): ?>

                                            <button
                                            type="button"
                                            class="client-hub-view-pdf"
                                            data-pdf-url="<?= esc_url($view_url) ?>"
                                            data-pdf-title="<?= esc_attr($nome) ?>"
                                        >
                                            Visualizar
                                        </button>

                                        <?php endif; ?>


                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <p class="client-hub-empty">
                            Nenhum estudo está disponível no momento.
                        </p>

                    <?php endif; ?>

                </div>

                <form
                    method="post"
                    action="<?= esc_url(
                        admin_url('admin-post.php')
                    ) ?>"
                    class="client-hub-logout"
                >
                    <input
                        type="hidden"
                        name="action"
                        value="client_hub_logout"
                    >

                    <?php wp_nonce_field('client_hub_logout'); ?>

                    <button
                        type="submit"
                        class="client-hub-logout-button"
                    >
                        Sair
                    </button>
                </form>

            </div>

            <div
                id="client-hub-pdf-modal"
                class="client-hub-pdf-modal"
                aria-hidden="true"
            >
                <div class="client-hub-pdf-backdrop"></div>

                <div
                    class="client-hub-pdf-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="client-hub-pdf-title"
                >

                    <div class="client-hub-pdf-header">

                        <h3 id="client-hub-pdf-title">
                            Documento
                        </h3>

                        <button
                            type="button"
                            class="client-hub-pdf-close"
                            aria-label="Fechar"
                        >
                            &times;
                        </button>

                    </div>

                    <div class="client-hub-pdf-body">

                        <div class="client-hub-pdf-loading">
                            Carregando documento...
                        </div>

                        <iframe
                            id="client-hub-pdf-frame"
                            src=""
                            title="Visualização do documento"
                        ></iframe>

                    </div>

                </div>

            </div>

            <?php
        } else {
            ?>

            <div class="client-hub client-hub-login-card">

                <h2>Central do Cliente</h2>

                <form id="client-hub-login">

                    <div class="client-hub-group">

                        <label for="client-hub-login-field">
                            Login de acesso
                        </label>

                        <input
                            type="text"
                            id="client-hub-login-field"
                            name="login"
                            placeholder="Digite seu login"
                            autocomplete="username"
                            required
                        >

                    </div>

                    <div class="client-hub-group">

                        <label for="client-hub-password-field">
                            Senha de acesso
                        </label>

                        <input
                            type="password"
                            id="client-hub-password-field"
                            name="senha"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <button type="submit">
                        Acessar
                    </button>

                </form>

            </div>

            <?php
        }

        return ob_get_clean();
    }
    
}
