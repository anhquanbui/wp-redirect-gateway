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
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wprg_gateway_nonce' ) ) {
            wp_send_json_error( 'Lỗi bảo mật (Nonce invalid).' );
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
        $pass = isset( $_POST['password'] ) ? sanitize_text_field( $_POST['password'] ) : '';

        if ( empty( $slug ) || empty( $pass ) ) wp_send_json_error( 'Vui lòng nhập đầy đủ thông tin.' );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT password FROM $table_links WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {
            if ( $pass === $link_data['password'] ) {
                // Nếu đúng, trả về thông tin để Javascript tạo Cookie
                wp_send_json_success( array( 
                    'cookie_name'  => 'wprg_unlock_' . md5($slug),
                    'cookie_value' => md5($pass)
                ) );
            } else {
                wp_send_json_error( 'Mật khẩu không chính xác! Vui lòng thử lại.' );
            }
        } else {
            wp_send_json_error( 'Link không tồn tại.' );
        }
    }

    // --- HÀM CŨ: LẤY LINK VÀ GHI LOG ---
    public function get_final_link() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wprg_gateway_nonce' ) ) {
            wp_send_json_error( 'Lỗi bảo mật (Nonce invalid).' );
        }

        $recap_secret = get_option( 'wprg_recaptcha_secret', '' );
        if ( ! empty( $recap_secret ) ) {
            $token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( $_POST['recaptcha_token'] ) : '';
            if ( empty( $token ) ) wp_send_json_error( 'Hệ thống yêu cầu mã xác thực chống BOT.' );

            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
            $response = wp_remote_post( $verify_url, array(
                'body' => array(
                    'secret'   => $recap_secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                )
            ));

            if ( is_wp_error( $response ) ) wp_send_json_error( 'Không thể kết nối đến Google reCAPTCHA.' );
            $body = wp_remote_retrieve_body( $response );
            $result = json_decode( $body );

            if ( ! $result->success || $result->score < 0.5 ) {
                $score_text = isset($result->score) ? $result->score : 'N/A';
                wp_send_json_error( 'Hệ thống từ chối truy cập vì nghi ngờ bạn là Robot. (Score: ' . $score_text . ')' );
            }
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
        if ( empty( $slug ) ) wp_send_json_error( 'Thiếu dữ liệu link.' );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';
        
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, original_url FROM $table_links WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {
            $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';
            $referrer = isset( $_POST['referrer'] ) && !empty( $_POST['referrer'] ) ? esc_url_raw( $_POST['referrer'] ) : 'Trực tiếp';
            
            if ( $referrer !== 'Trực tiếp' ) {
                $parsed_ref = wp_parse_url( $referrer );
                $ref_host = isset($parsed_ref['host']) ? str_replace('www.', '', strtolower($parsed_ref['host'])) : '';
                
                $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
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

            $sub_id = isset( $_POST['sub_id'] ) ? sanitize_text_field( $_POST['sub_id'] ) : '';
            $url_params = isset( $_POST['url_params'] ) ? sanitize_textarea_field( $_POST['url_params'] ) : '';
            $log_id = isset( $_POST['log_id'] ) ? intval( $_POST['log_id'] ) : 0;

            if ( $log_id > 0 ) {
                $wpdb->update( 
                    $table_logs, 
                    array( 'sub_id' => $sub_id, 'url_params' => $url_params, 'status' => 'completed' ), 
                    array( 'id' => $log_id ) 
                );
            } else {
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
            wp_send_json_error( 'Không tìm thấy link trong cơ sở dữ liệu.' );
        }
    }
}
new WPRG_Frontend_Ajax();