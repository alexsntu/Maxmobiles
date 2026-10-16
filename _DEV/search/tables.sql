-- Maxmobiles: свой поиск – данные для статистики и обучения поиска (2026-10-16, решение пользователя «да»).
-- Покупатель различается только случайной меткой браузера mm_sid (16 hex), без IP и личных данных. Хранение – 180 дней
-- (чистка – в mm-search-index.php раз в сутки). Только новые таблицы, существующие не меняются.

-- Каждый поиск со страницы результатов: что ввели, как нашли, что показали (порядок товаров на странице)
CREATE TABLE IF NOT EXISTS `cscart_mm_search_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ts` int unsigned NOT NULL,
  `sid` char(16) NOT NULL DEFAULT '',
  `source` varchar(16) NOT NULL DEFAULT 'page',      -- page: страница результатов
  `q` varchar(120) NOT NULL DEFAULT '',              -- как ввели
  `qn` varchar(120) NOT NULL DEFAULT '',             -- после обработки (нижний регистр, «ё»→«е», числа отдельно)
  `mode` varchar(12) NOT NULL DEFAULT '',            -- all / prefix / fuzzy / translit / quorum / none
  `total` int unsigned NOT NULL DEFAULT 0,           -- сколько нашлось
  `cat` int unsigned NOT NULL DEFAULT 0,             -- выбранная плитка категории (mm_cat)
  `page` smallint unsigned NOT NULL DEFAULT 1,
  `sort` varchar(24) NOT NULL DEFAULT '',
  `device` char(1) NOT NULL DEFAULT '',              -- m – телефон, d – ПК
  `ms` smallint unsigned NOT NULL DEFAULT 0,         -- время ответа поиска, мс
  `shown` text,                                      -- id товаров на странице по порядку показа
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`),
  KEY `qn` (`qn`),
  KEY `sid_ts` (`sid`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Клики: по товару в результатах (results), по подсказкам (sg_query, sg_scope, sg_product, sg_all, sg_history), по плитке категории
CREATE TABLE IF NOT EXISTS `cscart_mm_search_clicks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ts` int unsigned NOT NULL,
  `sid` char(16) NOT NULL DEFAULT '',
  `search_id` bigint unsigned NOT NULL DEFAULT 0,    -- поиск, к которому относится клик (для results)
  `source` varchar(16) NOT NULL DEFAULT '',
  `q` varchar(120) NOT NULL DEFAULT '',              -- запрос страницы или выбранный вариант подсказки
  `typed` varchar(120) NOT NULL DEFAULT '',          -- что было набрано в поле в момент выбора подсказки
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `pos` smallint unsigned NOT NULL DEFAULT 0,        -- место (1 – первый)
  `device` char(1) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`),
  KEY `search_id` (`search_id`),
  KEY `sid_ts` (`sid`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Корзина и заказы после поиска (только у тех, кто искал за последние 2 часа)
CREATE TABLE IF NOT EXISTS `cscart_mm_search_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ts` int unsigned NOT NULL,
  `sid` char(16) NOT NULL DEFAULT '',
  `type` varchar(8) NOT NULL DEFAULT '',             -- cart / order
  `search_id` bigint unsigned NOT NULL DEFAULT 0,    -- последний поиск этого покупателя
  `product_id` int unsigned NOT NULL DEFAULT 0,
  `qty` int unsigned NOT NULL DEFAULT 0,
  `price` decimal(12,2) NOT NULL DEFAULT 0,
  `order_id` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`),
  KEY `search_id` (`search_id`),
  KEY `sid_ts` (`sid`, `ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Синонимы, которые добавляют в админке (вдобавок к стартовому списку в app/mm_search/lib.php)
CREATE TABLE IF NOT EXISTS `cscart_mm_search_synonyms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `src` varchar(80) NOT NULL,                        -- что пишут покупатели
  `dst` varchar(120) NOT NULL,                       -- как называется в каталоге
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` int unsigned NOT NULL DEFAULT 0,
  `note` varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `src` (`src`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Закрепления: товар наверху выдачи по запросу
CREATE TABLE IF NOT EXISTS `cscart_mm_search_pins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `q` varchar(120) NOT NULL,                         -- запрос после обработки (как qn)
  `product_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `q_product` (`q`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
