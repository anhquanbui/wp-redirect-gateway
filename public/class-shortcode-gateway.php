<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Shortcode_Gateway {

    public function __construct() {
        add_shortcode( 'wprg_gateway', array( $this, 'render_gateway_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_register_script( 'wprg-gateway-js', WPRG_PLUGIN_URL . 'assets/js/gateway-timer.js', array('jquery'), time(), true );
        wp_enqueue_style( 'wprg-frontend-css', WPRG_PLUGIN_URL . 'assets/css/wprg-frontend.css', array(), time() );
    }

    public function render_gateway_shortcode( $atts ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $slug = isset( $_GET['wprg_link'] ) ? sanitize_text_field( wp_unslash( $_GET['wprg_link'] ) ) : '';
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $log_id_val = isset( $_GET['wprg_log_id'] ) ? intval( wp_unslash( $_GET['wprg_log_id'] ) ) : 0;

        if ( empty( $slug ) ) {
            return '<div class="wprg-shortcode-error"><h2>' . esc_html__( 'You do not have permission to access this page directly!', 'redirect-gateway-manager' ) . '</h2></div>';
        }

        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        $shortcodes = get_option( 'wprg_shortcodes', array() );
        
        if ( ! isset( $shortcodes[ $atts['id'] ] ) ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Error: Gateway does not exist.', 'redirect-gateway-manager' ) . '</div>';
        $sc_data = $shortcodes[ $atts['id'] ];

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $table_links . " WHERE slug = %s", $slug ), ARRAY_A );
        
        if ( ! $link_data ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Error: Link does not exist or has been deleted.', 'redirect-gateway-manager' ) . '</div>';

        $password = isset($link_data['password']) ? $link_data['password'] : '';
        $is_unlocked = false;

        if ( empty($password) ) {
            $is_unlocked = true; 
        } else {
            $cookie_name = 'wprg_unlock_' . md5($slug);
            if ( isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === md5($password) ) {
                $is_unlocked = true; 
            }
        }

        $raw_aff_links = get_option( 'wprg_affiliate_links', '' );
        $aff_links_array = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_aff_links ) ) );
        if ( empty( $aff_links_array ) ) $aff_links_array = array( home_url() ); 

        $captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
        $recap_site = get_option( 'wprg_recaptcha_site', '' );
        $ts_site = get_option( 'wprg_turnstile_site', '' );

        if ( $captcha_type === 'recaptcha' && ! empty( $recap_site ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recap_site ), array(), WPRG_VERSION, true );
        } elseif ( $captcha_type === 'turnstile' && ! empty( $ts_site ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
            wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), WPRG_VERSION, true );
        }

        $final_wait_time = ! empty( $link_data['wait_time'] ) ? sanitize_text_field( $link_data['wait_time'] ) : sanitize_text_field( $sc_data['wait_time'] );

        $c_val = intval( get_option( 'wprg_cookie_pass_val', 24 ) );
        $c_unit = get_option( 'wprg_cookie_pass_unit', 'hours' );
        $c_multipliers = array( 'seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400, 'weeks' => 604800, 'months' => 2592000, 'years' => 31536000 );
        $cookie_time_sec = $c_val * ( isset( $c_multipliers[$c_unit] ) ? $c_multipliers[$c_unit] : 3600 );

        wp_enqueue_script( 'wprg-gateway-js' );
        wp_localize_script( 'wprg-gateway-js', 'wprgData', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'wprg_gateway_nonce' ),
            'slug'        => $slug,
            'wait_time'   => $final_wait_time,
            'total_ads'   => intval( $link_data['ad_count'] ),
            'aff_links'   => array_values( $aff_links_array ),
            'active_tab'  => get_option( 'wprg_require_active_tab', '1' ),
            'single_link' => get_option( 'wprg_single_link_mode', '0' ),
            'new_tab_delay' => get_option( 'wprg_new_tab_delay', 0 ),
            'captcha_type'   => $captcha_type,
            'recaptcha_site'       => get_option( 'wprg_recaptcha_site', '' ),        
            'turnstile_site' => $ts_site,
            'enable_initial_click' => get_option( 'wprg_enable_initial_click', '1' ),
            'rel_noopener'         => get_option( 'wprg_rel_noopener', '1' ),
            'rel_noreferrer'       => get_option( 'wprg_rel_noreferrer', '0' ),
            'home_url'             => home_url(),
            'log_id'               => $log_id_val,
            'open_new_tab'         => get_option( 'wprg_open_link_new_tab', '0' ),
            'auto_retry'           => get_option( 'wprg_auto_retry_error', '0' ), 
            'cookie_time'          => $cookie_time_sec,
            
            'i18n'        => array(
                'wait_msg'            => __( 'Please wait...', 'redirect-gateway-manager' ),
                'watch_ad'            => __( 'Watch Ad', 'redirect-gateway-manager' ),
                'link_ready'          => __( 'Link is ready! Click to get', 'redirect-gateway-manager' ),
                'getting_link'        => __( 'Getting link...', 'redirect-gateway-manager' ),
                'checking_sec'        => __( 'Getting link...', 'redirect-gateway-manager' ),
                'active_warning'      => __( 'You have an active link running!', 'redirect-gateway-manager' ),
                'active_desc'         => __( 'Please finish the other link, or wait for the system to automatically unlock after 5 minutes.', 'redirect-gateway-manager' ),
                'error_prefix'        => __( 'Error:', 'redirect-gateway-manager' ),
                'error_msg'           => __( 'Cannot get the destination link!', 'redirect-gateway-manager' ),
                'network_err'         => __( 'Network error, please try again!', 'redirect-gateway-manager' ),
                'try_again'           => __( 'Try again', 'redirect-gateway-manager' ),
                'step_done'           => __( 'You have completed the steps.', 'redirect-gateway-manager' ),
                'counting'            => __( 'Counting down...', 'redirect-gateway-manager' ),
                'click_to_watch'      => __( 'Click to continue watching ads', 'redirect-gateway-manager' ),
                'stop_warning'        => __( '⚠️ Countdown stopped. You must return to this tab!', 'redirect-gateway-manager' ), 
                'verify_sec'          => __( '🔍 Security Verification', 'redirect-gateway-manager' ),
                'verifying'           => __( 'Running verification...', 'redirect-gateway-manager' ),
                'verify_msg'          => __( 'The system needs to check to ensure you are not a Robot.', 'redirect-gateway-manager' ),
                'start_btn'           => __( 'CLICK HERE TO CONTINUE', 'redirect-gateway-manager' ),
                'start_msg'           => __( 'Please click the button below to start', 'redirect-gateway-manager' ),
                'recap_error'         => __( 'reCAPTCHA error. Please reload the page.', 'redirect-gateway-manager' ),
                'script_blocked'      => __( 'Security script blocked. Please disable ad blocker to continue.', 'redirect-gateway-manager' ),
                'popup_blocked_alert' => __( "⚠️ Your browser is blocking Popups.\n\nPlease allow Popups to continue!", 'redirect-gateway-manager' ),
                'popup_blocked_msg'   => __( '⚠️ Please allow Popups in the address bar to continue!', 'redirect-gateway-manager' ),
                'checking_pass' => __( 'CHECKING...', 'redirect-gateway-manager' ),
                'unlock_now'    => __( 'UNLOCK NOW', 'redirect-gateway-manager' ),
                'wrong_pass'    => __( 'Incorrect password!', 'redirect-gateway-manager' ),
                'wait_verify'   => __( 'WAITING FOR VERIFICATION...', 'redirect-gateway-manager' ),
                'retrying'      => __( 'RETRYING...', 'redirect-gateway-manager' ),
                'network_error_short' => __( 'Network error!', 'redirect-gateway-manager' ),
                'checking_safe'       => __( 'System is checking for safety, please wait a moment...', 'redirect-gateway-manager' ),
                'cf_load_error'       => __( 'Error: Browser could not load Cloudflare Script. Please disable ad blocker or reload the page!', 'redirect-gateway-manager' ),
                'link_opened_btn'     => __( 'Link obtained!', 'redirect-gateway-manager' ),
                'link_opened_new_tab' => __( 'Destination link has been opened in a new tab.', 'redirect-gateway-manager' ),
                'auto_retrying'       => __( 'Network hiccup. Auto-retrying in 2 seconds', 'redirect-gateway-manager' ),
                'pass_backend_err'    => __( 'Security error: You have not unlocked the password for this link!', 'redirect-gateway-manager' ),
                'pls_enter_pass'      => __( 'It only takes a few seconds to enter the password, why not give it a try?', 'redirect-gateway-manager' )
            )
        ));

        ob_start(); 
        ?>
        
        <?php if ( ! $is_unlocked ) : ?>
        <div id="wprg-pass-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-password-container">
            <div class="wprg-lock-icon">🔒</div>
            <h3 class="wprg-title wprg-text-danger"><?php esc_html_e( 'Password Required Link', 'redirect-gateway-manager' ); ?></h3>
            <p class="wprg-desc"><?php esc_html_e( 'Please enter the password to continue getting the destination link.', 'redirect-gateway-manager' ); ?></p>
            <p id="wprg-pass-error-<?php echo esc_attr($slug); ?>" class="wprg-pass-error"></p>
            <form class="wprg-ajax-pass-form" data-slug="<?php echo esc_attr($slug); ?>">
                <input type="password" class="wprg-pass-input" placeholder="<?php esc_attr_e( 'Enter password here...', 'redirect-gateway-manager' ); ?>" required>
                <button type="submit" class="wprg-pass-submit"><?php esc_html_e( 'UNLOCK NOW', 'redirect-gateway-manager' ); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <div id="wprg-btn-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-theme-default" data-slug="<?php echo esc_attr($slug); ?>" data-wait="<?php echo esc_attr($final_wait_time); ?>" data-ads="<?php echo intval( $link_data['ad_count'] ); ?>" data-logid="<?php echo esc_attr($log_id_val); ?>" style="<?php echo ( ! $is_unlocked ) ? 'display:none;' : ''; ?>">
            <h2 class="wprg-title"><?php esc_html_e( 'Destination page is being prepared...', 'redirect-gateway-manager' ); ?></h2>
            <p class="wprg-desc"><?php esc_html_e( 'Please complete the steps below to get the link', 'redirect-gateway-manager' ); ?></p>
            
            <button class="wprg-action-btn">
                <?php esc_html_e( 'Click here to continue', 'redirect-gateway-manager' ); ?>
            </button>
            <p class="wprg-status-text"></p>
        </div>
        
        <?php
        return ob_get_clean();
    }
}
new WPRG_Shortcode_Gateway();