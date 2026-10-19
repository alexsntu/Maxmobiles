# Наши файлы на сервере магазинов – Maxmobiles

Копия файлов, которые относятся **только к витрине Maxmobiles** (company_id = 1), из `server-overrides/` репозитория GOODMi
(`https://github.com/alexsntu/GOODMi`, там – файлы GOODMi, общие файлы темы и полная инструкция восстановления). Перенесено 2026-10-16
по просьбе владельца: «все данные для Maxmobiles положить в его репозиторий». Пути внутри `server-overrides/` – те же, что на сервере
от корня сайта `/var/www/maxmobiles/data/www/maxmobiles.ru/`.

## Главное
- **Стили** – файл `maxmobiles-styles.css` в корне этого репозитория = «Свой CSS» Maxmobiles на сайте
  (`design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css`). Перед загрузкой через админку – всегда `git pull`:
  разделы MM-1…MM-13 (корзина, оформление, кабинет, поиск) дописываются на сервере и сразу отправляются сюда.
- **Код** – копии в `server-overrides/`; каждая правка на сервере попадает и в репозиторий GOODMi, и сюда.
- Рабочие материалы: `_DEV/mm-cabinet/` (план кабинета, таблицы, разовые скрипты), `_DEV/search/` (сборка поиска, сравнение с Diginetica, синонимы).
- Ключи и пароли (`app/mm_cabinet/secrets.php`, ключи МойСклад, БонусПлюс, СМС) в репозиторий не кладутся – они только на сервере.

## Общие файлы обеих витрин (лежат только в репозитории GOODMi)
У этих файлов внутри развилка по витрине (`company_id`), поэтому они хранятся в одном месте – `server-overrides/` репозитория GOODMi:
`design/themes/abt__unitheme2/templates/common/scripts.tpl`, `…/views/profiles/components/profiles_info.tpl`,
`…/blocks/static_templates/profile_info.tpl`, хуки темы в `…/addons/abt__unitheme2/hooks/…`, `…/addons/geo_maps/…`,
`…/addons/call_requests/…`, `…/addons/reward_points/…`, `…/addons/rf_yandex_smartcaptcha/…`, настройки nginx `etc/nginx/…`.

## Как восстановить после обновления CS-Cart / темы
Скопировать файлы из `server-overrides/` на те же места на сервере (сначала бэкап текущих), затем общие файлы из репозитория GOODMi,
очистить кэш и проверить все витрины. Подробный порядок и что настроено только в админке – README репозитория GOODMi.
Бэкапы и инструкции отката каждой выкладки – на сервере в `/root/gm-backups/2026-10-1*-mm-*/ROLLBACK.txt`.

## Перенос оформления с GOODMi

У Maxmobiles свои файлы, файлы GOODMi не трогаем. Стили – в «Своём CSS» витрины
`design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css` (точная копия – в этой папке), секции MM-*.
Шаблоны – в штатном модуле «Мои изменения» (`templates/addons/my_changes/`), каждый с условием `company_id == 1`;
модуль `my_changes` должен быть включён (Дополнения → Управление), иначе его шаблоны не подключаются.

### Этап 1: корзина, избранное, сравнение (2026-10-14)

- Стили – секции MM-1 (корзина, вкл. строку «Рекомендуем добавить к заказу» модуля «Интересные аксессуары»),
  MM-2 (избранное), MM-3 (сравнение `/compare/`) в конце файла стилей Maxmobiles.
- `addons/my_changes/hooks/checkout/product_options.post.tpl` – кнопки «В избранное» и «Сравнить» у товара в корзине.
- `addons/my_changes/hooks/ab__extended_comparison_wishlist/top_lists.pre.tpl` – сравнение сразу открывается на первой вкладке.
- Бэкап, проверки и откат – `/root/gm-backups/2026-10-14-mm-stage1/` (`ROLLBACK.txt`).

### Выбор города в окне геолокации (2026-10-14)

- `addons/my_changes/views/geo_maps/customer_geolocation.tpl` – выбор города Maxmobiles (поиск + популярные города, тот же
  список, что у GOODMi). Окно выводится одним файлом темы на все витрины, поэтому в файле GOODMi
  `addons/geo_maps/views/geo_maps/customer_geolocation.tpl` добавлена развилка `elseif company_id == 1` с подключением этого файла.
- Стили – секция MM-4 в конце «Своего CSS» Maxmobiles (акцент `#0071e3`).
- Бэкап, проверки и откат – `/root/gm-backups/2026-10-14-mm-geo/` (`ROLLBACK.txt`).

### Этап 2: оформление заказа (2026-10-14)

- Стили – секция MM-5 (a–d) в конце «Своего CSS» Maxmobiles: перенос секций 24, 29, 43 GOODMi в синих цветах, без
  карточек сроков доставки; штатный блок «Ваш заказ» оформлен компактными строками и стоит первым.
