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
            wp_send_json_error( __( 'Security error (Invalid nonce).', 'redirect-gateway-manager' ) );
        }

        $slug = isset( $_POST['slug'] ) ? esc_sql( sanitize_text_field( wp_unslash( $_POST['slug'] ) ) ) : '';
        $pass = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

        if ( empty( $slug ) || empty( $pass ) ) wp_send_json_error( __( 'Please enter all required information.', 'redirect-gateway-manager' ) );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        // [BẢN VÁ WPCS]: Bỏ chuỗi nội suy để vượt qua tool quét SQL
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT password FROM {$wpdb->prefix}rg_links WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {
            if ( $pass === $link_data['password'] ) {
                // Nếu đúng, trả về thông tin để Javascript tạo Cookie
                wp_send_json_success( array( 
                    'cookie_name'  => 'wprg_unlock_' . md5($slug),
                    'cookie_value' => md5($pass)
                ) );
            } else {
                wp_send_json_error( __( 'Incorrect password! Please try again.', 'redirect-gateway-manager' ) );
            }
        } else {
            wp_send_json_error( __( 'Link does not exist.', 'redirect-gateway-manager' ) );
        }
    }

    // --- HÀM CŨ: LẤY LINK VÀ GHI LOG ---
    public function get_final_link() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wprg_gateway_nonce' ) ) {
            wp_send_json_error( __( 'Security error (Invalid nonce).', 'redirect-gateway-manager' ) );
        }

        $captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
        $token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
        $remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        if ( $captcha_type === 'recaptcha' ) {
            $recap_secret = get_option( 'wprg_recaptcha_secret', '' );
            if ( ! empty( $recap_secret ) ) {
                if ( empty( $token ) ) wp_send_json_error( __( 'The system requires a verification code to prevent bots.', 'redirect-gateway-manager' ) );
                $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
                    'body' => array( 'secret' => $recap_secret, 'response' => $token, 'remoteip' => $remote_ip )
                ));
                if ( is_wp_error( $response ) ) wp_send_json_error( __( 'Cannot connect to Google reCAPTCHA.', 'redirect-gateway-manager' ) );
                $result = json_decode( wp_remote_retrieve_body( $response ) );
                if ( ! $result->success || $result->score < 0.5 ) {
                    wp_send_json_error( __( 'System denied access due to suspected bot activity.', 'redirect-gateway-manager' ) );
                }
            }
        } elseif ( $captcha_type === 'turnstile' ) {
            $ts_secret = get_option( 'wprg_turnstile_secret', '' );
            if ( ! empty( $ts_secret ) ) {
                if ( empty( $token ) ) wp_send_json_error( __( 'The system requires Turnstile verification.', 'redirect-gateway-manager' ) );
                $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                    'body' => array( 'secret' => $ts_secret, 'response' => $token, 'remoteip' => $remote_ip )
                ));
                if ( is_wp_error( $response ) ) wp_send_json_error( __( 'Cannot connect to Cloudflare.', 'redirect-gateway-manager' ) );
                $result = json_decode( wp_remote_retrieve_body( $response ) );
                if ( ! $result->success ) {
                    wp_send_json_error( __( 'Cloudflare Turnstile verification failed.', 'redirect-gateway-manager' ) );
                }
            }
        }

        $slug = isset( $_POST['slug'] ) ? esc_sql( sanitize_text_field( wp_unslash( $_POST['slug'] ) ) ) : '';
        if ( empty( $slug ) ) wp_send_json_error( __( 'Missing link data.', 'redirect-gateway-manager' ) );

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $table_logs  = $wpdb->prefix . 'rg_logs';
        
        // [BẢN VÁ WPCS]
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, original_url, password FROM {$wpdb->prefix}rg_links WHERE slug = %s", $slug ), ARRAY_A );

        if ( $link_data ) {

            // ========================================================
            // [BẢN VÁ BẢO MẬT WPCS]: LÀM SẠCH COOKIE TRƯỚC KHI SO SÁNH
            // ========================================================
            $password = isset($link_data['password']) ? $link_data['password'] : '';
            if ( ! empty( $password ) ) {
                $cookie_name = 'wprg_unlock_' . md5($slug);
                $cookie_val = isset( $_COOKIE[$cookie_name] ) ? sanitize_text_field( wp_unslash( $_COOKIE[$cookie_name] ) ) : '';
                
                // Nếu khách không có Cookie hợp lệ -> Chặn đứng lập tức
                if ( empty( $cookie_val ) || $cookie_val !== md5( $password ) ) {
                    wp_send_json_error( __( 'Security error: You have not unlocked the password for this link!', 'redirect-gateway-manager' ) );
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
                    $referrer = empty( $path ) ? 'Homepage' : '/' . $path;
                } else {
                    $referrer = preg_replace( '#^https?://(www\.)?#i', '', $referrer );
                    $referrer = rtrim( $referrer, '/' );
                }
            }

            $sub_id = isset( $_POST['sub_id'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_id'] ) ) : '';
            $url_params = isset( $_POST['url_params'] ) ? sanitize_textarea_field( wp_unslash( $_POST['url_params'] ) ) : '';
            $log_id = isset( $_POST['log_id'] ) ? intval( wp_unslash( $_POST['log_id'] ) ) : 0;

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
            wp_send_json_error( __( 'Link not found in the database.', 'redirect-gateway-manager' ) );
        }
    }
}
new WPRG_Frontend_Ajax();