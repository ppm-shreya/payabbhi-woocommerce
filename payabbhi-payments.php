<?php

/*
Plugin Name: Payabbhi WooCommerce
Plugin URI: https://payabbhi.com
Description: Payabbhi Payment Gateway Integration for WooCommerce
Version: 1.0.0
Author: Payabbhi Team
Author URI: https://payabbhi.com
*/

require_once('vendor/autoload.php');

add_action('plugins_loaded', 'init_payabbhi_woocommerce', 0);

function init_payabbhi_woocommerce()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_Payabbhi extends WC_Payment_Gateway
    {
        // This one stores the WooCommerce Order Id
        const WC_ORDER_SESSION_KEY = 'wc_order_id';
        const PAYABBHI_ORDER_SESSION_KEY = 'payabbhi_order_id';

        public function __construct()
        {
            $this->id = 'payabbhi';
            $this->method_title = 'Payabbhi';
            $this->icon =  plugins_url('images/logo.png' , __FILE__);
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->access_id = $this->settings['access_id'];
            $this->secret_key = $this->settings['secret_key'];
            $this->payment_auto_capture = $this->settings['payment_auto_capture'];

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('init', array(&$this, 'verify_payment_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'verify_payment_response'));

            $cb = array($this, 'process_admin_options');

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            {
                add_action("woocommerce_update_options_payment_gateways_{$this->id}", $cb);
            }
            else
            {
                add_action('woocommerce_update_options_payment_gateways', $cb);
            }

            add_action("woocommerce_receipt_{$this->id}", array($this, 'receipt_page'));
        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'payabbhi'),
                    'type' => 'checkbox',
                    'label' => __('Enable Payabbhi Payment.', 'payabbhi'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'payabbhi'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'payabbhi'),
                    'default' => __('Cards / NetBanking / Wallets', 'payabbhi')

                ),
                'description' => array(
                    'title' => __('Description', 'payabbhi'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'payabbhi'),
                    'default' => __('Pay securely using Card, NetBanking or Wallet via Payabbhi.', 'payabbhi')
                ),
                'access_id' => array(
                    'title' => __('Access ID', 'payabbhi'),
                    'type' => 'text',
                    'description' => __('Access ID is available as part of API Keys downloaded from the Portal', 'payabbhi')
                ),
                'secret_key' => array(
                    'title' => __('Secret Key', 'payabbhi'),
                    'type' => 'text',
                    'description' => __('Secret Key is available as part of API Keys downloaded from the Portal', 'payabbhi')
                ),
                'payment_auto_capture' => array(
                    'title' => __('Payment Auto Capture', 'payabbhi'),
                    'type' => 'select',
                    'description' =>  __('Specify whether the payment should be captured automatically. Refer to Payabbhi API Reference.', 'payabbhi'),
                    'default' => 'true',
                    'options' => array(
                        'true' => 'True',
                        'false'   => 'False'
                    )
                )
            );
        }
        public function admin_options()
        {
            echo '<h3>'.__('Payabbhi Payment Gateway', 'payabbhi') . '</h3>';
            echo '<p>'.__('Payabbhi is an online payment gateway for India with transparent pricing, seamless integration and great support') . '</p>';
            echo '<table class="form-table">';

            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if($this->description)
            {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Receipt Page
         **/
        function receipt_page($merchant_order_id)
        {
            echo '<p>'.__('Thank you for your order, please click the button below to pay securely via Payabbhi.', 'payabbhi').'</p>';
            echo $this->generate_payabbhi_form($merchant_order_id);
        }

        /**
         * Generate button link
         **/
        public function generate_payabbhi_form($merchant_order_id)
        {
            global $woocommerce;
            $wc_order = new WC_Order($merchant_order_id);

            $return_url = get_site_url() . '/?wc-api=' . get_class($this);

            try
            {
                $payabbhi_order_id = $woocommerce->session->get(self::PAYABBHI_ORDER_SESSION_KEY);

                if (($payabbhi_order_id === null) or
                      (($payabbhi_order_id and ($this->verify_order_amount($payabbhi_order_id, $merchant_order_id)) === false)))
                {
                    $payabbhi_order_id = $this->create_payabbhi_order($merchant_order_id);
                }
            }
            catch (Exception $e)
            {
                echo "Payabbhi ERROR: " . $e->getMessage();
            }

            $checkoutArgs = array(
            'access_id'     => $this->access_id,
            'order_id'      => $payabbhi_order_id,
            'amount'        => (int) round($wc_order->order_total*100),
            'name'          => get_bloginfo('name'),
            'description'   => 'Order #' . $merchant_order_id,
            'prefill'     => array(
              'name'      => $wc_order->billing_first_name." ".$wc_order->billing_last_name,
              'email'     => $wc_order->billing_email,
              'contact'   => $wc_order->billing_phone
            ),
            'notes'       => array(
              'merchant_order_id' => $merchant_order_id
            )
          );

          return $this->generate_order_form($return_url, json_encode($checkoutArgs));
        }

        protected function verify_order_amount($payabbhi_order_id, $merchant_order_id)
        {
            $wc_order = new WC_Order($merchant_order_id);

            $client = new \Payabbhi\Client($this->access_id, $this->secret_key);

            try {
              $payabbhi_order = $client->order->retrieve($payabbhi_order_id);
            } catch(Exception $e) {
                return false;
            }

            $payabbhi_order_args = array(
                'id'                  => $payabbhi_order_id,
                'amount'              => (int) round($wc_order->order_total*100),
                'currency'            => get_woocommerce_currency(),
                'merchant_order_id'   => (string) $merchant_order_id,
            );

            $orderKeys = array_keys($payabbhi_order_args);

            foreach ($orderKeys as $key)
            {
                if ($payabbhi_order_args[$key] !== $payabbhi_order[$key])
                {
                    return false;
                }
            }

            return true;
        }

        protected function create_payabbhi_order($merchant_order_id)
        {
            // Calls the helper function to create order data
            global $woocommerce;
            $client= new \Payabbhi\Client($this->access_id, $this->secret_key);

            $wc_order = new WC_Order($merchant_order_id);

            if (!isset($this->payment_auto_capture))
            {
                $this->payment_auto_capture = 'true';
            }

            $order_params = array('merchant_order_id'    => $merchant_order_id,
                                 'amount'               => (int) round($wc_order->order_total*100),
                                 'currency'             => get_woocommerce_currency(),
                                 'payment_auto_capture' => ($this->payment_auto_capture === 'true')
                               );

            $payabbhi_order_id = $client->order->create($order_params)->id;
            $woocommerce->session->set(self::PAYABBHI_ORDER_SESSION_KEY, $payabbhi_order_id);

            return $payabbhi_order_id;
        }

        /**
         * Generates the order form
         **/
        function generate_order_form($return_url, $checkoutArgs)
        {
            $html = <<<EOT
<script src="https://checkout.payabbhi.com/v1/checkout.js"></script>
<form name='checkoutform' id="checkout-form" action="$return_url" method="POST">
  <input type="hidden" name="order_id" id="order_id">
  <input type="hidden" name="payment_id" id="payment_id">
  <input type="hidden" name="payment_signature" id="payment_signature">
</form>

<p id="msg-success" class="woocommerce-info woocommerce-message" style="display:none">
Please wait while we are processing your payment.
</p>
<p>
    <button id="btn-submit">Pay Now</button>
    <button id="btn-cancel">Cancel</button>
</p>
<script>
    (function(){
        var setDisabled = function(id, state) {
          if (typeof state === 'undefined') {
            state = true;
          }
          var elem = document.getElementById(id);
          if (state === false) {
            elem.removeAttribute('disabled');
          }
          else {
            elem.setAttribute('disabled', state);
          }
        };
        var params = $checkoutArgs;

        params.handler = function(payment){
          setDisabled('btn-cancel');
          var successMsg = document.getElementById('msg-success');
          successMsg.style.display = "block";

          document.getElementById('order_id').value = payment.order_id;
          document.getElementById('payment_id').value = payment.payment_id;
          document.getElementById('payment_signature').value = payment.payment_signature;
          document.getElementById('checkout-form').submit();
        };
        var payabbhiCheckout = new Payabbhi(params);

        function openCheckout() {
          // setDisabled('btn-submit');
          payabbhiCheckout.open();
        }
        function addEvent(element, evnt, funct){
          if (element.attachEvent)
           return element.attachEvent('on'+evnt, funct);
          else
           return element.addEventListener(evnt, funct, false);
        }
        // Attach event listener
        addEvent(document.getElementById('btn-submit'), 'click', openCheckout);
        addEvent(document.getElementById('btn-cancel'), 'click', function () {
          document.getElementById('checkout-form').submit();
        });

        openCheckout();
})();
</script>
EOT;
            return $html;
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($merchant_order_id)
        {
            global $woocommerce;
            $wc_order = new WC_Order($merchant_order_id);
            $woocommerce->session->set(self::WC_ORDER_SESSION_KEY, $merchant_order_id);
            if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>='))
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('key', $wc_order->order_key, $wc_order->get_checkout_payment_url(true))
                );
            }
            else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $wc_order->id,
                        add_query_arg('key', $wc_order->order_key, $wc_order->get_checkout_payment_url(true)))
                );
            }
            else
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $wc_order->id,
                        add_query_arg('key', $wc_order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
                );
            }
        }

        /**
         * Check for valid payment response from server
         **/
        function verify_payment_response()
        {
            global $woocommerce;
            $merchant_order_id = $woocommerce->session->get(self::WC_ORDER_SESSION_KEY);

            $payment_id = $_POST['payment_id'];
            $order_id = $_POST['order_id'];

            if ($merchant_order_id and !empty($payment_id))
            {
                $wc_order = new WC_Order($merchant_order_id);

                $success = false;
                $error = 'WOOCOMMERCE_ERROR: Payment to Payabbhi Failed. ';

                $client = new \Payabbhi\Client($this->access_id, $this->secret_key);

                $attributes = array(
                  'payment_id'        => $payment_id,
                  'order_id'          => $order_id,
                  'payment_signature' => $_POST['payment_signature']
                );

                try
                {
                    $client->utility->verifyPaymentSignature($attributes);
                    $success = true;
                }
                catch (\Payabbhi\Error $e)
                {
                    $error .= $e->getMessage();
                }

                if ($success === true)
                {
                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be processing your order soon. Order Id: $merchant_order_id";
                    $this->msg['class'] = 'success';
                    $wc_order->payment_complete();
                    $wc_order->add_order_note("Payment successful via Payabbhi<br/>Payabbhi Payment ID: $payment_id<br/>Payabbhi Order ID: $order_id");
                    $wc_order->add_order_note($this->msg['message']);
                    $woocommerce->cart->empty_cart();
                }
                else
                {
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = 'Thank you for shopping with us. However, the payment failed.';
                    $wc_order->add_order_note("Transaction Declined: $error<br/>");
                    $wc_order->add_order_note("Payment Failed. Please check Payabbhi Portal. <br/>Payabbhi Payment ID: $payment_id<br/>Payabbhi Order ID: $order_id");
                    $wc_order->update_status('failed');
                }
            }
            // merchant_order_id is not null but payment_id is
            else
            {
                if ($merchant_order_id !== null)
                {
                    $wc_order = new WC_Order($merchant_order_id);
                    $wc_order->update_status('failed');
                    $wc_order->add_order_note('Customer cancelled the payment');
                }
                $this->msg['class'] = 'error';
                $this->msg['message'] = "An error occured while processing this payment";
            }
            $this->add_notice($this->msg['message'], $this->msg['class']);
            $return_url= $this->get_return_url($wc_order);
            wp_redirect( $return_url );
            exit;
        }

        /**
         * Add a woocommerce notification message
         *
         * @param string $message Notification message
         * @param string $type Notification type, default = notice
         */
        protected function add_notice($message, $type = 'notice')
        {
            global $woocommerce;
            $type = in_array($type, array('notice','error','success'), true) ? $type : 'notice';
            // Check for existence of new notification api. Else use previous add_error
            if (function_exists('wc_add_notice'))
            {
                wc_add_notice($message, $type);
            }
            else
            {
                // Retrocompatibility WooCommerce < 2.1
                switch ($type)
                {
                    case "error" :
                        $woocommerce->add_error($message);
                        break;
                    default :
                        $woocommerce->add_message($message);
                        break;
                }
            }
        }
    }

    function woocommerce_add_payabbhi_gateway($methods)
    {
       $methods[] = 'WC_Gateway_Payabbhi';
       return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_payabbhi_gateway');
}