- `js/addons/my_changes/checkout_city.js` (подключает `addons/my_changes/hooks/index/scripts.post.tpl`) – нажатие на город
  открывает окно выбора города.
- Бэкап, проверки и откат – `/root/gm-backups/2026-10-14-mm-checkout/` (`ROLLBACK.txt`).

### Сравнение: группировка «По разделам» (2026-10-14, обе витрины)

- `app/addons/my_changes/func.php`, `init.php`, `GmSectionComparisonGrouper.php` – свой вариант настройки «Группировать по» модуля
  ab__extended_comparison_wishlist: крупные группы (iPhone, MacBook, Смартфоны…) из `fn_my_changes_gm_compare_groups()`,
  остальное – по разделу второго уровня; понимает `?product_id=`. Включается в настройках модуля сравнения отдельно на
  каждой витрине. **Пока выбран этот вариант, модуль «Мои изменения» выключать нельзя** – страница сравнения упадёт
  (модуль сравнения не найдёт класс группировки). Откат – вернуть «Главная категория» в настройке.
- `js/addons/my_changes/compare_notice.js` (Maxmobiles, подключает `addons/my_changes/hooks/index/scripts.post.tpl`) и
  `js/addons/gm_delivery_date/compare_notice.js` (GOODMi, подключает scripts.post.tpl модуля gm_delivery_date) – кнопка
  «Список сравнения» в уведомлении «Товар добавлен» ведёт сразу на список товара, без окна «Списки сравнений» поверх уведомления.
- Бэкап и проверки – `/root/gm-backups/2026-10-14-compare-groups/` (`test/grouper-test.php`).

### Личный кабинет Maxmobiles (`mm_cabinet`, выложен 2026-10-15 по этапам)

Отдельная копия кабинета GOODMi (решение пользователя 2026-10-14), код GOODMi не трогается. Подробный ход, решения и откаты
по этапам – `_DEV/mm-cabinet/PLAN.md`.
- `app/mm_cabinet/lib.php`, `store.php` (+ `secrets.php` – **в репозитории нет**, на сервере, права 600: ключи БонусПлюс, МойСклад,
  Таргет СМС, `phone_verify => true`, `status_key` = `MAXMOBILES_STATUS_KEY` из `cscart-mcp-server/.env`), таблицы `cscart_mm_*`
  (`_DEV/mm-cabinet/tables.sql`).
- `app/controllers/frontend/mm_cabinet.php`, `backend/mm_cabinet.php`; шаблоны `views/mm_cabinet/*` (витрина и админка).
- Модуль «Мои изменения»: `func.php`/`init.php` (обработчики входа по номеру, регистрации, доступа к заказу, отзывов от купивших
  в магазине, блокировки до подтверждения), `controllers/frontend/{auth,profiles,checkout}.pre.php`, `schemas/menu/menu.post.php`,
  админ-хуки `design/backend/templates/addons/my_changes/hooks/{orders,profiles}/*`, хуки темы
  `addons/my_changes/hooks/{profiles/my_account_menu.post,wrapper/mainbox_general_title_wrapper.pre,index/scripts.post}.tpl`,
  `addons/my_changes/views/{profiles/components/profiles_info,blocks/profile_info_benefits}.tpl`, `js/addons/my_changes/auth.js`.
- Развилки `elseif company_id == 1` в общих файлах темы: `views/profiles/components/profiles_info.tpl`,
  `blocks/static_templates/profile_info.tpl` (ветки GOODMi не менялись).
- Стили – секции MM-7…MM-11 в конце «Своего CSS» Maxmobiles (исходники – `_DEV/mm-cabinet/MM-*.css`).
- Cron пользователя maxmobiles: `20 4 * * * … --dispatch=mm_cabinet.sync_orders --minutes=40`.
- Настройки в базе: капча витрины 1 – register + recover, «не показывать после первой проверки» – N; «Отзыв только после
  покупки» Maxmobiles включил пользователь. Массовое подтверждение номеров и объединение дублей – скрипты
  `_DEV/mm-cabinet/server-scripts/`.
- После обновления CS-Cart: если пункты меню кабинета пропали – удалить кэш списка хуков `var/cache/registry/thooks_C/1/*`.

### Подтверждённый номер меняет только администратор (2026-10-15, GOODMi и Maxmobiles)

