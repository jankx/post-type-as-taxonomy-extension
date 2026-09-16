<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy;

use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RegistrationInterface;

/**
 * Static registry for all "post type as taxonomy" registrations.
 *
 * This class intentionally uses only static state — it is a registry, not a
 * stateful service, so a Singleton instance would add unnecessary boilerplate.
 *
 * Usage from another extension's register_hooks():
 *
 *   // Apply to a single target post type (string shorthand)
 *   PostTypeAsTaxonomyManager::register('destination', 'tour', [
 *       'label'    => 'Điểm đến',
 *       'meta_key' => '_destination_ids',
 *       'multiple' => true,
 *   ]);
 *
 *   // Apply to multiple target post types (array)
 *   PostTypeAsTaxonomyManager::register('destination', ['tour', 'place', 'experience', 'tour_journey'], [
 *       'label'    => 'Điểm đến',
 *       'meta_key' => '_destination_ids',
 *       'multiple' => true,
 *   ]);
 */
class PostTypeAsTaxonomyManager
{
    /**
     * All registered items, keyed by their ID (source post type slug).
     *
     * @var RegistrationInterface[]
     */
    private static $registrations = [];

    /**
     * Map of target post type → registration IDs, for fast lookup.
     *
     * @var array<string, string[]>
     */
    private static $index = [];

    // Prevent instantiation — this class is a pure static registry.
    private function __construct() {}

    /**
     * Register a post type to behave like a taxonomy on one or more post types.
     *
     * @param string          $sourcePostType   Post type that acts as the taxonomy source.
     * @param string|string[] $targetPostTypes  One post type (string) or many (array).
     * @param array           $args {
     *     Optional. Configuration args. All keys are optional.
     *
     *     @type string   $label        Panel / meta-box title. Default: humanised source slug.
     *     @type string   $meta_key     Post meta key for storage. Default: '_<source>_ids'.
     *     @type bool     $multiple     Allow multi-select. Default: true.
     *     @type bool     $hierarchical Show tree UI. Default: false.
     *     @type string   $context      Meta-box context ('side'|'normal'|'advanced'). Default: 'side'.
     *     @type string   $priority     Meta-box priority ('default'|'high'|'low'). Default: 'default'.
     *     @type string   $id           Custom slug for HTML IDs / nonce. Default: '<source>-as-taxonomy'.
     *     @type array    $query_args   WP_Query args to fetch source posts. Default: all published, A→Z.
     * }
     * @return RegistrationInterface  The created registration (fluent-friendly).
     */
    public static function register(
        string $sourcePostType,
        $targetPostTypes,
        array $args = []
    ): RegistrationInterface {
        $registration = new Registration($sourcePostType, $targetPostTypes, $args);
        $id           = $registration->getId();

        self::$registrations[$id] = $registration;

        // Update reverse index.
        foreach ($registration->getTargetPostTypes() as $targetPostType) {
            self::$index[$targetPostType][] = $id;
        }

        return $registration;
    }

    /**
     * Return every registered item.
     *
     * @return RegistrationInterface[]
     */
    public static function getAll(): array
    {
        return array_values(self::$registrations);
    }

    /**
     * Return all registrations that should appear on a given post type's
     * edit screen.
     *
     * @param string $postType  The post type being edited (e.g. 'tour').
     * @return RegistrationInterface[]
     */
    public static function getForPostType(string $postType): array
    {
        if (empty(self::$index[$postType])) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn(string $id) => self::$registrations[$id] ?? null,
                self::$index[$postType]
            )
        ));
    }

    /**
     * Check whether a source post type has already been registered.
     *
     * @param string $sourcePostType
     * @param string $id             Optional: check by registration ID instead.
     * @return bool
     */
    public static function has(string $sourcePostType, string $id = ''): bool
    {
        if ($id) {
            return isset(self::$registrations[$id]);
        }

        foreach (self::$registrations as $registration) {
            if ($registration->getSourcePostType() === $sourcePostType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a registration by its ID (source post type slug or custom id).
     *
     * Primarily useful for testing.
     *
     * @param string $id
     */
    public static function deregister(string $id): void
    {
        if (!isset(self::$registrations[$id])) {
            return;
        }

        $registration = self::$registrations[$id];
        unset(self::$registrations[$id]);

        foreach ($registration->getTargetPostTypes() as $targetPostType) {
            self::$index[$targetPostType] = array_values(
                array_filter(
                    self::$index[$targetPostType] ?? [],
                    fn(string $rid) => $rid !== $id
                )
            );
        }
    }

    /**
     * Reset the entire registry (primarily for unit tests).
     */
    public static function reset(): void
    {
        self::$registrations = [];
        self::$index         = [];
    }
}
