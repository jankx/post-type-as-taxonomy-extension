<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Api;

use Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager;

/**
 * REST endpoint: search / list posts from a source post type.
 *
 * GET /wp-json/jankx/v1/post-type-as-taxonomy/search
 *
 * Query params:
 *   ?registration_id=destination_tour-as-taxonomy  (required)
 *   &s=Hà Nội                                      (optional, search string)
 *   &paged=1                                       (optional, default 1)
 *   &per_page=20                                   (optional, default 20)
 *
 * Response (array of items):
 * [
 *   { "id": 12, "title": "Hà Nội" },
 *   { "id": 45, "title": "Đà Nẵng" }
 * ]
 *
 * Consumed by a future Gutenberg JS panel to populate the selector dynamically.
 */
class SearchController
{
    const NAMESPACE = 'jankx/v1';
    const ROUTE     = '/post-type-as-taxonomy/search';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public function registerRoute(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'registration_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                's'               => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'paged'           => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page'        => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('edit_posts');
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $registrationId = (string) $request->get_param('registration_id');
        $registration   = null;

        foreach (PostTypeAsTaxonomyManager::getAll() as $reg) {
            if ($reg->getId() === $registrationId) {
                $registration = $reg;
                break;
            }
        }

        if (!$registration) {
            return new \WP_REST_Response(
                ['code' => 'not_found', 'message' => 'Registration not found.'],
                404
            );
        }

        $queryArgs = array_merge($registration->getQueryArgs(), [
            'posts_per_page' => min((int) $request->get_param('per_page'), 100),
            'paged'          => max(1, (int) $request->get_param('paged')),
            'no_found_rows'  => false,
        ]);

        $search = (string) $request->get_param('s');
        if ($search !== '') {
            $queryArgs['s'] = $search;
        }

        $query = new \WP_Query($queryArgs);
        $items = array_map(
            fn(\WP_Post $post) => ['id' => $post->ID, 'title' => $post->post_title],
            $query->posts
        );

        $response = new \WP_REST_Response($items, 200);
        $response->header('X-WP-Total', (string) $query->found_posts);
        $response->header('X-WP-TotalPages', (string) $query->max_num_pages);

        return $response;
    }
}
