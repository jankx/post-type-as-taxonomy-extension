# Post Type as Taxonomy

Extension cung cấp API để đăng ký một **Custom Post Type** hoạt động như một **taxonomy** — hiển thị panel chọn bài viết (kiểu checkbox/radio list) ngay trong sidebar khi edit post, hỗ trợ cả **Classic Editor** và **Gutenberg**.

---

## Tại sao cần extension này?

WordPress taxonomy (term) có hạn chế về SEO và nội dung: term không có featured image riêng, không có slug tùy ý, không có Gutenberg editor, không có custom fields linh hoạt. Extension này cho phép dùng **Custom Post Type** với đầy đủ sức mạnh đó — nhưng *giao diện chọn* vẫn thân thiện như taxonomy.

| | Taxonomy (term) | Post Type as Taxonomy |
|---|---|---|
| Featured image | ❌ | ✅ |
| Gutenberg content | ❌ | ✅ |
| SEO slug tùy chỉnh | Hạn chế | ✅ |
| Custom fields | Hạn chế | ✅ |
| Cấu trúc cha-con | ✅ | ✅ (hierarchical CPT) |
| UI chọn khi edit post | ✅ | ✅ (do extension này cung cấp) |

---

## Kiến trúc

```
PostTypeAsTaxonomyManager        ← Static registry (điểm vào công khai)
│
├── Registration                 ← Value object (RegistrationInterface)
│
├── Renderer/
│   ├── GutenbergRenderer        ← Boot cho cả 2 editors, đăng ký show_in_rest
│   └── ClassicEditorRenderer    ← Meta box HTML + inline search + save handler
│
├── Storage/
│   └── PostMetaStorage          ← Đọc/ghi post_meta (StorageInterface)
│
├── Api/
│   ├── SaveController           ← REST: POST /jankx/v1/post-type-as-taxonomy/save
│   └── SearchController         ← REST: GET  /jankx/v1/post-type-as-taxonomy/search
│
└── Contracts/
    ├── RegistrationInterface
    ├── RendererInterface
    └── StorageInterface
```

### Design pattern

- **Manager** (static registry) thay vì Singleton — vì đây là registry thuần tuý, không có state phức tạp. Extension khác gọi `PostTypeAsTaxonomyManager::register(...)` mà không cần inject instance.
- **Contracts (Interfaces)** cho cả 3 tầng (Registration / Renderer / Storage) — dễ swap implementation mà không thay đổi consumer.

### Hook timing

```
after_setup_theme @10   ← Các extension gọi register_hooks()
after_setup_theme @15   ← Consumer gọi PostTypeAsTaxonomyManager::register(...)
after_setup_theme @20   ← Extension boot: GutenbergRenderer::boot($registrations)
                           ├─ init hook → register_post_meta (show_in_rest)
                           └─ add_meta_boxes hook → thêm meta box vào target post types
```

---

## Cài đặt

Extension được load tự động qua hệ thống extension của Jankx khi có `manifest.json` với `auto_activate: true`. Không cần bước cài đặt thêm.

**Yêu cầu:**
- PHP >= 7.4
- WordPress >= 5.8

---

## Sử dụng

### 1. Gọi `register()` từ extension khác

Trong `register_hooks()` hoặc hook `after_setup_theme` (priority ≤ 15):

```php
use Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager;

PostTypeAsTaxonomyManager::register(
    'destination_tour',                              // source: post type đóng vai taxonomy
    ['tour', 'place', 'experience', 'tour_journey'], // target: string hoặc array
    [
        'label'        => 'Điểm đến Tour',
        'meta_key'     => '_destination_tour_ids',
        'multiple'     => true,
        'hierarchical' => true,
    ]
);
```

**Target post types: `string` hoặc `array`**

```php
// Apply cho 1 post type — truyền string
PostTypeAsTaxonomyManager::register('destination_tour', 'tour', [...]);

// Apply cho nhiều post type — truyền array
PostTypeAsTaxonomyManager::register('destination_tour', ['tour', 'place'], [...]);
```

### 2. Đọc giá trị đã lưu

```php
// Lấy danh sách post IDs đã chọn
$ids = get_post_meta($postId, '_destination_tour_ids', true);
// → [12, 45, 78]  (array of int)

// Lấy posts đầy đủ
$destinations = get_posts([
    'post_type'      => 'destination_tour',
    'post__in'       => $ids,
    'posts_per_page' => -1,
    'orderby'        => 'post__in',
]);
```

### 3. Kiểm tra trạng thái registry

```php
use Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager;

// Lấy tất cả registrations
$all = PostTypeAsTaxonomyManager::getAll();

// Lấy registrations cho 1 post type cụ thể
$forTour = PostTypeAsTaxonomyManager::getForPostType('tour');

// Kiểm tra đã đăng ký chưa
if (PostTypeAsTaxonomyManager::has('destination_tour')) { ... }
```

