<?php
/**
 * Reused common code.
 *
 * @package virtuaria/payments/pagseguro.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

trait Virtuaria_PagSeguro_Common {
	/**
	 * Get default common settings.
	 */
	public function get_default_settings() {
		$options = array(
			'enabled'     => array(
				'title'   => __( 'Habilitar', 'virtuaria-pagseguro' ),
				'type'    => 'checkbox',
				'label'   => sprintf(
					/* translators: %s: method title */
					__( 'Habilita o método de Pagamento %s', 'virtuaria-pagseguro' ),
					$this->method_title
				),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => __( 'Título', 'virtuaria-pagseguro' ),
				'type'        => 'text',
				'description' => __( 'Isto controla o título exibido ao usuário durante o checkout.', 'virtuaria-pagseguro' ),
				'default'     => __( 'PagSeguro', 'virtuaria-pagseguro' ),
			),
			'description' => array(
				'title'       => __( 'Descrição', 'virtuaria-pagseguro' ),
				'type'        => 'textarea',
				'description' => __( 'Controla a descrição exibida ao usuário durante o checkout.', 'virtuaria-pagseguro' ),
				'default'     => __( 'Pague com PagSeguro.', 'virtuaria-pagseguro' ),
			),
		);

		if ( in_array( $this->id, array( 'virt_pagseguro', 'virt_pagseguro_credit' ), true ) ) {
			$options += array(
				'comments' => array(
					'title'       => __( 'Observações', 'virtuaria-pagseguro' ),
					'type'        => 'textarea',
					'description' => __( 'Exibe suas observações logo abaixo da descrição na tela de finalização da compra.', 'virtuaria-pagseguro' ),
					'default'     => '',
				),
			);
		}
		return $options;
	}

	/**
	 * Virtuaria tecnology setting.
	 */
	public function get_merchan_setting() {
		return array(
			'tecvirtuaria' => array(
				'title' => __( 'Tecnologia Virtuaria', 'virtuaria-pagseguro' ),
				'type'  => 'title',
			),
		);
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = 'yes' === $this->get_option( 'enabled' );

		if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' )
			&& ! class_exists( 'Virtuaria_Correios' ) ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Metabox to additional charge.
	 *
	 * @param WP_Post|WC_Order $post_or_order the post or order instance.
	 */
	public function additional_charge_metabox( $post_or_order ) {
		$order = $this->get_order_from_mixed( $post_or_order );

		$options = get_option( 'woocommerce_virt_pagseguro_settings' );
		$credit  = get_user_meta(
			$order->get_customer_id(),
			'_pagseguro_credit_info_store_' . get_current_blog_id(),
			true
		);
		$methods = array(
			'virt_pagseguro',
			'virt_pagseguro_credit',
			'virt_pagseguro_pix',
			'virt_pagseguro_ticket',
		);

		if ( ! in_array( $order->get_payment_method(), $methods, true ) ) {
			return;
		}

		if ( ! $order
			|| 'BOLETO' === $order->get_meta( '_payment_mode' )
			|| ( 'CREDIT_CARD' === $order->get_meta( '_payment_mode' ) && $this->global_settings['payment_status'] !== $order->get_status() )
			|| ( 'PIX' === $order->get_meta( '_payment_mode' ) && ! in_array( $order->get_status(), array( 'on-hold', $this->global_settings['payment_status'] ), true ) )
			|| ( ! isset( $options['enabled'] ) || 'yes' !== $options['enabled'] )
			|| ( ( ! isset( $credit['token'] ) || ! $credit['token'] ) && 'PIX' !== $order->get_meta( '_payment_mode' ) ) ) {
			return;
		}

		$title = $this->global_settings['payment_status'] === $order->get_status()
			? __( 'Cobrança Adicional', 'virtuaria-pagseguro' ) : __( 'Nova Cobrança', 'virtuaria-pagseguro' );

		add_meta_box(
			'pagseguro-additional-charge',
			$title,
			array( $this, 'display_additional_charge_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Content to additional charge metabox.
	 *
	 * @param WP_Post $post the post.
	 */
	public function display_additional_charge_content( $post ) {
		?>
		<label for="additional-value">Informe o valor a ser cobrado (R$):</label>
		<input type="number" style="width:calc(100% - 36px)" name="additional_value" id="additional-value" step="0.01" min="0.1"/>
		<button id="submit-additional-charge" style="padding: 3px 4px;vertical-align:middle;color:green;cursor:pointer">
			<span class="dashicons dashicons-money-alt"></span>
		</button>
		<label for="reason-charge" style="margin-top: 5px;">Motivo:</label>
		<input type="text" name="credit_charge_reason" id="reason-charge" style="display:block;max-width:219px;">
		<style>
			#submit-additional-charge {
				border-color: #0071a1;
				color: #0071a1;
				font-size: 16px;
				border-width: 1px;
				border-radius: 5px;
			}
		</style>
		<?php
		wp_nonce_field( 'do_additional_charge', 'additional_charge_nonce' );
	}

	/**
	 * Do additional charge.
	 *
	 * @param int $order_id the order id.
	 */
	public function do_additional_charge( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order
			|| ! in_array(
				$order->get_status(),
				array(
					'on-hold',
					$this->global_settings['payment_status'],
				),
				true
			)
			|| $this->id !== $order->get_payment_method()
		) {
			return;
		}

		if ( isset( $_POST['additional_value'] )
			&& isset( $_POST['additional_charge_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['additional_charge_nonce'] ) ), 'do_additional_charge' )
			&& floatval( $_POST['additional_value'] ) > 0 ) {
			$amount = number_format(
				sanitize_text_field( wp_unslash( $_POST['additional_value'] ) ),
				2,
				'',
				''
			);

			$resp = $this->api->additional_charge(
				$order,
				$amount,
				isset( $_POST['credit_charge_reason'] )
					? sanitize_text_field( wp_unslash( $_POST['credit_charge_reason'] ) )
					: ''
			);

			if ( 'PIX' === $order->get_meta( '_payment_mode' ) && $resp ) {
				$qr_code     = $order->get_meta( '_pagseguro_additional_qrcode', true );
				$qr_code_png = $order->get_meta( '_pagseguro_additional_qrcode_png', true );

				if ( $qr_code && $qr_code_png ) {
					$this->add_qrcode_in_note( $order, $qr_code );
					$validate     = $this->format_pix_validate( $this->pix_validate );
					$amount      /= 100;
					$charge_title = $amount == $order->get_total() ? 'Nova Cobrança' : 'Cobrança Extra';
					ob_start();
					echo '<p>Olá, ' . esc_html( $order->get_billing_first_name() ) . '.</p>';
					echo '<p><strong>Uma ' . esc_html( mb_strtolower( $charge_title ) )
						. ' está disponível para seu pedido.</strong></p>';
					remove_action(
						'woocommerce_email_after_order_table',
						array( $this, 'email_instructions' ),
						10,
						3
					);
					wc_get_template(
						'emails/email-order-details.php',
						array(
							'order'         => $order,
							'sent_to_admin' => false,
							'plain_text'    => false,
							'email'         => '',
						)
					);
					add_action(
						'woocommerce_email_after_order_table',
						array( $this, 'email_instructions' ),
						10,
						3
					);
					if ( $amount != $order->get_total() ) {
						echo '<p style="color:green"><strong style="display:block;">Valor da Cobrança Extra: R$ '
						. number_format( $amount, 2, ',', '.' ) . '.</strong>';
					}
					if ( isset( $_POST['charge_reason'] ) && ! empty( $_POST['charge_reason'] ) ) {
						$reason = 'Motivo: ' . esc_html( sanitize_text_field( wp_unslash( $_POST['charge_reason'] ) ) ) . '.';
					}
					echo wp_kses_post( $reason ) . '</p>';
					require_once VIRTUARIA_PAGSEGURO_DIR . 'templates/payment-instructions.php';
					$message = ob_get_clean();

					$this->send_email(
						$order->get_billing_email(),
						'[' . get_bloginfo( 'name' ) . '] ' . $charge_title . ' PIX no Pedido #' . $order_id,
						'Novo Código de Pagamento Disponível para seu Pedido ',
						$message
					);
				}
			}
		}
	}

	/**
	 * Required billing_neighborhood.
	 *
	 * @param array $fields the fields.
	 */
	public function billing_neighborhood_required( $fields ) {
		if ( isset( $fields['billing_neighborhood'] ) ) {
			$fields['billing_neighborhood']['required'] = true;
		}
		if ( isset( $fields['shipping_neighborhood'] ) ) {
			$fields['shipping_neighborhood']['required'] = true;
		}
		return $fields;
	}

	/**
	 * Retrieve the screen ID for meta boxes.
	 *
	 * @return string
	 */
	private function get_meta_boxes_screen() {
		return class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	/**
	 * Retrieves the order from either a WP_Post object or directly from the order.
	 *
	 * @param mixed $post_or_order The WP_Post object or the order.
	 * @return WC_Order The WooCommerce order object
	 */
	private function get_order_from_mixed( $post_or_order ) {
		return $post_or_order instanceof WP_Post
		? wc_get_order( $post_or_order->ID )
		: $post_or_order;
	}

	/**
	 * Create box to fetch order status.
	 *
	 * @param WP_Post|WC_Order $post_or_order instance from post or order object.
	 */
	public function fetch_order_status_metabox( $post_or_order ) {
		$order = $this->get_order_from_mixed( $post_or_order );

		if ( $order
			&& $this->id === $order->get_payment_method()
			&& $order->get_meta( '_charge_id', true ) ) {
			add_meta_box(
				'fetch-status',
				__( 'Consultar PagSeguro', 'virtuaria-pagseguro' ),
				array( $this, 'fetch_order_status_content' ),
				$this->get_meta_boxes_screen(),
				'side'
			);
		}
	}

	/**
	 * Fetch order status box callback.
	 *
	 * @param wc_order $order the order.
	 */
	public function fetch_order_status_content( $order ) {
		global $post;

		$order_id = $post instanceof WP_Post
			? $post->ID
			: $order->get_id()
		?>
		<small>Clique para checar o status de pagamento deste pedido no painel do PagSeguro.</small>
		<small>O resultado da consulta será exibido nas notas(histórico) do pedido.</small>
		<button id="fetch-order-payment" class="button-primary button">
			Verificar Status<span class="dashicons dashicons-money-alt" style="vertical-align: middle;margin-left:5px"></span>
		</button>
		<input type="hidden" name="fetch_order_payment">
		<script>
			jQuery(document).ready(function($){
				$('#fetch-order-payment').on('click', function(){
					$('input[name="fetch_order_payment"]').val('<?php echo esc_html( $order_id ); ?>');
				});
			});
		</script>
		<style>
			#fetch-status small {
				display: block;
				margin-bottom: 10px;
			}
			#fetch-order-payment {
				display: table;
				margin: 0 auto;
			}
		</style>
		<?php
		wp_nonce_field( 'search_order_payment_status', 'fetch_payment_nonce' );
	}

	/**
	 * Search payment status.
	 */
	public function search_order_payment_status() {
		if ( isset( $_POST['fetch_order_payment'] )
			&& isset( $_POST['fetch_payment_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fetch_payment_nonce'] ) ), 'search_order_payment_status' ) ) {
			$order = wc_get_order(
				sanitize_text_field(
					wp_unslash(
						$_POST['fetch_order_payment']
					)
				)
			);

			if ( $order ) {
				if ( $this->id !== $order->get_payment_method() ) {
					return;
				}
				$status = $this->api->fetch_payment_status(
					$order->get_meta(
						'_charge_id',
						true
					)
				);

				if ( $status ) {
					$translated = $status;

					switch ( $status ) {
						case 'AUTHORIZED':
							$translated = __(
								'Pré-autorizada. O total do pedido está reservado no cartão de crédito do cliente.',
								'virtuaria-pagseguro'
							);
							break;
						case 'PAID':
							$translated = __(
								'Paga.',
								'virtuaria-pagseguro'
							);
							break;
						case 'IN_ANALYSIS':
							$translated = __(
								'Em análise. O comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.',
								'virtuaria-pagseguro'
							);
							break;
						case 'DECLINED':
							$translated = __(
								'Negada pelo PagSeguro ou Emissor do Cartão de Crédito.',
								'virtuaria-pagseguro'
							);
							break;
						case 'CANCELED':
							$translated = __(
								'Cancelada.',
								'virtuaria-pagseguro'
							);
							break;
						case 'WAITING':
							$translated = __(
								'Aguardando Pagamento.',
								'virtuaria-pagseguro'
							);
							break;
					}
					$order->add_order_note(
						'Consulta PagSeguro: Transação ' . $translated,
						0,
						true
					);
					return;
				}
			}

			if ( ! $status && $order ) {
				$order->add_order_note(
					'PagSeguro: Não possível consultar o status de pagamento do pedido. Consulte o log para mais detalhes.',
					0,
					true
				);
			}
		}
	}

	/**
	 * Valid nonce from checkout methods.
	 *
	 * @return bool
	 */
	private function valid_checkout_nonce() {
		return (
			( isset( $_POST['new_charge_nonce'] )
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash( $_POST['new_charge_nonce'] )
					),
					'do_new_charge'
				)
			)
			|| ( isset( $_POST[ $this->id . '_nonce' ] )
				&& wp_verify_nonce(
					sanitize_text_field(
						wp_unslash( $_POST[ $this->id . '_nonce' ] )
					),
					'do_new_charge'
				)
			)
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @throws Exception Exception in block.
	 */
	public function process_payment( $order_id ) {
		if ( $this->signup_checkout
			|| $this->valid_checkout_nonce() ) {
			$order = wc_get_order( $order_id );

			if ( 'virt_pagseguro' !== $this->id ) {
				switch ( $this->id ) {
					case 'virt_pagseguro_credit':
						$_POST['payment_mode'] = 'credit';
						break;
					case 'virt_pagseguro_pix':
						$_POST['payment_mode'] = 'pix';
						break;
					case 'virt_pagseguro_ticket':
						$_POST['payment_mode'] = 'ticket';
						break;
				}
			}

			$paid = $this->api->new_charge( $order, $_POST );

			if ( ! isset( $paid['error'] ) ) {
				if ( $paid ) {
					$this->add_installment_fee( $order );
					$payment_status = isset( $this->global_settings['payment_status'] )
						? $this->global_settings['payment_status']
						: 'processing';

					if ( isset( $this->global_settings['process_mode'] )
						&& 'async' === $this->global_settings['process_mode'] ) {
						$args = array( $order_id, $payment_status );
						if ( ! wp_next_scheduled( 'pagseguro_process_update_order_status', $args ) ) {
							wp_schedule_single_event(
								strtotime( 'now' ) + 60,
								'pagseguro_process_update_order_status',
								$args
							);
						}
					} else {
						$order->update_status(
							$payment_status,
							__( 'PagSeguro: Pagamento aprovado.', 'virtuaria-pagseguro' )
						);
					}
				} else {
					if ( method_exists( $this, 'check_payment_pix' ) ) {
						$this->check_payment_pix( $order );
					}

					if ( method_exists( $this, 'register_pdf_link_note' ) ) {
						$this->register_pdf_link_note( $order );
					}

					if ( isset( $this->global_settings['process_mode'] )
						&& 'async' === $this->global_settings['process_mode'] ) {
						$args = array( $order_id, 'on-hold' );
						if ( ! wp_next_scheduled( 'pagseguro_process_update_order_status', $args ) ) {
							wp_schedule_single_event(
								strtotime( 'now' ) + 60,
								'pagseguro_process_update_order_status',
								$args
							);
						}
					} else {
						$order->update_status(
							'on-hold',
							__( 'PagSeguro: Aguardando confirmação de pagamento.', 'virtuaria-pagseguro' )
						);
					}
				}

				$payment_method = $order->get_meta( '_payment_mode', true );
				if ( $payment_method ) {
					if ( 'PIX' === $payment_method ) {
						$order->set_payment_method_title(
							'PagSeguro Pix'
						);
					} elseif ( 'CREDIT_CARD' === $payment_method ) {
						$order->set_payment_method_title(
							'PagSeguro Crédito'
						);
					} elseif ( 'BOLETO' === $payment_method ) {
						$order->set_payment_method_title(
							'PagSeguro Boleto'
						);
					}
					$order->save();
				}

				wc_reduce_stock_levels( $order_id );
				// Remove cart.
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				if ( isset( $_POST['is_block'] )
					&& 'yes' === $_POST['is_block'] ) {
					throw new Exception(
						sprintf(
							/* translators: %s: error */
							__( 'PagSeguro: %s', 'virtuaria-pagseguro' ),
							$paid['error']
						),
						401
					);
				} else {
					wc_add_notice(
						sprintf(
							/* translators: %s: error */
							__( 'PagSeguro: %s', 'virtuaria-pagseguro' ),
							$paid['error']
						),
						'error'
					);
				}

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} else {
			if ( isset( $_POST['is_block'] )
				&& 'yes' === $_POST['is_block'] ) {
				throw new Exception(
					__(
						'Não foi possível processar a sua compra. Por favor, tente novamente mais tarde.',
						'virtuaria-pagseguro'
					),
					401
				);
			} else {
				wc_add_notice(
					__(
						'Não foi possível processar a sua compra. Por favor, tente novamente mais tarde.',
						'virtuaria-pagseguro'
					),
					'error'
				);
			}

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Process refund order.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		$refundable_status = array(
			$this->global_settings['payment_status'],
			'processing',
			'completed',
		);

		if ( $amount
			&& $amount >= 1
			&& apply_filters( 'virtuaria_pagseguro_allow_refund', true, $order, $amount )
			&& in_array( $order->get_status(), $refundable_status, true )
			&& 'BOLETO' !== $order->get_meta( '_payment_mode' ) ) {
			if ( $this->api->refund_order( $order_id, $amount ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: amount */
						__( 'PagSeguro: Reembolso de R$%s bem sucedido.', 'virtuaria-pagseguro' ),
						$amount
					),
					0,
					true
				);
				return true;
			}
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: amount */
				__(
					'PagSeguro: Não foi possível reembolsar R$%s. Verifique o status da transação e o valor a ser reembolsado e tente novamente.',
					'virtuaria-pagseguro'
				),
				$amount
			),
			0,
			true
		);

		return false;
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post(
				wpautop(
					wptexturize(
						$description
					)
				)
			);
		}

		$comments = isset( $this->comments ) ? $this->comments : false;
		if ( $comments ) {
			echo '<span class="pagseguro-info">' . wp_kses_post( $comments ) . '</span>';
		}

		$cart_total = $this->get_order_total();

		$combo_installments = array();
		if ( method_exists( $this, 'get_installment_value' ) ) {
			foreach ( range( 1, $this->installments ) as $installment ) {
				if ( $this->fee_from > $installment ) {
					$combo_installments[] = $cart_total;
					continue;
				}

				$combo_installments[] = $this->get_installment_value(
					$cart_total,
					$installment
				);
			}
		}

		$disable_discount = ( property_exists( $this, 'pix_discount_coupon' )
			&& $this->pix_discount_coupon
			&& WC()->cart
			&& count( WC()->cart->get_applied_coupons() ) > 0 )
			|| apply_filters( 'virtuaria_pagseguro_disable_discount_by_cart', false, WC()->cart );

		$card_loaded = false;
		if ( is_user_logged_in()
			&& isset( $this->save_card_info )
			&& 'do_not_store' !== $this->save_card_info ) {
			$pagseguro_card_info = get_user_meta(
				get_current_user_id(),
				'_pagseguro_credit_info_store_' . get_current_blog_id(),
				true
			);
			if ( isset( $pagseguro_card_info['token'] ) ) {
				$card_loaded = true;
			}
		}

		$checkou_args = array(
			'cart_total'        => $cart_total,
			'flag'              => plugins_url(
				'assets/images/brazilian-flag.png',
				VIRTUARIA_PAGSEGURO_URL
			),
			'installments'      => $combo_installments,
			'has_tax'           => isset( $this->tax ) && floatval( $this->tax ) > 0,
			'min_installment'   => isset( $this->min_installment ) ? floatval( $this->min_installment ) : false,
			'fee_from'          => isset( $this->fee_from ) ? $this->fee_from : false,
			'pix_validate'      => method_exists( $this, 'format_pix_validate' )
				? $this->format_pix_validate(
					$this->pix_validate
				)
				: '',
			'methods_enabled'   => array(
				'pix'    => isset( $this->pix_enable ) && 'yes' === $this->pix_enable,
				'ticket' => isset( $this->ticket_enable ) && 'yes' === $this->ticket_enable,
				'credit' => isset( $this->credit_enable ) && 'yes' === $this->credit_enable,
			),
			'full_width'        => 'one' === $this->get_option( 'display' ),
			'pix_discount'      => isset( $this->pix_discount )
				&& $this->pix_discount
				&& ! $disable_discount
					? $this->pix_discount / 100
					: 0,
			'pix_offer_text'    => method_exists( $this, 'discount_text' )
				? $this->discount_text(
					'PIX',
					$this->id
				)
				: '',
			'ticket_offer_text' => method_exists( $this, 'discount_text' )
				? $this->discount_text(
					'Boleto',
					$this->id
				)
			: '',
			'card_loaded'       => $card_loaded,
			'instance'          => $this,
			'save_card_info'    => isset( $this->save_card_info ) ? $this->save_card_info : false,
		);

		if ( isset( $this->save_card_info, $pagseguro_card_info ) && $pagseguro_card_info ) {
			$checkou_args['pagseguro_card_info'] = $pagseguro_card_info;
		}

		if ( isset( $this->global_settings['payment_form'] )
			&& 'separated' === $this->global_settings['payment_form'] ) {
			wc_get_template(
				'separated-transparent-checkout.php',
				$checkou_args,
				'woocommerce/pagseguro/',
				Virtuaria_Pagseguro::get_templates_path()
			);
		} elseif ( isset( $this->global_settings['layout_checkout'] )
			&& 'tabs' !== $this->global_settings['layout_checkout'] ) {
			wc_get_template(
				'lines-transparent-checkout.php',
				$checkou_args,
				'woocommerce/pagseguro/',
				Virtuaria_Pagseguro::get_templates_path()
			);
		} else {
			wc_get_template(
				'transparent-checkout.php',
				$checkou_args,
				'woocommerce/pagseguro/',
				Virtuaria_Pagseguro::get_templates_path()
			);
		}
	}

	/**
	 * Get invoic prefix.
	 *
	 * @return string
	 */
	private function get_invoice_prefix() {
		$prefix = 'WC-';
		if ( isset( $this->global_settings['invoice_prefix'] ) ) {
			$prefix = $this->global_settings['invoice_prefix'];
		}

		return $prefix;
	}

	/**
	 * Get log if is enabled.
	 *
	 * @return null|WC_Logger
	 */
	private function get_log() {
		$log = null;
		if ( isset( $this->global_settings['debug'] )
			&& 'yes' === $this->global_settings['debug'] ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$log = wc_get_logger();
			} else {
				$log = new WC_Logger();
			}
		}
		return $log;
	}

	/**
	 * Get token.
	 */
	private function get_token() {
		$token = null;
		if ( isset( $this->global_settings['environment'] ) ) {
			if ( 'sandbox' === $this->global_settings['environment'] ) {
				$token = isset( $this->global_settings['token_sanbox'] )
					? $this->global_settings['token_sanbox']
					: '';
			} else {
				$token = isset( $this->global_settings['token_production'] )
					? $this->global_settings['token_production']
					: '';
			}
		}
		return $token;
	}
	/**
	 * Get form class.
	 *
	 * @param boolean $card_loaded true if card is loaded.
	 * @param boolean $full_width  true if one column.
	 * @param string  $default default class.
	 */
	public function pagseguro_form_class( $card_loaded, $full_width, $default ) {
		$class = '';
		if ( $card_loaded ) {
			$class .= ' card-loaded';
		}
		if ( $full_width ) {
			$class .= ' form-row-wide';
		} else {
			$class .= ' ' . $default;
		}

		return $class;
	}

	/**
	 * Display ignore discount field.
	 *
	 * @param string $key  the name from field.
	 * @param array  $data the data.
	 */
	public function generate_ignore_discount_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$this->save_categories_ignored_in_discount( $key );

		$selected_cats = $this->get_option( $key );

		$selected_cats = is_array( $selected_cats )
			? $selected_cats
			: explode( ',', $selected_cats );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>">
					<?php echo esc_html( $data['title'] ); ?>
					<span class="woocommerce-help-tip" data-tip="<?php echo esc_html( $data['description'] ); ?>"></span>
				</label>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( $data['type'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" />
				<div id="product_cat-all" class="tabs-panel">
					<ul id="product_catchecklist" data-wp-lists="list:product_cat" class="categorychecklist form-no-clear">
						<?php
						wp_terms_checklist(
							0,
							array(
								'taxonomy'      => 'product_cat',
								'selected_cats' => $selected_cats,
							)
						);
						?>
					</ul>
				</div>
			</td>
		</tr>
		<script>
			jQuery(document).ready(function($){
				$('.woocommerce-save-button').on('click', function() {
					let selected_cats = [];
					$('#<?php echo esc_attr( $field_key ); ?> + #product_cat-all #product_catchecklist input[type="checkbox"]:checked').each(function(i, v){
						selected_cats.push($(v).val());
					});
					$('#<?php echo esc_attr( $field_key ); ?>').val(selected_cats);
				})
			});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save categories ignored in discount.
	 *
	 * @param string $key option key.
	 */
	private function save_categories_ignored_in_discount( $key ) {
		if ( isset( $_POST[ 'woocommerce_virt_pagseguro_' . $key ] ) ) {
			$ignored = sanitize_text_field( wp_unslash( $_POST[ 'woocommerce_virt_pagseguro_' . $key ] ) );
			$ignored = explode( ',', $ignored );
			$this->update_option(
				$key,
				$ignored
			);
		}
	}

	/**
	 * Ignore product from categorie to discount.
	 *
	 * @param boolean    $disable true if disable item otherwise false.
	 * @param wc_product $product the itens.
	 * @param string     $method  the method.
	 */
	public function disable_discount_by_product_categoria( $disable, $product, $method ) {
		$to_categories = $this->get_option( $method . '_discount_ignore', '' );

		$ignored_categories = is_array( $to_categories )
			? $to_categories
			: explode(
				',',
				$to_categories
			);

		if ( $ignored_categories
			&& is_array( $ignored_categories )
			&& count( $product->get_category_ids() ) > 0 ) {
			foreach ( $product->get_category_ids() as $category_id ) {
				if ( in_array( $category_id, $ignored_categories ) ) {
					$disable = true;
					break;
				}
			}
		}
		return $disable;
	}

	/**
	 * Display discount pix text.
	 *
	 * @param string $title      the gateway title.
	 * @param string $gateway_id the gateway id.
	 */
	public function discount_text( $title, $gateway_id ) {
		if ( is_checkout()
			&& isset( $_REQUEST['wc-ajax'] )
			&& 'update_order_review' === $_REQUEST['wc-ajax']
			&& ! apply_filters( 'virtuaria_pagseguro_disable_discount_by_cart', false, WC()->cart )
			&& $this->id === $gateway_id ) {

			if ( 'virt_pagseguro_pix' === $gateway_id ) {
				$has_discount = 'yes' === $this->pix_enable
				&& $this->pix_discount > 0
				&& ( ! $this->pix_discount_coupon || count( WC()->cart->get_applied_coupons() ) === 0 );
			} elseif ( 'virt_pagseguro_ticket' === $gateway_id ) {
				$has_discount = 'yes' === $this->ticket_enable
				&& $this->ticket_discount > 0
				&& ( ! $this->ticket_discount_coupon || count( WC()->cart->get_applied_coupons() ) === 0 );
			} else {
				$has_discount = false;
			}

			if ( ! $has_discount ) {
				return $title;
			}

			$discount = 'virt_pagseguro_pix' === $gateway_id
				? $this->pix_discount
				: $this->ticket_discount;

			$title .= '<span class="pix-discount">(desconto de <span class="percentage">'
				. str_replace( '.', ',', $discount ) . '%</span>)';

			if ( isset( $this->global_settings['payment_form'] )
				&& 'unified' === $this->global_settings['payment_form']
				&& isset( $this->global_settings['layout_checkout'] )
				&& 'tabs' === $this->global_settings['layout_checkout'] ) {
				$title .= 'virt_pagseguro_pix' === $gateway_id
					? ' no Pix'
					: ' no Boleto';
			}
			$title .= '</span>';
		}
		return $title;
	}

	/**
	 * Text about categories disable to pix discount.
	 *
	 * @param array $itens the cart itens.
	 */
	public function info_about_categories( $itens ) {
		$method = 'after_virtuaria_pix_validate_text' === current_action()
			? 'pix'
			: 'ticket';

		$ignored_categories = $this->get_option( $method . '_discount_ignore', '' );

		if ( is_array( $ignored_categories ) ) {
			$ignored_categories = array_filter( $ignored_categories );
		}

		if ( 'pix' === $method ) {
			$enabled = 'yes' === $this->pix_enable
				&& $this->pix_discount > 0;
		} elseif ( 'ticket' === $method ) {
			$enabled = 'yes' === $this->ticket_enable
				&& $this->ticket_discount > 0;
		} else {
			$enabled = false;
		}

		if ( $enabled
			&& is_array( $ignored_categories )
			&& $ignored_categories ) {

			$category_disabled = array();
			foreach ( $ignored_categories as $index => $category ) {
				$term = get_term( $category );
				if ( $term && ! is_wp_error( $term ) ) {
					$category_disabled[] = ucwords( mb_strtolower( $term->name ) );
				}
			}

			if ( $category_disabled ) {
				echo '<div class="info-category">' . wp_kses_post(
					sprintf(
						/* translators: %s: categories */
						_nx(
							'O desconto do %1$s não é válido para produtos da categoria <span class="categories">%2$s</span>.',
							'O desconto do %1$s não é válido para produtos das categorias <span class="categories">%2$s</span>.',
							count( $category_disabled ),
							'Checkout',
							'virtuaria-pagseguro'
						),
						'pix' === $method ? 'Pix' : 'Boleto',
						implode( ', ', $category_disabled )
					)
				) . '</div>';
			}
		}
	}

	public function display_total_discounted() {
		if ( 'after_virtuaria_pix_validate_text' === current_action() ) {
			$disabled_with_coupon = $this->pix_discount_coupon;
			$discount_percentual  = $this->pix_discount
				? $this->pix_discount / 100
				: 0;
			$method                = 'pix';
		} elseif ( 'after_virtuaria_ticket_text' === current_action() ) {
			$disabled_with_coupon = $this->ticket_discount_coupon;
			$discount_percentual  = $this->ticket_discount
				? $this->ticket_discount / 100
				: 0;
			$method               = 'ticket';
		} else {
			$disabled_with_coupon = false;
			$discount_percentual  = 0;
		}

		if ( ( $disabled_with_coupon
			&& WC()->cart
			&& count( WC()->cart->get_applied_coupons() ) > 0 )
			|| apply_filters( 'virtuaria_pagseguro_disable_discount_by_cart', false, WC()->cart ) ) {
			return;
		}

		if ( $discount_percentual > 0 ) {
			$shipping = 0;
			if ( isset( WC()->cart ) && WC()->cart->get_shipping_total() > 0 ) {
				$shipping = WC()->cart->get_shipping_total();
			}

			$cart_total      = $this->get_order_total();
			$discount_reduce = 0;
			$discount        = ( $cart_total - $shipping );
			foreach ( WC()->cart->get_cart() as $item ) {
				$product = wc_get_product( $item['product_id'] );
				if ( $product && apply_filters(
					'virtuaria_pagseguro_disable_discount',
					false,
					$product,
					$method
				) ) {
					$discount_reduce += $product->get_price() * $item['quantity'];
				}
			}
			$discount -= $discount_reduce;
			$discount  = $discount * $discount_percentual;
			if ( $discount > 0 ) {
				echo '<span class="discount">Desconto: <b style="color:green;">R$ '
				. esc_html( number_format( $discount, 2, ',', '.' ) )
				. '</b></span>';
				echo '<span class="total">Novo total: <b style="color:green">R$ '
				. esc_html( number_format( $cart_total - $discount, 2, ',', '.' ) )
				. '</b></span>';
			}
		}
	}
}
