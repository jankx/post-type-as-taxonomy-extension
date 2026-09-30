<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\PostTypeAsTaxonomy\Api\SaveController;
use Jankx\Extensions\PostTypeAsTaxonomy\Api\SearchController;
use Jankx\Extensions\PostTypeAsTaxonomy\Renderer\GutenbergRenderer;
use Jankx\Extensions\PostTypeAsTaxonomy\Storage\PostMetaStorage;

/**
 * Post Type as Taxonomy extension.
 *
 * Provides a static API (PostTypeAsTaxonomyManager) that other extensions can
 * call to register a Custom Post Type as a taxonomy-like selector panel on
 * any post type's edit screen — supporting both Classic Editor and Gutenberg.
 *
 * Usage from another extension:
 *
 *   use Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager;
 *
 *   // In register_hooks() or after_setup_theme (priority >= 20):
 *   PostTypeAsTaxonomyManager::register(
 *       'destination_tour',                              // source post type
 *       ['tour', 'place', 'tour_journey'], // target post types (string|array)
 *       [
 *           'label'    => 'Điểm đến',
 *           'meta_key' => '_destination_tour_ids',
 *           'multiple' => true,
 *       ]
 *   );
 */
class PostTypeAsTaxonomyExtension extends AbstractExtension
{
    /** @var static */
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix  = 'Jankx\\Extensions\\PostTypeAsTaxonomy\\';
            $baseDir = __DIR__ . '/src/';
            $len     = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // Boot renderers after all extensions have had a chance to call
        // PostTypeAsTaxonomyManager::register() (priority 20 = after
        // the default after_setup_theme at priority 10).
        add_action('after_setup_theme', [$this, 'bootRenderers'], 20);

        // REST API controllers — registered on rest_api_init (hooked inside).
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            (new SaveController())->register();
            (new SearchController())->register();
        }
    }

    /**
     * Instantiate and boot the renderer(s) once all registrations are in place.
     */
    public function bootRenderers(): void
    {
        $registrations = PostTypeAsTaxonomyManager::getAll();

        if (empty($registrations)) {
            return;
        }

        $storage  = new PostMetaStorage();

        // GutenbergRenderer delegates Classic Editor rendering to
        // ClassicEditorRenderer internally — one boot covers both editors.
        $renderer = new GutenbergRenderer($storage);
        $renderer->boot($registrations);
    }
}
