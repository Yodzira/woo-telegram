=== WooCommerce to Telegram ===
Contributors: yodzira
Tags: woocommerce, telegram, orders, notification, alerts
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instant WooCommerce order notifications in Telegram — queued delivery with retries, flood protection, 60-second setup.

== Description ==

Order emails get lost. Your Telegram doesn't. WooCommerce to Telegram sends a compact message the moment an order comes in: number, total, items, payment method — with status changes included.

* 60-second setup: paste a @BotFather token, paste the chat id, press "Send test"
* queued delivery with automatic retries (never slows down checkout)
* flood protection: a burst of orders collapses into one digest message
* privacy switch: hide customer details in notifications
* per-status control: new / processing / completed / cancelled / refunded
* clean uninstall: options and cron events wiped completely

== Installation ==

1. Install and activate (WooCommerce required).
2. Open "Woo to Telegram", paste your bot token and chat id.
3. Press "Send test" — done.

== Frequently Asked Questions ==

= Does it slow down checkout? =
No. Orders are queued and delivered from a cron event after checkout finishes.

= What if Telegram is unreachable? =
Delivery retries automatically (1 and 5 minutes later), then gives up quietly.

== Changelog ==

= 0.1.0 =
* First release: new-order and status-change notifications, retry queue, flood digest, setup wizard, clean uninstall.
