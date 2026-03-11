<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Backup_Manager {

    public function __construct() {
        add_action( 'admin_post_wprg_full_backup', array( $this, 'manual_backup_download' ) );
        add_action( 'wprg_daily_auto_backup_event', array( $this, 'run_auto_backup_task' ) );
        add_action( 'admin_post_wprg_full_restore', array( $this, 'manual_backup_restore' ) );
        add_action( 'admin_post_wprg_auto_restore', array( $this, 'auto_backup_restore' ) );
        // API Xóa file Backup
        add_action( 'admin_post_wprg_delete_backup', array( $this, 'delete_backup_file' ) );
    }

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

        $option_keys = array( 
            'wprg_affiliate_links', 
            'wprg_require_active_tab', 
            'wprg_single_link_mode', 
            'wprg_recaptcha_site', 
            'wprg_recaptcha_secret', 
            'wprg_delete_data', 
            'wprg_enable_initial_click', 
            'wprg_initial_links',
            'wprg_enable_auto_backup', 
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
        
        foreach ( $option_keys as $key ) {
            $data['settings'][$key] = get_option( $key );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $data['links'] = $wpdb->get_results( "SELECT * FROM {$table_links}", ARRAY_A );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $data['logs']  = $wpdb->get_results( "SELECT * FROM {$table_logs}", ARRAY_A );

        return wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
    }

    public function manual_backup_download() {
        if ( ! isset( $_POST['wprg_backup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprg_backup_nonce'] ) ), 'wprg_backup_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );

        $json_data = $this->get_all_plugin_data();
        $filename = 'wprg-full-backup-' . gmdate( 'Y-m-d-H-i' ) . '.json';

        if ( ob_get_length() ) ob_end_clean();
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $json_data;
        exit;
    }

    public function manual_backup_restore() {
        if ( ! isset( $_POST['wprg_restore_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprg_restore_nonce'] ) ), 'wprg_restore_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
        if ( empty( $_FILES['wprg_restore_file']['tmp_name'] ) ) wp_die( esc_html__( 'Vui lòng chọn file hợp lệ.', 'wp-redirect-gateway' ) );

        $tmp_name = sanitize_text_field( wp_unslash( $_FILES['wprg_restore_file']['tmp_name'] ) );
        $file_content = file_get_contents( $tmp_name );
        
        $this->process_restore_data( $file_content );
    }

    public function auto_backup_restore() {
        if ( ! isset( $_POST['wprg_auto_restore_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprg_auto_restore_nonce'] ) ), 'wprg_auto_restore_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
        if ( empty( $_POST['backup_file'] ) ) wp_die( esc_html__( 'Thiếu tên file.', 'wp-redirect-gateway' ) );

        $filename = sanitize_file_name( wp_unslash( $_POST['backup_file'] ) );
        
        if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'json' || strpos( $filename, 'wprg-autobackup-' ) !== 0 ) {
            wp_die( esc_html__( 'File không hợp lệ.', 'wp-redirect-gateway' ) );
        }

        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/wprg-backups/' . $filename;

        if ( ! file_exists( $filepath ) ) {
            wp_die( esc_html__( 'File backup không tồn tại trên máy chủ.', 'wp-redirect-gateway' ) );
        }

        $file_content = file_get_contents( $filepath );
        $this->process_restore_data( $file_content );
    }

    private function process_restore_data( $file_content ) {
        $decoded_data = json_decode( $file_content, true );

        if ( ! $decoded_data || ! isset( $decoded_data['plugin'] ) || $decoded_data['plugin'] !== 'wp-redirect-gateway' ) {
            wp_die( esc_html__( 'File không hợp lệ hoặc không phải file Full Backup của plugin WP Redirect Gateway.', 'wp-redirect-gateway' ) );
        }

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';

        if ( isset( $decoded_data['settings'] ) && is_array( $decoded_data['settings'] ) ) {
            foreach ( $decoded_data['settings'] as $key => $value ) {
                update_option( $key, $value );
            }
        }

        if ( isset( $decoded_data['links'] ) && is_array( $decoded_data['links'] ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("TRUNCATE TABLE {$table_links}"); 
            foreach ( $decoded_data['links'] as $link ) { $wpdb->insert( $table_links, $link ); }
        }

        if ( isset( $decoded_data['logs'] ) && is_array( $decoded_data['logs'] ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("TRUNCATE TABLE {$table_logs}"); 
            foreach ( $decoded_data['logs'] as $log ) { $wpdb->insert( $table_logs, $log ); }
        }

        $redirect_url = wp_get_referer();
        $redirect_url = add_query_arg( 'wprg_restore_success', '1', $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function run_auto_backup_task() {
        if ( get_option( 'wprg_enable_auto_backup' ) !== '1' ) return;

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wprg-backups';

        if ( ! file_exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
            file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
            file_put_contents( $backup_dir . '/.htaccess', 'deny from all' );
        }

        $json_data = $this->get_all_plugin_data();
        $filename = 'wprg-autobackup-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
        file_put_contents( $backup_dir . '/' . $filename, $json_data );

        // Lấy giới hạn lưu trữ do người dùng cài đặt
        $backup_limit = intval( get_option( 'wprg_backup_limit', 7 ) );
        if ( $backup_limit < 1 ) $backup_limit = 1; // Luôn giữ tối thiểu 1 bản

        $files = glob( $backup_dir . '/*.json' );
        if ( count( $files ) > $backup_limit ) {
            // Sắp xếp file theo thời gian tăng dần (cũ nhất ở đầu)
            usort( $files, function( $a, $b ) { return filemtime( $a ) - filemtime( $b ); } );
            
            // Xóa các file cũ dư thừa
            $files_to_delete = array_slice( $files, 0, count( $files ) - $backup_limit );
            foreach ( $files_to_delete as $file ) { 
                wp_delete_file( $file ); 
            }
        }
    }

    public function delete_backup_file() {
        if ( ! isset( $_POST['wprg_delete_backup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprg_delete_backup_nonce'] ) ), 'wprg_delete_backup_action' ) ) wp_die( esc_html__( 'Lỗi bảo mật!', 'wp-redirect-gateway' ) );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Bạn không có quyền!', 'wp-redirect-gateway' ) );
        if ( empty( $_POST['backup_file'] ) ) wp_die( esc_html__( 'Thiếu tên file.', 'wp-redirect-gateway' ) );

        $filename = sanitize_file_name( wp_unslash( $_POST['backup_file'] ) );
        
        // Kiểm tra đúng định dạng file json của hệ thống mới cho xóa
        if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'json' || strpos( $filename, 'wprg-autobackup-' ) !== 0 ) {
            wp_die( esc_html__( 'File không hợp lệ.', 'wp-redirect-gateway' ) );
        }

        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/wprg-backups/' . $filename;

        if ( file_exists( $filepath ) ) {
            wp_delete_file( $filepath ); // Dùng hàm xóa chuẩn của WordPress
        }

        $redirect_url = wp_get_referer();
        $redirect_url = add_query_arg( 'wprg_delete_success', '1', $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}

new WPRG_Backup_Manager();