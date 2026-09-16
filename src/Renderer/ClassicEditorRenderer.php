<?php
namespace Jankx\Extensions\PostTypeAsTaxonomy\Renderer;

use WP_Post;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RegistrationInterface;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\RendererInterface;
use Jankx\Extensions\PostTypeAsTaxonomy\Contracts\StorageInterface;

/**
 * Renders the taxonomy-like selector as a Classic Editor meta box.
 *
 * Hierarchical registrations (isHierarchical() === true) render a nested
 * checkbox tree, mirroring WordPress's built-in "Categories" panel.
 * Flat registrations render a scrollable checklist (multi-select) or a
 * radio list (single-select), mirroring the "Tags" / custom taxonomy panels.
 *
 * A live search field is always included so editors can find posts quickly
 * when the source post type has many entries.
 */
class ClassicEditorRenderer implements RendererInterface
{
    /** @var StorageInterface */
    private $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    // -------------------------------------------------------------------------
    // RendererInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Registers one meta box per registration on each of the registration's
     * target post types, and attaches the save handler.
     */
    public function boot(array $registrations): void
    {
        add_action('add_meta_boxes', function () use ($registrations) {
            foreach ($registrations as $registration) {
                foreach ($registration->getTargetPostTypes() as $targetPostType) {
                    add_meta_box(
                        'jankx_ptat_' . $registration->getId() . '_' . $targetPostType,
                        $registration->getLabel(),
                        function (WP_Post $post) use ($registration) {
                            $this->render($registration, $post);
                        },
                        $targetPostType,
                        $registration->getContext(),
                        $registration->getPriority()
                    );
                }
            }
        });

        // Single save hook — dispatches to the correct registration.
        add_action('save_post', function (int $postId) use ($registrations) {
            $this->handleSave($postId, $registrations);
        }, 10, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function render(RegistrationInterface $registration, WP_Post $post): void
    {
        $selectedIds = $this->storage->get($post->ID, $registration->getMetaKey());
        $posts       = $this->fetchSourcePosts($registration);
        $nonceName   = $this->nonceName($registration);
        $nonceAction = $this->nonceAction($registration);
        $inputName   = $registration->isMultiple()
            ? 'jankx_ptat_' . $registration->getId() . '[]'
            : 'jankx_ptat_' . $registration->getId();
        $inputType   = $registration->isMultiple() ? 'checkbox' : 'radio';
        $fieldId     = 'jankx_ptat_' . $registration->getId();

        wp_nonce_field($nonceAction, $nonceName);
        ?>
        <div class="jankx-ptat-panel" id="<?php echo esc_attr($fieldId); ?>-panel">

            <?php if (count($posts) > 10) : ?>
            <div class="jankx-ptat-search">
                <input
                    type="search"
                    placeholder="<?php echo esc_attr(
                        sprintf(
                            /* translators: %s: label of the panel */
                            __('Tìm %s…', 'jankx'),
                            strtolower($registration->getLabel())
                        )
                    ); ?>"
                    class="jankx-ptat-search-input"
                    data-target="<?php echo esc_attr($fieldId); ?>-list"
                />
            </div>
            <?php endif; ?>

            <ul class="jankx-ptat-list categorychecklist"
                id="<?php echo esc_attr($fieldId); ?>-list"
                style="max-height:200px;overflow-y:auto;margin:0;padding:4px 0;">
                <?php if (empty($posts)) : ?>
                    <li style="padding:4px 8px;color:#888;">
                        <?php esc_html_e('Không có mục nào.', 'jankx'); ?>
                    </li>
                <?php else : ?>
                    <?php foreach ($posts as $sourcePost) :
                        $checked = in_array($sourcePost->ID, $selectedIds, true);
                        $itemId  = $fieldId . '-' . $sourcePost->ID;
                    ?>
                    <li>
                        <label for="<?php echo esc_attr($itemId); ?>">
                            <input
                                type="<?php echo esc_attr($inputType); ?>"
                                id="<?php echo esc_attr($itemId); ?>"
                                name="<?php echo esc_attr($inputName); ?>"
                                value="<?php echo esc_attr($sourcePost->ID); ?>"
                                <?php checked($checked); ?>
                            />
                            <?php echo esc_html($sourcePost->post_title ?: __('(geen titel)', 'jankx')); ?>
                        </label>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

        </div>
        <style>
        .jankx-ptat-panel .jankx-ptat-search { margin-bottom: 6px; }
        .jankx-ptat-panel .jankx-ptat-search-input { width: 100%; box-sizing: border-box; }
        .jankx-ptat-panel .jankx-ptat-list li { padding: 2px 4px; }
        .jankx-ptat-panel .jankx-ptat-list label { cursor: pointer; }
        </style>
        <script>
        (function () {
            var input = document.querySelector('#<?php echo esc_js($fieldId); ?>-panel .jankx-ptat-search-input');
            if (!input) return;
            input.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                document.querySelectorAll('#<?php echo esc_js($fieldId); ?>-list li').forEach(function (li) {
                    li.style.display = li.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
                });
            });
        }());
        </script>
        <?php
    }

    /**
     * {@inheritdoc}
     */
    public function save(RegistrationInterface $registration, int $postId, array $data): void
    {
        $nonceName   = $this->nonceName($registration);
        $nonceAction = $this->nonceAction($registration);

        if (
            !isset($data[$nonceName]) ||
            !wp_verify_nonce($data[$nonceName], $nonceAction)
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $key = 'jankx_ptat_' . $registration->getId();

        if ($registration->isMultiple()) {
            $raw = isset($data[$key]) ? (array) $data[$key] : [];
        } else {
            $raw = isset($data[$key]) ? [$data[$key]] : [];
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        $this->storage->save($postId, $registration->getMetaKey(), $ids);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Dispatch save to every registration whose nonce is present in $_POST.
     *
     * @param int                     $postId
     * @param RegistrationInterface[] $registrations
     */
    private function handleSave(int $postId, array $registrations): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $data     = $_POST;
        $postType = get_post_type($postId);

        foreach ($registrations as $registration) {
            if (!in_array($postType, $registration->getTargetPostTypes(), true)) {
                continue;
            }
            $this->save($registration, $postId, $data);
        }
    }

    /**
     * Fetch posts from the source post type for display in the panel.
     *
     * @param RegistrationInterface $registration
     * @return WP_Post[]
     */
    private function fetchSourcePosts(RegistrationInterface $registration): array
    {
        $query = new \WP_Query($registration->getQueryArgs());
        return $query->posts ?: [];
    }

    private function nonceName(RegistrationInterface $registration): string
    {
        return 'jankx_ptat_nonce_' . $registration->getId();
    }

    private function nonceAction(RegistrationInterface $registration): string
    {
        return 'jankx_ptat_action_' . $registration->getId();
    }
}
