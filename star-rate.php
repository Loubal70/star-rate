<?php
/**
 * Plugin Name: Star Rate
 * Plugin URI: https://github.com/Loubal70/star-rate
 * Description: High-performance star rating with Interactivity API. GDPR compliant, SEO optimized with Schema.org.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * Author: Loubal70
 * Author URI: https://louis-boulanger.fr/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: star-rate
 * Domain Path: /languages
 *
 * @package StarRate
 */

declare(strict_types=1);

namespace StarRate;

if (!defined('ABSPATH')) {
    exit;
}

define('STAR_RATE_VERSION', '1.0.0');
define('STAR_RATE_PLUGIN_FILE', __FILE__);
define('STAR_RATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STAR_RATE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STAR_RATE_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once STAR_RATE_PLUGIN_DIR . 'includes/Utils/Autoloader.php';

Utils\Autoloader::register();

function activate(): void {
    $database = new Core\Database();
    $database->create_table();
}

function init(): void {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required until plugin is hosted on WordPress.org.
    load_plugin_textdomain('star-rate', false, dirname(STAR_RATE_PLUGIN_BASENAME) . '/languages');

    $database = new Core\Database();
    $settings = new Core\Settings();
    $rest_api = new Core\RestApi($database);
    $renderer = new Core\Renderer($database);
    $schema = new Core\Schema($database);

    $settings->init();
    $rest_api->init();
    $renderer->init();
    $schema->init();
}

register_activation_hook(STAR_RATE_PLUGIN_FILE, __NAMESPACE__ . '\\activate');
add_action('plugins_loaded', __NAMESPACE__ . '\\init');
