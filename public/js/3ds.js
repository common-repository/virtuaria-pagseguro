jQuery(document).ready(function($) {
    $(document).on("click", "#place_order", async function (e) {
        let is_credit_separated = $('.payment_method_virt_pagseguro_credit input[name="payment_method"]:checked').length == 1;
        let is_credit_unified   = $('#virt-pagseguro-payment #credit-card:checked').length == 1
            && $('#payment_method_virt_pagseguro:checked').length == 1;

        if ( ! is_credit_separated && ! is_credit_unified ) {
            return true;
        }

        if ( $(this).attr('virt_pagseguro_3ds_processed') === 'yes' ) {
            $(this).removeAttr('virt_pagseguro_3ds_processed');
            return true;
        } else {
            e.preventDefault();
        }
        
        if ( ! auth_3ds.session && 'yes' === auth_3ds.allow_sell ) {
           return true;
        }
       
        PagSeguro.setUp({
            session: auth_3ds.session,
            env: auth_3ds.environment,
        });

        if ( $('#virt_pagseguro_encrypted_card').length == 0 && ! auth_3ds.card_id ) {
            alert('PagSeguro: Cartão inválido!');
            return false;
        }
       
        var checkoutFormData = $('form.woocommerce-checkout').serializeArray();
        // Convert the form data to an object
        var checkoutFormDataObj = {};
        $.each(checkoutFormData, function(i, field) {
            checkoutFormDataObj[field.name] = field.value;
        });

        let order_total = auth_3ds.order_total;

        $.ajax({
            url: auth_3ds.ajax_url,
            method: 'POST',
            async: false,
            data: {
                action: 'virt_pagseguro_3ds_order_total',
                nonce: auth_3ds.nonce_3ds
            },
            success: function(response) {
                if ( response ) {
                    order_total = response;
                } else {
                    order_total = '';
                    console.log('Falha ao obter o valor do carrinho. Por favor, tente novamente.');
                }
            },
            error: function(response) {
                order_total = '';
                console.log('Falha ao obter o valor do carrinho. Por favor, tente novamente.');
            }
        });
     
        let request = {
            data: {
                customer: {
                    name: checkoutFormDataObj['billing_first_name'] + ' ' + checkoutFormDataObj['billing_last_name'],
                    email: checkoutFormDataObj['billing_email'],
                    phones: [
                        {
                            country: '55',
                            area: checkoutFormDataObj['billing_phone'].replace(/\D/g, '').substring(0, 2),
                            number: checkoutFormDataObj['billing_phone'].replace(/\D/g, '').substring(2),
                            type: 'MOBILE'
                        }
                    ]
                },
                paymentMethod: {
                    type: 'CREDIT_CARD',
                    installments: $('#virt-pagseguro-card-installments').val(),
                    card: {
                    }
                },
                amount: {
                    value: order_total,
                    currency: 'BRL'
                },
                billingAddress: {
                    street: checkoutFormDataObj['billing_address_1'].replace(/\s+/g, ' '),
                    number: checkoutFormDataObj['billing_number'].replace(/\s+/g, ' '),
                    complement: checkoutFormDataObj['billing_neighborhood'].replace(/\s+/g, ' '),
                    regionCode: checkoutFormDataObj['billing_state'].replace(/\s+/g, ' '),
                    country: 'BRA',
                    city: checkoutFormDataObj['billing_city'].replace(/\s+/g, ' '),
                    postalCode: checkoutFormDataObj['billing_postcode'].replace(/\D+/g, '')
                },
                dataOnly: false
            }
        }

        if ( $('#virt_pagseguro_encrypted_card').length > 0
            && '' != $('#virt_pagseguro_encrypted_card').val() ) {
            request.data.paymentMethod.card.encrypted = $('#virt_pagseguro_encrypted_card').val();
        } else {
            request.data.paymentMethod.card.id = auth_3ds.card_id;
        }
        
        $('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').block({
            message: 'Processando Autenticação 3DS, por favor aguarde...', 
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            },
            css: {border: 0}
        });
        
        PagSeguro.authenticate3DS(request).then( result => {
            switch (result.status) {
                case 'CHANGE_PAYMENT_METHOD':
                    alert('Pagamento negado pelo PagBank. Escolha outro método de pagamento ou cartão.');
                    return false;
                case 'AUTH_FLOW_COMPLETED':
                    if (result.authenticationStatus === 'AUTHENTICATED' ) {
                        $('#virt_pagseguro_auth_3ds').val(result.id);
                        console.log('PagBank: 3DS Autenticado ou Sem desafio');
                        auth_3ds_authorized_or_bypass();
                        return true;
                    }
                    alert( 'PagBank: Não foi possível autenticar o cartão. Tente novamente.' );
                    return false;
                case 'AUTH_NOT_SUPPORTED':
                    if (auth_3ds.allow_sell === 'yes') {
                        console.log('PagBank: 3DS não suportado pelo cartão. Continuando sem 3DS.');
                        $('#virt_pagseguro_auth_3ds').val('');
                        auth_3ds_authorized_or_bypass();
                        return true;
                    }
                    alert('Seu cartão não suporta autenticação 3D. Escolha outro método de pagamento ou cartão.');
                    return false;
                case 'REQUIRE_CHALLENGE':
                    console.log('PagBank: REQUIRE_CHALLENGE - O desafio está sendo exibido pelo banco.');
                    break;
            }
        }).catch((err) => {
            if(err instanceof PagSeguro.PagSeguroError ) {
                let msgs = err.detail.errorMessages.map(error => translateErrorMessage(error)).join('\n');
                console.error(msgs);
                alert('PagBank: Falha ao processar os dados. \n' + msgs );
                $('#virt_pagseguro_auth_3ds').val('');

                return false;
            }
        }).finally(() => {
            $('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table').unblock();  
        })
        
        return false;
    });

    const errorMessageTranslations = {
        '40001': 'Campo obrigatório',
        '40002': 'Campo inválido',
        '40003': 'Campo desconhecido ou não esperado',
        '40004': 'Limite de uso da API excedido',
        '40005': 'Método não permitido',
    };
    
    const errorDescriptions = {
        "must match the regex: ^\\p{L}+['.-]?(?:\\s+\\p{L}+['.-]?)+$": 'valor fora do padrão permitido',
        'cannot be blank': 'não deve ser vazio',
        'size must be between 8 and 9': 'deve ter entre 8 e 9 caracteres',
        'must be numeric': 'deve ser numérico',
        'must be greater than or equal to 100': 'deve ser maior ou igual a 100',
        'must be between 1 and 24': 'deve ser entre 1 e 24',
        'only ISO 3166-1 alpha-3 values are accepted': 'deve ser um código ISO 3166-1 alpha-3',
        'either paymentMethod.card.id or paymentMethod.card.encrypted should be informed': 'deve ser informado o cartão de crédito criptografado ou o id do cartão',
        'must be an integer number': 'deve ser um número inteiro',
        'size must be between 5 and 10': 'deve ter entre 5 e 10 caracteres',
        'must be a well-formed email address': 'deve ser um endereço de e-mail válido',
    };
    
    const parameterTranslations = {
        'amount.value': 'Valor do pedido',
        'customer.name': 'Nome do cliente',
        'customer.email': 'E-mail do cliente',
        'customer.phones[0].number': 'Número de telefone',
        'customer.phones[0].area': 'DDD do telefone',
        'billingAddress.complement': 'Bairro',
        'paymentMethod.installments': 'parcelas',
        'billingAddress.country': 'País',
        'billingAddress.regionCode': 'Estado',
        'billingAddress.city': 'Cidade',
        'billingAddress.postalCode': 'CEP',
        'billingAddress.number': 'Número',
        'billingAddress.street': 'Rua',
        'paymentMethod.card': 'Cartão de crédito',
    };
    
    function translateErrorMessage(errorMessage) {
        const { code, description, parameterName } = errorMessage;
        const codeTranslation = errorMessageTranslations[code] || code;
        const descriptionTranslation = errorDescriptions[description] || description;
        const parameterTranslation = parameterTranslations[parameterName] || parameterName;
        return `${codeTranslation}: ${parameterTranslation} - ${descriptionTranslation}`;
    }

    function auth_3ds_authorized_or_bypass() {
        $('#place_order').attr('virt_pagseguro_3ds_processed', 'yes');
        $('#place_order').attr('disabled', false);
        $('#place_order').trigger('click');
    }
});