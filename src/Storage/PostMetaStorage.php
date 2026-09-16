<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Storage;

use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\StorageInterface;

/**
 * Persists selected post IDs as a single post meta entry (serialized array).
 *
 * Storage format:
 *   meta_key => [12, 45, 78]   (array of integer post IDs)
 *
 * This is the default StorageInterface implementation. To use a custom table
 * or external store, implement StorageInterface and pass the custom instance
 * to the renderers via the extension's DI wiring.
 */
class PostMetaStorage implements StorageInterface
{
    /**
     * {@inheritdoc}
     */
    public function get(int $postId, string $metaKey): array
    {
        $raw = get_post_meta($postId, $metaKey, true);

        if (empty($raw) || !is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw)));
    }

    /**
     * {@inheritdoc}
     */
    public function save(int $postId, string $metaKey, array $ids): bool
    {
        $sanitized = array_values(array_unique(array_filter(array_map('absint', $ids))));

        if (empty($sanitized)) {
            return $this->delete($postId, $metaKey);
        }

        return (bool) update_post_meta($postId, $metaKey, $sanitized);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $postId, string $metaKey): bool
    {
        return (bool) delete_post_meta($postId, $metaKey);
    }
}
