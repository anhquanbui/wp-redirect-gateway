<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) ); 
        add_action( 'admin_notices', array( $this, 'wprg_admin_notices' ) );
        add_action( 'admin_init', array( $this, 'wprg_dismiss_notice_handler' ) );
    }
    
    public function register_menus() {
        add_menu_page(
            __( 'Gateway Redirect', 'wp-redirect-gateway' ),         
            __( 'Gateway Redirect', 'wp-redirect-gateway' ),         
            'manage_options',           
            'wprg-dashboard',           
            array( $this, 'render_dashboard' ), 
            'dashicons-randomize',      
            25                          
        );

        add_submenu_page( 'wprg-dashboard', __( 'Dashboard', 'wp-redirect-gateway' ), __( 'Dashboard', 'wp-redirect-gateway' ), 'manage_options', 'wprg-dashboard', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Quản lý Link', 'wp-redirect-gateway' ), __( 'Quản lý Link', 'wp-redirect-gateway' ), 'manage_options', 'wprg-links', array( $this, 'render_links_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Shortcodes', 'wp-redirect-gateway' ), __( 'Shortcodes', 'wp-redirect-gateway' ), 'manage_options', 'wprg-shortcodes', array( $this, 'render_shortcodes_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Cài đặt', 'wp-redirect-gateway' ), __( 'Cài đặt', 'wp-redirect-gateway' ), 'manage_options', 'wprg-settings', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Logs', 'wp-redirect-gateway' ), __( 'Logs', 'wp-redirect-gateway' ), 'manage_options', 'wprg-logs', array( $this, 'render_logs_page' ) );
    }

    public function render_dashboard() { require_once WPRG_PLUGIN_DIR . 'admin/views/dashboard.php'; }
    public function render_shortcodes_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/shortcodes-manager.php'; }
    public function render_settings_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/settings.php'; }
    public function render_logs_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/logs-manager.php'; }
    public function render_links_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/links-manager.php'; }

    public function wprg_dismiss_notice_handler() {
        if ( isset( $_GET['wprg_dismiss_plugin'] ) ) {
            $plugin_to_dismiss = sanitize_text_field( $_GET['wprg_dismiss_plugin'] );
            
            $dismissed = get_option( 'wprg_dismissed_conflicts', array() );
            if ( ! is_array( $dismissed ) ) {
                $dismissed = array();
            }
            
            if ( ! in_array( $plugin_to_dismiss, $dismissed ) ) {
                $dismissed[] = $plugin_to_dismiss;
                update_option( 'wprg_dismissed_conflicts', $dismissed );
            }
            
            $clean_url = remove_query_arg( 'wprg_dismiss_plugin' );
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    public function wprg_admin_notices() {
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'wprg-' ) === false ) {
            return;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        $cache_plugins = array(
            'wp-rocket/wp-rocket.php'             => 'WP Rocket',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
            'sg-cachepress/sg-cachepress.php'     => 'SiteGround Optimizer'
        );

        $security_plugins = array(
            'wordfence/wordfence.php'                             => 'Wordfence Security',
            'better-wp-security/better-wp-security.php'           => 'iThemes Security (Solid)',
            'ithemes-security-pro/ithemes-security-pro.php'       => 'iThemes Security Pro',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security'
        );

        $dismissed = get_option( 'wprg_dismissed_conflicts', array() );
        if ( ! is_array( $dismissed ) ) $dismissed = array();

        foreach ( $cache_plugins as $path => $name ) {
            if ( is_plugin_active( $path ) && ! in_array( $path, $dismissed ) ) {
                $dismiss_url = add_query_arg( 'wprg_dismiss_plugin', urlencode($path) );
                
                echo '<div class="notice notice-warning" style="padding-bottom: 10px;">';
                echo '<p><strong>' . esc_html__( '⚠️ Chú ý (Từ WP Redirect Gateway):', 'wp-redirect-gateway' ) . '</strong> ' . sprintf( esc_html__( 'Phát hiện website đang cài đặt %s.', 'wp-redirect-gateway' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '<br>';
                echo wp_kses_post( __( 'Vui lòng vào cài đặt Cache và thêm đường dẫn của <strong>Trang Gateway</strong> vào danh sách <strong>Không lưu bộ nhớ đệm (Never Cache URL)</strong>.', 'wp-redirect-gateway' ) ) . '</p>';
                echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-primary">' . sprintf( esc_html__( 'Đã thiết lập xong cho %s, Ẩn đi!', 'wp-redirect-gateway' ), esc_html( $name ) ) . '</a>';
                echo '</div>';
            }
        }

        foreach ( $security_plugins as $path => $name ) {
            if ( is_plugin_active( $path ) && ! in_array( $path, $dismissed ) ) {
                $dismiss_url = add_query_arg( 'wprg_dismiss_plugin', urlencode($path) );
                
                echo '<div class="notice notice-info" style="padding-bottom: 10px;">';
                echo '<p><strong>' . esc_html__( '🛡️ Mẹo Bảo Mật:', 'wp-redirect-gateway' ) . '</strong> ' . sprintf( esc_html__( 'Website đang dùng Tường lửa %s.', 'wp-redirect-gateway' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '<br>';
                echo wp_kses_post( __( 'Hãy đảm bảo dùng <strong>chung 1 bộ Key reCAPTCHA</strong> để tránh xung đột nhé.', 'wp-redirect-gateway' ) ) . '</p>';
                echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-primary">' . sprintf( esc_html__( 'Đã hiểu lưu ý của %s, Ẩn đi!', 'wp-redirect-gateway' ), esc_html( $name ) ) . '</a>';
                echo '</div>';
            }
        }
    }
}
new WPRG_Admin_Menu();