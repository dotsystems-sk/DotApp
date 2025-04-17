-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hostiteľ: 127.0.0.1:3306
-- Čas generovania: Št 17.Apr 2025, 15:04
-- Verzia serveru: 8.3.0
-- Verzia PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáza: `dotsystems`
--

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users`
--

DROP TABLE IF EXISTS `erp_users`;
CREATE TABLE IF NOT EXISTS `erp_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '// Uzivatelske meno',
  `email` varchar(100) NOT NULL COMMENT '// Email, moze sa pouzit na prihlasenie tiez. Moze sa pouzivat na emailove notifikacie',
  `password` varchar(100) NOT NULL COMMENT '// Heslo',
  `2fa_firewall` int NOT NULL COMMENT '// Pouzit alebo nepouzit firewall',
  `2fa_sms` int NOT NULL COMMENT '// Pouzivame 2faktor cez SMS?',
  `2fa_sms_number_prefix` varchar(8) NOT NULL,
  `2fa_sms_number` varchar(20) NOT NULL COMMENT '// Cislo pre zaslanie SMS',
  `2fa_sms_number_confirmed` int NOT NULL COMMENT '// Cislo potvrdene zadanim kodu',
  `2fa_auth` int NOT NULL COMMENT '// Pouzivame 2 faktor cez GOOGLE AUTH ?',
  `2fa_auth_secret` varchar(50) NOT NULL COMMENT '// Ak amme google auth, tak treba drzat ulozeny secret ',
  `2fa_auth_secret_confirmed` int NOT NULL COMMENT '// Bolo potvrdene 2FA auth?',
  `status` int NOT NULL COMMENT '// Status prihlasenia. 1 - Aktivny, 2-DLhsie neaktivny, 3 - Offline',
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `last_logged_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COMMENT='Tabulky s uzivatelmi modulu users';

--
-- Sťahujem dáta pre tabuľku `erp_users`
--

INSERT INTO `erp_users` (`id`, `username`, `email`, `password`, `2fa_firewall`, `2fa_sms`, `2fa_sms_number_prefix`, `2fa_sms_number`, `2fa_sms_number_confirmed`, `2fa_auth`, `2fa_auth_secret`, `2fa_auth_secret_confirmed`, `status`, `created_at`, `updated_at`, `last_logged_at`) VALUES
(1, 'admin', 'admin@admin.admin', 'c143ed21b41fe0f7606b6cea5569a566c946b1b554e32e04004088fb072d6605', 1, 0, '+421', '944255644', 0, 0, 'AAQNAIADADQAAABA', 1, 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '2025-04-17 12:47:46');

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_firewall`
--

