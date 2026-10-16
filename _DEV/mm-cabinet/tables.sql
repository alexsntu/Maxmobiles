-- Личный кабинет Maxmobiles (company_id = 1): пять своих таблиц – точная копия структуры таблиц GOODMi cscart_gm_*
-- (прочитана из базы 2026-10-15 через SHOW CREATE TABLE), код app/mm_cabinet/store.php обращается к тем же столбцам.
-- Таблицы GOODMi не затрагиваются. Откат: DROP TABLE для пяти таблиц ниже (пока код кабинета не выложен, они ни к чему не подключены).

CREATE TABLE IF NOT EXISTS `cscart_mm_phone_verify` (
  `user_id` int(10) unsigned NOT NULL,
  `phone` varchar(15) NOT NULL DEFAULT '',
  `verified_at` int(10) unsigned NOT NULL DEFAULT 0,
  `verified_by` int(10) unsigned NOT NULL DEFAULT 0,
  `note` varchar(40) NOT NULL DEFAULT '',
  `code_phone` varchar(15) NOT NULL DEFAULT '',
  `code_hash` char(64) NOT NULL DEFAULT '',
  `code_expires` int(10) unsigned NOT NULL DEFAULT 0,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_window` int(10) unsigned NOT NULL DEFAULT 0,
  `last_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `must_verify` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  KEY `code_phone` (`code_phone`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Maxmobiles: подтверждение номеров телефона покупателей';

CREATE TABLE IF NOT EXISTS `cscart_mm_phone_auth` (
  `phone` varchar(15) NOT NULL,
  `login_fails` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `login_window` int(10) unsigned NOT NULL DEFAULT 0,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `code_hash` char(64) NOT NULL DEFAULT '',
  `code_expires` int(10) unsigned NOT NULL DEFAULT 0,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sent_window` int(10) unsigned NOT NULL DEFAULT 0,
  `last_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `strikes` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `blocked_at` int(10) unsigned NOT NULL DEFAULT 0,
  `blocked_reason` varchar(80) NOT NULL DEFAULT '',
  PRIMARY KEY (`phone`),
  KEY `blocked_at` (`blocked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Maxmobiles: вход по номеру, СМС-коды, блокировки';

CREATE TABLE IF NOT EXISTS `cscart_mm_auth_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  `phone` varchar(15) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `event` varchar(20) NOT NULL DEFAULT '',
  `info` varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Maxmobiles: журнал входов и СМС';

CREATE TABLE IF NOT EXISTS `cscart_mm_ms_orders` (
  `id` char(36) NOT NULL,
  `doc` char(1) NOT NULL DEFAULT 'O',
  `ref_id` char(36) NOT NULL DEFAULT '',
  `agent_id` char(36) NOT NULL DEFAULT '',
  `phone` char(11) NOT NULL,
  `number` varchar(32) NOT NULL DEFAULT '',
  `moment` datetime NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `state` varchar(64) NOT NULL DEFAULT '',
  `channel` varchar(64) NOT NULL DEFAULT '',
  `store` varchar(64) NOT NULL DEFAULT '',
  `external_code` varchar(64) NOT NULL DEFAULT '',
  `updated` datetime NOT NULL,
  `items_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_moment` (`phone`,`moment`),
  KEY `updated` (`updated`),
  KEY `ref_id` (`ref_id`),
  KEY `phone_doc` (`phone`,`doc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Maxmobiles: заказы покупателей из МойСклад для личного кабинета';

CREATE TABLE IF NOT EXISTS `cscart_mm_ms_order_items` (
  `order_id` char(36) NOT NULL,
  `pos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ms_product_id` char(36) NOT NULL DEFAULT '',
  `ext_code` varchar(64) NOT NULL DEFAULT '',
  `code` varchar(64) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_id`,`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Maxmobiles: состав заказов из МойСклад для личного кабинета';
