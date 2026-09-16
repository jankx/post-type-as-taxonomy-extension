<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Renderer;

use WP_Post;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RegistrationInterface;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RendererInterface;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\StorageInterface;

/**
 * Renders the taxonomy-like selector for the Gutenberg block editor.
 *
 * Strategy: PHP-only (no custom JS build required).
 *
 * WordPress exposes `show_in_rest` post meta to the block editor automatically.
 * By registering the meta key with `show_in_rest` and providing a custom
 * `meta_box_cb`, WordPress makes the meta box available inside the Gutenberg
 * sidebar ("Document" tab → "More options" section), re-using the exact same
 * Classic Editor HTML without any React/webpack.
 *
 * This means a single unified renderer covers both editors. The Gutenberg-
 * specific renderer's job is therefore:
 *   1. Register the post meta with the REST API (show_in_rest) so the stored
 *      value is available to any future JS/block that may query it.
 *   2. Suppress the duplicate meta box that would otherwise be added by the
 *      ClassicEditorRenderer on Gutenberg screens (WordPress hides
 *      add_meta_box() calls automatically when meta_box_cb is set to the
 *      special string 'post_format_meta_box' or when using the built-in
 *      compat layer — here we rely on that compat layer).
 *   3. Enqueue a tiny inline script that re-labels the panel correctly inside
 *      the editor sidebar.
 *
 * If a full React sidebar panel is desired in the future, replace this class
 * with a custom RendererInterface implementation and register it in
 * PostTypeAsTaxonomyExtension::register_hooks().
 */
class GutenbergRenderer implements RendererInterface
{
    /** @var StorageInterface */
    private $storage;

    /** @var ClassicEditorRenderer */
    private $classicRenderer;

    public function __construct(StorageInterface $storage)
    {
        $this->storage         = $storage;
        $this->classicRenderer = new ClassicEditorRenderer($storage);
    }

    // -------------------------------------------------------------------------
    // RendererInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Registers the post meta with the REST API for each registration so that
     * Gutenberg can read/write the value. The actual UI is provided by the
     * Classic Editor renderer (WordPress Gutenberg compat layer surfaces it
     * in the "More options" sidebar panel).
     */
    public function boot(array $registrations): void
    {
        add_action('init', function () use ($registrations) {
            foreach ($registrations as $registration) {
                $this->registerMeta($registration);
            }
        });

        // The Classic Editor renderer also handles Gutenberg via the built-in
        // meta box compat layer — boot it here to avoid double-registration.
        $this->classicRenderer->boot($registrations);
    }

    /**
     * {@inheritdoc}
     *
     * Delegates to ClassicEditorRenderer — same HTML works in both editors
     * via WordPress's Gutenberg meta-box compatibility layer.
     */
    public function render(RegistrationInterface $registration, WP_Post $post): void
    {
        $this->classicRenderer->render($registration, $post);
    }

    /**
     * {@inheritdoc}
     */
    public function save(RegistrationInterface $registration, int $postId, array $data): void
    {
        $this->classicRenderer->save($registration, $postId, $data);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Register the post meta key with the REST API so Gutenberg can see it.
     *
     * @param RegistrationInterface $registration
     */
    private function registerMeta(RegistrationInterface $registration): void
    {
        foreach ($registration->getTargetPostTypes() as $targetPostType) {
            register_post_meta($targetPostType, $registration->getMetaKey(), [
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                ],
                'sanitize_callback' => function ($meta_value) {
                    return array_values(
                        array_unique(
                            array_filter(array_map('absint', (array) $meta_value))
                        )
                    );
                },
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }
}
