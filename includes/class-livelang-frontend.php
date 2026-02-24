<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LiveLang_Frontend {

    /** @var LiveLang_DB */
    protected $db;
    protected $buffering = false;

    public function __construct( $db ) {
        $this->db = $db;

       
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'livelang_language_switcher', array( $this, 'livelang_language_switcher' ) );
       

        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_filter( 'request', array( $this, 'handle_language_request' ) );
        add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect_for_lang' ), 10, 2 );
        add_action( 'init', array( $this, 'add_rewrite_tag' ) );
        add_action( 'init', array( $this, 'add_permastruct' ) );
        add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );

        // Filters to persist language in URLs
        add_filter( 'home_url', array( $this, 'filter_url' ), 10, 1 );
        add_filter( 'page_link', array( $this, 'filter_url' ), 10, 1 );
        add_filter( 'post_link', array( $this, 'filter_url' ), 10, 1 );
        add_filter( 'post_type_link', array( $this, 'filter_url' ), 10, 1 );
        add_filter( 'term_link', array( $this, 'filter_url' ), 10, 1 );
        add_filter( 'wp_nav_menu_objects', array( $this, 'filter_menu_urls' ), 10, 1 );
        add_filter( 'nav_menu_link_attributes', array( $this, 'nav_menu_link_attributes' ), 10, 4 );
        add_filter( 'wp_get_nav_menu_items', array( $this, 'expand_language_menu' ), 20 );
        add_filter( 'walker_nav_menu_start_el', array( $this, 'livelang_menu_item_output' ), 15, 4 );
        add_action( 'wp_footer', array( $this, 'render_editor_bar' ) );
    }

    function add_rewrite_tag() {
        add_rewrite_tag( '%lang%', '([a-z]{2})' );
    }

    function add_permastruct() {
        // Add custom rewrite rules for language/slug pattern
        
        // Rule 1: Language + any content (pages, posts, custom post types)
        // This will match /en/cart/, /es/my-post/, etc.
        add_rewrite_rule(
            '^([a-z]{2})/([^/]+)/?$',
            'index.php?lang=$matches[1]&pagename=$matches[2]',
            'top'
        );
        
        // Rule 2: Language + nested pages (e.g., /en/parent/child/)
        add_rewrite_rule(
            '^([a-z]{2})/(.+?)/?$',
            'index.php?lang=$matches[1]&pagename=$matches[2]',
            'top'
        );
        
        // Rule 3: Language only (homepage)
        add_rewrite_rule(
            '^([a-z]{2})/?$',
            'index.php?lang=$matches[1]',
            'top'
        );

        add_permastruct(
            'livelang',
            '%lang%/%postname%',
            array(
                'with_front' => false,
                'ep_mask'    => EP_PERMALINK | EP_PAGES,
            )
        );

    }

    function add_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    function handle_language_request( $query_vars ) {
        // Handle Lang-only request (Homepage for a language)
        // If we have lang but no page/post identifiers, it's the language root
        if ( isset( $query_vars['lang'] ) && ! isset( $query_vars['pagename'] ) && ! isset( $query_vars['name'] ) && ! isset( $query_vars['p'] ) && ! isset( $query_vars['page_id'] ) ) {
            if ( 'page' === get_option( 'show_on_front' ) ) {
                $page_id = get_option( 'page_on_front' );
                if ( $page_id ) {
                    $query_vars['page_id'] = $page_id;
                }
            }
        }

        // If we have a language and pagename, check if it's actually a post or other content type
        if ( isset( $query_vars['lang'] ) && isset( $query_vars['pagename'] ) ) {
            $slug = $query_vars['pagename'];
            
            // First, check if it's a page
            $page = get_page_by_path( $slug, OBJECT, 'page' );
            
            if ( $page ) {
                // It's a page - keep pagename
                // No changes needed, WordPress will handle it
                return $query_vars;
            }
            
            // Not a page, check if it's a post
            $post = get_page_by_path( $slug, OBJECT, 'post' );
            
            if ( $post ) {
                // It's a post, not a page - update query vars
                unset( $query_vars['pagename'] );
                $query_vars['name'] = $slug;
                $query_vars['post_type'] = 'post';
                return $query_vars;
            }
            
            // Check for other post types (like WooCommerce products, etc.)
            $post_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
            foreach ( $post_types as $post_type ) {
                $custom_post = get_page_by_path( $slug, OBJECT, $post_type );
                if ( $custom_post ) {
                    unset( $query_vars['pagename'] );
                    $query_vars['name'] = $slug;
                    $query_vars['post_type'] = $post_type;
                    return $query_vars;
                }
            }
        }
        
        return $query_vars;
    }

    /**
     * Disable canonical redirect for URLs with language prefix
     * Prevents WordPress from redirecting /es/cart/ to /cart/
     */
    function disable_canonical_redirect_for_lang( $redirect_url, $requested_url ) {
        // If the requested URL has a language prefix, don't redirect
        if ( preg_match( '#^https?://[^/]+/[a-z]{2}/#', $requested_url ) ) {
            return false;
        }
        return $redirect_url;
    }


    function livelang_detect_language() {

        $lang = get_query_var( 'lang' );

        if ( $lang ) {
            if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                define( 'LIVELANG_CURRENT_LANG', $lang );
            }
        } else {
            // Check cookie as fallback
            $lang_from_cookie = isset( $_COOKIE['livelang_lang'] ) ? sanitize_key( $_COOKIE['livelang_lang'] ) : '';
            
            if ( $lang_from_cookie && preg_match( '/^[a-z]{2}$/', $lang_from_cookie ) ) {
                if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                    define( 'LIVELANG_CURRENT_LANG', $lang_from_cookie );
                }
            } else {
                if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                    define( 'LIVELANG_CURRENT_LANG', 'en' );
                }
            }
        }
    }
   
    public function register_rest_routes() {
        register_rest_route( 'livelang/v1', '/save', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'rest_save_translation' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );
    }

    public function rest_permission_check() {
        return $this->is_enabled_for_user();
    }

    public function rest_save_translation( $request ) {
        $params = $request->get_params();
        
        $original   = isset( $params['original'] ) ? sanitize_text_field( $params['original'] ) : '';
        $translated = isset( $params['translated'] ) ? sanitize_text_field( $params['translated'] ) : '';
        $slug       = isset( $params['slug'] ) ? sanitize_text_field( $params['slug'] ) : '';
        $language   = isset( $params['language'] ) ? sanitize_text_field( $params['language'] ) : '';
        $is_global  = isset( $params['is_global'] ) ? (int) $params['is_global'] : 0;

        if ( $original === '' || $translated === '' ) {
            return new WP_Error( 'empty_text', 'Empty text', array( 'status' => 400 ) );
        }

        // update if exists
        $existing = $this->db->get_translation_by_original_and_slug( $original, $slug, $language );
        if ( $existing ) {
            $this->db->update(
                $existing->id,
                array(
                    'translated_text' => $translated,
                    'is_global'       => $is_global,
                )
            );
        } else {
            $this->db->insert(
                array(
                    'original_text'   => $original,
                    'translated_text' => $translated,
                    'slug'            => $slug,
                    'language'        => $language,
                    'is_global'       => $is_global,
                )
            );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    protected function is_enabled_for_user() {
        $settings = get_option( 'livelang_settings', array( 'enabled' => 1 ) );
        if ( empty( $settings['enabled'] ) ) {
            return false;
        }

        if ( ! empty( $settings['allowed_roles'] ) && is_array( $settings['allowed_roles'] ) ) {
            $user    = wp_get_current_user();
            $roles   = (array) $user->roles;
            $allowed = $settings['allowed_roles'];
            if( in_array( 'all', $allowed, true ) ) {
                return true;
            }
            foreach ( $roles as $role ) {
                if ( in_array( $role, $allowed, true ) ) {
                    return true;
                }
            }
            return false;
        }

        return current_user_can( 'manage_options' );
    }

    public function start_buffer() {
        $this->livelang_detect_language();
        // Start and close inside SAME function = WP.org rules OK
        ob_start();

        add_action('shutdown', function() {

            $content = ob_get_clean(); // close safely inside SAME logical flow
            
            // apply translation
            $content = livelang()->frontend->buffer_callback( $content );

            /**
             * IMPORTANT:
             * $content is the entire HTML buffer.
             * Escaping here would break HTML structure.
             * All replacements inside buffer_callback() are sanitized.
             */
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $content; // output final translated HTML
        }, 0);
    }

    public function buffer_callback( $content ) {

        $slug     = $this->get_current_slug();
        $language = $this->getCurrentLanguage();

        // Unique cache key per page + language
        $cache_key   = "livelang_translations_{$language}_{$slug}";
        $cache_group = 'livelang';

        // Try Object Cache first
        $translations = wp_cache_get( $cache_key, $cache_group );

        if ( false === $translations ) {

            // Cache miss → load from DB
            $translations = $this->db->get_translations_for_slug( $slug, $language );

            // Cache for 12 hours (filterable)
            wp_cache_set(
                $cache_key,
                $translations,
                $cache_group,
                apply_filters( 'livelang_cache_ttl', 12 * HOUR_IN_SECONDS )
            );
        }

        if ( empty( $translations ) ) {
            return $content;
        }

        // Check if we should skip translating text with numbers
        $settings = get_option( 'livelang_settings', array() );
        $translate_numbers = ! empty( $settings['translate_numbers'] ) ? true : false;

        // Replace loop (optimized order)
        foreach ( $translations as $row ) {

            if ( empty( $row->original_text ) ) {
                continue;
            }

            $original   = $row->original_text;
            $translated = $row->translated_text;

            // Skip translation if text contains numbers and translate_numbers is disabled
            if ( ! $translate_numbers && $this->text_contains_numbers( $original ) ) {
                $translated = $this->get_only_text_from_transation( $translated );
                $original = $this->get_only_text_from_transation( $original );
            }

            // Use a unique marker to prevent double-replacement (when original appears in translated)
            $marker = '___LIVELANG_' . md5( $original . $translated ) . '___';

            // Replace raw version with marker
            $content = str_replace(
                $original,
                $marker,
                $content
            );

            // Replace escaped version with marker
            $content = str_replace(
                esc_html( $original ),
                $marker,
                $content
            );

            // Replace marker with translated
            $content = str_replace(
                $marker,
                $translated,
                $content
            );
        }

        return $content;
    }

    /**
     * Check if text contains any numbers (digits)
     *
     * @param string $text The text to check
     * @return bool True if text contains numbers, false otherwise
     */
    protected function text_contains_numbers( $text ) {
        return (bool) preg_match( '/\d/', $text );
    }

    protected function get_only_text_from_transation ($text) {
        return preg_replace('/\p{N}/u', '', $text);
    }

    protected function get_only_number_from_transation ($text) {
        return preg_replace('/[^\p{N}]/u', '', $text);
    }


    protected function get_current_slug() {
        global $wp;
        if ( ! isset( $wp ) ) {
            return '';
        }
        $slug = trim( add_query_arg( array(), $wp->request ), '/' );
        $lang = LIVELANG_CURRENT_LANG;
        // add lang prefix if exists
        if ( $lang ) {
            $prefix = $lang . '/';
            if ( strpos( $slug, $prefix ) === 0 ) {
                $slug = substr( $slug, strlen( $prefix ) );
            } elseif ( $slug === $lang ) {
                $slug = '';
            }
        }
        
        // If slug is empty, we're on the homepage
        if ( $slug === '' ) {
            $slug = 'home';
        }
        return $slug;
    }

    protected function get_homepage_slug() {
        $homepage_id = (int) get_option( 'page_on_front' );
        if ( $homepage_id ) {
            $homepage_slug = get_post_field( 'post_name', $homepage_id );
            return $homepage_slug ? $homepage_slug : '';
        }
        return '';
    }

    /**
     * Get languages for frontend from database
     *
     * @return array
     */
    protected function get_languages_for_frontend() {
        $languages = get_option( 'livelang_languages', array() );
        if ( empty( $languages ) ) {
            // Fallback to defaults
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
        }
        // Sort by order
        usort( $languages, function ( $a, $b ) {
            return $a['order'] - $b['order'];
        });
        return $languages;
    }

    /**
     * Get default language
     *
     * @return string
     */
    protected function get_default_language() {
        $languages = $this->get_languages_for_frontend();
        foreach ( $languages as $lang ) {
            if ( ! empty( $lang['is_default'] ) ) {
                return $lang['code'];
            }
        }
        return 'en';
    }

    /**
     * Get label for language code
     *
     * @param string $code Language code
     * @return string Language label
     */
    protected function get_language_label( $code ) {
        $languages = $this->get_current_language();
        return isset( $languages[ $code ] ) ? $languages[ $code ] : $code;
    }

    

    public function enqueue_assets() {
        wp_enqueue_style(
            'livelang-frontend',
            LIVELANG_PLUGIN_URL . 'assets/css/livelang-frontend.css',
            array(),
            LIVELANG_VERSION
        );

        wp_enqueue_script(
            'livelang-frontend',
            LIVELANG_PLUGIN_URL . 'assets/js/livelang-frontend.js',
            array(),
            LIVELANG_VERSION,
            true
        );

        if ( $this->is_enabled_for_user() ) {
            wp_enqueue_script(
                'livelang-frontend-editor',
                LIVELANG_PLUGIN_URL . 'assets/js/livelang-frontend-editor.js',
                array(),
                LIVELANG_VERSION,
                true
        );
        }

        $slug         = $this->get_current_slug();
        $translations = $this->db->get_translations_for_slug( $slug );

        $dict = array();
        if ( $translations ) {
            foreach ( $translations as $row ) {
                $dict[] = array(
                    'original'   => $row->original_text,
                    'translated' => $row->translated_text,
                );
            }
        }


        // Get languages from database
        $languages_data = $this->get_languages_for_frontend();
        $languages = array();
        foreach ( $languages_data as $lang ) {
            $languages[ $lang['code'] ] = $lang['label'];
        }

        wp_localize_script(
            'livelang-frontend',
            'LiveLangSettings',
            array(
                'restUrl' => esc_url_raw( rest_url( 'livelang/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'enable_translation' => $this->is_enabled_for_user(),
                'slug'    => $slug,
                'currentLanguage' => $this->getCurrentLanguage(),
                'homepageSlug' => $this->get_homepage_slug(),
                'homeUrl' => get_option( 'home' ),
                'languages' => $languages,
                'dict'    => $dict, 
                'i18n'    => array(
                    'editText'   => __( 'Edit translation', 'livelang' ),
                    'original'   => __( 'Original', 'livelang' ),
                    'translated' => __( 'Translated', 'livelang' ),
                    'global'     => __( 'Global?', 'livelang' ),
                    'save'       => __( 'Save', 'livelang' ),
                    'saving'     => __( 'Saving...', 'livelang' ),
                    'saved'     => __( 'Saved', 'livelang' ),
                    'cancel'     => __( 'Cancel', 'livelang' ),
                    'undo'       => __( 'Undo', 'livelang' ),
                    'redo'       => __( 'Redo', 'livelang' ),
                    'translate'  => __( 'Translate', 'livelang' ),
                    'translating' => __( 'Translating...', 'livelang' ),
                    'translated' => __( 'Translated', 'livelang' ),
                ),
            )
        );
    }



    /**
     * Get all supported languages
     * Static cached for performance
     *
     * @return array
     */
    function get_current_language() {
        // Get languages from database
        $languages_data = $this->get_languages_for_frontend();
        $languages = array();
        foreach ( $languages_data as $lang ) {
            $languages[ $lang['code'] ] = $lang['label'];
        }

        return $languages;
    }

    /**
     * Get current selected language
     *
     * Priority:
     * 1. URL (?lang=fr)
     * 2. Cookie
     * 3. Default (en)
     *
     * @return string
     */
    function getCurrentLanguage() {
        // Check if constant is defined (it may not be in admin/REST API context)
        if ( defined( 'LIVELANG_CURRENT_LANG' ) ) {
            $lang = LIVELANG_CURRENT_LANG;
            if ( $lang ) {
                // Only set cookie if headers haven't been sent (prevents issues with REST API/block editor)
                if ( ! headers_sent() ) {
                    setcookie('livelang_lang', $lang, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                }
                return $lang;
            }
        }

        // Fallback to cookie or default
        if ( isset($_COOKIE['livelang_lang']) ) {
            return sanitize_key($_COOKIE['livelang_lang']);
        }

        return 'en';
    }

    function livelang_language_to_country($lang) {

        $map = apply_filters('livelang_language_flags', [

            // A
            'af' => 'za', // Afrikaans → South Africa
            'sq' => 'al',
            'am' => 'et',
            'ar' => 'sa',
            'hy' => 'am',
            'az' => 'az',

            // B
            'eu' => 'es',
            'be' => 'by',
            'bn' => 'bd',
            'bs' => 'ba',
            'bg' => 'bg',

            // C
            'ca' => 'es',
            'zh' => 'cn',
            'hr' => 'hr',
            'cs' => 'cz',

            // D
            'da' => 'dk',
            'nl' => 'nl',

            // E
            'en' => 'us', // default (can override)
            'et' => 'ee',

            // F
            'fi' => 'fi',
            'fr' => 'fr',

            // G
            'gl' => 'es',
            'ka' => 'ge',
            'de' => 'de',
            'el' => 'gr',
            'gu' => 'in',

            // H
            'he' => 'il',
            'hi' => 'in',
            'hu' => 'hu',

            // I
            'is' => 'is',
            'id' => 'id',
            'it' => 'it',

            // J
            'ja' => 'jp',

            // K
            'kn' => 'in',
            'kk' => 'kz',
            'km' => 'kh',
            'ko' => 'kr',

            // L
            'lo' => 'la',
            'lv' => 'lv',
            'lt' => 'lt',

            // M
            'mk' => 'mk',
            'ms' => 'my',
            'ml' => 'in',
            'mt' => 'mt',
            'mr' => 'in',
            'mn' => 'mn',

            // N
            'ne' => 'np',
            'nb' => 'no',
            'nn' => 'no',

            // P
            'fa' => 'ir',
            'pl' => 'pl',
            'pt' => 'pt',
            'pa' => 'in',

            // R
            'ro' => 'ro',
            'ru' => 'ru',

            // S
            'sr' => 'rs',
            'si' => 'lk',
            'sk' => 'sk',
            'sl' => 'si',
            'es' => 'es',
            'sw' => 'ke',
            'sv' => 'se',

            // T
            'ta' => 'in',
            'te' => 'in',
            'th' => 'th',
            'tr' => 'tr',

            // U
            'uk' => 'ua',
            'ur' => 'pk',
            'uz' => 'uz',

            // V
            'vi' => 'vn',

            // W
            'cy' => 'gb',
        ]);

        return $map[$lang] ?? 'un'; // fallback
    }

    function get_flag_url( $code ) {
        $country_code = $this->livelang_language_to_country($code);
        return plugins_url( 'assets/images/flags/' . $country_code . '.webp', LIVELANG_PLUGIN_FILE );
    }

    /**
     * Render the frontend editor bar
     */
    public function render_editor_bar() {
        if ( ! $this->is_enabled_for_user() ) {
            return;
        }

        $languages = $this->get_current_language();
        $current   = $this->getCurrentLanguage();
        $current_label = isset( $languages[ $current ] ) ? $languages[ $current ] : 'Language';
        
        $lang_dropdown_display = count($languages) <= 1 ? 'none' : 'block';
        ?>
        <div id="livelang-toggle" class="livelang-bar" contenteditable="false">
            <div class="livelang-bar-actions">
                <label class="livelang-global-label">
                    <input type="checkbox" class="livelang-global"> <?php _e( 'Global?', 'livelang' ); ?>
                </label>
                
                <div class="livelang-language-dropdown" style="display: <?php echo esc_attr($lang_dropdown_display); ?>">
                    <button class="livelang-language-toggle ddd" type="button">
                        <img src="<?php echo esc_url($this->get_flag_url($current)); ?>" class="livelang-current-language-flag">
                        <span class="livelang-current-language-label"><?php echo esc_html($current_label); ?></span>
                        <span class="livelang-toggle-icon">▼</span>
                    </button>
                    <ul class="livelang-language-list">
                        <?php foreach ( $languages as $code => $label ) : 
                            $active_class = $code === $current ? ' class="active"' : '';
                        ?>
                            <li>
                                <a href="#" data-lang="<?php echo esc_attr($code); ?>"<?php echo $active_class; ?>>
                                    <img src="<?php echo esc_url($this->get_flag_url($code)); ?>" class="livelang-language-flag">
                                    <span class="livelang-language-label"><?php echo esc_html($label); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="livelang-bar-main"><?php _e( 'Translate', 'livelang' ); ?></button>
        </div>
        <?php
    }

    /**
     * Render language switcher dropdown
     */
    function livelang_language_switcher() {
        $languages = $this->get_current_language();
        $current   = $this->getCurrentLanguage();
        $current_label = isset( $languages[ $current ] ) ? $languages[ $current ] : 'Language';

        ob_start();

        echo '<div id="livelang-language-switcher" class="livelang-language-dropdown">';
        echo '<button class="livelang-language-toggle"><img src="' . esc_url($this->get_flag_url($current)) . '" class="livelang-current-language-flag"><span class="livelang-current-language-label">' . esc_html( $current_label ) . '</span> <span class="livelang-toggle-icon">▼</span></button>';
        echo '<ul class="livelang-language-list">';

        foreach ( $languages as $code => $label ) {
            $is_active = $current === $code ? ' class="active"' : '';
            printf(
                '<li><a href="#" data-lang="%s"%s><img src="%s" class="livelang-language-flag"><span class="livelang-language-label">%s</span></a></li>',
                esc_attr($code),
                $is_active,
                esc_url($this->get_flag_url($code)),
                esc_html($label)
            );
        }

        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
    }

    function livelang_language_switcher_for_nav_menu() {
        $languages = $this->get_current_language();
        $current   = $this->getCurrentLanguage();
        $current_label = isset( $languages[ $current ] ) ? $languages[ $current ] : 'Language';

        ob_start();

        echo '<div id="livelang-language-switcher-for-nav-menu" class="livelang-language-dropdown">';
        echo '<button class="livelang-language-toggle"><img src="' . esc_url($this->get_flag_url($current)) . '" class="livelang-current-language-flag"><span class="livelang-language-label">' . esc_html( $current_label ) . '</span> <span class="livelang-toggle-icon">▼</span></button>';
        echo '<ul class="livelang-language-list-for-nav-menu sub-menu">';

        foreach ( $languages as $code => $label ) {
            $is_active = $current === $code ? ' class="active"' : '';
            printf(
                '<li><a href="#" data-lang="%s"%s><img src="%s" class="livelang-language-flag"><span class="livelang-language-label">%s</span></a></li>',
                esc_attr($code),
                $is_active,
                esc_url($this->get_flag_url($code)),
                esc_html($label)
            );
        }

        echo '</ul>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Filter menu item URLs to include language prefix
     */
    public function filter_menu_urls( $items ) {
        foreach ( $items as $item ) {
            if ( ! empty( $item->url ) ) {
                $item->url = $this->filter_url( $item->url );
            }
        }
        return $items;
    }

    /**
     * Filter URLs to include current language prefix
     */
    public function filter_url( $url ) {
        if ( is_admin() || ! $url || $url === '#' || strpos( $url, '#' ) === 0 ) {
            return $url;
        }

        $lang = $this->getCurrentLanguage();

        // Skip if default language or matching error/empty
        if ( ! $lang || $lang === 'en' || $lang === $this->get_default_language() ) {
            return $url;
        }

        // Basic check to exclude assets
        if ( preg_match( '/\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|xml)$/i', $url ) ) {
            return $url;
        }

        // Parse URL
        $parsed = parse_url( $url );
        
        // Get raw home URL to check host match (avoid infinite loop by NOT calling home_url())
        $raw_home = get_option( 'home' );
        $home_parsed = parse_url( $raw_home );
        $home_host = isset($home_parsed['host']) ? $home_parsed['host'] : '';

        // Ensure it's our host
        if ( isset($parsed['host']) && $parsed['host'] !== $home_host ) {
            return $url;
        }

        // Get path
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        
        // Exclude system paths
        if ( preg_match( '#^/(wp-admin|wp-content|wp-json|wp-includes)#', $path ) ) {
            return $url;
        }

        // Check availability of prefix
        if ( preg_match( '#^/' . $lang . '(/|$)#', $path ) ) {
            return $url;
        }

        // Inject prefix
        if ( $path === '/' ) {
            $path = '/' . $lang . '/';
        } else {
             // ensure path starts with /
             if ( substr( $path, 0, 1 ) !== '/' ) {
                 $path = '/' . $path;
             }
             $path = '/' . $lang . $path;
        }

        // Rebuild URL
        // Use component if available, else fallback to home settings or default
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : (isset($home_parsed['scheme']) ? $home_parsed['scheme'] . '://' : '//');
        $host   = isset($parsed['host']) ? $parsed['host'] : $home_host;
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $frag   = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        return $scheme . $host . $port . $path . $query . $frag;
    }

    
    /**
     * Expand the single language switcher menu item into multiple language items
     * This allows WordPress walker to handle rendering and classes properly.
     *
     * @param array $items Menu items
     * @return array
     */
    public function expand_language_menu( $items ) {
        if ( is_admin() || empty( $items ) ) {
            return $items;
        }

        $new_items = array();
        $languages = $this->get_current_language();
        $current   = $this->getCurrentLanguage();
        
        static $id_counter = 0;

        foreach ( $items as $item ) {
            $new_items[] = $item;

            // Target our specific menu item
            if ( $item->url === '#livelang_switcher' || in_array( 'menu-item-language-switcher', (array) $item->classes ) ) {
                
                // 1. Update the parent item as the "Current Language" label
                $current_label = isset( $languages[ $current ] ) ? $languages[ $current ] : 'Language';
                $item->title = esc_html( $current_label );
                $item->url = '#'; 
                
                if ( ! in_array( 'menu-item-has-children', (array) $item->classes ) ) {
                    $item->classes[] = 'menu-item-has-children';
                }
                $item->classes[] = 'livelang-menu-item-parent';
                $item->classes[] = 'livelang-language-dropdown';
                $item->livelang_type = 'parent';

                $parent_id = ! empty( $item->db_id ) ? $item->db_id : $item->ID;

                // 2. Add ALL languages as children (Submenu)
                $order_base = 10000; // prevent collision with real menu items

                foreach ( $languages as $code => $label ) {
                    $lang_item = new stdClass();

                    $lang_item->ID = 2000000 + ( ++$id_counter );
                    $lang_item->db_id = $lang_item->ID;
                    $lang_item->post_type = 'nav_menu_item';
                    $lang_item->post_status = 'publish';
                    $lang_item->type = 'custom';
                    $lang_item->object = 'custom';
                    $lang_item->object_id = $lang_item->ID;

                    $lang_item->title = $label;
                    $lang_item->url = '#';
                    $lang_item->menu_item_parent = (int) $item->ID;
                    $lang_item->menu_order = $item->menu_order + 100 + $id_counter;

                    $lang_item->classes = array(
                        'menu-item',
                        'menu-item-type-custom',
                        'menu-item-object-custom',
                        'livelang-language-item',
                        'livelang-lang-' . $code
                    );

                    $lang_item->livelang_code = $code;
                    $lang_item->livelang_type = 'child';


                    $new_items[] = $lang_item;
                }


            }
        }
        return $new_items;
    }

    /**
     * Add data-lang attribute to language switcher links
     */
    public function nav_menu_link_attributes( $atts, $item, $args, $depth ) {
        // Try to get lang code from property first
        if ( isset( $item->livelang_code ) ) {
            $atts['data-lang'] = $item->livelang_code;
        } else {
            // Fallback: check classes (useful if property was lost during WP setup)
            foreach ( (array) $item->classes as $class ) {
                if ( is_string( $class ) && strpos( $class, 'livelang-lang-' ) === 0 ) {
                    $atts['data-lang'] = str_replace( 'livelang-lang-', '', $class );
                    break;
                }
            }
        }
        
        // Add toggle class to the parent link for JS compatibility
        if ( property_exists( $item, 'classes' ) && is_array( $item->classes ) && in_array( 'livelang-menu-item-parent', $item->classes ) ) {
            $atts['class'] = ( isset( $atts['class'] ) ? $atts['class'] . ' ' : '' ) . 'livelang-language-toggle-inside-menu';
        }
        
        return $atts;
    }

    /**
     * Fallback for custom walkers that don't use nav_menu_link_attributes
     */
    public function livelang_menu_item_output( $item_output, $item, $depth, $args ) {
        if ( is_admin() ) {
            return $item_output;
        }

        $lang_code = '';
        if ( isset( $item->livelang_code ) ) {
            $lang_code = $item->livelang_code;
        } else {
            foreach ( (array) $item->classes as $class ) {
                if ( is_string( $class ) && strpos( $class, 'livelang-lang-' ) === 0 ) {
                    $lang_code = str_replace( 'livelang-lang-', '', $class );
                    break;
                }
            }
        }

        if ( $lang_code ) {
            $flag_url = $this->get_flag_url( $lang_code );
            $flag_html = '<img src="' . esc_url( $flag_url ) . '" class="livelang-language-flag" style="width:25px; height:15px; margin-right:10px; vertical-align:middle;">';
            
            // Inject data-lang attribute into the first <a> tag if not present
            if ( strpos( $item_output, 'data-lang=' ) === false ) {
                $item_output = preg_replace( '/<a /', '<a data-lang="' . esc_attr( $lang_code ) . '" ', $item_output, 1 );
            }

            // Inject flag before the title inside the <a> tag
            $item_output = preg_replace( '/(<a[^>]*>)(.*)(<\/a>)/isU', '$1' . $flag_html . '<span class="livelang-language-label">$2</span>$3', $item_output );
        }

        // Inject toggle class for parent if missed
        if ( property_exists( $item, 'classes' ) && is_array( $item->classes ) && in_array( 'livelang-menu-item-parent', $item->classes ) ) {
             if ( strpos( $item_output, 'livelang-language-toggle' ) === false ) {
                 $item_output = preg_replace( '/class="([^"]*)"/', 'class="$1 livelang-language-toggle-inside-menu"', $item_output, 1 );
             }
        }

        return $item_output;
    }
}
