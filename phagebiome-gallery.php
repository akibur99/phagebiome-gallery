<?php
/**
 * Plugin Name: Phagebiome Gallery
 * Description: Admin-managed gallery with category filters, responsive grid and accessible lightbox. Use the [phagebiome_gallery] shortcode.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: InsoSpark Agency
 * Author URI: https://insospark.com
 * Plugin URI: https://insospark.com
 * License: GPL v2 or later
 * Text Domain: phagebiome-gallery
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('PGL_VERSION', '1.0.1');
define('PGL_DIR', plugin_dir_path(__FILE__));
define('PGL_URL', plugin_dir_url(__FILE__));
define('PGL_ITEM', 'pgl_item');
define('PGL_CAT', 'pgl_category');
define('PGL_OPTION', 'pgl_settings');
define('PGL_MENU', 'pgl-gallery');

require_once PGL_DIR . 'includes/class-cpts.php';
require_once PGL_DIR . 'includes/class-meta.php';
require_once PGL_DIR . 'includes/class-order.php';
require_once PGL_DIR . 'includes/class-admin.php';
require_once PGL_DIR . 'includes/class-importer.php';
require_once PGL_DIR . 'includes/class-settings.php';
require_once PGL_DIR . 'includes/class-shortcode.php';

add_action('plugins_loaded', function () {
  load_plugin_textdomain('phagebiome-gallery', false, dirname(plugin_basename(__FILE__)) . '/languages');
  PGL_CPTs::register();
  PGL_Meta::register();
  PGL_Order::register();
  PGL_Admin::register();
  PGL_Importer::register();
  PGL_Settings::register();
  PGL_Shortcode::register();
});

register_activation_hook(__FILE__, function () {
  PGL_CPTs::register();
  PGL_CPTs::types();
  PGL_CPTs::grant_caps();
  PGL_Order::ensure_sequence();
  flush_rewrite_rules(false);
  if (false === get_option(PGL_OPTION)) {
    add_option(PGL_OPTION, PGL_Settings::defaults(), '', false);
  }
});

register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules(false);
});
