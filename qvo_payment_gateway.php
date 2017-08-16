<?php
/**
 * @package QVO Payment Gateway
 * @version 1.2.1
 * @link              https://qvo.cl
 * @since             1.2.0
 */

/**
 * Plugin Name: QVO Payment Gateway
 * Author: QVO
 * Version: 1.2.1
 * Description: Process payments using QVO API Webpay Plus
 * Author URI: http://qvo.cl/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: qvo-woocommerce-webpay-plus
*/

/**  ____ _   ______
 *  / __ \ | / / __ \
 * / /_/ / |/ / /_/ /
 * \___\_\___/\____/
*/

add_action( 'plugins_loaded', 'init_qvo_payment_gateway' );

function init_qvo_payment_gateway() {
  class QVO_Payment_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $plugin_dir = plugin_dir_url(__FILE__);

      $this->id = "qvo_webpay_plus";
      $this->icon = $plugin_dir."/assets/images/Logo_Webpay3-01-50x50.png";
      $this->method_title = __('QVO – Pago a través de Webpay Plus');
      $this->method_description = __('Pago con tarjeta través de QVO usando Webpay Plus');

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');

      $this->api_base_url = $this->get_option('environment') == 'sandbox' ? "https://playground.qvo.cl" : "https://api.qvo.cl";

      $this->headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Token '.$this->get_option('api_key')
      );

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
            'title' => __('Title', 'woocommerce'),
            'type' => 'text',
            'default' => __('Pago con Tarjetas de Crédito o Redcompra', 'woocommerce'),
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
            'desc_tip' => true
        ),
        'description' => array(
            'title' => __('Descripción', 'woocommerce'),
            'type' => 'textarea',
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
        'api_key' => array(
            'title' => __('API Key', 'woocommerce'),
            'description' => __('Ingresa tu API Token de QVO (Lo puedes encontrar en la sección <strong>API</strong> del Dashboard de QVO)', 'woocommerce'),
            'type' => 'text'
        )
      );
    }

    function doesnt_support_clp(){
      return !in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')));
    }

    function process_payment( $order_id ) {
      $order = new WC_Order( $order_id );

      $data = array(
        'amount' => $order->get_total(),
        'description' => "Orden ".$order_id." - ".get_bloginfo( 'name' ),
        'return_url' => $this->return_url( $order )
      );

      $response = Requests::post($this->api_base_url.'/webpay_plus/charge', $this->headers, json_encode($data));
      $body = json_decode($response->body);

      if ( $response->status_code == 201 ) {
        return array('result' => 'success', 'redirect' => $body->redirect_url);
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

      $response = Requests::get($this->api_base_url.'/transactions/'.$transaction_id, $this->headers);
      $body = json_decode($response->body);

      if ( $response->status_code == 200 ) {
        if ( $this->successful_transaction( $order, $body ) ) {
          $order->add_order_note(__('Pago con QVO Webpay Plus', 'woocommerce'));
          $order->add_order_note(__('Pago con '.$this->parse_payment_type($body->payment), 'woocommerce'));

          $order->payment_complete();

          if ($order->status == 'processing') {
            WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->id);
          }
          if ($order->status == 'completed') {
            WC()->mailer()->emails['WC_Email_Customer_Order_Completed']->trigger($order->id);
          }

          WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->id);

          $woocommerce->cart->empty_cart();
        }
        else {
          wc_add_notice( $body->gateway_response->message, 'error' );

          $order->add_order_note( 'Error: '. $body->gateway_response->message );
          $order->update_status( 'failed', $body->gateway_response->message );
        }
      }
      else { $order->update_status( 'failed', $body->error ); }
    }

    function order_already_handled( $order ) {
      return ($order->has_status('completed') || $order->has_status('processing') || $order->has_status('failed'));
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
