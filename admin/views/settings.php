<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// 1. XỬ LÝ LƯU DỮ LIỆU KHI BẤM NÚT SUBMIT (Form Cài đặt chính)
if ( isset( $_POST['wprg_save_settings'] ) && check_admin_referer( 'wprg_settings_nonce' ) ) {
    update_option( 'wprg_affiliate_links', isset( $_POST['wprg_affiliate_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wprg_affiliate_links'] ) ) : '' );
    update_option( 'wprg_require_active_tab', isset( $_POST['wprg_require_active_tab'] ) ? '1' : '0' );
    update_option( 'wprg_single_link_mode', isset( $_POST['wprg_single_link_mode'] ) ? '1' : '0' );
    update_option( 'wprg_delete_data', isset( $_POST['wprg_delete_data'] ) ? 'yes' : 'no' );
    update_option( 'wprg_enable_initial_click', isset( $_POST['wprg_enable_initial_click'] ) ? '1' : '0' );
    update_option( 'wprg_open_link_new_tab', isset( $_POST['wprg_open_link_new_tab'] ) ? '1' : '0' );
    update_option( 'wprg_auto_retry_error', isset( $_POST['wprg_auto_retry_error'] ) ? '1' : '0' );
    update_option( 'wprg_cookie_pass_val', isset( $_POST['wprg_cookie_pass_val'] ) ? intval( $_POST['wprg_cookie_pass_val'] ) : 24 );
    update_option( 'wprg_cookie_pass_unit', isset( $_POST['wprg_cookie_pass_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_cookie_pass_unit'] ) ) : 'hours' );
    
    // Lưu cài đặt Bảo mật
    update_option( 'wprg_captcha_type', isset( $_POST['wprg_captcha_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_captcha_type'] ) ) : 'recaptcha' );
    update_option( 'wprg_recaptcha_site', isset( $_POST['wprg_recaptcha_site'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_recaptcha_site'] ) ) : '' );
    update_option( 'wprg_recaptcha_secret', isset( $_POST['wprg_recaptcha_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_recaptcha_secret'] ) ) : '' );
    update_option( 'wprg_turnstile_site', isset( $_POST['wprg_turnstile_site'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_turnstile_site'] ) ) : '' );
    update_option( 'wprg_turnstile_secret', isset( $_POST['wprg_turnstile_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_turnstile_secret'] ) ) : '' );
    update_option( 'wprg_rel_noopener', isset( $_POST['wprg_rel_noopener'] ) ? '1' : '0' );
    update_option( 'wprg_rel_noreferrer', isset( $_POST['wprg_rel_noreferrer'] ) ? '1' : '0' );

    $initial_links = isset( $_POST['wprg_initial_links'] ) && is_array( $_POST['wprg_initial_links'] ) ? array_filter( array_map( 'esc_url_raw', wp_unslash( $_POST['wprg_initial_links'] ) ) ) : array();
    update_option( 'wprg_initial_links', $initial_links );

    $active_tab = isset( $_POST['wprg_active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_active_tab'] ) ) : 'tab-ads';
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã lưu cài đặt thành công.', 'redirect-gateway-manager' ) . '</p></div>';
} 
// 2. XỬ LÝ LƯU RIÊNG CHO PHẦN AUTO BACKUP
elseif ( isset( $_POST['wprg_save_backup_settings'] ) && check_admin_referer( 'wprg_backup_settings_nonce' ) ) {
    $old_enable = get_option( 'wprg_enable_auto_backup', '0' );
    $old_time   = get_option( 'wprg_backup_time', '00:00' );
    
    $new_enable = isset( $_POST['wprg_enable_auto_backup'] ) ? '1' : '0';
    $new_time   = isset( $_POST['wprg_backup_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_backup_time'] ) ) : '00:00';
    $new_limit  = isset( $_POST['wprg_backup_limit'] ) ? intval( $_POST['wprg_backup_limit'] ) : 7;
    
    update_option( 'wprg_enable_auto_backup', $new_enable );
    update_option( 'wprg_backup_time', $new_time );
    update_option( 'wprg_backup_limit', $new_limit );

    if ( $old_enable !== $new_enable || $old_time !== $new_time ) {
        if ( function_exists('wprg_reschedule_backup_cron') ) { wprg_reschedule_backup_cron(); }
    }
    
    $active_tab = 'tab-import-export'; 
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã cập nhật thiết lập Auto Backup thành công.', 'redirect-gateway-manager' ) . '</p></div>';
} else {
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'tab-ads'; 
}

// 3. XỬ LÝ THÔNG BÁO TỪ CÁC HÀNH ĐỘNG BACKUP
if ( isset( $_GET['wprg_import_success'] ) && $_GET['wprg_import_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã nạp file cấu hình JSON thành công.', 'redirect-gateway-manager' ) . '</p></div>';
    $active_tab = 'tab-import-export';
}

if ( isset( $_GET['wprg_restore_success'] ) && $_GET['wprg_restore_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible" style="border-left-color: #d63638;"><p><strong>' . esc_html__( 'CẢNH BÁO: Đã KHÔI PHỤC TOÀN BỘ dữ liệu từ file Backup JSON thành công!', 'redirect-gateway-manager' ) . '</strong></p></div>';
    $active_tab = 'tab-import-export';
}

if ( isset( $_GET['wprg_delete_success'] ) && $_GET['wprg_delete_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã xóa file backup thành công.', 'redirect-gateway-manager' ) . '</p></div>';
    $active_tab = 'tab-import-export';
}

// 4. LẤY DỮ LIỆU HIỂN THỊ
$current_links = get_option( 'wprg_affiliate_links', '' );
$require_active_tab = get_option( 'wprg_require_active_tab', '1' ); 
$single_link_mode = get_option( 'wprg_single_link_mode', '0' );   
$delete_data = get_option( 'wprg_delete_data', 'no' );
$enable_initial_click = get_option( 'wprg_enable_initial_click', '1' );
$initial_links = get_option( 'wprg_initial_links', array() );
$open_new_tab = get_option( 'wprg_open_link_new_tab', '0' );
$auto_retry_error = get_option( 'wprg_auto_retry_error', '0' );
$cookie_pass_val = get_option( 'wprg_cookie_pass_val', 24 );
$cookie_pass_unit = get_option( 'wprg_cookie_pass_unit', 'hours' );

// Lấy dữ liệu Bảo mật
$captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
$recap_site = get_option( 'wprg_recaptcha_site', '' );
$recap_secret = get_option( 'wprg_recaptcha_secret', '' );
$ts_site = get_option( 'wprg_turnstile_site', '' );
$ts_secret = get_option( 'wprg_turnstile_secret', '' );
$rel_noopener = get_option( 'wprg_rel_noopener', '1' ); 
$rel_noreferrer = get_option( 'wprg_rel_noreferrer', '0' ); 

// Lấy dữ liệu Backup
$enable_auto_backup = get_option( 'wprg_enable_auto_backup', '0' );
$backup_time = get_option( 'wprg_backup_time', '00:00' );
$backup_limit = get_option( 'wprg_backup_limit', 7 );

// QUÉT THƯ MỤC LẤY DANH SÁCH FILE BACKUP TRÊN SERVER
$upload_dir = wp_upload_dir();
$backup_dir = $upload_dir['basedir'] . '/wprg-backups';
$auto_backup_files = array();
if ( file_exists( $backup_dir ) ) {
    $files = glob( $backup_dir . '/wprg-autobackup-*.json' );
    if ( $files ) {
        rsort( $files ); 
        $auto_backup_files = $files;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Cài đặt Hệ thống Gateway', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper" style="margin-top: 15px; border-bottom: 1px solid #c3c4c7;">
        <a href="#tab-ads" class="nav-tab <?php echo ($active_tab === 'tab-ads') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ads"><?php esc_html_e( '🎯 Quảng cáo & Link', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-ux" class="nav-tab <?php echo ($active_tab === 'tab-ux') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ux"><?php esc_html_e( '🎨 Trải nghiệm (UX)', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-security" class="nav-tab <?php echo ($active_tab === 'tab-security') ? 'nav-tab-active' : ''; ?>" data-tab="tab-security"><?php esc_html_e( '🛡️ Bảo mật & Chống Bot', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-system" class="nav-tab <?php echo ($active_tab === 'tab-system') ? 'nav-tab-active' : ''; ?>" data-tab="tab-system"><?php esc_html_e( '⚙️ Hệ thống', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-import-export" class="nav-tab <?php echo ($active_tab === 'tab-import-export') ? 'nav-tab-active' : ''; ?>" data-tab="tab-import-export"><?php esc_html_e( '🔄 Nhập/Xuất & Backup', 'redirect-gateway-manager' ); ?></a>
    </h2>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; max-width: 800px;">
        
        <form method="post" action="" id="wprg-main-settings-form">
            <?php wp_nonce_field( 'wprg_settings_nonce' ); ?>
            <input type="hidden" name="wprg_active_tab" id="wprg_active_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div id="tab-ads" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ads') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Cấu hình Link Affiliate (Dùng cho Watch Ad)', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wprg_affiliate_links"><?php echo wp_kses_post( __( 'Danh sách Link Xoay vòng<br><small>(Mỗi link 1 dòng)</small>', 'redirect-gateway-manager' ) ); ?></label></th>
                        <td>
                            <textarea name="wprg_affiliate_links" id="wprg_affiliate_links" rows="6" class="large-text code"><?php echo esc_textarea( $current_links ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Các link này sẽ được mở xoay vòng (Round-Robin) từ lần click thứ 2 trở đi.', 'redirect-gateway-manager' ); ?></p>
                            <p style="color: #d63638; font-weight: 500; font-size: 13px; margin-top: 5px;">
                                <?php esc_html_e( '* Mẹo SEO: Hệ thống Gateway tự động chặn mọi bot tìm kiếm đi theo các link này (tương đương với nofollow an toàn).', 'redirect-gateway-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Quảng cáo cho Click Đệm', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_enable_initial_click" id="wprg_enable_initial_click" value="1" <?php checked( $enable_initial_click, '1' ); ?>>
                                <strong><?php esc_html_e( 'Bật tính năng Click Đệm (Mở tab khi click "Click here to continue")', 'redirect-gateway-manager' ); ?></strong>
                            </label>
                            
                            <div id="wprg-initial-links-container" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; <?php echo $enable_initial_click ? '' : 'display:none;'; ?>">
                                <h4 style="margin-top:0;"><?php esc_html_e( 'Các Tab mở kèm (Tùy chọn)', 'redirect-gateway-manager' ); ?></h4>
                                <div id="wprg-initial-links-wrapper">
                                    <?php if ( ! empty( $initial_links ) ) : ?>
                                        <?php foreach ( $initial_links as $link ) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <input type="url" name="wprg_initial_links[]" class="large-text" value="<?php echo esc_url( $link ); ?>" placeholder="https://..." style="width: 80%;" />
                                                <button type="button" class="button remove-link" style="color: #d63638; border-color: #d63638;"><?php esc_html_e( 'Xóa', 'redirect-gateway-manager' ); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="wprg-add-initial-link" class="button">+ <?php esc_html_e( 'Thêm Link mở kèm', 'redirect-gateway-manager' ); ?></button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-ux" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ux') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Thiết lập Trải nghiệm Người dùng', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo wp_kses_post( __( 'Bắt buộc ở lại Tab', 'redirect-gateway-manager' ) ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_require_active_tab" value="1" <?php checked( $require_active_tab, '1' ); ?>>
                                <?php esc_html_e( 'Bật (Nếu người dùng chuyển sang tab khác, thời gian sẽ ngừng đếm)', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Giới hạn mở Link', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_single_link_mode" value="1" <?php checked( $single_link_mode, '1' ); ?>>
                                <?php esc_html_e( 'Chỉ cho phép mở 1 link cùng lúc', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Mở link đích ở Tab mới', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_open_link_new_tab" value="1" <?php checked( $open_new_tab, '1' ); ?>>
                                <?php esc_html_e( 'Bật (Khi người dùng nhấn nút lấy link cuối cùng, trang đích sẽ được mở trong một tab mới)', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Tự động thử lại (Auto-Retry)', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_auto_retry_error" value="1" <?php checked( $auto_retry_error, '1' ); ?>>
                                <?php esc_html_e( 'Bật (Tự động thử lại 2 lần nếu mất kết nối mạng hoặc bị WAF/Cloudflare chặn, thay vì bắt khách tự bấm)', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-security" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-security') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Bảo vệ Cổng Gateway', 'redirect-gateway-manager' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Thời hạn lưu Mật khẩu', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <input type="number" name="wprg_cookie_pass_val" value="<?php echo esc_attr( $cookie_pass_val ); ?>" min="1" style="width: 80px;">
                            <select name="wprg_cookie_pass_unit">
                                <option value="seconds" <?php selected( $cookie_pass_unit, 'seconds' ); ?>><?php esc_html_e( 'Giây', 'redirect-gateway-manager' ); ?></option>
                                <option value="minutes" <?php selected( $cookie_pass_unit, 'minutes' ); ?>><?php esc_html_e( 'Phút', 'redirect-gateway-manager' ); ?></option>
                                <option value="hours" <?php selected( $cookie_pass_unit, 'hours' ); ?>><?php esc_html_e( 'Giờ', 'redirect-gateway-manager' ); ?></option>
                                <option value="days" <?php selected( $cookie_pass_unit, 'days' ); ?>><?php esc_html_e( 'Ngày', 'redirect-gateway-manager' ); ?></option>
                                <option value="weeks" <?php selected( $cookie_pass_unit, 'weeks' ); ?>><?php esc_html_e( 'Tuần', 'redirect-gateway-manager' ); ?></option>
                                <option value="months" <?php selected( $cookie_pass_unit, 'months' ); ?>><?php esc_html_e( 'Tháng', 'redirect-gateway-manager' ); ?></option>
                                <option value="years" <?php selected( $cookie_pass_unit, 'years' ); ?>><?php esc_html_e( 'Năm', 'redirect-gateway-manager' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Định mức thời gian trình duyệt sẽ "nhớ" mật khẩu mở khóa link (Mặc định: 24 Giờ).', 'redirect-gateway-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Hệ thống chống Bot', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="wprg_captcha_type" value="recaptcha" <?php checked($captcha_type, 'recaptcha'); ?> onclick="document.getElementById('wrap-recaptcha').style.display='block'; document.getElementById('wrap-turnstile').style.display='none';"> Google reCAPTCHA v3
                            </label>
                            <label>
                                <input type="radio" name="wprg_captcha_type" value="turnstile" <?php checked($captcha_type, 'turnstile'); ?> onclick="document.getElementById('wrap-recaptcha').style.display='none'; document.getElementById('wrap-turnstile').style.display='block';"> Cloudflare Turnstile
                            </label>
                        </td>
                    </tr>
                </table>

                <div id="wrap-recaptcha" style="<?php echo ($captcha_type === 'recaptcha') ? 'display:block;' : 'display:none;'; ?> background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 10px;">
                    <h4 style="margin-top:0;">Google reCAPTCHA v3</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_recaptcha_site"><?php esc_html_e( 'Site Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_recaptcha_site" id="wprg_recaptcha_site" type="text" class="regular-text" value="<?php echo esc_attr( $recap_site ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_recaptcha_secret"><?php esc_html_e( 'Secret Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_recaptcha_secret" id="wprg_recaptcha_secret" type="text" class="regular-text" value="<?php echo esc_attr( $recap_secret ); ?>"></td>
                        </tr>
                    </table>
                </div>

                <div id="wrap-turnstile" style="<?php echo ($captcha_type === 'turnstile') ? 'display:block;' : 'display:none;'; ?> background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 10px;">
                    <h4 style="margin-top:0; color: #f38020;">Cloudflare Turnstile</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_turnstile_site"><?php esc_html_e( 'Site Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_turnstile_site" id="wprg_turnstile_site" type="text" class="regular-text" value="<?php echo esc_attr( $ts_site ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_turnstile_secret"><?php esc_html_e( 'Secret Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_turnstile_secret" id="wprg_turnstile_secret" type="text" class="regular-text" value="<?php echo esc_attr( $ts_secret ); ?>"></td>
                        </tr>
                    </table>
                    <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Mẹo: Cloudflare Turnstile nhẹ hơn, bảo vệ quyền riêng tư và không yêu cầu khách phải giải mã hình ảnh.', 'redirect-gateway-manager' ); ?></p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Thuộc tính mở Link', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label style="margin-right: 20px;">
                                <input type="checkbox" name="wprg_rel_noopener" value="1" <?php checked( $rel_noopener, '1' ); ?>>
                                <code>rel="noopener"</code> <?php esc_html_e( '(Nên Bật)', 'redirect-gateway-manager' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="wprg_rel_noreferrer" value="1" <?php checked( $rel_noreferrer, '1' ); ?>>
                                <code>rel="noreferrer"</code> <?php esc_html_e( '(Nên Tắt nếu làm Affiliate)', 'redirect-gateway-manager' ); ?>
                            </label>
                            <p class="description" style="margin-top: 5px;"><?php esc_html_e( 'Tùy chọn noopener giúp chống hack Tabnabbing cực tốt. Tùy chọn noreferrer sẽ ẩn nguồn website của bạn nhưng có thể làm mất hoa hồng từ các mạng Affiliate (AccessTrade, Shopee...).', 'redirect-gateway-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-system" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-system') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #d63638;"><?php esc_html_e( 'Dữ liệu & Gỡ cài đặt', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Xóa dữ liệu', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_delete_data" value="yes" <?php checked( $delete_data, 'yes' ); ?>>
                                <span style="color: #d63638; font-weight: 500;"><?php esc_html_e( 'Xóa toàn bộ dữ liệu khi gỡ plugin này', 'redirect-gateway-manager' ); ?></span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="wprg-submit-wrapper" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:none;' : 'display:block;'; ?>">
                <p class="submit" style="border-top: 1px solid #f0f0f1; padding-top: 15px; margin-top: 20px;">
                    <input type="submit" name="wprg_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Lưu Cài Đặt', 'redirect-gateway-manager' ); ?>">
                </p>
            </div>
        </form>

        <div id="tab-import-export" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:block;' : 'display:none;'; ?>">
            
            <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Nhập / Xuất Cấu Hình (Chỉ Setting)', 'redirect-gateway-manager' ); ?></h3>
            <table class="form-table" style="margin-bottom: 30px;">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Xuất Thiết Lập', 'redirect-gateway-manager' ); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_export_settings">
                            <?php wp_nonce_field( 'wprg_export_nonce_action', 'wprg_export_nonce' ); ?>
                            <?php submit_button( esc_html__( 'Tải file JSON', 'redirect-gateway-manager' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Nhập Thiết Lập', 'redirect-gateway-manager' ); ?></th>
                    <td>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_import_settings">
                            <?php wp_nonce_field( 'wprg_import_nonce_action', 'wprg_import_nonce' ); ?>
                            <input type="file" name="wprg_import_file" accept=".json" required />
                            <br><br>
                            <?php submit_button( esc_html__( 'Nạp cấu hình', 'redirect-gateway-manager' ), 'primary', 'submit', false ); ?>
                            <p class="description" style="color:#d63638;"><?php esc_html_e( 'Cảnh báo: Hành động này sẽ ghi đè toàn bộ thiết lập hiện tại!', 'redirect-gateway-manager' ); ?></p>
                        </form>
                    </td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 1px dashed #ccc; margin: 30px 0;">

            <h3 style="margin-top: 0; color: #0073aa;">📦 <?php esc_html_e( 'Quản lý Backup Dữ Liệu Toàn Diện', 'redirect-gateway-manager' ); ?></h3>
            <table class="form-table">
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sao lưu thủ công', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-bottom: 25px;">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_backup">
                            <?php wp_nonce_field( 'wprg_backup_action', 'wprg_backup_nonce' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-download" style="margin-top:3px;"></span> <?php esc_html_e( 'Tải Full Backup (JSON)', 'redirect-gateway-manager' ); ?>
                            </button>
                            <p class="description"><?php esc_html_e( 'Gói toàn bộ Cài đặt, Danh sách Links và Lịch sử Logs thành 1 file duy nhất để tải về máy.', 'redirect-gateway-manager' ); ?></p>
                        </form>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Cấu hình Backup tự động', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <form method="post" action="">
                            <?php wp_nonce_field( 'wprg_backup_settings_nonce' ); ?>
                            <label style="display:block; margin-bottom:10px;">
                                <input type="checkbox" name="wprg_enable_auto_backup" id="wprg_enable_auto_backup" value="1" <?php checked( $enable_auto_backup, '1' ); ?>>
                                <strong><?php esc_html_e( 'Tự động sao lưu thiết lập và database mỗi ngày', 'redirect-gateway-manager' ); ?></strong>
                            </label>
                            
                            <div id="wprg-backup-time-wrap" style="margin-left: 25px; margin-bottom: 15px; <?php echo ($enable_auto_backup === '1') ? 'display:block;' : 'display:none;'; ?>">
                                <div style="display: flex; gap: 20px; margin-bottom: 5px;">
                                    <div>
                                        <label for="wprg_backup_time" style="font-weight: 500; margin-right: 5px;"><?php esc_html_e( 'Thời gian chạy:', 'redirect-gateway-manager' ); ?></label>
                                        <input type="time" name="wprg_backup_time" id="wprg_backup_time" value="<?php echo esc_attr( $backup_time ); ?>" style="padding: 3px 8px;">
                                    </div>
                                    <div>
                                        <label for="wprg_backup_limit" style="font-weight: 500; margin-right: 5px;"><?php esc_html_e( 'Số bản lưu tối đa:', 'redirect-gateway-manager' ); ?></label>
                                        <input type="number" name="wprg_backup_limit" id="wprg_backup_limit" value="<?php echo esc_attr( $backup_limit ); ?>" min="1" max="50" style="padding: 3px 8px; width: 60px;">
                                    </div>
                                </div>
                                <p class="description"><?php echo wp_kses_post( __( 'Hệ thống sẽ lưu file vào <code>wp-content/uploads/wprg-backups/</code>. Vượt quá số lượng sẽ tự động xóa bản cũ nhất.', 'redirect-gateway-manager' ) ); ?></p>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <input type="submit" name="wprg_save_backup_settings" class="button button-small" value="<?php esc_attr_e( 'Lưu thiết lập Auto Backup', 'redirect-gateway-manager' ); ?>">
                            </div>
                        </form>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Bản sao lưu trên Server', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <?php if ( empty( $auto_backup_files ) ) : ?>
                            <p style="color: #666; font-style: italic;"><?php esc_html_e( 'Chưa có bản sao lưu tự động nào.', 'redirect-gateway-manager' ); ?></p>
                        <?php else : ?>
                            <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 600px; margin-top: 5px; background: #fff;">
                                <table class="wp-list-table widefat striped" style="margin: 0; border: none; width: 100%;">
                                    <thead style="position: sticky; top: 0; background: #f6f7f7; box-shadow: 0 1px 1px rgba(0,0,0,.04); z-index: 1;">
                                        <tr>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Tên File Backup', 'redirect-gateway-manager' ); ?></th>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Dung lượng', 'redirect-gateway-manager' ); ?></th>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Hành động', 'redirect-gateway-manager' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $auto_backup_files as $file ) : 
                                            $filename = basename( $file );
                                            $filesize = size_format( filesize( $file ) );
                                            $filetime = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), filemtime( $file ) );
                                        ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                                        <div>
                                                            <strong><?php echo esc_html( $filename ); ?></strong><br>
                                                            <span style="color: #666; font-size: 12px;"><?php echo esc_html( $filetime ); ?></span>
                                                        </div>
                                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;" title="<?php esc_attr_e( 'Xóa file này', 'redirect-gateway-manager' ); ?>">
                                                            <input type="hidden" name="action" value="wprg_delete_backup">
                                                            <input type="hidden" name="backup_file" value="<?php echo esc_attr( $filename ); ?>">
                                                            <?php wp_nonce_field( 'wprg_delete_backup_action', 'wprg_delete_backup_nonce' ); ?>
                                                            <button type="submit" style="background: none; border: none; padding: 0; color: #d63638; cursor: pointer; display: flex; align-items: center;" onclick="return confirm('<?php echo esc_js( __( 'Bạn có chắc chắn muốn XÓA VĨNH VIỄN file backup này không?', 'redirect-gateway-manager' ) ); ?>');">
                                                                <span class="dashicons dashicons-no-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                                <td style="vertical-align: middle;"><?php echo esc_html( $filesize ); ?></td>
                                                <td style="vertical-align: middle;">
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                                                        <input type="hidden" name="action" value="wprg_auto_restore">
                                                        <input type="hidden" name="backup_file" value="<?php echo esc_attr( $filename ); ?>">
                                                        <?php wp_nonce_field( 'wprg_auto_restore_action', 'wprg_auto_restore_nonce' ); ?>
                                                        <button type="submit" class="button button-primary button-small" onclick="return confirm('<?php echo esc_js( __( 'CẢNH BÁO NGUY HIỂM: Hành động này sẽ XÓA SẠCH dữ liệu hiện tại và khôi phục từ bản backup này. Bạn chắc chắn chứ?', 'redirect-gateway-manager' ) ); ?>');">
                                                            <?php esc_html_e( 'Khôi phục ngay', 'redirect-gateway-manager' ); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="description" style="margin-top: 10px;">
                                <?php 
                                /* translators: %d: Maximum number of backups */
                                printf( esc_html__( 'Đang hiển thị các file backup tự động trên Server (Tối đa %d bản).', 'redirect-gateway-manager' ), intval($backup_limit) ); 
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Khôi phục từ máy tính', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_restore">
                            <?php wp_nonce_field( 'wprg_restore_action', 'wprg_restore_nonce' ); ?>
                            <input type="file" name="wprg_restore_file" accept=".json" required />
                            <br><br>
                            <button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'CẢNH BÁO NGUY HIỂM: Bạn có chắc chắn muốn ghi đè toàn bộ Cài đặt, Link và Logs bằng file tải lên không?', 'redirect-gateway-manager' ) ); ?>');">
                                <span class="dashicons dashicons-update" style="margin-top:3px;"></span> <?php esc_html_e( 'Up file & Phục hồi', 'redirect-gateway-manager' ); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.wprg-tab-content');
    const activeTabInput = document.getElementById('wprg_active_tab');
    const submitBtnWrap = document.getElementById('wprg-submit-wrapper');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            contents.forEach(c => c.style.display = 'none');
            this.classList.add('nav-tab-active');
            const targetId = this.getAttribute('data-tab');
            document.getElementById(targetId).style.display = 'block';
            if (activeTabInput) activeTabInput.value = targetId;
            if(submitBtnWrap) { submitBtnWrap.style.display = (targetId === 'tab-import-export') ? 'none' : 'block'; }
        });
    });

    const cbInitClick = document.getElementById('wprg_enable_initial_click');
    const wrapInitClick = document.getElementById('wprg-initial-links-container');
    if(cbInitClick && wrapInitClick) {
        cbInitClick.addEventListener('change', function() { wrapInitClick.style.display = this.checked ? 'block' : 'none'; });
    }

    const wrapper = document.getElementById('wprg-initial-links-wrapper');
    const addBtn = document.getElementById('wprg-add-initial-link');
    if(addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = document.createElement('div');
            row.style.marginBottom = '10px';
            row.innerHTML = '<input type="url" name="wprg_initial_links[]" class="large-text" value="" placeholder="https://..." style="width: 80%;" /> <button type="button" class="button remove-link" style="color: #d63638; border-color: #d63638;"><?php echo esc_js( __( 'Xóa', 'redirect-gateway-manager' ) ); ?></button>';
            wrapper.appendChild(row);
        });
    }

    if(wrapper) {
        wrapper.addEventListener('click', function(e) {
            if(e.target.classList.contains('remove-link')) { e.preventDefault(); e.target.parentElement.remove(); }
        });
    }

    const cbAutoBackup = document.getElementById('wprg_enable_auto_backup');
    const wrapBackupTime = document.getElementById('wprg-backup-time-wrap');
    if(cbAutoBackup && wrapBackupTime) {
        cbAutoBackup.addEventListener('change', function() {
            wrapBackupTime.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>