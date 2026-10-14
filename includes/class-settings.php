<?php
if (!defined('ABSPATH')) exit;

/** Display settings: order, filter order, captions, labels, columns. */
final class PGL_Settings {
  public static function register() {
    add_action('admin_menu', [__CLASS__, 'menu'], 20);
    add_action('admin_init', [__CLASS__, 'settings']);
  }

  public static function menu() {
    add_submenu_page(
      PGL_MENU,
      __('Gallery Display Settings', 'phagebiome-gallery'),
      __('Settings', 'phagebiome-gallery'),
      'manage_options',
      'pgl-settings',
      [__CLASS__, 'page']
    );
  }

  public static function defaults() {
    return [
      'direction' => 'asc',
      'filter_order' => 'custom',
      'show_captions' => 1,
      'all_label' => __('All', 'phagebiome-gallery'),
      'empty_message' => __('No images in this category yet.', 'phagebiome-gallery'),
      'cols_desktop' => 3, 'cols_tablet' => 2, 'cols_mobile' => 1,
    ];
  }

  public static function get() {
    return wp_parse_args((array) get_option(PGL_OPTION, []), self::defaults());
  }

  public static function settings() {
    register_setting('pgl_settings_group', PGL_OPTION, ['type' => 'array', 'sanitize_callback' => [__CLASS__, 'sanitize']]);
    add_settings_section('pgl_main', __('Gallery display', 'phagebiome-gallery'), '__return_false', 'pgl-settings');
    add_settings_field('direction', __('Default item order', 'phagebiome-gallery'), function () {
      $o = self::get();
      printf('<select name="%1$s[direction]"><option value="asc"%2$s>%3$s</option><option value="desc"%4$s>%5$s</option></select>',
        esc_attr(PGL_OPTION), selected($o['direction'], 'asc', false), esc_html__('Ascending', 'phagebiome-gallery'),
        selected($o['direction'], 'desc', false), esc_html__('Descending', 'phagebiome-gallery'));
      echo '<p class="description">' . esc_html__('By effective Display Order, then record ID.', 'phagebiome-gallery') . '</p>';
    }, 'pgl-settings', 'pgl_main');
    add_settings_field('filter_order', __('Category-filter order', 'phagebiome-gallery'), function () {
      $o = self::get();
      printf('<select name="%1$s[filter_order]"><option value="custom"%2$s>%3$s</option><option value="alpha"%4$s>%5$s</option></select>',
        esc_attr(PGL_OPTION), selected($o['filter_order'], 'custom', false), esc_html__('Custom (category Display order)', 'phagebiome-gallery'),
        selected($o['filter_order'], 'alpha', false), esc_html__('Alphabetical', 'phagebiome-gallery'));
    }, 'pgl-settings', 'pgl_main');
    add_settings_field('show_captions', __('Captions', 'phagebiome-gallery'), function () {
      $o = self::get();
      printf('<label><input type="checkbox" name="%1$s[show_captions]" value="1"%2$s /> %3$s</label>',
        esc_attr(PGL_OPTION), checked($o['show_captions'], 1, false), esc_html__('Show captions on gallery images', 'phagebiome-gallery'));
    }, 'pgl-settings', 'pgl_main');
    foreach (['cols_desktop' => __('Desktop columns', 'phagebiome-gallery'), 'cols_tablet' => __('Tablet columns', 'phagebiome-gallery'), 'cols_mobile' => __('Mobile columns', 'phagebiome-gallery')] as $k => $label) {
      add_settings_field($k, $label, function () use ($k) {
        $o = self::get();
        printf('<input type="number" name="%1$s[%2$s]" value="%3$d" min="1" max="6" step="1" class="small-text" />', esc_attr(PGL_OPTION), esc_attr($k), (int) $o[$k]);
      }, 'pgl-settings', 'pgl_main');
    }
    add_settings_section('pgl_text', __('Gallery interface text', 'phagebiome-gallery'), '__return_false', 'pgl-settings');
    foreach (['all_label' => __('“All” filter label', 'phagebiome-gallery'), 'empty_message' => __('Empty-results message', 'phagebiome-gallery')] as $k => $label) {
      add_settings_field($k, $label, function () use ($k) {
        $o = self::get();
        printf('<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" />', esc_attr(PGL_OPTION), esc_attr($k), esc_attr($o[$k]));
      }, 'pgl-settings', 'pgl_text');
    }
  }

  public static function sanitize($in) {
    $in = is_array($in) ? $in : [];
    $o = self::defaults();
    $o['direction'] = (isset($in['direction']) && 'desc' === $in['direction']) ? 'desc' : 'asc';
    $o['filter_order'] = (isset($in['filter_order']) && 'alpha' === $in['filter_order']) ? 'alpha' : 'custom';
    $o['show_captions'] = !empty($in['show_captions']) ? 1 : 0;
    foreach (['all_label', 'empty_message'] as $k) {
      if (isset($in[$k])) $o[$k] = sanitize_text_field($in[$k]);
    }
    if ('' === trim($o['all_label'])) $o['all_label'] = self::defaults()['all_label'];
    if ('' === trim($o['empty_message'])) $o['empty_message'] = self::defaults()['empty_message'];
    foreach (['cols_desktop', 'cols_tablet', 'cols_mobile'] as $k) {
      $o[$k] = isset($in[$k]) ? max(1, min(6, absint($in[$k]))) : $o[$k];
    }
    return $o;
  }

  public static function page() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-gallery'));
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Gallery Display Settings', 'phagebiome-gallery'); ?></h1>
      <form method="post" action="options.php">
        <?php settings_fields('pgl_settings_group'); do_settings_sections('pgl-settings'); submit_button(); ?>
      </form>
      <h2><?php esc_html_e('Shortcode', 'phagebiome-gallery'); ?></h2>
      <p><?php esc_html_e('Place this shortcode in any page, post, Shortcode block, or Elementor Shortcode widget:', 'phagebiome-gallery'); ?></p>
      <p><code>[phagebiome_gallery]</code></p>
    </div>
    <?php
  }
}
