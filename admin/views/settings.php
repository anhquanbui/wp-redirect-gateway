<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// XỬ LÝ LƯU DỮ LIỆU KHI BẤM NÚT SUBMIT (Form chính)
if ( isset( $_POST['wprg_save_settings'] ) && check_admin_referer( 'wprg_settings_nonce' ) ) {
    update_option( 'wprg_affiliate_links', sanitize_textarea_field( $_POST['wprg_affiliate_links'] ) );
    update_option( 'wprg_require_active_tab', isset( $_POST['wprg_require_active_tab'] ) ? '1' : '0' );
    update_option( 'wprg_single_link_mode', isset( $_POST['wprg_single_link_mode'] ) ? '1' : '0' );
    update_option( 'wprg_recaptcha_site', sanitize_text_field( $_POST['wprg_recaptcha_site'] ) );
    update_option( 'wprg_recaptcha_secret', sanitize_text_field( $_POST['wprg_recaptcha_secret'] ) );
    update_option( 'wprg_delete_data', isset( $_POST['wprg_delete_data'] ) ? 'yes' : 'no' );
    
    // Xử lý lưu Auto Backup chuẩn xác
    update_option( 'wprg_enable_auto_backup', isset( $_POST['wprg_enable_auto_backup'] ) ? '1' : '0' );
    
    update_option( 'wprg_enable_initial_click', isset( $_POST['wprg_enable_initial_click'] ) ? '1' : '0' );
    
    $initial_links = isset( $_POST['wprg_initial_links'] ) && is_array( $_POST['wprg_initial_links'] ) ? array_filter( array_map( 'esc_url_raw', $_POST['wprg_initial_links'] ) ) : array();
    update_option( 'wprg_initial_links', $initial_links );
    
    $active_tab = isset($_POST['wprg_active_tab']) ? sanitize_text_field($_POST['wprg_active_tab']) : 'tab-ads';
    
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã lưu cài đặt thành công.', 'wp-redirect-gateway' ) . '</p></div>';
} else {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'tab-ads'; 
}

// THÔNG BÁO TỪ TRANG XỬ LÝ IMPORT
if ( isset( $_GET['wprg_import_success'] ) && $_GET['wprg_import_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã nạp file cấu hình JSON thành công.', 'wp-redirect-gateway' ) . '</p></div>';
    $active_tab = 'tab-import-export'; // Nếu import thành công, giữ ở tab Nhập/Xuất
}

if ( isset( $_GET['wprg_restore_success'] ) && $_GET['wprg_restore_success'] == '1' ) {
    // Sử dụng wp_kses_post để cho phép thẻ <strong> hiển thị trong bản dịch
    echo '<div class="notice notice-success is-dismissible" style="border-left-color: #d63638;"><p><strong>' . wp_kses_post( __( 'CẢNH BÁO: Đã KHÔI PHỤC TOÀN BỘ dữ liệu từ file Backup JSON thành công!', 'wp-redirect-gateway' ) ) . '</strong></p></div>';
    $active_tab = 'tab-import-export';
}

