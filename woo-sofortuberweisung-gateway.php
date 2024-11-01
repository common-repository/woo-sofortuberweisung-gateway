<?php
/*
 * Plugin Name: Sofortueberweisung / Klarna Gateway for Woocommerce 
 * Version: 1.3.4
 * Plugin URI: http://www.mlfactory.at/sofortueberweisung-gateway-for-woocommerce
 * Description: Sofortueberweisung / Klarna Gateway for WooCommerce, easy, fast and safe
 * Author: Michael Leithold
 * Author URI: http://www.mlfactory.de
 * Requires at least: 4.0
 * Tested up to: 5.5.1
 * License: GPLv2 or later
 * Text Domain: woo-sofortuberweisung-gateway
 *
*/

defined( 'ABSPATH' ) or exit;

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

require_once(dirname(__FILE__).'/sofort/payment/sofortLibSofortueberweisung.inc.php');
require_once(dirname(__FILE__).'/sofort/core/sofortLibNotification.inc.php');
require_once(dirname(__FILE__).'/sofort/core/sofortLibTransactionData.inc.php');

add_filter( 'woocommerce_payment_gateways', 'sofortueberweisung_gateway_add_to_gateways' );
function sofortueberweisung_gateway_add_to_gateways( $gateways ) {
	$gateways[] = 'MlFactory_WC_Gateway_Sofort';
	return $gateways;
}

add_action( 'plugins_loaded', 'sofortueberweisung_gateway_textdomain' );
function sofortueberweisung_gateway_textdomain(){
	$loadfiles = load_plugin_textdomain('woo-sofortuberweisung-gateway', false, 
	dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	plugin_basename(( __FILE__ ). '/languages/' );
} 

$gzd_stop_order_cancellation = get_option('woocommerce_gzd_checkout_stop_order_cancellation', 'no');
if ($gzd_stop_order_cancellation == 'yes') {
	add_action( 'admin_notices', 'sofortueberweisung_gateway_gzd_notice' );
	function sofortueberweisung_gateway_gzd_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><b style="color:red"><?php echo __( 'VERY IMPORTANT', 'woo-sofortuberweisung-gateway' ); ?>:</b></p>
			<p>
			<?php echo __( 'It was detected that you are using the plugin Germanized for WooCommerce.', 'woo-sofortuberweisung-gateway' ); ?><br />
			<?php echo __( 'You have activated the function "Disallow cancellations".', 'woo-sofortuberweisung-gateway' ); ?><br />
			<u><?php echo __( 'This function must be deactivated!', 'woo-sofortuberweisung-gateway' ); ?></u><br />
			<?php echo __( 'Follow the link and deactivate the function "Disallow cancellations": <a href="admin.php?page=wc-settings&tab=germanized-general&section=checkout">Germanized for WooCommerce Setttings</a>', 'woo-sofortuberweisung-gateway' ); ?><br />
			</p>
			<p><?php echo __( 'This message disappears automatically as soon as you have made the necessary settings.', 'woo-sofortuberweisung-gateway' ); ?></p>
		</div>
		<?php
	}	
}

