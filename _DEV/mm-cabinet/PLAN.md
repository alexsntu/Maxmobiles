# Личный кабинет Maxmobiles – отдельная копия кабинета GOODMi

Решение пользователя 2026-10-14: «делаем отдельную копию для максмобаилс». Код GOODMi (`gm_cabinet`) не трогаем;
у Maxmobiles (company_id = 1) свои имена, файлы, таблицы, ключи, пункты админки. Xione (3) не трогаем.
Минус, принятый пользователем: исправления кабинета (особенно безопасности) нужно вносить в обе копии.

## Проверено 2026-10-14
- Ключи Maxmobiles в `cscart-mcp-server/.env` рабочие: БонусПлюс (заголовок `ApiKey ` + base64 ключа, как у GOODMi;
  тестовый номер найден, карта «Pro»), МойСклад (организация «1. maxmobiles», формат Bearer).
- Таргет СМС: логин/пароль общие с GOODMi, отправитель `Maxmobiles`.
- Телефон оператора 8 (800) 250-81-58 (`tel:+78002508158`) – совпадает с сайтом.
- Уровни программы Maxmobiles (страница /bonusnaya-programma-ru/): **Pro** – с первой покупки, кэшбэк до 5%
  (техника 1%, аудио и приставки 1%, аксессуары 4%, услуги 5%); **Ultra** – от 1 500 000 ₽ покупок, до 10%
  (1,5% / 2% / 6% / 10%). Бонусы через 14 дней, 1 бонус = 1 ₽, до 40% покупки. Есть карта в Wallet (QR).

## Карта соответствия GOODMi → Maxmobiles

| GOODMi | Maxmobiles |
|---|---|
| `app/gm_cabinet/lib.php`, `store.php`, `secrets.php` | `app/mm_cabinet/lib.php`, `store.php`, `secrets.php` |
| функции `gm_cab_*`, `fn_gm_cab_*`, константы `GM_CAB_*` | `mm_cab_*`, `fn_mm_cab_*`, `MM_CAB_*` |
| таблицы `cscart_gm_phone_verify`, `gm_phone_auth`, `gm_auth_log`, `gm_ms_orders` (+ проверить остальные) | `cscart_mm_*` |
| `app/controllers/frontend/gm_cabinet.php`, `backend/gm_cabinet.php` | `.../mm_cabinet.php` |
| шаблоны `views/gm_cabinet/*` (витрина и админка) | `views/mm_cabinet/*` |
| хуки в `gm_delivery_date` (init.php/func.php: auth_routines, update_user_pre, update_profile, before_dispatch, is_order_allowed_post, product_reviews_…) | `my_changes` (init.php/func.php) с условием company_id = 1 |
| `gm_delivery_date/controllers/frontend/auth.pre.php`, `profiles.pre.php`, `checkout.pre.php` | `my_changes/controllers/frontend/*.pre.php` |
| `gm_delivery_date/schemas/menu/menu.post.php`, админ-хуки `design/backend/templates/addons/gm_delivery_date/hooks/*` | `my_changes/...` |
| `js/addons/gm_delivery_date/auth.js` (заглушка почты `@goodmi.ru`) | `js/addons/my_changes/auth.js` (`@maxmobiles.ru`) |
| общие файлы темы с условием company_id = 2: `views/profiles/components/profiles_info.tpl`, `blocks/static_templates/profile_info.tpl`, `addons/abt__unitheme2/hooks/profiles/my_account_menu.post.tpl`, `.../hooks/wrapper/mainbox_general_title_wrapper.pre.tpl`, `addons/rf_yandex_smartcaptcha/hooks/index/scripts.post.tpl` | развилка `elseif company_id == 1` → свой файл в `my_changes` (как с окном выбора города) |
| стили секции 44–57, 64 «Своего CSS» GOODMi | секции MM-* «Своего CSS» Maxmobiles, синие цвета |
| сводка `gm_cabinet.auth_status` + облачная задача → Telegram | `mm_cabinet.auth_status` (облачная задача – в другом аккаунте claude.ai, см. reference-claude-accounts-tools) |

