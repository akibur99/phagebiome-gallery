<?php
// WP stub harness for Phagebiome Gallery logic tests.
error_reporting(E_ALL);
define('ABSPATH', '/tmp/');
define('PGL_VERSION', '1.0.0');
define('PGL_URL', 'http://example.test/wp-content/plugins/phagebiome-gallery/');
define('PGL_ITEM', 'pgl_item');
define('PGL_CAT', 'pgl_category');
define('PGL_OPTION', 'pgl_settings');
define('PGL_MENU', 'pgl-gallery');

function add_shortcode($t, $c) {} function add_action(...$a) {} function add_filter(...$a) {}
function wp_register_style(...$a) {} function wp_register_script(...$a) {}
function wp_enqueue_style(...$a) {} function wp_enqueue_script(...$a) {}
function wp_style_is(...$a) { return true; } function wp_script_is(...$a) { return true; }
function is_admin() { return false; } function is_singular() { return true; }
function get_queried_object() { return null; } function has_shortcode(...$a) { return false; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return $s; }
function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function __($s, $d = null) { return $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_attr_e($s, $d = null) { echo $s; }
function esc_html_e($s, $d = null) { echo $s; }
function selected($a, $b, $e = true) { return $a == $b ? ' selected="selected"' : ''; }
function checked($a, $b, $e = true) { return $a == $b ? ' checked="checked"' : ''; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $s)); }
function sanitize_file_name($s) { return preg_replace('/[^a-z0-9._\-]/i', '', (string) $s); }
function absint($v) { return abs((int) $v); }
function wp_unslash($s) { return $s; }
function add_query_arg($args, $base) {
  $q = http_build_query(array_filter($args, function ($v) { return '' !== $v && null !== $v; }));
  return $base . ($q ? ((strpos($base, '?') ? '&' : '?') . $q) : '');
}
function get_permalink() { return 'http://example.test/gallery/'; }
function home_url($p = '/') { return 'http://example.test' . $p; }
function wp_json_encode($d, $o = 0) { return json_encode($d, $o); }
function update_post_meta($id, $k, $v) { $GLOBALS['META'][$id][$k] = $v; return true; }
function get_post_meta($id, $k, $s) { return $GLOBALS['META'][$id][$k] ?? ''; }
function delete_post_meta($id, $k) { unset($GLOBALS['META'][$id][$k]); return true; }
function get_term_meta($id, $k, $s) { return $GLOBALS['TMETA'][$id][$k] ?? ''; }
function update_term_meta($id, $k, $v) { $GLOBALS['TMETA'][$id][$k] = $v; return true; }
function delete_term_meta($id, $k) { unset($GLOBALS['TMETA'][$id][$k]); return true; }

$GLOBALS['OPT'] = null; // override options in tests
function get_option($k, $d = false) { return $GLOBALS['OPT'] ?? $d; }

// Fake attachments: id => [mime, file-exists]
$GLOBALS['ATTS'] = [
  101 => ['image/jpeg', true], 102 => ['image/png', true],
  103 => ['image/svg+xml', true], 104 => ['image/jpeg', false],
];
function get_post($id) {
  if (isset($GLOBALS['ATTS'][$id])) { $p = new stdClass(); $p->ID = $id; $p->post_type = 'attachment'; return $p; }
  foreach ($GLOBALS['ITEMS'] as $it) {
    if ($it['id'] === $id) { $p = new stdClass(); $p->ID = $id; $p->post_type = PGL_ITEM; $p->post_title = $it['title']; $p->post_status = $it['status']; return $p; }
  }
  return null;
}
function get_post_mime_type($id) { return $GLOBALS['ATTS'][$id][0] ?? ''; }
function get_attached_file($id) {
  if (!isset($GLOBALS['ATTS'][$id]) || !$GLOBALS['ATTS'][$id][1]) return '';
  $f = sys_get_temp_dir() . '/pgl-att-' . $id . '.img';
  if (!is_file($f)) file_put_contents($f, 'x');
  return $f;
}
function wp_attachment_is_image($id) { return in_array(get_post_mime_type($id), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true); }
function wp_get_attachment_image($id, $size = 'medium', $icon = false, $attr = []) {
  if (!wp_attachment_is_image($id)) return '';
  $a = '';
  foreach ((array) $attr as $k => $v) $a .= ' ' . $k . '="' . esc_attr($v) . '"';
  return '<img src="http://example.test/img' . $id . '.jpg"' . $a . ' />';
}
function wp_get_attachment_image_src($id, $size = 'medium') {
  if (!wp_attachment_is_image($id)) return false;
  return ['http://example.test/img' . $id . '.jpg', 768, 512];
}
function wp_get_attachment_url($id) {
  if (!isset($GLOBALS['ATTS'][$id])) return false;
  return 'http://example.test/full' . $id . '.jpg';
}

