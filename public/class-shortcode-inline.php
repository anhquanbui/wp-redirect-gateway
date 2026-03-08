<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_Shortcode_Inline {

    public function __construct() {
        add_shortcode( 'wprg_inline_button', array( $this, 'render_inline_button_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts() {
        wp_register_script( 'wprg-gateway-js', WPRG_PLUGIN_URL . 'assets/js/gateway-timer.js', array('jquery'), time(), true );
    }

    public function render_inline_button_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'slug' => '' ), $atts );
        $slug = sanitize_text_field( $atts['slug'] );

        if ( empty( $slug ) ) {
            return '<div style="padding: 10px; border: 1px solid red; color: red;">' . esc_html__( 'Lỗi: Vui lòng nhập slug của link.', 'wp-redirect-gateway' ) . '</div>';
        }

        global $wpdb;
        $table_links = $wpdb->prefix . 'rg_links';
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_links WHERE slug = %s", $slug ), ARRAY_A );
        
        if ( ! $link_data ) return '<div style="padding: 10px; border: 1px solid red; color: red;">' . esc_html__( 'Lỗi: Link không tồn tại trong hệ thống.', 'wp-redirect-gateway' ) . '</div>';

        // --- KIỂM TRA MẬT KHẨU ---
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

        $shortcodes = get_option( 'wprg_shortcodes', array() );
        $sc_id = $link_data['shortcode_id'];
        
        if ( ! isset( $shortcodes[ $sc_id ] ) ) return '<div style="padding: 10px; border: 1px solid red; color: red;">' . esc_html__( 'Lỗi: Gateway cấu hình cho link này đã bị xóa.', 'wp-redirect-gateway' ) . '</div>';
        $sc_data = $shortcodes[ $sc_id ];

        $final_wait_time = ! empty( $link_data['wait_time'] ) ? sanitize_text_field( $link_data['wait_time'] ) : sanitize_text_field( $sc_data['wait_time'] );

        $raw_aff_links = get_option( 'wprg_affiliate_links', '' );
        $aff_links_array = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_aff_links ) ) );
        if ( empty( $aff_links_array ) ) $aff_links_array = array( home_url() ); 

        $recap_site = get_option( 'wprg_recaptcha_site', '' );
        if ( ! empty( $recap_site ) ) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $recap_site ), array(), null, true );
        }

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
            'recaptcha_site' => $recap_site,
            'enable_initial_click' => get_option( 'wprg_enable_initial_click', '1' ),
            'initial_links'        => get_option( 'wprg_initial_links', array() ),
            'home_url'             => home_url(),
            'log_id'               => 0, 
            
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
                'popup_blocked_msg'   => __( '⚠️ Vui lòng cấp quyền mở Popup trên thanh địa chỉ để đi tiếp!', 'wp-redirect-gateway' )
            )
        ));

        ob_start(); 
        ?>
        
        <?php if ( ! $is_unlocked ) : ?>
        <div id="wprg-pass-wrap-<?php echo esc_attr($slug); ?>" class="wprg-password-container" style="text-align: center; padding: 20px; background: #fffcfc; border: 1px dashed #d63638; border-radius: 8px; margin: 20px auto; max-width: 350px;">
            <h4 style="margin-top: 0; margin-bottom: 10px; color: #d63638; font-size: 16px;">🔒 <?php esc_html_e( 'Yêu Cầu Mật Khẩu', 'wp-redirect-gateway' ); ?></h4>
            <p id="wprg-pass-error-<?php echo esc_attr($slug); ?>" style="color: #d63638; font-weight: bold; font-size: 13px; margin-bottom: 10px; display: none;"></p>
            <form class="wprg-ajax-pass-form" data-slug="<?php echo esc_attr($slug); ?>">
                <input type="password" class="wprg-pass-input" placeholder="<?php esc_attr_e( 'Nhập mật khẩu...', 'wp-redirect-gateway' ); ?>" style="padding: 10px; width: 100%; box-sizing: border-box; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; text-align: center;" required>
                <button type="submit" class="wprg-pass-submit" style="padding: 10px; font-size: 14px; font-weight: bold; background: #d63638; color: #fff; border: none; border-radius: 4px; cursor: pointer; width: 100%; transition: 0.3s;"><?php esc_html_e( 'MỞ KHÓA NÚT BẤM', 'wp-redirect-gateway' ); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <div id="wprg-btn-wrap-<?php echo esc_attr($slug); ?>" class="wprg-inline-container" style="<?php echo ( ! $is_unlocked ) ? 'display:none;' : ''; ?> text-align: center; padding: 20px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 8px; margin: 20px 0;">
            <p style="margin-bottom: 15px; font-weight: bold; color: #333;"><?php esc_html_e( 'Bấm vào nút bên dưới để lấy link:', 'wp-redirect-gateway' ); ?></p>
            <button id="wprg-action-btn" style="padding: 12px 30px; font-size: 16px; font-weight: bold; background: #0073aa; color: #fff; border: none; border-radius: 5px; cursor: pointer; transition: 0.3s; width: 100%; max-width: 300px;">
                <?php esc_html_e( 'BẮT ĐẦU LẤY LINK', 'wp-redirect-gateway' ); ?>
            </button>
            <p id="wprg-status-text" style="margin-top: 10px; font-size: 13px; font-style: italic; color: #888;"></p>
        </div>
        
        <?php
        return ob_get_clean();
    }
}
new WPRG_Shortcode_Inline();