Константы GOODMi, которые меняются: адреса goodmi.ru, телефон 8 (800) 250-17-00, тексты СМС «Код подтверждения
GOODMi», почта-заглушка `@goodmi.ru` / `mail@goodmi.ru`, уровни `gm_cab_levels()`, скрытые каналы продаж МойСклад
`gm_cab_hidden_channels()` (у Maxmobiles свои – посмотреть в МойСклад), статусы заказов МойСклад (сверить с
состояниями МойСклад Maxmobiles), `company_id = 2` в запросах.

## Шаги
1. [x] (2026-10-15) Копия кода с новыми именами, константы Maxmobiles, уровни Pro/Ultra; изолированная проверка на сервере.
2. [x] (2026-10-15) Таблицы `cscart_mm_*` созданы пустыми, структура = GOODMi; откат – /root/gm-backups/2026-10-15-mm-cabinet-tables/ROLLBACK.txt
3. [ ] Страницы «Мои бонусы», «Мои заказы», «Купленные товары», меню аккаунта, стили – проверка под тестовой записью.
4. [ ] Вход и регистрация по номеру, СМС, восстановление пароля, блокировки – сначала тестовый номер, потом все.
5. [ ] Админка (подтверждение номера, заблокированные номера) и оповещения.
6. [ ] Покупатели: подтверждение номеров и дубли (3575 покупателей) – список до изменений.

## Открытые вопросы
- Карта лояльности со штрих-кодом (у GOODMi) – у Maxmobiles карта в Wallet с QR; что показывать в «Моих бонусах»?
- МойСклад Maxmobiles (прочитано 2026-10-14): каналы продаж – «Магазин Вконтакте» (4 шт.), «https://old.maxmobiles.ru/…/1c_exchange.php»,
  «MaxMobiles-CS», «Гудми», «Xione», «Xione - CS», «Консультация сайт»; точки продаж – «ДМ», «БМ». Статусы заказа:
  Новый, Нет связи с клиентом, Клиент думает, Уточняем возможность заказа, Требуется согласование, Предоплата внесена,
  Кредит, «Тинькофф Муленко», «Тинькофф Андрей» (имена сотрудников – покупателю не показывать!), Заказан, доставляется
  на склад, Готов к выдаче, Прозвонили, ожидает клиента, Доставка, Выдан, Отмена: … (6 видов), Данного товара нет на
  складе, В процессе подготовки, YA Отменен, Открыт, Обработан, Выполнен, Аннулирован, Неудача, Отклонен, Отложен.
  Отгрузки без статусов. Нужны решения: какие каналы скрыть (кандидаты: Гудми, Xione, Xione - CS; «MaxMobiles-CS» –
  заказы сайта, их берём из базы сайта?), как переводить статусы для покупателя.

## Решения пользователя
- 2026-10-14, каналы продаж: «все что из интернет магазина это MaxMobiles-CS – остальное это покупки в магазине».
  Скрывать в «Моих заказах» только «MaxMobiles-CS» (заказы сайта берутся из базы сайта). Ответ на уточнение
  про «Гудми», «Xione», «Xione - CS»: «эти 3 канала исключаем, это не продажи» – скрыты (mm_cab_hidden_channels).
- 2026-10-14, статусы МойСклад для покупателя – предложенный перевод подходит, с поправкой: «Тинькофф Муленко»,
  «Тинькофф Андрей» = заказ в обработке и внесена предоплата → «Предоплата получена». Итог:
  Кредит → «Оформление кредита»; Тинькофф … и «Предоплата внесена» → «Предоплата получена»; Новый, Клиент думает,
  Требуется согласование, Уточняем возможность заказа, В процессе подготовки → «В обработке»; Заказан, доставляется на
  склад → «Ожидает поступления»; Готов к выдаче, Прозвонили… → «Готов к выдаче»; Доставка → «Доставляется»; Выдан,
  Выполнен → «Выдан»; все «Отмена: …», YA Отменен, Аннулирован, Неудача, Отклонен, Данного товара нет на складе →
  «Отменён»; Нет связи с клиентом → «Не смогли связаться»; Отложен → «Отложен»; Открыт, Обработан → «В обработке».