Номер – ключ покупателя в МойСклад и БонусПлюс, а смена номера на сайте туда не доходит, поэтому покупатель сам
подтверждённый номер не меняет (неподтверждённый и гость – могут). Номер меняет администратор вручную: профиль в админке →
«Подтверждение телефона покупателей» → «Подтвердить номер» → контрагент в МойСклад и БонусПлюс.
- Кабинет: `views/{gm,mm}_cabinet/phone.tpl` – вместо смены по СМС карточка «Как сменить номер» (заявка на store@goodmi.ru /
  store@maxmobiles.ru, звонок на текущий номер или письмо с e-mail профиля); `controllers/frontend/{gm,mm}_cabinet.php` –
  `verify` отказывает в смене подтверждённого номера; плашка профиля в хуках `mainbox_general_title_wrapper.pre.tpl`.
- Оформление заказа: в конце `checkout.pre.php` (`gm_delivery_date` / `my_changes`) номер из формы и корзины подменяется номером
  профиля (иначе `fn_checkout_update_user_profile` записал бы его в профиль после заказа); флаг `Tygh.gm_phone_locked` – хуки
  `index/scripts.post.tpl`; поле только для чтения – `auth.js`; стили – секция 68 GOODMi и MM-12 Maxmobiles.
- Бэкапы и откаты: `/root/gm-backups/2026-10-15-phone-lock{,-mm}/`, `/root/gm-backups/2026-10-15-phone-checkout{,-mm}/`.

### «Все заказы» на странице «Заказ оформлен» ведёт в кабинет (2026-10-15, GOODMi и Maxmobiles)

Кнопки страницы `checkout.complete` (общий шаблон `responsive/templates/views/checkout/complete.tpl`) подменены через хук
`checkout:complete_button`: `addons/gm_delivery_date/hooks/checkout/complete_button.override.tpl` (только витрина 2 →
`gm_cabinet.orders`, `/cabinet/`) и `addons/my_changes/hooks/checkout/complete_button.override.tpl` (только витрина 1 →
`mm_cabinet.orders`). На другой витрине подмена пустая, и CS-Cart выводит штатные кнопки. После выкладки нужно удалить кэш
списка хуков `var/cache/registry/thooks_C/{1,2}`. Откат: `/root/gm-backups/2026-10-15-complete-button/ROLLBACK.txt`.

### Служебные команды `bonusplus.*` закрыты для посетителей (2026-10-15, все витрины)

`app/addons/my_changes/controllers/frontend/bonusplus.pre.php`: команды контроллера разработчика
`app/controllers/frontend/bonusplus.php` – `aj1`, `ajdel`, `aj` (подписки на вебхуки БонусПлюс), `orderinfo` (данные заказа),
`bonus`, `bonusfile` (перезапись баллов CS-Cart), `clearphone` – работают только из cron (CLI) и для вошедшего администратора,
остальным 404. Открыта `ajax` (проверка баланса, `js/tygh/bonus.js`); вебхуки `bonusplus.php`/`bonuspluscard.php` в корне не
затронуты. Сам контроллер не менялся (в нём ключ API БонусПлюс открытым текстом). Откат: `rm` этого файла
(`/root/gm-backups/2026-10-15-bonusplus-close/ROLLBACK.txt`).

### Свой поиск Maxmobiles вместо Diginetica (с 2026-10-16 у всех покупателей Maxmobiles; Diginetica на Maxmobiles удалена)

С включения для всех кука `mm_search_test` ничего не включает (`?test=` только стирает её), скрипт `mm_search.js` – в общем
бандле через хук my_changes, стили – секция MM-13 «Своего CSS» Maxmobiles (= `app/mm_search/suggest.css`), статистика – таблицы
`cscart_mm_search_*` и админка `mm_search.*` (Товары → «Поиск Maxmobiles»). Откат включения – `/root/gm-backups/2026-10-16-mm-search-golive/`.
Ниже – как было на этапах до включения.

Движок – Manticore Search 29.9 на этом же сервере (служба `manticore`, слушает только 127.0.0.1:9306/9308/9312; репозиторий
`/etc/apt/sources.list.d/manticoresearch.sources`). Индексы: `mm_products` (товары витрины 1), `mm_suggest` (словарь подсказок),
`mm_categories`, `mm_queries`. Сборка – скрипты `/root/mm-search/` (исходники и сравнение с Diginetica – `_DEV/search/`):
`mm-search-index.php` (каждые 2 часа, :15 нечётных часов), `mm-search-demand.php` (спрос за 90 дней из МойСклад, с 2026-10-17 раз в неделю – понедельник 05:10) – `/etc/cron.d/mm-search`
(копия – `_DEV/search/cron.d-mm-search`). С 2026-10-17 (после зависания Manticore на 14 ч) сборки обеих витрин идут под общим замком
`/run/lock/search-index.lock` с пределом времени, сборщики ждут ответа Manticore не дольше 2 мин, `mm-search.php` и `products.pre.php` –
не дольше 1 с на соединение и 3 с на ответ (при сбое – штатная выдача CS-Cart), а хук `get_products` в `func.php` снимает метку модуля
`ecl_instock_sorting` при сортировке «По релевантности», чтобы он не ставил аксессуары в наличии выше нужных устройств не в наличии.
Подробно и откат – README репозитория GOODMi, раздел «Защита поиска обеих витрин от зависания Manticore».
- Файлы сайта: `mm-search.php` (корень: подсказки `?q=`, категории `?cats=1&q=`, включение `?test=on|off`, скрипт и стили на время
  настройки `?asset=js|css`; не maxmobiles.ru → 404), `app/mm_search/lib.php` (поиск, синонимы, подсказки), `app/mm_search/suggest.css`,
  `js/addons/my_changes/mm_search.js` (подсказки, история «Вы искали», плитки «Найдено в категориях»).
