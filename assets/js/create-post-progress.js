
(function () {
    'use strict';

    const form = document.getElementById('postForm');
    if (!form) return; 

    /* ---------------- styles (scoped, prefixed lkp-) ---------------- */
    const style = document.createElement('style');
    style.textContent = `
    .lkp-overlay {
        position: fixed;
        right: 18px;
        bottom: 18px;
        z-index: 9999;
        display: none;
        pointer-events: none;
        max-width: calc(100vw - 36px);
    }
    .lkp-overlay.lkp-show {
        display: block;
        pointer-events: auto;
    }

    .lkp-card {
        width: 320px;
        max-width: 100%;
        background: var(--surface, #fff);
        border-radius: 16px;
        box-shadow: 0 14px 38px rgba(15, 17, 21, 0.22), 0 2px 8px rgba(15,17,21,0.08);
        border: 1px solid var(--line, #ececf2);
        padding: 18px 18px 15px;
        transform: translateY(18px) scale(0.97);
        opacity: 0;
        transition: transform 0.3s cubic-bezier(.2,.8,.2,1), opacity 0.25s ease;
        font-family: 'Inter', system-ui, sans-serif;
    }
    .lkp-overlay.lkp-show .lkp-card { transform: translateY(0) scale(1); opacity: 1; }

    .lkp-head { display: flex; align-items: center; gap: 10px; margin-bottom: 3px; }

    .lkp-spinner-ring {
        width: 22px; height: 22px; flex-shrink: 0;
        border-radius: 50%;
        border: 3px solid var(--accent-soft, #e7e6fb);
        border-top-color: var(--accent, #5b4fe0);
        animation: lkp-spin 0.8s linear infinite;
    }
    .lkp-head.lkp-head-done .lkp-spinner-ring { display: none; }

    .lkp-title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 14.5px;
        font-weight: 700;
        color: var(--ink, #14161a);
        margin: 0;
    }
    .lkp-subtitle {
        font-size: 11.5px;
        color: var(--slate, #6b7280);
        margin: 2px 0 14px;
        line-height: 1.4;
    }

    .lkp-bar-track {
        height: 5px;
        border-radius: 999px;
        background: var(--bg, #f2f2f7);
        overflow: hidden;
        margin-bottom: 14px;
    }
    .lkp-bar-fill {
        height: 100%;
        width: 4%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--accent, #5b4fe0), var(--accent-hover, #4a3fd0));
        transition: width 0.5s cubic-bezier(.2,.8,.2,1);
    }

    .lkp-platform-list {
        display: flex;
        flex-direction: column;
        gap: 7px;
        max-height: 200px;
        overflow-y: auto;
    }

    .lkp-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        background: var(--bg, #f7f7fb);
        border: 1px solid transparent;
        transition: background 0.2s ease, border-color 0.2s ease;
    }
    .lkp-row.lkp-active {
        background: var(--accent-soft, #ecebfd);
        border-color: var(--accent, #5b4fe0);
    }
    .lkp-row.lkp-done { background: var(--bg, #f7f7fb); }

    .lkp-row-icon {
        width: 22px; height: 22px; border-radius: 5px;
        flex-shrink: 0; object-fit: cover;
    }

    .lkp-row-text { flex: 1; min-width: 0; }
    .lkp-row-name {
        font-size: 12px; font-weight: 600; color: var(--ink, #14161a);
        margin: 0 0 1px;
    }
    .lkp-row-status {
        font-size: 10.5px; color: var(--slate, #6b7280);
        margin: 0;
        transition: opacity 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lkp-row.lkp-active .lkp-row-status { color: var(--accent, #5b4fe0); font-weight: 600; }
    .lkp-row.lkp-done .lkp-row-status { color: #17a768; font-weight: 600; }

    .lkp-row-state {
        width: 20px; height: 20px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }
    .lkp-mini-spinner {
        width: 16px; height: 16px; border-radius: 50%;
        border: 2.5px solid var(--accent-soft, #e7e6fb);
        border-top-color: var(--accent, #5b4fe0);
        animation: lkp-spin 0.7s linear infinite;
    }
    .lkp-check {
        width: 20px; height: 20px; border-radius: 50%;
        background: #17a768; color: #fff;
        display: flex; align-items: center; justify-content: center;
        transform: scale(0);
        animation: lkp-pop 0.28s cubic-bezier(.2,1.4,.4,1) forwards;
    }
    .lkp-check svg { width: 11px; height: 11px; }

    .lkp-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--line, #d8d8e2); }

    .lkp-footnote {
        margin-top: 20px;
        font-size: 11px;
        color: var(--slate, #9096a3);
        text-align: center;
        line-height: 1.5;
    }

    @keyframes lkp-spin { to { transform: rotate(360deg); } }
    @keyframes lkp-pop { to { transform: scale(1); } }
    @keyframes lkp-fade-swap {
        0% { opacity: 0; transform: translateY(2px); }
        15%, 85% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-2px); }
    }
    .lkp-status-anim { animation: lkp-fade-swap 2.2s ease both; }

    @media (max-width: 480px) {
        .lkp-card { padding: 22px 18px 20px; border-radius: 16px; }
    }
    `;
    document.head.appendChild(style);

    /* ---------------- build overlay skeleton (hidden until needed) ---------------- */
    const overlay = document.createElement('div');
    overlay.className = 'lkp-overlay';
    overlay.innerHTML = `
        <div class="lkp-card">
            <div class="lkp-head" id="lkpHead">
                <div class="lkp-spinner-ring"></div>
                <h3 class="lkp-title" id="lkpTitle">Publishing your post</h3>
            </div>
            <p class="lkp-subtitle" id="lkpSubtitle">Hang tight — this only takes a moment.</p>
            <div class="lkp-bar-track"><div class="lkp-bar-fill" id="lkpBarFill"></div></div>
            <div class="lkp-platform-list" id="lkpPlatformList"></div>
            <p class="lkp-footnote">Please don't close or refresh this tab while we publish.</p>
        </div>
    `;
    document.body.appendChild(overlay);

    const els = {
        head: overlay.querySelector('#lkpHead'),
        title: overlay.querySelector('#lkpTitle'),
        subtitle: overlay.querySelector('#lkpSubtitle'),
        barFill: overlay.querySelector('#lkpBarFill'),
        list: overlay.querySelector('#lkpPlatformList'),
    };

    
    const VIDEO_TIPS = {
        instagram: ['Uploading your video…', 'Processing on Instagram…', 'Almost there…'],
        tiktok: ['Uploading your video…', 'Processing on TikTok…', 'Almost there…'],
        linkedin: ['Uploading your video…', 'Processing on LinkedIn…', 'Almost there…'],
        facebook: ['Uploading your video…', 'Processing on Facebook…', 'Almost there…'],
    };
    const IMAGE_TIP = 'Posting…';

    function hasVideoSelected() {
        const input = document.getElementById('mediaInput');
        if (!input || !input.files) return false;
        return Array.from(input.files).some(f => f.type.startsWith('video/'));
    }

    function getSelectedPlatforms() {
        const checked = form.querySelectorAll('input[name="platforms[]"]:checked');
        const list = [];
        checked.forEach(cb => {
            const wrap = cb.closest('.platform-checkbox');
            if (!wrap) return;
            const key = wrap.getAttribute('data-platform') || cb.value;
            const img = wrap.querySelector('.platform-icon');
            const nameEl = wrap.querySelector('.platform-name');
            list.push({
                key,
                label: nameEl ? nameEl.textContent.trim() : key,
                icon: img ? img.getAttribute('src') : '',
            });
        });
        return list;
    }

    function isSchedulingMode() {
        const hidden = document.getElementById('scheduled_at');
        return !!(hidden && hidden.value && hidden.value.trim() !== '');
    }

    function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }

    function buildRow(p) {
        const row = document.createElement('div');
        row.className = 'lkp-row';
        row.dataset.platform = p.key;
        row.innerHTML = `
            ${p.icon ? `<img class="lkp-row-icon" src="${p.icon}" alt="">` : ''}
            <div class="lkp-row-text">
                <p class="lkp-row-name">${p.label}</p>
                <p class="lkp-row-status">Waiting…</p>
            </div>
            <div class="lkp-row-state"><span class="lkp-dot"></span></div>
        `;
        return row;
    }

    function setRowActive(row, video) {
        row.classList.add('lkp-active');
        row.querySelector('.lkp-row-state').innerHTML = '<div class="lkp-mini-spinner"></div>';
        const statusEl = row.querySelector('.lkp-row-status');
        statusEl.textContent = video ? VIDEO_TIPS[row.dataset.platform]?.[0] || 'Uploading…' : IMAGE_TIP;
    }

    function cycleActiveTips(row, tips) {
        let i = 0;
        const statusEl = row.querySelector('.lkp-row-status');
        return setInterval(() => {
            i = (i + 1) % tips.length;
            statusEl.classList.remove('lkp-status-anim');
            void statusEl.offsetWidth; // restart animation
            statusEl.classList.add('lkp-status-anim');
            statusEl.textContent = tips[i];
        }, 2200);
    }

    function setRowDone(row) {
        row.classList.remove('lkp-active');
        row.classList.add('lkp-done');
        row.querySelector('.lkp-row-state').innerHTML =
            '<span class="lkp-check"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>';
        row.querySelector('.lkp-row-status').textContent = 'Posted';
    }

    function show() {
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('lkp-show'));
    }

    /* ---------------- main animation ---------------- */

    async function runScheduleAnimation() {
        els.title.textContent = 'Scheduling your post';
        els.subtitle.textContent = 'Saving your post so it goes out right on time.';
        els.list.innerHTML = '';
        els.barFill.style.width = '85%';
        show();
    }

    async function runPublishAnimation() {
        const platforms = getSelectedPlatforms();
        const videoMode = hasVideoSelected();

        els.title.textContent = 'Publishing your post';
        els.subtitle.textContent = platforms.length > 1
            ? `Sending it out to ${platforms.length} platforms…`
            : 'Sending it out…';

        els.list.innerHTML = '';
        const rows = platforms.map(p => {
            const row = buildRow(p);
            els.list.appendChild(row);
            return row;
        });

        show();

        els.barFill.style.width = '8%';
        await sleep(700);

        if (rows.length === 0) {
            els.barFill.style.width = '95%';
            return;
        }

        const totalSteps = rows.length;
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const key = row.dataset.platform;
            const isVideoPlatform = videoMode && VIDEO_TIPS[key];

            setRowActive(row, isVideoPlatform);
            els.subtitle.textContent = `Posting to ${row.querySelector('.lkp-row-name').textContent}…`;

            let tipInterval = null;
            let activeDuration;

            if (isVideoPlatform) {
                tipInterval = cycleActiveTips(row, VIDEO_TIPS[key]);
                activeDuration = 6600; 
            } else {
                activeDuration = 1900;
            }

            await sleep(activeDuration);
            if (tipInterval) clearInterval(tipInterval);

            setRowDone(row);
            const pct = Math.round(((i + 1) / totalSteps) * 88) + 6; // ends around ~94%
            els.barFill.style.width = pct + '%';
            await sleep(250);
        }

        els.head.classList.remove('lkp-head-done');
        els.title.textContent = 'Wrapping up';
        els.subtitle.textContent = 'Finalizing everything';
        els.barFill.style.width = '96%';
    }

    form.addEventListener('submit', function () {

        if (isSchedulingMode()) {
            runScheduleAnimation();
        } else {
            runPublishAnimation();
        }
    });
})();