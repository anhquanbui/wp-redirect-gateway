<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Frontend_Ajax {
    public function __construct() {
        // API Lấy Link Đích
        add_action( 'wp_ajax_wprg_get_final_link', array( $this, 'get_final_link' ) );
        add_action( 'wp_ajax_nopriv_wprg_get_final_link', array( $this, 'get_final_link' ) );

        // [MỚI] API Kiểm tra mật khẩu (Dùng riêng cho Nút Inline)
        add_action( 'wp_ajax_wprg_verify_password', array( $this, 'verify_password' ) );
        add_action( 'wp_ajax_nopriv_wprg_verify_password', array( $this, 'verify_password' ) );
    }

    // --- HÀM MỚI: KIỂM TRA MẬT KHẨU ---
    public function verify_password() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wprg_gateway_nonce' ) ) {
            wp_send_json_error( __( 'Lỗi bảo mật (Nonce invalid).', 'redirect-gateway-manager' ) );
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
        $pass = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

        if ( empty( $slug ) || empty( $pass ) ) wp_send_json_error( __( 'Vui lòng nhập đầy đủ thông tin.', 'redirect-gateway-manager' ) );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT password FROM {$table_links} WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {
            if ( $pass === $link_data['password'] ) {
                // Nếu đúng, trả về thông tin để Javascript tạo Cookie
                wp_send_json_success( array( 
                    'cookie_name'  => 'wprg_unlock_' . md5($slug),
                    'cookie_value' => md5($pass)
                ) );
            } else {
                wp_send_json_error( __( 'Mật khẩu không chính xác! Vui lòng thử lại.', 'redirect-gateway-manager' ) );
            }
        } else {
            wp_send_json_error( __( 'Link không tồn tại.', 'redirect-gateway-manager' ) );
        }
    }

    // --- HÀM CŨ: LẤY LINK VÀ GHI LOG ---
    public function get_final_link() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wprg_gateway_nonce' ) ) {
            wp_send_json_error( __( 'Lỗi bảo mật (Nonce invalid).', 'redirect-gateway-manager' ) );
        }

        $captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
        $token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
        $remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        if ( $captcha_type === 'recaptcha' ) {
            $recap_secret = get_option( 'wprg_recaptcha_secret', '' );
            if ( ! empty( $recap_secret ) ) {
                if ( empty( $token ) ) wp_send_json_error( __( 'Hệ thống yêu cầu mã xác thực chống BOT.', 'redirect-gateway-manager' ) );
                $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
                    'body' => array( 'secret' => $recap_secret, 'response' => $token, 'remoteip' => $remote_ip )
                ));
                if ( is_wp_error( $response ) ) wp_send_json_error( __( 'Không thể kết nối đến Google reCAPTCHA.', 'redirect-gateway-manager' ) );
                $result = json_decode( wp_remote_retrieve_body( $response ) );
                if ( ! $result->success || $result->score < 0.5 ) {
                    wp_send_json_error( __( 'Hệ thống từ chối truy cập vì nghi ngờ bạn là Robot.', 'redirect-gateway-manager' ) );
                }
            }
        } elseif ( $captcha_type === 'turnstile' ) {
            $ts_secret = get_option( 'wprg_turnstile_secret', '' );
            if ( ! empty( $ts_secret ) ) {
                if ( empty( $token ) ) wp_send_json_error( __( 'Hệ thống yêu cầu mã xác thực Turnstile.', 'redirect-gateway-manager' ) );
                $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                    'body' => array( 'secret' => $ts_secret, 'response' => $token, 'remoteip' => $remote_ip )
                ));
                if ( is_wp_error( $response ) ) wp_send_json_error( __( 'Không thể kết nối đến Cloudflare.', 'redirect-gateway-manager' ) );
                $result = json_decode( wp_remote_retrieve_body( $response ) );
                if ( ! $result->success ) {
                    wp_send_json_error( __( 'Xác thực Cloudflare Turnstile thất bại.', 'redirect-gateway-manager' ) );
                }
            }
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
        if ( empty( $slug ) ) wp_send_json_error( __( 'Thiếu dữ liệu link.', 'redirect-gateway-manager' ) );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, original_url, password FROM {$table_links} WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {

            // ========================================================
            // [BẢN VÁ BẢO MẬT]: KIỂM TRA MẬT KHẨU (COOKIE) TẠI SERVER
            // ========================================================
            $password = isset($link_data['password']) ? $link_data['password'] : '';
            if ( ! empty( $password ) ) {
                $cookie_name = 'wprg_unlock_' . md5($slug);
                // Nếu khách không có Cookie hợp lệ -> Chặn đứng lập tức
                if ( ! isset($_COOKIE[$cookie_name]) || $_COOKIE[$cookie_name] !== md5($password) ) {
                    wp_send_json_error( __( 'Lỗi bảo mật: Bạn chưa mở khóa mật khẩu cho link này!', 'redirect-gateway-manager' ) );
                }
            }
            // ========================================================

            $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : $remote_ip;
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Unknown';
            $referrer = isset( $_POST['referrer'] ) && !empty( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : 'Trực tiếp';
            
            if ( $referrer !== 'Trực tiếp' ) {
                $parsed_ref = wp_parse_url( $referrer );
                $ref_host = isset($parsed_ref['host']) ? str_replace('www.', '', strtolower($parsed_ref['host'])) : '';
                
                $current_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (isset($_SERVER['SERVER_NAME']) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '');
                $home_host = str_replace('www.', '', strtolower($current_host));

                if ( $ref_host === $home_host || empty($ref_host) ) {
                    $path = isset($parsed_ref['path']) ? $parsed_ref['path'] : '';
                    $path = trim( $path, '/' );
                    $referrer = empty( $path ) ? 'Trang chủ' : '/' . $path;
                } else {
                    $referrer = preg_replace( '#^https?://(www\.)?#i', '', $referrer );
                    $referrer = rtrim( $referrer, '/' );
                }
            }

            $sub_id = isset( $_POST['sub_id'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_id'] ) ) : '';
            $url_params = isset( $_POST['url_params'] ) ? sanitize_textarea_field( wp_unslash( $_POST['url_params'] ) ) : '';
            $log_id = isset( $_POST['log_id'] ) ? intval( wp_unslash( $_POST['log_id'] ) ) : 0;

            if ( $log_id > 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update( 
                    $table_logs, 
                    array( 'sub_id' => $sub_id, 'url_params' => $url_params, 'status' => 'completed' ), 
                    array( 'id' => $log_id ) 
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert( 
                    $table_logs,
                    array(
                        'link_id'    => $link_data['id'],
                        'ip_address' => $ip,
                        'user_agent' => $user_agent,
                        'referrer'   => $referrer, 
                        'sub_id'     => $sub_id,
                        'url_params' => $url_params,
                        'status'     => 'completed',
                        'clicked_at' => current_time( 'mysql' )
                    )
                );
            }
            wp_send_json_success( array( 'url' => $link_data['original_url'] ) );
        } else {
            wp_send_json_error( __( 'Không tìm thấy link trong cơ sở dữ liệu.', 'redirect-gateway-manager' ) );
        }
    }
}
new WPRG_Frontend_Ajax();