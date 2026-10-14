<?php
if (!defined('ABSPATH')) exit;

/**
 * Reference gallery import (safe Media Library download) + removal.
 * Reference images are third-party stock placeholders, not verified lab
 * photos; admin UI states this explicitly.
 */
final class PGL_Importer {
  const IMPORT = 'pgl_import_reference';
  const REMOVE = 'pgl_remove_reference';
  const MAX_BYTES = 10485760; // 10 MB
  const TIMEOUT = 20;

  public static function register() {
    add_action('admin_menu', [__CLASS__, 'menu'], 20);
    add_action('admin_post_' . self::IMPORT, [__CLASS__, 'handle_import']);
    add_action('admin_post_' . self::REMOVE, [__CLASS__, 'handle_remove']);
  }

  public static function menu() {
    add_submenu_page(
      PGL_MENU,
      __('Import Reference Gallery', 'phagebiome-gallery'),
      __('Import', 'phagebiome-gallery'),
      'manage_options',
      'pgl-import',
      [__CLASS__, 'page']
    );
  }

  public static function categories() {
    return [
      'lab' => [__('Lab', 'phagebiome-gallery'), 1],
      'research' => [__('Research', 'phagebiome-gallery'), 2],
      'field' => [__('Field Work', 'phagebiome-gallery'), 3],
      'events' => [__('Events', 'phagebiome-gallery'), 4],
      'conferences' => [__('Conferences', 'phagebiome-gallery'), 5],
    ];
  }

  public static function load_data() {
    $file = PGL_DIR . 'data/reference.json';
    if (!is_readable($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
  }

  public static function page() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-gallery'));
    $uid = get_current_user_id();
    $ires = get_transient('pgl_import_result_' . $uid);
    if ($ires) delete_transient('pgl_import_result_' . $uid);
    $rres = get_transient('pgl_remove_result_' . $uid);
    if ($rres) delete_transient('pgl_remove_result_' . $uid);
    $eligible = self::eligible_for_removal();
    $trash_off = defined('EMPTY_TRASH_DAYS') && EMPTY_TRASH_DAYS == 0;
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Import Reference Gallery', 'phagebiome-gallery'); ?></h1>
      <?php if (is_array($ires) && isset($ires['fatal'])) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($ires['fatal']); ?></p></div>
      <?php elseif (is_array($ires)) : ?>
        <div class="notice notice-success is-dismissible"><p>
          <?php printf(esc_html__('Reference gallery: imported %1$d, skipped %2$d, drafts %3$d, failed %4$d.', 'phagebiome-gallery'), (int) $ires['imported'], (int) $ires['skipped'], (int) $ires['drafts'], (int) $ires['failed']); ?>
          <?php if (!empty($ires['missing'])) printf(' ' . esc_html__('Missing source images: %s.', 'phagebiome-gallery'), esc_html(implode(', ', $ires['missing']))); ?>
        </p></div>
      <?php endif; ?>
      <?php if (is_array($rres)) : ?>
        <div class="notice notice-success is-dismissible"><p>
          <?php printf(esc_html__('Reference gallery items: %1$d moved to Trash.', 'phagebiome-gallery'), (int) $rres['moved']); ?>
          <?php if ($rres['failed'] > 0) printf(' ' . esc_html__('%d could not be moved (permission or error).', 'phagebiome-gallery'), (int) $rres['failed']); ?>
        </p></div>
      <?php endif; ?>
      <p><?php esc_html_e('Imports the 9 reference gallery items (titles, captions, categories) and downloads their images into the Media Library.', 'phagebiome-gallery'); ?></p>
      <div class="notice notice-warning inline"><p><?php esc_html_e('Placeholder notice: the reference images are third-party stock photographs, not verified photos of lab events. Replace them with the lab’s originals after import.', 'phagebiome-gallery'); ?></p></div>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
        <?php wp_nonce_field('pgl_import', 'pgl_import_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(self::IMPORT); ?>" />
        <?php submit_button(__('Import Reference Gallery', 'phagebiome-gallery'), 'primary', '', false); ?>
      </form>
      <hr />
      <h2><?php esc_html_e('Remove All Imported Reference Gallery Items', 'phagebiome-gallery'); ?></h2>
      <p><?php printf(esc_html(_n('There is currently %d imported reference gallery item.', 'There are currently %d imported reference gallery items.', count($eligible), 'phagebiome-gallery')), (int) count($eligible)); ?></p>
      <p><?php esc_html_e('Moves only reference-imported items, including edited copies, to Trash. Manually created items, Media Library images and categories are preserved. Trashed items can be restored while they remain within the site’s Trash retention period.', 'phagebiome-gallery'); ?></p>
      <?php if ($trash_off) : ?>
        <div class="notice notice-error inline"><p><?php esc_html_e('Removal is blocked: WordPress Trash is disabled on this site (EMPTY_TRASH_DAYS is 0), so trashed records could not be restored. Enable Trash to use removal.', 'phagebiome-gallery'); ?></p></div>
      <?php else : ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
        <?php wp_nonce_field('pgl_remove', 'pgl_remove_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(self::REMOVE); ?>" />
        <?php submit_button(__('Remove All Imported Reference Gallery Items', 'phagebiome-gallery'), 'delete', '', false, count($eligible) ? null : ['disabled' => 'disabled']); ?>
      </form>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function handle_import() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-gallery'));
    check_admin_referer('pgl_import', 'pgl_import_nonce');
    $r = self::run_import();
    set_transient('pgl_import_result_' . get_current_user_id(), $r, 60);
    wp_safe_redirect(add_query_arg(['page' => 'pgl-import'], admin_url('admin.php')));
    exit;
  }

  public static function handle_remove() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'phagebiome-gallery'));
    check_admin_referer('pgl_remove', 'pgl_remove_nonce');
    if (defined('EMPTY_TRASH_DAYS') && EMPTY_TRASH_DAYS == 0) wp_die(esc_html__('Removal is blocked because WordPress Trash is disabled (EMPTY_TRASH_DAYS is 0).', 'phagebiome-gallery'));
    $r = self::run_removal();
    set_transient('pgl_remove_result_' . get_current_user_id(), $r, 60);
    wp_safe_redirect(add_query_arg(['page' => 'pgl-import'], admin_url('admin.php')));
    exit;
  }

