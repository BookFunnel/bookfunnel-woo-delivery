=== BookFunnel ===
Contributors: bookfunnel
Tags: bookfunnel, ebook, audiobook, digital delivery, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1
WC requires at least: 8.0
WC tested up to: 11.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 
Sell ebooks and audiobooks on your WooCommerce store and let BookFunnel handle the delivery.
 
== Description ==
 
Sell your ebooks and audiobooks on your WooCommerce store and BookFunnel will automatically email your buyers a unique download link after purchase, plus show a direct download button right on the order confirmation page. If a reader has any trouble downloading their book, BookFunnel's support team is always happy to help.
 
The source code for this plugin is publicly available at https://github.com/bookfunnel/bookfunnel-woo-delivery
 
**How Does It Work?**
 
1. Install and connect the BookFunnel plugin
2. Create a delivery action on BookFunnel for each book you want to deliver
3. When a reader completes a purchase, BookFunnel emails them a unique link to download their book
 
That's it! BookFunnel handles all delivery emails and reader support, so you can focus on writing.
 
**What Gets Delivered?**
 
BookFunnel matches purchased products to your delivery actions using the product SKU. Any product in your WooCommerce store with a matching SKU in BookFunnel will be delivered automatically. Products without a matching SKU are ignored — perfect for stores that sell both digital and physical items.
 
**Features**
 
* Automatic delivery of ebooks and audiobooks after purchase
* Supports free and paid orders
* Supports mixed orders with both digital and physical products
* Full refunds and cancellations automatically revoke reader access; partial refunds don't revoke access by default, so courtesy discounts won't lock readers out of their books (configurable)
* Delivery log in your WooCommerce admin so you can see exactly what was sent
* Automatic retry if a delivery notification fails, with email alerts if a delivery is delayed
* One-click log export if you ever need to loop in BookFunnel support
* A "Your order is ready" block with a direct download button on the thank-you page and order confirmation email
 
**Requirements**
 
* A BookFunnel account on the Mid List Author plan or above
* A self-hosted WordPress site or a WordPress.com plan that supports plugins
 
**Getting Started**
 
1. Install and activate the plugin
2. Go to **WooCommerce → BookFunnel → Settings** and click **Connect to BookFunnel**
3. Log in to your BookFunnel account and authorize the connection
4. In your BookFunnel dashboard, go to **Sales → WooCommerce** and create a delivery action for each book, using the product SKU from your WooCommerce store
5. That's it — your next sale will trigger an automatic delivery!
 
