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
    // Follow Polylang's way of generating placeholder ID
    $_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
    ?>
    <div id="livelang-switcher" class="posttypediv">
        <div id="tabs-panel-livelang-switcher" class="tabs-panel tabs-panel-active">
            <ul id="livelang-switcher-checklist" class="categorychecklist form-no-clear">
                <li>
                    <label class="menu-item-title">
                        <input type="checkbox" class="menu-item-checkbox"
                               name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-object-id]" value="-1">
                        <?php esc_html_e( 'Language Switcher', 'livelang' ); ?>
                    </label>

                    <input type="hidden" class="menu-item-type" name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-type]" value="custom">
                    <input type="hidden" class="menu-item-title" name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-title]" value="Language Switcher">
                    <input type="hidden" class="menu-item-url" name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-url]" value="#livelang_switcher">
                    <input type="hidden" class="menu-item-classes" name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-classes]" value="menu-item-language-switcher">
                </li>
            </ul>
        </div>
        <p class="button-controls">
            <span class="add-to-menu">
                <input type="submit" <?php disabled( $nav_menu_selected_id, 0 ); ?> 
                       class="button-secondary submit-add-to-menu right" 
                       value="<?php esc_attr_e( 'Add to Menu', 'livelang' ); ?>" 
                       name="add-post-type-menu-item" id="submit-livelang-switcher">
                <span class="spinner"></span>
            </span>
        </p>
    </div>
    <?php
}