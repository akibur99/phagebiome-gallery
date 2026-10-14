<?php
if (!defined('ABSPATH')) exit;

/** [phagebiome_gallery] — server-rendered filters/grid, JS-enhanced, no-JS fallback. */
final class PGL_Shortcode {
  private static $instances = 0;
  private static $rendered = false;

  public static function register() {
    add_shortcode('phagebiome_gallery', [__CLASS__, 'render']);
    add_action('init', [__CLASS__, 'register_assets']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue']);
    add_action('wp_footer', [__CLASS__, 'footer_fallback'], 20);
  }

  public static function register_assets() {
    wp_register_style('pgl-frontend', PGL_URL . 'assets/css/frontend.css', [], PGL_VERSION);
    wp_register_script('pgl-frontend', PGL_URL . 'assets/js/frontend.js', [], PGL_VERSION, true);
  }

  public static function maybe_enqueue() {
    if (self::detect()) self::enqueue();
  }

  public static function footer_fallback() {
    if (self::$rendered && !wp_style_is('pgl-frontend', 'enqueued')) wp_print_styles('pgl-frontend');
    if (self::$rendered && !wp_script_is('pgl-frontend', 'enqueued')) wp_print_scripts('pgl-frontend');
  }

  private static function detect() {
    if (is_admin()) return false;
    if (isset($_GET['elementor-preview'])) return true;
    if (!is_singular()) return false;
    $post = get_queried_object();
    if (!$post instanceof WP_Post) return false;
    if (has_shortcode($post->post_content, 'phagebiome_gallery')) return true;
    $edata = get_post_meta($post->ID, '_elementor_data', true);
    if (is_string($edata) && false !== stripos($edata, 'phagebiome_gallery')) return true;
    return false;
  }

  private static function enqueue() {
    wp_enqueue_style('pgl-frontend');
    wp_enqueue_script('pgl-frontend');
  }

  /** Published items with usable attachments, ordered by settings. */
  public static function items() {
    $opt = PGL_Settings::get();
    $posts = get_posts([
      'post_type' => PGL_ITEM, 'post_status' => 'publish', 'numberposts' => -1,
      'no_found_rows' => true, 'update_post_term_cache' => true,
    ]);
    $rows = [];
    foreach ($posts as $p) {
      $att = absint(get_post_meta($p->ID, '_pgl_attachment_id', true));
      if (!PGL_Meta::attachment_usable($att)) continue; // never expose broken/missing
      $terms = get_the_terms($p->ID, PGL_CAT);
      $cats = [];
      if (is_array($terms)) foreach ($terms as $t) $cats[] = $t->slug;
      $rows[] = [
        'id' => $p->ID,
        'title' => $p->post_title,
        'alt' => PGL_Meta::alt_for($p->ID, $att),
        'caption' => (string) get_post_meta($p->ID, '_pgl_caption', true),
        'cats' => $cats,
        'att' => $att,
        'order' => PGL_Order::effective_of($p->ID),
      ];
    }
    $desc = ('desc' === $opt['direction']);
    usort($rows, function ($a, $b) use ($desc) {
      if ($a['order'] !== $b['order']) return $desc ? ($b['order'] - $a['order']) : ($a['order'] - $b['order']);
      return $desc ? ($b['id'] - $a['id']) : ($a['id'] - $b['id']);
    });
    return $rows;
  }

  /** Categories containing published usable items, ordered per settings. */
  public static function filters($rows) {
    $opt = PGL_Settings::get();
    $have = [];
    foreach ($rows as $r) foreach ($r['cats'] as $s) $have[$s] = true;
    if (!$have) return [];
    $terms = get_terms(['taxonomy' => PGL_CAT, 'hide_empty' => false, 'slug' => array_keys($have)]);
    if (is_wp_error($terms) || !is_array($terms)) return [];
    if ('alpha' === $opt['filter_order']) {
      usort($terms, function ($a, $b) { return strcasecmp($a->name, $b->name); });
    } else {
      usort($terms, function ($a, $b) {
        $ao = get_term_meta($a->term_id, '_pgl_term_order', true);
        $bo = get_term_meta($b->term_id, '_pgl_term_order', true);
        $ao = ('' === $ao) ? 999999 : (int) $ao;
        $bo = ('' === $bo) ? 999999 : (int) $bo;
        if ($ao !== $bo) return $ao - $bo;
        return strcasecmp($a->name, $b->name);
      });
    }
    return $terms;
  }

  public static function render($atts) {
    self::$instances++;
    self::$rendered = true;
    self::enqueue();
    $n = self::$instances;
    $opt = PGL_Settings::get();
    $key = 'pgl' . $n . '_f';
    $f = isset($_GET[$key]) ? sanitize_key(wp_unslash($_GET[$key])) : 'all';
    $rows = self::items();
    $terms = self::filters($rows);
    $slugs = array_map(function ($t) { return $t->slug; }, $terms);
    if ('all' !== $f && !in_array($f, $slugs, true)) $f = 'all';
    $visible = array_values(array_filter($rows, function ($r) use ($f) {
      return 'all' === $f || in_array($f, $r['cats'], true);
    }));
    $base = is_singular() ? get_permalink() : home_url('/');
    if (!$base) $base = home_url('/');
    $url_for = function ($fv) use ($n, $base) {
      $args = 'all' === $fv ? [] : ['pgl' . $n . '_f' => $fv];
      foreach ($_GET as $k => $v) {
        if (0 === strpos($k, 'pgl' . $n . '_') || 'pgl' . $n . '_f' === $k) continue;
        if (is_string($v)) $args[$k] = sanitize_text_field(wp_unslash($v));
      }
      return esc_url(add_query_arg($args, $base));
    };
    ob_start();
    ?>
    <div class="phagebiome-gallery" data-pgl-instance="<?php echo esc_attr($n); ?>"
      data-pgl-captions="<?php echo $opt['show_captions'] ? '1' : '0'; ?>"
      data-pgl-all="<?php echo esc_attr($opt['all_label']); ?>"
      data-pgl-empty="<?php echo esc_attr($opt['empty_message']); ?>">
      <div class="pgl-filters" role="group" aria-label="<?php esc_attr_e('Filter gallery', 'phagebiome-gallery'); ?>">
        <a href="<?php echo $url_for('all'); ?>" class="pgl-filter<?php echo 'all' === $f ? ' is-active' : ''; ?>" data-cat="all"<?php echo 'all' === $f ? ' aria-current="true"' : ''; ?>><?php echo esc_html($opt['all_label']); ?></a>
        <?php foreach ($terms as $t) : ?>
          <a href="<?php echo $url_for($t->slug); ?>" class="pgl-filter<?php echo $f === $t->slug ? ' is-active' : ''; ?>" data-cat="<?php echo esc_attr($t->slug); ?>"<?php echo $f === $t->slug ? ' aria-current="true"' : ''; ?>><?php echo esc_html($t->name); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="pgl-grid" style="--pgl-cd:<?php echo (int) $opt['cols_desktop']; ?>;--pgl-ct:<?php echo (int) $opt['cols_tablet']; ?>;--pgl-cm:<?php echo (int) $opt['cols_mobile']; ?>;">
        <?php foreach ($visible as $r) echo self::figure($r, $terms, (bool) $opt['show_captions']); ?>
        <?php if (!$visible) : ?><p class="pgl-empty"><?php echo esc_html($opt['empty_message']); ?></p><?php endif; ?>
      </div>
      <script type="application/json" class="pgl-data"><?php echo self::json_payload($rows); ?></script>
      <script type="application/json" class="pgl-cats"><?php echo self::json_cats($terms); ?></script>
    </div>
    <?php
    return ob_get_clean();
  }

  public static function cat_label($r, $terms) {
    foreach ($terms as $t) {
      if (in_array($t->slug, $r['cats'], true)) return $t->name;
    }
    return '';
  }

  public static function figure($r, $terms, $show_captions) {
    $full = wp_get_attachment_url($r['att']);
    if (!$full) return '';
    $img = wp_get_attachment_image($r['att'], 'medium_large', false, [
      'loading' => 'lazy', 'decoding' => 'async',
      'alt' => $r['alt'],
    ]);
    if (!$img) return '';
    $label = self::cat_label($r, $terms);
    $cap_bits = [];
    if ('' !== trim($r['caption']) && $show_captions) $cap_bits[] = esc_html($r['caption']);
    if ('' !== $label) $cap_bits[] = esc_html($label);
    ob_start();
    ?>
    <figure class="pgl-item" data-pgl-id="<?php echo esc_attr($r['id']); ?>" data-cats="<?php echo esc_attr(implode(' ', $r['cats'])); ?>">
      <a class="pgl-open" href="<?php echo esc_url($full); ?>" data-pgl-open="<?php echo esc_attr($r['id']); ?>" data-elementor-open-lightbox="no" aria-label="<?php printf(esc_attr__('Open image: %s', 'phagebiome-gallery'), esc_attr($r['title'])); ?>">
        <?php echo $img; ?>
      </a>
      <?php if ($show_captions) : ?>
        <figcaption><strong><?php echo esc_html($r['title']); ?></strong><?php echo $cap_bits ? implode(' · ', $cap_bits) : ''; ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php
    return ob_get_clean();
  }

  /** slug => name lookup for client-side caption labels. */
  public static function json_cats($terms) {
    $map = [];
    foreach ($terms as $t) $map[$t->slug] = $t->name;
    return wp_json_encode($map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
  }
  /** Public payload: published usable items only, no internal metadata. */
  public static function json_payload($rows) {
    $pub = [];
    foreach ($rows as $r) {
      $thumb = wp_get_attachment_image_src($r['att'], 'medium_large');
      $large = wp_get_attachment_image_src($r['att'], 'large');
      $full = wp_get_attachment_url($r['att']);
      if (!$thumb || !$full) continue;
      $pub[] = [
        'id' => $r['id'], 'title' => $r['title'], 'alt' => $r['alt'],
        'caption' => $r['caption'], 'cats' => array_values($r['cats']),
        'thumb' => $thumb[0], 'large' => $large ? $large[0] : $full, 'full' => $full,
      ];
    }
    return wp_json_encode($pub, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
  }
}
