<?php

declare(strict_types=1);

namespace StarRate\Core;

final readonly class Schema {
    private Database $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function init(): void {
        add_action('wp_footer', [$this, 'inject_schema']);
    }

    public function inject_schema(): void {
        if (!is_singular('post')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $stats = $this->database->get_stats($post_id);

        if ($stats['count'] === 0) {
            return;
        }

        $schema_type = apply_filters('star_rate_schema_type', 'Article');

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => $schema_type,
            'name'            => get_the_title($post_id),
            'url'             => get_permalink($post_id),
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $stats['average'],
                'ratingCount' => $stats['count'],
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
        ];

        $schema = apply_filters('star_rate_schema', $schema, $post_id);

        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '</script>' . "\n";
    }
}
