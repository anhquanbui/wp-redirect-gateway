<?php
/**
 * Plugin Name: WP Redirect Gateway & Ads Monetization
 * Description: Plugin quản lý link redirect, gateway ads, đếm ngược thời gian, chống bot và thống kê log chi tiết.
 * Version:     1.0.0
 * Author:      Anh Quan Bui
 * Text Domain: wp-redirect-gateway
 * Domain Path: /languages
 */

// 1. BẢO MẬT: Ngăn chặn truy cập trực tiếp vào file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. ĐỊNH NGHĨA HẰNG SỐ (Constants)
define( 'WPRG_VERSION', '1.0.0' );
define( 'WPRG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPRG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 3. Load Text Domain để hỗ trợ đa ngôn ngữ
 */
function wprg_load_textdomain() {
    load_plugin_textdomain( 
        'wp-redirect-gateway', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages/' 
    );
}
add_action( 'plugins_loaded', 'wprg_load_textdomain' );

/**
 * 4. Tạo Database khi kích hoạt plugin (Activation Hook)
 */
function wprg_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // TẠO BẢNG LINKS
    $table_links = $wpdb->prefix . 'rg_links';
    $sql_links = "CREATE TABLE $table_links (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        original_url text NOT NULL,
        slug varchar(255) NOT NULL,
        ad_count int(11) DEFAULT 0,
        wait_time varchar(255) DEFAULT '', 
        password varchar(255) DEFAULT '', 
        shortcode_id varchar(100) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

    // TẠO BẢNG LOGS
    $table_logs = $wpdb->prefix . 'rg_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        link_id mediumint(9) NOT NULL,
        ip_address varchar(100) NOT NULL,
        user_agent text,
        referrer text,
        sub_id varchar(255) DEFAULT '',
        url_params text,
        status varchar(50) DEFAULT 'click',
        clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Gọi thư viện upgrade của WordPress để chạy lệnh an toàn
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_links );
    dbDelta( $sql_logs );

    // Khởi tạo các tùy chọn mặc định nếu chưa có
    add_option( 'wprg_require_active_tab', '1' );
    add_option( 'wprg_single_link_mode', '0' );
    add_option( 'wprg_enable_initial_click', '1' );
}
register_activation_hook( __FILE__, 'wprg_activate_plugin' );

/**
 * 5. Nạp các module (Modules)
 */
require_once WPRG_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once WPRG_PLUGIN_DIR . 'admin/backup-manager.php';
require_once WPRG_PLUGIN_DIR . 'public/class-gateway-logic.php';
require_once WPRG_PLUGIN_DIR . 'public/class-shortcode-gateway.php'; 
require_once WPRG_PLUGIN_DIR . 'public/class-shortcode-inline.php';  
require_once WPRG_PLUGIN_DIR . 'public/class-frontend-ajax.php';

/**
 * 6. XỬ LÝ IMPORT / EXPORT CÀI ĐẶT
 */

// --- XỬ LÝ EXPORT JSON ---
add_action( 'admin_post_wprg_export_settings', 'wprg_handle_export_settings' );
function wprg_handle_export_settings() {
    if ( ! isset( $_POST['wprg_export_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_export_nonce'], 'wprg_export_nonce_action' ) ) {
        wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'wp-redirect-gateway' ) );
    }

    $option_keys = array(
        'wprg_affiliate_links',
        'wprg_require_active_tab',
        'wprg_single_link_mode',
        'wprg_recaptcha_site',
        'wprg_recaptcha_secret',
        'wprg_delete_data',
        'wprg_enable_initial_click',
        'wprg_initial_links',
        'wprg_shortcodes' // Đã giữ nguyên Shortcodes
    );

    $export_data = array(
        'plugin'      => 'wp-redirect-gateway',
        'version'     => WPRG_VERSION,
        'export_date' => current_time( 'Y-m-d H:i:s' ),
        'settings'    => array()
    );

    foreach ( $option_keys as $key ) {
        $export_data['settings'][$key] = get_option( $key );
    }

    $filename = 'wprg-settings-' . date( 'Y-m-d' ) . '.json';
    
    // Khắc phục lỗi trắng trang
    if ( ob_get_length() ) {
        ob_end_clean();
    }

    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
}

