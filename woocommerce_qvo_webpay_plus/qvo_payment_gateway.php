<?php
/**
 * @package QVO Payment Gateway
 * @version 0.1
 */
/*
Plugin Name: QVO Payment Gateway
Author: QVO
Version: 0.1
Author URI: http://qvo.cl/
*/

// Now we set that function up to execute when the admin_notices action is called
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
      $this->api = new RestClient([
        'base_url' => "http://api.qvo.cl", 
        'format' => "json", 
        'headers' => ['Authorization' => 'Bearer '.$this->get_option('api_key')]
      ]);

      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'check_qvo_webpay_plus', array( $this, 'check_response') );

      if (!$this->is_valid_for_use()) {
        $this->enabled = false;
      }
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
            'default' => __('Permite el pago de productos y/o servicios, con Tarjetas de Crédito y Redcompra a través de QVO usando Webpay Plus', 'woocommerce')
        ),
        'environment' => array(
            'title' => __('Ambiente', 'woocommerce'),
            'type' => 'select',
            'options' => array('staging' => 'Prueba', 'production' => 'Producción'),
            'default'     => __( 'staging', 'woocommerce' )
        ),
        'api_key' => array(
            'title' => __('API Key', 'woocommerce'),
            'type' => 'text'
        )
      );
    }

    function process_payment( $order_id ) {

      $order = new WC_Order( $order_id );
      $baseUrl = $this->get_return_url( $order );

      if( strpos( $baseUrl, '?') !== false ) {
        $baseUrl .= '&';
      } else {
        $baseUrl .= '?';
      }

      $result = $this->api->post("webpay_plus/create_transaction", ['amount' => $order->get_total(), 'return_url' => $baseUrl . 'qvo_webpay_plus=true&order_id=' . $order_id]);

      if($result->info->http_code == 201) {
        return array(
          'result' => 'success',
          'redirect' => $result['redirect_url']
        );
      }
      else {
        wc_add_notice( 'Falló la conección con el procesador de pago. Notifique al comercio.', 'error' );
        return array('result' => 'failure', 'redirect' => '');
      }
    }

    function is_valid_for_use() {
      if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))) {
        return false;
      }
        return true;
    }

    public function check_response() {
      global $woocommerce;

      $order_id = $_GET['order_id'];
      $transaction_uid = $_GET['uid'];
      $order = new WC_Order( $order_id );

      if ( $order->has_status('completed') || $order->has_status('processing') || $order->has_status('failed') ) { return; }

      $result = $this->api->get("webpay_plus/transaction/".$transaction_uid);

      if ( $result->info->http_code == 200 ) {
        if ( (string)$result['status'] == 'paid' && $order->get_total() == $result['payment']->amount ) {
          // Process paid order
          $order->add_order_note(__('Pago con QVO Webpay Plus', 'woocommerce'));
          $order->update_status('completed');
          $order->reduce_order_stock();
          $woocommerce->cart->empty_cart();
        }
        else {
          // Transaction was not succesful
          // Add notice to the cart
          wc_add_notice( $result['error']->reason, 'error' );

          // Add note to the order for your reference
          $order->add_order_note( 'Error: '. $result['error']->reason );
          $order->update_status( 'failed', $result['error']->reason );
        }
      }
      else { $order->update_status( 'failed', $result['error']->reason ); }
    }
  }
}

function add_qvo_payment_gateway_class( $methods ) {
  $methods[] = 'QVO_Payment_Gateway'; 
  return $methods;
}

function check_for_qvo_webpay_plus() {
  if( isset($_GET['qvo_webpay_plus'])) {
    
    WC()->payment_gateways();

    do_action( 'check_qvo_webpay_plus' );
  } 
}

add_action( 'init', 'check_for_qvo_webpay_plus' );
add_filter( 'woocommerce_payment_gateways', 'add_qvo_payment_gateway_class' );

?>