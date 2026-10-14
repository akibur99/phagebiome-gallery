<?php
if (!defined('ABSPATH')) exit;

/** Admin list columns, menu order, category order field, scoped assets. */
final class PGL_Admin {
  public static function register() {
    add_filter('manage_' . PGL_ITEM . '_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_' . PGL_ITEM . '_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    add_filter('manage_edit-' . PGL_ITEM . '_sortable_columns', [__CLASS__, 'sortable']);
    add_action('pre_get_posts', [__CLASS__, 'sort_query']);
    add_action('admin_menu', [__CLASS__, 'reorder_menu'], 999);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    add_action(PGL_CAT . '_add_form_fields', [__CLASS__, 'term_add_field']);
    add_action(PGL_CAT . '_edit_form_fields', [__CLASS__, 'term_edit_field'], 10, 2);
    add_action('created_' . PGL_CAT, [__CLASS__, 'term_save']);
    add_action('edited_' . PGL_CAT, [__CLASS__, 'term_save']);
    add_filter('display_post_states', [__CLASS__, 'post_states'], 10, 2);
  }

  /** Flag reference stock placeholders in the items list. */
  public static function post_states($states, $post) {
    if ($post instanceof WP_Post && PGL_ITEM === $post->post_type && get_post_meta($post->ID, '_pgl_import_placeholder', true)) {
      $states['pgl_placeholder'] = __('Stock placeholder', 'phagebiome-gallery');
    }
    return $states;
  }

  public static function columns($cols) {
    return [
      'cb' => $cols['cb'] ?? '',
      'pgl_thumb' => __('Image', 'phagebiome-gallery'),
      'title' => __('Title', 'phagebiome-gallery'),
      'taxonomy-' . PGL_CAT => __('Categories', 'phagebiome-gallery'),
      'pgl_order' => __('Display order', 'phagebiome-gallery'),
      'date' => $cols['date'] ?? '',
    ];
  }

  public static function column_content($col, $post_id) {
    if ('pgl_thumb' === $col) {
      $att = absint(get_post_meta($post_id, '_pgl_attachment_id', true));
      if ($att && wp_attachment_is_image($att)) {
        echo wp_get_attachment_image($att, [64, 64], true, ['style' => 'width:64px;height:64px;object-fit:cover;border-radius:4px;']);
      } else {
        echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__('No image', 'phagebiome-gallery') . '</span>';
      }
    } elseif ('pgl_order' === $col) {
      $manual = get_post_meta($post_id, '_pgl_order_manual', true);
      printf('%s <span style="color:#646970">(%s)</span>', esc_html((string) PGL_Order::effective_of($post_id)), '' !== $manual ? esc_html__('manual', 'phagebiome-gallery') : esc_html__('auto', 'phagebiome-gallery'));
    }
  }

  public static function sortable($cols) {
    $cols['pgl_order'] = 'pgl_order';
    return $cols;
  }

  public static function sort_query($q) {
    if (!is_admin() || !$q->is_main_query()) return;
    if (PGL_ITEM !== $q->get('post_type')) return;
    if ('pgl_order' === $q->get('orderby')) {
      $q->set('meta_key', '_pgl_order');
      $q->set('orderby', 'meta_value_num');
    }
  }

  /** Order: All Gallery Items, Add New Gallery Item, Categories, Import, Settings. */
  public static function reorder_menu() {
    global $submenu;
    if (empty($submenu[PGL_MENU])) return;
    $by_slug = [];
    foreach ($submenu[PGL_MENU] as $item) {
      $slug = $item[2] ?? '';
      if ('' === $slug || isset($by_slug[$slug])) continue;
      $by_slug[$slug] = $item;
    }
    unset($by_slug[PGL_MENU]);
    $want = [
      'edit.php?post_type=' . PGL_ITEM,
      'post-new.php?post_type=' . PGL_ITEM,
      'edit-tags.php?taxonomy=' . PGL_CAT . '&amp;post_type=' . PGL_ITEM,
      'edit-tags.php?taxonomy=' . PGL_CAT . '&post_type=' . PGL_ITEM,
      'pgl-import',
      'pgl-settings',
    ];
    $ordered = [];
    foreach ($want as $slug) {
      if (isset($by_slug[$slug])) { $ordered[] = $by_slug[$slug]; unset($by_slug[$slug]); }
    }
    foreach ($by_slug as $item) $ordered[] = $item;
    $submenu[PGL_MENU] = array_values($ordered);
  }

  public static function term_add_field() {
    if (!current_user_can('manage_pgl_cats')) return;
    ?>
    <div class="form-field">
      <label for="pgl_term_order"><?php esc_html_e('Display order', 'phagebiome-gallery'); ?></label>
      <input type="number" name="pgl_term_order" id="pgl_term_order" value="" min="0" step="1" />
      <p><?php esc_html_e('Order of this category in the frontend filter buttons when “Custom” order is selected.', 'phagebiome-gallery'); ?></p>
    </div>
    <?php
  }

  public static function term_edit_field($term) {
    if (!current_user_can('edit_pgl_cats')) return;
    $v = get_term_meta($term->term_id, '_pgl_term_order', true);
    ?>
    <tr class="form-field">
      <th scope="row"><label for="pgl_term_order"><?php esc_html_e('Display order', 'phagebiome-gallery'); ?></label></th>
      <td><input type="number" name="pgl_term_order" id="pgl_term_order" value="<?php echo ('' === $v) ? '' : esc_attr((int) $v); ?>" min="0" step="1" />
      <p class="description"><?php esc_html_e('Order of this category in the frontend filter buttons when “Custom” order is selected.', 'phagebiome-gallery'); ?></p></td>
    </tr>
    <?php
  }

  public static function term_save($term_id) {
    if (!current_user_can('edit_pgl_cats')) return;
    if (isset($_POST['pgl_term_order']) && is_string($_POST['pgl_term_order'])) {
      check_admin_referer('update-tag_' . $term_id);
      $v = trim(wp_unslash($_POST['pgl_term_order']));
      if ('' === $v) delete_term_meta($term_id, '_pgl_term_order');
      else update_term_meta($term_id, '_pgl_term_order', absint($v));
    }
  }

  public static function assets($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;
    $on_item = PGL_ITEM === $screen->post_type;
    $on_page = in_array($screen->id, ['gallery_page_pgl-import', 'gallery_page_pgl-settings'], true);
    if ($on_item || $on_page) {
      wp_enqueue_style('pgl-admin', PGL_URL . 'assets/css/admin.css', [], PGL_VERSION);
    }
    if ($on_item && 'post' === $screen->base) {
      wp_enqueue_media();
      wp_enqueue_script('pgl-admin-media', PGL_URL . 'assets/js/admin-media.js', ['jquery'], PGL_VERSION, true);
    }
  }
}
