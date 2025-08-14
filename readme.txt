=== Live Rates for ShipStation ===
Contributors: iqcomputing
Tags: woocommerce, shipstation, usps, ups, fedex
Requires at least: 5.9
Tested up to: 6.8
Stable tag: 1.0.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Pulls live shipping rates from your favorite carriers connected to 3rd party provider ShipStation.

== Description ==

**ShipStation** is a 3rd party provider helping WooCommerce store owners compare shipping carrier rates, automate shipping processes, print labels, sync order data, and group tracking information, among other features.

This plugin connects to the ShipStation API using an authentication key to display shipping rates from various common carriers supported by ShipStation. This allows store owners to group all their shipping carriers under one umbrella which makes management easier and allows customers to choose the best shipping method for them which leads to happier customers.

In order to use the Live Rates for ShipStation plugin, you must have a [premium ShipStation account](https://www.shipstation.com/pricing/), and purchased the [ShipStation for WooCommerce](https://woocommerce.com/products/shipstation-integration/) plugin. This plugin **will not work** without access to the ShipStation API which is tied to your premium ShipStation account.

Please review [ShipStations Terms of Service](https://www.shipstation.com/terms-of-service/) and [ShipStations Privacy Policy](https://auctane.com/legal/privacy-policy/) for more information about how your data is managed.

== Plugin Requirements ==

1. [A Premium ShipStation Account](https://www.shipstation.com/pricing/)
1. [The WooCommerce Plugin](https://wordpress.org/plugins/woocommerce/)
1. [The ShipStation for WooCommerce Plugin](https://woocommerce.com/products/shipstation-integration/)
1. The Live Rates for ShipStation Plugin

== IQComputing ==

* Like us on [Facebook](https://www.facebook.com/iqcomputing)
* Follow us on [Twitter](https://twitter.com/iqcomputing/)
* Fork on [Github](https://github.com/IQComputing/live-rates-for-shipstation)

== Installation ==

[ShipStation for WooCommerce](https://woocommerce.com/products/shipstation-integration/) is a required plugin.
[WooCommerce](https://wordpress.org/plugins/woocommerce/) is a required plugin.

1. Ensure that the WooCommerce plugin is installed and active.
1. Ensure that the ShipStation for WooCommerce plugin is installed and active.
1. Install this (Live Rates for ShipStation) plugin either manually or via the WordPress Plugin Repository.
1. Navigate to WooCommerce > Settings > Integration > ShipStation and enter your ShipStation REST V2 API Key. Please refer to their documentation to [Access the ShipStation API](https://help.shipstation.com/hc/en-us/articles/360025856212-ShipStation-API).
1. Once the API Key has been entered and verified, you may select which carriers you would like to support using the Shipping Carriers select box.
1. Once you have your carriers selected you can navigate to WooCommerce > Settings > Shipping to setup your Shipping Zones.
1. Select a zone and click the Add Shipping Method button at the bottom to add "ShipStation Live Rates".
1. From here you can setup custom boxes and select which services from the previously selected carriers you would like to make available for the selected shipping zone.

== Changelog ==

= 1.0.3 (2025-08-05) =
* Patches an issue with Shipping Method availability (Thanks to @sportswreathshop for reporting!)

= 1.0.2 (2025-08-04) =
* Refines API caching that clears on settings save (thanks again to @dpkonofa for test/reporting!).

= 1.0.1 (2025-08-01) =
* Patches an issue with Individual Shipping Requests (thanks @dpkonofa !).
* Attempt to discern ShipStation Carriers from Manually Connected Carriers.

= 1.0.0 (2025-07-28) =
* Initial release