# WooCommerce Multi-Crypto Payment Gateway

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0%2B-purple.svg)](https://woocommerce.com)

A comprehensive WordPress plugin that extends WooCommerce functionality to accept multiple cryptocurrencies as payment methods. Enable your store to receive payments in Bitcoin, Ethereum, USDT, Bitcoin Cash, and Litecoin with real-time exchange rate calculations and automatic payment verification.

## ✨ Features

- **Multi-Currency Support** — Accept payments in BTC, ETH, USDT, BCH, and LTC with per-merchant enable/disable controls
- **Real-Time Exchange Rates** — Integrates with CoinGecko API for accurate USD conversion calculations
- **Dynamic QR Codes** — Generates scannable QR codes at checkout and thank you pages using QRCode.js
- **Automatic Payment Detection** — Monitors blockchain transactions via explorer APIs and auto-updates order status
- **Multiple Blockchain Explorers** — Built-in support for Blockstream (BTC/LTC), Etherscan (ETH/USDT)
- **Complete Order Metadata** — Stores transaction details including currency, amount, wallet address, exchange rate, hash, and confirmations
- **Manual Verification** — Customers can trigger blockchain checks without waiting for automatic detection
- **Admin Configuration Panel** — Streamlined WooCommerce settings interface for wallet management and coin selection

## 📋 Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.0+
- External APIs: CoinGecko, Blockstream, Etherscan

## 🚀 Quick Start

### Installation

1. Clone this repository into your WordPress plugins directory:
   ```bash
   git clone https://github.com/f3stuso/woocommerce-multi-crypto-gateway.git wp-content/plugins/woocommerce-multi-crypto-gateway
   ```

2. Navigate to your WordPress admin panel
3. Go to **Plugins** and find "WooCommerce Multi-Crypto Payment Gateway"
4. Click **Activate**

### Configuration

#### Wallet Setup

1. Go to **WooCommerce → Settings → Payments**
2. Click on "Cryptocurrency Payment" to configure
3. Enter wallet addresses for each cryptocurrency:
   - Bitcoin (BTC) address
   - Ethereum (ETH) address
   - USDT (TRC20) Ethereum address
   - Bitcoin Cash (BCH) address
   - Litecoin (LTC) address

#### Enable Cryptocurrencies

From the settings page, use the "Enabled Cryptocurrencies" multiselect to choose which coins your store accepts. Enable any combination of the five supported currencies.

#### API Configuration

**CoinGecko** — Public endpoint, no API key required

**Blockstream** — Public endpoint for Bitcoin, Bitcoin Cash, and Litecoin

**Etherscan** — Currently uses a placeholder key. Update with your own key in `woo_crypto_get_explorer_url()` for production use

## 💱 How It Works

### Customer Checkout Flow

```
1. Select cryptocurrency payment method
   ↓
2. Plugin fetches current exchange rate from CoinGecko
   ↓
3. Calculate exact crypto amount needed
   ↓
4. Display rate, amount, and QR code
   ↓
5. Customer scans QR code and sends payment
```

### Payment Verification Flow

```
1. Order set to "on-hold" status
   ↓
2. Background job checks blockchain every 5 minutes
   ↓
3. Transaction detected with ≥1 confirmation
   ↓
4. Order status updates to "completed"
   ↓
5. Order notes updated with transaction details
```

### Transaction Matching

The plugin uses a 2% tolerance on payment amounts to account for exchange rate slippage, allowing transactions within reasonable variance to be confirmed as valid.

## 🏗️ Architecture

| Component | Purpose |
|-----------|---------|
| `WC_Gateway_Crypto` | Main payment gateway class extending WooCommerce base |
| Transaction Monitoring | Runs via `wp_loaded` hook with transient-based rate limiting |
| Explorer Integration | Modular parsers for each blockchain's API format |
| Payment Processing | Handles form validation, checkout UI, and payment logic |

## 📁 Project Structure

```
woocommerce-multi-crypto-gateway/
├── woocommerce-multi-crypto-gateway.php    # Main plugin file (~650 lines)
├── README.md
├── LICENSE
├── src/
│   ├── payment-gateway.php                 # WC_Gateway_Crypto class
│   ├── transaction-monitor.php             # Background verification
│   └── explorers.php                       # Blockchain API parsers
└── assets/
    └── js/
        └── checkout.js                     # QR code & frontend logic
```

## ⚡ Performance

- **Rate Limiting** — Transaction checks throttled to every 5 minutes using WordPress transients
- **Client-Side QR Generation** — QRCode.js runs on browser to reduce server load
- **Lazy API Calls** — Exchange rate requests only on currency selector interaction

## 🔒 Security

- All user input sanitized with WordPress functions (`sanitize_text_field()`)
- Output properly escaped (`esc_html()`, `esc_attr()`) to prevent XSS
- AJAX endpoints include user capability checks
- Nonce-less AJAX for read-only operations

⚠️ **Important**: The Etherscan API key is a placeholder. Replace with a legitimate key before production deployment. Verify all wallet addresses before saving to prevent typos.

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Payments not detected | Verify wallet addresses match blockchain exactly. Check API keys and endpoint accessibility |
| QR code not displaying | Ensure QRCode.js CDN is accessible. Check browser console for JavaScript errors |
| Exchange rate errors | Verify CoinGecko API is reachable. Check cryptocurrency names match API identifiers |
| High API usage | Increase transaction check interval or implement webhook-based verification |

## 🚧 Planned Enhancements

- [ ] Support for additional cryptocurrencies (Dogecoin, XRP, Monero)
- [ ] Webhook-based payment verification for faster confirmation
- [ ] Customer transaction history dashboard
- [ ] Multi-wallet support with hot/cold wallet rotation
- [ ] Stablecoin preference option to reduce volatility
- [ ] Email notifications for payment confirmations
- [ ] Configurable confirmation requirements per cryptocurrency
- [ ] Payment timeout handling and partial payment logic

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## 📝 License

This project is licensed under the GNU General Public License v2 or later. See [LICENSE](LICENSE) file for details.

## ⚠️ Disclaimer

This plugin is suitable for accepting cryptocurrency payments but should be thoroughly tested in a staging environment before production deployment. Consider implementing additional security measures such as webhook verification and multi-signature wallets for high-value transactions.

---

**Author:** Festus Okonye

For issues, feature requests, or questions, please open an [issue](../../issues) on GitHub.
