<?php
/**
 * Handle Virtuaria PagSeguro events.
 *
 * @package virtuaria/payments/pagSeguro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Definition.
 */
class Virtuaria_PagSeguro_Events {
	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialization.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_virt_pagseguro_settings' );

		add_action( 'wp_ajax_fetch_payment_order', array( $this, 'fetch_payment_order' ) );
		add_action( 'wp_ajax_nopriv_fetch_payment_order', array( $this, 'fetch_payment_order' ) );
		add_action( 'pagseguro_pix_check_payment', array( $this, 'check_order_paid' ) );
		add_action(
			'pagseguro_process_update_order_status',
			array( $this, 'process_order_status' ),
			10,
			2
		);

		add_action(
			'wp_ajax_virt_pagseguro_3ds_order_total',
			array( $this, 'get_current_order_total' )
		);
		add_action(
			'wp_ajax_nopriv_virt_pagseguro_3ds_order_total',
			array( $this, 'get_current_order_total' )
		);
	}

	/**
	 * Check order status.
	 */
	public function fetch_payment_order() {
		if ( isset( $_POST['order_id'] )
			&& isset( $_POST['payment_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['payment_nonce'] ) ), 'fecth_order_status' ) ) {
			$payment_status = $this->settings['payment_status'];
			$order          = wc_get_order(
				sanitize_text_field(
					wp_unslash(
						$_POST['order_id']
					)
				)
			);

			if ( $order && $payment_status === $order->get_status() ) {
				echo 'success';
			}
		}
		wp_die();
	}

	/**
	 * Process schedule order status.
	 *
	 * @param int    $order_id the order id.
	 * @param string $status the status scheduled.
	 */
	public function process_order_status( $order_id, $status ) {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			if ( 'on-hold' === $status ) {
				if ( $order->has_status( 'pending' ) ) {
					$order->update_status(
						'on-hold',
						__( 'PagSeguro: Aguardando confirmação de pagamento.', 'virtuaria-pagseguro' )
					);
				}
			} elseif ( isset( $this->settings['payment_status'] ) ) {
				$order->update_status(
					$this->settings['payment_status'],
					__( 'PagSeguro: Pagamento aprovado.', 'virtuaria-pagseguro' )
				);
			} else {
				$order->update_status(
					'processing',
					__( 'PagSeguro: Pagamento aprovado.', 'virtuaria-pagseguro' )
				);
			}
		}
	}

	/**
	 * Check status from order. If unpaid cancel order.
	 *
	 * @param int $order_id the args.
	 */
	public function check_order_paid( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order && ! $order->get_meta( '_charge_id' ) ) {
			$order->add_order_note(
				'Pagseguro Pix: o limite de tempo para pagamento deste pedido expirou.'
			);
			$order->update_status( 'cancelled' );
			if ( 'yes' === $this->settings['debug'] ) {
				wc_get_logger()->add(
					'virtuaria-pagseguro',
					'Pedido #' . $order->get_order_number() . ' mudou para o status cancelado.',
					WC_Log_Levels::INFO
				);
			}
		}
	}

	/**
	 * Get the current order total.
	 */
	public function get_current_order_total() {
		if ( isset( $_POST['nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['nonce']
					)
				),
				'get_3ds_order_total'
			)
			&& isset( WC()->cart )
		) {
			echo esc_html( WC()->cart->total * 100 );
		}
		wp_die();
	}
}

new Virtuaria_PagSeguro_Events();
