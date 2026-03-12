<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Shortcode_Inline {

    public function __construct() {
        add_shortcode( 'wprg_inline_button', array( $this, 'render_inline_button_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_register_script( 'wprg-gateway-js', WPRG_PLUGIN_URL . 'assets/js/gateway-timer.js', array('jquery'), time(), true );
        wp_enqueue_style( 'wprg-frontend-css', WPRG_PLUGIN_URL . 'assets/css/wprg-frontend.css', array(), time() );
        
        $captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
        $recap_site   = get_option( 'wprg_recaptcha_site', '' );
        $ts_site      = get_option( 'wprg_turnstile_site', '' );

        if ( $captcha_type === 'recaptcha' && ! empty( $recap_site ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recap_site ), array(), WPRG_VERSION, true );
        } elseif ( $captcha_type === 'turnstile' && ! empty( $ts_site ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
            wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), WPRG_VERSION, true );
        }
    }

    public function render_inline_button_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'slug' => '' ), $atts );
        $slug = sanitize_text_field( $atts['slug'] );

        if ( empty( $slug ) ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Lỗi: Vui lòng nhập slug của link.', 'redirect-gateway-manager' ) . '</div>';

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_links} WHERE slug = %s", $slug ), ARRAY_A );
        
        if ( ! $link_data ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Lỗi: Link không tồn tại trong hệ thống.', 'redirect-gateway-manager' ) . '</div>';

        $password = isset($link_data['password']) ? $link_data['password'] : '';
        $is_unlocked = false;

        if ( empty($password) ) {
            $is_unlocked = true; 
        } else {
            $cookie_name = 'wprg_unlock_' . md5($slug);
            if ( isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === md5($password) ) $is_unlocked = true; 
        }

        $shortcodes = get_option( 'wprg_shortcodes', array() );
        $sc_id = $link_data['shortcode_id'];
        
        if ( ! isset( $shortcodes[ $sc_id ] ) ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Lỗi: Gateway bị xóa.', 'redirect-gateway-manager' ) . '</div>';
        $sc_data = $shortcodes[ $sc_id ];

        $final_wait_time = ! empty( $link_data['wait_time'] ) ? sanitize_text_field( $link_data['wait_time'] ) : sanitize_text_field( $sc_data['wait_time'] );
        $raw_aff_links = get_option( 'wprg_affiliate_links', '' );
        $aff_links_array = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_aff_links ) ) );
        if ( empty( $aff_links_array ) ) $aff_links_array = array( home_url() ); 

        $c_val = intval( get_option( 'wprg_cookie_pass_val', 24 ) );
        $c_unit = get_option( 'wprg_cookie_pass_unit', 'hours' );
        $c_multipliers = array( 'seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400, 'weeks' => 604800, 'months' => 2592000, 'years' => 31536000 );
        $cookie_time_sec = $c_val * ( isset( $c_multipliers[$c_unit] ) ? $c_multipliers[$c_unit] : 3600 );

        wp_enqueue_script( 'wprg-gateway-js' );
        wp_localize_script( 'wprg-gateway-js', 'wprgData', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'wprg_gateway_nonce' ),
            'aff_links'   => array_values( $aff_links_array ),
            'active_tab'  => get_option( 'wprg_require_active_tab', '1' ),
            'single_link' => get_option( 'wprg_single_link_mode', '0' ),
            'captcha_type'         => get_option( 'wprg_captcha_type', 'recaptcha' ), 
            'recaptcha_site'       => get_option( 'wprg_recaptcha_site', '' ),        
            'turnstile_site'       => get_option( 'wprg_turnstile_site', '' ),
            'enable_initial_click' => get_option( 'wprg_enable_initial_click', '1' ),
            'rel_noopener'         => get_option( 'wprg_rel_noopener', '1' ),
            'rel_noreferrer'       => get_option( 'wprg_rel_noreferrer', '0' ),
            'initial_links'        => get_option( 'wprg_initial_links', array() ),
            'home_url'             => home_url(),
            'open_new_tab'         => get_option( 'wprg_open_link_new_tab', '0' ),
            'auto_retry'           => get_option( 'wprg_auto_retry_error', '0' ),
            'cookie_time'          => $cookie_time_sec,
            
            'i18n'        => array(
                'wait_msg'            => __( 'Vui lòng đợi...', 'redirect-gateway-manager' ),
                'watch_ad'            => __( 'Xem quảng cáo', 'redirect-gateway-manager' ),
                'link_ready'          => __( 'Link đã sẵn sàng! Bấm để nhận', 'redirect-gateway-manager' ),
                'getting_link'        => __( 'Đang lấy link...', 'redirect-gateway-manager' ),
                'checking_sec'        => __( 'Đang lấy link...', 'redirect-gateway-manager' ),
                'active_warning'      => __( 'Bạn đang có 1 link khác đang hoạt động!', 'redirect-gateway-manager' ),
                'active_desc'         => __( 'Hãy hoàn thành link kia, hoặc chờ hệ thống tự mở khóa sau 5 phút.', 'redirect-gateway-manager' ),
                'error_prefix'        => __( 'Lỗi:', 'redirect-gateway-manager' ),
                'error_msg'           => __( 'Không thể lấy link đích!', 'redirect-gateway-manager' ),
                'network_err'         => __( 'Lỗi kết nối mạng, vui lòng thử lại!', 'redirect-gateway-manager' ),
                'try_again'           => __( 'Thử lại', 'redirect-gateway-manager' ),
                'step_done'           => __( 'Bạn đã hoàn thành các bước.', 'redirect-gateway-manager' ),
                'counting'            => __( 'Đang đếm ngược...', 'redirect-gateway-manager' ),
                'click_to_watch'      => __( 'Bấm để tiếp tục', 'redirect-gateway-manager' ),
                'stop_warning'        => __( '⚠️ Đã dừng đếm. Bạn phải quay lại tab này!', 'redirect-gateway-manager' ), 
                'verify_sec'          => __( '🔍 Xác minh bảo mật', 'redirect-gateway-manager' ),
                'verifying'           => __( 'Đang chạy xác minh...', 'redirect-gateway-manager' ),
                'verify_msg'          => __( 'Hệ thống cần kiểm tra để đảm bảo bạn không phải Robot.', 'redirect-gateway-manager' ),
                'start_btn'           => __( 'CLICK HERE TO CONTINUE', 'redirect-gateway-manager' ),
                'start_msg'           => __( 'Vui lòng nhấn nút bên dưới để bắt đầu', 'redirect-gateway-manager' ),
                'recap_error'         => __( 'reCAPTCHA bị lỗi. Vui lòng tải lại trang.', 'redirect-gateway-manager' ),
                'script_blocked'      => __( 'Script bảo mật bị chặn. Vui lòng tắt trình chặn quảng cáo để đi tiếp.', 'redirect-gateway-manager' ),
                'popup_blocked_alert' => __( "⚠️ Trình duyệt đang chặn Cửa sổ bật lên (Popup).\n\nVui lòng cấp quyền mở Popup để tiếp tục!", 'redirect-gateway-manager' ),
                'popup_blocked_msg'   => __( '⚠️ Vui lòng cấp quyền mở Popup trên thanh địa chỉ để đi tiếp!', 'redirect-gateway-manager' ),
                'checking_pass' => __( 'ĐANG KIỂM TRA...', 'redirect-gateway-manager' ),
                'unlock_now'    => __( 'MỞ KHÓA NGAY', 'redirect-gateway-manager' ),
                'wrong_pass'    => __( 'Mật khẩu sai!', 'redirect-gateway-manager' ),
                'wait_verify'   => __( 'ĐANG CHỜ XÁC MINH...', 'redirect-gateway-manager' ),
                'retrying'      => __( 'ĐANG THỬ LẠI...', 'redirect-gateway-manager' ),
                'network_error_short' => __( 'Lỗi kết nối mạng!', 'redirect-gateway-manager' ),
                'checking_safe'       => __( 'Hệ thống đang kiểm tra an toàn, vui lòng đợi giây lát...', 'redirect-gateway-manager' ),
                'cf_load_error'       => __( 'Lỗi: Trình duyệt không thể tải Script Cloudflare. Vui lòng tắt chặn quảng cáo hoặc tải lại trang!', 'redirect-gateway-manager' ),
                'link_opened_btn'     => __( 'Đã lấy link!', 'redirect-gateway-manager' ),
                'link_opened_new_tab' => __( 'Link đích đã được mở ở Tab mới.', 'redirect-gateway-manager' ),
                'auto_retrying'       => __( 'Vấp mạng. Tự động thử lại sau 2 giây', 'redirect-gateway-manager' ),
                'pass_backend_err'    => __( 'Lỗi bảo mật: Bạn chưa mở khóa mật khẩu cho link này!', 'redirect-gateway-manager' ),
                'pls_enter_pass'      => __( 'Tốn vài giây để nhập mật khẩu thôi mà, tại sao bạn không cố gắng nhỉ?', 'redirect-gateway-manager' )
            )
        ));

        ob_start(); 
        ?>
        
        <?php if ( ! $is_unlocked ) : ?>
        <div id="wprg-pass-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-theme-inline">
            <div class="wprg-password-container">
                <h4 class="wprg-title wprg-text-danger">🔒 <?php esc_html_e( 'Yêu Cầu Mật Khẩu', 'redirect-gateway-manager' ); ?></h4>
                <p id="wprg-pass-error-<?php echo esc_attr($slug); ?>" class="wprg-pass-error"></p>
                <form class="wprg-ajax-pass-form" data-slug="<?php echo esc_attr($slug); ?>">
                    <input type="password" class="wprg-pass-input" placeholder="<?php esc_attr_e( 'Nhập mật khẩu...', 'redirect-gateway-manager' ); ?>" required>
                    <button type="submit" class="wprg-pass-submit"><?php esc_html_e( 'MỞ KHÓA NÚT BẤM', 'redirect-gateway-manager' ); ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div id="wprg-btn-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-theme-inline" data-slug="<?php echo esc_attr($slug); ?>" data-wait="<?php echo esc_attr($final_wait_time); ?>" data-ads="<?php echo intval( $link_data['ad_count'] ); ?>" data-logid="0" style="<?php echo ( ! $is_unlocked ) ? 'display:none;' : ''; ?>">
            <p class="wprg-title wprg-fw-bold"><?php esc_html_e( 'Bấm vào nút bên dưới để lấy link:', 'redirect-gateway-manager' ); ?></p>
            <button class="wprg-action-btn">
                <?php esc_html_e( 'BẮT ĐẦU LẤY LINK', 'redirect-gateway-manager' ); ?>
            </button>
            <p class="wprg-status-text"></p>
        </div>
        
        <?php
        return ob_get_clean();
    }
}
new WPRG_Shortcode_Inline();