// Fake items: id, title, status, att, cats(slugs), order, caption, alt
$GLOBALS['ITEMS'] = [
  ['id' => 1, 'title' => 'Wet-lab', 'status' => 'publish', 'att' => 101, 'cats' => ['lab', 'research'], 'order' => 2, 'caption' => 'Cap one', 'alt' => ''],
  ['id' => 2, 'title' => 'Field trip', 'status' => 'publish', 'att' => 102, 'cats' => ['field'], 'order' => 1, 'caption' => '', 'alt' => 'Custom alt'],
  ['id' => 3, 'title' => 'Draft one', 'status' => 'draft', 'att' => 101, 'cats' => ['lab'], 'order' => 5, 'caption' => '', 'alt' => ''],
  ['id' => 4, 'title' => 'SVG item', 'status' => 'publish', 'att' => 103, 'cats' => ['lab'], 'order' => 4, 'caption' => '', 'alt' => ''],
  ['id' => 5, 'title' => 'No image', 'status' => 'publish', 'att' => 0, 'cats' => [], 'order' => 6, 'caption' => '', 'alt' => ''],
  ['id' => 6, 'title' => 'Lone wolf', 'status' => 'publish', 'att' => 101, 'cats' => [], 'order' => 3, 'caption' => '', 'alt' => ''],
];
$GLOBALS['META'] = [
  1 => ['_pgl_attachment_id' => '101', '_pgl_caption' => 'Cap one', '_pgl_alt' => '', '_pgl_order_manual' => '', '_pgl_order' => 2],
  2 => ['_pgl_attachment_id' => '102', '_pgl_caption' => '', '_pgl_alt' => 'Custom alt', '_pgl_order_manual' => '', '_pgl_order' => 1],
  3 => ['_pgl_attachment_id' => '101', '_pgl_order_manual' => '', '_pgl_order' => 5],
  4 => ['_pgl_attachment_id' => '103', '_pgl_order_manual' => '', '_pgl_order' => 4],
  5 => ['_pgl_attachment_id' => '', '_pgl_order_manual' => '', '_pgl_order' => 6],
  6 => ['_pgl_attachment_id' => '101', '_pgl_order_manual' => '', '_pgl_order' => 3],
  101 => ['_wp_attachment_image_alt' => 'Library alt'],
];
$GLOBALS['TERMS'] = [
  (object) ['term_id' => 11, 'slug' => 'lab', 'name' => 'Lab'],
  (object) ['term_id' => 12, 'slug' => 'research', 'name' => 'Research'],
  (object) ['term_id' => 13, 'slug' => 'field', 'name' => 'Field Work'],
  (object) ['term_id' => 14, 'slug' => 'events', 'name' => 'Events'],
];
$GLOBALS['TMETA'] = [11 => ['_pgl_term_order' => 1], 12 => ['_pgl_term_order' => 2], 13 => ['_pgl_term_order' => 3], 14 => ['_pgl_term_order' => 4]];
$GLOBALS['ITEMTERMS'] = [1 => [11, 12], 2 => [13], 3 => [11], 4 => [11]];

class FakeItemPost { public $ID; public $post_title; public $post_status; public function __construct($it) { $this->ID = $it['id']; $this->post_title = $it['title']; $this->post_status = $it['status']; } }
function get_posts($args) {
  if (($args['post_type'] ?? '') === 'attachment') return [];
  $out = [];
  foreach ($GLOBALS['ITEMS'] as $it) {
    if (isset($args['post_status'])) {
      $want = (array) $args['post_status'];
      if (!in_array($it['status'], $want, true)) continue;
    }
    $out[] = new FakeItemPost($it);
  }
  return $out;
}
function get_the_terms($id, $tax) {
  $out = [];
  foreach ($GLOBALS['ITEMTERMS'][$id] ?? [] as $tid) {
    foreach ($GLOBALS['TERMS'] as $t) if ($t->term_id === $tid) $out[] = $t;
  }
  return $out;
}
function get_terms($args) {
  $out = [];
  $slugs = $args['slug'] ?? null;
  foreach ($GLOBALS['TERMS'] as $t) {
    if (is_array($slugs) && !in_array($t->slug, $slugs, true)) continue;
    $out[] = $t;
  }
  return $out;
}
function is_wp_error($t) { return false; }
function wp_parse_args($a, $d = []) { return array_merge($d, is_array($a) ? $a : []); }

require __DIR__ . '/includes/class-order.php';
require __DIR__ . '/includes/class-meta.php';
require __DIR__ . '/includes/class-settings.php';
require __DIR__ . '/includes/class-shortcode.php';
require __DIR__ . '/includes/class-admin.php';

$fail = 0;
function check($label, $cond) { global $fail; if (!$cond) { $fail++; echo "FAIL: $label\n"; } else { echo "ok: $label\n"; } }
function payload_of($html, $cls) {
  if (preg_match('/<script type="application\/json" class="' . $cls . '">(.*?)<\/script>/s', $html, $m)) return $m[1];
  return '[]';
}

