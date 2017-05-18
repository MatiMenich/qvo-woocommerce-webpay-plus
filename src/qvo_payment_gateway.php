<?php
/**
 * @package QVO Payment Gateway
 * @version 1.0
 */

/**
 * Plugin Name: QVO Payment Gateway
 * Author: QVO
 * Version: 1.0
 * Description: Process payments using QVO API Webpay Plus
 * Author URI: http://qvo.cl/
*/

/**  ____ _   ______
 *  / __ \ | / / __ \
 * / /_/ / |/ / /_/ /
 * \___\_\___/\____/
*/

require_once( dirname(__FILE__).'/lib/restclient.php' );

add_action( 'plugins_loaded', 'init_qvo_payment_gateway' );

function init_qvo_payment_gateway() {
  class QVO_Payment_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $this->id = "qvo_webpay_plus";
      $this->icon = "https://www.transbank.cl/public/img/Logo_Webpay3-01-50x50.png";
      $this->method_title = __('QVO – Pago a través de Webpay Plus');
      $this->method_description = __('Pago con tarjeta través de QVO usando Webpay Plus');

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');

      $api_base_url = $this->get_option('environment') == 'sandbox' ? "https://sandbox.qvo.cl" : "https://api.qvo.cl";

      $this->api = new RestClient([
        'base_url' => $api_base_url,
        'format' => "json",
        'headers' => ['Authorization' => 'Token '.$this->get_option('api_key')]
      ]);

      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'check_qvo_webpay_plus', array( $this, 'check_response') );

      if ($this->doesnt_support_clp()) { $this->enabled = false; }
    }

    function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
            'title' => __('Activar/Desactivar', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Activar QVO Webpay Plus', 'woocommerce'),
            'default' => 'yes'
        ),
        'title' => array(
            'title' => __('T&iacute;tulo', 'woocommerce'),
            'type' => 'text',
            'default' => __('Pago con Tarjetas de Crédito o Redcompra', 'woocommerce')
        ),
        'description' => array(
            'title' => __('Descripción', 'woocommerce'),
            'type' => 'textarea',
            'default' => __('Paga con tu tarjeta usando Webpay Plus', 'woocommerce')
        ),
        'environment' => array(
            'title' => __('Ambiente', 'woocommerce'),
            'type' => 'select',
            'options' => array('sandbox' => 'Prueba', 'production' => 'Producción'),
            'default'     => __( 'sandbox', 'woocommerce' )
        ),
        'api_key' => array(
            'title' => __('API Key', 'woocommerce'),
            'type' => 'text'
        )
      );
    }

    function doesnt_support_clp(){
      return !in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')));
    }

    function process_payment( $order_id ) {
      $order = new WC_Order( $order_id );

      $result = $this->api->post( "webpay_plus/transactions", [
        'amount' => $order->get_total(),
        'return_url' => $this->return_url( $order )
      ]);

      if ( $result->info->http_code == 201 ) {
        return array('result' => 'success', 'redirect' => $result['redirect_url']);
      }
      else {
        wc_add_notice( 'Falló la conección con el procesador de pago. Notifique al comercio.', 'error' );
        return array('result' => 'failure', 'redirect' => '');
      }
    }

    function return_url( $order ){
      $baseUrl = $this->get_return_url( $order );

      if ( strpos( $baseUrl, '?' ) !== false ) {
        $baseUrl .= '&';
      } else {
        $baseUrl .= '?';
      }

      $order_id =  trim( str_replace( '#', '', $order->get_order_number() ) );

      return $baseUrl . 'qvo_webpay_plus=true&order_id=' . $order_id;
    }

    function check_response() {
      global $woocommerce;

      $order = new WC_Order( $_GET['order_id'] );

      if ( $this->order_already_handled( $order ) ) { return; }

      $transaction_id = $_GET['transaction_id'];
      $result = $this->api->get( "transactions/".$transaction_id );

      if ( $result->info->http_code == 200 ) {
        if ( $this->successful_transaction( $order, $result ) ) {
          $order->add_order_note(__('Pago con QVO Webpay Plus', 'woocommerce'));
          /* Agregar datos como número de cuotas, tipo de pago (crédito o débito) y últimos dígitos de la tarjeta */
          $order->update_status( 'completed' );
          $order->reduce_order_stock();
          $woocommerce->cart->empty_cart();
        }
        else {
          wc_add_notice( $result['gateway_response']->message, 'error' );

          $order->add_order_note( 'Error: '. $result['gateway_response']->message );
          $order->update_status( 'failed', $result['gateway_response']->message );
        }
      }
      else { $order->update_status( 'failed', $result['error'] ); }
    }

    function order_already_handled( $order ) {
      return ($order->has_status('completed') || $order->has_status('processing') || $order->has_status('failed'));
    }

    function successful_transaction( $order, $result ) {
      return ((string)$result['status'] == 'successful' && $order->get_total() == $result['payment']->amount);
    }
  }
}

function add_qvo_payment_gateway_class( $methods ) {
  $methods[] = 'QVO_Payment_Gateway';
  return $methods;
}

function check_for_qvo_webpay_plus() {
  if ( isset($_GET['qvo_webpay_plus']) ) {
    WC()->payment_gateways();
    do_action( 'check_qvo_webpay_plus' );
  }
}

add_action( 'init', 'check_for_qvo_webpay_plus' );
add_filter( 'woocommerce_payment_gateways', 'add_qvo_payment_gateway_class' );

?>