- Модуль «Мои изменения»: `controllers/frontend/products.pre.php` (страница результатов products.search – наши товары, `mm_cat` – одна
  категория), хуки `get_products_pre`, `get_products`, `get_products_post`, `products_sorting` в `func.php` (сортировка «По релевантности»).
- Шаблоны: `common/scripts.tpl` – Diginetica Maxmobiles не грузится при куке; хук my_changes `index/scripts.post.tpl` – при куке
  подключает `/mm-search.php?asset=js`. Секция MM-13 «Своего CSS» Maxmobiles – старая версия стилей, при включении для всех заменить
  содержимым `app/mm_search/suggest.css`.
- Откаты: `/root/gm-backups/2026-10-16-manticore/`, `…-mm-search/`, `…-mm-search-results/` (ROLLBACK.txt в каждой).

### Статистика поиска без ботов, «Часто ищут» по своему журналу (2026-10-18)

- `products.pre.php` модуля my_changes: поиск без cookie сессии сайта (`sid_customer_…`, её ставит первая же страница) выполняется,
  но в журнал `cscart_mm_search_log` не пишется – так ходят боты (17.10 на GOODMi бот без cookie сделал 49 поисков за 35 с).
- `_DEV/search/mm-search-index.php`: «Часто ищут» и запросы в подсказках = основа (`/root/mm-search/queries.txt` от 2026-10-16) + свой
  журнал за 90 дней, по 3 за посетителя, только где что-то нашлось; запрос только из журнала – если его искали минимум двое.
- Из журнала удалены 7 строк наших проверок 17.10. Копии – `/root/gm-backups/2026-10-18-search-stats/`.
- NGENIX (набор правил общий с GOODMi): правило № 2322118 – Referer ровно `https://www.google.com/` на поиск, «в сравнение», избранное,
  сравнение → 403.

### Карточка товара Maxmobiles `maxmobiles_card` + модуль сроков `mm_delivery` (2026-10-19)

Свой шаблон страницы товара (выбирается в админке как «Maxmobiles_card» – у товара, категории или витрины) и модуль
«Maxmobiles: сроки получения и карточка товара». Исходники, макет, правила и порядок выкладки – `_DEV/product-card/README.md`
и `_DEV/mm_delivery/RULES.md`.
- Шаблоны: `blocks/product_templates/maxmobiles_card.tpl` (ПК, три колонки: галерея | название, варианты, характеристики |
  цена, бонусы, покупка, сроки, Trade-in, Гарантия+; основа – `abt__ut2_bigpicture_flat_template.tpl`),
  `components/maxmobiles_card_mobile.tpl` (телефон, основа – `abt__ut2_mobile_template.tpl`), `components/maxmobiles_card_parts.tpl`.
  После обновления UniTheme2 сверить с исходными шаблонами темы (копии – `_DEV/product-card/server-orig/`).
- Модуль `app/addons/mm_delivery/`: сроки получения (`lib/engine.php`, проверки – `_DEV/mm_delivery/tests.php`), наличие по складу 27,
  цены вариантов для кнопок, бонусы Pro/Ultra, разделы для Trade-in (смартфоны) и Гарантии+ (новые iPhone, Watch, iPad, MacBook,
  AirPods); запрос `mm_delivery.card` – пересчёт блока после смены города. Скрипт `js/addons/mm_delivery/card.js` (хук
  `addons/mm_delivery/hooks/index/scripts.post.tpl`): отсчёт, цены на кнопках вариантов, смена города, «Все характеристики»,
  обход ошибки «Свернуть» модуля ab__hide_product_description.
- Стили – секция MM-14 «Своего CSS» (исходник – `_DEV/product-card/MM-14-card.css`).
- Выкладка – `_DEV/product-card/deploy.sh` (бэкап → проверка CSS/PHP → выкладка → очистка кэша и шаблонов
  `/var/www/maxmobiles/data/mm_cc.php` от пользователя maxmobiles). Бэкапы и откат – `/root/gm-backups/2026-10-19-mm-card/`
  (`ROLLBACK.txt`).
