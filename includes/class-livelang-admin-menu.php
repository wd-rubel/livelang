<?php
add_action('admin_init', 'add_language_switcher_metabox');

function add_language_switcher_metabox() {
    add_meta_box(
        'livelang-custom-switcher',
        __('Language Switcher', 'livelang'),
        'render_language_switcher_metabox',
        'nav-menus',
        'side',
        'default'
    );
}

function render_language_switcher_metabox() {
    global $_nav_menu_placeholder, $nav_menu_selected_id;
    ?>
    <div id="livelang-switcher" class="posttypediv">
        <div id="tabs-panel-livelang-switcher" class="tabs-panel tabs-panel-active">
            <ul id="livelang-switcher-checklist" class="categorychecklist form-no-clear">
                <li>
                    <label class="menu-item-title">
                        <input type="checkbox" class="menu-item-checkbox"
                               name="menu-item[-1][menu-item-object-id]" value="-1">
                        <?php esc_html_e( 'Language Switcher', 'livelang' ); ?>
                    </label>

                    <input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom">
                    <input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="Language Switcher">
                    <input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="#livelang_switcher">
                    <input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="livelang-menu-item">
                </li>
            </ul>
        </div>
        <p class="button-controls">
            <span class="add-to-menu">
                <button type="submit"
                        class="button-secondary submit-add-to-menu right"
                        id="submit-livelang-switcher"
                        name="add-livelang-menu-item">
                    <?php esc_html_e( 'Add to Menu', 'livelang' ); ?>
                </button>
                <span class="spinner"></span>
            </span>
        </p>
    </div>
    <?php
}

add_filter('wp_nav_menu_objects', 'render_language_switcher_menu_item', 10, 2);

function render_language_switcher_menu_item($items, $args) {
    foreach ($items as $item) {
        // Match by title or URL hash we set above
        if ( $item->title === 'Language Switcher' && $item->url === '#livelang_switcher' ) {
            // Add a class so we can identify it in the walker
            $item->classes[] = 'menu-item-language-switcher';
        }
    }
    return $items;
}

add_filter( 'walker_nav_menu_start_el', 'livelang_menu_item_output', 10, 4 );

function livelang_menu_item_output( $item_output, $item, $depth, $args ) {
    // Check if this is our language switcher menu item
    if ( in_array( 'menu-item-language-switcher', (array) $item->classes ) ) {
        return do_shortcode('[livelang_language_switcher]');
    }
    return $item_output;
}
