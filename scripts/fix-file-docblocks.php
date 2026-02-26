<?php
/**
 * Fix missing file doc comments for PHPCS compliance.
 *
 * Adds standard WordPress file-level docblocks to files that are missing them.
 *
 * @package TrackSure
 */

$base = dirname( __DIR__ );

// Map of files to their docblock descriptions.
// Format: relative path => [ description, package ]
$files = array(
	'uninstall.php' => array( 'Uninstall TrackSure plugin.', 'TrackSure' ),
	'includes/core/admin/class-tracksure-admin-ui.php' => array( 'Admin UI handler for TrackSure.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-diagnostics-controller.php' => array( 'REST API diagnostics controller.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-goals-controller.php' => array( 'REST API goals controller.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-ingest-controller.php' => array( 'REST API ingest controller.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-pixel-callback-controller.php' => array( 'REST API pixel callback controller.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-query-controller.php' => array( 'REST API query controller.', 'TrackSure' ),
	'includes/core/api/class-tracksure-rest-settings-controller.php' => array( 'REST API settings controller.', 'TrackSure' ),
	'includes/core/api/tracksure-consent-api.php' => array( 'Consent API endpoint functions.', 'TrackSure' ),
	'includes/core/class-tracksure-core.php' => array( 'Core plugin class for TrackSure.', 'TrackSure' ),
	'includes/core/class-tracksure-currency-handler.php' => array( 'Currency conversion handler.', 'TrackSure' ),
	'includes/core/class-tracksure-db.php' => array( 'Database abstraction layer for TrackSure.', 'TrackSure' ),
	'includes/core/class-tracksure-event-bridge.php' => array( 'Event bridge for cross-system event routing.', 'TrackSure' ),
	'includes/core/destinations/class-tracksure-destinations-manager.php' => array( 'Destinations manager for analytics platforms.', 'TrackSure' ),
	'includes/core/integrations/class-tracksure-integrations-manager.php' => array( 'Integrations manager for e-commerce platforms.', 'TrackSure' ),
	'includes/core/jobs/class-tracksure-cleanup-worker.php' => array( 'Background cleanup worker for stale data.', 'TrackSure' ),
	'includes/core/jobs/class-tracksure-daily-aggregator.php' => array( 'Daily data aggregation background job.', 'TrackSure' ),
	'includes/core/jobs/class-tracksure-delivery-worker.php' => array( 'Outbox delivery worker for server-side events.', 'TrackSure' ),
	'includes/core/jobs/class-tracksure-hourly-aggregator.php' => array( 'Hourly data aggregation background job.', 'TrackSure' ),
	'includes/core/registry/class-tracksure-registry-loader.php' => array( 'Module registry loader.', 'TrackSure' ),
	'includes/core/services/class-tracksure-action-scheduler.php' => array( 'Action scheduler service for cron-based tasks.', 'TrackSure' ),
	'includes/core/services/class-tracksure-attribution-analytics.php' => array( 'Attribution analytics service.', 'TrackSure' ),
	'includes/core/services/class-tracksure-attribution-hooks.php' => array( 'Attribution hook handlers.', 'TrackSure' ),
	'includes/core/services/class-tracksure-consent-manager.php' => array( 'Cookie consent management service.', 'TrackSure' ),
	'includes/core/services/class-tracksure-conversion-recorder.php' => array( 'Conversion recording service.', 'TrackSure' ),
	'includes/core/services/class-tracksure-event-builder.php' => array( 'Event builder for constructing normalized events.', 'TrackSure' ),
	'includes/core/services/class-tracksure-event-mapper.php' => array( 'Event mapper for platform-specific transformations.', 'TrackSure' ),
	'includes/core/services/class-tracksure-event-recorder.php' => array( 'Event recorder service for persisting tracking events.', 'TrackSure' ),
	'includes/core/services/class-tracksure-geolocation.php' => array( 'Geolocation lookup service.', 'TrackSure' ),
	'includes/core/services/class-tracksure-goal-evaluator.php' => array( 'Goal evaluation engine for conversion matching.', 'TrackSure' ),
	'includes/core/services/class-tracksure-goal-validator.php' => array( 'Goal configuration validator.', 'TrackSure' ),
	'includes/core/services/class-tracksure-journey-engine.php' => array( 'Customer journey engine.', 'TrackSure' ),
	'includes/core/services/class-tracksure-logger.php' => array( 'Logging service for TrackSure.', 'TrackSure' ),
	'includes/core/services/class-tracksure-rate-limiter.php' => array( 'Rate limiting service for API protection.', 'TrackSure' ),
	'includes/core/services/class-tracksure-session-manager.php' => array( 'Session management service.', 'TrackSure' ),
	'includes/core/services/class-tracksure-suggestion-engine.php' => array( 'Setup suggestion engine for guided configuration.', 'TrackSure' ),
	'includes/core/services/class-tracksure-touchpoint-recorder.php' => array( 'Touchpoint recording service for attribution.', 'TrackSure' ),
	'includes/core/tracking/class-tracksure-checkout-tracking.php' => array( 'Checkout event tracking handler.', 'TrackSure' ),
	'includes/core/tracking/class-tracksure-tracker-assets.php' => array( 'Frontend tracking script asset loader.', 'TrackSure' ),
	'includes/core/utils/class-tracksure-utilities.php' => array( 'Utility helper functions.', 'TrackSure' ),
	'includes/free/adapters/class-tracksure-fluentcart-adapter.php' => array( 'FluentCart e-commerce platform adapter.', 'TrackSure' ),
	'includes/free/destinations/class-tracksure-ga4-destination.php' => array( 'Google Analytics 4 destination handler.', 'TrackSure' ),
	'includes/free/destinations/class-tracksure-ga4-setup-guide.php' => array( 'GA4 setup guide configuration helper.', 'TrackSure' ),
	'includes/free/destinations/class-tracksure-meta-destination.php' => array( 'Meta (Facebook) Conversions API destination handler.', 'TrackSure' ),
	'includes/free/integrations/class-tracksure-fluentcart-integration.php' => array( 'FluentCart integration module.', 'TrackSure' ),
);

$fixed = 0;
$skipped = 0;

foreach ( $files as $rel_path => $info ) {
	$full_path = $base . '/' . $rel_path;
	if ( ! file_exists( $full_path ) ) {
		echo "SKIP (not found): $rel_path\n";
		++$skipped;
		continue;
	}

	$content = file_get_contents( $full_path );

	// Check if it already has a file docblock after <?php.
	// A file docblock is: <?php\n/**\n * ... (at the very start).
	if ( preg_match( '/^<\?php\s*\n\/\*\*/', $content ) ) {
		echo "SKIP (already has docblock): $rel_path\n";
		++$skipped;
		continue;
	}

	list( $description, $package ) = $info;

	$docblock = "/**\n * $description\n *\n * @package $package\n */\n";

	// Handle files that start with <?php followed by a phpcs:disable or blank line.
	// Insert docblock right after <?php\n.
	$content = preg_replace(
		'/^(<\?php)\s*\n/',
		"$1\n$docblock\n",
		$content,
		1,
		$count
	);

	if ( $count > 0 ) {
		file_put_contents( $full_path, $content );
		echo "FIXED: $rel_path\n";
		++$fixed;
	} else {
		echo "SKIP (no match for pattern): $rel_path\n";
		++$skipped;
	}
}

echo "\nDone! Fixed: $fixed, Skipped: $skipped\n";
