<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy;

use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RegistrationInterface;

/**
 * Value object that describes a single "post type as taxonomy" registration.
 *
 * Instantiated exclusively by PostTypeAsTaxonomyManager::register().
 * Consumers should treat this as read-only after creation.
 *
 * The $targetPostTypes constructor parameter accepts either a plain string
 * (applies the UI to one post type) or an indexed array (applies to many).
 * Internally the value is always normalized to an array.
 *
 * Default args:
 *
 *   [
 *       'label'        => ucfirst($sourcePostType),
 *       'meta_key'     => '_' . $sourcePostType . '_ids',
 *       'multiple'     => true,
 *       'hierarchical' => false,
 *       'context'      => 'side',
 *       'priority'     => 'default',
 *       'query_args'   => [
 *           'posts_per_page' => -1,
 *           'orderby'        => 'title',
 *           'order'          => 'ASC',
 *           'post_status'    => 'publish',
 *       ],
 *   ]
 */
class Registration implements RegistrationInterface
{
    /** @var string */
    private $sourcePostType;

    /** @var string[] */
    private $targetPostTypes;

    /** @var array */
    private $args;

    /**
     * @param string          $sourcePostType   Post type acting as the taxonomy source.
     * @param string|string[] $targetPostTypes  One or more post types to attach the UI to.
     * @param array           $args             Optional overrides (see class docblock).
     */
    public function __construct(string $sourcePostType, $targetPostTypes, array $args = [])
    {
        $this->sourcePostType  = $sourcePostType;

        // Normalize: a plain string registers on exactly one post type.
        $this->targetPostTypes = is_array($targetPostTypes)
            ? array_values(array_unique(array_filter($targetPostTypes, 'is_string')))
            : [$targetPostTypes];

        $this->args = wp_parse_args($args, $this->defaults($sourcePostType));
    }

    // -------------------------------------------------------------------------
    // RegistrationInterface
    // -------------------------------------------------------------------------

    public function getSourcePostType(): string
    {
        return $this->sourcePostType;
    }

    public function getTargetPostTypes(): array
    {
        return $this->targetPostTypes;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getMetaKey(): string
    {
        return (string) $this->args['meta_key'];
    }

    public function getLabel(): string
    {
        return (string) $this->args['label'];
    }

    public function isMultiple(): bool
    {
        return (bool) $this->args['multiple'];
    }

    public function isHierarchical(): bool
    {
        return (bool) $this->args['hierarchical'];
    }

    public function getQueryArgs(): array
    {
        $queryArgs              = (array) $this->args['query_args'];
        $queryArgs['post_type'] = $this->sourcePostType; // always override
        return $queryArgs;
    }

    public function getId(): string
    {
        return isset($this->args['id']) && $this->args['id']
            ? (string) $this->args['id']
            : $this->sourcePostType . '-as-taxonomy';
    }

    public function getContext(): string
    {
        return (string) $this->args['context'];
    }

    public function getPriority(): string
    {
        return (string) $this->args['priority'];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the default args for a given source post type.
     *
     * @param string $sourcePostType
     * @return array
     */
    private function defaults(string $sourcePostType): array
    {
        return [
            'label'        => ucfirst(str_replace(['-', '_'], ' ', $sourcePostType)),
            'meta_key'     => '_' . $sourcePostType . '_ids',
            'multiple'     => true,
            'hierarchical' => false,
            'context'      => 'side',
            'priority'     => 'default',
            'id'           => '',
            'query_args'   => [
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'post_status'    => 'publish',
            ],
        ];
    }
}
