<?php
if (!defined('ABSPATH')) exit;

/**
 * Automatic display order with atomic allocation (single gallery sequence).
 * - _pgl_order_manual  user override ('' = automatic)
 * - _pgl_order_auto    system number, kept when manually overridden
 * - _pgl_first_published timestamp of first publish
 * - _pgl_order         EFFECTIVE mirror (manual if set, else auto)
 */
final class PGL_Order {
  const SEQ = 'pgl_seq';

  public static function register() {
    add_action('transition_post_status', [__CLASS__, 'on_transition'], 10, 3);
  }

  public static function ensure_sequence() {
    if (false === get_option(self::SEQ, false)) add_option(self::SEQ, 0, '', false);
  }

  public static function next_seq() {
    global $wpdb;
    self::ensure_sequence();
    $r = $wpdb->query($wpdb->prepare(
      "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s",
      self::SEQ
    ));
    if (false === $r) return 0;
    $n = (int) $wpdb->get_var('SELECT LAST_INSERT_ID()');
    return $n > 0 ? $n : 0;
  }

  public static function auto_of($post_id) {
    $v = get_post_meta($post_id, '_pgl_order_auto', true);
    return '' === $v ? '' : (int) $v;
  }

  public static function manual_of($post_id) {
    $v = get_post_meta($post_id, '_pgl_order_manual', true);
    return '' === $v ? '' : (int) $v;
  }

  public static function effective_of($post_id) {
    $manual = get_post_meta($post_id, '_pgl_order_manual', true);
    if ('' !== $manual) return (int) $manual;
    $auto = get_post_meta($post_id, '_pgl_order_auto', true);
    if ('' !== $auto) return (int) $auto;
    $legacy = get_post_meta($post_id, '_pgl_order', true);
    if ('' !== $legacy && is_numeric($legacy)) return (int) $legacy;
    return 999999;
  }

  public static function refresh_mirror($post_id) {
    update_post_meta($post_id, '_pgl_order', self::effective_of($post_id));
  }

  public static function maybe_assign($post_id) {
    if ('' !== get_post_meta($post_id, '_pgl_order_auto', true)) {
      return (int) get_post_meta($post_id, '_pgl_order_auto', true);
    }
    if (PGL_ITEM !== get_post_type($post_id)) return 0;
    $n = self::next_seq();
    if ($n <= 0) return 0;
    update_post_meta($post_id, '_pgl_order_auto', $n);
    if (!get_post_meta($post_id, '_pgl_first_published', true)) {
      update_post_meta($post_id, '_pgl_first_published', time());
    }
    self::refresh_mirror($post_id);
    return $n;
  }

  public static function on_transition($new, $old, $post) {
    if (PGL_ITEM !== $post->post_type) return;
    if ('publish' !== $new) return;
    if (!(defined('DOING_CRON') && DOING_CRON) && !current_user_can('edit_post', $post->ID)) return;
    self::maybe_assign($post->ID);
  }
}
