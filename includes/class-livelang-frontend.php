<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LiveLang_Frontend {

    /** @var LiveLang_DB */
    protected $db;
    protected $buffering = false;
    
    // Global recursion prevention flag
    private static $in_translation_hook = false;

    public function __construct( $db ) {
        $this->db = $db;

       
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'livelang_language_switcher', array( $this, 'livelang_language_switcher' ) );
       

        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_filter( 'request', array( $this, 'handle_language_request' ) );
        add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect_for_lang' ), 10, 2 );
        add_action( 'init', array( $this, 'add_rewrite_tag' ) );
        add_action( 'init', array( $this, 'setup_language_uri' ), 0 );

        // Early locale filters
        add_filter( 'locale', array( $this, 'filter_wp_locale' ), 20 );
        add_filter( 'determine_locale', array( $this, 'filter_wp_locale' ), 20 );

        add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
        // Re-apply locale at the wp hook to ensure it persists through theme/plugin initialization
        add_action( 'wp', array( $this, 'ensure_locale_persists' ), 1 );
        // Also re-apply just before rendering to ensure it hasn't been reset
        add_action( 'wp_head', array( $this, 'ensure_locale_persists' ), 1 );

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
        
        // Register gettext hooks at wp action (when query is ready)
        //add_action('wp', [$this, 'init_gettext_hooks']);

    }

    function add_rewrite_tag() {
        add_rewrite_tag( '%lang%', '([a-z]{2,5})' );
    }

    public function setup_language_uri() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        $home_path = parse_url(get_option('home'), PHP_URL_PATH);
        
        if ($home_path && $home_path !== '/') {
            $path = preg_replace('#^' . preg_quote(rtrim($home_path, '/'), '#') . '#', '', $path);
        }
        
        if (empty($path) || substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        if (preg_match('#^/([a-z]{2,5})(?:/(.*))?$#', $path, $matches)) {
            $lang_from_url = $matches[1];
            
            $valid_codes = array();
            $languages = $this->get_languages_for_frontend();
            if (is_array($languages)) {
                foreach ($languages as $l) {
                    if (isset($l['code'])) {
                        $valid_codes[] = $l['code'];
                    }
                }
            }
            
            if (in_array($lang_from_url, $valid_codes)) {
                $_GET['lang'] = $lang_from_url;
                $rest_of_path = isset($matches[2]) ? '/' . ltrim($matches[2], '/') : '/';
                
                $query = parse_url($request_uri, PHP_URL_QUERY);
                $new_uri = $home_path && $home_path !== '/' ? rtrim($home_path, '/') . $rest_of_path : $rest_of_path;
                if ($query) {
                    $new_uri .= '?' . $query;
                }
                
                $_SERVER['REQUEST_URI'] = $new_uri;
            }
        }
    }

    function add_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    function handle_language_request( $query_vars ) {
        // Since we extract the language early in setup_language_uri,
        // WordPress natively parses the query_vars. We just need to persist the lang.
        if (isset($_GET['lang'])) {
            $query_vars['lang'] = sanitize_key($_GET['lang']);
        }

        // Handle Lang-only request (Homepage for a language)
        // If we only have 'lang' (and possibly 'paged'/'page'), it's the true homepage
        $core_vars = array_diff( array_keys( $query_vars ), array( 'lang', 'paged', 'page', 'preview' ) );
        
        if ( isset( $query_vars['lang'] ) && empty( $core_vars ) ) {
            if ( 'page' === get_option( 'show_on_front' ) ) {
                $page_id = get_option( 'page_on_front' );
                if ( $page_id ) {
                    $query_vars['page_id'] = $page_id;
                    $query_vars['is_front_page'] = 1;
                }
            }
        }

        return $query_vars;
    }

    function disable_canonical_redirect_for_lang( $redirect_url, $requested_url ) {
        // If we are actively parsing a custom language, disable native WordPress
        // canonical redirect fixing, because we altered $_SERVER['REQUEST_URI'] 
        // to spoof native routing, which causes an infinite redirect loop.
        if ( isset($_GET['lang']) && sanitize_key($_GET['lang']) !== $this->get_default_language() ) {
            return false;
        }
        return $redirect_url;
    }


    function livelang_detect_language() {

        $lang = get_query_var( 'lang' );

        if ( $lang ) {
            if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                define( 'LIVELANG_CURRENT_LANG', $lang );
                if ( ! headers_sent() ) {
                    setcookie('livelang_lang', $lang, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                }
            }
        } else {
            // Check cookie as fallback
            $lang_from_cookie = isset( $_COOKIE['livelang_lang'] ) ? sanitize_key( $_COOKIE['livelang_lang'] ) : '';
            
            if ( $lang_from_cookie && preg_match( '/^[a-z]{2,5}$/', $lang_from_cookie ) ) {
                if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                    define( 'LIVELANG_CURRENT_LANG', $lang_from_cookie );
                }
            } else {
                if ( ! defined( 'LIVELANG_CURRENT_LANG' ) ) {
                    $default_lang = $this->get_default_language();
                    define( 'LIVELANG_CURRENT_LANG', $default_lang );
                    // Set cookie for default language too
                    if ( ! headers_sent() ) {
                        setcookie('livelang_lang', $default_lang, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                    }
                }
            }
        }
    }
   
    public function register_rest_routes() {
        register_rest_route( 'livelang-api/v1', '/save', array(
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
        
        // Set locale for the detected language
        $this->set_current_locale();
        
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

    /**
     * Safely add gettext hooks only on frontend after WP is ready
     * These hooks work WITH the buffer method to catch dynamic translations
     */
    public function init_gettext_hooks() {
        // Don't add hooks if we're in admin or REST API or AJAX
        if ( is_admin() || defined('REST_REQUEST') || defined('DOING_AJAX') ) {
            return;
        }

        // To avoid recursion and 502 errors we DO NOT register gettext hooks here.
        // The plugin uses the output buffer (`buffer_callback`) for frontend
        // translations which is safer and avoids infinite recursion caused by
        // running gettext filters during early WP bootstrap. If you need to
        // re-enable gettext interception, do so carefully and behind a feature
        // flag after ensuring `getCurrentLanguage()` and slug helpers cannot
        // trigger gettext themselves.
        return;
    }

    public function filter_wp_locale( $locale ) {
        if ( is_admin() || defined('REST_REQUEST') || defined('DOING_AJAX') ) {
            return $locale;
        }

        if ( defined( 'LIVELANG_CURRENT_LANG' ) && LIVELANG_CURRENT_LANG ) {
            $lang = LIVELANG_CURRENT_LANG;
        } else {
            $path = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
            $home_path = parse_url( get_option('home'), PHP_URL_PATH );
            
            if ( $home_path && $home_path !== '/' ) {
                if ( strpos( $path, $home_path ) === 0 ) {
                    $path = substr( $path, strlen( $home_path ) );
                }
            }
            if ( ! $path || substr( $path, 0, 1 ) !== '/' ) {
                $path = '/' . $path;
            }

            if ( preg_match( '#^/([a-z]{2,5})(?:/|$)#', $path, $matches ) ) {
                $lang = $matches[1];
            } else if ( isset( $_COOKIE['livelang_lang'] ) ) {
                $lang = sanitize_key( $_COOKIE['livelang_lang'] );
            } else {
                return $locale;
            }
        }

        if ( empty( $lang ) || $lang === $this->get_default_language() ) {
            return $locale;
        }

        return $this->get_locale_for_language( $lang );
    }

    /**
     * Map language code to WordPress locale
     * e.g., 'bn' => 'bn_BD', 'es' => 'es_ES'
     */
    protected function get_locale_for_language($lang) {
        $locale_map = apply_filters('livelang_locale_map', array(
            'af' => 'af',
            'sq' => 'sq_AL',
            'am' => 'am_ET',
            'ar' => 'ar',
            'hy' => 'hy',
            'az' => 'az',
            'eu' => 'eu_ES',
            'be' => 'be_BY',
            'bn' => 'bn_BD',
            'bs' => 'bs_BA',
            'bg' => 'bg_BG',
            'ca' => 'ca_ES',
            // Prefer Traditional Chinese by default when admin shows zh_TW
            'zh' => 'zh_TW',
            // Assamese (present in WP admin language list)
            'as' => 'as',
            'hr' => 'hr_HR',
            'cs' => 'cs_CZ',
            'da' => 'da_DK',
            'nl' => 'nl_NL',
            'en' => 'en_US',
            'et' => 'et_EE',
            'fi' => 'fi_FI',
            'fr' => 'fr_FR',
            'gl' => 'gl_ES',
            'ka' => 'ka_GE',
            'de' => 'de_DE',
            'el' => 'el_GR',
            'gu' => 'gu_IN',
            'he' => 'he_IL',
            'hi' => 'hi_IN',
            'hu' => 'hu_HU',
            'is' => 'is_IS',
            'id' => 'id_ID',
            'it' => 'it_IT',
            'ja' => 'ja',
            'kn' => 'kn_IN',
            'kk' => 'kk_KZ',
            'km' => 'km',
            'ko' => 'ko_KR',
            'lo' => 'lo',
            'lv' => 'lv_LV',
            'lt' => 'lt_LT',
            'mk' => 'mk_MK',
            'ms' => 'ms_MY',
            'ml' => 'ml_IN',
            'mt' => 'mt_MT',
            'mr' => 'mr_IN',
            'mn' => 'mn',
            'ne' => 'ne_NP',
            'nb' => 'nb_NO',
            'nn' => 'nn_NO',
            'fa' => 'fa_IR',
            'pl' => 'pl_PL',
            'pt' => 'pt_PT',
            'pa' => 'pa_IN',
            'ro' => 'ro_RO',
            'ru' => 'ru_RU',
            'sr' => 'sr_RS',
            'si' => 'si_LK',
            'sk' => 'sk_SK',
            'sl' => 'sl_SI',
            'es' => 'es_ES',
            'sw' => 'sw_KE',
            'sv' => 'sv_SE',
            'ta' => 'ta_IN',
            'te' => 'te_IN',
            'th' => 'th',
            'tr' => 'tr_TR',
            'uk' => 'uk_UA',
            'ur' => 'ur_PK',
            'uz' => 'uz_UZ',
            'vi' => 'vi_VN',
            'cy' => 'cy_GB',
        ));

        return isset($locale_map[$lang]) ? $locale_map[$lang] : $lang;
    }

    /**
     * Set WordPress locale to current language
     * This enables WordPress to load correct .mo files for translation
     */
    protected function set_current_locale() {
        // Get language safely (doesn't rely on constant)
        $language = $this->getCurrentLanguage();
        if (empty($language)) {
            return;
        }

        $locale = $this->get_locale_for_language($language);
        
        // Debug: Check current state before setting
        $debug_info = array(
            'language' => $language,
            'target_locale' => $locale,
            'wp_lang_dir' => WP_LANG_DIR,
            'mo_file' => WP_LANG_DIR . '/' . $locale . '.mo',
            'mo_exists' => file_exists(WP_LANG_DIR . '/' . $locale . '.mo'),
        );
        
        // Try to load .mo file explicitly before using switch_to_locale
        $mo_file = WP_LANG_DIR . '/' . $locale . '.mo';
        
        if (file_exists($mo_file)) {
            if (function_exists('load_textdomain')) {
                $mo_loaded = load_textdomain('default', $mo_file);
                $debug_info['mo_loaded'] = $mo_loaded;
            }
        }
        
        // Set the locale globally using switch_to_locale
        if (function_exists('switch_to_locale')) {
            $switch_result = switch_to_locale($locale);
            $debug_info['switch_locale_result'] = $switch_result;
            $debug_info['current_locale_after_switch'] = get_locale();
        } else {
            global $wp_locale;
            $GLOBALS['wp_locale'] = new WP_Locale();
            $debug_info['fallback_used'] = true;
        }

        // Also set PHP locale for any PHP translation functions
        // Note: This might fail on some servers if locale is not installed at OS level
        $country = $this->livelang_language_to_country($language);
        $posix_locale = $language . '_' . strtoupper($country); // e.g. af_ZA

        $locales_to_try = array(
            $locale,
            $locale . '.UTF-8',
            $locale . '.utf8',
            $posix_locale,
            $posix_locale . '.UTF-8',
            $posix_locale . '.utf8',
            'en_US.UTF-8',
            'C.UTF-8',
            'C'
        );

        $php_locale_result = setlocale(LC_ALL, ...$locales_to_try);
        $debug_info['php_setlocale_result'] = $php_locale_result;
        
        if (!$php_locale_result || $php_locale_result === false) {
            $debug_info['php_setlocale_fallback'] = setlocale(LC_ALL, 0); // Get current
        }
        
        // Store debug info as transient for easy access
        set_transient('livelang_debug_locale_' . $language, $debug_info, HOUR_IN_SECONDS);
    }


    /**
     * Ensure locale persists throughout page generation
     * This is called at multiple hooks to re-apply locale if it was reset
     */
    public function ensure_locale_persists() {
        $language = $this->getCurrentLanguage();
        
        // Skip for default language (no need to switch)
        if (empty($language) || $language === $this->get_default_language()) {
            return;
        }

        $locale = $this->get_locale_for_language($language);
        $current_locale = get_locale();

        // Check if locale needs to be re-applied
        if ($current_locale !== $locale) {        
            // Re-apply the locale
            if (function_exists('switch_to_locale')) {
                switch_to_locale($locale);
            }
        }
    }

    /**
     * Get diagnostic information for troubleshooting locale issues
     */
    public function get_locale_diagnostic_info() {
        $language = $this->getCurrentLanguage();
        $locale = $this->get_locale_for_language($language);
        $mo_file = WP_LANG_DIR . '/' . $locale . '.mo';
        
        $info = array(
            'current_language' => $language,
            'current_locale' => $locale,
            'wp_lang_dir' => WP_LANG_DIR,
            'mo_file_path' => $mo_file,
            'mo_file_exists' => file_exists($mo_file),
            'get_locale()' => get_locale(),
            'server_locales' => explode(',', (string)setlocale(LC_ALL, 0)),
            'WordPress_version' => get_bloginfo('version'),
            'function_switch_to_locale_exists' => function_exists('switch_to_locale'),
        );
        
        // Check if debug log is enabled
        if (defined('WP_DEBUG_LOG')) {
            $info['wp_debug_log_enabled'] = WP_DEBUG_LOG;
        }
        
        return $info;
    }

    protected function is_rtl_language($lang) {
        $rtl_langs = array('ar', 'he', 'fa', 'ur', 'ps', 'yi', 'syr', 'dv', 'ku', 'ug');
        return in_array(strtolower(substr($lang, 0, 2)), $rtl_langs, true);
    }

    protected function apply_rtl_formatting( &$content, $language ) {
        if ( $this->is_rtl_language( $language ) ) {
            // Force HTML dir attributes
            if (preg_match('/<html[^>]*dir=["\']ltr[\'"][^>]*>/i', $content)) {
                 $content = preg_replace('/(<html[^>]*dir=["\'])ltr([\'"][^>]*>)/i', '${1}rtl${2}', $content);
            } else if (strpos($content, 'dir="rtl"') === false && preg_match('/<html[^>]*>/i', $content)) {
                 $content = preg_replace('/(<html[^>]*)>/i', '$1 dir="rtl">', $content, 1);
            }

            // Force body classes for themes that rely on it
            if ( preg_match( '/<body[^>]*class=["\'][^"\']*["\'][^>]*>/i', $content ) ) {
                 $content = preg_replace( '/(<body[^>]*class=["\'])([^"\']*)(["\'][^>]*>)/i', '${1}rtl $2${3}', $content, 1 );
            } else if ( preg_match( '/<body[^>]*>/i', $content ) ) {
                 $content = preg_replace( '/(<body[^>]*)>/i', '$1 class="rtl">', $content, 1 );
            }
        }
    }

    public function buffer_callback( $content ) {

        $slug     = $this->get_current_slug();
        $language = $this->getCurrentLanguage();

        $map = $this->get_translations_map($slug, $language);

        $this->apply_rtl_formatting( $content, $language );

        if (empty($map)) {
            return $content;
        }

        // We support number-normalized keys in the map. If a key contains the
        // special placeholder "::NUM::" we'll treat it as a regex pattern and
        // replace matched numbers into the translated string where
        // the placeholder "{NUM}" exists.
        foreach ($map as $original => $translated) {
            if (empty($original) || empty($translated) || !is_string($original) || !is_string($translated)) {
                continue;
            }

            // Numeric-normalized key handling
            if (strpos($original, '::NUM::') !== false) {
                // Build regex pattern: escape non-placeholder parts and replace
                // each ::NUM:: with a capture group for digits
                $parts = explode('::NUM::', $original);
                $regex_parts = array_map(function($p) { return preg_quote($p, '/'); }, $parts);
                $pattern = '/'.implode('([0-9]+)', $regex_parts).'/u';

                // Replace using callback so we can inject captured numbers
                $content = preg_replace_callback($pattern, function($matches) use ($translated) {
                    // $matches[0] is full match; subsequent entries are captures
                    $repl = $translated;
                    // Replace each {NUM} occurrence with the corresponding capture
                    for ($i = 1; $i < count($matches); $i++) {
                        $repl = preg_replace('/\{NUM\}/', $matches[$i], $repl, 1);
                    }
                    return $repl;
                }, $content);
            } else {
                // Simple direct replace for exact keys
                $content = str_replace($original, $translated, $content);
            }
        }

        return $content;
    }

    public function get_translations_map($slug, $language = null) {
        if( !$language ) {
            $language = $this->getCurrentLanguage();
        }
        if( !$slug ) {
            $slug = $this->get_current_slug();
        }

        $settings = get_option( 'livelang_settings', array() );
        $translate_numbers = ! empty( $settings['translate_numbers'] ) ? true : false;
        $cache_key   = "livelang_translations_{$language}_{$slug}";
        $cache_group = 'livelang';

        $translations = wp_cache_get($cache_key, $cache_group);

        if (false === $translations) {
            $translations = $this->db->get_translations_for_slug($slug, $language);

            wp_cache_set(
                $cache_key,
                $translations,
                $cache_group,
                apply_filters('livelang_cache_ttl', 12 * HOUR_IN_SECONDS)
            );
        }

        // convert to map with safety checks
        $map = array();
        if (!empty($translations) && is_array($translations)) {
            foreach ($translations as $row) {
                // Type check: ensure $row is an object with the required properties
                if (!is_object($row) || empty($row->original_text) || empty($row->translated_text)) {
                    continue;
                }
                
                // Type check: ensure properties are strings
                $original = trim((string) $row->original_text);
                $translated = trim((string) $row->translated_text);

                // If numbers should be ignored for matching, create a
                // normalized map key where all digit runs are replaced by
                // the placeholder ::NUM::. Store the translated text with a
                // {NUM} placeholder so we can inject the actual number when
                // performing replacements.
                if ( ! $translate_numbers && $this->text_contains_numbers( $original ) ) {
                    // Check if both original and translation hold numeric portions.
                    $orig_numbers_count  = preg_match_all( '/\p{N}+/u', $original );
                    $trans_numbers_count = preg_match_all( '/\p{N}+/u', $translated );

                    if ( $orig_numbers_count > 0 && $orig_numbers_count === $trans_numbers_count ) {
                        // The user provided matching numbers to establish a layout template
                        $normalized_key              = preg_replace( '/\p{N}+/u', '::NUM::', $original );
                        $translated_with_placeholder = preg_replace( '/\p{N}+/u', '{NUM}', $translated );

                        if ( ! isset( $map[ $normalized_key ] ) ) {
                            $map[ $normalized_key ] = $translated_with_placeholder;
                        }
                    } else {
                        // User omitted the number (wanted to translate just text pieces) or counts mismatched.
                        $original_text   = trim( $this->get_only_text_from_transation( $original ) );
                        $translated_text = trim( $this->get_only_text_from_transation( $translated ) );

                        if ( ! empty( $original_text ) && ! empty( $translated_text ) ) {
                            $map[ $original_text ] = $translated_text;
                        }
                    }

                    // Do not keep exact mapping when number translation is disabled, 
                    // otherwise it causes a double-replacement resulting in duplicated words.
                    continue;
                }
                
                if (empty($original) || empty($translated)) {
                    continue;
                }
                
                $map[$original] = $translated;
            }
        }

        return $map;
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


    /**
     * Build translation map for a specific slug and language with proper validation
     * Used by gettext hooks for efficient lookups
     */
    protected function get_single_language_map($slug, $language) {
        if (empty($slug) || empty($language)) {
            return array();
        }

        $cache_key = "livelang_translations_{$language}_{$slug}";
        $cache_group = 'livelang';
        
        // Try cache first
        $map = wp_cache_get($cache_key, $cache_group);
        
        if ($map !== false) {
            return is_array($map) ? $map : array();
        }

        // Build map from database with comprehensive validation
        $map = array();
        $translations = $this->db->get_translations_for_slug($slug, $language);
        
        if (!empty($translations) && is_array($translations)) {
            foreach ($translations as $row) {
                // Comprehensive type validation
                if (!is_object($row)) {
                    continue;
                }
                
                if (empty($row->original_text) || empty($row->translated_text)) {
                    continue;
                }
                
                // Cast and trim both values
                $original = trim((string) $row->original_text);
                $translated = trim((string) $row->translated_text);
                
                // Skip if either is empty after trimming
                if (empty($original) || empty($translated)) {
                    continue;
                }
                
                $map[$original] = $translated;
            }
        }
        
        // Cache the map (even if empty)
        wp_cache_set(
            $cache_key,
            $map,
            $cache_group,
            apply_filters('livelang_cache_ttl', 12 * HOUR_IN_SECONDS)
        );

        return $map;
    }

    public function translate_gettext($translated, $text, $domain) {
        // Prevent recursion FIRST (before anything else)
        if (self::$in_translation_hook) {
            return $translated;
        }

        // Early exits prevent unnecessary processing
        if (empty($text)) {
            return $translated;
        }

        if (is_admin() || defined('REST_REQUEST') || defined('DOING_AJAX')) {
            return $translated;
        }

        // Set flag immediately
        self::$in_translation_hook = true;
        
        try {
            // Get language
            $language = $this->getCurrentLanguage();
            if (empty($language) || $language === $this->get_default_language()) {
                return $translated;
            }

            // Get slug
            $slug = $this->get_current_slug();
            if (empty($slug)) {
                $slug = 'home';
            }

            // Query database directly (simple one-liner)
            $translation = $this->db->get_translation_by_original_and_slug($text, $slug, $language);
            
            if ($translation && !empty($translation->translated_text)) {
                return $translation->translated_text;
            }
        } finally {
            self::$in_translation_hook = false;
        }

        return $translated;
    }

    public function translate_ngettext($translated, $single, $plural, $number, $domain) {
        // Prevent recursion FIRST
        if (self::$in_translation_hook) {
            return $translated;
        }

        // Early exits
        if (empty($single) && empty($plural)) {
            return $translated;
        }

        if (is_admin() || defined('REST_REQUEST') || defined('DOING_AJAX')) {
            return $translated;
        }

        // Set flag immediately
        self::$in_translation_hook = true;
        
        try {
            // Get language
            $language = $this->getCurrentLanguage();
            if (empty($language) || $language === $this->get_default_language()) {
                return $translated;
            }

            // Determine which text to use
            $check_text = ($number == 1) ? $single : $plural;
            if (empty($check_text)) {
                return $translated;
            }

            // Get slug
            $slug = $this->get_current_slug();
            if (empty($slug)) {
                $slug = 'home';
            }

            // Query database
            $translation = $this->db->get_translation_by_original_and_slug($check_text, $slug, $language);
            
            if ($translation && !empty($translation->translated_text)) {
                return $translation->translated_text;
            }
        } finally {
            self::$in_translation_hook = false;
        }

        return $translated;
    }

    public function translate_gettext_context($translated, $text, $context, $domain) {
        // Prevent recursion FIRST
        if (self::$in_translation_hook) {
            return $translated;
        }

        // Early exits
        if (empty($text) || empty($context)) {
            return $translated;
        }

        if (is_admin() || defined('REST_REQUEST') || defined('DOING_AJAX')) {
            return $translated;
        }

        // Set flag immediately
        self::$in_translation_hook = true;
        
        try {
            // Get language
            $language = $this->getCurrentLanguage();
            if (empty($language) || $language === $this->get_default_language()) {
                return $translated;
            }

            // Get slug
            $slug = $this->get_current_slug();
            if (empty($slug)) {
                $slug = 'home';
            }

            // Build context key
            $lookup_key = $context . '|' . $text;

            // Query database
            $translation = $this->db->get_translation_by_original_and_slug($lookup_key, $slug, $language);
            
            if ($translation && !empty($translation->translated_text)) {
                return $translation->translated_text;
            }
        } finally {
            self::$in_translation_hook = false;
        }

        return $translated;
    }

    /**
     * Debug helper - Check if translations exist in database for current page/language
     * Add to a temporary admin page to test
     */
    public function debug_translations() {
        if (!current_user_can('manage_options')) {
            return 'Access denied';
        }

        $slug = $this->get_current_slug();
        $language = $this->getCurrentLanguage();
        
        $output = "DEBUG INFO:<br>";
        $output .= "Current Slug: " . esc_html($slug) . "<br>";
        $output .= "Current Language: " . esc_html($language) . "<br>";
        
        if (defined('LIVELANG_CURRENT_LANG')) {
            $output .= "Constant LIVELANG_CURRENT_LANG: " . esc_html(LIVELANG_CURRENT_LANG) . "<br>";
        } else {
            $output .= "Constant LIVELANG_CURRENT_LANG: NOT DEFINED<br>";
        }

        // Add locale diagnostic info
        $output .= "<br><strong>LOCALE INFORMATION:</strong><br>";
        $locale_info = $this->get_locale_diagnostic_info();
        foreach ($locale_info as $key => $value) {
            if (is_array($value)) {
                $output .= esc_html($key) . ": " . implode(', ', array_map('esc_html', $value)) . "<br>";
            } else {
                $output .= esc_html($key) . ": " . esc_html((string)$value) . "<br>";
            }
        }
        
        // Check for stored debug info from initialization
        $init_debug = get_transient('livelang_debug_locale_' . $language);
        if ($init_debug) {
            $output .= "<br><strong>INITIALIZATION DEBUG:</strong><br>";
            foreach ($init_debug as $key => $value) {
                if (is_array($value)) {
                    $output .= esc_html($key) . ": " . implode(', ', array_map('esc_html', $value)) . "<br>";
                } else {
                    $output .= esc_html($key) . ": " . esc_html((string)$value) . "<br>";
                }
            }
        }

        $translations = $this->db->get_translations_for_slug($slug, $language);
        $output .= "<br><strong>Translations found in database: " . (is_array($translations) ? count($translations) : 0) . "</strong><br>";

        if (!empty($translations)) {
            $output .= "<br><strong>Sample translations:</strong><br>";
            foreach (array_slice($translations, 0, 10) as $row) {
                $output .= "<br>Row Object: " . print_r($row, true);
                $output .= "Original: '" . esc_html((string)$row->original_text) . "'<br>";
                $output .= "Translated: '" . esc_html((string)$row->translated_text) . "'<br>";
                $output .= "Slug: '" . esc_html((string)$row->slug) . "'<br>";
                $output .= "Language: '" . esc_html((string)$row->language) . "'<br>";
                $output .= "---<br>";
            }
        } else {
            $output .= "<strong>❌ No translations found in database!</strong><br>";
        }

        $output .= "<br><strong>Map Data:</strong><br>";
        $map = $this->get_translations_map($slug, $language);
        $output .= "Map Count: " . count($map) . "<br>";
        if (!empty($map)) {
            $output .= "Sample Map Entries (first 5):<br>";
            foreach (array_slice($map, 0, 5, true) as $original => $translated) {
                $output .= "'" . esc_html((string)$original) . "' => '" . esc_html((string)$translated) . "'<br>";
            }
        }

        $output .= "<br><strong>Cache Status:</strong><br>";
        $cache_key = "livelang_translations_{$language}_{$slug}";
        $cached = wp_cache_get($cache_key, 'livelang');
        $output .= "Cache Key: " . esc_html($cache_key) . "<br>";
        $output .= "Cached Data: " . ($cached === false ? "NOT CACHED" : "CACHED (" . count($cached) . " items)") . "<br>";

        return "<pre>" . $output . "</pre>";
    }

    protected function get_only_text_from_transation ($text) {
        return preg_replace('/\p{N}/u', '', $text);
    }

    protected function get_only_number_from_transation ($text) {
        return preg_replace('/[^\p{N}]/u', '', $text);
    }


    protected function get_current_slug() {
        global $wp;
        if (!isset($wp) || !isset($wp->request)) {
            return 'home';
        }
        
        // Get raw slug without calling functions that might trigger hooks
        $slug = trim($wp->request, '/');
        
        if (empty($slug)) {
            return 'home';
        }
        
        // Remove language prefix if present (simple string operation, no function calls)
        $language = $this->getCurrentLanguage();
        if (!empty($language) && $language !== $this->get_default_language()) {
            $prefix = $language . '/';
            if (strpos($slug, $prefix) === 0) {
                $slug = substr($slug, strlen($prefix));
            } elseif ($slug === $language) {
                $slug = '';
            }
        }
        
        return empty($slug) ? 'home' : $slug;
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

    public function livelang_rest_url( $path ) {
        $base = site_url(); // safer than home_url
        return $base . '/?rest_route=/' . ltrim( $path, '/' );
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
                array( 'livelang-frontend' ),
                LIVELANG_VERSION,
                true
        );
        }

        $slug         = $this->get_current_slug();
        $language     = $this->getCurrentLanguage();
        $default_lang = $this->get_default_language();
        $translations = $this->db->get_translations_for_slug( $slug, $language );

        $dict = array();
        if ( $translations ) {
            foreach ( $translations as $row ) {
                $dict[] = array(
                    'original'   => $row->original_text,
                    'translated' => $row->translated_text,
                );
            }
        }

        $rest_url = $this->livelang_rest_url( 'livelang-api/v1' );
        if ( is_ssl() ) {
            $rest_url = set_url_scheme( $rest_url, 'https' );
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
                'restUrl' => untrailingslashit( esc_url_raw( $rest_url ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'enable_translation' => $this->is_enabled_for_user(),
                'slug'    => $slug,
                'currentLanguage' => $this->getCurrentLanguage(),
                'defaultLanguage' => $this->get_default_language(),
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

    public function filter_url( $url ) {
        if ( is_admin() && !wp_doing_ajax() ) {
            return $url;
        }

        // Ignore empty or fragment URLs
        if ( empty( $url ) || strpos( $url, '#' ) === 0 ) {
            return $url;
        }

        $home_url = get_option('home');
        if ( strpos( $url, rtrim( $home_url, '/' ) ) !== 0 ) {
            return $url;
        }

        // Avoid adding prefix to wp-login, wp-admin, ajax
        if ( strpos( $url, 'wp-login.php' ) !== false || strpos( $url, 'wp-admin' ) !== false || strpos( $url, 'admin-ajax.php' ) !== false ) {
            return $url;
        }

        $current_lang = $this->getCurrentLanguage();
        $default_lang = $this->get_default_language();

        if ( empty( $current_lang ) || $current_lang === $default_lang ) {
            return $url;
        }

        $home_url_slash = trailingslashit( $home_url );
        $path = str_replace( $home_url_slash, '', $url );
        // Also check unslashed just in case
        $path = str_replace( $home_url, '', $path );

        // If it starts with the valid language prefix, skip
        if ( preg_match( '#^/?' . preg_quote( $current_lang, '#' ) . '(/|$)#', $path ) ) {
            return $url;
        }

        return $home_url_slash . $current_lang . '/' . ltrim( $path, '/' );
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
