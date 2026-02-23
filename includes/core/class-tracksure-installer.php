<?php

/**
 *
 * TrackSure Installer
 *
 * Handles database schema creation, migrations, and default settings.
 * Creates all 10 tables with proper indexes and relationships.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Installer class.
 */
class TrackSure_Installer
{





	/**
	 * Run installation.
	 */
	public static function install()
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create tables.
		dbDelta(self::get_schema());

		// Set default options.
		self::set_default_options();

		// Update database version.
		update_option('tracksure_db_version', TRACKSURE_DB_VERSION);

		/**
		 * Fires after TrackSure database is installed.
		 *
		 * @since 1.0.0
		 */
		do_action('tracksure_installed');
	}

	/**
	 * Get database schema.
	 *
	 * @return string SQL schema.
	 */
	public static function get_schema()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$schema = "
		CREATE TABLE {$wpdb->prefix}tracksure_visitors (
			visitor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id VARCHAR(36) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (visitor_id),
			UNIQUE KEY client_id (client_id),
			KEY created_at (created_at)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_sessions (
			session_id VARCHAR(36) NOT NULL,
			visitor_id BIGINT UNSIGNED NOT NULL,
			session_number INT UNSIGNED NOT NULL DEFAULT 1,
			is_returning TINYINT(1) NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL,
			last_activity_at DATETIME NOT NULL,
			referrer VARCHAR(2048) DEFAULT NULL,
			landing_page VARCHAR(2048) DEFAULT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			utm_term VARCHAR(255) DEFAULT NULL,
			utm_content VARCHAR(255) DEFAULT NULL,
			gclid VARCHAR(255) DEFAULT NULL,
			fbclid VARCHAR(255) DEFAULT NULL,
			msclkid VARCHAR(255) DEFAULT NULL,
			ttclid VARCHAR(255) DEFAULT NULL,
			twclid VARCHAR(255) DEFAULT NULL,
			li_fat_id VARCHAR(255) DEFAULT NULL,
			irclickid VARCHAR(255) DEFAULT NULL,
			ScCid VARCHAR(255) DEFAULT NULL,
			device_type VARCHAR(50) DEFAULT NULL,
			browser VARCHAR(100) DEFAULT NULL,
			os VARCHAR(100) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			region VARCHAR(100) DEFAULT NULL,
			city VARCHAR(100) DEFAULT NULL,
			event_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (session_id),
			KEY visitor_id (visitor_id),
			KEY started_at (started_at),
			KEY last_activity_at (last_activity_at),
			KEY utm_source (utm_source),
			KEY source_medium_date (utm_source, utm_medium, started_at),
			KEY idx_msclkid (msclkid),
			KEY idx_ttclid (ttclid),
			KEY idx_twclid (twclid),
			KEY idx_li_fat_id (li_fat_id),
			KEY idx_irclickid (irclickid),
			KEY idx_sccid (ScCid),
			KEY idx_all_click_ids (gclid(100), fbclid(100), msclkid(100), ttclid(100))
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_events (
			event_id VARCHAR(36) NOT NULL,

			visitor_id BIGINT UNSIGNED DEFAULT NULL,
			session_id VARCHAR(36) DEFAULT NULL,
			event_name VARCHAR(255) NOT NULL,
			event_source ENUM('browser', 'server', 'api', 'offline') DEFAULT 'server',
			browser_fired TINYINT(1) DEFAULT 0,
			server_fired TINYINT(1) DEFAULT 1,
		browser_fired_at DATETIME DEFAULT NULL,
		destinations_sent JSON,
		event_params JSON,
		user_data JSON,
		ecommerce_data JSON,
		occurred_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL,
		page_url VARCHAR(2048) DEFAULT NULL,
		page_path VARCHAR(1024) DEFAULT NULL,
			page_title VARCHAR(500) DEFAULT NULL,
			page_url_hash CHAR(64) GENERATED ALWAYS AS (SHA2(page_url, 256)) STORED,
			referrer VARCHAR(2048) DEFAULT NULL,
			user_agent VARCHAR(512) DEFAULT NULL,
			ip_address VARBINARY(16) DEFAULT NULL,
			device_type VARCHAR(50) DEFAULT NULL,
			browser VARCHAR(100) DEFAULT NULL,
			os VARCHAR(100) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			region VARCHAR(100) DEFAULT NULL,
			city VARCHAR(100) DEFAULT NULL,
			is_conversion TINYINT(1) NOT NULL DEFAULT 0,
			conversion_value DECIMAL(10,2) DEFAULT NULL,
			consent_granted TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (event_id),
			KEY visitor_id (visitor_id),
			KEY session_id (session_id),
			KEY event_name (event_name),

			KEY event_source (event_source, occurred_at),
			KEY occurred_at (occurred_at),
			KEY created_at (created_at),
			KEY page_url_hash (page_url_hash, occurred_at),
			KEY is_conversion (is_conversion, occurred_at),
			KEY session_occurred (session_id, occurred_at),
			KEY idx_semantic_dedup (session_id, event_name(50), page_url_hash, occurred_at),
			KEY idx_dedup_check (event_id, browser_fired, server_fired),
			KEY idx_agg_covering (occurred_at, session_id, event_name(50), is_conversion, conversion_value)
		) ENGINE=InnoDB $charset_collate ROW_FORMAT=COMPRESSED;

		CREATE TABLE {$wpdb->prefix}tracksure_goals (
			goal_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			event_name VARCHAR(255) NOT NULL,
		conditions JSON,
		trigger_type VARCHAR(50) DEFAULT 'pageview',
		match_logic VARCHAR(20) DEFAULT 'all',
		trigger_config TEXT DEFAULT NULL,
		value_type VARCHAR(20) DEFAULT 'none',
		frequency VARCHAR(20) DEFAULT 'unlimited',
		cooldown_minutes INT DEFAULT 0,
		fixed_value DECIMAL(10,2) DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		is_pro TINYINT(1) DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (goal_id),
		KEY event_name (event_name),
		KEY trigger_type (trigger_type),
		KEY is_active (is_active),
		KEY idx_active_created (is_active, created_at),
		KEY idx_trigger_active (trigger_type, is_active),
		KEY idx_event_active (event_name(100), is_active),
		KEY idx_active_event_trigger (is_active, event_name(100), trigger_type)
	) ENGINE=InnoDB $charset_collate;

	CREATE TABLE {$wpdb->prefix}tracksure_conversions (
		conversion_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		visitor_id BIGINT UNSIGNED NOT NULL,
		session_id VARCHAR(36) NOT NULL,
		event_id VARCHAR(36) NOT NULL,
		conversion_type VARCHAR(50) NOT NULL,
		goal_id BIGINT UNSIGNED DEFAULT NULL,
		conversion_value DECIMAL(10,2) DEFAULT 0.00,
		currency VARCHAR(3) DEFAULT 'USD',
		transaction_id VARCHAR(255) DEFAULT NULL,
		items_count INT UNSIGNED DEFAULT 0,
		converted_at DATETIME NOT NULL,
		time_to_convert INT UNSIGNED DEFAULT NULL,
		sessions_to_convert INT UNSIGNED DEFAULT 1,
		first_touch_source VARCHAR(255) DEFAULT NULL,
		first_touch_medium VARCHAR(255) DEFAULT NULL,
		first_touch_campaign VARCHAR(255) DEFAULT NULL,
		last_touch_source VARCHAR(255) DEFAULT NULL,
		last_touch_medium VARCHAR(255) DEFAULT NULL,
		last_touch_campaign VARCHAR(255) DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (conversion_id),
		UNIQUE KEY idx_event_goal_unique (event_id, goal_id),
		KEY visitor_id (visitor_id),
		KEY session_id (session_id),
		KEY event_id (event_id),
		KEY conversion_type (conversion_type),
		KEY goal_id (goal_id),
		KEY converted_at (converted_at),
		KEY first_touch_source (first_touch_source),
		KEY last_touch_source (last_touch_source),
		KEY idx_session_converted (session_id, converted_at),
		KEY idx_date_range (converted_at, conversion_value),
		KEY idx_goal_date (goal_id, converted_at),
		KEY idx_goal_visitor (goal_id, visitor_id, converted_at),
		KEY idx_visitor_goal_date (visitor_id, goal_id, converted_at),
		KEY idx_analytics_covering (goal_id, converted_at, conversion_value, visitor_id)
	) ENGINE=InnoDB $charset_collate;

	CREATE TABLE {$wpdb->prefix}tracksure_touchpoints (
			touchpoint_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visitor_id BIGINT UNSIGNED NOT NULL,
			session_id VARCHAR(36) NOT NULL,
			event_id VARCHAR(36) DEFAULT NULL,
			touchpoint_seq INT UNSIGNED NOT NULL,
			touched_at DATETIME NOT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			utm_term VARCHAR(255) DEFAULT NULL,
			utm_content VARCHAR(255) DEFAULT NULL,
			channel VARCHAR(100) DEFAULT NULL,
			page_url VARCHAR(2048) DEFAULT NULL,
			page_title VARCHAR(500) DEFAULT NULL,
			page_path VARCHAR(1024) DEFAULT NULL,
			referrer VARCHAR(2048) DEFAULT NULL,
			conversion_id BIGINT UNSIGNED DEFAULT NULL,
			is_conversion_touchpoint TINYINT(1) DEFAULT 0,
			attribution_weight DECIMAL(5,4) DEFAULT 1.0000,		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,			PRIMARY KEY (touchpoint_id),
			KEY visitor_id (visitor_id),
			KEY session_id (session_id),
			KEY event_id (event_id),
			KEY conversion_id (conversion_id),
			KEY touched_at (touched_at),
			KEY channel (channel),
			KEY utm_source (utm_source),
			KEY utm_campaign (utm_campaign),
			KEY visitor_sequence (visitor_id, touchpoint_seq),
			KEY conversion_flag (is_conversion_touchpoint, touched_at),
			KEY idx_session_seq (session_id, touchpoint_seq)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_conversion_attribution (
			attribution_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversion_id BIGINT UNSIGNED NOT NULL,
			touchpoint_id BIGINT UNSIGNED NOT NULL,
			attribution_model VARCHAR(50) NOT NULL,
			credit_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			credit_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
			attribution_weight DECIMAL(5,4) NOT NULL DEFAULT 1.0000,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			channel VARCHAR(100) DEFAULT NULL,
			touchpoint_order INT UNSIGNED NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (attribution_id),
			KEY conversion_id (conversion_id),
			KEY touchpoint_id (touchpoint_id),
			KEY attribution_model (attribution_model),
			KEY model_source (attribution_model, utm_source),
			UNIQUE KEY unique_conversion_touchpoint_model (conversion_id, touchpoint_id, attribution_model)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_outbox (
			outbox_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(36) NOT NULL,
		destinations JSON NOT NULL,
		destinations_status JSON NOT NULL,
		payload JSON NOT NULL,
		status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
		retry_count INT UNSIGNED DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (outbox_id),
		UNIQUE KEY event_id (event_id),
		KEY status_created (status, created_at),
		KEY event_id_idx (event_id)
	) ENGINE=InnoDB ROW_FORMAT=COMPRESSED $charset_collate;

	CREATE TABLE {$wpdb->prefix}tracksure_click_ids (
			click_id_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(36) NOT NULL,
			platform VARCHAR(50) NOT NULL,
			click_id_type VARCHAR(50) NOT NULL,
			click_id_value VARCHAR(500) NOT NULL,
			first_seen_at DATETIME NOT NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY (click_id_id),
			KEY session_id (session_id),
			KEY platform (platform),
			KEY type_value (click_id_type, click_id_value(255)),
			UNIQUE KEY unique_session_type (session_id, click_id_type)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_agg_hourly (
			agg_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			hour_start DATETIME NOT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			channel VARCHAR(100) DEFAULT NULL,
			page_url_hash CHAR(64) DEFAULT NULL,
			page_path VARCHAR(1024) DEFAULT NULL,
			page_title VARCHAR(500) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			device_type VARCHAR(20) DEFAULT NULL,
			browser VARCHAR(50) DEFAULT NULL,
			os VARCHAR(50) DEFAULT NULL,
			sessions INT UNSIGNED DEFAULT 0,
			pageviews INT UNSIGNED DEFAULT 0,
			unique_visitors INT UNSIGNED DEFAULT 0,
			new_visitors INT UNSIGNED DEFAULT 0,
			returning_visitors INT UNSIGNED DEFAULT 0,
			total_engagement_time INT UNSIGNED DEFAULT 0,
			bounced_sessions INT UNSIGNED DEFAULT 0,
			avg_session_duration DECIMAL(10,2) DEFAULT 0,
			avg_pages_per_session DECIMAL(5,2) DEFAULT 0,
			conversions INT UNSIGNED DEFAULT 0,
			conversion_value DECIMAL(12,2) DEFAULT 0,
			conversion_rate DECIMAL(5,4) DEFAULT 0,
			transactions INT UNSIGNED DEFAULT 0,
			revenue DECIMAL(12,2) DEFAULT 0,
			tax DECIMAL(10,2) DEFAULT 0,
			shipping DECIMAL(10,2) DEFAULT 0,
			items_sold INT UNSIGNED DEFAULT 0,
			form_views INT UNSIGNED DEFAULT 0,
			form_starts INT UNSIGNED DEFAULT 0,
			form_submits INT UNSIGNED DEFAULT 0,
			form_abandons INT UNSIGNED DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (agg_id),
			UNIQUE KEY unique_hour_dims (hour_start, utm_source(50), utm_medium(50), utm_campaign(50), page_url_hash, country, device_type),
			KEY hour_start (hour_start),
			KEY source_medium (utm_source(50), utm_medium(50)),
			KEY channel (channel),
			KEY page_path (page_path(255)),
			KEY country (country),
			KEY device_type (device_type),
			KEY hour_source (hour_start, utm_source(50), utm_medium(50))
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_agg_daily (
			agg_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			date DATE NOT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			channel VARCHAR(100) DEFAULT NULL,
			page_path VARCHAR(1024) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			device_type VARCHAR(20) DEFAULT NULL,
			sessions INT UNSIGNED DEFAULT 0,
			pageviews INT UNSIGNED DEFAULT 0,
			unique_visitors INT UNSIGNED DEFAULT 0,
			new_visitors INT UNSIGNED DEFAULT 0,
			returning_visitors INT UNSIGNED DEFAULT 0,
			total_engagement_time INT UNSIGNED DEFAULT 0,
			bounced_sessions INT UNSIGNED DEFAULT 0,
			avg_session_duration DECIMAL(10,2) DEFAULT 0,
			avg_pages_per_session DECIMAL(5,2) DEFAULT 0,
			conversions INT UNSIGNED DEFAULT 0,
			conversion_value DECIMAL(12,2) DEFAULT 0,
			conversion_rate DECIMAL(5,4) DEFAULT 0,
			transactions INT UNSIGNED DEFAULT 0,
			revenue DECIMAL(12,2) DEFAULT 0,
			tax DECIMAL(10,2) DEFAULT 0,
			shipping DECIMAL(10,2) DEFAULT 0,
			items_sold INT UNSIGNED DEFAULT 0,
			form_views INT UNSIGNED DEFAULT 0,
			form_starts INT UNSIGNED DEFAULT 0,
			form_submits INT UNSIGNED DEFAULT 0,
			form_abandons INT UNSIGNED DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (agg_id),
			UNIQUE KEY unique_date_dims (date, utm_source(50), utm_medium(50), utm_campaign(50), page_path(255), country, device_type),
			KEY date (date),
			KEY source_medium (utm_source(50), utm_medium(50)),
			KEY channel (channel),
			KEY country (country),
			KEY device_type (device_type)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_agg_product_daily (
			agg_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			date DATE NOT NULL,
			product_id VARCHAR(100) NOT NULL,
			product_name VARCHAR(500) DEFAULT NULL,
			product_category VARCHAR(255) DEFAULT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			views INT UNSIGNED DEFAULT 0,
			add_to_carts INT UNSIGNED DEFAULT 0,
			checkouts INT UNSIGNED DEFAULT 0,
			purchases INT UNSIGNED DEFAULT 0,
			items_sold INT UNSIGNED DEFAULT 0,
			revenue DECIMAL(12,2) DEFAULT 0,
			conversion_rate DECIMAL(5,4) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (agg_id),
			UNIQUE KEY unique_product_date (date, product_id(50), utm_source(50), utm_medium(50)),
			KEY date (date),
			KEY product_id (product_id(50)),
			KEY product_category (product_category),
			KEY source_medium (utm_source(50), utm_medium(50))
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_funnels (
			funnel_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			funnel_name VARCHAR(255) NOT NULL,
			funnel_type VARCHAR(50) NOT NULL,
			is_active TINYINT(1) DEFAULT 1,
			time_window INT UNSIGNED DEFAULT 1800,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (funnel_id),
			KEY is_active (is_active)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_funnel_steps (
			step_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			funnel_id BIGINT UNSIGNED NOT NULL,
			step_order INT UNSIGNED NOT NULL,
			step_name VARCHAR(255) NOT NULL,
			step_type VARCHAR(50) NOT NULL,
			event_name VARCHAR(100) DEFAULT NULL,
			url_match VARCHAR(1024) DEFAULT NULL,
			param_name VARCHAR(100) DEFAULT NULL,
			param_value VARCHAR(255) DEFAULT NULL,
			PRIMARY KEY (step_id),
			KEY funnel_id (funnel_id),
			KEY funnel_order (funnel_id, step_order)
		) ENGINE=InnoDB $charset_collate;

		CREATE TABLE {$wpdb->prefix}tracksure_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL DEFAULT 'info',
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address VARCHAR(45) NULL,
			PRIMARY KEY (id),
			KEY level (level),
			KEY occurred_at (occurred_at)
		) ENGINE=InnoDB $charset_collate;
		";

		return $schema;
	}

	/**
	 * Set default options.
	 */
	private static function set_default_options()
	{
		// Define default settings WITHOUT translations (to avoid early textdomain loading).
		// Settings schema labels/descriptions are only needed in admin UI, not during install.
		$defaults = array(
			'tracksure_version'                 => TRACKSURE_VERSION,
			'tracksure_db_version'              => TRACKSURE_DB_VERSION,
			'tracksure_api_token'               => bin2hex(random_bytes(32)),
			'tracksure_public_token'            => bin2hex(random_bytes(16)),
			'tracksure_keep_data_on_uninstall'   => 0,
			'tracksure_tracking_enabled'        => 0,
			'tracksure_track_admins'            => 0,
			'tracksure_session_timeout'         => 30,
			'tracksure_batch_size'              => 10,
			'tracksure_batch_timeout'           => 5000,
			'tracksure_respect_dnt'             => 0,
			'tracksure_anonymize_ip'            => 0,
			'tracksure_ip_anonymization_method' => 'hash',
			'tracksure_retention_days'          => 90,
			'tracksure_attribution_window'      => 30,
			'tracksure_attribution_model'       => 'last_touch',
			'tracksure_utm_persistence_days'    => 30,
			'tracksure_enable_destinations'     => 1,
			'tracksure_enable_goals'            => 1,
			'tracksure_enable_funnels'          => 1,
			'tracksure_enable_segments'         => 1,
			'tracksure_consent_mode'            => 'disabled',
			'tracksure_default_consent_state'   => 1,
		);

		foreach ($defaults as $key => $value) {
			// Skip if already exists.
			if (false !== get_option($key)) {
				continue;
			}

			add_option($key, $value, '', 'no'); // 'no' = don't autoload non-critical settings
		}

		// Note: Goals should be created by users via the admin panel, not automatically.
		// This ensures users create goals that match their business objectives.

		// Create default funnels (NEW).
		self::create_default_funnels();
	}


	/**
	 * Create default funnels.
	 *
	 * @since 1.0.0
	 */
	private static function create_default_funnels()
	{
		// Check if Funnel_Analyzer class exists.
		if (! class_exists('TrackSure_Funnel_Analyzer')) {
			return;
		}

		$funnel_analyzer = TrackSure_Funnel_Analyzer::get_instance();
		$funnel_analyzer->create_default_funnels();
	}

	/**
	 * Generate secure API token.
	 *
	 * @return string
	 */
	public static function generate_token()
	{
		return 'ts_' . bin2hex(random_bytes(32));
	}
}
