<?php

declare(strict_types=1);

namespace StarRate\Core;

final readonly class Settings {
    private const OPTION_GROUP = 'star_rate_settings';
    private const OPTION_NAME = 'star_rate_options';

    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu(): void {
        add_options_page(
            __('Star Rate Settings', 'star-rate'),
            __('Star Rate', 'star-rate'),
            'manage_options',
            'star-rate-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default'           => $this->get_default_options(),
            ]
        );

        add_settings_section(
            'star_rate_general_section',
            __('General Settings', 'star-rate'),
            [$this, 'render_section_description'],
            'star-rate-settings'
        );

        add_settings_field(
            'enable_schema',
            __('Enable Schema.org', 'star-rate'),
            [$this, 'render_enable_schema_field'],
            'star-rate-settings',
            'star_rate_general_section'
        );
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections('star-rate-settings');
                submit_button(__('Save Settings', 'star-rate'));
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Statistics', 'star-rate'); ?></h2>
            <?php $this->render_statistics(); ?>

            <hr>

            <h2><?php esc_html_e('Customization', 'star-rate'); ?></h2>
            <p><?php esc_html_e('Override CSS variables in your theme to customize the widget appearance:', 'star-rate'); ?></p>
            <pre style="background:#f5f5f5;padding:1rem;border-radius:4px;overflow-x:auto;"><code>:root {
    --star-rate-color-star-active: #fbde20;
    --star-rate-color-star-inactive: #d3daeb;
    --star-rate-color-background: #ffffff;
    --star-rate-color-text: #1a1f2c;
    --star-rate-color-text-muted: #5b647a;
    --star-rate-radius: 16px;
    --star-rate-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}</code></pre>

            <hr>

            <h2><?php esc_html_e('Filters', 'star-rate'); ?></h2>
            <ul>
                <li><code>star_rate_schema_type</code> - <?php esc_html_e('Change Schema.org type (default: Article)', 'star-rate'); ?></li>
                <li><code>star_rate_schema</code> - <?php esc_html_e('Modify the complete JSON-LD schema', 'star-rate'); ?></li>
            </ul>
        </div>
        <?php
    }

    public function render_section_description(): void {
        echo '<p>' . esc_html__('Configure how Star Rate displays on your site.', 'star-rate') . '</p>';
    }

    public function render_enable_schema_field(): void {
        $options = $this->get_options();
        $enable_schema = $options['enable_schema'] ?? true;
        ?>
        <label>
            <input
                type="checkbox"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_schema]"
                value="1"
                <?php checked($enable_schema, true); ?>
            >
            <?php esc_html_e('Enable Schema.org JSON-LD for SEO', 'star-rate'); ?>
        </label>
        <?php
    }

    private function render_statistics(): void {
        global $wpdb;
        $database = new Database();
        $table_name = $database->get_table_name();

        $total_votes = wp_cache_get('total_votes', 'star-rate');
        if ($total_votes === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires $wpdb.
            $total_votes = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table_name));
            wp_cache_set('total_votes', $total_votes, 'star-rate', HOUR_IN_SECONDS);
        }

        $total_posts = wp_cache_get('total_posts', 'star-rate');
        if ($total_posts === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires $wpdb.
            $total_posts = $wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT post_id) FROM %i', $table_name));
            wp_cache_set('total_posts', $total_posts, 'star-rate', HOUR_IN_SECONDS);
        }

        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Total Votes:', 'star-rate'); ?></strong></td>
                    <td><?php echo esc_html(number_format_i18n((int) $total_votes)); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Rated Posts:', 'star-rate'); ?></strong></td>
                    <td><?php echo esc_html(number_format_i18n((int) $total_posts)); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @return array<string, mixed>
     */
    private function get_options(): array {
        $options = get_option(self::OPTION_NAME);
        return is_array($options) ? $options : $this->get_default_options();
    }

    /**
     * @return array<string, mixed>
     */
    private function get_default_options(): array {
        return [
            'enable_schema' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitize_options(mixed $input): array {
        if (!is_array($input)) {
            return $this->get_default_options();
        }

        return [
            'enable_schema' => isset($input['enable_schema']) && $input['enable_schema'] === '1',
        ];
    }
}
