# Payabbhi Payments - WooCommerce Integration

This extension builds on top of Payabbhi PHP Library to provide seamless integration of [Payabbhi Checkout ](https://payabbhi.com/docs/checkout) with WooCommerce.

The plugin is compatible with WooCommerce v2.4 onwards (which includes v3.x).

### Installation
Make sure you have signed up for your [Payabbhi Account](https://payabbhi.com/docs/account) and downloaded the [API keys](https://payabbhi.com/docs/account/#api-keys) from the [Portal](https://payabbhi.com/portal).


1. Download [payabbhi-woocommerce-VERSION.zip](https://github.com/payabbhi/payabbhi-woocommerce/releases).
2. Navigate to `WordPress Dashboard` -> `Plugins` and click on `Add New`.
3. Click on `Upload Plugin`.
4. Browse for `payabbhi-woocommerce-VERSION.zip` and click on `Install Now`.
5. Click on `Activate plugin` to activate the plugin..

### Configuration

1. Navigate to `WooCommerce -> settings` page, and click on the `Checkout` tab.
2. Click on `Payabbhi` under `Checkout options` to edit the settings. If you do not see Payabbhi there, make sure the plugin is activated and check again.
3. Make sure the checkbox titled `Enable Payabbhi Payment` is checked.
4. Configure `Payabbhi` and save the settings:
  - [Access ID](https://payabbhi.com/docs/account/#api-keys)
  - [Secret Key](https://payabbhi.com/docs/account/#api-keys)
  - [payment_auto_capture](https://payabbhi.com/docs/api/#create-an-order)


[Payabbhi Checkout](https://payabbhi.com/docs/checkout) is now enabled in WooCommerce.
