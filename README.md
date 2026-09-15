# WooCommerce to Telegram

**[EN]** Instant WooCommerce order notifications in Telegram: number, total, items, payment method — delivered from a queue with retries, so checkout never waits and burst orders collapse into one digest. 60-second setup with a "Send test" button.

**[RU]** Мгновенные уведомления о заказах WooCommerce в Telegram: номер, сумма, товары, способ оплаты. Доставка из очереди с ретраями — чекаут не ждёт, шквал заказов сворачивается в один дайджест. Настройка за минуту с кнопкой «Отправить тест».

🔗 [**Скачать бесплатно / Download free**](https://github.com/Yodzira/woo-telegram/releases/latest/download/woo-telegram.zip)

## Почему Telegram

Письма о заказах теряются так же, как письма ядра о фатальных ошибках: 4 миллиона сайтов поставили SMTP-плагины уже ПОСЛЕ того, как потеряли письма. Сообщение в Telegram приходит за секунды и видно всем, кто в чате.

## Принципы

- **Чекаут не тормозит**: заказ ставится в очередь и уходит крон-событием сразу после оформления
- **Ретраи**: Telegram недоступен → повтор через 1 и 5 минут, потом тихо сдаёмся
- **Анти-флуд**: 15 заказов за минуту = один дайджест, а не 15 сообщений
- **Приватность**: переключатель «скрыть город/контакты покупателя»
- **Статусы под контролем**: new / processing / completed / cancelled / refunded
- **Чистый uninstall**: опции и крон-события стираются полностью

## Установка / Install

1. Скачайте [`woo-telegram.zip`](https://github.com/Yodzira/woo-telegram/releases/latest/download/woo-telegram.zip)
2. WP-админка → **Плагины → Добавить новый → Загрузить плагин** → zip → Активировать (нужен WooCommerce)
3. Меню **Woo to Telegram**: токен от @BotFather → chat id → **Send test** — готово

## Требования / Requirements

- WordPress 6.0+ (протестировано до 7.1), PHP 7.4+, WooCommerce

## Качество / Quality

- PHPUnit (ядро): 13 тестов, 31 assertion ✅ (шаблоны сообщений, флуд-гейт, клиент API, санитизация)
- Интеграция на живом WP 7.1: 14/14 (очередь, ретраи, анти-флуд, cleanup) ✅
- Официальный Plugin Checker: 0 errors (release build) ✅

## Лицензия / License

GPL-2.0-or-later (совместимо с WordPress).

💰 **[Купить Pro / Buy Pro — 2 990 ₽/год](https://yodsira.com/buy/woo-telegram-orders)** — лицензия на 1 сайт, 12 месяцев обновлений.
