<?php
/**
 * Plugin Name: WP Redirect Gateway & Ads Monetization
 * Description: Plugin quản lý link redirect, gateway ads, đếm ngược thời gian, chống bot và thống kê log chi tiết. <br>🔗 <a href="https://quanbui.net" target="_blank" style="color:#0073aa; font-weight:500;">Visit my plugin</a> &nbsp;|&nbsp; 📖 <a href="https://quanbui.net/huong-dan" target="_blank" style="color:#0073aa; font-weight:500;">Document</a>
 * Version:     1.0.2
 * Author:      Anh Quan Bui
 * Text Domain: wp-redirect-gateway
 * Domain Path: /languages
 */

// 1. BẢO MẬT: Ngăn chặn truy cập trực tiếp vào file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. ĐỊNH NGHĨA HẰNG SỐ (Constants)
define( 'WPRG_VERSION', '1.0.2' );
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
        tag varchar(255) DEFAULT '',
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
        'wprg_shortcodes',
        'wprg_open_link_new_tab',
        'wprg_backup_time',
        'wprg_backup_limit',
        'wprg_rel_noopener',
        'wprg_rel_noreferrer',
        'wprg_captcha_type',
        'wprg_turnstile_site',
        'wprg_turnstile_secret'
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
        __( 'Nhãn (Tag)', 'wp-redirect-gateway' ),
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
            $tag_export = isset($link['tag']) ? $link['tag'] : '';
            fputcsv( $output, array(
                $link['id'], $link['name'], $tag_export, $link['original_url'], $link['slug'],
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
 * LÊN LỊCH CRON JOB THÔNG MINH (Chạy theo giờ tùy chỉnh)
 */

// Hàm tính toán và hẹn lại lịch Cron
function wprg_reschedule_backup_cron() {
    // 1. Xóa lịch cũ đi (nếu có)
    wp_clear_scheduled_hook( 'wprg_daily_auto_backup_event' );

    // 2. Nếu người dùng tắt Auto Backup thì dừng ở đây
    if ( get_option( 'wprg_enable_auto_backup', '0' ) !== '1' ) {
        return;
    }

    // 3. Lấy giờ người dùng đã cài (VD: '02:00')
    $time_string = get_option( 'wprg_backup_time', '00:00' );
    if ( empty( $time_string ) ) $time_string = '00:00';

    list( $hour, $minute ) = explode( ':', $time_string );

    // 4. Lấy múi giờ của Website (WP Settings > General)
    $timezone = wp_timezone();
    $now = new DateTime( 'now', $timezone );
    
    // Tạo 1 mốc thời gian chạy cho ngày hôm nay
    $next_run = clone $now;
    $next_run->setTime( (int)$hour, (int)$minute, 0 );

    // 5. Nếu giờ đó của ngày hôm nay đã trôi qua rồi -> Hẹn sang ngày mai
    if ( $next_run <= $now ) {
        $next_run->modify( '+1 day' );
    }

    // 6. Đặt lịch (Dùng getTimestamp() vì wp_schedule_event cần giờ UTC)
    wp_schedule_event( $next_run->getTimestamp(), 'daily', 'wprg_daily_auto_backup_event' );
}

// Hook đảm bảo hệ thống luôn tự hẹn giờ lại mỗi khi load nếu chưa có lịch
add_action( 'init', 'wprg_ensure_cron_is_scheduled' );
function wprg_ensure_cron_is_scheduled() {
    if ( get_option('wprg_enable_auto_backup') === '1' && ! wp_next_scheduled( 'wprg_daily_auto_backup_event' ) ) {
        wprg_reschedule_backup_cron();
    }
}

// Xóa lịch Cron khi người dùng hủy kích hoạt (Deactivate) plugin
register_deactivation_hook( __FILE__, 'wprg_deactivate_plugin' );
function wprg_deactivate_plugin() {
    wp_clear_scheduled_hook( 'wprg_daily_auto_backup_event' );
}

/**
 * 7. THÊM LINK VÀO TRANG QUẢN LÝ PLUGIN (Settings & Visit my website)
 */

// Thêm nút "Settings" kế bên chữ Deactivate
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wprg_add_settings_link' );
function wprg_add_settings_link( $links ) {
    // LƯU Ý: Nếu URL trang cài đặt của bạn không phải là ?page=wprg-settings thì bạn sửa chữ 'wprg-settings' ở dòng dưới cho khớp nhé.
    $settings_url = admin_url( 'admin.php?page=wprg-settings' ); 
    
    $settings_link = '<a href="' . esc_url( $settings_url ) . '" style="font-weight: bold; color: #2271b1;">' . esc_html__( 'Settings', 'wp-redirect-gateway' ) . '</a>';
    
    array_unshift( $links, $settings_link );
    
    return $links;
}