// LẤY DỮ LIỆU HIỂN THỊ
$current_links = get_option( 'wprg_affiliate_links', '' );
$require_active_tab = get_option( 'wprg_require_active_tab', '1' ); 
$single_link_mode = get_option( 'wprg_single_link_mode', '0' );   
$recap_site = get_option( 'wprg_recaptcha_site', '' );
$recap_secret = get_option( 'wprg_recaptcha_secret', '' );
$delete_data = get_option( 'wprg_delete_data', 'no' );
$enable_initial_click = get_option( 'wprg_enable_initial_click', '1' );
$initial_links = get_option( 'wprg_initial_links', array() );
$enable_auto_backup = get_option( 'wprg_enable_auto_backup', '0' );
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Cài đặt Hệ thống Gateway', 'wp-redirect-gateway' ); ?></h1>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper" style="margin-top: 15px; border-bottom: 1px solid #c3c4c7;">
        <a href="#tab-ads" class="nav-tab <?php echo ($active_tab === 'tab-ads') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ads"><?php esc_html_e( '🎯 Quảng cáo & Link', 'wp-redirect-gateway' ); ?></a>
        <a href="#tab-ux" class="nav-tab <?php echo ($active_tab === 'tab-ux') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ux"><?php esc_html_e( '🎨 Trải nghiệm (UX)', 'wp-redirect-gateway' ); ?></a>
        <a href="#tab-security" class="nav-tab <?php echo ($active_tab === 'tab-security') ? 'nav-tab-active' : ''; ?>" data-tab="tab-security"><?php esc_html_e( '🛡️ Bảo mật & Chống Bot', 'wp-redirect-gateway' ); ?></a>
        <a href="#tab-system" class="nav-tab <?php echo ($active_tab === 'tab-system') ? 'nav-tab-active' : ''; ?>" data-tab="tab-system"><?php esc_html_e( '⚙️ Hệ thống', 'wp-redirect-gateway' ); ?></a>
        <a href="#tab-import-export" class="nav-tab <?php echo ($active_tab === 'tab-import-export') ? 'nav-tab-active' : ''; ?>" data-tab="tab-import-export"><?php esc_html_e( '🔄 Nhập/Xuất', 'wp-redirect-gateway' ); ?></a>
    </h2>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; max-width: 800px;">
        
        <form method="post" action="" id="wprg-main-settings-form">
            <?php wp_nonce_field( 'wprg_settings_nonce' ); ?>
            <input type="hidden" name="wprg_active_tab" id="wprg_active_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div id="tab-ads" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ads') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Cấu hình Link Affiliate (Dùng cho Watch Ad)', 'wp-redirect-gateway' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wprg_affiliate_links"><?php echo wp_kses_post( __( 'Danh sách Link Xoay vòng<br><small>(Mỗi link 1 dòng)</small>', 'wp-redirect-gateway' ) ); ?></label></th>
                        <td>
                            <textarea name="wprg_affiliate_links" id="wprg_affiliate_links" rows="6" class="large-text code"><?php echo esc_textarea( $current_links ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Các link này sẽ được mở xoay vòng (Round-Robin) từ lần click thứ 2 trở đi.', 'wp-redirect-gateway' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Quảng cáo cho Click Đệm', 'wp-redirect-gateway' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_enable_initial_click" id="wprg_enable_initial_click" value="1" <?php checked( $enable_initial_click, '1' ); ?>>
                                <strong><?php esc_html_e( 'Bật tính năng Click Đệm (Mở tab khi click "Click here to continue")', 'wp-redirect-gateway' ); ?></strong>
                            </label>
                            
                            <div id="wprg-initial-links-container" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; <?php echo $enable_initial_click ? '' : 'display:none;'; ?>">
                                <h4 style="margin-top:0;"><?php esc_html_e( 'Các Tab mở kèm (Tùy chọn)', 'wp-redirect-gateway' ); ?></h4>
                                <div id="wprg-initial-links-wrapper">
                                    <?php if ( ! empty( $initial_links ) ) : ?>
                                        <?php foreach ( $initial_links as $link ) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <input type="url" name="wprg_initial_links[]" class="large-text" value="<?php echo esc_url( $link ); ?>" placeholder="https://..." style="width: 80%;" />
                                                <button type="button" class="button remove-link" style="color: #d63638; border-color: #d63638;"><?php esc_html_e( 'Xóa', 'wp-redirect-gateway' ); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="wprg-add-initial-link" class="button">+ <?php esc_html_e( 'Thêm Link mở kèm', 'wp-redirect-gateway' ); ?></button>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-ux" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ux') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Thiết lập Trải nghiệm Người dùng', 'wp-redirect-gateway' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo wp_kses_post( __( 'Bắt buộc ở lại Tab', 'wp-redirect-gateway' ) ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_require_active_tab" value="1" <?php checked( $require_active_tab, '1' ); ?>>
                                <?php esc_html_e( 'Bật (Nếu người dùng chuyển sang tab khác, thời gian sẽ ngừng đếm)', 'wp-redirect-gateway' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Giới hạn mở Link', 'wp-redirect-gateway' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_single_link_mode" value="1" <?php checked( $single_link_mode, '1' ); ?>>
                                <?php esc_html_e( 'Chỉ cho phép mở 1 link cùng lúc', 'wp-redirect-gateway' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-security" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-security') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Thiết lập Google reCAPTCHA v3', 'wp-redirect-gateway' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wprg_recaptcha_site"><?php esc_html_e( 'Site Key', 'wp-redirect-gateway' ); ?></label></th>
                        <td><input name="wprg_recaptcha_site" type="text" class="regular-text" value="<?php echo esc_attr( $recap_site ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wprg_recaptcha_secret"><?php esc_html_e( 'Secret Key', 'wp-redirect-gateway' ); ?></label></th>
                        <td><input name="wprg_recaptcha_secret" type="text" class="regular-text" value="<?php echo esc_attr( $recap_secret ); ?>"></td>
                    </tr>
                </table>
            </div>

            <div id="tab-system" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-system') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #0073aa;"><?php esc_html_e( 'Sao lưu hệ thống (Cron)', 'wp-redirect-gateway' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Backup Tự Động', 'wp-redirect-gateway' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_enable_auto_backup" value="1" <?php checked( $enable_auto_backup, '1' ); ?>>
                                <strong><?php esc_html_e( 'Tự động sao lưu thiết lập và database mỗi ngày 1 lần', 'wp-redirect-gateway' ); ?></strong>
                            </label>
                            <p class="description"><?php echo wp_kses_post( __( 'Hệ thống sẽ chạy ngầm và lưu file vào thư mục <code>wp-content/uploads/wprg-backups/</code>. Tối đa lưu 7 bản mới nhất.', 'wp-redirect-gateway' ) ); ?></p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top: 30px; color: #d63638;"><?php esc_html_e( 'Dữ liệu & Gỡ cài đặt', 'wp-redirect-gateway' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Xóa dữ liệu', 'wp-redirect-gateway' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_delete_data" value="yes" <?php checked( $delete_data, 'yes' ); ?>>
                                <span style="color: #d63638; font-weight: 500;"><?php esc_html_e( 'Xóa toàn bộ dữ liệu khi gỡ plugin này', 'wp-redirect-gateway' ); ?></span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="wprg-submit-wrapper" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:none;' : 'display:block;'; ?>">
                <p class="submit" style="border-top: 1px solid #f0f0f1; padding-top: 15px; margin-top: 20px;">
                    <input type="submit" name="wprg_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Lưu Cài Đặt', 'wp-redirect-gateway' ); ?>">
                </p>
            </div>
        </form>

        <div id="tab-import-export" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:block;' : 'display:none;'; ?>">
            <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Nhập / Xuất Cấu Hình (JSON)', 'wp-redirect-gateway' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Xuất Thiết Lập', 'wp-redirect-gateway' ); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_export_settings">
                            <?php wp_nonce_field( 'wprg_export_nonce_action', 'wprg_export_nonce' ); ?>
                            <?php submit_button( esc_html__( 'Tải file JSON', 'wp-redirect-gateway' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Nhập Thiết Lập', 'wp-redirect-gateway' ); ?></th>
                    <td>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_import_settings">
                            <?php wp_nonce_field( 'wprg_import_nonce_action', 'wprg_import_nonce' ); ?>
                            <input type="file" name="wprg_import_file" accept=".json" required />
                            <br><br>
                            <?php submit_button( esc_html__( 'Nạp cấu hình', 'wp-redirect-gateway' ), 'primary', 'submit', false ); ?>
                            <p class="description" style="color:#d63638;"><?php esc_html_e( 'Cảnh báo: Hành động này sẽ ghi đè toàn bộ thiết lập hiện tại!', 'wp-redirect-gateway' ); ?></p>
                        </form>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top: 30px; color: #d63638;">📦 <?php esc_html_e( 'Backup Toàn Bộ Dữ Liệu', 'wp-redirect-gateway' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sao lưu thủ công', 'wp-redirect-gateway' ); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_backup">
                            <?php wp_nonce_field( 'wprg_backup_action', 'wprg_backup_nonce' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-download" style="margin-top:3px;"></span> <?php esc_html_e( 'Tải Full Backup (JSON)', 'wp-redirect-gateway' ); ?>
                            </button>
                            <p class="description"><?php esc_html_e( 'Gói toàn bộ Cài đặt, Danh sách Links và Lịch sử Logs thành 1 file duy nhất để tải về máy.', 'wp-redirect-gateway' ); ?></p>
                        </form>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Khôi phục hệ thống (Restore)', 'wp-redirect-gateway' ); ?></th>
                    <td>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_restore">
                            <?php wp_nonce_field( 'wprg_restore_action', 'wprg_restore_nonce' ); ?>
                            <input type="file" name="wprg_restore_file" accept=".json" required />
                            <br><br>
                            <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'CẢNH BÁO NGUY HIỂM: Hành động này sẽ XÓA SẠCH toàn bộ Cài đặt, Danh sách Link và Lịch sử hiện tại trên website này, thay thế bằng dữ liệu từ file backup. Bạn có chắc chắn muốn tiếp tục không?', 'wp-redirect-gateway' ) ); ?>');">
                                <span class="dashicons dashicons-update" style="margin-top:3px;"></span> <?php esc_html_e( 'Phục hồi dữ liệu', 'wp-redirect-gateway' ); ?>
                            </button>
                            <p class="description" style="color: #d63638; font-weight: bold;"><?php esc_html_e( 'Cẩn thận: Ghi đè toàn bộ Database & Settings của plugin bằng file Backup JSON!', 'wp-redirect-gateway' ); ?></p>
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

            if(submitBtnWrap) {
                submitBtnWrap.style.display = (targetId === 'tab-import-export') ? 'none' : 'block';
            }
        });
    });

    const checkbox = document.getElementById('wprg_enable_initial_click');
    const container = document.getElementById('wprg-initial-links-container');
    const wrapper = document.getElementById('wprg-initial-links-wrapper');
    const addBtn = document.getElementById('wprg-add-initial-link');

    if(checkbox && container) {
        checkbox.addEventListener('change', function() {
            container.style.display = this.checked ? 'block' : 'none';
        });
    }

    if(addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = document.createElement('div');
            row.style.marginBottom = '10px';
            // Đã bọc text "Xóa" cho JS chèn nội dung HTML
            row.innerHTML = '<input type="url" name="wprg_initial_links[]" class="large-text" value="" placeholder="https://..." style="width: 80%;" /> <button type="button" class="button remove-link" style="color: #d63638; border-color: #d63638;"><?php echo esc_js( __( 'Xóa', 'wp-redirect-gateway' ) ); ?></button>';
            wrapper.appendChild(row);
        });
    }

    if(wrapper) {
        wrapper.addEventListener('click', function(e) {
            if(e.target.classList.contains('remove-link')) {
                e.preventDefault();
                e.target.parentElement.remove();
            }
        });
    }
});
</script>