// --- items(): published + usable only, asc order ---
$items = PGL_Shortcode::items();
check('published usable only', [2, 1, 6] === array_column($items, 'id'));
check('svg excluded', !in_array(4, array_column($items, 'id')));
check('imageless excluded', !in_array(5, array_column($items, 'id')));
check('custom alt kept', 'Custom alt' === $items[0]['alt']);
check('library alt fallback', 'Library alt' === $items[1]['alt']);

// --- direction desc ---
$GLOBALS['OPT'] = PGL_Settings::defaults(); $GLOBALS['OPT']['direction'] = 'desc';
$items_d = PGL_Shortcode::items();
check('desc order', [6, 1, 2] === array_column($items_d, 'id'));
$GLOBALS['OPT'] = null;

// --- filters(): only cats with usable published items, custom order ---
$terms = PGL_Shortcode::filters($items);
check('filter slugs custom', ['lab', 'research', 'field'] === array_map(function ($t) { return $t->slug; }, $terms));
$GLOBALS['OPT'] = PGL_Settings::defaults(); $GLOBALS['OPT']['filter_order'] = 'alpha';
$terms_a = PGL_Shortcode::filters($items);
check('filter slugs alpha', ['field', 'lab', 'research'] === array_map(function ($t) { return $t->slug; }, $terms_a));
$GLOBALS['OPT'] = null;

// --- render ---
$_GET = [];
$html1 = PGL_Shortcode::render([]);
$html2 = PGL_Shortcode::render([]);
check('instance1', false !== strpos($html1, 'data-pgl-instance="1"'));
check('instance2', false !== strpos($html2, 'data-pgl-instance="2"'));
check('inst params isolated', false !== strpos($html1, 'pgl1_f') && false === strpos($html1, 'pgl2_f'));
check('no-JS filter link', false !== strpos($html1, 'pgl1_f=lab'));
check('figure links to file', false !== strpos($html1, 'http://example.test/full101.jpg'));
check('elementor lightbox opt-out', 3 === substr_count($html1, 'data-elementor-open-lightbox="no"'));
check('grid imgs lazy', 3 === substr_count($html1, 'loading="lazy"'));
check('cols vars', false !== strpos($html1, '--pgl-cd:3'));
check('payload all published', 3 === count(json_decode(payload_of($html1, 'pgl-data'), true)));
check('payload no internal meta', false === strpos(payload_of($html1, 'pgl-data'), '_pgl_'));
check('cats map embed', false !== strpos(payload_of($html1, 'pgl-cats'), 'Field Work'));
$_GET = ['pgl3_f' => 'lab'];
$html3 = PGL_Shortcode::render([]);
check('lab filter active', false !== strpos($html3, 'data-cat="lab"') && 1 === substr_count($html3, 'pgl-item'));
check('bogus filter falls back', (function () {
  $_GET = ['pgl4_f' => 'nope'];
  $h = PGL_Shortcode::render([]);
  return 3 === substr_count($h, 'pgl-item');
})());
check('empty state', (function () {
  $keep = $GLOBALS['ITEMS'];
  $GLOBALS['ITEMS'] = [];
  $_GET = [];
  $h = PGL_Shortcode::render([]);
  $GLOBALS['ITEMS'] = $keep;
  return false !== strpos($h, 'No images in this category yet.');
})());
check('captions off hides figcaption', (function () {
  $GLOBALS['OPT'] = PGL_Settings::defaults(); $GLOBALS['OPT']['show_captions'] = 0;
  $_GET = [];
  $h = PGL_Shortcode::render([]);
  $GLOBALS['OPT'] = null;
  return false === strpos($h, '<figcaption>');
})());

// --- admin menu ---
$GLOBALS['submenu'][PGL_MENU] = [
  ['Gallery', 'edit_posts', PGL_MENU],
  ['All Gallery Items', 'edit_posts', 'edit.php?post_type=' . PGL_ITEM],
  ['Add New Gallery Item', 'edit_posts', 'post-new.php?post_type=' . PGL_ITEM],
  ['Categories', 'edit_posts', 'edit-tags.php?taxonomy=' . PGL_CAT . '&post_type=' . PGL_ITEM],
  ['Import', 'manage_options', 'pgl-import'],
  ['Settings', 'manage_options', 'pgl-settings'],
];
global $submenu;
$submenu = &$GLOBALS['submenu'];
PGL_Admin::reorder_menu();
$slugs = array_column($submenu[PGL_MENU], 2);
check('no duplicate parent', !in_array(PGL_MENU, $slugs, true));
check('submenu order', 0 === strpos($slugs[2], 'edit-tags.php?taxonomy=' . PGL_CAT));

echo $fail ? "RESULT: $fail FAILURES\n" : "RESULT: ALL PASS\n";
exit($fail ? 1 : 0);
