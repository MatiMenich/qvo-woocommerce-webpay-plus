<?php
/**
 * @package QVO Payment Gateway
 * @version 1.3.1
 * @link              https://qvo.cl
 * @since             1.2.0
 */

/**
 * Plugin Name: QVO Payment Gateway
 * Author: QVO
 * Version: 1.3.1
 * Description: Process payments using QVO API Webpay Plus
 * Author URI: https://qvo.cl/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: qvo-woocommerce-webpay-plus
*/

/**  ____ _   ______
 *  / __ \ | / / __ \
 * / /_/ / |/ / /_/ /
 * \___\_\___/\____/
*/

defined( 'ABSPATH' ) or exit;

/*
 * Set global parameters
 */
global $qvo_settings;

/*
 * Get Settings
 */
$qvo_settings = get_option('woocommerce_qvo_webpay_plus_settings');

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

add_action( 'plugins_loaded', 'init_qvo_payment_gateway' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qvo_webpay_plus_action_links' );

function qvo_webpay_plus_action_links( $links ) {
  $plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=qvo_webpay_plus' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>',
  );
  return array_merge( $plugin_links, $links );
}

if($qvo_settings['environment'] == 'production' && empty($qvo_settings['api_key_production'])) {

  function qvo_production_api_key_notice() {
      ?>
      <div class="notice notice-error" style="<?php
          if(isset($_GET['section']) &&  $_GET['section'] == "qvo_webpay_plus") echo "display:none;";
      ?>">
          <p>⚠️ <strong>QVO Payment Gateway</strong>: Revisa tus credenciales. Es posible que <strong>no puedas recibir pagos</strong>. <a href="admin.php?page=wc-settings&tab=checkout&section=qvo_webpay_plus">Ir a la configuración</a></p>
      </div>
      <?php
  }
  add_action('admin_notices', 'qvo_production_api_key_notice');
}

if($qvo_settings['environment'] != 'production' && empty($qvo_settings['api_key_sandbox'])) {

  function qvo_test_api_key_notice() {
      ?>
      <div class="notice notice-warning" style="<?php
          if(isset($_GET['section']) &&  $_GET['section'] == "qvo_webpay_plus") echo "display:none;";
      ?>">
          <p>ℹ <strong>QVO Payment Gateway</strong>: Debes configurar las credenciales para poder recibir pagos. <a href="admin.php?page=wc-settings&tab=checkout&section=qvo_webpay_plus">Ir a la configuración</a></p>
      </div>
      <?php
  }
  add_action('admin_notices', 'qvo_test_api_key_notice');
}

