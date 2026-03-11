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
            return '<div class="wprg-shortcode-error"><h2>' . esc_html__( 'Bạn không có quyền truy cập trực tiếp trang này!', 'wp-redirect-gateway' ) . '</h2></div>';
        }

        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        $shortcodes = get_option( 'wprg_shortcodes', array() );
        
        if ( ! isset( $shortcodes[ $atts['id'] ] ) ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Lỗi: Gateway không tồn tại.', 'wp-redirect-gateway' ) . '</div>';
        $sc_data = $shortcodes[ $atts['id'] ];

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_links} WHERE slug = %s", $slug ), ARRAY_A );
        
        if ( ! $link_data ) return '<div class="wprg-shortcode-error">' . esc_html__( 'Lỗi: Link không tồn tại hoặc đã bị xóa.', 'wp-redirect-gateway' ) . '</div>';

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
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recap_site ), array(), null, true );
        } elseif ( $captcha_type === 'turnstile' && ! empty( $ts_site ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
            wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
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
            'captcha_type'   => $captcha_type,
            'recaptcha_site'       => get_option( 'wprg_recaptcha_site', '' ),        
            'turnstile_site' => $ts_site,
            'enable_initial_click' => get_option( 'wprg_enable_initial_click', '1' ),
            'rel_noopener'         => get_option( 'wprg_rel_noopener', '1' ),
            'rel_noreferrer'       => get_option( 'wprg_rel_noreferrer', '0' ),
            'initial_links'        => get_option( 'wprg_initial_links', array() ),
            'home_url'             => home_url(),
            'log_id'               => $log_id_val,
            'open_new_tab'         => get_option( 'wprg_open_link_new_tab', '0' ),
            'auto_retry'           => get_option( 'wprg_auto_retry_error', '0' ), 
            'cookie_time'          => $cookie_time_sec,
            
            'i18n'        => array(
                'wait_msg'            => __( 'Vui lòng đợi...', 'wp-redirect-gateway' ),
                'watch_ad'            => __( 'Xem quảng cáo', 'wp-redirect-gateway' ),
                'link_ready'          => __( 'Link đã sẵn sàng! Bấm để nhận', 'wp-redirect-gateway' ),
                'getting_link'        => __( 'Đang lấy link...', 'wp-redirect-gateway' ),
                'checking_sec'        => __( 'Đang lấy link...', 'wp-redirect-gateway' ),
                'active_warning'      => __( 'Bạn đang có 1 link đang hoạt động!', 'wp-redirect-gateway' ),
                'active_desc'         => __( 'Hãy hoàn thành link kia, hoặc chờ hệ thống tự mở khóa sau 5 phút.', 'wp-redirect-gateway' ),
                'error_prefix'        => __( 'Lỗi:', 'wp-redirect-gateway' ),
                'error_msg'           => __( 'Không thể lấy link đích!', 'wp-redirect-gateway' ),
                'network_err'         => __( 'Lỗi kết nối mạng, vui lòng thử lại!', 'wp-redirect-gateway' ),
                'try_again'           => __( 'Thử lại', 'wp-redirect-gateway' ),
                'step_done'           => __( 'Bạn đã hoàn thành các bước.', 'wp-redirect-gateway' ),
                'counting'            => __( 'Đang đếm ngược...', 'wp-redirect-gateway' ),
                'click_to_watch'      => __( 'Bấm để tiếp tục xem quảng cáo', 'wp-redirect-gateway' ),
                'stop_warning'        => __( '⚠️ Đã dừng đếm. Bạn phải quay lại tab này!', 'wp-redirect-gateway' ), 
                'verify_sec'          => __( '🔍 Xác minh bảo mật', 'wp-redirect-gateway' ),
                'verifying'           => __( 'Đang chạy xác minh...', 'wp-redirect-gateway' ),
                'verify_msg'          => __( 'Hệ thống cần kiểm tra để đảm bảo bạn không phải Robot.', 'wp-redirect-gateway' ),
                'start_btn'           => __( 'CLICK HERE TO CONTINUE', 'wp-redirect-gateway' ),
                'start_msg'           => __( 'Vui lòng nhấn nút bên dưới để bắt đầu', 'wp-redirect-gateway' ),
                'recap_error'         => __( 'reCAPTCHA bị lỗi. Vui lòng tải lại trang.', 'wp-redirect-gateway' ),
                'script_blocked'      => __( 'Script bảo mật bị chặn. Vui lòng tắt trình chặn quảng cáo để đi tiếp.', 'wp-redirect-gateway' ),
                'popup_blocked_alert' => __( "⚠️ Trình duyệt đang chặn Cửa sổ bật lên (Popup).\n\nVui lòng cấp quyền mở Popup để tiếp tục!", 'wp-redirect-gateway' ),
                'popup_blocked_msg'   => __( '⚠️ Vui lòng cấp quyền mở Popup trên thanh địa chỉ để đi tiếp!', 'wp-redirect-gateway' ),
                'checking_pass' => __( 'ĐANG KIỂM TRA...', 'wp-redirect-gateway' ),
                'unlock_now'    => __( 'MỞ KHÓA NGAY', 'wp-redirect-gateway' ),
                'wrong_pass'    => __( 'Mật khẩu sai!', 'wp-redirect-gateway' ),
                'wait_verify'   => __( 'ĐANG CHỜ XÁC MINH...', 'wp-redirect-gateway' ),
                'retrying'      => __( 'ĐANG THỬ LẠI...', 'wp-redirect-gateway' ),
                'network_error_short' => __( 'Lỗi kết nối mạng!', 'wp-redirect-gateway' ),
                'checking_safe'       => __( 'Hệ thống đang kiểm tra an toàn, vui lòng đợi giây lát...', 'wp-redirect-gateway' ),
                'cf_load_error'       => __( 'Lỗi: Trình duyệt không thể tải Script Cloudflare. Vui lòng tắt chặn quảng cáo hoặc tải lại trang!', 'wp-redirect-gateway' ),
                'link_opened_btn'     => __( 'Đã lấy link!', 'wp-redirect-gateway' ),
                'link_opened_new_tab' => __( 'Link đích đã được mở ở Tab mới.', 'wp-redirect-gateway' ),
                'auto_retrying'       => __( 'Vấp mạng. Tự động thử lại sau 2 giây', 'wp-redirect-gateway' ),
                'pass_backend_err'    => __( 'Lỗi bảo mật: Bạn chưa mở khóa mật khẩu cho link này!', 'wp-redirect-gateway' ),
                'pls_enter_pass'      => __( 'Tốn vài giây để nhập mật khẩu thôi mà, tại sao bạn không cố gắng nhỉ?', 'wp-redirect-gateway' )
            )
        ));

        ob_start(); 
        ?>
        
        <?php if ( ! $is_unlocked ) : ?>
        <div id="wprg-pass-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-password-container">
            <div class="wprg-lock-icon">🔒</div>
            <h3 class="wprg-title wprg-text-danger"><?php esc_html_e( 'Link Yêu Cầu Mật Khẩu', 'wp-redirect-gateway' ); ?></h3>
            <p class="wprg-desc"><?php esc_html_e( 'Vui lòng nhập mật khẩu để tiếp tục lấy link đích.', 'wp-redirect-gateway' ); ?></p>
            <p id="wprg-pass-error-<?php echo esc_attr($slug); ?>" class="wprg-pass-error"></p>
            <form class="wprg-ajax-pass-form" data-slug="<?php echo esc_attr($slug); ?>">
                <input type="password" class="wprg-pass-input" placeholder="<?php esc_attr_e( 'Nhập mật khẩu vào đây...', 'wp-redirect-gateway' ); ?>" required>
                <button type="submit" class="wprg-pass-submit"><?php esc_html_e( 'MỞ KHÓA NGAY', 'wp-redirect-gateway' ); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <div id="wprg-btn-wrap-<?php echo esc_attr($slug); ?>" class="wprg-gateway-wrapper wprg-theme-default" data-slug="<?php echo esc_attr($slug); ?>" data-wait="<?php echo esc_attr($final_wait_time); ?>" data-ads="<?php echo intval( $link_data['ad_count'] ); ?>" data-logid="<?php echo esc_attr($log_id_val); ?>" style="<?php echo ( ! $is_unlocked ) ? 'display:none;' : ''; ?>">
            <h2 class="wprg-title"><?php esc_html_e( 'Trang đích đang được chuẩn bị...', 'wp-redirect-gateway' ); ?></h2>
            <p class="wprg-desc"><?php esc_html_e( 'Vui lòng hoàn thành các bước bên dưới để lấy link', 'wp-redirect-gateway' ); ?></p>
            
            <button class="wprg-action-btn">
                <?php esc_html_e( 'Bấm vào đây để tiếp tục', 'wp-redirect-gateway' ); ?>
            </button>
            <p class="wprg-status-text"></p>
        </div>
        
        <?php
        return ob_get_clean();
    }
}
new WPRG_Shortcode_Gateway();