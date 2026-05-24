<?php
/**
 * Plugin Name: WooCommerce M-Pesa Gateway
 * Description: A custom WooCommerce payment gateway plugin written in PHP for integrating Safaricom M-Pesa STK Push payments into a WordPress/WooCommerce store.
 * Version: 1.0
 * Author: Jesse Jim @ iTechie 360
 * Author URI: https://www.itechie360.com | https://www.github.com/IAmJesseJim | https://iamjessejim.vercel.app
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 1. Register the custom gateway with WooCommerce
add_filter('woocommerce_payment_gateways', 'add_my_mpesa_gateway');
function add_my_mpesa_gateway($gateways) {
    $gateways[] = 'WC_Mpesa_Gateway';
    return $gateways;
}

// 2. Initialize the gateway class
add_action('plugins_loaded', 'init_my_mpesa_gateway');
function init_my_mpesa_gateway() {

    class WC_Mpesa_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'my_mpesa';
            $this->icon               = apply_filters('woocommerce_mpesa_icon', plugins_url('assets/mpesa-logo.png', __FILE__)); // Optional: Add an icon
            $this->has_fields         = true; // We need a phone number field
            $this->method_title       = __('Lipa na M-Pesa', 'my-mpesa');
            $this->method_description = __('Enable customers to pay directly via M-Pesa STK Push.', 'my-mpesa');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user-facing properties.
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->enabled      = $this->get_option('enabled');

            // Admin options handler
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Callback handler
            add_action('woocommerce_api_mpesa_callback', array($this, 'handle_callback'));
        }

        /**
         * Admin Settings Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable M-Pesa Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Lipa na M-Pesa',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay securely using M-Pesa STK Push.',
                ),
                'consumer_key' => array(
                    'title' => 'Consumer Key',
                    'type'  => 'text'
                ),
                'consumer_secret' => array(
                    'title' => 'Consumer Secret',
                    'type'  => 'password'
                ),
                'shortcode' => array(
                    'title' => 'Business Shortcode',
                    'type'  => 'text'
                ),
                'passkey' => array(
                    'title' => 'Lipa na M-Pesa Passkey',
                    'type'  => 'text'
                ),
                'environment' => array(
                    'title'   => 'Environment',
                    'type'    => 'select',
                    'options' => array(
                        'sandbox'    => 'Sandbox',
                        'production' => 'Production',
                    ),
                    'default' => 'sandbox',
                )
            );
        }
        
        /**
         * Phone number input field on the checkout page.
         */
        public function payment_fields() {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
            echo '<p class="form-row form-row-first">
                    <label>M-Pesa Phone Number <span class="required">*</span></label>
                    <input type="text" class="input-text" name="mpesa_phone" placeholder="2547XXXXXXXX">
                  </p>';
            echo '<div class="clear"></div>';
            echo '</fieldset>';
        }

        /**
         * Process the payment and return the result.
         */
        public function process_payment($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);
            
            // Sanitize phone number
            $phone_number = sanitize_text_field($_POST['mpesa_phone']);
            if (substr($phone_number, 0, 1) === '0') {
                $phone_number = '254' . substr($phone_number, 1);
            }

            // Get API credentials from settings
            $consumer_key = $this->get_option('consumer_key');
            $consumer_secret = $this->get_option('consumer_secret');
            $shortcode = $this->get_option('shortcode');
            $passkey = $this->get_option('passkey');
            $environment = $this->get_option('environment');

            $api_url = ($environment == 'sandbox') ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
            $token_url = $api_url . '/oauth/v1/generate?grant_type=client_credentials';
            $stk_push_url = $api_url . '/mpesa/stkpush/v1/processrequest';

            // 1. Get Access Token
            $response = wp_remote_get($token_url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret)
                ]
            ]);

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            $access_token = $data->access_token;
            
            if (!$access_token) {
                 wc_add_notice('Could not connect to M-Pesa. Please try again later.', 'error');
                 return;
            }

            // 2. Prepare STK Push Request
            $timestamp = date('YmdHis');
            $password = base64_encode($shortcode . $passkey . $timestamp);
            $amount = round($order->get_total()); // M-Pesa requires a whole number
            $callback_url = home_url('/wc-api/mpesa_callback/');

            $request_body = [
                'BusinessShortCode' => $shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline', // or 'CustomerBuyGoodsOnline'
                'Amount'            => $amount,
                'PartyA'            => $phone_number,
                'PartyB'            => $shortcode,
                'PhoneNumber'       => $phone_number,
                'CallBackURL'       => $callback_url,
                'AccountReference'  => 'Order ' . $order_id,
                'TransactionDesc'   => 'Payment for order ' . $order_id
            ];

            // 3. Send STK Push Request
            $stk_response = wp_remote_post($stk_push_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json'
                ],
                'body' => json_encode($request_body)
            ]);

            $stk_body = json_decode(wp_remote_retrieve_body($stk_response));

            if (isset($stk_body->ResponseCode) && $stk_body->ResponseCode == '0') {
                // Success - store CheckoutRequestID to verify callback
                $checkout_request_id = $stk_body->CheckoutRequestID;
                $order->update_meta_data('_mpesa_checkout_request_id', $checkout_request_id);
                $order->save();

                // Reduce stock levels
                wc_reduce_stock_levels($order_id);
                
                // Remove cart
                $woocommerce->cart->empty_cart();

                // Return redirect to the "thank you" page
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                $error_message = isset($stk_body->errorMessage) ? $stk_body->errorMessage : 'An unknown error occurred.';
                wc_add_notice('Payment failed: ' . $error_message, 'error');
                return;
            }
        }
        
        /**
         * Handle the callback from Safaricom.
         */
        public function handle_callback() {
            $callback_data = file_get_contents('php://input');
            $data = json_decode($callback_data);
            
            $result_code = $data->Body->stkCallback->ResultCode;
            $checkout_request_id = $data->Body->stkCallback->CheckoutRequestID;
            
            // Find the order using the CheckoutRequestID
            $orders = wc_get_orders(array(
                'meta_key' => '_mpesa_checkout_request_id',
                'meta_value' => $checkout_request_id,
            ));
            
            if ($orders) {
                $order = $orders[0];
                
                if ($result_code == 0) {
                    // Payment successful
                    $order->payment_complete();
                    $order->add_order_note('M-Pesa payment successful.');
                } else {
                    // Payment failed or was cancelled
                    $order->update_status('failed', 'M-Pesa payment failed or was cancelled by the user.');
                }
            }
            
            // Respond to Safaricom to acknowledge receipt
            header('Content-Type: application/json');
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            exit;
        }
    }
}