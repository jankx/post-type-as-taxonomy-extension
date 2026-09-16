<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Contracts;

/**
 * Reads and writes the selected post IDs for a given relationship.
 *
 * The default implementation (PostMetaStorage) persists data as a
 * serialized array in post meta. Swap this for a custom table or
 * external store by providing an alternative implementation.
 */
interface StorageInterface
{
    /**
     * Return the post IDs that are currently associated with $postId
     * under the given meta key.
     *
     * @param int    $postId   The ID of the post being edited.
     * @param string $metaKey  The meta key declared in the registration.
     * @return int[]           Array of associated post IDs (may be empty).
     */
    public function get(int $postId, string $metaKey): array;

    /**
     * Persist an array of associated post IDs.
     *
     * @param int    $postId   The ID of the post being edited.
     * @param string $metaKey  The meta key declared in the registration.
     * @param int[]  $ids      Sanitized post IDs to store.
     * @return bool            True on success, false on failure.
     */
    public function save(int $postId, string $metaKey, array $ids): bool;

    /**
     * Remove all stored associations for this post / meta key pair.
     *
     * @param int    $postId   The ID of the post being edited.
     * @param string $metaKey  The meta key declared in the registration.
     * @return bool            True on success, false on failure.
     */
    public function delete(int $postId, string $metaKey): bool;
}