function init_qvo_payment_gateway()
{
  class QVO_Payment_Gateway extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $plugin_dir = plugin_dir_url(__FILE__);

      $this->id = "qvo_webpay_plus";
      $this->icon = $plugin_dir."/assets/images/Logo_Webpay3-01-50x50.png";
      $this->method_title = __('QVO – Pago a través de Webpay Plus');
      $this->method_description = __('Pago con tarjeta a través de QVO usando Webpay Plus');
      //$this->supports = array('products','refunds'); // we will add refunds support soon

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->api_key_sandbox = $this->get_option('api_key_sandbox');
      $this->api_key_production = $this->get_option('api_key_production');

      if ($this->get_option('environment') == 'sandbox') {
        $this->api_base_url = 'https://playground.qvo.cl';
        $this->api_key = $this->api_key_sandbox;
        $this->view_transaction_url = 'https://dashboard-test.qvo.cl/dashboard/transactions/%s';
      } else {
        $this->api_base_url = 'https://api.qvo.cl';
        $this->api_key = $this->api_key_production;
        $this->view_transaction_url = 'https://dashboard.qvo.cl/dashboard/transactions/%s';
      }

      $this->headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Token '.$this->api_key
      );

      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_thankyou', array( $this, 'check_response') );

      if ($this->doesnt_support_clp()) { $this->enabled = false; }

    }


    function init_form_fields()
    {
      $this->form_fields = array(
        'enabled' => array(
            'title' => __('Activar/Desactivar', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Activar QVO Webpay Plus', 'woocommerce'),
            'default' => 'yes'
        ),
        'title' => array(
            'title' => __('Title', 'woocommerce'),
            'type' => 'text',
            'default' => __('Pago con Tarjetas de Crédito o Redcompra', 'woocommerce'),
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
            'desc_tip' => true
        ),
        'description' => array(
            'title' => __('Descripción', 'woocommerce'),
            'type' => 'text',
            'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
            'default' => __('Paga con tu tarjeta usando Webpay Plus', 'woocommerce'),
            'desc_tip' => true
        ),
        'environment' => array(
            'title' => __('Ambiente', 'woocommerce'),
            'type' => 'select',
            'options' => array('sandbox' => 'Prueba', 'production' => 'Producción'),
            'default'     => __( 'sandbox', 'woocommerce' ),
            'description' => __('Para realizar cobros de prueba, selecciona "Prueba" e inserta tu API Token de prueba a continuación. Si estás listo y deseas cobrar de verdad, selecciona "Producción" y solicita tus credenciales de producción en el Dashboard de QVO o a <a href="mailto:soporte@qvo.cl">soporte@qvo.cl</a>', 'woocommerce')
        ),
        'api_key_sandbox' => array(
            'title' => __('API Token Prueba', 'woocommerce'),
            'description' => __('Ingresa tu API Token de Prueba de QVO (Lo puedes encontrar en la sección <a href="https://dashboard-test.qvo.cl/dashboard/api" target="_blank"><strong>API</strong> del Dashboard Test de QVO</a>)', 'woocommerce'),
            'type' => 'text'
        ),
        'api_key_production' => array(
            'title' => __('API Token Producción', 'woocommerce'),
            'description' => __('Ingresa tu API Token de Producción de QVO (Lo puedes encontrar en la sección <a href="https://dashboard.qvo.cl/dashboard/api" target="_blank"><strong>API</strong> del Dashboard de QVO</a>)', 'woocommerce'),
            'type' => 'text'
        )
      );
    }

    function doesnt_support_clp()
    {
      return !in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')));
    }

    function process_payment( $order_id )
    {
      $order = wc_get_order( $order_id );

      $data = array(
        'amount' => number_format($order->get_total(), -2, '', ''),
        'description' => "Orden ".$order_id." - ".get_bloginfo( 'name' ),
        'return_url' => $this->return_url( $order ),
        'customer' => $this->build_customer_params( $order )
      );

      $response = Requests::post($this->api_base_url.'/webpay_plus/charge', $this->headers, json_encode($data));
      $body = json_decode($response->body);

      if ( $response->status_code == 201 ) {
        return array(
          'result' => 'success',
          'redirect' => $body->redirect_url
        );
      }
      else {
        wc_add_notice( 'Falló la conexión con el procesador de pago. Notifique al comercio.', 'error' );
        return array(
          'result' => 'failure',
          'redirect' => ''
        );
      }
    }

    function return_url( $order )
    {
      $baseUrl = $order->get_checkout_order_received_url();

      if ( strpos( $baseUrl, '?' ) !== false ) {
        $baseUrl .= '&';
      } else {
        $baseUrl .= '?';
      }

      $order_id =  trim( str_replace( '#', '', $order->get_order_number() ) );

      return $baseUrl . 'order_id=' . $order_id;
    }

    function build_customer_params( $order )
    {
      $customerEmail = $order->get_billing_email();

      $customerName = $order->get_billing_first_name();
      $customerName .= ' ' . $order->get_billing_last_name();

      $customerPhone = $order->get_billing_phone();

      # TODO: Customer address!

      return array(
        'email' => $customerEmail,
        'name' => $customerName,
        'phone' => $customerPhone
      );
    }

    function check_response()
    {
      global $woocommerce;

      //  TODO: Better way of handling this
      //  Means redirect from failed payment attempt
      if(empty($_GET['order_id'])) { return; }

      $order = wc_get_order($_GET['order_id']);
      $order_id = $order->get_id();

      if ( $this->order_already_handled( $order ) ) { return; }

      $qvo_data = new QVO_Payment_Gateway;
      if ($order->get_payment_method_title() != $qvo_data->title) { return; }

      $transaction_id = $_GET['transaction_id'];

      $response = Requests::get($this->api_base_url.'/transactions/'.$transaction_id, $this->headers);
      $body = json_decode($response->body);

      if ( $response->status_code == 200 ) {
        if ( $this->successful_transaction( $order, $body ) ) {
          $has_to_redirect = $order->has_status('failed');

          $order->add_order_note(__('Pago con QVO Webpay Plus', 'woocommerce'));
          $order->add_order_note(__('Pago con '.$this->parse_payment_type($body->payment), 'woocommerce'));

          $order->payment_complete( $transaction_id );

          $woocommerce->cart->empty_cart();

          if($has_to_redirect) {
            wp_redirect($this->get_return_url($order));

            return;
          }
        }
        else {
          wc_add_notice( $body->gateway_response->message . '. Por favor intenta nuevamente.', 'error' );

          $order->add_order_note( 'Error: '. $body->gateway_response->message );
          $order->update_status( 'failed', $body->gateway_response->message );

          wp_redirect($order->get_checkout_payment_url());
        }
      }
      else {
        wc_add_notice( 'Ha existido un error en el pago, por favor intenta nuevamente.', 'error' );

        $order->update_status( 'failed', $body->error );

        wp_redirect($order->get_checkout_payment_url());
      }
    }

    function order_already_handled( $order ) {
      return ($order->has_status('completed') || $order->has_status('processing'));
    }

    function parse_payment_type( $payment ) {
      if ((string)$payment->payment_type == 'debit') {
        return 'Débito';
      }
      else {
        return 'Crédito en '.((string)$payment->installments).' cuotas';
      }
    }

    function successful_transaction( $order, $body ) {
      return ((string)$body->status == 'successful' && $order->get_total() == $body->payment->amount);
    }
  }
}

/* function process_refund( $order_id, $amount = null ) {
  // Do your refund here. Refund $amount for the order with ID $order_id
  return true;
} */ // we will add refunds support soon

function add_qvo_payment_gateway_class( $methods ) {
  $methods[] = 'QVO_Payment_Gateway';
  return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_qvo_payment_gateway_class' );

?>
