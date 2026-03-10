document.addEventListener('DOMContentLoaded', function() {
    if (typeof wprgData === 'undefined') return;

    // --- 1. XỬ LÝ FORM MẬT KHẨU (Đã hỗ trợ nhiều form từ trước) ---
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

            btnSubmit.innerText = wprgData.i18n.checking_pass;
            btnSubmit.style.cursor = "wait";
            btnSubmit.style.opacity = "0.7";
            errorText.style.display = "none";

            fetch(wprgData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'wprg_verify_password', nonce: wprgData.nonce, slug: slug, password: passVal })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.cookie = data.data.cookie_name + "=" + data.data.cookie_value + "; max-age=" + wprgData.cookie_time + "; path=/";
                    const wrapPass = document.getElementById('wprg-pass-wrap-' + slug);
                    const wrapBtn = document.getElementById('wprg-btn-wrap-' + slug);
                    wrapPass.style.transition = "opacity 0.3s ease";
                    wrapPass.style.opacity = 0;
                    setTimeout(() => {
                        wrapPass.style.display = 'none';
                        wrapBtn.style.display = 'block';
                        wrapBtn.style.opacity = 0;
                        void wrapBtn.offsetWidth; 
                        wrapBtn.style.transition = "opacity 0.5s ease";
                        wrapBtn.style.opacity = 1; 
                    }, 300);
                } else {
                    errorText.innerText = data.data || wprgData.i18n.wrong_pass;
                    errorText.style.display = "block";
                    btnSubmit.innerText = wprgData.i18n.unlock_now
                    btnSubmit.style.cursor = "pointer";
                    btnSubmit.style.opacity = "1";
                }
            })
            .catch(err => {
                errorText.innerText = wprgData.i18n.network_error_short;
                errorText.style.display = "block";
                btnSubmit.innerText = wprgData.i18n.unlock_now;
                btnSubmit.style.cursor = "pointer";
                btnSubmit.style.opacity = "1";
            });
        });
    });

    // --- 2. LOGIC ĐẾM NGƯỢC (ĐA LUỒNG - HỖ TRỢ NỀN TẢNG NHIỀU NÚT) ---
    const wrappers = document.querySelectorAll('.wprg-gateway-wrapper');
    if (wrappers.length === 0) return;

    // Lặp qua từng nút để tạo không gian chạy riêng biệt
    wrappers.forEach(function(wrapper) {
        const btn = wrapper.querySelector('.wprg-action-btn');
        const statusText = wrapper.querySelector('.wprg-status-text');
        
        if (!btn || !statusText) return; 

        // Lấy dữ liệu riêng của nút đó từ HTML
        const slug = wrapper.dataset.slug;
        const totalAds = parseInt(wrapper.dataset.ads) || 0;
        const logId = parseInt(wrapper.dataset.logid) || 0;
        
        const lsKey = 'wprg_progress_' + slug; 
        const globalActiveKey = 'wprg_active_link_data'; 
        const i18n = wprgData.i18n; 

        const waitTimeStr = wrapper.dataset.wait || "10";
        const waitTimesArray = waitTimeStr.split(',').map(n => parseInt(n.trim()) || 0);

        function getWaitTimeForStep(stepIndex) {
            if (stepIndex <= waitTimesArray.length) return waitTimesArray[stepIndex - 1];
            return waitTimesArray[waitTimesArray.length - 1] || 10; 
        }

        let countdownInterval;
        let isCounting = false;
        let recapToken = '';

        // Khóa nếu Single Link Mode bật (Khóa tính trên toàn website)
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
                    return; // Ngừng thiết lập nút này nếu đang có link khác chạy
                }
            }
        }

        let state = JSON.parse(localStorage.getItem(lsKey)) || {
            isStarted: false, currentAd: 1, timeLeft: getWaitTimeForStep(1), isReady: false, adOpened: false
        };
        state.isVerified = false; 

        function saveState() {
            let tempState = Object.assign({}, state);
            tempState.isVerified = false; 
            localStorage.setItem(lsKey, JSON.stringify(tempState));
            if (wprgData.single_link === '1' && state.isStarted && !state.isReady) {
                localStorage.setItem(globalActiveKey, JSON.stringify({ slug: slug, timestamp: new Date().getTime() }));
            }
        }

        function renderUI() {
            if (state.isReady) {
                let needVerify = false;
                let isTurnstile = (wprgData.captcha_type === 'turnstile' && wprgData.turnstile_site);
                let isRecaptcha = (wprgData.captcha_type === 'recaptcha' && wprgData.recaptcha_site);

                if (isTurnstile || isRecaptcha) needVerify = true;

                if (needVerify && !state.isVerified) {
                    if (isTurnstile) {
                        btn.innerText = wprgData.i18n.wait_verify; 
                        btn.style.backgroundColor = '#f39c12'; 
                        btn.style.color = '#fff';
                        btn.disabled = true; 
                        statusText.innerText = wprgData.i18n.checking_safe;
                        btn.dataset.ready = "true";
                        btn.dataset.step = "wait_turnstile";

                        let tsWaitCount = 0;
                        let tsWaitInterval = setInterval(function() {
                            if (typeof turnstile !== 'undefined') {
                                clearInterval(tsWaitInterval);
                                // Tạo ID riêng biệt cho mỗi khung Cloudflare
                                let tsContainerId = 'wprg-turnstile-container-' + slug;
                                let tsContainer = document.getElementById(tsContainerId);
                                if (!tsContainer) {
                                    tsContainer = document.createElement('div');
                                    tsContainer.id = tsContainerId;
                                    tsContainer.style.margin = '15px auto';
                                    tsContainer.style.display = 'flex';
                                    tsContainer.style.justifyContent = 'center';
                                    btn.parentNode.insertBefore(tsContainer, btn);
                                    
                                    window['wprgTsId_' + slug] = turnstile.render('#' + tsContainerId, {
                                        sitekey: wprgData.turnstile_site,
                                        callback: function(token) {
                                            recapToken = token;
                                            state.isVerified = true;
                                            saveState();
                                            document.getElementById(tsContainerId).style.display = 'none';
                                            btn.disabled = false;
                                            btn.dataset.step = "get_link";
                                            btn.click(); 
                                        },
                                        "error-callback": function() {
                                            alert(i18n.error_prefix + " Cloudflare Turnstile error.");
                                            btn.disabled = false;
                                            btn.innerText = i18n.try_again;
                                            btn.dataset.step = "wait_turnstile";
                                            turnstile.reset(window['wprgTsId_' + slug]);
                                        }
                                    });
                                }
                            } else {
                                tsWaitCount++;
                                if (tsWaitCount > 20) {
                                    clearInterval(tsWaitInterval);
                                    statusText.innerHTML = "<span style='color:#d63638; font-weight:bold;'>" + wprgData.i18n.cf_load_error + "</span>";
                                }
                            }
                        }, 500);
                    } else if (isRecaptcha) {
                        btn.innerText = i18n.verify_sec; 
                        btn.style.backgroundColor = '#f39c12'; 
                        btn.style.color = '#fff';
                        btn.disabled = false;
                        statusText.innerText = i18n.verify_msg;
                        btn.dataset.ready = "true";
                        btn.dataset.step = "verify"; 
                    }
                } else {
                    btn.innerText = i18n.link_ready; 
                    btn.style.backgroundColor = '#00a32a'; 
                    btn.style.color = '#fff';
                    btn.disabled = false;
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

            // [BẢN VÁ LỖI] KIỂM TRA TRỰC TIẾP LÚC CLICK CHUỘT (Cho chế độ 1 Link)
            if (wprgData.single_link === '1' && !state.isStarted && !state.isReady) {
                const activeDataStr = localStorage.getItem(globalActiveKey);
                if (activeDataStr) {
                    const activeData = JSON.parse(activeDataStr);
                    const now = new Date().getTime();
                    // Nếu có link khác đang chạy và chưa quá 5 phút
                    if (activeData.slug !== slug && (now - activeData.timestamp < 300000)) {
                        alert(i18n.active_warning + "\n" + i18n.active_desc); // Bật thông báo Pop-up
                        
                        // Khóa cứng nút này lại ngay lập tức
                        btn.innerText = i18n.active_warning;
                        btn.style.backgroundColor = '#666';
                        btn.style.cursor = 'not-allowed';
                        btn.disabled = true;
                        statusText.innerHTML = i18n.active_desc;
                        return; // Chặn không cho lệnh bên dưới chạy
                    }
                }
            }

            let secFeatures = '';
            if (wprgData.rel_noopener === '1') secFeatures += 'noopener,';
            if (wprgData.rel_noreferrer === '1') secFeatures += 'noreferrer,';
            secFeatures = secFeatures.replace(/,$/, '');

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
                        btn.style.cursor = 'pointer';
                    }
                    return;
                }

                if (btn.dataset.step === "get_link") {
                    btn.innerText = i18n.checking_sec; 
                    btn.style.cursor = 'wait';
                    
                    function executeAjaxLink(token = '', attempt = 0) {
                        const currentUrlParams = new URLSearchParams(window.location.search);
                        const subId = currentUrlParams.get('subid') || ''; 
                        currentUrlParams.delete('subid'); 
                        currentUrlParams.delete('wprg_link'); 
                        currentUrlParams.delete('wprg_log_id'); 
                        const otherParams = currentUrlParams.toString(); 
                        const realReferrer = (logId === 0) ? window.location.href : document.referrer;

                        fetch(wprgData.ajax_url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'wprg_get_final_link', nonce: wprgData.nonce, slug: slug,
                                log_id: logId, recaptcha_token: token, referrer: realReferrer, 
                                sub_id: subId, url_params: otherParams
                            })
                        })
                        .then(response => {
                            if (!response.ok) throw new Error("HTTP Error"); // Bắt các lỗi do Tường lửa (403, 429)
                            return response.json();
                        })
                        .then(data => {
                            if (data.success && data.data.url) {
                                localStorage.removeItem(lsKey);
                                localStorage.removeItem(globalActiveKey);
                                if (wprgData.open_new_tab === '1') {
                                    window.open(data.data.url, '_blank', secFeatures); 
                                    btn.innerText = wprgData.i18n.link_opened_btn;
                                    btn.style.backgroundColor = '#666';
                                    btn.style.cursor = 'not-allowed';
                                    btn.disabled = true;
                                    statusText.innerText = wprgData.i18n.link_opened_new_tab;
                                } else {
                                    window.location.href = data.data.url; 
                                }
                            } else {
                                // Lỗi do server trả về (như sai token)
                                handleError(data.data || i18n.error_msg);
                            }
                        })
                        .catch(err => {
                            // Lỗi mạng hoặc bị chặn ngắt quãng
                            handleError(i18n.network_err); 
                        });

                        // Hàm xử lý lỗi: Quyết định Auto-Retry hay bắt người dùng bấm
                        function handleError(errorMsg) {
                            
                            // [BẢN VÁ UX ĐA NGÔN NGỮ]: So sánh khớp biến dịch thay vì text cứng
                            if (errorMsg === wprgData.i18n.pass_backend_err) {
                                alert("🛑 " + errorMsg + "\n\n" + wprgData.i18n.pls_enter_pass);
                                window.location.reload(); // Ép tải lại trang để ẩn nút đi
                                return;
                            }

                            // Nếu bật chế độ Auto Retry và số lần thử < 2 (Sẽ thử lại tối đa 2 lần)
                            if (wprgData.auto_retry === '1' && attempt < 2) {
                                btn.innerText = wprgData.i18n.retrying;
                                btn.style.cursor = 'wait';
                                statusText.innerHTML = `<span style='color:#f39c12; font-weight:bold;'>${wprgData.i18n.auto_retrying} (${attempt + 1}/2)...</span>`;
                                
                                setTimeout(() => {
                                    executeAjaxLink(token, attempt + 1);
                                }, 2000);
                            } else {
                                // Nếu tắt Auto Retry hoặc đã thử quá 2 lần vẫn xịt
                                alert(`${i18n.error_prefix} ${errorMsg}`);
                                btn.innerText = i18n.try_again;
                                btn.style.cursor = 'pointer';
                                statusText.innerText = "";
                            }
                        }
                    }
                    executeAjaxLink(recapToken);
                    return;
                }
            }

            if (!state.isStarted) {
                if (wprgData.enable_initial_click === '1') {
                    let popupBlocked = false; 
                    let winHome = window.open(wprgData.home_url, '_blank', secFeatures);
                    if (!winHome || winHome.closed || typeof winHome.closed === 'undefined') popupBlocked = true;

                    let initLinks = wprgData.initial_links;
                    if (initLinks && initLinks.length > 0) {
                        initLinks.forEach(function(link) {
                            if (link && link.trim() !== '') {
                                let winExtra = window.open(link, '_blank', secFeatures);
                                if (!winExtra || winExtra.closed || typeof winExtra.closed === 'undefined') popupBlocked = true;
                            }
                        });
                    }

                    if (popupBlocked) {
                        alert(i18n.popup_blocked_alert || "Popup blocked.");
                        statusText.innerHTML = `<span style='color:#d63638; font-weight:bold;'>${i18n.popup_blocked_msg || "Enable popups!"}</span>`; 
                    }
                }

                state.isStarted = true;
                statusText.style.color = "#888"; 
                if (totalAds === 0) { state.isReady = true; state.timeLeft = 0; }
                saveState();
                renderUI();
                return; 
            }

            const affLinks = wprgData.aff_links;
            if (affLinks && affLinks.length > 0) {
                let adClickCount = parseInt(localStorage.getItem('wprg_ad_counter')) || 0;
                let nextIndex = adClickCount % affLinks.length;
                window.open(affLinks[nextIndex], '_blank', secFeatures);
                localStorage.setItem('wprg_ad_counter', adClickCount + 1);
            }

            state.adOpened = true;
            saveState();
            renderUI();

            isCounting = true;
            clearInterval(countdownInterval);
            countdownInterval = setInterval(tick, 1000);
        });
    }); // Kết thúc vòng lặp ForEach
});