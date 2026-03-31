document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. JS CHO TRANG CÀI ĐẶT (SETTINGS) ---
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.wprg-tab-content');
    const activeTabInput = document.getElementById('wprg_active_tab');
    const submitBtnWrap = document.getElementById('wprg-submit-wrapper');

    if (tabs.length > 0) {
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                tabs.forEach(t => t.classList.remove('nav-tab-active'));
                contents.forEach(c => c.style.display = 'none');
                this.classList.add('nav-tab-active');
                const targetId = this.getAttribute('data-tab');
                const targetEl = document.getElementById(targetId);
                if (targetEl) targetEl.style.display = 'block';
                if (activeTabInput) activeTabInput.value = targetId;
                if(submitBtnWrap) { submitBtnWrap.style.display = (targetId === 'tab-import-export') ? 'none' : 'block'; }
            });
        });
    }

    const cbAutoBackup = document.getElementById('wprg_enable_auto_backup');
    const wrapBackupTime = document.getElementById('wprg-backup-time-wrap');
    if (cbAutoBackup && wrapBackupTime) {
        cbAutoBackup.addEventListener('change', function() { wrapBackupTime.style.display = this.checked ? 'block' : 'none'; });
    }

    const cbNewTab = document.getElementById('wprg_open_link_new_tab');
    const wrapDelay = document.getElementById('wprg-new-tab-delay-wrap');
    if (cbNewTab && wrapDelay) {
        cbNewTab.addEventListener('change', function() { wrapDelay.style.display = this.checked ? 'block' : 'none'; });
    }

    // --- 2. JS CHO NÚT COPY (BẢNG QUẢN LÝ LINK) ---
    document.body.addEventListener("click", function(e) {
        let btn = e.target.closest(".wprg-btn-copy");
        if (btn) {
            e.preventDefault();
            let textToCopy = btn.getAttribute("data-copy");
            if (textToCopy) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    let icon = btn.querySelector(".dashicons");
                    if (icon) {
                        icon.classList.remove("dashicons-admin-page");
                        icon.classList.add("dashicons-saved");
                        icon.style.color = "#00a32a";
                        setTimeout(() => {
                            icon.classList.add("dashicons-admin-page");
                            icon.classList.remove("dashicons-saved");
                            icon.style.color = "";
                        }, 1500);
                    }
                });
            }
        }
    });

    // --- 3. JS CHO BIỂU ĐỒ (DASHBOARD) ---
    const canvasEl = document.getElementById('clicksChart');
    if (canvasEl && typeof Chart !== 'undefined') {
        // Lấy dữ liệu PHP từ thẻ HTML data-attribute
        const chartData = JSON.parse(canvasEl.getAttribute('data-chart') || '[]');
        const labelText = canvasEl.getAttribute('data-label') || '';
        const titleText = canvasEl.getAttribute('data-title') || '';
        const chartLabels = ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'];

        new Chart(canvasEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: labelText,
                    data: chartData,
                    backgroundColor: 'rgba(0, 115, 170, 0.7)',
                    borderColor: 'rgba(0, 115, 170, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(context) { return titleText + context[0].label; }
                        }
                    }
                }
            }
        });
    }
});