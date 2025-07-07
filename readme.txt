=== Live Rates for ShipStation ===
Contributors: iqcomputing
Tags: woocommerce, shipstation, usps, ups, fedex
Requires at least: 4.9
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pulls live shipping rates from your carriers connected to ShipStation.

== Description ==

This plugin requires both the Premium ShipStation Plugin and the Free WooCommerce Plugin to work properly.

ShipStation is a 3rd party carrier system which allows you to connect multiple shipping methods to your store.

This plugin uses the ShipStation API to pull in your carriers shipping rates directly to your WooCommerce storefront.

== IQComputing ==

* Like us on [Facebook](https://www.facebook.com/iqcomputing/ "IQComputing on Facebook")
* Follow us on [Twitter](https://twitter.com/iqcomputing/ "IQComputing on Twitter")
* Fork on [Github](https://github.com/IQComputing/wpcf7-recaptcha "IQComputing on Github")

== Installation ==

[ShipStation for WooCommerce](https://woocommerce.com/products/shipstation-integration/) "ShipStation for WooCommerce plugin page") is a required premium plugin.
[WooCommerce](https://wordpress.org/plugins/woocommerce/ "WooCommerce plugin page") is a required free plugin.

1. Ensure that the WooCommerce plugin is installed and active.
1. Ensure that the ShipStation for WooCommerce plugin is installed and active.
1. Install this (Live Rates for ShipStation) plugin either manually or via the WordPress Plugin Repository.
1. Navigate to WooCommerce > Settings > Integration > ShipStation and enter your ShipStation REST V2 API Key. Please refer to their documentation to [Access the ShipStation API](https://help.shipstation.com/hc/en-us/articles/360025856212-ShipStation-API "External Link to ShipStation Documentation").
1. Once the API Key has been entered and verified, you may select which carriers you would like to support using the Shipping Carriers select box.
1. Once you have your carriers selected you can navigate to WooCommerce > Settings > Shipping to setup your Shipping Zones.
1. Select a zone and click the Add Shipping Method button at the bottom to add "ShipStation Live Rates".
1. From here you can setup custom boxes and select which services from the previously selected carriers you would like to make available for the selected shipping zone.

== Changelog ==

= 1.0.0 (0000-00-00) =
* Initial release