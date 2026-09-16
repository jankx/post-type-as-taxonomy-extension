<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Contracts;

/**
 * Describes a single "post type as taxonomy" registration.
 *
 * A registration ties a **source post type** (the post type that acts like a
 * taxonomy) to one or more **target post types** (the post types whose edit
 * screens will show the taxonomy-like UI panel).
 *
 * Example — registering "destination" as a taxonomy-like panel on "tour":
 *
 *   PostTypeAsTaxonomyManager::register('destination', ['tour'], [
 *       'label'        => 'Điểm đến',
 *       'meta_key'     => '_destination_ids',
 *       'multiple'     => true,
 *       'hierarchical' => false,
 *       'query_args'   => ['posts_per_page' => -1, 'orderby' => 'title'],
 *   ]);
 */
interface RegistrationInterface
{
    /**
     * The post type that acts as the "taxonomy" (source of selectable items).
     *
     * @return string  e.g. 'destination'
     */
    public function getSourcePostType(): string;

    /**
     * The post types whose edit screens will show this panel.
     *
     * The public registration API (PostTypeAsTaxonomyManager::register) accepts
     * either a plain string (single post type) or an array (multiple post types).
     * This method always returns the normalized array form.
     *
     *   // string → applies to one post type
     *   PostTypeAsTaxonomyManager::register('destination', 'tour', [...]);
     *
     *   // array → applies to many post types
     *   PostTypeAsTaxonomyManager::register('destination', ['tour', 'trip'], [...]);
     *
     * @return string[]  e.g. ['tour'] or ['tour', 'trip']
     */
    public function getTargetPostTypes(): array;

    /**
     * Full args array passed at registration time (merged with defaults).
     *
     * @return array
     */
    public function getArgs(): array;

    /**
     * The post meta key used to persist the selected post IDs.
     *
     * @return string  e.g. '_destination_ids'
     */
    public function getMetaKey(): string;

    /**
     * Human-readable label shown as the panel / meta-box title.
     *
     * @return string  e.g. 'Điểm đến'
     */
    public function getLabel(): string;

    /**
     * Whether the user can select multiple posts (true) or only one (false).
     *
     * @return bool
     */
    public function isMultiple(): bool;

    /**
     * Whether the source post type is hierarchical (parent/child tree UI).
     *
     * When true the Classic Editor renderer mimics the "Categories" checkbox
     * tree; when false it renders a flat searchable list like "Tags".
     *
     * @return bool
     */
    public function isHierarchical(): bool;

    /**
     * WP_Query args used to fetch the selectable posts from the source post
     * type.  The 'post_type' key is always overridden by getSourcePostType().
     *
     * @return array
     */
    public function getQueryArgs(): array;

    /**
     * Unique slug for this registration (used as HTML ID / nonce prefix).
     *
     * Defaults to "{source_post_type}-as-taxonomy".
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Context in which the meta box / panel should appear.
     *
     * Accepted Classic Editor values: 'normal' | 'side' | 'advanced'.
     * For Gutenberg this maps to the document settings panel.
     *
     * @return string
     */
    public function getContext(): string;

    /**
     * Priority of the meta box within its context.
     *
     * Accepted values: 'high' | 'default' | 'low'.
     *
     * @return string
     */
    public function getPriority(): string;
}