DROP TABLE IF EXISTS `erp_users_firewall`;
CREATE TABLE IF NOT EXISTS `erp_users_firewall` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `rule` varchar(50) NOT NULL COMMENT '// Pravidlo pre firewall. CIDR tvar. Napriklad 192.168.1.0/24',
  `action` int NOT NULL COMMENT '0 - Block, 1 - Allow',
  `active` int NOT NULL COMMENT '// Rule is active or inactive',
  `ordering` int NOT NULL COMMENT '// Poradie pravidla',
  PRIMARY KEY (`id`),
  KEY `ordering` (`ordering`),
  KEY `user_id` (`user_id`),
  KEY `user_id_2` (`user_id`,`active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

--
-- Sťahujem dáta pre tabuľku `erp_users_firewall`
--

INSERT INTO `erp_users_firewall` (`id`, `user_id`, `rule`, `action`, `active`, `ordering`) VALUES
(1, 1, '127.0.0.1', 1, 1, 1),
(2, 1, '0.0.0.0/0', 0, 1, 3),
(3, 1, '192.168.111.0/24', 1, 1, 2);

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_password_resets`
--

DROP TABLE IF EXISTS `erp_users_password_resets`;
CREATE TABLE IF NOT EXISTS `erp_users_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_rights`
--

DROP TABLE IF EXISTS `erp_users_rights`;
CREATE TABLE IF NOT EXISTS `erp_users_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `right_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`right_id`),
  KEY `user_id_2` (`user_id`),
  KEY `right_id` (`right_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

--
-- Sťahujem dáta pre tabuľku `erp_users_rights`
--

INSERT INTO `erp_users_rights` (`id`, `user_id`, `right_id`) VALUES
(2, 1, 1),
(3, 1, 4),
(4, 1, 17);

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_rights_groups`
--

DROP TABLE IF EXISTS `erp_users_rights_groups`;
CREATE TABLE IF NOT EXISTS `erp_users_rights_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL COMMENT '// Nazov grupy - Normalne textom',
  `ordering` int NOT NULL COMMENT '// Poradie',
  `creator` varchar(100) NOT NULL COMMENT '// Ktory modul to vytvoril pre odinstalaciu. Ak je prazdne tak je to vstavane defaultne do systemu',
  `editable` int NOT NULL COMMENT '// 0 - nesmie sa upravovat / 1 - moze sa upravovat',
  PRIMARY KEY (`id`),
  KEY `ordering` (`ordering`),
  KEY `creator` (`creator`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

--
-- Sťahujem dáta pre tabuľku `erp_users_rights_groups`
--

INSERT INTO `erp_users_rights_groups` (`id`, `name`, `ordering`, `creator`, `editable`) VALUES
(1, 'Vlastné', 1, 'custom', 1),
(2, 'Správa užívateľov', 2, 'users', 0),
(3, 'Správa systému', 3, 'system', 0),
(4, 'Dot CMS', 4, 'dotcms', 0);

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_rights_list`
--

DROP TABLE IF EXISTS `erp_users_rights_list`;
CREATE TABLE IF NOT EXISTS `erp_users_rights_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL COMMENT '// Id zoskupenia opravneni kedze kazdym odzul moze mat vlastnu skupinu nech v tom nie je bordel',
  `name` text NOT NULL COMMENT '// Nazov prava v dlhom formate',
  `description` text NOT NULL COMMENT '// Popis opravnenia v detailoch',
  `module` varchar(100) NOT NULL COMMENT '// Nazov modulu ktory pravo vytvoril',
  `rightname` varchar(100) NOT NULL COMMENT '// Opravnenie ',
  `active` int NOT NULL COMMENT '// 0 nie 1 ano',
  `ordering` int NOT NULL COMMENT '// Zoradenie',
  `creator` varchar(100) NOT NULL COMMENT '// Ktory modul vytvoril zoznam aby bolo mozne pri odinstalacii ho zmazat',
  `custom` int NOT NULL COMMENT '0 - nie, 1 - ano',
  PRIMARY KEY (`id`),
  KEY `module` (`module`),
  KEY `rightname` (`rightname`),
  KEY `module_2` (`module`,`rightname`),
  KEY `ordering` (`ordering`),
  KEY `rightname_2` (`rightname`,`active`,`ordering`),
  KEY `group_id` (`group_id`,`module`,`rightname`,`ordering`),
  KEY `id` (`id`,`active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3 COMMENT='Zoznam opravneni ktore je mozne uzivatelvi priradit';

--
-- Sťahujem dáta pre tabuľku `erp_users_rights_list`
--

INSERT INTO `erp_users_rights_list` (`id`, `group_id`, `name`, `description`, `module`, `rightname`, `active`, `ordering`, `creator`, `custom`) VALUES
(1, 3, 'Správa nastavení DOT ERP', 'Správa nastavení samotného systému DOT ERP - teda úplných detailov, inštalácie modulov a podobne. Užívateľ má právo vstupovať do tejto sekcie.', 'system', 'settings', 1, 1, 'system', 0),
(2, 2, 'Vytvoriť nového užívateľa', 'Užívateľ môže v administrácii vytvárať ďalších užívateľov.', 'users', 'create.user', 1, 1, 'users', 0),
(3, 2, 'Zobraziť zoznam užívateľov', 'Užívateľ môže vidieť zoznam užívateľov', 'users', 'list.show.all', 1, 2, 'users', 0),
(4, 2, 'Upraviť užívateľov', 'Užívateľ môže upraviť zoznam užívateľov', 'users', 'list.edit.all', 1, 3, 'users', 0),
(5, 3, 'Aktívny počas údržby', 'Pre užívateľa ostáva systém aktívny aj keď je v móde údržby.', 'system', 'settings.bypass.maintenance', 1, 2, 'system', 0),
(6, 2, 'Upraviť nastavenia modulu', 'Užívateľ môže meniť hlavné nastavenia modulu', 'users', 'edit.settings', 1, 4, 'users', 0),
(17, 4, 'Plné oprávnenia', 'Môže robiť čokoľvek v module DOT CMS', 'dotcms', 'full', 1, 1, 'dotcms', 0),
(18, 4, 'Spravovať jazyky', 'Povoliť spravovanie nastavenia jazykov', 'dotcms', 'languages', 1, 4, 'dotcms', 0),
(19, 4, 'Spravovať všetky články', 'Povoliť spravovanie všetkých článkov.', 'dotcms', 'articles.all', 1, 5, 'dotcms', 0),
(20, 4, 'Spravovať svoje články', 'Povoliť spravovanie článkov, ktoré užívateľ vytvoril.', 'dotcms', 'articles.created', 1, 6, 'dotcms', 0),
(21, 4, 'Spravovať všetky webstránky', 'Povoliť správu všetkých webstránok. ', 'dotcms', 'websites.all', 1, 2, 'dotcms', 0),
(22, 4, 'Spravovať vybrané webstránky', 'Povoliť správu vybraných webstránok. ', 'dotcms', 'websites.selected', 1, 3, 'dotcms', 0);

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_roles`
--

DROP TABLE IF EXISTS `erp_users_roles`;
CREATE TABLE IF NOT EXISTS `erp_users_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `id_roly` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf16;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_roles_list`
--

DROP TABLE IF EXISTS `erp_users_roles_list`;
CREATE TABLE IF NOT EXISTS `erp_users_roles_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf16 NOT NULL,
  `description` text CHARACTER SET utf16,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_roles_rights`
--

DROP TABLE IF EXISTS `erp_users_roles_rights`;
CREATE TABLE IF NOT EXISTS `erp_users_roles_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `right_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_right_role` (`right_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf16;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_sessions`
--

DROP TABLE IF EXISTS `erp_users_sessions`;
CREATE TABLE IF NOT EXISTS `erp_users_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `erp_users_url_firewall`
--

DROP TABLE IF EXISTS `erp_users_url_firewall`;
CREATE TABLE IF NOT EXISTS `erp_users_url_firewall` (
  `id` int NOT NULL,
  `user` int NOT NULL,
  `url` varchar(200) NOT NULL COMMENT '// Url moze byt s * napriklad moze byt * - to znamena vsetky adresy blokneme. Alebo blokneme len */uzivatelia/* takze ak je v UR! /uzivatelia/ tak blokneme alebo naopak povolime',
  `action` int NOT NULL COMMENT '0-Blokni / 1 - Povol',
  `active` int NOT NULL COMMENT '// Pravidlo je aktivovane alebo deaktivovane'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Obmedzenie pre exportované tabuľky
--

--
-- Obmedzenie pre tabuľku `erp_users_firewall`
--
ALTER TABLE `erp_users_firewall`
  ADD CONSTRAINT `users_vs_firewall` FOREIGN KEY (`user_id`) REFERENCES `erp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_password_resets`
--
ALTER TABLE `erp_users_password_resets`
  ADD CONSTRAINT `erp_users_password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `erp_users` (`id`) ON DELETE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_rights`
--
ALTER TABLE `erp_users_rights`
  ADD CONSTRAINT `pravo_id` FOREIGN KEY (`right_id`) REFERENCES `erp_users_rights_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `uziv_id` FOREIGN KEY (`user_id`) REFERENCES `erp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_rights_list`
--
ALTER TABLE `erp_users_rights_list`
  ADD CONSTRAINT `erp_users_rights_list_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `erp_users_rights_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_roles`
--
ALTER TABLE `erp_users_roles`
  ADD CONSTRAINT `id_roly` FOREIGN KEY (`role_id`) REFERENCES `erp_users_roles_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uzivatelove_id` FOREIGN KEY (`user_id`) REFERENCES `erp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_roles_rights`
--
ALTER TABLE `erp_users_roles_rights`
  ADD CONSTRAINT `erp_users_roles_rights_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `erp_users_roles_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `erp_users_roles_rights_ibfk_2` FOREIGN KEY (`right_id`) REFERENCES `erp_users_rights_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Obmedzenie pre tabuľku `erp_users_sessions`
--
ALTER TABLE `erp_users_sessions`
  ADD CONSTRAINT `erp_users_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `erp_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