- 2026-10-14, карта лояльности в «Моих бонусах»: и штрих-код (как GOODMi), и кнопка «Добавить карту в Wallet»
  (ссылка программы со страницы бонусов: https://k.bonusplus.pro/!16YTQBZ5 , электронная карта …/Ecard).

## Прогресс шага 1 (2026-10-14)
- Сделана копия с переименованием: `app/mm_cabinet/lib.php`, `store.php`, `app/controllers/frontend|backend/mm_cabinet.php`,
  шаблоны `views/mm_cabinet/*` (витрина и админка) – в `server-overrides/`. Таблицы `?:mm_phone_verify`, `mm_phone_auth`,
  `mm_auth_log`, `mm_ms_orders`, `mm_ms_order_items`; ключи сессии/кэша `mm_*`.
- Константы: company_id 1, телефон 8 (800) 250-81-58, заглушка `@maxmobiles.ru`, программа /bonusnaya-programma-ru/,
  тексты «Maxmobiles». Уровни Pro/Ultra (`mm_cab_levels`, img пока пустые – пользователь пришлёт картинки карт).
  Скрытый канал – только `maxmobiles-cs`. Перевод статусов проверен на всех 30 статусах (`status-test.php`).
- Ночная загрузка МойСклад GOODMi: crontab пользователя maxmobiles, 03:20, `index.php --dispatch=gm_cabinet.sync_orders
  --minutes=40` – для Maxmobiles нужна своя строка (`mm_cabinet.sync_orders`) после создания таблиц.
- Сделано дальше: обработчики событий магазина перенесены в `my_changes/func.php` (fn_my_changes_*, company 1) и
  подключены в `my_changes/init.php`; pre-контроллеры `my_changes/controllers/frontend/{auth,profiles,checkout}.pre.php`;
  `js/addons/my_changes/auth.js` (заглушка `@maxmobiles.ru`); в «Моих бонусах» блок «Карта лояльности» – штрих-код
  + кнопка «Добавить карту в Wallet» (https://k.bonusplus.pro/!16YTQBZ5), карта уровня рисуется стилями, пока нет
  картинки; тексты программы Maxmobiles (Pro с первой покупки, до 300 бонусов, 14 дней, до 40%).
- Порядок выкладки: сначала таблицы (шаг 2), потом код – без таблиц обработчики молча отступают, но не спешить.
- Картинки уровней (прислал пользователь, Pro.png/Ultra.png 685×395 с прозрачностью) сжаты в webp ~11 КБ и
  ЗАГРУЖЕНЫ на сервер: https://maxmobiles.ru/images/mm_cabinet/card-pro.webp, card-ultra.webp (копии – server-overrides/
  images/mm_cabinet/); прописаны в mm_cab_levels, в шаблоне размеры 300×173.
- Админка скопирована: `my_changes/schemas/menu/menu.post.php` (пункты «… Maxmobiles», позиции 152/153),
  `design/backend/templates/addons/my_changes/hooks/{orders,profiles}/*` (классы mm-mark-*, fn_my_changes_mm_phone_mark).
- Ещё не сделано в шаге 1: развилки в общих шаблонах темы (profiles_info.tpl, profile_info.tpl, my_account_menu.post.tpl,
  mainbox_general_title_wrapper.pre.tpl, капча), подключение auth.js в scripts.post.tpl my_changes, стили MM-*.
  Картинки уровней Pro/Ultra – ждём от пользователя.

## Пауза 2026-10-14 вечер
Пользователь: «Пока давай остановимся и продолжим позже». Продолжать с «Ещё не сделано в шаге 1» (развилки в общих
шаблонах темы, подключение auth.js, стили MM-*), затем шаг 2 (таблицы – по «да»). Этап 3 (карточка) – отдельно, 2026-10-15.
ВНИМАНИЕ: `server-overrides/app/addons/my_changes/func.php` и `init.php` в репозитории уже содержат обработчики кабинета
Maxmobiles, которых НА СЕРВЕРЕ ЕЩЁ НЕТ (там версия с одной группировкой сравнения). Зеркало опережает сервер – не
«восстанавливать» эти два файла на сервер из зеркала до выкладки кабинета (шаг 2 – таблицы, потом код).

## 2026-10-15 – шаг 1 закончен (на сайт по-прежнему ничего не выложено)
Просьба пользователя: «будь внимателен и проверяй всё, чтобы не затронуть работу пользователей на сайте и не вызвать
ошибки на второй витрине»; GOODMi «сделано правильно и работает как надо» – ветки GOODMi не меняем, только образец.
- Меню аккаунта и левое меню кабинета – СВОИ хуки в my_changes (хуки срабатывают у всех включённых модулей, файлы GOODMi
  не тронуты): `addons/my_changes/hooks/profiles/my_account_menu.post.tpl`, `hooks/wrapper/mainbox_general_title_wrapper.pre.tpl`
  (переменная `mm_lk_std_done`, ссылки mm_cabinet.*, $gm_phone_locked из my_changes/profiles.pre.php).
- Развилки `elseif company_id == 1` → include в двух общих файлах темы (иначе нельзя – они перекрывают штатные):
  `views/profiles/components/profiles_info.tpl` → `addons/my_changes/views/profiles/components/profiles_info.tpl`
  (адреса/часы пунктов по shipping_id: 11 Севастополь Нахимова 19 ТЦ «Детский мир» – часы в описании нет, 36 Меганом
  10–21, 38 Дом Торговли 10–20, 37 Москва БЦ «Рубин» 10–19; СДЭК 32, курьер 15, «Обсудить с менеджером» 6 – общие ветки);
  `blocks/static_templates/profile_info.tpl` → `addons/my_changes/views/blocks/profile_info_benefits.tpl` (до 40%).
- `auth.js` подключён в `my_changes/hooks/index/scripts.post.tpl` – выкладывать ТОЛЬКО вместе с кодом кабинета.
- Стили: `_DEV/mm-cabinet/MM-6-cabinet.css` (MM-6a…o = секции 44–57, 64 GOODMi; оранжевый → синий, Roboto убран,
  селекторы gm_cabinet → mm_cabinet; классы в шаблонах остались gm-*). В файл стилей Maxmobiles ещё НЕ вставлено.
- Проверки на сервере (`/root/gm-backups/2026-10-15-mm-cabinet-tpl/`): Smarty – все шаблоны × витрины 2/1/3, гость/вошедший,
  разные страницы, 0 ошибок и 0 предупреждений (GOODMi выдаёт прежнее, Xione – штатное); LESS – секция и весь файл
  Maxmobiles собираются, grid/aspect-ratio не искажены; php -l (7.4) – 10 файлов чисто; имена функций не пересекаются
  ни с GOODMi, ни с остальным кодом; обработчики событий – все с проверкой fn_my_changes_mm_is_front().
- Зеркало опережает сервер ещё и в двух общих файлах темы (profiles_info.tpl, profile_info.tpl) и в my_changes/hooks/index/scripts.post.tpl.
- Шаг 2 подготовлен: `_DEV/mm-cabinet/tables.sql` – копия структуры пяти таблиц GOODMi (SHOW CREATE TABLE), код
  Maxmobiles обращается к тем же столбцам (diff store.php – только company_id и тексты). Ждёт «да» пользователя.
- Админские хуки (fn_my_changes_mm_phone_mark читает cscart_mm_phone_verify) – выкладывать только после таблиц.
- После таблиц нужна строка cron для `mm_cabinet.sync_orders` (как у GOODMi, 03:20 – выбрать другое время).

## 2026-10-15 ~10:00 – часть А выложена (серверный код, ключи, админка), покупателям не видна
- 26 файлов (`/root/gm-backups/2026-10-15-mm-cabinet-partA/files.txt`, бэкап func.php/init.php в `before/`, откат – ROLLBACK.txt).
- `app/mm_cabinet/secrets.php` собран из локального .env (БонусПлюс `ApiKey `+base64, МойСклад `Bearer`, Таргет СМС общий
  логин/пароль + отправитель Maxmobiles, phone_verify = [21] – тестовая запись (Алексей, тестовый номер), phone_verify_sms = true,
  новые verify_salt и status_key), права 600. Файл ключей GOODMi на сервере не читался (заблокировано проверкой разрешений – и не нужно).
- На сервере init.php БЕЗ хука `product_reviews_is_user_eligible_to_write_product_review` (он действует на всех покупателей,
  ходит в МойСклад) – подключить в части Б; зеркало server-overrides содержит полную версию.
- Проверено: три витрины 200; mm_cabinet.* на Maxmobiles 200, на GOODMi/Xione 404; auth_status с чужим ключом 404;
  в журналах ошибок nginx после выкладки ни одной ошибки PHP (только ограничитель частоты для бота s63896, картинка, шрифт).
- Не сделано: первая загрузка МойСклад в cscart_mm_ms_orders (`php index.php --dispatch=mm_cabinet.sync_orders`, первая –
  вся история) и строка cron – ждут слова пользователя; пункты админки появятся после очистки кэша.
- Часть Б: хук отзывов в init.php, меню/левое меню/развилки тем, auth.js, стили MM-6 (_DEV/mm-cabinet/MM-6-cabinet.css).

## 2026-10-15 – переход на поэтапную работу по просьбе пользователя
Пользователь посмотрел кабинет тестовой записи без стилей: «там всё плохо… давай поэтапно». Его правила:
«независимо от подтверждения номера вход по номеру телефона должен работать»; «подтверждение номера нужно только для
работы с меню Мои заказы и бонусы. А также при регистрации».

### Этап 1 – вход по номеру и паролю (выложен ~10:25, пользователь проверил: «вход по номеру работает»)
`fn_mm_cab_login_owners` в store.php: подтвердившие номер + включённые покупатели Maxmobiles с этим номером в профиле
(RIGHT(REGEXP_REPLACE(phone,'[^0-9]',''),10), при пустом phone – s_phone/b_phone профиля; clear_phone не используется);
пароль сверяется у каждого. auth.js – флаги STAGE {login, emailOptional, recoverSms}. Бэкап и откат –
/root/gm-backups/2026-10-15-mm-login-phone/.

### Этап 2 – восстановление пароля по СМС + капча (выложено ~10:35, ждёт очистки кэша и проверки пользователем)
- recover_send/recover_check используют fn_mm_cab_login_owners (код на номер из профиля; при нескольких записях – подтвердившая,
  иначе последняя вошедшая); тексты recover.tpl без «подтверждённый»; auth.js STAGE.recoverSms = true.
- Капча как на GOODMi: настройки 138 и 6605 витрины 1 = register + recover; 165 (не показывать после первой проверки) = N.
  Скрипты `_DEV/mm-cabinet/server-scripts/mm-captcha-setting.php`, `mm-captcha-every-time.php`.
- Стили MM-7 (`_DEV/mm-cabinet/MM-7-recover.css`: .gm-lk__card + .gm-vf + .gm-phone-lock) дописаны в «Свой CSS» Maxmobiles
  (зеркало синхронизировано). В MM-6 эти правила потом не повторять.
- Бэкап/откат – /root/gm-backups/2026-10-15-mm-recover-sms/ (ROLLBACK.txt, settings-before.json).
- В журнале после выкладки: предупреждения старого вебхука `moysklad.editordermm` (заказ МойСклад без заказа сайта) и модуля
  cp_ima… – не наши (кабинет к МойСклад обращается только GET).

### Этап 3 – стили страниц кабинета (MM-8, выложено ~10:45, ждёт очистки кэша)
Пользователь: кабинет «выглядит без стилей». Выложены только стили самих страниц кабинета: `_DEV/mm-cabinet/MM-8-cabinet-pages.css`
= MM-6a, 6c, 6g, 6n, 6o + правила левого меню по классу из 6j, БЕЗ правил шапки/нижнего меню телефона (.ut2-top-my-account,
.ut2-sticky-panel, body…) и без .gm-lk__card (он в MM-7). Поправки под Maxmobiles: уровней 2 → колонки по 240px; карты 300×173
целиком (aspect-ratio, LESS превратил 300/173 в 1.734 – безвредно); скрытый переключатель «Показать ещё» – тема Maxmobiles
показывает все галочки (body[class*=screen] input[type=checkbox]) → `.gm-bp input.gm-bp-toggle{display:none !important}`;
кнопка «Добавить карту в Wallet» – чёрная. Превью в Chrome на ПК и 430px (заказы, бонусы, купленные товары).
Бэкап/откат – /root/gm-backups/2026-10-15-mm-cabinet-styles/. Временный preview-mm8.css удалён.
Данные: пустые фото у части товаров – у товаров нет картинок на сайте (напр. 4905, скрыт); 11 заказов тестового номера
23.07.2025 в статусе «Обработан» без канала висят в «Актуальных» – спросить пользователя, считать ли «Обработан» завершённым.
Дальше по этапам: меню аккаунта в шапке и нижнее меню телефона (MM-6b, 6i, 6j-шапка + хук my_account_menu), левое меню на
штатных страницах профиль/заказ/избранное (хук mainbox + MM-6f, 6h, 6k), страницы входа/регистрации/окно входа (MM-6d, 6e, 6l).

### МойСклад Maxmobiles – первая загрузка завершена 2026-10-15 10:37, ночной запуск добавлен
Итог: заказы 28012/27189 записано, возвраты покупателей 120/114, розничные возвраты 356/277, отгрузки 4712/4471, розничные
продажи 18756/14075 (продажи и возвраты с MM_CAB_SALES_FROM = 2024-01-01; старую историю можно догрузить `--history=1`).
Crontab пользователя maxmobiles: `20 4 * * * … --dispatch=mm_cabinet.sync_orders --minutes=40` (GOODMi – 03:20), откат –
/root/gm-backups/2026-10-15-mm-cabinet-cron/ROLLBACK.txt.

### Этап 4 – меню аккаунта (выложено ~10:42, ждёт очистки кэша для стилей)
Пользователь: «Обработан» оставить «В обработке»; «далее меню аккаунта»; «там надо скрывать штатные бонусы и отображать наши».
- Хук `addons/my_changes/hooks/profiles/my_account_menu.post.tpl` (пункты .gm-acc-orders/-purchased/-bonus → mm_cabinet.*).
  После выкладки пункты не появились – кэш списка хуков витрины; удалён `var/cache/registry/thooks_C/1/cache_customer__1`
  (копия в бэкапе thooks-before/), пересобрался сам, пункты появились.
- Стили MM-9 (`_DEV/mm-cabinet/MM-9-account-menu.css`): MM-6b, 6i целиком + правила шапки/нижнего меню из 6c, 6g, 6j, 6n;
  штатные «Мои баллы» (reward_points) скрыты; поправка: штатные «Заказы» Maxmobiles – `/orders/` (у GOODMi account-orders).
  Превью в Chrome на ПК (порядок и значки как на GOODMi). Нижнее меню телефона в Chrome не воспроизводится (тема строит его
  только для мобильного браузера; в HTML для iPhone панель есть, 5 пунктов) – проверить на телефоне.
- Бэкап/откат – /root/gm-backups/2026-10-15-mm-account-menu/. Догрузка истории продаж (--history=1) запущена 10:38.

### Этап 5 – левое меню на штатных страницах (выложено ~10:50, ждёт очистки кэша для стилей)
- Хук `addons/my_changes/hooks/wrapper/mainbox_general_title_wrapper.pre.tpl` (профиль, подробности заказа, избранное; гостю на
  странице заказа – «Заказ оформлен без входа»; плашка защищённого номера в профиле).
- Развилка `elseif company_id == 1` в общем `views/profiles/components/profiles_info.tpl` → `addons/my_changes/views/profiles/components/profiles_info.tpl`
  (плитки «Магазин Maxmobiles / пункт выдачи…» и «Получатель»). Живой файл до правки совпадал с утренним (9c35eb9c…).
  Удалены скомпилированные копии profiles_info.tpl и details.tpl и кэш хуков thooks_C/1 – проверено: на заказе 1015589 одно меню, 2 плитки.
- Стили MM-10 (`_DEV/mm-cabinet/MM-10-std-pages.css`) = MM-6f, 6h, 6k + поправка «Заказ Заказ #…» (у Maxmobiles слово уже в заголовке).
  Превью в Chrome: профиль, заказ, избранное.
- Бэкап/откат – /root/gm-backups/2026-10-15-mm-std-pages/.
Пользователь напомнил: у покупателей CS-Cart всегда должна быть почта – заглушка `7XXXXXXXXXX@maxmobiles.ru` (код готов:
fn_mm_cab_stub_email, profiles.pre.php, auth.js STAGE.emailOptional) – включить на этапе «Регистрация» (phone_verify => true).

### Этап 6 – массовое подтверждение старых номеров (вариант А, как на GOODMi), 2026-10-15 ~11:04
Решение пользователя: «делаем вариант А, как на гудми». Скрипт `_DEV/mm-cabinet/server-scripts/mm-bulk-verify.php`
(пробный прогон / --apply, одна транзакция). Подтверждено 3011: «по базе МойСклад» 2557, «зарегистрирован до 15.10.2026» 454
(у каждого номера одна запись). Не тронуты: 101 номер-дубль (207 записей), 362 без номера, тестовая 21 (подтвердила сама).
Ловушка: номер-ключ массива PHP становится int – сравнивать (string). SSH дважды обрывался – запуск через nohup с логом.
Откат – /root/gm-backups/2026-10-15-mm-bulk-verify/ROLLBACK.txt.

### Этап 7 – регистрация по номеру (выложено ~11:15, ждёт очистки кэша и проверки)
- secrets.php: `'phone_verify' => true` (было [21]) – подтверждение для «Моих заказов»/«Моих бонусов» и при регистрации для всех;
  регистрация: номер обязателен, e-mail необязателен → заглушка 7XXXXXXXXXX@maxmobiles.ru, СМС с кодом, до подтверждения – только «Мои заказы».
- auth.js STAGE.emailOptional = true.
- Развилка в общем `blocks/static_templates/profile_info.tpl` → `addons/my_changes/views/blocks/profile_info_benefits.tpl` (проверено curl:
  Maxmobiles – 40%, GOODMi – 25%, Xione – штатный).
- Стили MM-11 (`_DEV/mm-cabinet/MM-11-login-register.css`) = MM-6d, 6e, 6l; превью гостевых страниц через fetch(credentials:'omit') + document.write.
- Бэкап/откат – /root/gm-backups/2026-10-15-mm-registration/ (быстрый выключатель – phone_verify обратно [21] + старый auth.js).
Осталось: хук отзывов `product_reviews_is_user_eligible_to_write_product_review` в init.php (купившим в магазине); дубли (101 номер);
крошки в «Избранном» (пользователь отложил); оповещения auth_status Maxmobiles; коммит.

### Этап 8 – отзывы, дубли, оповещения (2026-10-15 ~11:20)
- Отзывы: в init.php подключён `product_reviews_is_user_eligible_to_write_product_review` (бэкап /root/gm-backups/2026-10-15-mm-reviews/).
  У Maxmobiles была выключена настройка «Отзыв только после покупки» (review_after_purchase, у GOODMi – Y) – пользователь включил сам.
- Дубли: `_DEV/mm-cabinet/server-scripts/mm-merge-duplicates.php` (копия GOODMi: company 1, mm_ таблицы, откат в /root/gm-backups/2026-10-15-mm-merge/).
  Пробный прогон: 101 номер → 84 по правилам GOODMi, 6 нет в МойСклад (тот же человек), 11 разные имена (из них 5 – латиница/кириллица
  одного человека). Предложено объединить 95 (84 + 6 через --no-ms + 5 через --any-names по номерам), оставить 6 с разными людьми
  (…1773, …5912, …5794, …2573, …4161, …3974). ЖДЁТ ответа пользователя.
- Оповещения: сводка `mm_cabinet.auth_status&key=` работает; ключ – `MAXMOBILES_STATUS_KEY` в cscart-mcp-server/.env (в git не попадает),
  на сервере в secrets.php (бэкап /root/gm-backups/2026-10-15-mm-alerts/). Тревоги антивируса из сводки Maxmobiles убраны (их даёт сводка GOODMi).
  Доставка в Telegram – облачная задача goodmi-sms-watch-tick в ДРУГОМ аккаунте claude.ai: добавить в её промпт второй адрес (делает пользователь).
- Дубли ОБЪЕДИНЕНЫ 2026-10-15 11:23 (пользователь: «объединяй 95, 6 оставь»): 3 прохода (--apply --all; --phones set2 --no-ms --any-names;
  --phones set3 --any-names) → 95 номеров, 100 вторых записей отключены, 70 заказов перенесено; не перенеслись 1 соцсеть и 3 товара
  избранного/корзины (уже были у основной). Итог: 3481 включённых, дублей 6 (оставлены), подтверждено 3107 (1 сам, 454, 95, 2557).
  Откат – /root/gm-backups/2026-10-15-mm-merge/ROLLBACK.txt (json на номер). Имена файлов отката = полные номера – не выводить ls в чат.
