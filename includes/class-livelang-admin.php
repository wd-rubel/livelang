<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LiveLang_Admin {

    /** @var LiveLang_DB */
    protected $db;

    public function __construct( $db ) {
        $this->db = $db;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        add_action( 'wp_ajax_livelang_clear_translations', array( $this, 'ajax_clear_translations' ) );
        add_action( 'admin_post_livelang_delete', array( $this, 'handle_delete' ) );
        add_action( 'admin_post_livelang_toggle_status', array( $this, 'handle_toggle_status' ) );
        add_action( 'wp_ajax_livelang_add_language', array( $this, 'ajax_add_language' ) );
        add_action( 'wp_ajax_livelang_delete_language', array( $this, 'ajax_delete_language' ) );
        add_action( 'wp_ajax_livelang_update_language', array( $this, 'ajax_update_language' ) );
        add_action( 'wp_ajax_livelang_reorder_languages', array( $this, 'ajax_reorder_languages' ) );
        add_action( 'wp_ajax_livelang_set_default_language', array( $this, 'ajax_set_default_language' ) );
        add_action( 'wp_ajax_livelang_get_languages', array( $this, 'ajax_get_languages' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'LiveLang', 'livelang' ),
            __( 'LiveLang', 'livelang' ),
            'manage_options',
            'livelang',
            array( $this, 'render_translations_page' ),
            'dashicons-translation',
            58
        );

        add_submenu_page(
            'livelang',
            __( 'Translations', 'livelang' ),
            __( 'Translations', 'livelang' ),
            'manage_options',
            'livelang',
            array( $this, 'render_translations_page' )
        );

        add_submenu_page(
            'livelang',
            __( 'Settings', 'livelang' ),
            __( 'Settings', 'livelang' ),
            'manage_options',
            'livelang-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'livelang_settings_group', 'livelang_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'livelang_main',
            __( 'LiveLang Settings', 'livelang' ),
            '__return_false',
            'livelang-settings'
        );

        add_settings_field(
            'livelang_enabled',
            __( 'Enable LiveLang', 'livelang' ),
            array( $this, 'field_enabled' ),
            'livelang-settings',
            'livelang_main'
        );

        add_settings_field(
            'livelang_roles',
            __( 'Allowed Roles', 'livelang' ),
            array( $this, 'field_roles' ),
            'livelang-settings',
            'livelang_main'
        );

        add_settings_section(
            'livelang_filters',
            __( 'Translation Filters', 'livelang' ),
            '__return_false',
            'livelang-settings'
        );

        add_settings_field(
            'livelang_translate_numbers',
            __( 'Translate Text with Numbers', 'livelang' ),
            array( $this, 'field_translate_numbers' ),
            'livelang-settings',
            'livelang_filters'
        );
    }

    public function sanitize_settings( $input ) {
        $output = array();

        $output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

        $output['allowed_roles'] = array();
        if ( ! empty( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
            foreach ( $input['allowed_roles'] as $role ) {
                $output['allowed_roles'][] = sanitize_text_field( $role );
            }
        }

        $output['translate_numbers'] = ! empty( $input['translate_numbers'] ) ? 1 : 0;

        return $output;
    }

    public function field_enabled() {
        $options = get_option( 'livelang_settings', array() );
        $enabled = ! empty( $options['enabled'] );
        ?>
        <label>
            <input type="checkbox" name="livelang_settings[enabled]" value="1" <?php checked( $enabled ); ?>>
            <?php esc_html_e( 'Enable plugin functionality', 'livelang' ); ?>
        </label>
        <?php
    }

    public function field_roles() {
        $options = get_option( 'livelang_settings', array() );
        $allowed = isset( $options['allowed_roles'] ) ? (array) $options['allowed_roles'] : array();

        global $wp_roles;
        $roles = $wp_roles->roles;
        ?>
        <fieldset>
            <!-- all -->
            <label>
                <input type="checkbox" name="livelang_settings[allowed_roles][]" value="all"
                    <?php checked( in_array( 'all', $allowed, true ) ); ?>>
                <?php esc_html_e( 'All Roles', 'livelang' ); ?>
            </label>
            <br><br>
            <?php foreach ( $roles as $role_key => $role ) : ?>
                <label>
                    <input type="checkbox" name="livelang_settings[allowed_roles][]" value="<?php echo esc_attr( $role_key ); ?>"
                        <?php checked( in_array( $role_key, $allowed, true ) ); ?>>
                    <?php echo esc_html( $role['name'] ); ?>
                </label><br>
            <?php endforeach; ?>
            <p class="description">
                <?php esc_html_e( 'Only selected roles will see the frontend translation mode toggle.', 'livelang' ); ?>
            </p>
        </fieldset>
        <?php
    }

    public function field_translate_numbers() {
        $options = get_option( 'livelang_settings', array() );
        $translate_numbers = ! empty( $options['translate_numbers'] ) ? 1 : 0;
        ?>
        <label>
            <input type="checkbox" name="livelang_settings[translate_numbers]" value="1" <?php checked( $translate_numbers ); ?>>
            <?php esc_html_e( 'Enable translation of text containing numbers', 'livelang' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled, text containing numbers (e.g., "10 English Books") will skip the number part (translate only "English Books").', 'livelang' ); ?>
        </p>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap livelang-settings-wrap">
            <h1><?php esc_html_e( 'LiveLang Settings', 'livelang' ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-livelang-tab="general">
                    <?php esc_html_e( 'General', 'livelang' ); ?>
                </a>
                <a href="#" class="nav-tab nav-tab-active" data-livelang-tab="languages">
                    <?php esc_html_e( 'Languages', 'livelang' ); ?>
                </a>
                <a href="#" class="nav-tab nav-tab-active" data-livelang-tab="maintenance">
                    <?php esc_html_e( 'Maintenance', 'livelang' ); ?>
                </a>
                <a href="#" class="nav-tab" data-livelang-tab="usage">
                    <?php esc_html_e( 'Usage', 'livelang' ); ?>
                </a>
                <a href="#" class="nav-tab" data-livelang-tab="help">
                    <?php esc_html_e( 'Help Center', 'livelang' ); ?>
                </a>
            </h2>

            <div id="livelang-tab-general" class="livelang-tab-panel is-active">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'livelang_settings_group' );
                    do_settings_sections( 'livelang-settings' );
                    submit_button();
                    ?>
                </form>
            </div>
            
            <div id="livelang-tab-languages" class="livelang-tab-panel">
                <h2><?php esc_html_e( 'Manage Languages', 'livelang' ); ?></h2>
                <p><?php esc_html_e( 'Add, edit, delete, and reorder languages. Drag to reorder.', 'livelang' ); ?></p>
                
                <!-- Upgrade notice is shown on-demand via JS when user attempts to add more than 3 languages -->
                
                <div class="livelang-languages-actions" style="margin-bottom: 20px;">
                    <select id="livelang-language-code" style="width: 200px; padding: 8px;">
                        <option value=""><?php esc_html_e( 'Select Language', 'livelang' ); ?></option>
                        <?php
                        $available_langs = array(
                            'af' => 'Afrikaans',
                            'sq' => 'Albanian',
                            'am' => 'Amharic',
                            'ar' => 'Arabic',
                            'hy' => 'Armenian',
                            'az' => 'Azerbaijani',
                            'eu' => 'Basque',
                            'be' => 'Belarusian',
                            'bn' => 'Bengali',
                            'bs' => 'Bosnian',
                            'bg' => 'Bulgarian',
                            'ca' => 'Catalan',
                            'zh' => 'Chinese',
                            'hr' => 'Croatian',
                            'cs' => 'Czech',
                            'da' => 'Danish',
                            'nl' => 'Dutch',
                            'en' => 'English',
                            'et' => 'Estonian',
                            'fi' => 'Finnish',
                            'fr' => 'French',
                            'gl' => 'Galician',
                            'ka' => 'Georgian',
                            'de' => 'German',
                            'el' => 'Greek',
                            'gu' => 'Gujarati',
                            'he' => 'Hebrew',
                            'hi' => 'Hindi',
                            'hu' => 'Hungarian',
                            'is' => 'Icelandic',
                            'id' => 'Indonesian',
                            'it' => 'Italian',
                            'ja' => 'Japanese',
                            'kn' => 'Kannada',
                            'kk' => 'Kazakh',
                            'km' => 'Khmer',
                            'ko' => 'Korean',
                            'lo' => 'Lao',
                            'lv' => 'Latvian',
                            'lt' => 'Lithuanian',
                            'mk' => 'Macedonian',
                            'ms' => 'Malay',
                            'ml' => 'Malayalam',
                            'mt' => 'Maltese',
                            'mr' => 'Marathi',
                            'mn' => 'Mongolian',
                            'ne' => 'Nepali',
                            'nb' => 'Norwegian Bokmål',
                            'nn' => 'Norwegian Nynorsk',
                            'fa' => 'Persian',
                            'pl' => 'Polish',
                            'pt' => 'Portuguese',
                            'pa' => 'Punjabi',
                            'ro' => 'Romanian',
                            'ru' => 'Russian',
                            'sr' => 'Serbian',
                            'si' => 'Sinhala',
                            'sk' => 'Slovak',
                            'sl' => 'Slovenian',
                            'es' => 'Spanish',
                            'sw' => 'Swahili',
                            'sv' => 'Swedish',
                            'ta' => 'Tamil',
                            'te' => 'Telugu',
                            'th' => 'Thai',
                            'tr' => 'Turkish',
                            'uk' => 'Ukrainian',
                            'ur' => 'Urdu',
                            'uz' => 'Uzbek',
                            'vi' => 'Vietnamese',
                            'cy' => 'Welsh',
                        );
                        foreach ( $available_langs as $code => $name ) {
                            echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name . ' (' . $code . ')' ) . '</option>';
                        }
                        ?>
                    </select>
                    <input type="text" id="livelang-language-label" placeholder="<?php esc_attr_e( 'Language Label (e.g., Spanish)', 'livelang' ); ?>" style="width: 200px; padding: 8px; margin-left: 10px;">
                    <button class="button button-primary" id="livelang-add-language-btn" style="margin-left: 10px;">
                        <?php esc_html_e( 'Add Language', 'livelang' ); ?>
                    </button>
                </div>

                <table class="widefat striped" id="livelang-languages-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">📋</th>
                            <th><?php esc_html_e( 'Code', 'livelang' ); ?></th>
                            <th><?php esc_html_e( 'Label', 'livelang' ); ?></th>
                            <th><?php esc_html_e( 'Default', 'livelang' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'livelang' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="livelang-languages-tbody">
                        <!-- Languages will be loaded via JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div id="livelang-tab-maintenance" class="livelang-tab-panel">
                <h2><?php esc_html_e( 'Maintenance Actions', 'livelang' ); ?></h2>
                <p><?php esc_html_e( 'You can clear all stored translations. This action cannot be undone.', 'livelang' ); ?></p>
                <button class="button button-secondary" id="livelang-clear-translations-maintenance">
                    <?php esc_html_e( 'Clear All Translations', 'livelang' ); ?>
                </button>
            </div>

            <div id="livelang-tab-usage" class="livelang-tab-panel">
                <h2><?php esc_html_e( 'Language Switcher Shortcode', 'livelang' ); ?></h2>
                <p><?php esc_html_e( 'Use the following shortcode to display a language switcher dropdown anywhere on your site:', 'livelang' ); ?></p>
                
                <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0;">
                    <code style="font-size: 16px; font-weight: bold;">[livelang_language_switcher]</code>
                </div>

                <h3><?php esc_html_e( 'How to Use', 'livelang' ); ?></h3>
                <ul style="line-height: 1.8;">
                    <li><strong><?php esc_html_e( 'In Header, Footer, Posts or Pages:', 'livelang' ); ?></strong> <?php esc_html_e( 'Add a Shortcode block and paste the shortcode above.', 'livelang' ); ?></li>
                    <li><strong><?php esc_html_e( 'In Widgets:', 'livelang' ); ?></strong> <?php esc_html_e( 'Add a Shortcode widget and paste the shortcode.', 'livelang' ); ?></li>
                    <li><strong><?php esc_html_e( 'In Theme Files:', 'livelang' ); ?></strong> <?php esc_html_e( 'Use', 'livelang' ); ?> <code>&lt;?php echo do_shortcode('[livelang_language_switcher]'); ?&gt;</code></li>
                </ul>

                <h3><?php esc_html_e( 'What It Does', 'livelang' ); ?></h3>
                <p><?php esc_html_e( 'The language switcher displays a dropdown menu with all your configured languages. When a user selects a language, the page content will be translated to that language based on your saved translations.', 'livelang' ); ?></p>
                
                <p class="description">
                    <?php esc_html_e( 'Note: Make sure you have configured your languages in the Languages tab and created translations using the frontend editor.', 'livelang' ); ?>
                </p>
            </div>

            <div id="livelang-tab-help" class="livelang-tab-panel">
                <h2><?php esc_html_e( 'How to use LiveLang', 'livelang' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Make sure the plugin is enabled in the General tab.', 'livelang' ); ?></li>
                    <li><?php esc_html_e( 'Assign which user roles can use the frontend editor.', 'livelang' ); ?></li>
                    <li><?php esc_html_e( 'Visit any page on the frontend while logged in with an allowed role.', 'livelang' ); ?></li>
                    <li><?php esc_html_e( 'Click the "LiveLang" floating button to enable translation mode.', 'livelang' ); ?></li>
                    <li><?php esc_html_e( 'Click any visible text to open the inline editor, then save your translation.', 'livelang' ); ?></li>
                    <li><?php esc_html_e( 'Manage all stored translations from the Translations screen.', 'livelang' ); ?></li>
                </ol>
                <p class="description">
                    <?php esc_html_e( 'This is an MVP version. For production, you may want to integrate with your deployment and backup workflow.', 'livelang' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    public function render_translations_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $translations = $this->db->get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LiveLang Translations', 'livelang' ); ?></h1>

            <p class="description">
                <?php esc_html_e( 'List of saved translations. Use inline editor on frontend to add/update items.', 'livelang' ); ?>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Original', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Translated', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Language', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Scope', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'livelang' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'livelang' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $translations ) ) : ?>
                    <?php foreach ( $translations as $row ) : 
                        $date = get_date_from_gmt( $row->created_at, 'Y-m-d' );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $row->id ); ?></td>
                            <td style="max-width:240px;"><?php echo esc_html( wp_trim_words( $row->original_text, 12 ) ); ?></td>
                            <td style="max-width:240px;"><?php echo esc_html( wp_trim_words( $row->translated_text, 12 ) ); ?></td>
                            <td><?php echo esc_html( $row->slug ); ?></td>
                            <td><?php echo esc_html( $row->language ); ?></td>
                            <td><?php echo $row->is_global ? esc_html__( 'Global', 'livelang' ) : esc_html__( 'Per Page', 'livelang' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
                            <td><?php echo esc_html( $date ); ?></td>
                            <td>
                                <?php
                                $toggle_url = wp_nonce_url(
                                    admin_url( 'admin-post.php?action=livelang_toggle_status&id=' . intval( $row->id ) ),
                                    'livelang_toggle_' . $row->id
                                );
                                $delete_url = wp_nonce_url(
                                    admin_url( 'admin-post.php?action=livelang_delete&id=' . intval( $row->id ) ),
                                    'livelang_delete_' . $row->id
                                );
                                ?>
                                <a href="<?php echo esc_url( $toggle_url ); ?>">
                                    <?php echo ( $row->status === 'active' ) ? esc_html__( 'Deactivate', 'livelang' ) : esc_html__( 'Activate', 'livelang' ); ?>
                                </a> |
                                <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this translation?', 'livelang' ) ); ?>')">
                                    <?php esc_html_e( 'Delete', 'livelang' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="9"><?php esc_html_e( 'No translations found.', 'livelang' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function ajax_clear_translations() {
        check_ajax_referer( 'livelang_clear' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $this->db->clear_all();
        wp_send_json_success();
    }

    public function handle_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed', 'livelang' ) );
        }
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'livelang_delete_' . $id );
        if ( $id ) {
            $this->db->delete( $id );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=livelang' ) );
        exit;
    }

    public function handle_toggle_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed', 'livelang' ) );
        }
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'livelang_toggle_' . $id );

        if ( $id ) {
            global $wpdb;
            $table = $this->db->table;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
            if ( $row ) {
                $new_status = ( $row->status === 'active' ) ? 'inactive' : 'active';
                $this->db->update( $id, array( 'status' => $new_status ) );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=livelang' ) );
        exit;
    }

    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'livelang_page_livelang-settings' ) !== false ) {
            wp_enqueue_script(
                'livelang-admin',
                LIVELANG_PLUGIN_URL . 'assets/js/livelang-admin.js',
                array( 'jquery', 'jquery-ui-sortable' ),
                LIVELANG_VERSION,
                true
            );
            wp_enqueue_style(
                'livelang-admin',
                LIVELANG_PLUGIN_URL . 'assets/css/livelang-admin.css',
                array(),
                LIVELANG_VERSION
            );
        }

        // localize script if needed
        wp_localize_script(
            'livelang-admin',
            'LiveLangAdminSettings',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'livelang_clear' ),
                'langNonce' => wp_create_nonce( 'livelang_languages' ),
            )
        );
    }

    /**
     * Get all languages
     */
    public function get_languages() {
        $languages = get_option( 'livelang_languages', array() );
        if ( empty( $languages ) ) {
            // Default languages
            $languages = array(
                array(
                    'code'      => 'en',
                    'label'     => 'English',
                    'is_default' => 1,
                    'order'     => 0,
                ),
                array(
                    'code'      => 'es',
                    'label'     => 'Spanish',
                    'is_default' => 0,
                    'order'     => 1,
                ),
            );
            update_option( 'livelang_languages', $languages );
        }
        return $languages;
    }

    /**
     * AJAX: Get all languages
     */
    public function ajax_get_languages() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $languages = $this->get_languages();
        wp_send_json_success( $languages );
    }

    /**
     * AJAX: Add language
     */
    public function ajax_add_language() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

        if ( empty( $code ) || empty( $label ) ) {
            wp_send_json_error( array( 'message' => 'Code and label are required' ) );
        }

        // Validate code format (2-5 lowercase letters)
        if ( ! preg_match( '/^[a-z]{2,5}$/', $code ) ) {
            wp_send_json_error( array( 'message' => 'Invalid code format' ) );
        }

        $languages = $this->get_languages();

        // Check if code already exists
        foreach ( $languages as $lang ) {
            if ( $lang['code'] === $code ) {
                wp_send_json_error( array( 'message' => 'Language code already exists' ) );
            }
        }

        // Check 3-language limit
        if ( count( $languages ) >= 3 ) {
            wp_send_json_error( array( 'message' => 'Maximum 3 languages allowed. Upgrade to Pro for unlimited languages.' ) );
        }

        $new_language = array(
            'code'       => $code,
            'label'      => $label,
            'is_default' => 0,
            'order'      => count( $languages ),
        );

        $languages[] = $new_language;
        update_option( 'livelang_languages', $languages );

        wp_send_json_success( array( 'language' => $new_language ) );
    }

    /**
     * AJAX: Delete language
     */
    public function ajax_delete_language() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => 'Code is required' ) );
        }

        $languages = $this->get_languages();
        $updated = array();

        foreach ( $languages as $lang ) {
            if ( $lang['code'] !== $code ) {
                $updated[] = $lang;
            }
        }

        if ( count( $updated ) === count( $languages ) ) {
            wp_send_json_error( array( 'message' => 'Language not found' ) );
        }

        update_option( 'livelang_languages', $updated );
        wp_send_json_success();
    }

    /**
     * AJAX: Update language
     */
    public function ajax_update_language() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

        if ( empty( $code ) || empty( $label ) ) {
            wp_send_json_error( array( 'message' => 'Code and label are required' ) );
        }

        $languages = $this->get_languages();
        $found = false;

        foreach ( $languages as &$lang ) {
            if ( $lang['code'] === $code ) {
                $lang['label'] = $label;
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            wp_send_json_error( array( 'message' => 'Language not found' ) );
        }

        update_option( 'livelang_languages', $languages );
        wp_send_json_success();
    }

    /**
     * AJAX: Reorder languages
     */
    public function ajax_reorder_languages() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => 'Order is required' ) );
        }

        $languages = $this->get_languages();
        $updated = array();

        foreach ( $order as $index => $code ) {
            $code = sanitize_text_field( $code );
            foreach ( $languages as $lang ) {
                if ( $lang['code'] === $code ) {
                    $lang['order'] = $index;
                    $updated[] = $lang;
                    break;
                }
            }
        }

        usort( $updated, function ( $a, $b ) {
            return $a['order'] - $b['order'];
        });

        update_option( 'livelang_languages', $updated );
        wp_send_json_success();
    }

    /**
     * AJAX: Set default language
     */
    public function ajax_set_default_language() {
        check_ajax_referer( 'livelang_languages' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Not allowed' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => 'Code is required' ) );
        }

        $languages = $this->get_languages();
        $found = false;

        foreach ( $languages as &$lang ) {
            if ( $lang['code'] === $code ) {
                $lang['is_default'] = 1;
                $found = true;
            } else {
                $lang['is_default'] = 0;
            }
        }

        if ( ! $found ) {
            wp_send_json_error( array( 'message' => 'Language not found' ) );
        }

        update_option( 'livelang_languages', $languages );
        wp_send_json_success();
    }
}
