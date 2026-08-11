jQuery(function ($) {

    /*
     * LOGIN
     */
    $('#client-hub-login').on('submit', function (event) {
        event.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');

        $button
            .prop('disabled', true)
            .text('Acessando...');

        $.ajax({
            url: clientHub.ajax_url,
            type: 'POST',
            dataType: 'json',

            data: {
                action: 'client_hub_login',
                nonce: clientHub.nonce,
                login: $form.find('[name="login"]').val(),
                senha: $form.find('[name="senha"]').val()
            },

            success: function (response) {
                if (!response?.success) {
                    alert(
                        response?.message
                        || 'Não foi possível realizar o login.'
                    );

                    return;
                }

                window.location.reload();
            },

            error: function (xhr) {
                const response = xhr.responseJSON;

                alert(
                    response?.message
                    || 'Erro ao conectar ao servidor.'
                );
            },

            complete: function () {
                $button
                    .prop('disabled', false)
                    .text('Acessar');
            }
        });
    });


    /*
     * MODAL PDF
     */
    const $modal = $('#client-hub-pdf-modal');
    const $frame = $('#client-hub-pdf-frame');
    const $title = $('#client-hub-pdf-title');
    const $loading = $('.client-hub-pdf-loading');

    if ($modal.length) {
        $modal.appendTo('body');
    }


    function abrirPdf(url, title) {
        if (!url) {
            return;
        }

        $title.text(
            title || 'Documento'
        );

        $loading.show();
        $frame.hide();

        $frame.attr(
            'src',
            url
        );

        $modal
            .addClass('is-open')
            .attr('aria-hidden', 'false');

        $('body').addClass(
            'client-hub-modal-open'
        );
    }


    function fecharPdf() {
        $modal
            .removeClass('is-open')
            .attr('aria-hidden', 'true');

        $frame.attr(
            'src',
            ''
        );

        $frame.hide();
        $loading.show();

        $('body').removeClass(
            'client-hub-modal-open'
        );
    }


    /*
     * Abre orçamento ou estudo.
     */
    $(document).on(
        'click',
        '.client-hub-view-pdf',
        function () {
            const url = $(this).attr(
                'data-pdf-url'
            );

            const title = $(this).attr(
                'data-pdf-title'
            );

            abrirPdf(
                url,
                title
            );
        }
    );


    /*
     * Quando o PDF terminar de carregar.
     */
    $frame.on('load', function () {
        if (!$frame.attr('src')) {
            return;
        }

        $loading.hide();
        $frame.show();
    });


    /*
     * Fecha no X.
     */
    $(document).on(
        'click',
        '.client-hub-pdf-close',
        fecharPdf
    );


    /*
     * Fecha clicando no fundo.
     */
    $(document).on(
        'click',
        '.client-hub-pdf-backdrop',
        fecharPdf
    );


    /*
     * Fecha com ESC.
     */
    $(document).on('keydown', function (event) {
        if (
            event.key === 'Escape'
            && $modal.hasClass('is-open')
        ) {
            fecharPdf();
        }
    });

});