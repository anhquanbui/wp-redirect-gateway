document.addEventListener('DOMContentLoaded', function() {
    if (typeof wprgData === 'undefined') return;

    // --- 1. XỬ LÝ FORM MẬT KHẨU BẰNG AJAX (CHO CẢ GATEWAY & INLINE) ---
    const passForms = document.querySelectorAll('.wprg-ajax-pass-form');
    passForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const slug = this.dataset.slug;
            const input = this.querySelector('.wprg-pass-input');
            const btnSubmit = this.querySelector('.wprg-pass-submit');
            const errorText = document.getElementById('wprg-pass-error-' + slug);
            const passVal = input.value;

            if (!passVal) return;

            // Đổi trạng thái chờ
            btnSubmit.innerText = "ĐANG KIỂM TRA...";
            btnSubmit.style.cursor = "wait";
            btnSubmit.style.opacity = "0.7";
            errorText.style.display = "none";

            fetch(wprgData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wprg_verify_password',
                    nonce: wprgData.nonce,
                    slug: slug,
                    password: passVal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Lưu cookie để nếu khách F5 tải lại trang sẽ không bị hỏi lại pass
                    document.cookie = data.data.cookie_name + "=" + data.data.cookie_value + "; max-age=86400; path=/";
                    
                    // Hiệu ứng Fade out (Mờ khung pass) và Fade in (Hiện Nút bấm/Khung Gateway)
                    const wrapPass = document.getElementById('wprg-pass-wrap-' + slug);
                    const wrapBtn = document.getElementById('wprg-btn-wrap-' + slug);
                    
                    wrapPass.style.transition = "opacity 0.3s ease";
                    wrapPass.style.opacity = 0;
                    
                    setTimeout(() => {
                        wrapPass.style.display = 'none'; // Giấu hẳn khung pass
                        wrapBtn.style.display = 'block'; // Hiện khung nút lên
                        wrapBtn.style.opacity = 0;
                        void wrapBtn.offsetWidth; // Ép trình duyệt vẽ lại (Reflow)
                        wrapBtn.style.transition = "opacity 0.5s ease";
                        wrapBtn.style.opacity = 1; // Làm rõ dần nút bấm
                    }, 300);

                } else {
                    errorText.innerText = data.data || "Mật khẩu sai!";
                    errorText.style.display = "block";
                    btnSubmit.innerText = "MỞ KHÓA NGAY";
                    btnSubmit.style.cursor = "pointer";
                    btnSubmit.style.opacity = "1";
                }
            })
            .catch(err => {
                errorText.innerText = "Lỗi kết nối mạng!";
                errorText.style.display = "block";
                btnSubmit.innerText = "MỞ KHÓA NGAY";
                btnSubmit.style.cursor = "pointer";
                btnSubmit.style.opacity = "1";
            });
        });
    });

    // --- 2. LOGIC ĐẾM NGƯỢC VÀ CHUYỂN HƯỚNG ---
    const btn = document.getElementById('wprg-action-btn');
    const statusText = document.getElementById('wprg-status-text');
    
    // Nếu nút bấm bị xóa khỏi DOM, ngừng chạy JS
    if (!btn || !statusText) return; 
    
    const slug = wprgData.slug;
    const totalAds = parseInt(wprgData.total_ads);
    
    const lsKey = 'wprg_progress_' + slug; 
    const globalActiveKey = 'wprg_active_link_data'; 
    const i18n = wprgData.i18n; 

    const waitTimeStr = wprgData.wait_time.toString();
    const waitTimesArray = waitTimeStr.split(',').map(n => parseInt(n.trim()) || 0);

    function getWaitTimeForStep(stepIndex) {
        if (stepIndex <= waitTimesArray.length) {
            return waitTimesArray[stepIndex - 1];
        }
        return waitTimesArray[waitTimesArray.length - 1] || 10; 
    }

    let countdownInterval;
    let isCounting = false;

    if (wprgData.single_link === '1') {
        const activeDataStr = localStorage.getItem(globalActiveKey);
        if (activeDataStr) {
            const activeData = JSON.parse(activeDataStr);
            const now = new Date().getTime();
            if (activeData.slug !== slug && (now - activeData.timestamp < 300000)) {
                btn.innerText = i18n.active_warning;
                btn.style.backgroundColor = '#666';
                btn.style.cursor = 'not-allowed';
                btn.disabled = true;
                statusText.innerHTML = i18n.active_desc;
                return; 
            }
        }
        localStorage.setItem(globalActiveKey, JSON.stringify({ slug: slug, timestamp: new Date().getTime() }));
    }

    let state = JSON.parse(localStorage.getItem(lsKey)) || {
        isStarted: false, 
        currentAd: 1,
        timeLeft: getWaitTimeForStep(1),
        isReady: false,
        adOpened: false,
        isVerified: false
    };

    if (typeof state.isStarted === 'undefined') state.isStarted = false;
    if (typeof state.isVerified === 'undefined') state.isVerified = false;

    let recapToken = '';

    function saveState() {
        localStorage.setItem(lsKey, JSON.stringify(state));
        if (wprgData.single_link === '1') {
            localStorage.setItem(globalActiveKey, JSON.stringify({ slug: slug, timestamp: new Date().getTime() }));
        }
    }

    function renderUI() {
        if (state.isReady) {
            if (wprgData.recaptcha_site && !state.isVerified) {
                btn.innerText = i18n.verify_sec; 
                btn.style.backgroundColor = '#f39c12'; 
                btn.style.color = '#fff';
                statusText.innerText = i18n.verify_msg;
                btn.dataset.ready = "true";
                btn.dataset.step = "verify"; 
            } else {
                btn.innerText = i18n.link_ready; 
                btn.style.backgroundColor = '#00a32a'; 
                btn.style.color = '#fff';
                statusText.innerText = i18n.step_done; 
                btn.dataset.ready = "true";
                btn.dataset.step = "get_link"; 
            }
        } else if (state.adOpened && state.timeLeft > 0) {
            btn.innerText = `${i18n.wait_msg} (${state.timeLeft}s)`; 
            btn.style.backgroundColor = '#ccc';
            btn.style.color = '#666';
            statusText.innerText = i18n.counting; 
        } else if (!state.isStarted) {
            btn.innerText = i18n.start_btn || 'CLICK HERE TO CONTINUE'; 
            btn.style.backgroundColor = '#0073aa';
            btn.style.color = '#fff';
            statusText.innerText = i18n.start_msg || 'Vui lòng nhấn nút bên dưới để bắt đầu'; 
        } else {
            btn.innerText = `${i18n.watch_ad} (${state.currentAd}/${totalAds})`; 
            btn.style.backgroundColor = state.currentAd > 1 ? '#d63638' : '#0073aa';
            btn.style.color = '#fff';
            statusText.innerText = i18n.click_to_watch; 
        }
    }

    function tick() {
        if (state.timeLeft > 0) {
            state.timeLeft--;
            saveState();
            renderUI();
        } else {
            clearInterval(countdownInterval);
            isCounting = false;
            
            if (state.currentAd < totalAds) {
                state.currentAd++;
                state.timeLeft = getWaitTimeForStep(state.currentAd);
                state.adOpened = false;
                saveState();
                renderUI();
            } else {
                state.isReady = true;
                saveState();
                renderUI();
            }
        }
    }

    if (state.adOpened && !state.isReady && state.timeLeft > 0) {
        isCounting = true;
        clearInterval(countdownInterval);
        countdownInterval = setInterval(tick, 1000);
    }

    document.addEventListener("visibilitychange", function() {
        if (wprgData.active_tab === '1') {
            if (document.hidden) {
                clearInterval(countdownInterval);
                if (isCounting) statusText.innerText = i18n.stop_warning; 
            } else {
                if (isCounting) {
                    clearInterval(countdownInterval); 
                    countdownInterval = setInterval(tick, 1000);
                    statusText.innerText = i18n.counting; 
                }
            }
        }
    });

    renderUI();

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (btn.disabled || isCounting) return;

        if (btn.dataset.ready === "true") {
            if (btn.dataset.step === "verify") {
                btn.innerText = i18n.verifying;
                btn.style.cursor = 'wait';
                
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(wprgData.recaptcha_site, {action: 'get_link'}).then(function(token) {
                            recapToken = token;
                            state.isVerified = true;
                            saveState();
                            renderUI();
                            btn.style.cursor = 'pointer';
                        }).catch(function(err) {
                            alert(i18n.error_prefix + " " + (i18n.recap_error || "reCAPTCHA error."));
                            btn.innerText = i18n.try_again;
                            btn.style.cursor = 'pointer';
                        });
                    });
                } else {
                    alert(i18n.script_blocked || "Script blocked.");
                    btn.innerText = i18n.try_again;
                }
                return;
            }

            if (btn.dataset.step === "get_link") {
                btn.innerText = i18n.checking_sec; 
                btn.style.cursor = 'wait';
                
                function executeAjaxLink(token = '') {
                    const currentUrlParams = new URLSearchParams(window.location.search);
                    const subId = currentUrlParams.get('subid') || ''; 
                    
                    currentUrlParams.delete('subid'); 
                    currentUrlParams.delete('wprg_link'); 
                    currentUrlParams.delete('wprg_log_id'); 
                    
                    const otherParams = currentUrlParams.toString(); 

                    const realReferrer = (parseInt(wprgData.log_id) === 0) ? window.location.href : document.referrer;

                    fetch(wprgData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'wprg_get_final_link',
                            nonce: wprgData.nonce,
                            slug: wprgData.slug,
                            log_id: wprgData.log_id,
                            recaptcha_token: token,
                            referrer: realReferrer, 
                            sub_id: subId,
                            url_params: otherParams
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.url) {
                            localStorage.removeItem(lsKey);
                            localStorage.removeItem(globalActiveKey);

                            // [MỚI] Kiểm tra cấu hình có bật mở Tab mới không
                            if (wprgData.open_new_tab === '1') {
                                window.open(data.data.url, '_blank'); // Mở link đích ra Tab mới
                                
                                // Khóa nút Get Link hiện tại để khách không bấm nhiều lần
                                btn.innerText = i18n.step_done || "Đã lấy link!";
                                btn.style.backgroundColor = '#666';
                                btn.style.cursor = 'not-allowed';
                                btn.disabled = true;
                                statusText.innerText = "Link đích đã được mở ở Tab mới.";
                            } else {
                                window.location.href = data.data.url; // Mặc định mở ở Tab hiện tại
                            }
                        } else {
                            alert(`${i18n.error_prefix} ${data.data || i18n.error_msg}`);
                            btn.innerText = i18n.try_again;
                            btn.style.cursor = 'pointer';
                        }
                    })
                    .catch(err => {
                        alert(i18n.network_err); 
                        btn.innerText = i18n.try_again; 
                        btn.style.cursor = 'pointer';
                    });
                }

                executeAjaxLink(recapToken);
                return;
            }
        }

        if (!state.isStarted) {
            if (wprgData.enable_initial_click === '1') {
                let popupBlocked = false; 
                
                let winHome = window.open(wprgData.home_url, '_blank');
                if (!winHome || winHome.closed || typeof winHome.closed === 'undefined') {
                    popupBlocked = true;
                }

                let initLinks = wprgData.initial_links;
                if (initLinks && initLinks.length > 0) {
                    initLinks.forEach(function(link) {
                        if (link && link.trim() !== '') {
                            let winExtra = window.open(link, '_blank');
                            if (!winExtra || winExtra.closed || typeof winExtra.closed === 'undefined') {
                                popupBlocked = true;
                            }
                        }
                    });
                }

                if (popupBlocked) {
                    alert(i18n.popup_blocked_alert || "Popup blocked.");
                    statusText.innerHTML = `<span style='color:#d63638; font-weight:bold;'>${i18n.popup_blocked_msg || "Enable popups!"}</span>`;
                    return; 
                }
            }

            state.isStarted = true;
            statusText.style.color = "#888"; 
            
            if (totalAds === 0) {
                state.isReady = true;
                state.timeLeft = 0;
            }

            saveState();
            renderUI();
            return; 
        }

        const affLinks = wprgData.aff_links;
        if (affLinks && affLinks.length > 0) {
            let adClickCount = parseInt(localStorage.getItem('wprg_ad_counter')) || 0;
            let nextIndex = adClickCount % affLinks.length;
            window.open(affLinks[nextIndex], '_blank');
            localStorage.setItem('wprg_ad_counter', adClickCount + 1);
        }

        state.adOpened = true;
        saveState();
        renderUI();

        isCounting = true;
        clearInterval(countdownInterval);
        countdownInterval = setInterval(tick, 1000);
    });
});