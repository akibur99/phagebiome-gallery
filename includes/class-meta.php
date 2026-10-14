<?php
if (!defined('ABSPATH')) exit;

/**
 * Gallery item details: Media Library image (attachment ID), alt override,
 * caption, display order. Publishing requires a valid image attachment;
 * drafts may be incomplete. Values are always preserved.
 */
final class PGL_Meta {
  const NONCE = 'pgl_meta_nonce';
  const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  public static function register() {
    add_action('add_meta_boxes_' . PGL_ITEM, [__CLASS__, 'boxes']);
    add_filter('enter_title_here', [__CLASS__, 'title_placeholder'], 10, 2);
    add_filter('wp_insert_post_data', [__CLASS__, 'enforce'], 10, 2);
    add_action('save_post_' . PGL_ITEM, [__CLASS__, 'save'], 10, 2);
    add_action('admin_notices', [__CLASS__, 'notices']);
  }

  public static function boxes($post) {
    add_meta_box('pgl-image', __('Gallery Image', 'phagebiome-gallery'), [__CLASS__, 'image_box'], PGL_ITEM, 'normal', 'high');
    add_meta_box('pgl-details', __('Image Details', 'phagebiome-gallery'), [__CLASS__, 'details_box'], PGL_ITEM, 'normal', 'default');
  }

  public static function title_placeholder($text, $post) {
    if ($post instanceof WP_Post && PGL_ITEM === $post->post_type) return __('Enter image title', 'phagebiome-gallery');
    return $text;
  }

  /** A usable grid/lightbox image: existing image attachment, allowed type. */
  public static function attachment_usable($att_id) {
    $att_id = absint($att_id);
    if (!$att_id) return false;
    $p = get_post($att_id);
    if (!$p || 'attachment' !== $p->post_type) return false;
    $mime = get_post_mime_type($att_id);
    if (!in_array($mime, self::ALLOWED_MIMES, true)) return false;
    $file = get_attached_file($att_id);
    if (!$file || !is_file($file)) return false;
    return true;
  }

  public static function alt_for($post_id, $att_id) {
    $over = trim((string) get_post_meta($post_id, '_pgl_alt', true));
    if ('' !== $over) return $over;
    $lib = trim((string) get_post_meta($att_id, '_wp_attachment_image_alt', true));
    if ('' !== $lib) return $lib;
    $t = get_post($post_id);
    return $t ? $t->post_title : '';
  }

  public static function image_box($post) {
    wp_nonce_field(self::NONCE, self::NONCE);
    $att_id = absint(get_post_meta($post->ID, '_pgl_attachment_id', true));
    $usable = self::attachment_usable($att_id);
    $thumb = $usable ? wp_get_attachment_image($att_id, 'medium', false, ['class' => 'pgl-preview-img']) : '';
    $fname = $usable ? basename((string) get_attached_file($att_id)) : '';
    ?>
    <div class="pgl-image-picker" data-has="<?php echo $usable ? '1' : '0'; ?>">
      <div class="pgl-preview"><?php echo $thumb ? $thumb : '<span class="pgl-no-image">' . esc_html__('No image selected.', 'phagebiome-gallery') . '</span>'; ?></div>
      <p class="pgl-filename description"><?php echo $fname ? esc_html($fname) : ''; ?></p>
      <p>
        <button type="button" class="button button-primary pgl-select"><?php echo $usable ? esc_html__('Replace Image', 'phagebiome-gallery') : esc_html__('Select Image', 'phagebiome-gallery'); ?></button>
        <?php if ($usable) : ?>
          <button type="button" class="button pgl-remove"><?php esc_html_e('Remove Image', 'phagebiome-gallery'); ?></button>
        <?php endif; ?>
      </p>
      <input type="hidden" name="pgl_attachment_id" id="pgl_attachment_id" value="<?php echo $usable ? esc_attr((string) $att_id) : ''; ?>" />
      <p class="description"><?php esc_html_e('Choose an image from the Media Library. JPEG, PNG, GIF or WebP. Required before publishing.', 'phagebiome-gallery'); ?></p>
    </div>
    <?php
  }

