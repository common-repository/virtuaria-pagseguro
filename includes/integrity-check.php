<?php
/**
 * Check plugin integrity.
 *
 * @package Virtuaria/Integrations/Pagseguro.
 */

defined( 'ABSPATH' ) || exit;

$allowed_plugin_name = array(
	'Virtuaria - Pagseguro para Woocommerce',
	'Virtuaria - Pagseguro Crédito, Pix e Boleto',
	'Virtuaria PagBank / PagSeguro para Woocommerce',
);

if ( ! is_plugin_active( 'virtuaria-pagseguro/virtuaria-pagseguro.php' )
|| ! in_array( $plugin_data['Name'], $allowed_plugin_name, true )
|| '<a href="https://virtuaria.com.br/">Virtuaria</a>' !== $plugin_data['Author'] ) {
	wp_die( 'Erro: Plugin corrompido. Favor baixar novamente o código e reinstalar o plugin.' );
}
