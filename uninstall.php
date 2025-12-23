<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

define('STAR_RATE_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once STAR_RATE_PLUGIN_DIR . 'includes/Utils/Autoloader.php';

StarRate\Utils\Autoloader::register();

function star_rate_uninstall(): void {
    $database = new StarRate\Core\Database();
    $database->drop_table();

    delete_metadata('post', 0, '_star_rate_avg', '', true);
    delete_metadata('post', 0, '_star_rate_count', '', true);

    delete_option('star_rate_options');

    wp_cache_flush();
}

star_rate_uninstall();
