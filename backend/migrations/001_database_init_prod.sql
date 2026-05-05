-- Production Database Initialization for Mytherra

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `bet_target_modifiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `target_type` varchar(50) NOT NULL,
  `bet_type` varchar(50) NOT NULL,
  `condition_field` varchar(50) NOT NULL,
  `condition_value` decimal(8,2) NOT NULL,
  `comparison_operator` varchar(10) NOT NULL,
  `modifier_value` decimal(4,2) NOT NULL,
  `modifier_type` enum('multiply','add') DEFAULT 'multiply',
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_bet_type` (`target_type`,`bet_type`),
  KEY `idx_condition_field` (`condition_field`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `bet_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `base_odds` decimal(4,2) NOT NULL,
  `min_timeframe` int NOT NULL DEFAULT '1',
  `max_timeframe` int NOT NULL DEFAULT '50',
  `resolve_conditions` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `betting_system_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `building_condition_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_condition` int NOT NULL,
  `max_condition` int NOT NULL,
  `color_code` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#666666',
  `maintenance_multiplier` decimal(3,2) NOT NULL DEFAULT '1.00',
  `productivity_multiplier` decimal(3,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_condition_levels_code_unique` (`code`),
  KEY `building_condition_levels_code_index` (`code`),
  KEY `building_condition_levels_is_active_index` (`is_active`),
  KEY `building_condition_levels_min_condition_max_condition_index` (`min_condition`,`max_condition`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `building_special_properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `effects` json DEFAULT NULL,
  `rarity` enum('common','uncommon','rare','legendary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'common',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_special_properties_code_unique` (`code`),
  KEY `building_special_properties_code_index` (`code`),
  KEY `building_special_properties_is_active_index` (`is_active`),
  KEY `building_special_properties_category_index` (`category`),
  KEY `building_special_properties_rarity_index` (`rarity`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `building_statuses` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `productivity_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `maintenance_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_statuses_code_unique` (`code`),
  KEY `building_statuses_is_active_index` (`is_active`),
  KEY `building_statuses_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `building_type_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_cost` int NOT NULL,
  `maintenance` int NOT NULL,
  `prosperity_bonus` int DEFAULT NULL,
  `defensibility_bonus` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_type_configs_code_unique` (`code`),
  KEY `building_type_configs_category_code_index` (`category`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `building_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_cost` int NOT NULL DEFAULT '100',
  `maintenance_cost` int NOT NULL DEFAULT '10',
  `prosperity_bonus` int NOT NULL DEFAULT '0',
  `defensibility_bonus` int NOT NULL DEFAULT '0',
  `special_properties` json DEFAULT NULL,
  `prerequisites` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_types_code_unique` (`code`),
  KEY `building_types_is_active_index` (`is_active`),
  KEY `building_types_code_index` (`code`),
  KEY `building_types_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `buildings` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settlement_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition` int NOT NULL DEFAULT '100',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `level` int NOT NULL DEFAULT '1',
  `specialProperties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `buildings_settlement_id_index` (`settlement_id`),
  KEY `buildings_type_index` (`type`),
  KEY `buildings_status_index` (`status`),
  KEY `buildings_condition_index` (`condition`),
  KEY `buildings_level_index` (`level`),
  CONSTRAINT `buildings_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `confidence_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `odds_modifier` decimal(4,2) NOT NULL,
  `stake_multiplier` decimal(4,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `divine_bets` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bet_type` enum('settlement_growth','landmark_discovery','cultural_shift','hero_settlement_bond','hero_location_visit','settlement_transformation','corruption_spread') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `timeframe` int NOT NULL,
  `confidence` enum('long_shot','possible','likely','near_certain') COLLATE utf8mb4_unicode_ci NOT NULL,
  `divine_favor_stake` int NOT NULL,
  `potential_payout` int NOT NULL,
  `current_odds` decimal(10,2) NOT NULL,
  `status` enum('active','won','lost','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `placed_year` int NOT NULL,
  `resolved_year` int DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_divine_bets_player_id` (`player_id`),
  KEY `idx_divine_bets_bet_type` (`bet_type`),
  KEY `idx_divine_bets_target_id` (`target_id`),
  KEY `idx_divine_bets_status` (`status`),
  KEY `idx_divine_bets_confidence` (`confidence`),
  KEY `idx_divine_bets_placed_year` (`placed_year`),
  KEY `idx_divine_bets_timeframe` (`timeframe`),
  CONSTRAINT `divine_bets_chk_1` CHECK (((`timeframe` >= 1) and (`timeframe` <= 50))),
  CONSTRAINT `divine_bets_chk_2` CHECK (((`divine_favor_stake` >= 1) and (`divine_favor_stake` <= 1000))),
  CONSTRAINT `divine_bets_chk_3` CHECK ((`current_odds` >= 1.1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evolution_parameters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parameter` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(8,4) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evolution_parameters_parameter_unique` (`parameter`),
  KEY `evolution_parameters_parameter_index` (`parameter`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_configs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` enum('number','string','boolean','array') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_configs_category_key_unique` (`category`,`key`),
  KEY `game_configs_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_events` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `region_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_region_ids` json DEFAULT NULL,
  `related_hero_ids` json DEFAULT NULL,
  `year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_events_region_id_index` (`region_id`),
  KEY `game_events_type_index` (`type`),
  KEY `game_events_status_index` (`status`),
  KEY `game_events_year_index` (`year`),
  CONSTRAINT `game_events_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_initial_configs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subcategory` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` enum('number','string','boolean','array') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_initial_configs_category_subcategory_key_unique` (`category`,`subcategory`,`key`),
  KEY `game_initial_configs_category_subcategory_index` (`category`,`subcategory`),
  KEY `game_initial_configs_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `game_states` (
  `singleton_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_year` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`singleton_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_death_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'combat',
  `severity` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hero_death_reasons_code_unique` (`code`),
  KEY `hero_death_reasons_code_index` (`code`),
  KEY `hero_death_reasons_is_active_index` (`is_active`),
  KEY `hero_death_reasons_category_severity_index` (`category`,`severity`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_event_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subcategory` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hero_event_messages_code_unique` (`code`),
  KEY `hero_event_messages_code_index` (`code`),
  KEY `hero_event_messages_is_active_index` (`is_active`),
  KEY `hero_event_messages_category_subcategory_index` (`category`,`subcategory`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_roles` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `primary_attributes` json DEFAULT NULL,
  `special_abilities` json DEFAULT NULL,
  `starting_level_range` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hero_roles_code_unique` (`code`),
  KEY `hero_roles_is_active_index` (`is_active`),
  KEY `hero_roles_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_settlement_interaction_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `base_duration` int NOT NULL DEFAULT '1',
  `success_chance` decimal(5,2) NOT NULL DEFAULT '0.50',
  `influence_cost` int NOT NULL DEFAULT '0',
  `cooldown_hours` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hero_settlement_interaction_types_code_unique` (`code`),
  KEY `hero_settlement_interaction_types_is_active_index` (`is_active`),
  KEY `hero_settlement_interaction_types_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_settlement_interactions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hero_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settlement_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_year` int NOT NULL,
  `duration` int NOT NULL DEFAULT '1',
  `success` tinyint(1) DEFAULT NULL,
  `outcome_description` text COLLATE utf8mb4_unicode_ci,
  `interaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hero_settlement_interactions_hero_id_index` (`hero_id`),
  KEY `hero_settlement_interactions_settlement_id_index` (`settlement_id`),
  KEY `hero_settlement_interactions_landmark_id_index` (`landmark_id`),
  KEY `hero_settlement_interactions_interaction_type_index` (`interaction_type`),
  KEY `hero_settlement_interactions_started_year_duration_index` (`started_year`,`duration`),
  CONSTRAINT `hero_settlement_interactions_hero_id_foreign` FOREIGN KEY (`hero_id`) REFERENCES `heroes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hero_settlement_interactions_interaction_type_foreign` FOREIGN KEY (`interaction_type`) REFERENCES `hero_settlement_interaction_types` (`code`),
  CONSTRAINT `hero_settlement_interactions_landmark_id_foreign` FOREIGN KEY (`landmark_id`) REFERENCES `landmarks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hero_settlement_interactions_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `heroes` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `feats` json DEFAULT NULL,
  `influence_last_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int NOT NULL DEFAULT '1',
  `is_alive` tinyint(1) NOT NULL DEFAULT '1',
  `age` int NOT NULL DEFAULT '20',
  `death_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personality_traits` json DEFAULT NULL,
  `alignment` json DEFAULT NULL,
  `status` enum('living','deceased','undead','ascended') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `heroes_region_id_index` (`region_id`),
  KEY `heroes_role_index` (`role`),
  KEY `heroes_is_alive_index` (`is_alive`),
  KEY `heroes_status_index` (`status`),
  KEY `heroes_level_index` (`level`),
  CONSTRAINT `heroes_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `heroes_role_foreign` FOREIGN KEY (`role`) REFERENCES `hero_roles` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `influence_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `target_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `influence_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `strength` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `effects` json DEFAULT NULL,
  `game_year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `influence_history_target_type_target_id_index` (`target_type`,`target_id`),
  KEY `influence_history_game_year_index` (`game_year`),
  KEY `influence_history_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landmark_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `magic_modifier` int NOT NULL DEFAULT '0',
  `danger_modifier` int NOT NULL DEFAULT '0',
  `exploration_difficulty_modifier` int NOT NULL DEFAULT '0',
  `special_effects` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `landmark_statuses_code_unique` (`code`),
  KEY `landmark_statuses_code_index` (`code`),
  KEY `landmark_statuses_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landmark_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_magic_level` int NOT NULL DEFAULT '0',
  `base_danger_level` int NOT NULL DEFAULT '0',
  `discovery_difficulty` int NOT NULL DEFAULT '50',
  `exploration_rewards` json DEFAULT NULL,
  `special_properties` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `landmark_types_code_unique` (`code`),
  KEY `landmark_types_code_index` (`code`),
  KEY `landmark_types_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landmarks` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pristine',
  `magic_level` int NOT NULL DEFAULT '0',
  `danger_level` int NOT NULL DEFAULT '0',
  `discovered_year` int DEFAULT NULL,
  `last_visited_year` int DEFAULT NULL,
  `associated_events` json DEFAULT NULL,
  `traits` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `landmarks_region_id_index` (`region_id`),
  KEY `landmarks_type_index` (`type`),
  KEY `landmarks_status_index` (`status`),
  KEY `landmarks_magic_level_index` (`magic_level`),
  KEY `landmarks_danger_level_index` (`danger_level`),
  CONSTRAINT `landmarks_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `players` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `divine_favor` int NOT NULL DEFAULT '100',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `region_climate_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `resource_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `population_growth_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `region_climate_types_code_unique` (`code`),
  KEY `region_climate_types_is_active_index` (`is_active`),
  KEY `region_climate_types_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `region_cultural_influences` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `hero_spawn_rate_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `development_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `stability_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `region_cultural_influences_code_unique` (`code`),
  KEY `region_cultural_influences_is_active_index` (`is_active`),
  KEY `region_cultural_influences_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `region_statuses` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `hero_spawn_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `prosperity_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `chaos_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `region_statuses_code_unique` (`code`),
  KEY `region_statuses_is_active_index` (`is_active`),
  KEY `region_statuses_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `regions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prosperity` int NOT NULL DEFAULT '50',
  `chaos` int NOT NULL DEFAULT '0',
  `magic_affinity` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'peaceful',
  `event_ids` json DEFAULT NULL,
  `influence_last_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `danger_level` int NOT NULL DEFAULT '5',
  `tags` json DEFAULT NULL,
  `population_total` int NOT NULL DEFAULT '0',
  `regional_traits` json DEFAULT NULL,
  `climate_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'temperate',
  `trade_routes` json DEFAULT NULL,
  `cultural_influence` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pastoral',
  `divine_resonance` int NOT NULL DEFAULT '50',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `regions_status_index` (`status`),
  KEY `regions_climate_type_index` (`climate_type`),
  KEY `regions_cultural_influence_index` (`cultural_influence`),
  CONSTRAINT `regions_climate_type_foreign` FOREIGN KEY (`climate_type`) REFERENCES `region_climate_types` (`code`),
  CONSTRAINT `regions_cultural_influence_foreign` FOREIGN KEY (`cultural_influence`) REFERENCES `region_cultural_influences` (`code`),
  CONSTRAINT `regions_status_foreign` FOREIGN KEY (`status`) REFERENCES `region_statuses` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resource_node_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_modifier` decimal(4,2) NOT NULL DEFAULT '1.00',
  `extraction_difficulty_modifier` int NOT NULL DEFAULT '0',
  `can_harvest` tinyint(1) NOT NULL DEFAULT '1',
  `special_effects` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_node_statuses_code_unique` (`code`),
  KEY `resource_node_statuses_code_index` (`code`),
  KEY `resource_node_statuses_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resource_node_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_output` int NOT NULL DEFAULT '50',
  `extraction_difficulty` int NOT NULL DEFAULT '50',
  `renewal_rate` int NOT NULL DEFAULT '0',
  `properties` json DEFAULT NULL,
  `resource_category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_node_types_code_unique` (`code`),
  KEY `resource_node_types_code_index` (`code`),
  KEY `resource_node_types_is_active_index` (`is_active`),
  KEY `resource_node_types_resource_category_index` (`resource_category`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resource_nodes` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settlement_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_nodes_region_id_index` (`region_id`),
  KEY `resource_nodes_settlement_id_index` (`settlement_id`),
  KEY `resource_nodes_type_index` (`type`),
  KEY `resource_nodes_status_index` (`status`),
  CONSTRAINT `resource_nodes_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resource_nodes_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settlement_evolution_thresholds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `settlement_type` varchar(50) NOT NULL,
  `next_type` varchar(50) NOT NULL,
  `min_population` int NOT NULL,
  `min_prosperity` int NOT NULL,
  `min_influence` int NOT NULL,
  `required_buildings` text,
  `evolution_time_days` int NOT NULL,
  `divine_favor_cost` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`settlement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_evolution_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_weight` int DEFAULT '10',
  `prosperity_threshold` int DEFAULT NULL,
  `prosperity_modifier` decimal(4,2) DEFAULT NULL,
  `population_modifier` decimal(4,2) DEFAULT NULL,
  `defensibility_modifier` decimal(4,2) DEFAULT NULL,
  `regional_requirements` json DEFAULT NULL,
  `settlement_requirements` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_growth_modifiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modifier_type` varchar(50) NOT NULL,
  `condition_type` varchar(50) NOT NULL,
  `condition_value` varchar(50) NOT NULL,
  `population_modifier` decimal(4,2) DEFAULT '1.00',
  `prosperity_modifier` decimal(4,2) DEFAULT '1.00',
  `influence_modifier` decimal(4,2) DEFAULT '1.00',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type_condition` (`modifier_type`,`condition_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_prosperity_factors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `factor_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_impact` decimal(4,2) NOT NULL,
  `impact_interval_hours` int NOT NULL,
  `stack_type` enum('additive','multiplicative') DEFAULT 'additive',
  `max_stacks` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `factor_code` (`factor_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_specializations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_prosperity_bonus` decimal(4,2) DEFAULT '0.00',
  `base_population_bonus` decimal(4,2) DEFAULT '0.00',
  `weight` int DEFAULT '10',
  `requirements` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_statuses` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `prosperity_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `growth_modifier` decimal(5,2) NOT NULL DEFAULT '1.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_statuses_code_unique` (`code`),
  KEY `settlement_statuses_is_active_index` (`is_active`),
  KEY `settlement_statuses_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settlement_traits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_defensibility_bonus` decimal(4,2) DEFAULT '0.00',
  `base_prosperity_bonus` decimal(4,2) DEFAULT '0.00',
  `weight` int DEFAULT '10',
  `biome_restrictions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `settlement_type_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_population` int NOT NULL,
  `max_population` int NOT NULL,
  `min_buildings` int NOT NULL,
  `max_buildings` int NOT NULL,
  `base_defensibility` int NOT NULL,
  `evolution_threshold` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_type_configs_code_unique` (`code`),
  KEY `settlement_type_configs_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settlement_types` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_population` int NOT NULL DEFAULT '0',
  `max_population` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_types_code_unique` (`code`),
  KEY `settlement_types_is_active_index` (`is_active`),
  KEY `settlement_types_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settlements` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `population` int NOT NULL DEFAULT '0',
  `prosperity` int NOT NULL DEFAULT '50',
  `defensibility` int NOT NULL DEFAULT '25',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stable',
  `specializations` json DEFAULT NULL,
  `events` json DEFAULT NULL,
  `founded_year` int NOT NULL,
  `last_event_year` int DEFAULT NULL,
  `traits` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settlements_region_id_index` (`region_id`),
  KEY `settlements_type_index` (`type`),
  KEY `settlements_status_index` (`status`),
  KEY `settlements_population_index` (`population`),
  KEY `settlements_prosperity_index` (`prosperity`),
  CONSTRAINT `settlements_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `settlements_status_foreign` FOREIGN KEY (`status`) REFERENCES `settlement_statuses` (`code`) ON DELETE RESTRICT,
  CONSTRAINT `settlements_type_foreign` FOREIGN KEY (`type`) REFERENCES `settlement_types` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `timeframe_modifiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `max_timeframe` int NOT NULL,
  `modifier` decimal(4,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_max_timeframe` (`max_timeframe`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `auth_user_id` bigint unsigned DEFAULT NULL,
  `auth0_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `divine_influence` int NOT NULL DEFAULT '100',
  `divine_favor` int NOT NULL DEFAULT '100',
  `level` int NOT NULL DEFAULT '1',
  `experience` int NOT NULL DEFAULT '0',
  `character_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'novice',
  `guild_id` bigint unsigned DEFAULT NULL,
  `guild_rank` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `betting_stats` json DEFAULT NULL,
  `game_preferences` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auth_user_id` (`auth_user_id`),
  KEY `idx_auth0_id` (`auth0_id`),
  KEY `users_auth_user_id_index` (`auth_user_id`),
  KEY `users_auth0_id_index` (`auth0_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Initial Data for Mytherra Production
-- Region Climate Types
INSERT INTO region_climate_types (`id`, `name`, `code`, `description`, `resource_modifier`, `population_growth_modifier`, `is_active`) VALUES
('climate-temperate', 'Temperate', 'temperate', 'Moderate climate with balanced seasons', 1.0, 1.0, 1),
('climate-arctic', 'Arctic', 'arctic', 'Extremely cold climate with harsh winters', 0.7, 0.6, 1),
('climate-tropical', 'Tropical', 'tropical', 'Hot and humid climate with abundant rainfall', 1.3, 1.2, 1),
('climate-arid', 'Arid', 'arid', 'Hot and dry climate with scarce rainfall', 0.6, 0.8, 1),
('climate-magical', 'Magical', 'magical', 'Climate infused with arcane energy', 1.5, 0.9, 1);
-- Region Cultural Influences
INSERT INTO region_cultural_influences (`id`, `name`, `code`, `description`, `hero_spawn_rate_modifier`, `development_modifier`, `stability_modifier`, `is_active`) VALUES
('culture-pastoral', 'Pastoral', 'pastoral', 'Rural culture focused on agriculture and animal husbandry', 0.8, 0.7, 1.2, 1),
('culture-mercantile', 'Mercantile', 'mercantile', 'Trade-focused culture with emphasis on commerce', 1.1, 1.3, 0.9, 1),
('culture-martial', 'Martial', 'martial', 'Warrior culture with strong military traditions', 1.4, 0.8, 0.7, 1),
('culture-mystical', 'Mystical', 'mystical', 'Magic-focused culture with arcane traditions', 1.2, 1.0, 0.8, 1),
('culture-nomadic', 'Nomadic', 'nomadic', 'Mobile culture with minimal permanent settlements', 1.0, 0.6, 0.5, 1),
('culture-scholarly', 'Scholarly', 'scholarly', 'Academic culture focused on knowledge and learning', 1.1, 1.3, 1.1, 1);
-- Region Statuses
INSERT INTO region_statuses (`id`, `name`, `code`, `description`, `hero_spawn_modifier`, `prosperity_modifier`, `chaos_modifier`, `is_active`) VALUES
('status_flourishing', 'Flourishing', 'flourishing', 'A region experiencing exceptional prosperity and growth', 1.5, 1.4, 0.6, 1),
('status_prosperous', 'Prosperous', 'prosperous', 'A thriving region with strong economy and stability', 1.2, 1.2, 0.8, 1),
('status_stable', 'Stable', 'stable', 'A well-balanced region with steady development', 1.0, 1.0, 1.0, 1),
('status_turbulent', 'Turbulent', 'turbulent', 'A region facing social or political unrest', 1.3, 0.8, 1.4, 1),
('status_declining', 'Declining', 'declining', 'A region experiencing economic or social deterioration', 0.9, 0.7, 1.3, 1),
('status_war_torn', 'War-Torn', 'war_torn', 'A region devastated by conflict and violence', 1.8, 0.4, 2.0, 1),
('status_abandoned', 'Abandoned', 'abandoned', 'A forsaken region with minimal civilization', 0.3, 0.2, 0.5, 1),
('status_mysterious', 'Mysterious', 'mysterious', 'A region shrouded in unknown forces and strange phenomena', 1.1, 0.9, 1.6, 1),
('status_blessed', 'Blessed', 'blessed', 'A region favored by divine intervention and good fortune', 1.4, 1.3, 0.7, 1),
('status_cursed', 'Cursed', 'cursed', 'A region afflicted by dark forces and misfortune', 1.6, 0.6, 1.8, 1);
-- Regions
INSERT INTO regions (`id`, `name`, `color`, `prosperity`, `chaos`, `magic_affinity`, `status`, `event_ids`, `influence_last_action`, `danger_level`, `tags`, `population_total`, `regional_traits`, `climate_type`, `trade_routes`, `cultural_influence`, `divine_resonance`) VALUES
('region-001', 'Arcane Highlands', '#8B4513', 65, 35, 80, 'mysterious', '[]', NULL, 30, '[\"magical\", \"mountainous\", \"scholarly\"]', 5000, '[\"arcane_nexus\", \"high_elevation\", \"ancient_ruins\"]', 'temperate', NULL, 'scholarly', 50),
('region-002', 'Merchant''s Haven', '#DAA520', 85, 20, 40, 'prosperous', '[]', NULL, 15, '[\"commercial\", \"coastal\", \"wealthy\"]', 12000, '[\"trade_hub\", \"coastal_bounty\", \"cultural_melting_pot\"]', 'temperate', NULL, 'mercantile', 50),
('region-003', 'Mystic Vale', '#228B22', 55, 45, 90, 'mysterious', '[]', NULL, 50, '[\"mystical\", \"verdant\", \"mysterious\"]', 3000, '[\"ley_line_convergence\", \"enchanted_forest\", \"ancient_mysteries\"]', 'magical', NULL, 'mystical', 50);
-- Settlement Statuses
INSERT INTO settlement_statuses (`id`, `name`, `code`, `description`, `prosperity_modifier`, `growth_modifier`, `is_active`) VALUES
('status_thriving', 'Thriving', 'thriving', 'A settlement experiencing rapid growth and prosperity', 1.5, 1.3, 1),
('status_prosperous', 'Prosperous', 'prosperous', 'A thriving settlement with strong economy and growth', 1.2, 1.1, 1),
('status_stable', 'Stable', 'stable', 'A well-maintained settlement with steady development', 1.0, 1.0, 1),
('status_declining', 'Declining', 'declining', 'A settlement facing economic or social challenges', 0.8, 0.9, 1),
('status_struggling', 'Struggling', 'struggling', 'A settlement dealing with significant hardships', 0.6, 0.7, 1),
('status_abandoned', 'Abandoned', 'abandoned', 'A deserted settlement with no active population', 0.0, 0.0, 1),
('status_ruined', 'Ruined', 'ruined', 'A settlement destroyed or fallen into complete disrepair', 0.0, 0.0, 1);
-- Settlement Types
INSERT INTO settlement_types (`id`, `name`, `code`, `description`, `min_population`, `max_population`, `is_active`) VALUES
('type-hamlet', 'Hamlet', 'hamlet', 'A small rural settlement with a few families', 1, 100, 1),
('type-village', 'Village', 'village', 'A small settlement with basic amenities', 101, 500, 1),
('type-town', 'Town', 'town', 'A medium-sized settlement with diverse services', 501, 2000, 1),
('type-city', 'City', 'city', 'A large settlement with significant infrastructure', 2001, 10000, 1),
('type-metropolis', 'Metropolis', 'metropolis', 'A major urban center of great importance', 10001, NULL, 1),
('type-outpost', 'Outpost', 'outpost', 'A small frontier settlement', 5, 50, 1),
('type-stronghold', 'Stronghold', 'stronghold', 'A fortified settlement focused on defense', 100, 1000, 1);
-- Settlements
INSERT INTO settlements (`id`, `region_id`, `name`, `type`, `population`, `prosperity`, `defensibility`, `status`, `specializations`, `events`, `founded_year`, `last_event_year`, `traits`) VALUES
('settlement-001', 'region-001', 'Crystalhaven', 'city', 3000, 75, 65, 'prosperous', '[\"magic_research\", \"trade\"]', '[]', 1, NULL, '[\"academic\", \"fortified\", \"affluent\"]'),
('settlement-002', 'region-001', 'Fellwood Village', 'village', 500, 45, 30, 'stable', '[\"farming\", \"woodworking\"]', '[]', 1, NULL, '[\"rural\", \"agricultural\"]'),
('settlement-003', 'region-001', 'Arcane Observatory', 'hamlet', 100, 60, 45, 'prosperous', '[\"magic_research\", \"astrology\"]', '[]', 1, NULL, '[\"magical\", \"research\"]');
-- Hero Roles
INSERT INTO hero_roles (`id`, `name`, `code`, `description`, `primary_attributes`, `special_abilities`, `starting_level_range`, `is_active`) VALUES
('role-warrior', 'Warrior', 'warrior', 'A skilled fighter who excels in combat and military leadership', '[\"strength\", \"constitution\", \"leadership\"]', '[\"combat_prowess\", \"tactical_knowledge\", \"inspire_troops\"]', '{\"min\": 1, \"max\": 10}', 1),
('role-scholar', 'Scholar', 'scholar', 'A seeker of knowledge and wisdom, scholars excel at research and understanding the world', '[\"intelligence\", \"wisdom\", \"research\"]', '[\"arcane_knowledge\", \"research_boost\", \"decipher_mysteries\"]', '{\"min\": 1, \"max\": 8}', 1),
('role-prophet', 'Prophet', 'prophet', 'A spiritual leader with divine insights, prophets guide communities through spiritual matters', '[\"wisdom\", \"charisma\", \"intuition\"]', '[\"divine_insight\", \"prophecy\", \"inspire_faith\"]', '{\"min\": 1, \"max\": 10}', 1),
('role-agent-of-change', 'Agent of Change', 'agent of change', 'A catalyst for transformation, these heroes drive social or political evolution', '[\"charisma\", \"intelligence\", \"leadership\"]', '[\"inspire_change\", \"social_influence\", \"rally_support\"]', '{\"min\": 1, \"max\": 10}', 1);
-- Heroes
INSERT INTO heroes (`id`, `name`, `region_id`, `role`, `description`, `feats`, `influence_last_action`, `level`, `is_alive`, `age`, `death_reason`, `personality_traits`, `alignment`, `status`) VALUES
('hero-001', 'Eldara the Wise', 'region-001', 'scholar', 'A wise scholar who seeks knowledge in the arcane arts.', '[\"Discovered ancient runes\", \"Founded the Academy of Magic\"]', NULL, 3, 1, 45, NULL, '[\"curious\", \"patient\", \"analytical\"]', '{\"good\": 70, \"chaotic\": 30}', 'living'),
('hero-002', 'Marcus Goldhand', 'region-002', 'agent of change', 'A merchant who revolutionized trade in the region.', '[\"Established the Grand Bazaar\", \"Created the Merchant''s Guild\"]', NULL, 2, 1, 38, NULL, '[\"ambitious\", \"charismatic\", \"practical\"]', '{\"good\": 60, \"chaotic\": 50}', 'living'),
('hero-003', 'Sylvana Moonshadow', 'region-003', 'prophet', 'A mystic who communicates with the ancient spirits of the vale.', '[\"Communed with Ancient Spirits\", \"Established the Moonrite Circle\"]', NULL, 4, 1, 52, NULL, '[\"mystical\", \"wise\", \"mysterious\"]', '{\"good\": 80, \"chaotic\": 40}', 'living');
-- Hero Interaction Types
INSERT INTO hero_settlement_interaction_types (`id`, `name`, `code`, `description`, `base_duration`, `success_chance`, `influence_cost`, `cooldown_hours`, `is_active`) VALUES
('interaction-visit', 'Visit', 'visit', 'Visit the settlement to gather information', 1, 0.90, 0, 24, 1),
('interaction-establish-base', 'Establish Base', 'establish_base', 'Create a permanent presence in the settlement', 5, 0.70, 10, 168, 1),
('interaction-quest', 'Quest', 'quest', 'Undertake a quest for the settlement', 3, 0.60, 5, 72, 1),
('interaction-trade', 'Trade', 'trade', 'Engage in trade with the settlement', 2, 0.80, 2, 48, 1),
('interaction-research', 'Research', 'research', 'Study and research in the settlement', 4, 0.75, 3, 96, 1),
('interaction-corruption-cleanse', 'Corruption Cleanse', 'corruption_cleanse', 'Attempt to cleanse corruption from the settlement', 7, 0.40, 15, 336, 1),
('interaction-founding', 'Found Settlement', 'founding', 'Found a new settlement', 10, 0.50, 20, 720, 1);
-- Building Types
INSERT INTO building_types (`id`, `name`, `code`, `description`, `category`, `base_cost`, `maintenance_cost`, `prosperity_bonus`, `defensibility_bonus`, `special_properties`, `prerequisites`, `is_active`) VALUES
('type-house', 'House', 'house', 'Basic residential building for families', 'residential', 50, 5, 1, 0, '[\"housing\"]', '[]', 1),
('type-manor', 'Manor', 'manor', 'Large residential building for wealthy families', 'residential', 200, 20, 5, 2, '[\"housing\", \"luxurious\"]', '[\"house\"]', 1);
-- Landmark Types
INSERT INTO landmark_types (`name`, `code`, `description`, `base_magic_level`, `base_danger_level`, `discovery_difficulty`, `exploration_rewards`, `special_properties`, `is_active`) VALUES
('Temple', 'temple', 'Ancient place of worship and spiritual power', 30, 20, 40, '{\"divine_favor\": 100, \"ancient_knowledge\": true}', '{\"spiritual_resonance\": true, \"blessing_potential\": 0.3}', 1),
('Ruin', 'ruin', 'Remains of an ancient civilization', 20, 40, 50, '{\"artifacts\": true, \"historical_knowledge\": true}', '{\"hidden_treasures\": 0.4, \"trap_potential\": 0.3}', 1),
('Sacred Grove', 'grove', 'Natural sanctuary of primal magic', 40, 15, 35, '{\"natural_resources\": true, \"magical_essence\": true}', '{\"nature_magic\": 0.5, \"healing_potential\": 0.4}', 1),
('Ancient Tower', 'tower', 'Mysterious tower from a forgotten age', 50, 35, 45, '{\"magical_knowledge\": true, \"arcane_artifacts\": true}', '{\"arcane_study\": 0.5, \"magical_defense\": 0.4}', 1),
('Battlefield', 'battlefield', 'Site of a historic battle', 25, 45, 30, '{\"military_artifacts\": true, \"battle_knowledge\": true}', '{\"martial_resonance\": 0.4, \"haunted\": 0.3}', 1);
-- Landmark Statuses
INSERT INTO landmark_statuses (`name`, `code`, `description`, `magic_modifier`, `danger_modifier`, `exploration_difficulty_modifier`, `special_effects`, `is_active`) VALUES
('Pristine', 'pristine', 'Landmark in perfect condition, untouched by time or corruption', 20, -10, -15, '{\"discovery_bonus\": 0.2}', 1),
('Corrupted', 'corrupted', 'Landmark tainted by dark forces or magical corruption', -20, 30, 20, '{\"corruption_spread\": 0.1}', 1),
('Awakened', 'awakened', 'Landmark pulsing with newly awakened magical energy', 40, 15, 10, '{\"magic_resonance\": 0.3}', 1),
('Dormant', 'dormant', 'Landmark in a state of magical slumber', -10, 0, 0, NULL, 1),
('Unstable', 'unstable', 'Landmark exhibiting unpredictable magical fluctuations', 30, 25, 30, '{\"random_events\": 0.2}', 1);
-- Landmarks
INSERT INTO landmarks (`id`, `region_id`, `name`, `type`, `description`, `status`, `magic_level`, `danger_level`, `discovered_year`, `last_visited_year`, `associated_events`, `traits`) VALUES
('landmark-001', 'region-001', 'Crystal Sanctuary', 'temple', 'An ancient temple made of pure crystal, emanating mystical energy throughout the Arcane Highlands.', 'pristine', 75, 25, 1, 1, '[]', '[\"ancient\", \"magical\", \"holy_site\"]'),
('landmark-002', 'region-001', 'Whispering Grove', 'grove', 'A sacred grove where the trees themselves seem to whisper ancient secrets.', 'pristine', 60, 15, NULL, NULL, '[]', '[\"ancient\", \"magical\", \"hidden\"]'),
('landmark-003', 'region-002', 'Merchant''s Rest Monument', 'monument', 'A grand monument commemorating the founding of the great trade routes.', 'weathered', 20, 10, 1, 1, '[]', '[\"historical\", \"strategic\"]'),
('landmark-004', 'region-003', 'The Forgotten Tower', 'tower', 'A mysterious tower that appears to be much older than the surrounding landscape.', 'haunted', 85, 70, NULL, NULL, '[]', '[\"ancient\", \"magical\", \"hidden\", \"cursed_ground\"]');



-- Resource Node Types
INSERT INTO resource_node_types (`name`, `code`, `description`, `base_output`, `extraction_difficulty`, `renewal_rate`, `properties`, `resource_category`, `is_active`) VALUES
('Mine', 'mine', 'An excavation site for extracting minerals and metals', 60, 70, 0, '[\"valuable\", \"dangerous\", \"finite\"]', 'mineral', 1),
('Quarry', 'quarry', 'A site for extracting stone and building materials', 55, 60, 0, '[\"steady\", \"reliable\", \"finite\"]', 'stone', 1),
('Forest', 'forest', 'A woodland area providing timber and natural resources', 45, 40, 20, '[\"renewable\", \"abundant\", \"seasonal\"]', 'timber', 1),
('Farmland', 'farmland', 'Agricultural land for food production', 50, 30, 40, '[\"seasonal\", \"fertile\", \"renewable\"]', 'food', 1),
('Fishing Waters', 'fishing', 'A water body providing fish and aquatic resources', 40, 35, 30, '[\"variable\", \"coastal\", \"renewable\"]', 'food', 1),
('Magical Spring', 'magical_spring', 'A mystical source of magical energy and rare materials', 80, 90, 10, '[\"magical\", \"rare\", \"powerful\", \"unstable\"]', 'magical', 1),
('Herb Garden', 'herb_garden', 'Cultivated area for growing medicinal and magical herbs', 35, 25, 50, '[\"medicinal\", \"magical\", \"renewable\", \"cultivated\"]', 'herbs', 1);
-- Resource Node Statuses
INSERT INTO resource_node_statuses (`name`, `code`, `description`, `output_modifier`, `extraction_difficulty_modifier`, `can_harvest`, `special_effects`, `is_active`) VALUES
('Active', 'active', 'Fully operational and producing resources', 1.0, 0, 1, '[\"normal_operation\"]', 1),
('Depleted', 'depleted', 'Resources have been exhausted', 0.0, 0, 0, '[\"requires_restoration\", \"no_output\"]', 1),
('Contested', 'contested', 'Under dispute or conflict', 0.3, 40, 1, '[\"conflict_risk\", \"reduced_efficiency\", \"dangerous_extraction\"]', 1),
('Corrupted', 'corrupted', 'Tainted by dark forces', 0.1, 60, 0, '[\"corruption_spread\", \"dangerous_exposure\", \"requires_cleansing\"]', 1),
('Flourishing', 'status-flourishing', 'Exceptionally productive and well-maintained', 1.5, -20, 1, '[\"bonus_yields\", \"easier_extraction\", \"enhanced_renewal\"]', 1),
('Overworked', 'overworked', 'Being exploited beyond sustainable levels', 0.7, 25, 1, '[\"depletion_risk\", \"worker_fatigue\", \"equipment_strain\"]', 1),
('Blessed', 'blessed', 'Enhanced by divine or magical blessings', 1.3, -15, 1, '[\"divine_favor\", \"enhanced_yields\", \"worker_protection\"]', 1),
('Unstable', 'unstable', 'Subject to unpredictable changes and fluctuations', 0.8, 30, 1, '[\"random_fluctuations\", \"unpredictable_yields\", \"safety_hazards\"]', 1);
-- Building Special Properties
INSERT INTO building_special_properties (`name`, `code`, `description`, `effects`, `rarity`, `category`, `is_active`) VALUES
('Magical', 'magical', 'Imbued with arcane energy that enhances its capabilities', '{\"magic_production\": 0.2, \"mana_cost_reduction\": 0.1}', 'uncommon', 'magical', 1),
('Ancient', 'ancient', 'Built in ages past with forgotten techniques and materials', '{\"durability_bonus\": 0.3, \"cultural_value\": 0.5}', 'rare', 'historical', 1),
('Fortified', 'fortified', 'Reinforced to withstand attacks and siege', '{\"defense_bonus\": 0.4, \"durability_bonus\": 0.2}', 'common', 'military', 1),
('Sacred', 'sacred', 'Blessed by divine powers and used for religious purposes', '{\"divine_favor\": 0.3, \"corruption_resistance\": 0.2}', 'uncommon', 'religious', 1);
-- Building Condition Levels
INSERT INTO building_condition_levels (`name`, `code`, `description`, `min_condition`, `max_condition`, `color_code`, `maintenance_multiplier`, `productivity_multiplier`, `is_active`) VALUES
('Excellent', 'excellent', 'Perfect condition - building operates at peak efficiency', 80, 100, '#22c55e', 0.8, 1.2, 1),
('Good', 'good', 'Well-maintained building with minor wear', 60, 79, '#84cc16', 0.9, 1.1, 1),
('Fair', 'fair', 'Average condition with visible wear and minor issues', 40, 59, '#eab308', 1.0, 1.0, 1),
('Poor', 'poor', 'Deteriorated building requiring significant repairs', 20, 39, '#f97316', 1.3, 0.8, 1),
('Ruined', 'ruined', 'Severely damaged or abandoned building barely functional', 0, 19, '#dc2626', 2.0, 0.5, 1);
-- Building Statuses
INSERT INTO building_statuses (`id`, `name`, `code`, `description`, `productivity_modifier`, `maintenance_modifier`, `is_active`) VALUES
('status-active', 'Active', 'active', 'Building is fully operational and functioning normally', 1.0, 1.0, 1),
('status-abandoned', 'Abandoned', 'abandoned', 'Building has been left empty and unmaintained', 0.0, 0.5, 1),
('status-corrupted', 'Corrupted', 'corrupted', 'Building has been tainted by dark magic or evil influence', 0.3, 1.5, 1),
('status-ruined', 'Ruined', 'ruined', 'Building has been severely damaged and is mostly unusable', 0.1, 2.0, 1),
('status-blessed', 'Blessed', 'blessed', 'Building has been blessed by divine forces', 1.3, 0.8, 1),
('status-under-construction', 'Under Construction', 'under_construction', 'Building is currently being built', 0.0, 0.0, 1),
('status-renovating', 'Renovating', 'renovating', 'Building is undergoing renovation or upgrade', 0.5, 1.2, 1),
('status-haunted', 'Haunted', 'haunted', 'Building is inhabited by supernatural entities', 0.6, 1.4, 1),
('status-enchanted', 'Enchanted', 'enchanted', 'Building is enhanced by beneficial magic', 1.4, 0.7, 1),
('status-quarantined', 'Quarantined', 'quarantined', 'Building has been sealed off due to disease or danger', 0.0, 1.5, 1);
-- Buildings
INSERT INTO buildings (`id`, `settlement_id`, `type`, `name`, `condition`, `status`, `level`, `specialProperties`) VALUES
('building-001', 'settlement-001', 'temple', 'Crystal Temple', 95, 'active', 3, '[\"sacred\", \"magical\"]'),
('building-002', 'settlement-001', 'market', 'Crystal Bazaar', 85, 'active', 2, '[\"profitable\"]'),
('building-003', 'settlement-001', 'house', 'Arcane Residence', 80, 'active', 1, '[\"magical\"]'),
('building-004', 'settlement-001', 'manor', 'Mage Council Manor', 90, 'active', 4, '[\"luxurious\", \"magical\"]'),
('building-005', 'settlement-002', 'house', 'Woodsman''s Cottage', 70, 'active', 1, '[]'),
('building-006', 'settlement-002', 'house', 'Hunter''s Lodge', 75, 'active', 2, '[]'),
('building-007', 'settlement-003', 'house', 'Scholar''s Quarters', 85, 'active', 2, '[\"scholarly\"]'),
('building-008', 'settlement-003', 'temple', 'Observatory Tower', 90, 'active', 5, '[\"magical\", \"ancient\"]');
-- Hero Death Reasons
INSERT INTO hero_death_reasons (`code`, `description`, `category`, `severity`, `is_active`) VALUES
('glorious_battle', 'Fell in glorious battle', 'combat', 4, 1),
('lost_wilderness', 'Lost to the wilderness', 'tragic', 3, 1),
('dark_magic', 'Claimed by dark magic', 'magical', 5, 1),
('treachery', 'Victim of treachery', 'tragic', 4, 1),
('exploring_ruins', 'Lost exploring ancient ruins', 'tragic', 3, 1),
('mysterious_illness', 'Succumbed to a mysterious illness', 'natural', 2, 1),
('vanished', 'Vanished without a trace', 'mysterious', 3, 1),
('heroic_sacrifice', 'Met their end in a heroic sacrifice', 'combat', 5, 1);
-- Evolution Parameters
INSERT INTO evolution_parameters (`parameter`, `value`, `description`) VALUES
('base_growth_rate', 0.1, 'Base growth rate for settlements'),
('max_growth_rate', 0.5, 'Maximum growth rate for settlements'),
('prosperity_growth_modifier', 0.2, 'Growth modifier based on prosperity'),
('min_evolution_years', 5, 'Minimum years before evolution check'),
('prosperity_threshold', 100, 'Prosperity threshold for evolution');
-- Game Events
INSERT INTO game_events (`id`, `title`, `description`, `type`, `status`, `region_id`, `timestamp`, `related_region_ids`, `related_hero_ids`, `year`) VALUES
('event-001', 'Academy of Magic Founded', 'The Academy of Magic was founded in the Arcane Highlands, marking a new era of magical learning.', 'founding', 'completed', 'region-001', CURRENT_TIMESTAMP, '[\"region-001\"]', '[\"hero-001\"]', 1),
('event-002', 'Grand Bazaar Opens', 'The Grand Bazaar opened in Merchant''s Haven, attracting traders from across the realm.', 'economic', 'completed', 'region-002', CURRENT_TIMESTAMP, '[\"region-002\"]', '[\"hero-002\"]', 1),
('event-003', 'Moonrite Circle Established', 'The Moonrite Circle was established in Mystic Vale, strengthening the region''s connection to ancient magics.', 'mystical', 'completed', 'region-003', CURRENT_TIMESTAMP, '[\"region-003\"]', '[\"hero-003\"]', 1);
-- Divine Bets
INSERT INTO divine_bets (`id`, `player_id`, `bet_type`, `target_id`, `description`, `timeframe`, `confidence`, `divine_favor_stake`, `potential_payout`, `current_odds`, `status`, `placed_year`, `resolved_year`, `resolution_notes`) VALUES
('bet-001', 'SINGLE_PLAYER', 'settlement_growth', 'settlement-001', 'Crystalhaven will grow to city status within the next 5 years', 5, 'possible', 100, 250, 2.5, 'active', 1, NULL, NULL),
('bet-002', 'SINGLE_PLAYER', 'landmark_discovery', 'region-001', 'A new landmark will be discovered in Arcane Highlands within 7 years', 7, 'long_shot', 50, 200, 4.0, 'active', 1, NULL, NULL),
('bet-003', 'SINGLE_PLAYER', 'cultural_shift', 'region-001', 'Arcane Highlands will shift to mystical cultural influence within 10 years', 10, 'possible', 75, 225, 3.0, 'active', 1, NULL, NULL);
-- Game States (singleton)
INSERT INTO game_states (`singleton_id`, `current_year`) VALUES ('default', 1);
