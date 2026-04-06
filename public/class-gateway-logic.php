<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Gateway_Logic {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'intercept_go_url' ) );
    }

    public function add_rewrite_rules() {
        // Luật 1: Bắt URL có thư mục ngôn ngữ (VD: /zh-hans/go/slug) -> Truyền cả lang và slug
        add_rewrite_rule( '^([a-z]{2}(?:-[a-z0-9]+)?)/go/([a-zA-Z0-9]+)/?$', 'index.php?lang=$matches[1]&wprg_slug=$matches[2]', 'top' );
        
        // Luật 2: Bắt URL mặc định tiếng Anh (VD: /go/slug)
        add_rewrite_rule( '^go/([a-zA-Z0-9]+)/?$', 'index.php?wprg_slug=$matches[1]', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'wprg_slug';
        return $vars;
    }

    public function intercept_go_url() {
        $slug = get_query_var( 'wprg_slug' );
        
        if ( ! empty( $slug ) ) {
            global $wpdb;
            $table_links = $wpdb->prefix . 'rg_links';
            $table_logs  = $wpdb->prefix . 'rg_logs';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_links} WHERE slug = %s", $slug ), ARRAY_A );

            if ( $link_data ) {
                $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : ( isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Unknown';
                $referrer = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : 'Trực tiếp';
                
                // Tối ưu Referrer: So sánh bằng HTTP_HOST
                if ( $referrer !== 'Trực tiếp' ) {
                    $parsed_ref = wp_parse_url( $referrer );
                    $ref_host = isset($parsed_ref['host']) ? str_replace('www.', '', strtolower($parsed_ref['host'])) : '';
                    
                    $current_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (isset($_SERVER['SERVER_NAME']) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '');
                    $home_host = str_replace('www.', '', strtolower($current_host));

                    if ( $ref_host === $home_host || empty($ref_host) ) {
                        // Link nội bộ: Lấy mỗi đuôi
                        $path = isset($parsed_ref['path']) ? $parsed_ref['path'] : '';
                        $path = trim( $path, '/' );
                        $referrer = empty( $path ) ? 'Homepage' : '/' . $path;
                    } else {
                        // Link bên ngoài: Xóa https:// và www.
                        $referrer = preg_replace( '#^https?://(www\.)?#i', '', $referrer );
                        $referrer = rtrim( $referrer, '/' );
                    }
                }
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert( $table_logs, array(
                    'link_id'    => $link_data['id'],
                    'ip_address' => $ip,
                    'user_agent' => $user_agent,
                    'referrer'   => $referrer, 
                    'status'     => 'click', 
                    'clicked_at' => current_time('mysql') 
                ));
                $log_id = $wpdb->insert_id;

                $sc_id = $link_data['shortcode_id'];
                $shortcodes = get_option( 'wprg_shortcodes', array() );

                if ( ! empty( $sc_id ) && isset( $shortcodes[ $sc_id ] ) ) {
                    $page_id = apply_filters( 'wprg_gateway_page_id', $shortcodes[ $sc_id ]['page_id'] );
                    $gateway_url = get_permalink( $page_id );

                    // [THÊM DÒNG NÀY] Cho phép Module WPML can thiệp ép ngôn ngữ vào URL cuối cùng
                    $gateway_url = apply_filters( 'wprg_final_gateway_url', $gateway_url );

                    if ( $gateway_url ) {
                        $query_args = array(); 
                        
                        // [BẢN VÁ WPCS]: Chỉ cho phép các tham số sạch (UTM) được vượt qua Gateway
                        $allowed_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'lang', 'ref' );
                        foreach ( $allowed_params as $param ) {
                            if ( isset( $_GET[ $param ] ) ) {
                                $query_args[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                            }
                        }
                        
                        $query_args['wprg_link'] = $slug; 
                        $query_args['wprg_log_id'] = $log_id; 
                        
                        $redirect_url = add_query_arg( $query_args, $gateway_url );
                        wp_redirect( $redirect_url, 302 );
                        exit;
                    } else {
                        wp_die( esc_html__( 'The Gateway page containing the shortcode does not exist or has been deleted.', 'redirect-gateway-manager' ) );
                    }
                } else {
                    wp_die( esc_html__('This link has not been assigned a Gateway or the Gateway has been disabled.','redirect-gateway-manager') );
                }
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header( 404 );
                nocache_headers();
            }
        }
    }
}
new WPRG_Gateway_Logic();