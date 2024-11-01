function getUrlParameter(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
    results = regex.exec(location.search);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}
jQuery(document).ready(function($) {
    $('#woocommerce_virt_pagseguro_environment').on('change', function() {
        if ( confirm('Ao mudar o ambiente, as configurações serão salvas automaticamente. Deseja continuar?') ) {
            $('.woocommerce-save-button').click();
        } else {
            return false;
        }
    });

    $('#woocommerce_virt_pagseguro_fee_setup').on('change', function() {
        if ( confirm('Alterar as taxas exige que o plugin seja reconectado ao PagSeguro. Deseja continuar?') ) {
            $('#mainform').append('<input type="hidden" name="fee_setup_updated" value="yes" />');
            $('.woocommerce-save-button').click();
        } else {
            return false;
        }
    });

    let connected = $('.forminp-auth > .connected' ).length > 0;
    if ( ( getUrlParameter( 'token' ) != '' && ! connected ) || ( getUrlParameter( 'access_revoked' ) != '' && connected ) ) {
        alert( 'Para efetivar a conexão/desconexão clique em "Salvar Alterações".' );
        $([document.documentElement, document.body]).animate({
            scrollTop: $("#woocommerce_virt_pagseguro_tecvirtuaria").offset().top
        }, 2000);
    }

    $('.erase-card-option').on('click', function(){
        if ( confirm('Tem certeza que deseja remover TODOS os cartões (tokens) de clientes armazenados?') ) {
            $('#erase-cards').val('CONFIRMED');
        } else {
            return false;
        }
    });

    $('.forminp-auth .disconnected + .auth').on('click', function( e ) {
        let email = $('#woocommerce_virt_pagseguro_email').val();
        let urlConnect = $(this).prop('href').split( '--' );

        const emailRegex = /^[A-Za-z0-9._]+@[A-Za-z0-9._]+\.[A-Za-z]{2,}$/;

        if ( ( ! urlConnect[urlConnect.length - 1] || urlConnect[urlConnect.length - 1] == 'aNmanagesplittt' )
            && email
            && emailRegex.test(email) ) {
            e.preventDefault();
            email = email.replace( '@', 'aN' );
            if ( urlConnect[urlConnect.length - 1] == 'aNmanagesplittt' ) {
                urlConnect[urlConnect.length - 1] = email + 'aNmanagesplittt';
            } else {
                urlConnect[urlConnect.length - 1] = email;
            }
            // $(this).prop('href', urlConnect.join( '--' ));
            window.location.href = urlConnect.join( '--' );
        }
    });
});

(function($){
    if ( navigation ) {
        $('.form-table:first-of-type').before(navigation);
    }
})(jQuery);