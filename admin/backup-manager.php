<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Backup_Manager {

    public function __construct() {
        add_action( 'admin_post_wprg_full_backup', array( $this, 'manual_backup_download' ) );
        add_action( 'wprg_daily_auto_backup_event', array( $this, 'run_auto_backup_task' ) );
        add_action( 'admin_post_wprg_full_restore', array( $this, 'manual_backup_restore' ) );
    }

    /**
     * Hàm gom toàn bộ dữ liệu của Plugin
     */
    private function get_all_plugin_data() {
        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';

        $data = array(
            'plugin'      => 'wp-redirect-gateway',
            'version'     => defined('WPRG_VERSION') ? WPRG_VERSION : '1.0.0',
            'backup_date' => current_time( 'Y-m-d H:i:s' ),
            'settings'    => array(),
            'links'       => array(),
            'logs'        => array()
        );

        // 1. Lấy Settings
        $option_keys = array( 
            'wprg_affiliate_links', 
            'wprg_require_active_tab', 
            'wprg_single_link_mode', 
            'wprg_recaptcha_site', 
            'wprg_recaptcha_secret', 
            'wprg_delete_data', 
            'wprg_enable_initial_click', 
            'wprg_initial_links',
            'wprg_enable_auto_backup', // Lưu luôn trạng thái bật/tắt Cron
            'wprg_shortcodes'          // <--- BỔ SUNG DỮ LIỆU SHORTCODE VÀO ĐÂY
        );
        
        foreach ( $option_keys as $key ) {
            $data['settings'][$key] = get_option( $key );
        }

        // 2. Lấy Database Links & Logs
        $data['links'] = $wpdb->get_results( "SELECT * FROM $table_links", ARRAY_A );
        $data['logs']  = $wpdb->get_results( "SELECT * FROM $table_logs", ARRAY_A );

        return wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
    }

    /**
     * Xử lý khi người dùng bấm nút "Tải Backup"
     */
    public function manual_backup_download() {
        if ( ! isset( $_POST['wprg_backup_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_backup_nonce'], 'wprg_backup_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );

        $json_data = $this->get_all_plugin_data();
        $filename = 'wprg-full-backup-' . date( 'Y-m-d-H-i' ) . '.json';

        if ( ob_get_length() ) ob_end_clean();
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        echo $json_data;
        exit;
    }

    /**
     * Xử lý Khôi phục (Restore) toàn bộ dữ liệu
     */
    public function manual_backup_restore() {
        if ( ! isset( $_POST['wprg_restore_nonce'] ) || ! wp_verify_nonce( $_POST['wprg_restore_nonce'], 'wprg_restore_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
        if ( empty( $_FILES['wprg_restore_file']['tmp_name'] ) ) wp_die( esc_html__( 'Vui lòng chọn file hợp lệ.', 'wp-redirect-gateway' ) );

        $file_content = file_get_contents( $_FILES['wprg_restore_file']['tmp_name'] );
        $decoded_data = json_decode( $file_content, true );

        // Kiểm tra tính hợp lệ của file Backup
        if ( ! $decoded_data || ! isset( $decoded_data['plugin'] ) || $decoded_data['plugin'] !== 'wp-redirect-gateway' ) {
            wp_die( esc_html__( 'File không hợp lệ hoặc không phải file Full Backup của plugin WP Redirect Gateway.', 'wp-redirect-gateway' ) );
        }

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';

        // 1. Phục hồi Cài đặt (Settings)
        if ( isset( $decoded_data['settings'] ) && is_array( $decoded_data['settings'] ) ) {
            foreach ( $decoded_data['settings'] as $key => $value ) {
                update_option( $key, $value );
            }
        }

        // 2. Phục hồi Links (Xóa bảng cũ, chèn data mới)
        if ( isset( $decoded_data['links'] ) && is_array( $decoded_data['links'] ) ) {
            $wpdb->query("TRUNCATE TABLE $table_links"); // Xóa sạch dữ liệu cũ
            foreach ( $decoded_data['links'] as $link ) {
                $wpdb->insert( $table_links, $link );
            }
        }

        // 3. Phục hồi Logs (Xóa bảng cũ, chèn data mới)
        if ( isset( $decoded_data['logs'] ) && is_array( $decoded_data['logs'] ) ) {
            $wpdb->query("TRUNCATE TABLE $table_logs"); // Xóa sạch dữ liệu cũ
            foreach ( $decoded_data['logs'] as $log ) {
                $wpdb->insert( $table_logs, $log );
            }
        }

        // Báo thành công
        $redirect_url = wp_get_referer();
        $redirect_url = add_query_arg( 'wprg_restore_success', '1', $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Xử lý Cron Job chạy ngầm trên Server
     */
    public function run_auto_backup_task() {
        // Kiểm tra xem user có bật tính năng này trong cài đặt không
        if ( get_option( 'wprg_enable_auto_backup' ) !== '1' ) return;

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wprg-backups';

        // Tạo thư mục nếu chưa có và bảo vệ nó khỏi việc tải trộm
        if ( ! file_exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
            file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
            file_put_contents( $backup_dir . '/.htaccess', 'deny from all' );
        }

        $json_data = $this->get_all_plugin_data();
        $filename = 'wprg-autobackup-' . date( 'Y-m-d' ) . '.json';
        file_put_contents( $backup_dir . '/' . $filename, $json_data );

        // (Tùy chọn) Xóa các bản backup cũ để nhẹ server, chỉ giữ lại 7 bản gần nhất
        $files = glob( $backup_dir . '/*.json' );
        if ( count( $files ) > 7 ) {
            usort( $files, function( $a, $b ) { return filemtime( $a ) - filemtime( $b ); } );
            $files_to_delete = array_slice( $files, 0, count( $files ) - 7 );
            foreach ( $files_to_delete as $file ) {
                unlink( $file );
            }
        }
    }
}
new WPRG_Backup_Manager();