  public static function details_box($post) {
    $alt = get_post_meta($post->ID, '_pgl_alt', true);
    $caption = get_post_meta($post->ID, '_pgl_caption', true);
    $manual = get_post_meta($post->ID, '_pgl_order_manual', true);
    $auto = get_post_meta($post->ID, '_pgl_order_auto', true);
    $is_new = 'auto-draft' === $post->post_status;
    ?>
    <div class="pgl-fields">
      <p>
        <label for="pgl_alt"><?php esc_html_e('Alternative Text', 'phagebiome-gallery'); ?></label><br />
        <input name="pgl_alt" id="pgl_alt" type="text" class="large-text" placeholder="<?php esc_attr_e('Describe what is visible in the image', 'phagebiome-gallery'); ?>" value="<?php echo esc_attr($alt); ?>" />
      </p>
      <p class="description"><?php esc_html_e('Screen-reader text for this gallery image. When empty, the Media Library alt text is used. The shared library value is never overwritten.', 'phagebiome-gallery'); ?></p>
      <p>
        <label for="pgl_caption"><?php esc_html_e('Caption', 'phagebiome-gallery'); ?></label><br />
        <textarea name="pgl_caption" id="pgl_caption" rows="2" cols="50" class="large-text" placeholder="<?php esc_attr_e('Enter an optional caption', 'phagebiome-gallery'); ?>"><?php echo esc_textarea($caption); ?></textarea>
      </p>
      <div class="pgl-field-order">
        <span class="pgl-order-status" role="status">
          <?php if ($is_new || ('' === $manual && '' === $auto)) : ?>
            <?php esc_html_e('Assigned when published', 'phagebiome-gallery'); ?>
          <?php elseif ('' !== $manual) : ?>
            <?php printf(esc_html__('Manual ordering active: %d', 'phagebiome-gallery'), (int) $manual); ?>
          <?php else : ?>
            <?php printf(esc_html__('Automatic order: %d', 'phagebiome-gallery'), (int) $auto); ?>
          <?php endif; ?>
        </span><br />
        <label for="pgl_order_manual"><?php esc_html_e('Display Order', 'phagebiome-gallery'); ?></label><br />
        <input name="pgl_order_manual" id="pgl_order_manual" type="number" min="0" step="1" placeholder="<?php esc_attr_e('Automatic', 'phagebiome-gallery'); ?>" value="<?php echo ('' === $manual) ? '' : esc_attr((int) $manual); ?>" class="small-text" />
        <p class="description"><?php esc_html_e('Assigned automatically when first published. Enter a number to override the automatic order.', 'phagebiome-gallery'); ?></p>
        <?php if ('' !== $manual) : ?>
          <p><button type="submit" name="pgl_reset_auto" value="1" class="button button-secondary"><?php esc_html_e('Reset to automatic', 'phagebiome-gallery'); ?></button></p>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  private static function posted($key) {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : null;
  }

  public static function problems_for($post_id, $postarr, $title) {
    $problems = [];
    if ('' === trim((string) $title)) $problems[] = __('an image title', 'phagebiome-gallery');
    $raw = self::posted('pgl_attachment_id');
    if (null !== $raw) $att = $raw;
    elseif (isset($postarr['meta_input']['_pgl_attachment_id'])) $att = (string) $postarr['meta_input']['_pgl_attachment_id'];
    else $att = $post_id ? (string) get_post_meta($post_id, '_pgl_attachment_id', true) : '';
    if (!self::attachment_usable($att)) {
      $problems[] = __('a valid supported image attachment (JPEG, PNG, GIF or WebP)', 'phagebiome-gallery');
    }
    return $problems;
  }

  public static function enforce($data, $postarr) {
    if (PGL_ITEM !== ($data['post_type'] ?? '')) {
      if (PGL_ITEM !== get_post_type($postarr['ID'] ?? 0)) return $data;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $data;
    $post_id = (int) ($postarr['ID'] ?? 0);
    if (!in_array($data['post_status'] ?? '', ['publish', 'future', 'private'], true)) return $data;
    $can = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
    if (!$can) return $data;
    $problems = self::problems_for($post_id, $postarr, $postarr['post_title'] ?? ($post_id ? get_the_title($post_id) : ''));
    if ($problems) {
      $data['post_status'] = 'draft';
      $msg = sprintf(__('Not published — please provide %s. Your entries were kept as a draft.', 'phagebiome-gallery'), implode(', ', $problems));
      if ($post_id) update_post_meta($post_id, '_pgl_admin_error', $msg);
      else set_transient('pgl_pending_err_' . get_current_user_id(), $msg, 300);
    }
    return $data;
  }

  public static function save($post_id, $post) {
    if (PGL_ITEM !== $post->post_type) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    $key = 'pgl_pending_err_' . get_current_user_id();
    $msg = get_transient($key);
    if ($msg) {
      delete_transient($key);
      update_post_meta($post_id, '_pgl_admin_error', $msg);
    }
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $raw_att = self::posted('pgl_attachment_id');
    if (null !== $raw_att) {
      $att = absint($raw_att);
      if ($att > 0 && !self::attachment_usable($att)) {
        update_post_meta($post_id, '_pgl_admin_error', __('The selected image is not a usable JPEG, PNG, GIF or WebP file. Previous image kept.', 'phagebiome-gallery'));
      } else {
        update_post_meta($post_id, '_pgl_attachment_id', $att ? (string) $att : '');
      }
    }
    $raw_alt = self::posted('pgl_alt');
    if (null !== $raw_alt) update_post_meta($post_id, '_pgl_alt', sanitize_text_field($raw_alt));
    $raw_cap = self::posted('pgl_caption');
    if (null !== $raw_cap) update_post_meta($post_id, '_pgl_caption', sanitize_textarea_field($raw_cap));
    if (isset($_POST['pgl_reset_auto'])) {
      update_post_meta($post_id, '_pgl_order_manual', '');
    } elseif (array_key_exists('pgl_order_manual', $_POST) && is_string($_POST['pgl_order_manual'])) {
      $m = trim(wp_unslash($_POST['pgl_order_manual']));
      update_post_meta($post_id, '_pgl_order_manual', ('' === $m) ? '' : absint($m));
    }
    PGL_Order::refresh_mirror($post_id);
  }

  public static function notices() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || PGL_ITEM !== $screen->post_type || 'post' !== $screen->base) return;
    global $post;
    if (!$post) return;
    $msg = get_post_meta($post->ID, '_pgl_admin_error', true);
    if ($msg) {
      delete_post_meta($post->ID, '_pgl_admin_error');
      printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($msg));
    }
  }
}
