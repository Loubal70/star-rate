<?php

declare(strict_types=1);

namespace StarRate\Core;

final readonly class Database {
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'star_rate_logs';
    }

    public function create_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            user_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_user (post_id, user_hash),
            KEY post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function drop_table(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->table_name));
    }

    /**
     * @return array{success: bool, message: string, data?: array{average: float, count: int}}
     */
    public function cast_vote(int $post_id, int $rating, string $user_hash): array {
        global $wpdb;

        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'message' => __('Invalid rating value. Must be between 1 and 5.', 'star-rate'),
            ];
        }

        if (!get_post($post_id)) {
            return [
                'success' => false,
                'message' => __('Invalid post ID.', 'star-rate'),
            ];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('START TRANSACTION');

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $inserted = $wpdb->insert(
                $this->table_name,
                [
                    'post_id'    => $post_id,
                    'rating'     => $rating,
                    'user_hash'  => $user_hash,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%s']
            );

            if ($inserted === false) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('You have already voted for this post.', 'star-rate'),
                ];
            }

            $stats = $this->calculate_stats($post_id);

            if ($stats === null) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Failed to calculate statistics.', 'star-rate'),
                ];
            }

            update_post_meta($post_id, '_star_rate_avg', (string) $stats['average']);
            update_post_meta($post_id, '_star_rate_count', (string) $stats['count']);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction control.
            $wpdb->query('COMMIT');

            wp_cache_delete('total_votes', 'star-rate');
            wp_cache_delete('total_posts', 'star-rate');

            return [
                'success' => true,
                'message' => __('Vote recorded successfully.', 'star-rate'),
                'data'    => $stats,
            ];
        } catch (\Exception $e) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('ROLLBACK');
            return [
                'success' => false,
                'message' => __('An error occurred while recording your vote.', 'star-rate'),
            ];
        }
    }

    /**
     * @return array{average: float, count: int}|null
     */
    private function calculate_stats(int $post_id): ?array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats calculation.
        $result = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT AVG(rating) as average, COUNT(*) as count FROM %i WHERE post_id = %d',
                $this->table_name,
                $post_id
            ),
            ARRAY_A
        );

        if ($result === null || !isset($result['average'], $result['count'])) {
            return null;
        }

        return [
            'average' => round((float) $result['average'], 2),
            'count'   => (int) $result['count'],
        ];
    }

    /**
     * @return array{average: float, count: int}
     */
    public function get_stats(int $post_id): array {
        $average = get_post_meta($post_id, '_star_rate_avg', true);
        $count = get_post_meta($post_id, '_star_rate_count', true);

        return [
            'average' => $average !== '' ? (float) $average : 0.0,
            'count'   => $count !== '' ? (int) $count : 0,
        ];
    }

    public function has_voted(int $post_id, string $user_hash): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Vote check.
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE post_id = %d AND user_hash = %s',
                $this->table_name,
                $post_id,
                $user_hash
            )
        );

        return (int) $result > 0;
    }

    public function generate_user_hash(): string {
        $ip = $this->get_anonymized_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';
        $salt = defined('NONCE_SALT') ? NONCE_SALT : '';

        return hash('sha256', $ip . $user_agent . $salt);
    }

    private function get_anonymized_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '0.0.0.0';

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::';
        }

        return '0.0.0.0';
    }

    public function get_table_name(): string {
        return $this->table_name;
    }
}