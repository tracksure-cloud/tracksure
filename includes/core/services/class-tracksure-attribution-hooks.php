<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug logging intentionally used for attribution tracking diagnostics, only fires when WP_DEBUG=true

/**
 *
 * TrackSure Attribution Hooks
 *
 * Listens to core hooks and creates touchpoints for attribution tracking.
 * This service connects the session manager with the touchpoint recorder.
 *
 * @package TrackSure\Core\Services
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TrackSure Attribution Hooks class.
 */
class TrackSure_Attribution_Hooks
{



	/**
	 * Instance.
	 *
	 * @var TrackSure_Attribution_Hooks
	 */
	private static $instance = null;

	/**
	 * Touchpoint recorder instance.
	 *
	 * @var TrackSure_Touchpoint_Recorder
	 */
	private $touchpoint_recorder;

	/**
	 * Recursion guard - prevents infinite loops when recording touchpoints.
	 *
	 * @var bool
	 */
	private $is_recording = false;

	/**
	 * Get instance.
	 *
	 * @return TrackSure_Attribution_Hooks
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		$this->touchpoint_recorder = TrackSure_Touchpoint_Recorder::get_instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks()
	{
		// Create touchpoint when new session starts.
		add_action('tracksure_session_started', array($this, 'on_session_started'), 10, 5);

		// Create touchpoint for significant events (conversions).
		add_action('tracksure_event_recorded', array($this, 'on_event_recorded'), 10, 3);
	}

	/**
	 * Handle session start - create touchpoint for attribution.
	 *
	 * @param int   $db_session_id Session database ID.
	 * @param int   $visitor_id Visitor ID.
	 * @param array $session_data Session context data (utm_*, referrer, etc).
	 * @param bool  $is_returning Is returning visitor.
	 * @param int   $session_number Session sequence number.
	 */
	public function on_session_started($db_session_id, $visitor_id, $session_data, $is_returning, $session_number)
	{
		// CRITICAL: Prevent infinite loops - only record touchpoint once per session start.
		if ($this->is_recording) {
			return;
		}

		$this->is_recording = true;

		// Prepare session data for touchpoint recorder.
		$touchpoint_session_data = array(
			'visitor_id'     => $visitor_id,
			'session_id'     => ! empty($session_data['session_id']) ? $session_data['session_id'] : null,
			'session_number' => $session_number,
			'utm_source'     => ! empty($session_data['utm_source']) ? $session_data['utm_source'] : null,
			'utm_medium'     => ! empty($session_data['utm_medium']) ? $session_data['utm_medium'] : null,
			'utm_campaign'   => ! empty($session_data['utm_campaign']) ? $session_data['utm_campaign'] : null,
			'utm_term'       => ! empty($session_data['utm_term']) ? $session_data['utm_term'] : null,
			'utm_content'    => ! empty($session_data['utm_content']) ? $session_data['utm_content'] : null,
			'referrer'       => ! empty($session_data['referrer']) ? $session_data['referrer'] : null,
		);

		// Event data for context.
		$event_data = array(
			'page_url'   => ! empty($session_data['page_url']) ? $session_data['page_url'] : null,
			'page_title' => ! empty($session_data['page_title']) ? $session_data['page_title'] : null,
		);

		$touchpoint_id = $this->touchpoint_recorder->maybe_record_touchpoint($touchpoint_session_data, $event_data);

		$this->is_recording = false;
	}

	/**
	 * Handle event recorded - create touchpoint for significant events.
	 *
	 * Only creates touchpoints for conversion events, not every event.
	 * Regular events (view_item, add_to_cart) don't need touchpoints.
	 *
	 * @param string $event_id Event ID (UUID).
	 * @param array  $event_data Event data.
	 * @param array  $session Session data.
	 */
	public function on_event_recorded($event_id, $event_data, $session)
	{
		// ✅ FIXED: Only create touchpoints for actual conversions.
		// Not for every view_item or add_to_cart (too noisy).
		$conversion_events = array(
			'purchase',
			'form_submit',  // Lead generation
			// Removed: 'view_item', 'add_to_cart', 'begin_checkout'.
			// These are tracked in session but don't need individual touchpoints.
		);

		if (! in_array($event_data['event_name'], $conversion_events)) {
			return; // Not a conversion event
		}

		// Prepare session data.
		$touchpoint_session_data = array(
			'visitor_id'     => $session['visitor_id'],
			'session_id'     => $session['session_id'],
			'session_number' => ! empty($session['session_number']) ? $session['session_number'] : 1,
			'utm_source'     => ! empty($session['utm_source']) ? $session['utm_source'] : null,
			'utm_medium'     => ! empty($session['utm_medium']) ? $session['utm_medium'] : null,
			'utm_campaign'   => ! empty($session['utm_campaign']) ? $session['utm_campaign'] : null,
			'utm_term'       => ! empty($session['utm_term']) ? $session['utm_term'] : null,
			'utm_content'    => ! empty($session['utm_content']) ? $session['utm_content'] : null,
			'referrer'       => ! empty($session['referrer']) ? $session['referrer'] : null,
		);

		$event_context = array(
			'event_id'   => $event_id,
			'page_url'   => ! empty($event_data['page_url']) ? $event_data['page_url'] : null,
			'page_title' => ! empty($event_data['page_title']) ? $event_data['page_title'] : null,
		);

		$this->touchpoint_recorder->maybe_record_touchpoint($touchpoint_session_data, $event_context);
	}
}
