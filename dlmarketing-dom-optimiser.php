<?php
/**
 * Plugin Name: DLMarketing DOM Optimiser
 * Plugin URI:  https://dlmarketing.com/dom-optimiser
 * Description: High-performance DOM cleaner for Elementor (Legacy & V4). Removes bloat, ghost nodes, and redundant wrappers without breaking layout.
 * Version:     0.3a
 * Author:      DLMarketing
 * Author URI:  https://dlmarketing.com
 * Text Domain: dlmarketing-dom-optimiser
 * License:     GPL-2.0+
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// 1. Load Dependencies
require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-optimiser.php';

/**
 * Class DLMarketing_DOM_Optimiser
 */
class DLMarketing_DOM_Optimiser
{

	private static $instance = null;

	public static function get_instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct()
	{
		// Init Admin Settings
		if (is_admin()) {
			DLMarketing_DOM_Settings::get_instance();
		}

		// Only run logic on frontend and not in Elementor Editor
		if (!is_admin() && !isset($_GET['elementor-preview'])) {
			add_action('template_redirect', [$this, 'start_buffer'], 999);
		}
	}

	public function start_buffer()
	{
		ob_start([$this, 'process_buffer']);
	}

	public function process_buffer($buffer)
	{
		// Delegate to Core Class
		return DLMarketing_DOM_Optimiser_Core::get_instance()->process_buffer($buffer);
	}
}

// Initialize
DLMarketing_DOM_Optimiser::get_instance();
