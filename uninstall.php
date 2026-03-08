<?php
// Chống truy cập trực tiếp
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Kiểm tra xem user có tick chọn "Xóa toàn bộ dữ liệu" không
$delete_data = get_option( 'wprg_delete_data', 'no' );

if ( $delete_data === 'yes' ) {
    global $wpdb;

    // 1. DROP các bảng
    $table_links = $wpdb->prefix . 'rg_links';
    $table_logs  = $wpdb->prefix . 'rg_logs';
    $wpdb->query( "DROP TABLE IF EXISTS $table_links" );
    $wpdb->query( "DROP TABLE IF EXISTS $table_logs" );

    // 2. Xóa sạch mọi Cài đặt (Bao gồm cả bộ nhớ thông báo)
    $options_to_delete = array(
        'wprg_affiliate_links',
        'wprg_require_active_tab',
        'wprg_single_link_mode',
        'wprg_recaptcha_site',
        'wprg_recaptcha_secret',
        'wprg_delete_data',
        'wprg_enable_initial_click',
        'wprg_initial_links',
        'wprg_shortcodes',
        'wprg_hide_conflict_notice', // Bộ nhớ thông báo cũ
        'wprg_hide_conflict_hash',   // Bộ nhớ thông báo Hash
        'wprg_dismissed_conflicts'   // Bộ nhớ thông báo phân mảnh
    );

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }
}