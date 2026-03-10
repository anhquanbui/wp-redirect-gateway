<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Gateway_Logic {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'intercept_go_url' ) );
    }

    public function add_rewrite_rules() {
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

            $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_links WHERE slug = %s", $slug ), ARRAY_A );

            if ( $link_data ) {
                $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';
                $referrer = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : 'Trực tiếp';
                
                // [ĐÃ FIX CHUẨN] Tối ưu Referrer: So sánh bằng HTTP_HOST
                if ( $referrer !== 'Trực tiếp' ) {
                    $parsed_ref = wp_parse_url( $referrer );
                    $ref_host = isset($parsed_ref['host']) ? str_replace('www.', '', strtolower($parsed_ref['host'])) : '';
                    
                    $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
                    $home_host = str_replace('www.', '', strtolower($current_host));

                    if ( $ref_host === $home_host || empty($ref_host) ) {
                        // Link nội bộ: Lấy mỗi đuôi
                        $path = isset($parsed_ref['path']) ? $parsed_ref['path'] : '';
                        $path = trim( $path, '/' );
                        $referrer = empty( $path ) ? 'Trang chủ' : '/' . $path;
                    } else {
                        // Link bên ngoài: Xóa https:// và www.
                        $referrer = preg_replace( '#^https?://(www\.)?#i', '', $referrer );
                        $referrer = rtrim( $referrer, '/' );
                    }
                }
                
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
                    $page_id = $shortcodes[ $sc_id ]['page_id'];
                    $gateway_url = get_permalink( $page_id );

                    if ( $gateway_url ) {
                        $query_args = $_GET; 
                        $query_args['wprg_link'] = $slug; 
                        $query_args['wprg_log_id'] = $log_id; 
                        
                        $redirect_url = add_query_arg( $query_args, $gateway_url );
                        wp_redirect( $redirect_url, 302 );
                        exit;
                    } else {
                        wp_die( esc_html__( 'Trang Gateway chứa shortcode không tồn tại hoặc đã bị xóa.', 'wp-redirect-gateway' ) );
                    }
                } else {
                    wp_die( esc_html__('Link này chưa được gán Gateway hoặc Gateway đã bị vô hiệu hóa.','wp-redirect-gateway') );
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