  private static function ensure_category($slug) {
    $map = self::categories();
    if (!isset($map[$slug])) return 0;
    list($name, $order) = $map[$slug];
    $t = get_term_by('slug', $slug, PGL_CAT);
    if ($t && !is_wp_error($t)) {
      if ('' === get_term_meta($t->term_id, '_pgl_term_order', true)) update_term_meta($t->term_id, '_pgl_term_order', $order);
      return (int) $t->term_id;
    }
    $r = wp_insert_term($name, PGL_CAT, ['slug' => $slug]);
    if (is_wp_error($r) || empty($r['term_id'])) return 0;
    update_term_meta((int) $r['term_id'], '_pgl_term_order', $order);
    return (int) $r['term_id'];
  }

  /**
   * Download a remote image into the Media Library with validation.
   * Returns attachment ID or WP_Error. Uses wp_safe_remote_get semantics
   * (rejects local/private-network destinations), size + type checks.
   */
  public static function sideload_image($url, $key) {
    $parts = wp_parse_url($url);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
      return new WP_Error('pgl_bad_url', __('Unsupported image URL.', 'phagebiome-gallery'));
    }
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = download_url($url, self::TIMEOUT);
    if (is_wp_error($tmp)) return $tmp;
    if (filesize($tmp) > self::MAX_BYTES) {
      @unlink($tmp);
      return new WP_Error('pgl_too_big', __('Image exceeds the 10 MB limit.', 'phagebiome-gallery'));
    }
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$info['mime']])) {
      @unlink($tmp);
      return new WP_Error('pgl_bad_type', __('Downloaded file is not a supported image.', 'phagebiome-gallery'));
    }
    $file = ['name' => sanitize_file_name($key . '.' . $allowed[$info['mime']]), 'type' => $info['mime'], 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)];
    $att_id = media_handle_sideload($file, 0);
    if (is_wp_error($att_id)) {
      @unlink($tmp);
      return $att_id;
    }
    update_post_meta($att_id, '_pgl_source_hash', md5($url));
    return $att_id;
  }

  public static function find_attachment_by_hash($url) {
    $ids = get_posts([
      'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
      'meta_key' => '_pgl_source_hash', 'meta_value' => md5($url),
    ]);
    return $ids ? (int) $ids[0] : 0;
  }

  /** Import reference items. Never touches existing posts. */
  public static function run_import() {
    $data = self::load_data();
    if (null === $data) return ['fatal' => __('Reference data file is missing or unreadable.', 'phagebiome-gallery')];
    $result = ['imported' => 0, 'skipped' => 0, 'drafts' => 0, 'failed' => 0, 'missing' => []];
    foreach ($data as $row) {
      if (!is_array($row)) { $result['failed']++; continue; }
      $key = isset($row['key']) ? sanitize_key($row['key']) : '';
      $title = isset($row['title']) ? trim(sanitize_text_field($row['title'])) : '';
      if ('' === $key || '' === $title) { $result['failed']++; continue; }
      $exists = get_posts([
        'post_type' => PGL_ITEM, 'post_status' => ['publish', 'draft', 'pending', 'private', 'trash'],
        'meta_key' => '_pgl_import_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1,
      ]);
      if ($exists) { $result['skipped']++; continue; }
      $term_ids = [];
      foreach ((array) ($row['cats'] ?? []) as $slug) {
        $tid = self::ensure_category(sanitize_key($slug));
        if ($tid) $term_ids[] = $tid;
      }
      $att_id = self::find_attachment_by_hash($row['src'] ?? '');
      if (!$att_id && !empty($row['src'])) {
        $dl = self::sideload_image($row['src'], $key);
        if (is_wp_error($dl)) {
          $result['missing'][] = $title;
        } else {
          $att_id = (int) $dl;
        }
      }
      $meta = [
        '_pgl_import_key' => $key,
        '_pgl_import_placeholder' => '1',
        '_pgl_order_manual' => '',
        '_pgl_alt' => '',
        '_pgl_caption' => isset($row['desc']) ? sanitize_textarea_field($row['desc']) : '',
      ];
      $complete = $att_id > 0 && PGL_Meta::attachment_usable($att_id);
      if ($att_id > 0) $meta['_pgl_attachment_id'] = (string) $att_id;
      $pid = wp_insert_post([
        'post_type' => PGL_ITEM, 'post_title' => $title,
        'post_status' => $complete ? 'publish' : 'draft',
        'meta_input' => $meta, 'tax_input' => [PGL_CAT => $term_ids],
      ], true);
      if (is_wp_error($pid) || !$pid) { $result['failed']++; continue; }
      if ($complete && 'publish' === get_post_status($pid)) $result['imported']++;
      else $result['drafts']++;
    }
    return $result;
  }

  public static function eligible_for_removal() {
    $ids = get_posts([
      'post_type' => PGL_ITEM,
      'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
      'numberposts' => -1, 'fields' => 'ids',
      'meta_query' => [['key' => '_pgl_import_key', 'value' => '', 'compare' => '!=']],
      'orderby' => 'ID', 'order' => 'ASC',
    ]);
    return array_map('intval', (array) $ids);
  }

  public static function run_removal() {
    $result = ['moved' => 0, 'failed' => 0];
    foreach (self::eligible_for_removal() as $pid) {
      if (!current_user_can('delete_post', $pid)) { $result['failed']++; continue; }
      $r = wp_trash_post($pid);
      if ($r) $result['moved']++;
      else $result['failed']++;
    }
    return $result;
  }
}
