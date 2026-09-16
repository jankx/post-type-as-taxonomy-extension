<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Api;

use Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager;
use Jankx\Extensions\PostTypeAsTaxonomy\Storage\PostMetaStorage;

/**
 * REST endpoint: save selected post IDs for a given registration.
 *
 * POST /wp-json/jankx/v1/post-type-as-taxonomy/save
 *
 * Request body (JSON):
 * {
 *   "post_id":  123,
 *   "meta_key": "_destination_tour_ids",
 *   "ids":      [1, 2, 3]
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "ids":     [1, 2, 3]
 * }
 *
 * This endpoint is primarily consumed by a future Gutenberg JS panel.
 * Classic Editor saves happen through the standard form POST / save_post hook.
 */
class SaveController
{
    const NAMESPACE = 'jankx/v1';
    const ROUTE     = '/post-type-as-taxonomy/save';

    /** @var PostMetaStorage */
    private $storage;

    public function __construct()
    {
        $this->storage = new PostMetaStorage();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public function registerRoute(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'post_id'  => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'meta_key' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'ids'      => [
                    'required' => false,
                    'type'     => 'array',
                    'default'  => [],
                    'items'    => ['type' => 'integer'],
                ],
            ],
        ]);
    }

    public function checkPermission(\WP_REST_Request $request): bool
    {
        $postId = (int) $request->get_param('post_id');
        return current_user_can('edit_post', $postId);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId  = (int) $request->get_param('post_id');
        $metaKey = (string) $request->get_param('meta_key');
        $ids     = array_values(
            array_unique(
                array_filter(array_map('absint', (array) $request->get_param('ids')))
            )
        );

        // Validate: meta_key must belong to a known registration.
        $registration = $this->findRegistrationByMetaKey($postId, $metaKey);
        if (!$registration) {
            return new \WP_REST_Response(
                ['code' => 'invalid_meta_key', 'message' => 'Unknown meta key or post type.'],
                400
            );
        }

        $saved = $this->storage->save($postId, $metaKey, $ids);

        return new \WP_REST_Response([
            'success' => $saved,
            'ids'     => $this->storage->get($postId, $metaKey),
        ], 200);
    }

    /**
     * Find a registration whose meta_key matches and whose target post types
     * include the given post's post type.
     *
     * @param int    $postId
     * @param string $metaKey
     * @return \Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RegistrationInterface|null
     */
    private function findRegistrationByMetaKey(int $postId, string $metaKey)
    {
        $postType = get_post_type($postId);
        if (!$postType) {
            return null;
        }

        foreach (PostTypeAsTaxonomyManager::getForPostType($postType) as $registration) {
            if ($registration->getMetaKey() === $metaKey) {
                return $registration;
            }
        }

        return null;
    }
}
