<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Contracts;

use WP_Post;

/**
 * Renders the "post type as taxonomy" UI panel for a given registration.
 *
 * Two concrete implementations exist:
 *   - ClassicEditorRenderer — adds a meta box via add_meta_boxes
 *   - GutenbergRenderer     — registers a PluginDocumentSettingPanel via PHP
 *                             (show_in_rest + meta_box_cb fallback, no JS build)
 *
 * Additional renderers (e.g. a full React panel) can be swapped in by
 * injecting a custom RendererInterface into the Manager.
 */
interface RendererInterface
{
    /**
     * Register all necessary WordPress hooks for this renderer.
     *
     * Called once during `register_hooks()` in the extension bootstrap.
     * Implementations should hook into `add_meta_boxes`,
     * `enqueue_block_editor_assets`, REST registration, etc.
     *
     * @param RegistrationInterface[] $registrations  All registered items.
     */
    public function boot(array $registrations): void;

    /**
     * Output the HTML for the selector panel inside a meta box callback.
     *
     * @param RegistrationInterface $registration  The item being rendered.
     * @param WP_Post               $post          The post being edited.
     */
    public function render(RegistrationInterface $registration, WP_Post $post): void;

    /**
     * Persist the selected post IDs from a submitted $_POST payload.
     *
     * Implementations must verify the nonce and capability before writing.
     *
     * @param RegistrationInterface $registration  The item being saved.
     * @param int                   $postId        The post being saved.
     * @param array                 $data          Raw $_POST data (unsanitized).
     */
    public function save(RegistrationInterface $registration, int $postId, array $data): void;
}