For full setup instructions, visit [authors.bookfunnel.com/help/setup-woocommerce](https://authors.bookfunnel.com/help/setup-woocommerce/).
 
== Installation ==
 
1. Upload the plugin files to the `/wp-content/plugins/bookfunnel-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **WooCommerce → BookFunnel** and click **Connect to BookFunnel** to link your BookFunnel account.
 
== Frequently Asked Questions ==
 
= Do I need a BookFunnel account? =
 
Yes. BookFunnel for WooCommerce requires a BookFunnel account on the Mid List Author plan or above. You can sign up at [bookfunnel.com](https://bookfunnel.com).
 
= How does BookFunnel know which products to deliver? =
 
BookFunnel matches products using the SKU you set in WooCommerce. You create a delivery action in your BookFunnel dashboard with the same SKU, and BookFunnel automatically delivers the matching book when that product is purchased.
 
= What happens if my store sells both physical and digital products? =
 
No problem! BookFunnel only delivers products with a matching SKU in your BookFunnel account. Physical products or any product without a matching delivery action are ignored.
 
= Does BookFunnel deliver free books? =
 
Yes, as long as your BookFunnel account is on the Mid List Author plan or above.
 
= What happens if a delivery fails? =
 
The plugin will automatically retry failed deliveries on a schedule: 1 hour, 4 hours, 12 hours, 24 hours, and 48 hours after the initial failure. You will receive an email alert if a delivery has not been confirmed after 24 hours. You can also manually resend any delivery from the **Deliveries** tab in your BookFunnel settings.
 
= What happens when I issue a refund? =
 
It depends on whether the refund is full or partial. A full refund automatically revokes the reader's access to their download link. A partial refund does not revoke access by default — this is so you can issue a courtesy discount without cutting off a reader who's keeping their book. If you'd rather have any refund, partial or full, revoke access, you can turn that on in the plugin's Settings tab under **Revoke BookFunnel access when an order is partially refunded**.
 
= How do I restore a reader's access after a refund? =
 
You can restore access from your BookFunnel Dashboard using bulk delivery.
 
= I previously connected WooCommerce to BookFunnel manually. Do I need to do anything? =
 
Yes. If you previously set up a WooCommerce integration in your BookFunnel dashboard without using this plugin, you should remove that legacy connection before using the plugin. Having both active at the same time will cause readers to receive duplicate delivery emails. To remove the legacy connection, go to your BookFunnel dashboard, navigate to Sales → Delivery Settings → WooCommerce, and disconnect the existing integration. Then connect the plugin using the Connect to BookFunnel button in your WooCommerce settings.
 
= Can I see a record of what was delivered? =
 
Yes. The **Deliveries** tab in **WooCommerce → BookFunnel** shows a full log of all delivery notifications, including their status and the number of attempts.
 
= What if I need help from BookFunnel support? =
 
The **Logs** tab in **WooCommerce → BookFunnel** lets you filter your logs, copy them with one click, and open a pre-addressed email to BookFunnel support — so you can just paste and send. You can also always reach us directly at support@bookfunnel.com.
 
= Where do readers go if they need help downloading their book? =
 
BookFunnel handles all reader support. Readers can visit [bookfunnel.com/help](https://bookfunnel.com/help) for step-by-step download instructions and live support.
 
== External Services ==
 
This plugin connects to the BookFunnel API to deliver ebooks and audiobooks to readers after purchase, and to authenticate the connection between your WooCommerce store and your BookFunnel account.
 
**What data is sent and when:**
 
* When an order is completed, cancelled, or refunded, the plugin sends order data (order ID, line items, SKUs, customer email, and billing information) to BookFunnel's webhook service at `https://webhooks.bookfunnel.com`.
* During the connect flow, the plugin sends your store URL to BookFunnel's dashboard at `https://dashboard.bookfunnel.com` to authenticate the connection.
* A ping is sent to BookFunnel after connecting to verify the connection is working.
 
No data is sent unless the plugin is connected to a BookFunnel account.
 
This service is provided by BookFunnel: [Terms of Service](https://bookfunnel.com/terms/), [Privacy Policy](https://bookfunnel.com/privacy/).
 
 
 
== Screenshots ==
 
1. The Settings tab — connect your BookFunnel account to get started.
2. The Settings tab — connected state with delivery action, email notification, and partial refund settings.
3. The Deliveries tab — view and manage all delivery notifications.
4. The order thank-you page showing the BookFunnel delivery link block.
5. The Logs tab — filter, copy, and email support logs directly to BookFunnel.
 
== Changelog ==
 
2026-08-27 - version 1.1
* Add - Order-specific delivery link block on the thank-you page and order confirmation email, replacing the generic purchase link
* Add - Partial refunds no longer revoke reader access by default (new setting to opt back into the old behavior)
* Fix - Delivery confirmation block now renders higher on the thank-you page
* Fix - Remove Webhook UID from the Settings page display (no longer needed by any supported integration)
* Add - Helper text on the Settings page explaining SKUs and delivery actions
* Add - Admin notice prompting reconnection when a store connected before direct download links were supported
 
 
2026-05-05 - version 1.0.1
* Fix - Move inline CSS to enqueued stylesheet
* Fix - Use wp_upload_dir() for log file path instead of hardcoded WP_CONTENT_DIR
* Fix - Remove unused build scaffolding files
* Add - External services documentation in readme
 
 
= 1.0.0 =
* Initial release.
 
== Upgrade Notice ==
 
= 1.0.0 =
Initial release.