---

## API Reference

### `PostTypeAsTaxonomyManager::register()`

```php
PostTypeAsTaxonomyManager::register(
    string $sourcePostType,
    string|string[] $targetPostTypes,
    array $args = []
): RegistrationInterface
```

#### `$sourcePostType`

Slug của Custom Post Type sẽ đóng vai "taxonomy". Post type này phải đã được `register_post_type()` trước hoặc cùng lúc (trên hook `init`).

#### `$targetPostTypes`

Post type(s) sẽ hiển thị panel chọn. Nhận:
- **`string`** — apply cho đúng 1 post type
- **`string[]`** — apply cho nhiều post types

#### `$args`

| Key | Type | Default | Mô tả |
|---|---|---|---|
| `label` | `string` | Humanised source slug | Tiêu đề của meta box / panel |
| `meta_key` | `string` | `_<source>_ids` | Post meta key để lưu IDs đã chọn |
| `multiple` | `bool` | `true` | `true` = checkbox (nhiều), `false` = radio (một) |
| `hierarchical` | `bool` | `false` | `true` = hiển thị tree (như Categories), `false` = flat list |
| `context` | `string` | `'side'` | Vị trí meta box: `'side'`, `'normal'`, `'advanced'` |
| `priority` | `string` | `'default'` | Ưu tiên meta box: `'high'`, `'default'`, `'low'` |
| `id` | `string` | `<source>-as-taxonomy` | Slug dùng cho HTML ID và nonce |
| `query_args` | `array` | Tất cả published, A→Z | `WP_Query` args để lấy danh sách source posts |

---

## Contracts

Toàn bộ logic được định nghĩa qua interfaces — bạn có thể swap bất kỳ implementation nào mà không thay đổi consumer.

### `RegistrationInterface`

Mô tả một registration. Read-only value object.

```php
interface RegistrationInterface {
    public function getSourcePostType(): string;
    public function getTargetPostTypes(): array;   // luôn là array, dù register() nhận string
    public function getArgs(): array;
    public function getMetaKey(): string;
    public function getLabel(): string;
    public function isMultiple(): bool;
    public function isHierarchical(): bool;
    public function getQueryArgs(): array;
    public function getId(): string;
    public function getContext(): string;
    public function getPriority(): string;
}
```

### `RendererInterface`

Render UI panel và xử lý save.

```php
interface RendererInterface {
    public function boot(array $registrations): void;
    public function render(RegistrationInterface $registration, WP_Post $post): void;
    public function save(RegistrationInterface $registration, int $postId, array $data): void;
}
```

### `StorageInterface`

Đọc/ghi IDs đã chọn.

```php
interface StorageInterface {
    public function get(int $postId, string $metaKey): array;        // → int[]
    public function save(int $postId, string $metaKey, array $ids): bool;
    public function delete(int $postId, string $metaKey): bool;
}
```

---

## Renderers

### ClassicEditorRenderer

Thêm meta box vào Classic Editor với:

- **Checkbox list** khi `multiple = true`
- **Radio list** khi `multiple = false`
- **Search field** tự động xuất hiện khi source post type có > 10 bài viết
- Nonce verification + capability check trong `save()`

Ví dụ UI (Classic Editor, sidebar):

```
┌─ Điểm đến Tour ─────────────────┐
│ [🔍 Tìm điểm đến...]            │
│                                  │
│ ☑ Hà Nội                        │
│ ☑ Đà Nẵng                       │
│ □  Hồ Chí Minh                  │
│ □  Phú Quốc                     │
└──────────────────────────────────┘
```

### GutenbergRenderer

Wrapper PHP-only (không cần build JS / webpack):

1. Đăng ký `post_meta` với `show_in_rest` → meta value có thể đọc qua REST API
2. Delegate toàn bộ HTML render sang `ClassicEditorRenderer`
3. WordPress Gutenberg tự nhúng meta box vào sidebar "Document → More options" qua compat layer

> **Phase 2**: Thay thế bằng React sidebar panel (`@wordpress/components`) để có UX đẹp hơn. Chỉ cần implement `RendererInterface` mới và đổi trong `PostTypeAsTaxonomyExtension::bootRenderers()`.

---

## REST API

Dùng cho Gutenberg JS panel tương lai (và AJAX requests nếu cần).

### `GET /wp-json/jankx/v1/post-type-as-taxonomy/search`

Tìm kiếm posts từ source post type.

