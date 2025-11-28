<?php
/**
 * Plugin Name: WooCommerce Multi-Crypto Payment Gateway
 * Plugin URI: https://example.com/woo-crypto-payment
 * Description: Accept multiple cryptocurrencies in your WooCommerce store
 * Version: 1.0.0
 * Author: Festus Okonye
 * License: GPL v2 or later
 * Text Domain: woo-crypto-payment
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main Payment Gateway Class - Defined inside plugins_loaded hook
function woo_crypto_define_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }
    
    class WC_Gateway_Crypto extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'crypto_payment';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'Cryptocurrency Payment';
        $this->method_description = 'Accept Bitcoin, Ethereum, USDT, Bitcoin Cash, and Litecoin';
        
        $this->supports = array( 'products' );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled_cryptos = $this->get_option( 'enabled_cryptos', array( 'bitcoin', 'ethereum' ) );
        
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Cryptocurrency Payment Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'Cryptocurrency Payment',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Payment method description that the customer will see on your checkout.',
                'default' => 'Pay with Bitcoin, Ethereum, USDT, Bitcoin Cash, or Litecoin',
                'desc_tip' => true,
            ),
            'enabled_cryptos' => array(
                'title' => 'Enabled Cryptocurrencies',
                'type' => 'multiselect',
                'options' => array(
                    'bitcoin' => 'Bitcoin (BTC)',
                    'ethereum' => 'Ethereum (ETH)',
                    'tether' => 'USDT (TRC20)',
                    'bitcoincash' => 'Bitcoin Cash (BCH)',
                    'litecoin' => 'Litecoin (LTC)',
                ),
                'default' => array( 'bitcoin', 'ethereum', 'tether', 'bitcoincash', 'litecoin' ),
                'desc_tip' => true,
            ),
            'bitcoin_address' => array(
                'title' => 'Bitcoin (BTC) Address',
                'type' => 'text',
                'description' => 'Enter your Bitcoin wallet address',
                'default' => '',
                'desc_tip' => true,
            ),
            'ethereum_address' => array(
                'title' => 'Ethereum (ETH) Address',
                'type' => 'text',
                'description' => 'Enter your Ethereum wallet address',
                'default' => '',
                'desc_tip' => true,
            ),
            'usdt_address' => array(
                'title' => 'USDT (TRC20) Address',
                'type' => 'text',
                'description' => 'Enter your Ethereum address (USDT uses Ethereum)',
                'default' => '',
                'desc_tip' => true,
            ),
            'bitcoincash_address' => array(
                'title' => 'Bitcoin Cash (BCH) Address',
                'type' => 'text',
                'description' => 'Enter your Bitcoin Cash wallet address',
                'default' => '',
                'desc_tip' => true,
            ),
            'litecoin_address' => array(
                'title' => 'Litecoin (LTC) Address',
                'type' => 'text',
                'description' => 'Enter your Litecoin wallet address',
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }
    
    public function payment_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_script( 'qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js' );
        }
    }
    
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        
        echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background: transparent;">';
        
        echo '<p class="form-row form-row-wide">';
        echo '<label>Select Cryptocurrency *</label>';
        echo '<select name="crypto_currency" id="crypto_currency" style="width:100%; padding: 8px;">';
        echo '<option value="">-- Select Cryptocurrency --</option>';
        
        $enabled = $this->get_option( 'enabled_cryptos', array() );
        $crypto_labels = array(
            'bitcoin' => 'Bitcoin (BTC)',
            'ethereum' => 'Ethereum (ETH)',
            'tether' => 'USDT (TRC20)',
            'bitcoincash' => 'Bitcoin Cash (BCH)',
            'litecoin' => 'Litecoin (LTC)',
        );
        
        foreach ( $enabled as $crypto ) {
            if ( isset( $crypto_labels[ $crypto ] ) ) {
                echo '<option value="' . esc_attr( $crypto ) . '">' . esc_html( $crypto_labels[ $crypto ] ) . '</option>';
            }
        }
        
        echo '</select>';
        echo '</p>';
        
        echo '<div id="crypto_rate_info" style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-radius: 5px; display: none;">';
        echo '<p id="crypto_rate_text"></p>';
        echo '<p id="crypto_amount_text" style="font-weight: bold; font-size: 1.1em;"></p>';
        echo '</div>';
        
        echo '<div id="qrcode" style="margin-top: 15px; text-align: center;"></div>';
        
        echo '</fieldset>';
        
        $this->display_rate_script();
    }
    
    private function display_rate_script() {
        global $woocommerce;
        $cart_total = $woocommerce->cart->get_cart_total();
        $cart_total = preg_replace( '/[^0-9.]/', '', $cart_total );
        
        ?>
        <script>
        jQuery(function($) {
            const cartTotal = <?php echo floatval( $cart_total ); ?>;
            
            $('#crypto_currency').on('change', function() {
                const selected = $(this).val();
                if (!selected) {
                    $('#crypto_rate_info').hide();
                    $('#qrcode').html('');
                    return;
                }
                
                fetch('https://api.coingecko.com/api/v3/simple/price?ids=' + selected + '&vs_currencies=usd')
                    .then(response => response.json())
                    .then(data => {
                        if (data[selected] && data[selected].usd) {
                            const rate = data[selected].usd;
                            const cryptoAmount = (cartTotal / rate).toFixed(8);
                            
                            $('#crypto_rate_text').text('Current ' + selected.toUpperCase() + ' rate: $' + rate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                            $('#crypto_amount_text').text('Amount to send: ' + cryptoAmount + ' ' + selected.toUpperCase());
                            $('#crypto_rate_info').show();
                            
                            generateQRCode(selected, cryptoAmount);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching rate:', error);
                        $('#crypto_rate_text').text('Error fetching exchange rate. Please try again.');
                    });
            });
            
            function generateQRCode(crypto, amount) {
                $('#qrcode').html('');
                const wallet = getWalletAddress(crypto);
                if (!wallet) return;
                
                const qrText = crypto.toLowerCase() + ':' + wallet + '?amount=' + amount;
                new QRCode(document.getElementById('qrcode'), {
                    text: qrText,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
            
            function getWalletAddress(crypto) {
                const walletMap = <?php echo json_encode( $this->parse_wallet_addresses() ); ?>;
                return walletMap[crypto] || '';
            }
        });
        </script>
        <?php
    }
    
    private function parse_wallet_addresses() {
        $addresses = array();
        
        if ( $this->get_option( 'bitcoin_address' ) ) {
            $addresses['bitcoin'] = $this->get_option( 'bitcoin_address' );
        }
        if ( $this->get_option( 'ethereum_address' ) ) {
            $addresses['ethereum'] = $this->get_option( 'ethereum_address' );
        }
        if ( $this->get_option( 'usdt_address' ) ) {
            $addresses['tether'] = $this->get_option( 'usdt_address' );
        }
        if ( $this->get_option( 'bitcoincash_address' ) ) {
            $addresses['bitcoincash'] = $this->get_option( 'bitcoincash_address' );
        }
        if ( $this->get_option( 'litecoin_address' ) ) {
            $addresses['litecoin'] = $this->get_option( 'litecoin_address' );
        }
        
        return $addresses;
    }
    
    public function validate_fields() {
        if ( empty( $_POST['crypto_currency'] ) ) {
            wc_add_notice( 'Please select a cryptocurrency', 'error' );
            return false;
        }
        return true;
    }
    
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $crypto = sanitize_text_field( $_POST['crypto_currency'] );
        $wallet_addresses = $this->parse_wallet_addresses();
        
        if ( ! isset( $wallet_addresses[ $crypto ] ) ) {
            wc_add_notice( 'Wallet address not configured for selected cryptocurrency', 'error' );
            return array( 'result' => 'failure' );
        }
        
        $response = wp_remote_get( 'https://api.coingecko.com/api/v3/simple/price?ids=' . $crypto . '&vs_currencies=usd' );
        
        if ( is_wp_error( $response ) ) {
            wc_add_notice( 'Error fetching cryptocurrency rate', 'error' );
            return array( 'result' => 'failure' );
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body[ $crypto ]['usd'] ) ) {
            wc_add_notice( 'Unable to calculate cryptocurrency amount', 'error' );
            return array( 'result' => 'failure' );
        }
        
        $rate = $body[ $crypto ]['usd'];
        $order_total = $order->get_total();
        $crypto_amount = $order_total / $rate;
        $wallet = $wallet_addresses[ $crypto ];
        
        $order->update_meta_data( '_crypto_currency', $crypto );
        $order->update_meta_data( '_crypto_amount', $crypto_amount );
        $order->update_meta_data( '_crypto_wallet', $wallet );
        $order->update_meta_data( '_crypto_rate_usd', $rate );
        $order->set_status( 'on-hold' );
        $order->add_order_note( sprintf( 'Awaiting payment of %s %s to %s', number_format( $crypto_amount, 8 ), strtoupper( $crypto ), $wallet ) );
        $order->save();
        
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
        }
    }
}

// Register Gateway
add_filter( 'woocommerce_payment_gateways', 'woo_crypto_add_gateway' );

function woo_crypto_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Crypto';
    return $gateways;
}

// Hook to load class at the right time
add_action( 'plugins_loaded', 'woo_crypto_define_gateway_class', 15 );

// Transaction Monitor
add_action( 'wp_loaded', 'woo_crypto_check_transactions' );

function woo_crypto_check_transactions() {
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
        return;
    }
    
    $last_check = get_transient( 'woo_crypto_last_check' );
    if ( $last_check ) {
        return;
    }
    
    set_transient( 'woo_crypto_last_check', true, 5 * MINUTE_IN_SECONDS );
    
    $args = array(
        'status' => 'on-hold',
        'meta_query' => array(
            array(
                'key' => '_crypto_currency',
                'compare' => 'EXISTS',
            ),
        ),
    );
    
    $orders = wc_get_orders( $args );
    
    foreach ( $orders as $order ) {
        woo_crypto_check_order_payment( $order );
    }
}

function woo_crypto_check_order_payment( $order ) {
    $crypto = $order->get_meta( '_crypto_currency' );
    $amount = $order->get_meta( '_crypto_amount' );
    $wallet = $order->get_meta( '_crypto_wallet' );
    
    if ( ! $crypto || ! $amount || ! $wallet ) {
        return;
    }
    
    $tx_data = woo_crypto_check_wallet_balance( $crypto, $wallet, $amount );
    
    if ( $tx_data && isset( $tx_data['confirmed'] ) && $tx_data['confirmed'] ) {
        $order->update_meta_data( '_crypto_tx_hash', $tx_data['tx_hash'] );
        $order->update_meta_data( '_crypto_confirmations', $tx_data['confirmations'] );
        $order->payment_complete( $tx_data['tx_hash'] );
        $order->add_order_note( sprintf( 
            'Cryptocurrency payment received: %s %s (TX: %s, %d confirmations)', 
            number_format( $amount, 8 ),
            strtoupper( $crypto ),
            $tx_data['tx_hash'],
            $tx_data['confirmations']
        ) );
        $order->save();
    } else if ( $tx_data ) {
        $order->update_meta_data( '_crypto_tx_hash', $tx_data['tx_hash'] );
        $order->update_meta_data( '_crypto_confirmations', $tx_data['confirmations'] );
        $order->add_order_note( sprintf( 
            'Cryptocurrency payment detected: %d confirmation(s), awaiting confirmation', 
            $tx_data['confirmations']
        ) );
        $order->save();
    }
    
    $order->update_meta_data( '_crypto_tx_checked', current_time( 'mysql' ) );
    $order->save();
}

function woo_crypto_check_wallet_balance( $crypto, $wallet, $expected_amount ) {
    $explorer_url = woo_crypto_get_explorer_url( $crypto, $wallet );
    
    if ( ! $explorer_url ) {
        return false;
    }
    
    $response = wp_remote_get( $explorer_url, array( 'timeout' => 10 ) );
    
    if ( is_wp_error( $response ) ) {
        return false;
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    return woo_crypto_parse_explorer_response( $crypto, $body, $expected_amount );
}

function woo_crypto_get_explorer_url( $crypto, $wallet ) {
    $urls = array(
        'bitcoin' => 'https://blockstream.info/api/address/' . $wallet,
        'ethereum' => 'https://api.etherscan.io/api?module=account&action=txlist&address=' . $wallet . '&sort=desc&apikey=YourEtherscanAPIKey',
        'tether' => 'https://api.etherscan.io/api?module=account&action=tokentx&contractaddress=0xdac17f958d2ee523a2206206994597c13d831ec7&address=' . $wallet . '&sort=desc&apikey=YourEtherscanAPIKey',
        'bitcoincash' => 'https://blockstream.info/api/address/' . $wallet,
        'litecoin' => 'https://blockstream.info/litecoin/api/address/' . $wallet,
    );
    
    return isset( $urls[ $crypto ] ) ? $urls[ $crypto ] : false;
}

function woo_crypto_parse_explorer_response( $crypto, $data, $expected_amount ) {
    if ( ! $data ) {
        return false;
    }
    
    switch ( $crypto ) {
        case 'bitcoin':
        case 'litecoin':
            return woo_crypto_parse_blockstream( $data, $expected_amount );
        
        case 'bitcoincash':
            return woo_crypto_parse_bitcoincash( $data, $expected_amount );
        
        case 'ethereum':
            return woo_crypto_parse_etherscan( $data, $expected_amount );
        
        case 'tether':
            return woo_crypto_parse_usdt_trc20( $data, $expected_amount );
        
        default:
            return false;
    }
}

function woo_crypto_parse_blockstream( $data, $expected_amount ) {
    if ( ! isset( $data['txs'] ) || empty( $data['txs'] ) ) {
        return false;
    }
    
    $tolerance = $expected_amount * 0.02;
    
    foreach ( $data['txs'] as $tx ) {
        foreach ( $tx['vout'] as $vout ) {
            $received = isset( $vout['value'] ) ? $vout['value'] / 100000000 : 0;
            
            if ( abs( $received - $expected_amount ) <= $tolerance ) {
                $confirmations = isset( $tx['status']['confirmed'] ) && $tx['status']['confirmed'] ? 
                    max( 1, $tx['status']['block_height'] ? 1 : 0 ) : 0;
                
                return array(
                    'confirmed' => $confirmations >= 1,
                    'confirmations' => $confirmations,
                    'tx_hash' => $tx['txid'],
                    'amount' => $received,
                );
            }
        }
    }
    
    return false;
}

function woo_crypto_parse_etherscan( $data, $expected_amount ) {
    if ( ! isset( $data['result'] ) || ! is_array( $data['result'] ) ) {
        return false;
    }
    
    foreach ( $data['result'] as $tx ) {
        if ( $tx['isError'] == 0 && $tx['txreceipt_status'] == 1 ) {
            $amount = intval( $tx['value'] ) / 1e18;
            $confirmations = max( 0, intval( $tx['confirmations'] ) );
            
            if ( $confirmations >= 1 ) {
                return array(
                    'confirmed' => true,
                    'confirmations' => $confirmations,
                    'tx_hash' => $tx['hash'],
                    'amount' => $amount,
                );
            }
        }
    }
    
    return false;
}

function woo_crypto_parse_bitcoincash( $data, $expected_amount ) {
    if ( ! isset( $data['txs'] ) || empty( $data['txs'] ) ) {
        return false;
    }
    
    $tolerance = $expected_amount * 0.02;
    
    foreach ( $data['txs'] as $tx ) {
        foreach ( $tx['vout'] as $vout ) {
            $received = isset( $vout['value'] ) ? $vout['value'] / 100000000 : 0;
            
            if ( abs( $received - $expected_amount ) <= $tolerance ) {
                $confirmations = isset( $tx['status']['confirmed'] ) && $tx['status']['confirmed'] ? 
                    max( 1, $tx['status']['block_height'] ? 1 : 0 ) : 0;
                
                if ( $confirmations >= 1 ) {
                    return array(
                        'confirmed' => true,
                        'confirmations' => $confirmations,
                        'tx_hash' => $tx['txid'],
                        'amount' => $received,
                    );
                }
            }
        }
    }
    
    return false;
}

function woo_crypto_parse_usdt_trc20( $data, $expected_amount ) {
    if ( ! isset( $data['result'] ) || ! is_array( $data['result'] ) ) {
        return false;
    }
    
    $tolerance = $expected_amount * 0.02;
    
    foreach ( $data['result'] as $tx ) {
        if ( $tx['isError'] == 0 && $tx['txreceipt_status'] == 1 ) {
            $amount = intval( $tx['value'] ) / 1e6;
            $confirmations = max( 0, intval( $tx['confirmations'] ) );

            if ( abs( $amount - $expected_amount ) <= $tolerance && $confirmations >= 1 ) {
                return array(
                    'confirmed' => true,
                    'confirmations' => $confirmations,
                    'tx_hash' => $tx['hash'],
                    'amount' => $amount,
                );
            }
        }
    }
    
    return false;
}

// Thank You Page
add_action( 'woocommerce_thankyou_crypto_payment', 'woo_crypto_thankyou_page' );

function woo_crypto_thankyou_page( $order_id ) {
    $order = wc_get_order( $order_id );
    
    if ( $order->get_status() === 'on-hold' ) {
        $crypto = $order->get_meta( '_crypto_currency' );
        $amount = $order->get_meta( '_crypto_amount' );
        $wallet = $order->get_meta( '_crypto_wallet' );
        $rate = $order->get_meta( '_crypto_rate_usd' );
        
        wp_enqueue_script( 'qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js' );
        
        echo '<div style="margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; background: #f9f9f9;">';
        echo '<h5>Payment Instructions</h5>';
        echo '<p><strong>Cryptocurrency:</strong> ' . esc_html( strtoupper( $crypto ) ) . '</p>';
        echo '<p><strong>Amount to Send:</strong> <code style="background: #fff; padding: 8px; display: inline-block; border-radius: 3px; font-family: monospace;">' . esc_html( number_format( $amount, 8 ) ) . '</code></p>';
        echo '<p><strong>Exchange Rate:</strong> 1 ' . esc_html( strtoupper( $crypto ) ) . ' = $' . esc_html( number_format( $rate, 2 ) ) . '</p>';
        echo '<p><strong>Send to Address:</strong> <code style="background: #fff; padding: 8px; display: block; border-radius: 3px; font-family: monospace; word-break: break-all; margin-top: 5px;">' . esc_html( $wallet ) . '</code></p>';

        echo '<div style="margin-top: 20px; text-align: center;">';
        echo '<p style="margin-bottom: 10px;"><strong>Scan QR Code:</strong></p>';
        echo '<div id="qrcode-thankyou" style="display: inline-block; margin-bottom: 15px;"></div>';
        echo '<p style="background: #fff; padding: 10px; border-radius: 3px; font-family: monospace; word-break: break-all; font-size: 0.9em; margin: 0;">' . esc_html( $wallet ) . '</p>';
        echo '</div>';
        
        echo '<p style="color: #666; font-size: 0.9em; margin-top: 15px;">Your order will be marked complete once payment is received and confirmed on the blockchain.</p>';
        echo '</div>';

        woo_crypto_generate_thankyou_qr( $crypto, $wallet, $amount );
    }
}

function woo_crypto_generate_thankyou_qr( $crypto, $wallet, $amount ) {
    $qr_text = $crypto . ':' . $wallet . '?amount=' . $amount;
    ?>
    <script>
    jQuery(function($) {
        new QRCode(document.getElementById('qrcode-thankyou'), {
            text: '<?php echo esc_js( $qr_text ); ?>',
            width: 250,
            height: 250,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    });
    </script>
    <?php
}

// Manual check handler
add_action( 'wp_ajax_woo_crypto_manual_check', 'woo_crypto_manual_check_handler' );

function woo_crypto_manual_check_handler() {
    $order_id = intval( $_POST['order_id'] ?? 0 );
    
    if ( ! $order_id ) {
        wp_send_json_error( 'Invalid order ID' );
    }
    
    $order = wc_get_order( $order_id );
    
    if ( ! $order ) {
        wp_send_json_error( 'Order not found' );
    }
    
    if ( ! current_user_can( 'view_order', $order_id ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    
    woo_crypto_check_order_payment( $order );
    $order = wc_get_order( $order_id );
    
    wp_send_json_success( array(
        'status' => $order->get_status(),
        'message' => $order->get_status() === 'completed' ? 'Payment confirmed!' : 'Payment still pending. Checking blockchain...'
    ) );
}

// Add manual check button
add_filter( 'woocommerce_order_received_text', 'woo_crypto_order_received_text', 10, 2 );

function woo_crypto_order_received_text( $text, $order ) {
    if ( $order->get_payment_method() === 'crypto_payment' && $order->get_status() === 'on-hold' ) {
        $text .= ' <button id="check-payment-btn" class="button" style="margin-top: 10px;">Check Payment Status</button>';
        woo_crypto_add_manual_check_script( $order->get_id() );
    }
    
    return $text;
}

function woo_crypto_add_manual_check_script( $order_id ) {
    ?>
    <script>
    jQuery(function($) {
        $('#check-payment-btn').on('click', function() {
            $(this).prop('disabled', true).text('Checking...');

            $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                action: 'woo_crypto_manual_check',
                order_id: <?php echo intval( $order_id ); ?>
            }, function(response) {
                if (response.success) {
                    $('#check-payment-btn').prop('disabled', false).text(response.data.message);
                    if (response.data.status === 'completed') {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $('#check-payment-btn').prop('disabled', false).text('Check Payment Status');
                    alert('Error: ' + response.data);
                }
            });
        });
    });
    </script>
    <?php
}

?>
