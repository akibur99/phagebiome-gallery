<?php
if (!defined('ABSPATH')) exit;

/**
 * Gallery item CPT + gallery-category taxonomy, dedicated capabilities.
 * Taxonomy terms carry _pgl_term_order for the custom filter order.
 */
final class PGL_CPTs {
  const SING = 'pgl_item';
  const PLUR = 'pgl_items';

  public static function register() {
    add_action('init', [__CLASS__, 'types'], 5);
    add_action('admin_menu', [__CLASS__, 'parent_menu'], 9);
    add_action('admin_init', [__CLASS__, 'ensure_caps']);
  }

  public static function parent_menu() {
    add_menu_page(
      __('Gallery', 'phagebiome-gallery'),
      __('Gallery', 'phagebiome-gallery'),
      'edit_posts',
      PGL_MENU,
      null,
      'dashicons-format-gallery',
      27
    );
  }

  private static function caps_for($sing, $plur) {
    return [
      'edit_post' => "edit_{$sing}", 'read_post' => "read_{$sing}", 'delete_post' => "delete_{$sing}",
      'edit_posts' => "edit_{$plur}", 'edit_others_posts' => "edit_others_{$plur}",
      'create_posts' => "edit_{$plur}",
      'publish_posts' => "publish_{$plur}", 'read_private_posts' => "read_private_{$plur}",
      'delete_posts' => "delete_{$plur}", 'delete_others_posts' => "delete_others_{$plur}",
      'delete_private_posts' => "delete_private_{$plur}", 'delete_published_posts' => "delete_published_{$plur}",
      'edit_private_posts' => "edit_private_{$plur}", 'edit_published_posts' => "edit_published_{$plur}",
    ];
  }

  public static function grant_caps() {
    $role = get_role('administrator');
    if (!$role) return;
    foreach (self::caps_for(self::SING, self::PLUR) as $cap) $role->add_cap($cap);
    foreach (['manage_pgl_cats', 'edit_pgl_cats', 'delete_pgl_cats', 'assign_pgl_cats'] as $cap) $role->add_cap($cap);
  }

  /** Updates don't re-run the activator: self-heal missing caps for admins. */
  public static function ensure_caps() {
    if (!current_user_can('manage_options')) return;
    $o = get_post_type_object(PGL_ITEM);
    if (!$o) return;
    if (!current_user_can($o->cap->create_posts) || !current_user_can('manage_pgl_cats')) {
      self::grant_caps();
    }
  }

  public static function types() {
    register_post_type(PGL_ITEM, [
      'labels' => [
        'name' => __('Gallery Items', 'phagebiome-gallery'),
        'singular_name' => __('Gallery Item', 'phagebiome-gallery'),
        'add_new' => __('Add New Gallery Item', 'phagebiome-gallery'),
        'add_new_item' => __('Add New Gallery Item', 'phagebiome-gallery'),
        'edit_item' => __('Edit Gallery Item', 'phagebiome-gallery'),
        'all_items' => __('All Gallery Items', 'phagebiome-gallery'),
        'menu_name' => __('All Gallery Items', 'phagebiome-gallery'),
        'search_items' => __('Search Gallery Items', 'phagebiome-gallery'),
        'not_found' => __('No gallery items found.', 'phagebiome-gallery'),
        'not_found_in_trash' => __('No gallery items found in Trash.', 'phagebiome-gallery'),
      ],
      'public' => false,
      'publicly_queryable' => false,
      'exclude_from_search' => true,
      'show_ui' => true,
      'show_in_menu' => PGL_MENU,
      'show_in_nav_menus' => false,
      'show_in_admin_bar' => false,
      'show_in_rest' => false,
      'has_archive' => false,
      'rewrite' => false,
      'query_var' => false,
      'map_meta_cap' => true,
      'hierarchical' => false,
      'supports' => ['title'],
      'delete_with_user' => false,
      'menu_name' => __('All Gallery Items', 'phagebiome-gallery'),
      'capability_type' => [self::SING, self::PLUR],
      'capabilities' => self::caps_for(self::SING, self::PLUR),
    ]);
    register_taxonomy(PGL_CAT, [PGL_ITEM], [
      'labels' => [
        'name' => __('Categories', 'phagebiome-gallery'),
        'singular_name' => __('Category', 'phagebiome-gallery'),
        'search_items' => __('Search Categories', 'phagebiome-gallery'),
        'all_items' => __('All Categories', 'phagebiome-gallery'),
        'edit_item' => __('Edit Category', 'phagebiome-gallery'),
        'add_new_item' => __('Add New Category', 'phagebiome-gallery'),
        'new_item_name' => __('New Category Name', 'phagebiome-gallery'),
        'menu_name' => __('Categories', 'phagebiome-gallery'),
      ],
      'public' => false,
      'publicly_queryable' => false,
      'show_ui' => true,
      'show_in_menu' => PGL_MENU,
      'show_in_nav_menus' => false,
      'show_in_rest' => false,
      'show_admin_column' => true,
      'hierarchical' => true,
      'query_var' => false,
      'rewrite' => false,
      'capabilities' => [
        'manage_terms' => 'manage_pgl_cats',
        'edit_terms' => 'edit_pgl_cats',
        'delete_terms' => 'delete_pgl_cats',
        'assign_terms' => 'assign_pgl_cats',
      ],
    ]);
  }
}