**Query params:**

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `registration_id` | `string` | ✅ | ID của registration (mặc định: `<source>-as-taxonomy`) |
| `s` | `string` | | Từ khóa tìm kiếm |
| `paged` | `int` | | Trang (default: 1) |
| `per_page` | `int` | | Số kết quả/trang (default: 20, max: 100) |

**Response:**
```json
[
  { "id": 12, "title": "Hà Nội" },
  { "id": 45, "title": "Đà Nẵng" }
]
```

Headers: `X-WP-Total`, `X-WP-TotalPages`

**Capability:** `edit_posts`

---

### `POST /wp-json/jankx/v1/post-type-as-taxonomy/save`

Lưu IDs đã chọn (dùng cho Gutenberg JS).

**Request body (JSON):**
```json
{
  "post_id":  123,
  "meta_key": "_destination_tour_ids",
  "ids":      [12, 45]
}
```

**Response:**
```json
{
  "success": true,
  "ids":     [12, 45]
}
```

**Capability:** `edit_post` (theo `post_id`)

---

## Storage

Mặc định dùng `PostMetaStorage` — lưu array IDs vào một `post_meta` entry duy nhất:

```
wp_postmeta:
  post_id   = 100  (bài tour)
  meta_key  = _destination_tour_ids
  meta_value = a:2:{i:0;i:12;i:1;i:45;}  (serialized PHP array)
```

Để dùng storage khác (ví dụ custom table):

```php
// Implement StorageInterface
class MyCustomStorage implements StorageInterface { ... }

// Inject vào renderer trong PostTypeAsTaxonomyExtension::bootRenderers()
$renderer = new GutenbergRenderer(new MyCustomStorage());
```

---

## Ví dụ thực tế: nibitour-business-logics

Extension [`nibitour-business-logics`](../nibitour-business-logics/) đăng ký `destination_tour` CPT và apply nó vào 4 post types:

```php
// NibitourBusinessLogicsExtension.php — after_setup_theme @15
PostTypeAsTaxonomyManager::register(
    'destination_tour',
    ['tour', 'place', 'experience', 'tour_journey'],
    [
        'label'        => 'Điểm đến Tour',
        'meta_key'     => '_destination_tour_ids',
        'multiple'     => true,
        'hierarchical' => true,
        'context'      => 'side',
        'priority'     => 'default',
        'query_args'   => [
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ],
    ]
);
```

**Kết quả:** Khi edit bất kỳ bài `tour`, `place`, `experience`, hoặc `tour_journey` nào, sidebar sẽ có panel **"Điểm đến Tour"** với danh sách tất cả `destination_tour` posts để chọn.

---

## Mở rộng

### Thêm registration mới từ extension bất kỳ

```php
add_action('after_setup_theme', function () {
    if (!class_exists(\Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager::class)) {
        return;
    }

    \Jankx\Extensions\PostTypeAsTaxonomy\PostTypeAsTaxonomyManager::register(
        'hotel',        // source post type
        'tour',         // target: 1 post type (string)
        [
            'label'    => 'Khách sạn',
            'meta_key' => '_tour_hotel_ids',
            'multiple' => true,
        ]
    );
}, 15);
```

### Custom Renderer (React panel)

```php
// 1. Implement RendererInterface
class ReactPanelRenderer implements RendererInterface { ... }

// 2. Override trong PostTypeAsTaxonomyExtension::bootRenderers()
$renderer = new ReactPanelRenderer(new PostMetaStorage());
$renderer->boot($registrations);
```

### Custom Storage

```php
class P2PStorage implements StorageInterface {
    public function get(int $postId, string $metaKey): array { ... }
    public function save(int $postId, string $metaKey, array $ids): bool { ... }
    public function delete(int $postId, string $metaKey): bool { ... }
}
```

---

## File structure

```
extensions/post-type-as-taxonomy/
├── PostTypeAsTaxonomyExtension.php   ← Bootstrap
├── manifest.json                      ← Extension metadata
├── composer.json                      ← PSR-4 autoload
└── src/
    ├── Contracts/
    │   ├── RegistrationInterface.php  ← Contract: mô tả 1 registration
    │   ├── RendererInterface.php      ← Contract: render UI + save
    │   └── StorageInterface.php       ← Contract: đọc/ghi IDs
    ├── Registration.php               ← Value object (RegistrationInterface)
    ├── PostTypeAsTaxonomyManager.php  ← Static registry (điểm vào công khai)
    ├── Storage/
    │   └── PostMetaStorage.php        ← Default: lưu qua post_meta
    ├── Renderer/
    │   ├── ClassicEditorRenderer.php  ← Meta box HTML + save handler
    │   └── GutenbergRenderer.php      ← show_in_rest + delegate Classic
    └── Api/
        ├── SaveController.php          ← REST save endpoint
        └── SearchController.php        ← REST search endpoint
```
