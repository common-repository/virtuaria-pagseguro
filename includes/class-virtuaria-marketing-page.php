<?php
/**
 * Handle marketing page.
 *
 * @package Virtuaria/Payments/PagSeguro.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class definition.
 */
class Virtuaria_Marketing_Page {
	/**
	 * Initialize functions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu_marketing' ) );
		add_action( 'admin_footer', array( $this, 'hide_submenu_makerting' ) );
	}

	/**
	 * Adds a submenu page for the Virtuaria Marketing plugin.
	 */
	public function add_submenu_marketing() {
		add_submenu_page(
			'virtuaria_pagseguro',
			'Virtuaria Marketing',
			'Virtuaria Marketing',
			apply_filters(
				'virtuaria_pagseguro_menu_capability',
				'remove_users'
			),
			'virtuaria_marketing',
			array( $this, 'content_marketing_tab' ),
		);
	}

	/**
	 * Display the content of the marketing tab.
	 */
	public function content_marketing_tab() {
		?>
		<h1 class="main-title">Virtuaria PagSeguro</h1>
		<form action="" method="post" id="mainform" class="main-setting">
			<table class="form-table">
				<tbody>
					<tr class="marketing" valign="top">
						<td>
							<h2 class="title">
								Virtuaria Correios - Frete, Etiqueta e Rastreio
							</h2>
							<img src="<?php echo esc_url( VIRTUARIA_PAGSEGURO_URL ) . '/admin/images/entregador-correios.webp' ?>" alt="Correios Entrega">
							<p class="description">
								Os Correios são a principal solução de entrega no Brasil, garantindo que seus produtos cheguem aos clientes de forma rápida e segura.
								<h3 class="resources-title">
									Principais Recursos
								</h3>
								<ul class="correios-resources">
									<li><b>Geração de etiqueta</b> -  Simplifique a logística de envio com a funcionalidade de criação de etiquetas diretamente do painel da sua loja online. Este processo, é também conhecido como pré-postagem ou impressão de rótulo, na nomenclatura dos Correios;</li>
									<li><b>Cálculo automático de frete</b> - exibe no carrinho e checkout, valor e previsão de entrega do frete para seus clientes;</li>
									<li><b>Cálculo na página do produto</b> - exibe calculadora de frete na página do produto;</li>
									<li><b>Rastreamento</b> - permite a visualização dos status da entrega pelo gestor e pelo cliente, nas respectivas telas de detalhes do pedido de cada um.</li>
									<li><b>Autopreenchimento</b> - com base no CEP informado no checkout, preenche as informações sobre o endereço do cliente.</li>
									<li><b>Suporte a serviços adicionais dos Correios</b> - Opcionalmente, permite o uso dos serviços: Declaração de Valor, Mãos Próprias e Aviso de Recebimento;</li>
									<li><b>Suporte a todas as modalidades de entregas do Contrato</b> - permite o uso dos serviços contratados em contrato com os Correios;</li>
									<li><b>Compatível com Wordpress Multisite</b> - permite configuração unificada para todos os subsites usando os mesmos dados de contrato.</li>
								</ul>

								<h3 class="resources-title">
									Potencializando sua Experiência de Entrega com Correios
								</h3>
								Ao expandir além dos recursos já robustos oferecidos na versão gratuita, nossa solução premium para integração com o serviço de entrega dos Correios adiciona uma camada de flexibilidade e personalização, capacitando ainda mais os comerciantes online a moldarem suas estratégias de envio de acordo com suas necessidades específicas e o perfil de seus produtos.<br><br>

								<li><b>Preço por Categoria</b> - Tenha o controle total sobre os custos de envio, ajustando os preços de frete com base nas categorias de produtos selecionadas. Seja aumentando, diminuindo ou fixando os preços, essa funcionalidade permite uma abordagem granular e estratégica para gerenciar os custos de envio de acordo com a natureza dos produtos.</li>
								<li><b>Barra de Progresso para Frete Grátis</b> - Transforme a experiência de compra dos seus clientes, proporcionando uma visualização clara e motivadora do progresso em direção ao frete grátis. Com uma barra de progresso visível no checkout e carrinho, os clientes são incentivados a adicionar mais itens ao carrinho para atingir o valor necessário para a gratuidade do frete, aumentando assim o valor médio do pedido.</li>
								<li><b>Shortcode [progress_free_shipping]</b> - Flexibilidade é a chave, e com este shortcode, você pode exibir a barra de progresso para frete grátis em qualquer lugar do seu site. Seja na página inicial, em páginas de produtos específicos ou até mesmo em campanhas promocionais, essa ferramenta permite uma integração fluida e adaptável ao layout do seu site.</li>
								<li><b>Esconder Métodos de Entrega</b> - Simplifique o processo de escolha do cliente ao oferecer frete grátis. Quando o método de envio gratuito está disponível, essa função oculta automaticamente todos os outros métodos de entrega, garantindo uma experiência de compra mais direta e intuitiva.</li>
								<li><b>Frete Grátis</b> - O frete grátis do plugin permite que os métodos de envio dos Correios tenham um custo zero quando o valor mínimo para obtenção do frete grátis, configurado pelo usuário, é alcançado.</li>
							</p>
							<br><br>
							<a class="button button-primary" target="_blank" href="https://virtuaria.com.br/correios-woocommerce-plugin/" target="_blank">
								Obtenha o Virtuaria Correios
							</a>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<style>
			.correios-resources {
				list-style: disc;
				margin-left: 30px;
			}
			.navigation-tab .marketing:after {
				content: "(novo)";
				font-size: 9px;
				background-position-y: 13px;
				display: inline-block;
				vertical-align: top;
				margin-left: 3px;
				color: #27cf54;
			}
			.marketing img {
				max-width: 500px;
				float: right;
			}
		</style>
		<?php
	}

	/**
	 * Hide submenu.
	 */
	public function hide_submenu_makerting() {
		?>
		<style>
			#adminmenu .wp-submenu a[href="admin.php?page=virtuaria_marketing"] {
				display: none;
			}
		</style>
		<?php
	}
}

new Virtuaria_Marketing_Page();
