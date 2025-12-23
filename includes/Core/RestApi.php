<?php

declare(strict_types=1);

namespace StarRate\Core;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

final readonly class RestApi {
    private Database $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('star-rate/v1', '/vote', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_vote'],
            'permission_callback' => '__return_true',
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
                'rating'  => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param >= 1 && $param <= 5;
                    },
                ],
                'nonce'   => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function handle_vote(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = $request->get_param('post_id');
        $rating = $request->get_param('rating');
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('nonce');

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'invalid_nonce',
                __('Security verification failed.', 'star-rate'),
                ['status' => 403]
            );
        }

        $cookie_name = 'star_rate_voted_' . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            return new WP_Error(
                'already_voted',
                __('You have already voted for this post.', 'star-rate'),
                ['status' => 409]
            );
        }

        $user_hash = $this->database->generate_user_hash();
        $result = $this->database->cast_vote($post_id, $rating, $user_hash);

        if (!$result['success']) {
            return new WP_Error(
                'vote_failed',
                $result['message'],
                ['status' => 400]
            );
        }

        $this->set_vote_cookie($cookie_name);

        return new WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'average' => $result['data']['average'],
                'count'   => $result['data']['count'],
            ],
        ], 200);
    }

    private function set_vote_cookie(string $cookie_name): void {
        setcookie(
            $cookie_name,
            '1',
            [
                'expires'  => time() + (30 * DAY_IN_SECONDS),
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
