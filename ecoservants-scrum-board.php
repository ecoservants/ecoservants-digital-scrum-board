// DC-09: Activity Log System
require_once ES_SCRUM_PLUGIN_DIR . 'includes/activity/install.php';
require_once ES_SCRUM_PLUGIN_DIR . 'includes/activity/logger.php';
require_once ES_SCRUM_PLUGIN_DIR . 'includes/activity/rest.php';
require_once ES_SCRUM_PLUGIN_DIR . 'admin/activity-page.php';

// Activation: create/upgrade tables
register_activation_hook( ES_SCRUM_PLUGIN_FILE, 'es_scrum_activity_install' );

// Ensure upgrades apply when version changes (optional but professional)
add_action( 'admin_init', 'es_scrum_activity_maybe_upgrade' );
