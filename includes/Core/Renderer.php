<?php

declare(strict_types=1);

namespace StarRate\Core;

final readonly class Renderer {
    private Database $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('the_content', [$this, 'append_rating_widget']);
        add_shortcode('star-rate', [$this, 'shortcode_handler']);
    }

    public function enqueue_assets(): void {
        if (!is_singular('post')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        wp_enqueue_style(
            'star-rate-style',
            STAR_RATE_PLUGIN_URL . 'assets/css/style.css',
            [],
            STAR_RATE_VERSION
        );

        wp_enqueue_script_module('@wordpress/interactivity');
        wp_enqueue_script_module(
            'star-rate-view',
            STAR_RATE_PLUGIN_URL . 'assets/js/view.js',
            ['@wordpress/interactivity'],
            STAR_RATE_VERSION
        );

        $stats = $this->database->get_stats($post_id);
        $user_hash = $this->database->generate_user_hash();
        $has_voted = $this->has_voted_cookie($post_id) || $this->database->has_voted($post_id, $user_hash);

        wp_interactivity_state('star-rate', [
            'postId'    => $post_id,
            'average'   => $stats['average'],
            'count'     => $stats['count'],
            'hasVoted'  => $has_voted,
            'isLoading' => false,
            'restUrl'   => rest_url('star-rate/v1/vote'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'i18n'      => $this->get_js_translations(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function get_js_translations(): array {
        return [
            'noVotes'      => __('No votes yet', 'star-rate'),
            'oneVote'      => __('1 vote', 'star-rate'),
            // translators: %d is the number of votes.
            'votes'        => __('%d votes', 'star-rate'),
            'errorVote'    => __('Failed to record vote. Please try again.', 'star-rate'),
            'errorNetwork' => __('Network error. Please check your connection and try again.', 'star-rate'),
        ];
    }

    public function append_rating_widget(string $content): string {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $widget = $this->render_widget();

        return $content . $widget;
    }

    private function render_widget(): string {
        ob_start();
        ?>
        <div
            class="star-rate-widget"
            data-wp-interactive="star-rate"
            data-wp-context='{"selectedRating": 0, "hoverRating": 0}'
        >
            <div class="star-rate-header">
                <h3><?php esc_html_e('Was this article helpful?', 'star-rate'); ?></h3>
                <p><?php esc_html_e('Rate it to help us improve our content.', 'star-rate'); ?></p>
            </div>

            <div class="star-rate-stars" role="radiogroup" aria-label="<?php esc_attr_e('Rate this post', 'star-rate'); ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button
                        type="button"
                        class="star-rate-star"
                        role="radio"
                        aria-checked="false"
                        <?php // translators: %d is the star number (1-5). ?>
                        aria-label="<?php echo esc_attr(sprintf(__('Rate %d out of 5 stars', 'star-rate'), $i)); ?>"
                        data-wp-on--click="actions.vote"
                        data-wp-on--mouseenter="actions.hoverStar"
                        data-wp-on--mouseleave="actions.resetHover"
                        data-wp-class--star-rate-star--filled="callbacks.isStarFilled"
                        data-wp-class--star-rate-star--disabled="state.hasVoted"
                        data-wp-bind--disabled="state.hasVoted"
                        data-wp-context='{"rating": <?php echo esc_attr((string) $i); ?>}'
                    >
                        <?php echo $this->get_star_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded ?>
                    </button>
                <?php endfor; ?>
            </div>

            <div class="star-rate-stats">
                <span class="star-rate-average" data-wp-text="state.formattedAverage"></span>
                <span class="star-rate-count" data-wp-text="state.formattedCount"></span>
            </div>

            <div
                class="star-rate-message"
                data-wp-class--star-rate-message--visible="state.hasVoted"
                aria-live="polite"
            >
                <div class="star-rate-message__icon">
                    <?php echo $this->get_check_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded ?>
                </div>
                <span class="star-rate-message__title"><?php esc_html_e('Thank you!', 'star-rate'); ?></span>
                <p><?php esc_html_e('Your feedback is valuable.', 'star-rate'); ?></p>
                <div class="star-rate-message__rating">
                    <span data-wp-text="state.formattedAverage"></span>
                    <span data-wp-text="state.formattedCount"></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_star_svg(): string {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
    }

    private function get_check_svg(): string {
        return '<svg viewBox="0 0 24 24" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    }

    private function has_voted_cookie(int $post_id): bool {
        return isset($_COOKIE['star_rate_voted_' . $post_id]);
    }

    public function shortcode_handler(array $atts = []): string {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
        ], $atts, 'star-rate');

        $post_id = absint($atts['post_id']);

        if (!$post_id || !get_post($post_id)) {
            return '';
        }

        if (!wp_script_is('star-rate-view', 'enqueued')) {
            $this->enqueue_assets_for_shortcode($post_id);
        }

        return $this->render_widget();
    }

    private function enqueue_assets_for_shortcode(int $post_id): void {
        wp_enqueue_style(
            'star-rate-style',
            STAR_RATE_PLUGIN_URL . 'assets/css/style.css',
            [],
            STAR_RATE_VERSION
        );

        wp_enqueue_script_module('@wordpress/interactivity');
        wp_enqueue_script_module(
            'star-rate-view',
            STAR_RATE_PLUGIN_URL . 'assets/js/view.js',
            ['@wordpress/interactivity'],
            STAR_RATE_VERSION
        );

        $stats = $this->database->get_stats($post_id);
        $user_hash = $this->database->generate_user_hash();
        $has_voted = $this->has_voted_cookie($post_id) || $this->database->has_voted($post_id, $user_hash);

        wp_interactivity_state('star-rate', [
            'postId'    => $post_id,
            'average'   => $stats['average'],
            'count'     => $stats['count'],
            'hasVoted'  => $has_voted,
            'isLoading' => false,
            'restUrl'   => rest_url('star-rate/v1/vote'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'i18n'      => $this->get_js_translations(),
        ]);
    }
}