// --- XỬ LÝ IMPORT JSON ---
add_action( 'admin_post_wprg_import_settings', 'wprg_handle_import_settings' );
function wprg_handle_import_settings() {
    if ( ! isset( $_POST['wprg_import_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_import_nonce'], 'wprg_import_nonce_action' ) ) {
        wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Bạn không có quyền thực hiện hành động này.', 'wp-redirect-gateway' ) );
    }
    if ( empty( $_FILES['wprg_import_file']['tmp_name'] ) ) {
        wp_die( esc_html__( 'Vui lòng chọn file hợp lệ.', 'wp-redirect-gateway' ) );
    }

    $file_content = file_get_contents( $_FILES['wprg_import_file']['tmp_name'] );
    $decoded_data = json_decode( $file_content, true );

    if ( ! $decoded_data || ! isset( $decoded_data['plugin'] ) || $decoded_data['plugin'] !== 'wp-redirect-gateway' ) {
        wp_die( esc_html__( 'File không hợp lệ hoặc không phải của plugin WP Redirect Gateway.', 'wp-redirect-gateway' ) );
    }

    if ( isset( $decoded_data['settings'] ) && is_array( $decoded_data['settings'] ) ) {
        foreach ( $decoded_data['settings'] as $key => $value ) {
            update_option( $key, $value );
        }
    }

    $redirect_url = wp_get_referer();
    $redirect_url = add_query_arg( 'wprg_import_success', '1', $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}

// --- XỬ LÝ EXPORT LINKS RA CSV ---
add_action( 'admin_post_wprg_export_links_csv', 'wprg_handle_export_links_csv' );
function wprg_handle_export_links_csv() {
    if ( ! isset( $_POST['wprg_export_links_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_export_links_nonce'], 'wprg_export_links_action' ) ) {
        wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
    }

    global $wpdb;
    $table_links = $wpdb->prefix . 'rg_links';
    $links = $wpdb->get_results( "SELECT * FROM $table_links ORDER BY id DESC", ARRAY_A );

    if ( ob_get_length() ) ob_end_clean(); // Xóa đệm tránh lỗi file

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="wprg-links-' . date('Y-m-d') . '.csv"' );

    $output = fopen( 'php://output', 'w' );
    fputs( $output, "\xEF\xBB\xBF" ); // Ghi BOM để Excel đọc được Tiếng Việt có dấu

    // Đã bọc dịch cho dòng tiêu đề file CSV
    fputcsv( $output, array( 
        __( 'ID', 'wp-redirect-gateway' ), 
        __( 'Tên Link', 'wp-redirect-gateway' ), 
        __( 'Link Gốc', 'wp-redirect-gateway' ), 
        __( 'Slug', 'wp-redirect-gateway' ), 
        __( 'Số QC', 'wp-redirect-gateway' ), 
        __( 'Thời gian chờ', 'wp-redirect-gateway' ), 
        __( 'Mật khẩu', 'wp-redirect-gateway' ), 
        __( 'Mã Gateway', 'wp-redirect-gateway' ), 
        __( 'Ngày tạo', 'wp-redirect-gateway' ) 
    ) );

    // Dữ liệu
    if ( ! empty( $links ) ) {
        foreach ( $links as $link ) {
            fputcsv( $output, array(
                $link['id'], $link['name'], $link['original_url'], $link['slug'],
                $link['ad_count'], $link['wait_time'], $link['password'], $link['shortcode_id'], $link['created_at']
            ));
        }
    }
    fclose( $output );
    exit;
}

// --- XỬ LÝ EXPORT LOGS RA CSV ---
add_action( 'admin_post_wprg_export_logs_csv', 'wprg_handle_export_logs_csv' );
function wprg_handle_export_logs_csv() {
    if ( ! isset( $_POST['wprg_export_logs_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_export_logs_nonce'], 'wprg_export_logs_action' ) ) {
        wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
    }

    global $wpdb;
    $table_logs = $wpdb->prefix . 'rg_logs';
    $table_links = $wpdb->prefix . 'rg_links';

    // Xử lý nếu người dùng đang lọc theo tháng
    $where = "";
    $filename_suffix = "all";
    if ( ! empty( $_POST['filter_month'] ) ) {
        $month = sanitize_text_field( $_POST['filter_month'] );
        $where = $wpdb->prepare( "WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s", $month );
        $filename_suffix = $month;
    }

    $logs = $wpdb->get_results( "
        SELECT lg.*, lk.name as link_name 
        FROM $table_logs lg 
        LEFT JOIN $table_links lk ON lg.link_id = lk.id 
        $where ORDER BY lg.clicked_at DESC
    ", ARRAY_A );

    if ( ob_get_length() ) ob_end_clean();

    header( 'Content-Type: text/csv; charset=utf-8' );
    // Đã sửa triệt để lỗi dư dấu ngoặc kép ở filename
    header( 'Content-Disposition: attachment; filename="wprg-logs-' . $filename_suffix . '-' . date('Y-m-d') . '.csv"' );

    $output = fopen( 'php://output', 'w' );
    fputs( $output, "\xEF\xBB\xBF" ); // BOM Tiếng Việt

    // Đã bọc dịch cho tiêu đề file CSV Logs
    fputcsv( $output, array( 
        __( 'Thời gian', 'wp-redirect-gateway' ), 
        __( 'Trạng thái', 'wp-redirect-gateway' ), 
        __( 'Tên Link', 'wp-redirect-gateway' ), 
        __( 'Sub-ID', 'wp-redirect-gateway' ), 
        __( 'Tham số UTM', 'wp-redirect-gateway' ), 
        __( 'IP', 'wp-redirect-gateway' ), 
        __( 'Nguồn (Referrer)', 'wp-redirect-gateway' ), 
        __( 'Thiết bị (User Agent)', 'wp-redirect-gateway' ) 
    ) );

    if ( ! empty( $logs ) ) {
        foreach ( $logs as $log ) {
            // Dịch trạng thái bên trong file CSV luôn
            $status = ( $log['status'] === 'completed' ) ? __( 'Hoàn thành', 'wp-redirect-gateway' ) : __( 'Truy cập', 'wp-redirect-gateway' );
            fputcsv( $output, array(
                $log['clicked_at'], $status, $log['link_name'], $log['sub_id'],
                $log['url_params'], $log['ip_address'], $log['referrer'], $log['user_agent']
            ));
        }
    }
    fclose( $output );
    exit;
}

/**
 * LÊN LỊCH CRON JOB (Chạy 1 lần/ngày)
 */
if ( ! wp_next_scheduled( 'wprg_daily_auto_backup_event' ) ) {
    wp_schedule_event( time(), 'daily', 'wprg_daily_auto_backup_event' );
}

// Xóa lịch Cron khi người dùng hủy kích hoạt (Deactivate) plugin
register_deactivation_hook( __FILE__, 'wprg_deactivate_plugin' );
function wprg_deactivate_plugin() {
    wp_clear_scheduled_hook( 'wprg_daily_auto_backup_event' );
}