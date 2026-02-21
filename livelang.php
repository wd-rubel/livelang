<?php
/**
 * Plugin Name: LiveLang - Smart Visual Translator
 * Description: Inline visual translator for WordPress. Click → edit → translate → save. Page/slug based + global translations.
 * Version:     1.0.2
 * Author: LiveLang Team
 * Author URI: https://livelang.pro/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: livelang
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LIVELANG_VERSION', '1.0.2' );
define( 'LIVELANG_PLUGIN_FILE', __FILE__ );
define( 'LIVELANG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIVELANG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LIVELANG_PLUGIN_BASE', plugin_basename( LIVELANG_PLUGIN_FILE ) );

require_once LIVELANG_PLUGIN_DIR . 'includes/class-livelang-db.php';
require_once LIVELANG_PLUGIN_DIR . 'includes/class-livelang-admin.php';
require_once LIVELANG_PLUGIN_DIR . 'includes/class-livelang-frontend.php';
require_once LIVELANG_PLUGIN_DIR . 'includes/class-livelang-admin-menu.php';

class LiveLang_Plugin {

    private static $instance = null;

    /** @var LiveLang_DB */
    public $db;

    /** @var LiveLang_Admin */
    public $admin;

    /** @var LiveLang_Frontend */
    public $frontend;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db       = new LiveLang_DB();
        $this->admin    = new LiveLang_Admin( $this->db );
        $this->frontend = new LiveLang_Frontend( $this->db );

        register_activation_hook( LIVELANG_PLUGIN_FILE, array( $this, 'activate' ) );
    }

    public function activate() {
        $this->db->create_table();

        if ( ! get_option( 'livelang_settings' ) ) {
            update_option(
                'livelang_settings',
                array(
                    'enabled'       => 1,
                    'allowed_roles' => array( 'administrator' ),
                )
            );
        }
        
        // Flush rewrite rules to register new rules
        flush_rewrite_rules( false );
    }
}

function livelang() {
    return LiveLang_Plugin::instance();
}
livelang();