<?php

/**
 *
 * TrackSure Attribution Resolver
 *
 * Resolves source/medium/campaign from UTM parameters, referrers, and page context.
 * Implements first-touch and last-touch attribution for Free version.
 * Pro extends with multi-touch models.
 *
 * @package TrackSure\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TrackSure Attribution Resolver class.
 */
class TrackSure_Attribution_Resolver {


	/**
	 * Instance.
	 *
	 * @var TrackSure_Attribution_Resolver
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var TrackSure_DB
	 */
	private $db;

	/**
	 * Search engine patterns.
	 *
	 * @var array
	 */
	private $search_engines = array(
		'google'     => array( 'google.com', 'google.' ),
		'bing'       => array( 'bing.com' ),
		'yahoo'      => array( 'yahoo.com' ),
		'duckduckgo' => array( 'duckduckgo.com' ),
		'baidu'      => array( 'baidu.com' ),
		'yandex'     => array( 'yandex.com', 'yandex.ru' ),
	);

	/**
	 * Social platform patterns.
	 *
	 * @var array
	 */
	private $social_platforms = array(
		'facebook'  => array( 'facebook.com', 'fb.com', 'm.facebook.com', 'fb.me' ),
		'instagram' => array( 'instagram.com', 'ig.me', 'instagr.am' ),
		'twitter'   => array( 'twitter.com', 't.co', 'x.com' ),
		'linkedin'  => array( 'linkedin.com', 'lnkd.in' ),
		'pinterest' => array( 'pinterest.com', 'pin.it' ),
		'reddit'    => array( 'reddit.com', 'redd.it' ),
		'tiktok'    => array( 'tiktok.com', 'vm.tiktok.com' ),
		'whatsapp'  => array( 'whatsapp.com', 'wa.me', 'chat.whatsapp.com' ),
		'youtube'   => array( 'youtube.com', 'youtu.be', 'm.youtube.com' ),
		'snapchat'  => array( 'snapchat.com', 'sc.com' ),
		'telegram'  => array( 'telegram.org', 't.me', 'telegram.me' ),
		'discord'   => array( 'discord.com', 'discord.gg' ),
		'threads'   => array( 'threads.net' ),
		'wechat'    => array( 'wechat.com', 'weixin.qq.com' ),
		'weibo'     => array( 'weibo.com', 'weibo.cn' ),
		'vimeo'     => array( 'vimeo.com' ),
		'tumblr'    => array( 'tumblr.com', 't.umblr.com' ),
		'clubhouse' => array( 'clubhouse.com', 'joinclubhouse.com' ),
		'mastodon'  => array( 'mastodon.social', 'mastodon.online' ),
		'bluesky'   => array( 'bsky.app', 'bluesky.app' ),
		'line'      => array( 'line.me' ),
		'kakao'     => array( 'kakaotalk.com', 'kakao.com' ),
		'viber'     => array( 'viber.com' ),
	);

