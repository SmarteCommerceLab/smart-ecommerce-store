=== Smart eCommerce Store ===
Contributors: deradrea
Tags: plugins, store, ecommerce, installer, catalog
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A curated catalog for discovering Smart eCommerce plugins published on WordPress.org and Freemius.

== Description ==

Smart eCommerce Store presents only products explicitly approved for public distribution.

Free plugins are installed through the official WordPress.org plugin service. Premium purchase buttons open the Freemius checkout. Internal, development and retired products are never displayed.

= External services =

The plugin retrieves a signed public product catalog from repository.smartecommerce.it when an administrator opens or refreshes the Store. The request sends standard web request data such as the site IP address and user agent. No WordPress user data is included by the plugin. Service information: https://smartecommerce.it/privacy-policy/

Free plugin installation uses the WordPress.org Plugins API and download service. WordPress.org privacy policy: https://wordpress.org/about/privacy/

Premium purchase buttons open checkout.freemius.com only after an administrator clicks the button. A customer who already owns a license can submit it explicitly to repository.smartecommerce.it; the service forwards the activation to Freemius and, only after authorization, returns the current Premium package. The Store does not retain the license key. It stores an opaque, site-bound entitlement receipt and periodically sends that receipt, the site URL and a random site identifier to repository.smartecommerce.it to display license status and expiration. Freemius privacy policy: https://freemius.com/privacy/

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New.
2. Activate Smart eCommerce Store.
3. Open Smart Store in the WordPress administration menu.

== Frequently Asked Questions ==

= Does the Store show internal Smart eCommerce products? =

No. Products must be explicitly marked public or commercial in the signed catalog.

= Are premium files publicly downloaded? =

No. A Premium package is returned only after Freemius authorizes the submitted license for the current site.

== Changelog ==

= 0.2.1 =
* Show Premium license status, expiration, authorized site, renewal and customer portal actions.
* Keep the customer license key out of WordPress storage by using an encrypted site-bound entitlement receipt.

= 0.2.0 =
* Add licensed Premium verification and guided installation through the protected Smart eCommerce endpoint.

= 0.1.1 =
* Add the active repository catalog public key.

= 0.1.0 =
* Initial public catalog, WordPress.org installation and Freemius checkout flow.
