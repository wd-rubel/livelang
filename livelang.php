<?php
/**
 * Plugin Name: LiveLang - Smart Visual Translator
 * Description: Inline visual translator for WordPress. Click → edit → translate → save. Page/slug based + global translations.
 * Version:     1.0.3
 * Author: LiveLang Team
 * Author URI: https://livelang.pro/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: livelang
 * Domain Path: /languages
 */
/* Set Freemius into development mode */
if ( ! function_exists( 'liv_fs' ) ) {
    // Create a helper function for easy SDK access.
    function liv_fs() {
        global $liv_fs;

        if ( ! isset( $liv_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            $liv_fs = fs_dynamic_init( array(
                'id'                  => '22482',
                'slug'                => 'livelang',
                'premium_slug'        => 'livelang-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_c35f7a2cd995c48badcf794eb6991',
                'secret_key'          => 'sk_k<z!:qG2MB2<n4pVIM$6Xo=nGB?BX',
                'is_premium'          => false,
                'is_premium_only'     => false,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'trial'               => array(
                    'days'               => 7,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'livelang',
                    'support'        => false,
                    'affiliation' => false,
                    'network'     => true,
                ),
                'parallel_activation' => array(
                    'enabled' => true,
                    'premium_version_basename' => 'livelang-pro/livelang-pro.php',
                ),
            ) );
        }

        return $liv_fs;
    }

    // Init Freemius.
    liv_fs();
    // Signal that SDK was initiated.
    do_action( 'liv_fs_loaded' );
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LIVELANG_VERSION', '1.0.3' );
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