	/**
	 * Attribution lookback window in days.
	 *
	 * @var int
	 */
	private $lookback_days = 30;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Attribution_Resolver
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db            = TrackSure_DB::get_instance();
		$this->lookback_days = absint( get_option( 'tracksure_attribution_window', 30 ) );
	}

	/**
	 * Resolve attribution from session data.
	 *
	 * @param array $session_data Session context (utm_*, referrer, landing_page).
	 * @return array Resolved attribution with source/medium/campaign/channel.
	 */
	public function resolve( $session_data ) {
		// Priority 1: UTM parameters (explicit campaign tracking).
		if ( ! empty( $session_data['utm_source'] ) ) {
			return array(
				'source'   => $this->sanitize_param( $session_data['utm_source'] ),
				'medium'   => ! empty( $session_data['utm_medium'] ) ? $this->sanitize_param( $session_data['utm_medium'] ) : 'unknown',
				'campaign' => ! empty( $session_data['utm_campaign'] ) ? $this->sanitize_param( $session_data['utm_campaign'] ) : null,
				'channel'  => $this->classify_channel( $session_data['utm_source'], $session_data['utm_medium'] ?? 'unknown' ),
			);
		}

		// Priority 2: Referrer classification.
		if ( ! empty( $session_data['referrer'] ) ) {
			$referrer_url = $session_data['referrer'];
			$parsed       = wp_parse_url( $referrer_url );
			$host         = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';

			// Check if internal referrer.
			$site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
			if ( false !== strpos( $host, $site_host ) ) {
				return $this->get_direct_attribution();
			}

			// Check search engines.
			foreach ( $this->search_engines as $engine => $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( false !== strpos( $host, $pattern ) ) {
						return array(
							'source'   => $engine,
							'medium'   => 'organic',
							'campaign' => null,
							'channel'  => 'organic_search',
						);
					}
				}
			}

			// Check social platforms.
			foreach ( $this->social_platforms as $platform => $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( false !== strpos( $host, $pattern ) ) {
						return array(
							'source'   => $platform,
							'medium'   => 'social',
							'campaign' => null,
							'channel'  => 'social',
						);
					}
				}
			}

			// Check AI chatbots (ChatGPT, Bard, Claude, Bing Chat, etc.).
			$ai_platforms = array(
				'chat.openai.com' => 'chatgpt',
				'bard.google.com' => 'bard',
				'claude.ai'       => 'claude',
				'bing.com/chat'   => 'bing_chat',
				'perplexity.ai'   => 'perplexity',
				'you.com'         => 'you_chat',
			);
			foreach ( $ai_platforms as $pattern => $name ) {
				if ( false !== strpos( $host, $pattern ) ) {
					return array(
						'source'   => $name,
						'medium'   => 'ai_chat',
						'campaign' => null,
						'channel'  => 'ai_referral',
					);
				}
			}

			// Check mobile apps and dark social (Android/iOS app referrers).
			if ( false !== strpos( $referrer_url, 'android-app://' ) || false !== strpos( $referrer_url, 'ios-app://' ) ) {
				return array(
					'source'   => 'mobile_app',
					'medium'   => 'app',
					'campaign' => null,
					'channel'  => 'mobile_app',
				);
			}

			// Generic referral.
			return array(
				'source'   => $host,
				'medium'   => 'referral',
				'campaign' => null,
				'channel'  => 'referral',
			);
		}

		// Priority 3: Direct traffic (no referrer, no UTM).
		return $this->get_direct_attribution();
	}

	/**
	 * Get direct traffic attribution.
	 *
	 * @return array
	 */
	private function get_direct_attribution() {
		return array(
			'source'   => '(direct)',
			'medium'   => '(none)',
			'campaign' => null,
			'channel'  => 'direct',
		);
	}

	/**
	 * Classify channel from source and medium.
	 *
	 * @param string $source Source.
	 * @param string $medium Medium.
	 * @return string Channel name.
	 */
	private function classify_channel( $source, $medium ) {
		$source = strtolower( $source );
		$medium = strtolower( $medium );

		// Paid search.
		if ( in_array( $medium, array( 'cpc', 'ppc', 'paidsearch' ), true ) ) {
			return 'paid_search';
		}

		// Organic search.
		if ( 'organic' === $medium ) {
			return 'organic_search';
		}

		// Social.
		if ( in_array( $medium, array( 'social', 'social-network', 'social-media', 'sm', 'social network', 'social media' ), true ) ) {
			return 'social';
		}

		// Email.
		if ( in_array( $medium, array( 'email', 'e-mail', 'e_mail', 'e mail' ), true ) ) {
			return 'email';
		}

		// Affiliate.
		if ( in_array( $medium, array( 'affiliate', 'affiliates' ), true ) ) {
			return 'affiliate';
		}

		// Display ads.
		if ( in_array( $medium, array( 'display', 'cpm', 'banner' ), true ) ) {
			return 'display';
		}

		// Video ads.
		if ( in_array( $medium, array( 'video', 'youtube' ), true ) ) {
			return 'video';
		}

		// Referral.
		if ( 'referral' === $medium ) {
			return 'referral';
		}

		// Direct.
		if ( '(direct)' === $source || '(none)' === $medium ) {
			return 'direct';
		}

		// Default: other.
		return 'other';
	}

	/**
	 * Sanitize attribution parameter.
	 *
	 * @param string $value Parameter value.
	 * @return string Sanitized value.
	 */
	private function sanitize_param( $value ) {
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Get attribution models (Free: first/last; Pro adds more).
	 *
	 * @return array Available attribution models.
	 */
	public function get_available_models() {
		$models = array(
			'first_touch' => array(
				'id'          => 'first_touch',
				'name'        => __( 'First Touch', 'tracksure' ),
				'description' => __( 'Credit goes to the first known touchpoint', 'tracksure' ),
				'available'   => true,
			),
			'last_touch'  => array(
				'id'          => 'last_touch',
				'name'        => __( 'Last Touch', 'tracksure' ),
				'description' => __( 'Credit goes to the last touchpoint before conversion', 'tracksure' ),
				'available'   => true,
			),
		);

		/**
		 * Filter available attribution models.
		 *
		 * Pro can add: linear, position_based, time_decay, custom.
		 *
		 * @since 1.0.0
		 *
		 * @param array $models Attribution models.
		 */
		return apply_filters( 'tracksure_attribution_models', $models );
	}

	/**
	 * Calculate attribution credits for a conversion (Pro hook).
	 *
	 * @param int    $conversion_id Conversion ID.
	 * @param string $model Model ID (first_touch, last_touch, linear, etc.).
	 * @return array Attribution credits array.
	 */
	public function calculate_credits( $conversion_id, $model = 'last_touch' ) {
		/**
		 * Filter attribution credit calculation.
		 *
		 * Free returns simple first/last.
		 * Pro computes multi-touch credits.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $credits Empty array (to be filled).
		 * @param int    $conversion_id Conversion ID.
		 * @param string $model Attribution model.
		 */
		return apply_filters( 'tracksure_calculate_attribution_credits', array(), $conversion_id, $model );
	}

	/**
	 * Get lookback window in days.
	 *
	 * @return int
	 */
	public function get_lookback_days() {
		return $this->lookback_days;
	}
}
