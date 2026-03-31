<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) ); 
        add_action( 'admin_notices', array( $this, 'wprg_admin_notices' ) );
        add_action( 'admin_init', array( $this, 'wprg_dismiss_notice_handler' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
    }

    public function enqueue_admin_styles( $hook_suffix ) {
        if ( strpos( $hook_suffix, 'wprg-' ) !== false ) {
            wp_enqueue_style( 'wprg-admin-css', WPRG_PLUGIN_URL . 'assets/css/wprg-admin.css', array(), WPRG_VERSION );
            
            // THÊM DÒNG NÀY ĐỂ NẠP CHART.JS CHUẨN WORDPRESS:
            wp_enqueue_script( 'chart-js', WPRG_PLUGIN_URL . 'assets/js/chart.min.js', array(), '3.9.1', true );
        }
    }
    
    public function register_menus() {
        add_menu_page(
            __( 'Gateway Redirect', 'redirect-gateway-manager' ),         
            __( 'Gateway Redirect', 'redirect-gateway-manager' ),         
            'manage_options',           
            'wprg-dashboard',           
            array( $this, 'render_dashboard' ), 
            'dashicons-randomize',      
            25                          
        );

        add_submenu_page( 'wprg-dashboard', __( 'Dashboard', 'redirect-gateway-manager' ), __( 'Dashboard', 'redirect-gateway-manager' ), 'manage_options', 'wprg-dashboard', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Manage Links', 'redirect-gateway-manager' ), __( 'Manage Links', 'redirect-gateway-manager' ), 'manage_options', 'wprg-links', array( $this, 'render_links_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Shortcodes', 'redirect-gateway-manager' ), __( 'Shortcodes', 'redirect-gateway-manager' ), 'manage_options', 'wprg-shortcodes', array( $this, 'render_shortcodes_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Settings', 'redirect-gateway-manager' ), __( 'Settings', 'redirect-gateway-manager' ), 'manage_options', 'wprg-settings', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'wprg-dashboard', __( 'Logs', 'redirect-gateway-manager' ), __( 'Logs', 'redirect-gateway-manager' ), 'manage_options', 'wprg-logs', array( $this, 'render_logs_page' ) );
    }

    public function render_dashboard() { require_once WPRG_PLUGIN_DIR . 'admin/views/dashboard.php'; }
    public function render_shortcodes_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/shortcodes-manager.php'; }
    public function render_settings_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/settings.php'; }
    public function render_logs_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/logs-manager.php'; }
    public function render_links_page() { require_once WPRG_PLUGIN_DIR . 'admin/views/links-manager.php'; }

    public function wprg_dismiss_notice_handler() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['wprg_dismiss_plugin'] ) ) {
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'wprg_dismiss_notice' ) ) {
                wp_die( esc_html__( 'Security error!', 'redirect-gateway-manager' ) );
            }
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $plugin_to_dismiss = sanitize_text_field( wp_unslash( $_GET['wprg_dismiss_plugin'] ) );
            
            $dismissed = get_option( 'wprg_dismissed_conflicts', array() );
            if ( ! is_array( $dismissed ) ) {
                $dismissed = array();
            }
            
            if ( ! in_array( $plugin_to_dismiss, $dismissed ) ) {
                $dismissed[] = $plugin_to_dismiss;
                update_option( 'wprg_dismissed_conflicts', $dismissed );
            }
            
            $clean_url = remove_query_arg( array( 'wprg_dismiss_plugin', '_wpnonce' ) );
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    public function wprg_admin_notices() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
        
        if ( strpos( $current_page, 'wprg-' ) === false ) {
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
                // Sửa thành wp_nonce_url để thêm mã bảo mật vào nút bấm
                $dismiss_url = wp_nonce_url( add_query_arg( 'wprg_dismiss_plugin', urlencode($path) ), 'wprg_dismiss_notice' );
                
                echo '<div class="notice notice-warning" style="padding-bottom: 10px;">';
                
                /* translators: %s: Plugin name */
                echo '<p><strong>' . esc_html__( '⚠️ Attention (From WP Redirect Gateway):', 'redirect-gateway-manager' ) . '</strong> ' . sprintf( esc_html__( 'Detected that the website has %s installed.', 'redirect-gateway-manager' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '<br>';
                
                echo wp_kses_post( __( 'Please go to the Cache settings and add the <strong>Gateway Page</strong> URL to the <strong>Never Cache URL</strong> list.', 'redirect-gateway-manager' ) ) . '</p>';
                
                /* translators: %s: Plugin name */
                echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-primary">' . sprintf( esc_html__( 'Setup completed for %s, Hide this!', 'redirect-gateway-manager' ), esc_html( $name ) ) . '</a>';
                
                echo '</div>';
            }
        }

        foreach ( $security_plugins as $path => $name ) {
            if ( is_plugin_active( $path ) && ! in_array( $path, $dismissed ) ) {
                // Sửa thành wp_nonce_url để thêm mã bảo mật vào nút bấm
                $dismiss_url = wp_nonce_url( add_query_arg( 'wprg_dismiss_plugin', urlencode($path) ), 'wprg_dismiss_notice' );
                
                echo '<div class="notice notice-info" style="padding-bottom: 10px;">';
                
                /* translators: %s: Plugin name */
                echo '<p><strong>' . esc_html__( '🛡️ Security Tip:', 'redirect-gateway-manager' ) . '</strong> ' . sprintf( esc_html__( 'The website is using the %s Firewall.', 'redirect-gateway-manager' ), '<strong>' . esc_html( $name ) . '</strong>' ) . '<br>';
                
                echo wp_kses_post( __( 'Make sure to use the <strong>same reCAPTCHA Key set</strong> to avoid conflicts.', 'redirect-gateway-manager' ) ) . '</p>';
                
                /* translators: %s: Plugin name */
                echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-primary">' . sprintf( esc_html__( 'Understood the note for %s, Hide this!', 'redirect-gateway-manager' ), esc_html( $name ) ) . '</a>';
                
                echo '</div>';
            }
        }
    }
}
new WPRG_Admin_Menu();