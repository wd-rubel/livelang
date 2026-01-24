<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LiveLang_DB {

    public $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'livelang_translations';
    }

    public function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            original_text LONGTEXT NOT NULL,
            translated_text LONGTEXT NOT NULL,
            slug VARCHAR(190) NOT NULL,
            language VARCHAR(10) NOT NULL DEFAULT '',
            is_global TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY slug (slug),
            KEY language (language),
            KEY is_global (is_global),
            KEY status (status)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    public function get_translations_for_slug( $slug, $language = '' ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $language ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE (slug = %s OR is_global = 1) AND (language = %s OR is_global = 1) AND status = 'active'", $slug, $language );
        } else {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE (slug = %s OR is_global = 1) AND status = 'active'", $slug );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    public function get_translation_by_original_and_slug( $original_text, $slug, $language = '' ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $language ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE original_text = %s AND (slug = %s OR is_global = 1) AND (language = %s OR is_global = 1) AND status = 'active' LIMIT 1", $original_text, $slug, $language );
        } else {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE original_text = %s AND (slug = %s OR is_global = 1) AND status = 'active' LIMIT 1", $original_text, $slug );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row( $sql );
    }

    public function get_translations_for_global() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table} WHERE is_global = 1 AND status = 'active'" );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    public function get_all( $limit = 200 ) {
        global $wpdb;
        $limit = intval( $limit );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql   = $wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $sql );
    }

    public function insert( $data ) {
        global $wpdb;

        $defaults = array(
            'original_text'   => '',
            'translated_text' => '',
            'slug'            => '',
            'language'        => '',
            'is_global'       => 0,
            'status'          => 'active',
        );
        $data = wp_parse_args( $data, $defaults );
        $now  = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->table,
            array(
                'original_text'   => $data['original_text'],
                'translated_text' => $data['translated_text'],
                'slug'            => $data['slug'],
                'language'        => $data['language'],
                'is_global'       => (int) $data['is_global'],
                'status'          => $data['status'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    public function update( $id, $data ) {
        global $wpdb;

        $id   = intval( $id );
        $now  = current_time( 'mysql' );
        $data['updated_at'] = $now;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $this->table,
            $data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );
    }

    public function delete( $id ) {
        global $wpdb;
        $id = intval( $id );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
    }

    public function clear_all() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query( "TRUNCATE TABLE {$this->table}" );
        $this->clear_translation_cache();
    }

     public function clear_translation_cache( $slug = null, $language = null ) {

        if ( $slug && $language ) {
            wp_cache_delete(
                "livelang_translations_{$language}_{$slug}",
                'livelang'
            );
            return;
        }

        // Full flush (fallback)
        wp_cache_flush();
    }

}