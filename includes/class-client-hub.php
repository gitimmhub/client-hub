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
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        add_shortcode('client_hub', [$this, 'renderLogin']);
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
    }

    public function renderLogin()
    {
        ob_start();
        ?>

        <div class="client-hub">

            <h2>Central do Cliente</h2>

            <p>
                Faça login para acessar seus estudos.
            </p>

            <form id="client-hub-login">

                <div class="client-hub-group">
                    <label>Login</label>

                    <input
                        type="text"
                        name="login"
                        required>
                </div>

                <div class="client-hub-group">
                    <label>Senha</label>

                    <input
                        type="password"
                        name="senha"
                        required>
                </div>

                <button type="submit">

                    Entrar

                </button>

            </form>

        </div>

        <?php

        return ob_get_clean();
    }
}