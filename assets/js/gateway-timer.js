document.addEventListener('DOMContentLoaded', function() {
    if (typeof wprgData === 'undefined') return;

    // --- 1. PASSWORD FORM HANDLING (Supports multiple forms) ---
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
                    btnSubmit.innerText = wprgData.i18n.unlock_now;
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

    // --- 2. COUNTDOWN LOGIC (MULTI-THREAD - SUPPORTS MULTIPLE BUTTONS) ---
    const wrappers = document.querySelectorAll('.wprg-gateway-wrapper');
    if (wrappers.length === 0) return;

    // Loop through each button to create an isolated execution space
    wrappers.forEach(function(wrapper) {
        const btn = wrapper.querySelector('.wprg-action-btn');
        const statusText = wrapper.querySelector('.wprg-status-text');
        
        if (!btn || !statusText) return; 

        // Get specific data for the button from HTML attributes
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
        let linkOpenedSuccess = false;

        // Lock if Single Link Mode is enabled (Global lock across the site)
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
                    return; // Prevent further execution for this button
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
                                renderTurnstileWidget();
                            } else {
                                tsWaitCount++;
                                // Dynamically load Turnstile if missing (e.g., after Adblock is turned off)
                                if (tsWaitCount === 5) {
                                    const script = document.createElement('script');
                                    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                                    script.onerror = function() {
                                        clearInterval(tsWaitInterval);
                                        statusText.innerHTML = "<span style='color:#d63638; font-weight:bold;'>" + (i18n.adblock_detected || "Script blocked. Please refresh (F5)!") + "</span>";
                                    };
                                    document.head.appendChild(script);
                                }
                                if (tsWaitCount > 30) {
                                    clearInterval(tsWaitInterval);
                                    statusText.innerHTML = "<span style='color:#d63638; font-weight:bold;'>" + wprgData.i18n.cf_load_error + "</span>";
                                }
                            }
                        }, 500);

                        function renderTurnstileWidget() {
                            // Create a unique ID for each Cloudflare widget
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

                                        // CHỈ TỰ ĐỘNG CLICK NẾU LINK CHƯA ĐƯỢC MỞ THÀNH CÔNG
                                        if (!linkOpenedSuccess) {
                                            btn.click(); 
                                        }
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
                        }
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
                statusText.innerText = i18n.start_msg || 'Please click the button below to start'; 
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
            // NẾU LINK ĐÃ MỞ THÀNH CÔNG THÌ KHÔNG LÀM GÌ NỮA
            if (btn.disabled || isCounting || linkOpenedSuccess) return;

            // [BUG FIX] DIRECT CHECK ON CLICK (For Single Link Mode)
            if (wprgData.single_link === '1' && !state.isStarted && !state.isReady) {
                const activeDataStr = localStorage.getItem(globalActiveKey);
                if (activeDataStr) {
                    const activeData = JSON.parse(activeDataStr);
                    const now = new Date().getTime();
                    // If another link is active and less than 5 minutes have passed
                    if (activeData.slug !== slug && (now - activeData.timestamp < 300000)) {
                        alert(i18n.active_warning + "\n" + i18n.active_desc); // Show popup alert
                        
                        // Immediately disable this button
                        btn.innerText = i18n.active_warning;
                        btn.style.backgroundColor = '#666';
                        btn.style.cursor = 'not-allowed';
                        btn.disabled = true;
                        statusText.innerHTML = i18n.active_desc;
                        return; // Prevent further execution
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
                    
                    function executeRecaptcha() {
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
                    }

                    if (typeof grecaptcha !== 'undefined') {
                        executeRecaptcha();
                    } else {
                        // Dynamically load reCAPTCHA script if missing (e.g., after Adblock disabled)
                        const script = document.createElement('script');
                        script.src = 'https://www.google.com/recaptcha/api.js?render=' + wprgData.recaptcha_site;
                        script.onload = executeRecaptcha;
                        script.onerror = function() {
                            alert((i18n.adblock_detected || "Script blocked.") + " Please refresh the page (F5)!");
                            btn.innerText = i18n.try_again;
                            btn.style.cursor = 'pointer';
                        };
                        document.head.appendChild(script);
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
                            if (!response.ok) throw new Error("HTTP Error"); // Catch Firewall errors (403, 429)
                            return response.json();
                        })
                        .then(data => {
                            if (data.success && data.data.url) {
                                linkOpenedSuccess = true; // <-- ĐÁNH DẤU THÀNH CÔNG TẠI ĐÂY
                                localStorage.removeItem(lsKey);
                                localStorage.removeItem(globalActiveKey);
                                if (wprgData.open_new_tab === '1') {
                                    // 1. Mở tab mới KHÔNG kèm secFeatures để giữ quyền điều khiển
                                    let newWin = window.open('', '_blank'); 
                                    let delaySeconds = parseInt(wprgData.new_tab_delay) || 0; 
                                    
                                    if (delaySeconds > 0) {
                                        // Đổi giao diện nút bấm
                                        btn.innerText = "Loading...";
                                        btn.style.backgroundColor = '#666';
                                        btn.style.cursor = 'not-allowed';
                                        btn.disabled = true;
                                        
                                        // [UX TUYỆT ĐỈNH]: In giao diện chờ vào chính cái Tab trắng đó
                                        if (newWin) {
                                            newWin.document.write(`
                                                <div style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; background:#f0f2f5; color:#444; margin:0;">
                                                    <div style="text-align:center; background:#fff; padding:30px 50px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                                                        <div style="width:40px; height:40px; border:4px solid #f3f3f3; border-top:4px solid #0073aa; border-radius:50%; animation:spin 1s linear infinite; margin: 0 auto 15px;"></div>
                                                        <h2 style="margin:0 0 10px; font-size:22px;">${wprgData.i18n.preparing_tab_title}</h2>
                                                        <p style="margin:0; font-size:15px; color:#666;">${wprgData.i18n.preparing_tab_desc}</p>
                                                    </div>
                                                </div>
                                                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
                                            `);
                                        }

                                        let currentSec = delaySeconds;
                                        statusText.innerHTML = `Please keep the new tab open for <b style="color:#d63638;">${currentSec}</b> seconds...`;

                                        // Bắt đầu đếm ngược từng giây
                                        let delayInterval = setInterval(function() {
                                            currentSec--;
                                            if (currentSec > 0) {
                                                statusText.innerHTML = `Please keep the new tab open for <b style="color:#d63638;">${currentSec}</b> seconds...`;
                                            } else {
                                                clearInterval(delayInterval); // Dừng bộ đếm
                                                
                                                // Kiểm tra xem khách có lỡ tay tắt mất tab mới chưa
                                                if (newWin && !newWin.closed) {
                                                    newWin.location.href = data.data.url; // Bơm link đích
                                                    
                                                    // Chốt chặn bảo mật (noopener) sau khi chuyển link
                                                    if (wprgData.rel_noopener === '1') newWin.opener = null;
                                                    
                                                    statusText.innerText = wprgData.i18n.link_opened_new_tab;
                                                } else {
                                                    statusText.innerHTML = `<span style='color:#d63638; font-weight:bold;'>Error: You closed the new tab too early! Please refresh (F5) to try again.</span>`;
                                                }
                                            }
                                        }, 1000);

                                    } else {
                                        // Nếu Admin cấu hình độ trễ = 0 thì chuyển link ngay lập tức
                                        if (newWin && !newWin.closed) {
                                            newWin.location.href = data.data.url;
                                            if (wprgData.rel_noopener === '1') newWin.opener = null;
                                            
                                            btn.innerText = wprgData.i18n.link_opened_btn;
                                            btn.style.backgroundColor = '#666';
                                            btn.style.cursor = 'not-allowed';
                                            btn.disabled = true;
                                            statusText.innerText = wprgData.i18n.link_opened_new_tab;
                                        }
                                    }

                                } else {
                                    window.location.href = data.data.url; 
                                }
                            } else {
                                // Server returned error (e.g., invalid token)
                                handleError(data.data || i18n.error_msg);
                            }
                        })
                        .catch(err => {
                            // Network error or request blocked
                            handleError(i18n.network_err); 
                        });

                        // Error handler: Decide whether to Auto-Retry or ask user to click
                        function handleError(errorMsg) {
                            
                            // [UX FIX MULTILINGUAL]: Match translated variable instead of hardcoded text
                            if (errorMsg === wprgData.i18n.pass_backend_err) {
                                alert("🛑 " + errorMsg + "\n\n" + wprgData.i18n.pls_enter_pass);
                                window.location.reload(); // Force reload page to hide button
                                return;
                            }

                            // If Auto Retry is enabled and attempts < 2 (Will retry max 2 times)
                            if (wprgData.auto_retry === '1' && attempt < 2) {
                                btn.innerText = wprgData.i18n.retrying;
                                btn.style.cursor = 'wait';
                                statusText.innerHTML = `<span style='color:#f39c12; font-weight:bold;'>${wprgData.i18n.auto_retrying} (${attempt + 1}/2)...</span>`;
                                
                                setTimeout(() => {
                                    executeAjaxLink(token, attempt + 1);
                                }, 2000);
                            } else {
                                // If Auto Retry disabled or max attempts reached
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

            // Khi khách click lần đầu tiên -> Chuyển ngay sang trạng thái xem quảng cáo
            if (!state.isStarted) {
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
    }); // End of ForEach loop
});