add_action( 'plugins_loaded', 'sofortueberweisung_gateway_init', 11 );
function sofortueberweisung_gateway_init() {
 
	class MlFactory_WC_Gateway_Sofort extends WC_Payment_Gateway {

		public function __construct() {
		 
					$this->id                 = 'sofortueberweisung_gateway';
					$this->icon               = apply_filters('woocommerce_offline_icon', '');
					$this->has_fields         = false;
					$this->method_title       = __( 'Klarna / Sofortüberweisung', 'woo-sofortuberweisung-gateway' );
					$this->method_description .= __( 'Allows Klarna / Sofortüberweisung payments in Woocommerce. Easy, fast and safe. Orders are marked as "processing" when received.', 'woo-sofortuberweisung-gateway' );
					$this->method_description .= '<div class="notice notice-info" data-nonce="cf716d3210">';
					$this->method_description .= '<p>'.__('Thanks for using <strong>Sofortüberweisung Gateway</strong>. <br /><br />Please support our free work by rating this plugin with 5 stars on WordPress.org. <a href="https://wordpress.org/support/plugin/sofortueberweisung-gateway-woocommerce/reviews/#new-post" target="_blank">Click here to rate us.</a><br><br><br>', 'woo-sofortuberweisung-gateway');
					$this->method_description .= '<b>'.__('How to get a API Key:', 'woo-sofortuberweisung-gateway').'</b> '.__('<a href="https://www.youtube.com/watch?v=NgjtALKCLeg" target="blank">YouTube Video Tutorial</a></p>', 'woo-sofortuberweisung-gateway');
					$this->method_description .= '<b>'.__('Information:', 'woo-sofortuberweisung-gateway').'</b> '.__('This plugin work fine in the free version.<br/> In the PRO version of this plugin there are many more Features. <a href="https://wordpress.org/plugins/sofortueberweisung-gateway-woocommerce/"> Check out Features of Pro Version</a></p>', 'woo-sofortuberweisung-gateway');
					$this->method_description .= __('Buy the Pro Version for only €14,99,- incl. Tax.', 'woo-sofortuberweisung-gateway').'<br />';
					$this->method_description .= '<a href="mailto:michaelleithold18@gmail.com">'.__('Send me a email michaelleithold18@gmail.com', 'woo-sofortuberweisung-gateway').'</a>';
					$this->method_description .= '</div>';
				  
					// Load the settings.
					$this->init_form_fields();
					$this->init_settings();
				  
					// Define user set variables
					$this->title        = $this->get_option( 'title' );
					$this->sendEmail        = $this->get_option( 'sendEmail', 'no');
					$this->successEmailHeader  = $this->get_option( 'successEmailHeader', 'Payment received');
					$_SESSION["klarna_successEmailHeader"] = $this->successEmailHeader;
					$_SESSION["klarna_sendEmail"] = $this->sendEmail;
					$this->configkey  = $this->get_option( 'configkey' );
					$this->displayService  = $this->get_option( 'displayService' );
					$this->displayOrderStatus  = $this->get_option( 'displayOrderStatus' );
					$this->displayTransID  = $this->get_option( 'displayTransID' );
					$this->displayTransTime  = $this->get_option( 'displayTransTime' );
					$this->displayTransAmount  = $this->get_option( 'displayTransAmount' );
					$this->displayTransInfo  = $this->get_option( 'displayTransInfo' );
					$displayTransInfo1  = $this->get_option( 'displayTransInfo' );
					$cnfgkey  = $this->get_option( 'configkey' );
					$_SESSION["configkeys9384720"] = $this->get_option( 'configkey' );
					$this->status_after_payment  = $this->get_option( 'status_after_payment', 'yes');
					if ($this->get_option( 'displayPolicy' ) == "yes") {
					$gdrp_policy = "You can read the privacy policy of Klarna <a href='https://www.klarna.com/sofort/privacy-policy/' target='_blank' title='Klarna Privacy Policy'>here</a>.";
					$this->description  = $this->get_option( 'description' ).'<br />'.$gdrp_policy;
					} else {
					$this->description  = $this->get_option( 'description' );
					}
					$this->instructions = $this->get_option( 'instructions' );
					$this->successEmailText = $this->get_option( 'successEmailText' );
					$this->pluginsUrl = plugin_dir_url(dirname(__FILE__))."woo-sofortuberweisung-gateway/";
				  
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

					add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
					
					wp_enqueue_script( 'tinymce_js', includes_url( 'js/tinymce/' ) . 'wp-tinymce.php', array( 'jquery' ), false, true );
					
					if ($this->sendEmail == "yes") {
					add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
					}

					add_action( 'admin_print_footer_scripts', array( $this, 'admin_editor' ), 99 );	
					
					add_filter( 'woocommerce_gateway_icon', array( $this, 'sofort_gateway_icon' ), 10, 2 );
			
		 }

		public function sofort_gateway_icon( $icon_html, $id ) {
			
			if ($id == "sofortueberweisung_gateway") {
				$icon_html = '<img src="'.plugin_dir_url(__FILE__ ).'core/klarna.svg" class="" style="max-width:70px;"/>';
				$icon_html .= '<img src="'.plugin_dir_url(__FILE__ ).'core/sofort-logo.png" class="" style="max-width:70px;margin-left:10px;"/>';
			}
			
			return $icon_html;
			
		}		 
		 
		public function admin_editor() { ?>
			<script type="text/javascript">
				/* <![CDATA[ */
					jQuery(function($){
						jQuery( document ).ready( function( $ ) {
							tinymce.init( {
								mode : "exact",
								elements : 'woocommerce_sofortueberweisung_gateway_successEmailText',
								theme: "modern",
								skin: "lightgray",
								menubar : true,
								statusbar : true,
								toolbar: [
									"bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | undo redo"
								],
								plugins : "paste",
								paste_auto_cleanup_on_paste : true,
								paste_postprocess : function( pl, o ) {
									o.node.innerHTML = o.node.innerHTML.replace( /&nbsp;+/ig, " " );
								}
							} );
							tinymce.init( {
								mode : "exact",
								elements : 'woocommerce_sofortueberweisung_gateway_description',
								theme: "modern",
								skin: "lightgray",
								menubar : true,
								statusbar : true,
								toolbar: [
									"bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | undo redo"
								],
								plugins : "paste",
								paste_auto_cleanup_on_paste : true,
								paste_postprocess : function( pl, o ) {
									o.node.innerHTML = o.node.innerHTML.replace( /&nbsp;+/ig, " " );
								}
							} );	
							tinymce.init( {
								mode : "exact",
								elements : 'woocommerce_sofortueberweisung_gateway_instructions',
								theme: "modern",
								skin: "lightgray",
								menubar : true,
								statusbar : true,
								toolbar: [
									"bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | undo redo"
								],
								plugins : "paste",
								paste_auto_cleanup_on_paste : true,
								paste_postprocess : function( pl, o ) {
									o.node.innerHTML = o.node.innerHTML.replace( /&nbsp;+/ig, " " );
								}
							} );						
						} );
					});
				/* ]]> */
			</script>
		<?php }
 

		public function init_form_fields() {

	  
			$defaul_email_text = __("Thank you for your purchase! <br/><br/><u>Details of the payment:</u> <br/>
										Service: Klarna / Sofortüberweisung<br/>
										Transaction ID: %transactionID%<br/>
										<br />
										Your order will be processed immediately.<br/>
										You will receive separately after the shipment of the goods an e-mail with the tracking number.<br/>
										<br/>
										Your %sitename% Team			
								", "woo-sofortuberweisung-gateway");					
								
	  
			$this->form_fields = apply_filters( 'wc_sofortueberweisung_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable Klarna / Sofortüberweisung', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Klarna / Sofortüberweisung Payment', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
					
				),
				
				
				'configkey' => array(
					'title'       => __( 'Configuration key', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'text',
					'description' => __( 'Get the key from sofort.com. Create a project and click on the "Migrate to Gateway" arrow on the right side of you project on the page "Project overview". Now u see under "Sofort Gateway" a Project. Click on that. Scroll down and you see the "Configuration key for your shop system"', 'woo-sofortuberweisung-gateway' ),
					'default'     => __( 'xxxxxx:xxxxxx:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'woo-sofortuberweisung-gateway' ),
					'desc_tip'    => true,
				),
		
				
				'title' => array(
					'title'       => __( 'Title', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'woo-sofortuberweisung-gateway' ),
					'default'     => __( 'Klarna / Sofortüberweisung', 'woo-sofortuberweisung-gateway' ),
					'desc_tip'    => true,
				),
				
				'privacyPoliy' => array(
					'title'       => __( 'Privacy Policy', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'text',
					'description' => __( 'This ist the Privacy Policy Text that displayed on the Checkout.', 'woo-sofortuberweisung-gateway' ),
					'default'     => __( 'You can read the privacy policy of Klarna <a href=\'https://www.klarna.com/sofort/privacy-policy/\' target=\'_blank\' title=\'Klarna Privacy Policy\'>here</a>.', 'woo-sofortuberweisung-gateway' ),
					'desc_tip'    => true,
				),					
				
				'description' => array(
					'title'       => __( 'Description', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-sofortuberweisung-gateway' ),
					'default'     => __( 'Safe, easy and direct payment via you bank account.', 'woo-sofortuberweisung-gateway' ),
					'desc_tip'    => true
				),				
			
				'instructions' => array(
					'title'       => __( 'Custom message on order received page', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Custom Message if order received. Leave it empty if its should not displayed', 'woo-sofortuberweisung-gateway' ),
					'default'     => 'Order received. Thanks!',
					'desc_tip'    => true,
				),

				'successEmailHeader' => array(
					'title'       => __( 'Success Payment Email Subject', 'woo-sofortuberweisung-gateway' ),
					'type'        => 'text',
					'description' => __( 'Subject of Email.', 'woo-sofortuberweisung-gateway' ),
					'default'     => __( 'Payment received', 'woo-sofortuberweisung-gateway' ),
					'desc_tip'    => true,
				),				
				
				'successEmailText' => array(
					'title'   => __( 'Success Payment Email Text', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'textarea',
					'label'   => __( 'Show or hide "Transaction Time" on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => $defaul_email_text,
					'desc_tip'    => true,
					'description' => __( 'Message Body Payment Success Email. Variables: %sitename% - %transactionID%', 'woo-sofortuberweisung-gateway' )
				),
				
				'displayTransInfo' => array(
					'title'   => __( 'Transaction Info', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide "Transaction Info" <b>completely</b> on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				
				'displayService' => array(
					'title'   => __( 'Service', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide "Service" on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				
				'displayOrderStatus' => array(
					'title'   => __( 'Order status', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide "Order Status" on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				
				'displayTransID' => array(
					'title'   => __( 'Transaction ID', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide "Transaction ID" on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				
				'displayTransTime' => array(
					'title'   => __( 'Transaction Time', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide "Transaction Time" on Paymentinfo', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				
				'displayPolicy' => array(
					'title'   => __( 'Display Policy Text', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Show or hide the Policy text on Checkout', 'woo-sofortuberweisung-gateway' ),
					'default' => 'yes'
				),
				'sendEmail' => array(
					'title'   => __( 'Send Info Email', 'woo-sofortuberweisung-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Disable/Enable sending Email after Payment Success', 'woo-sofortuberweisung-gateway' ),
					'default' => 'no'
				)				
			) );
		}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
		if ( $this->description ) {
			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
		}		
 
		}
 
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
		 
 
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
 
 
	 	}
 
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				
				echo wpautop( wptexturize( $this->instructions ) );
			}
		} 
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
		

		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {

	
			$order = new WC_Order( $order_id );
			$shop_name = get_bloginfo( 'name' );	
			$order_key = $order->get_order_key();
			// Start Sofortüberweisung
			
			//get configkey from admin page
			$configkey = $this->get_option( 'configkey' );
			$status_after_payment = $this->get_option( 'status_after_payment' );
			$country = WC()->countries->countries[ $order->get_shipping_country() ];
			if ($country == "Austria" or $country == "Styria") {
				$countrycode = "AT";
			}
			if ($country == "Germany") {
				$countrycode = "DE";
			}	
			if ($country == "Belgium") {
				$countrycode = "BE";
			}	
			if ($country == "Italy") {
				$countrycode = "IT";
			}
			if ($country == "Netherlands") {
				$countrycode = "NL";
			}
			if ($country == "Poland") {
				$countrycode = "PL";
			}	
			if ($country == "Spain") {
				$countrycode = "ES";
			}	
			if ($country == "Switzerland") {
				$countrycode = "CH";
			}				
			
			if (!$countrycode) {
				$countrycode = "DE";
			}
			
			$Sofortueberweisung = new Sofortueberweisung($configkey);
			
			$Sofortueberweisung->setAmount($order->get_total());
			
			$Sofortueberweisung->setCurrencyCode("EUR");
			
			$Sofortueberweisung->setSenderCountryCode($countrycode);
			
			$Sofortueberweisung->setReason($shop_name, __('Order Nr. ', 'woo-sofortuberweisung-gateway').$order_id);
			
			$Sofortueberweisung->setSuccessUrl($this->get_return_url( $order ).'&transId=-TRANSACTION-', true);
			
			//$Sofortueberweisung->setAbortUrl($this->get_return_url( $order ).'&transId=-TRANSACTION-&act=failed');$order ->get_cancel_order_url_raw();
			
			$Sofortueberweisung->setAbortUrl($order ->get_cancel_order_url_raw());
			
			$Sofortueberweisung->setNotificationUrl(get_site_url());
			
			$Sofortueberweisung->setCustomerprotection(true);
		
			$Sofortueberweisung->sendRequest();

			if($Sofortueberweisung->isError()) {
				echo "<div style='background-color: #eae9e9;border: 1px solid red; padding: 10px;margin-right: 25px;color: #333;margin-left: 25px;'>";
				echo "<p><b style='color: red;'>".__("Klarna/Sofort API Error", "woo-sofortuberweisung-gateway")."!</b></p>";
				echo "<p>".__("An error has occurred while communicating with the API. Please check if the API Key you entered is correct and valid!", "woo-sofortuberweisung-gateway")."</p>";
				echo "<p>".__("If you are not the operator of the online offer, please contact the operator.", "woo-sofortuberweisung-gateway")."</p>";
				echo "<p>".__("Error Code", "woo-sofortuberweisung-gateway").": ".$Sofortueberweisung->getError()."</p>";
				echo __("</div>");
			} else {
				//buyer must be redirected to $paymentUrl else payment cannot be successfully completed!
				$paymentUrl = $Sofortueberweisung->getPaymentUrl();
				
				// Mark as on-hold (we're awaiting the payment)
				$order->update_status( 'pending', __( 'Awaiting Sofortüberweisung payment', 'woo-sofortuberweisung-gateway' ) );
				
				$_SESSION["sofort_transactionsid"] = $Sofortueberweisung->getTransactionId();
				
				return array('result' => 'success', 'redirect' => $paymentUrl);
			}		
		}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
 
		
 
	 	}
 	}
}


class sofortueberweisung_gateway_check_payment {
	
     function __construct() {
		
          add_action('woocommerce_thankyou', 'check_if_paid_sofort', 5, 1);
		  
			function check_if_paid_sofort( $order_id ) {
				
				if ( ! $order_id )
					return;

				// Getting an instance of the order object
				$order = wc_get_order( $order_id );
				
				if($order->is_paid())
					$paid = 'yes';
				else
					$paid = 'no';
				

				if (isset($_GET['transId'])) {
					$transID = $_GET['transId'];
				}

				//sofort response from api
				if (isset($transID)) {

					//get config key from session
					//config key = transaction id
					$configkey = $_SESSION["configkeys9384720"];
					$SofortLibTransactionData = new SofortLibTransactionData($configkey);
					$SofortLibTransactionData->addTransaction($transID);
					$SofortLibTransactionData->setApiVersion('2.0');

					//wait for api / test
					sleep(5);

					//send request to sofort to check transaction
					$SofortLibTransactionData->sendRequest();

					$output = array();
					$methods = array(
								'getAmount' => '',
								'getAmountRefunded' => '',
								'getCount' => '',
								'getPaymentMethod' => '',
								'getConsumerProtection' => '',
								'getStatus' => '',
								'getStatusReason' => '',
								'getStatusModifiedTime' => '',
								'getLanguageCode' => '',
								'getCurrency' => '',
								'getTransaction' => '',
								'getReason' => array(0,0),
								'getUserVariable' => 0,
								'getTime' => '',
								'getProjectId' => '',
								'getRecipientHolder' => '',
								'getRecipientAccountNumber' => '',
								'getRecipientBankCode' => '',
								'getRecipientCountryCode' => '',
								'getRecipientBankName' => '',
								'getRecipientBic' => '',
								'getRecipientIban' => '',
								'getSenderHolder' => '',
								'getSenderAccountNumber' => '',
								'getSenderBankCode' => '',
								'getSenderCountryCode' => '',
								'getSenderBankName' => '',
								'getSenderBic' => '',
								'getSenderIban' => '',
					);
					$status = "";
					$payment_ok = "Payment via Sofortüberweisung successfully! <br /> Transaction ID: $transID <br/> Transaction Time: ";

					$sofort_options = get_option( 'woocommerce_sofortueberweisung_gateway_settings' );
					
					if (isset($sofort_options['successEmailText'])) {
						$successEmailText = $sofort_options['successEmailText'];
						$successEmailText = str_replace("%transactionID%", $transID, $successEmailText);
						$successEmailText = str_replace("%sitename%", get_bloginfo( 'name' ), $successEmailText);
					} else {
						$successEmailText = "";
					}
					//########################
					// construct email
					//########################

					global $woocommerce;
					// Create a mailer
					$mailer = $woocommerce->mailer();
					$message = $mailer->wrap_message(sprintf( __( $_SESSION["klarna_successEmailHeader"].' %s' ), $order->get_order_number() ), $successEmailText );

					//get counter and check if first time visit
					$sofortTransCounter = get_post_meta( $order_id, 'sofortTransCounter', true );

						foreach($methods as $method => $params) {
							//get transTime time
							if ($method == "getTime") {
							$transTime = $SofortLibTransactionData->$method();	
							}
							if ($method == "getAmount") {
							$amount = $SofortLibTransactionData->$method();	
							}
							if ($method == "getCurrency") {
							$currency = $SofortLibTransactionData->$method();	
							}
							error_reporting(0);
							if($params && count($params) == 2) {
								if ($method == "getStatus" && $sofortTransCounter != 1) {
									if ($SofortLibTransactionData->$method($params[0], $params[1])) {
										$status .= "ok";
										$order->update_status('processing', 'order_note');
										$order->add_order_note($payment_ok.$transTime,0,true);
										// SEND MAIL
										add_post_meta( $order_id, 'sofortTransCounter', 1 );
										if (isset($_SESSION["klarna_sendEmail"]) && $_SESSION["klarna_sendEmail"] == "yes") {
										$mailer->send( $order->billing_email, sprintf( __( $_SESSION["klarna_successEmailHeader"].' %s' ), $order->get_order_number() ), $message );
										}
										$order->reduce_order_stock();
										WC()->cart->empty_cart();
									} else {
										$order->update_status('on-hold', 'order_note');
										$order->add_order_note("Payment via Sofortüberweisung <b>NOT</b> successfully!",0,true);
										$status .= "notok";
									}
								}
								$output[] = $method . ': ' . $SofortLibTransactionData->$method($params[0], $params[1]);
							} else if($params !== '') {
								if ($method == "getStatus" && $sofortTransCounter != 1) {
									if ($SofortLibTransactionData->$method($params)) {
										$status .= "ok";
										$order->update_status('processing', 'order_note');
										$order->add_order_note($payment_ok.$transTime,0,true);
										// SEND MAIL
										add_post_meta( $order_id, 'sofortTransCounter', 1 );
										if (isset($_SESSION["klarna_sendEmail"]) && $_SESSION["klarna_sendEmail"] == "yes") {
										$mailer->send( $order->billing_email, sprintf( __( $_SESSION["klarna_successEmailHeader"].' %s' ), $order->get_order_number() ), $message );
										}
										$order->reduce_order_stock();
										WC()->cart->empty_cart();
									} else {
										$order->update_status('on-hold', 'order_note');
										$order->add_order_note("Payment via Sofortüberweisung <b>NOT</b> successfully!",0,true);
										$status .= "notok";
									}
								}
								$output[] = $method . ': ' . $SofortLibTransactionData->$method($params);
							} else {
								$output[] = $method . ': ' . $SofortLibTransactionData->$method();
								if ($method == "getStatus" && $sofortTransCounter != 1) {
									if ($SofortLibTransactionData->$method()) {
										$status .= "ok";
										$order->update_status('processing', 'order_note');
										$order->add_order_note($payment_ok.$transTime,0,true);
										// SEND MAIL
										add_post_meta( $order_id, 'sofortTransCounter', 1 );
										if (isset($_SESSION["klarna_sendEmail"]) && $_SESSION["klarna_sendEmail"] == "yes") {
										$mailer->send( $order->billing_email, sprintf( __( $_SESSION["klarna_successEmailHeader"].' %s' ), $order->get_order_number() ), $message );
										}
										$order->reduce_order_stock();
										WC()->cart->empty_cart();
									} else {
										$order->update_status('on-hold', 'order_note');
										$order->add_order_note("Payment via Sofortüberweisung <b>NOT</b> successfully!",0,true);
										$status .= "notok";
									}
								}
							}
						}
						
					$res ="";
					if($SofortLibTransactionData->isError()) {
						$res.= $SofortLibTransactionData->getError();	
					} else {
						$res.= implode('<br />', $output);
							
						
							
						//get all datas from backend
						$settings = get_option( 'woocommerce_sofortueberweisung_gateway_settings' );
						$displayService = $settings['displayService'];
						$displayOrderStatus = $settings['displayOrderStatus'];
						$displayTransID = $settings['displayTransID'];
						$displayTransTime = $settings['displayTransTime'];
						//$displayTransAmount = $settings['displayTransAmount'];
						$displayTransInfo = $settings['displayTransInfo'];

						//display or not?
						if ($displayService == "yes") { $displayService = "style=''"; } else { $displayService = "style='display: none;'"; }
						if ($displayOrderStatus == "yes") { $displayOrderStatus = "style=''"; } else { $displayOrderStatus = "style='display: none;'"; }
						if ($displayTransID == "yes") { $displayTransID = "style=''"; } else { $displayTransID = "style='display: none;'"; }
						if ($displayTransTime == "yes") { $displayTransTime = "style=''"; } else { $displayTransTime = "style='display: none;'"; }
						if ($displayTransInfo == "yes" or $displayTransInfo == "") { $displayTransInfo = "style=''"; } else { $displayTransInfo = "style='display: none;'"; }

						// Displaying transaction details
						echo '	<div class="col-xs" '.$displayTransInfo.'>
								<div class="card" style="padding: 20px;">
								<h2 class="woocommerce-order-details__title">'.__( 'Payment status', 'woo-sofortuberweisung-gateway').'</h2>
								<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">
								<tbody>
									<tr>
									<tr '.$displayService.'><th>'.__( 'Service', 'woo-sofortuberweisung-gateway').':</th> <td>Sofortüberweisung/Klarna</td></tr>
									<tr '.$displayOrderStatus.'><th>'.__( 'Order status', 'woo-sofortuberweisung-gateway').':</th> <td>' . $order->get_status() . '</td></tr>
									<tr '.$displayTransID.'><th>'.__( 'Transaction ID', 'woo-sofortuberweisung-gateway').':</th> <td>' . $transID . '</td></tr>			
							';
						//if payment failed status pending
						if (isset($_GET['act']) == "failed" or $order->get_status() == "pending") {
						$cart_url = get_permalink( wc_get_page_id( 'cart' ) );
						echo '	<tr><th>'.__('Start payment now', 'woo-sofortuberweisung-gateway').':</th><td><a href="'.$cart_url.'/order-pay/'.$order_id.'/?pay_for_order=true&amp;key='.$order->order_key.'" class="woocommerce-button button pay">'.__('Pay', 'woo-sofortuberweisung-gateway').'</a></td></tr>';	
						} else {
						echo "	<tr $displayTransTime><th>".__( 'Transaction time', 'woo-sofortuberweisung-gateway').":</th> <td>$transTime</td></tr>";
						}
						
						echo '	</tbody>
								</table>	
								</div>
								</div>
						';
					}
				}
			}
	}
}

new sofortueberweisung_gateway_check_payment();