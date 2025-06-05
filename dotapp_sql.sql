SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `dotapp_users`;
CREATE TABLE IF NOT EXISTS `dotapp_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Uzivatelske meno',
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Email, moze sa pouzit na prihlasenie tiez. Moze sa pouzivat na emailove notifikacie',
  `password` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Heslo',
  `tfa_firewall` int NOT NULL COMMENT '// Pouzit alebo nepouzit firewall',
  `tfa_sms` int NOT NULL COMMENT '// Pouzivame 2faktor cez SMS?',
  `tfa_sms_number_prefix` varchar(8) COLLATE utf8mb4_general_ci NOT NULL,
  `tfa_sms_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Cislo pre zaslanie SMS',
  `tfa_sms_number_confirmed` int NOT NULL COMMENT '// Cislo potvrdene zadanim kodu',
  `tfa_auth` int NOT NULL COMMENT '// Pouzivame 2 faktor cez GOOGLE AUTH ?',
  `tfa_auth_secret` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Ak amme google auth, tak treba drzat ulozeny secret ',
  `tfa_auth_secret_confirmed` int NOT NULL COMMENT '// Bolo potvrdene 2FA auth?',
  `tfa_email` int NOT NULL COMMENT '// Pouzivame 2 faktor cez e-mail?',
  `status` int NOT NULL COMMENT '// Status prihlasenia. 1 - Aktivny, 2-DLhsie neaktivny, 3 - Offline',
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `last_logged_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabulky s uzivatelmi modulu users';


DROP TABLE IF EXISTS `dotapp_users_firewall`;
CREATE TABLE IF NOT EXISTS `dotapp_users_firewall` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `rule` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Pravidlo pre firewall. CIDR tvar. Napriklad 192.168.1.0/24',
  `action` int NOT NULL COMMENT '0 - Block, 1 - Allow',
  `active` int NOT NULL COMMENT '// Rule is active or inactive',
  `ordering` int NOT NULL COMMENT '// Poradie pravidla',
  PRIMARY KEY (`id`),
  KEY `ordering` (`ordering`),
  KEY `user_id` (`user_id`),
  KEY `user_id_2` (`user_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_password_resets`;
CREATE TABLE IF NOT EXISTS `dotapp_users_password_resets` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_rights`;
CREATE TABLE IF NOT EXISTS `dotapp_users_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `right_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`right_id`),
  KEY `user_id_2` (`user_id`),
  KEY `right_id` (`right_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_rights_groups`;
CREATE TABLE IF NOT EXISTS `dotapp_users_rights_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` mediumtext COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Nazov grupy - Normalne textom',
  `ordering` int NOT NULL COMMENT '// Poradie',
  `creator` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT '// Ktory modul to vytvoril pre odinstalaciu. Ak je prazdne tak je to vstavane defaultne do systemu',
  `editable` int NOT NULL COMMENT '// 0 - nesmie sa upravovat / 1 - moze sa upravovat',
  PRIMARY KEY (`id`),
  KEY `ordering` (`ordering`),
  KEY `creator` (`creator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_rights_list`;
CREATE TABLE IF NOT EXISTS `dotapp_users_rights_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL COMMENT '// Id zoskupenia opravneni kedze kazdym odzul moze mat vlastnu skupinu nech v tom nie je bordel',
  `name` text CHARACTER SET utf8mb3 NOT NULL COMMENT '// Nazov prava v dlhom formate',
  `description` text CHARACTER SET utf8mb3 NOT NULL COMMENT '// Popis opravnenia v detailoch',
  `module` varchar(100) CHARACTER SET utf8mb3 NOT NULL COMMENT '// Nazov modulu ktory pravo vytvoril',
  `rightname` varchar(100) CHARACTER SET utf8mb3 NOT NULL COMMENT '// Opravnenie ',
  `active` int NOT NULL COMMENT '// 0 nie 1 ano',
  `ordering` int NOT NULL COMMENT '// Zoradenie',
  `creator` varchar(100) CHARACTER SET utf8mb3 NOT NULL COMMENT '// Ktory modul vytvoril zoznam aby bolo mozne pri odinstalacii ho zmazat',
  `custom` int NOT NULL COMMENT '0 - nie, 1 - ano',
  PRIMARY KEY (`id`),
  KEY `module` (`module`),
  KEY `rightname` (`rightname`),
  KEY `module_2` (`module`,`rightname`),
  KEY `ordering` (`ordering`),
  KEY `rightname_2` (`rightname`,`active`,`ordering`),
  KEY `group_id` (`group_id`,`module`,`rightname`,`ordering`),
  KEY `id` (`id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Zoznam opravneni ktore je mozne uzivatelvi priradit';

DROP TABLE IF EXISTS `dotapp_users_rmtokens`;
CREATE TABLE IF NOT EXISTS `dotapp_users_rmtokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `dotapp_users_sessions_ibfk_1` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_roles`;
CREATE TABLE IF NOT EXISTS `dotapp_users_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `id_roly` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_roles_list`;
CREATE TABLE IF NOT EXISTS `dotapp_users_roles_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf16 NOT NULL,
  `description` text CHARACTER SET utf16,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_roles_rights`;
CREATE TABLE IF NOT EXISTS `dotapp_users_roles_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `right_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_right_role` (`right_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_sessions`;
CREATE TABLE IF NOT EXISTS `dotapp_users_sessions` (
  `session_id` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `sessname` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `values` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `variables` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `expiry` bigint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`,`sessname`),
  KEY `idx_expiry` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `dotapp_users_url_firewall`;
CREATE TABLE IF NOT EXISTS `dotapp_users_url_firewall` (
  `id` int NOT NULL,
  `user` int NOT NULL,
  `url` varchar(200) CHARACTER SET utf8mb3 NOT NULL COMMENT '// Url moze byt s * napriklad moze byt * - to znamena vsetky adresy blokneme. Alebo blokneme len */uzivatelia/* takze ak je v UR! /uzivatelia/ tak blokneme alebo naopak povolime',
  `action` int NOT NULL COMMENT '0-Blokni / 1 - Povol',
  `active` int NOT NULL COMMENT '// Pravidlo je aktivovane alebo deaktivovane'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `dotapp_users_firewall`
  ADD CONSTRAINT `users_vs_firewall` FOREIGN KEY (`user_id`) REFERENCES `dotapp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dotapp_users_password_resets`
  ADD CONSTRAINT `dotapp_users_password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `dotapp_users` (`id`) ON DELETE CASCADE;

ALTER TABLE `dotapp_users_rights`
  ADD CONSTRAINT `pravo_id` FOREIGN KEY (`right_id`) REFERENCES `dotapp_users_rights_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `uziv_id` FOREIGN KEY (`user_id`) REFERENCES `dotapp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dotapp_users_rights_list`
  ADD CONSTRAINT `dotapp_users_rights_list_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `dotapp_users_rights_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dotapp_users_rmtokens`
  ADD CONSTRAINT `dotapp_users_rmtokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `dotapp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dotapp_users_roles`
  ADD CONSTRAINT `id_roly` FOREIGN KEY (`role_id`) REFERENCES `dotapp_users_roles_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uzivatelove_id` FOREIGN KEY (`user_id`) REFERENCES `dotapp_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dotapp_users_roles_rights`
  ADD CONSTRAINT `dotapp_users_roles_rights_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `dotapp_users_roles_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `dotapp_users_roles_rights_ibfk_2` FOREIGN KEY (`right_id`) REFERENCES `dotapp_users_rights_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;
