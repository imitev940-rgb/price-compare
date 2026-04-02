<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Dashboard</title>
    <style>
        body {
            margin: 0;
            background:
                radial-gradient(circle at top, rgba(25, 60, 125, 0.18), transparent 25%),
                linear-gradient(180deg, #020617, #030d22, #020714);
            color: white;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }

        .tv-wrap {
            height: 100vh;
            padding: 18px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .tv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            gap: 16px;
        }

        .tv-title {
            font-size: 44px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .tv-subtitle {
            font-size: 16px;
            color: #94a3b8;
            margin-top: 8px;
        }

        .tv-header-right {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .tv-status-box {
            text-align: right;
            padding-top: 8px;
        }

        .tv-fullscreen-btn {
            height: 44px;
            padding: 0 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .tv-fullscreen-btn:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.25);
        }

        .tv-fullscreen-btn:active {
            transform: scale(0.98);
        }

        .tv-columns {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            min-height: 0;
            overflow: hidden;
        }

        .tv-col {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 24px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.22);
            backdrop-filter: blur(8px);
        }

        .tv-col-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .tv-col-title {
            font-size: 24px;
            font-weight: 900;
            line-height: 1;
        }

        .tv-col-count {
            font-size: 20px;
            font-weight: 900;
            line-height: 1;
        }

        .tv-col-sub {
            font-size: 14px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .tv-list {
            flex: 1;
            min-height: 0;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-right: 4px;
            scrollbar-width: none;
        }

        .tv-list::-webkit-scrollbar {
            display: none;
        }

        .tv-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 14px 16px;
            transition: transform 0.45s ease, opacity 0.45s ease, box-shadow 0.45s ease, border-color 0.45s ease;
        }

        .tv-card.updated {
            animation: tvPulse 2.4s ease;
            border-color: rgba(250, 204, 21, 0.95);
            box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.35), 0 0 34px rgba(250, 204, 21, 0.22);
        }

        .tv-card.entered-top {
            animation: enteredTop 2.6s ease;
        }

        .tv-card.entered-not-top {
            animation: enteredNotTop 2.6s ease;
        }

        .tv-card.fade-swap {
            animation: fadeSwap 0.65s ease;
        }

        @keyframes tvPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(250, 204, 21, 0); }
            25% { transform: scale(1.02); box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.45), 0 0 38px rgba(250, 204, 21, 0.30); }
            100% { transform: scale(1); box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.15), 0 0 0 rgba(250, 204, 21, 0); }
        }

        @keyframes enteredTop {
            0% { transform: translateX(40px) scale(0.98); opacity: 0.2; background: rgba(34, 197, 94, 0.28); }
            50% { transform: translateX(0) scale(1.02); opacity: 1; box-shadow: 0 0 28px rgba(34, 197, 94, 0.30); }
            100% { transform: translateX(0) scale(1); background: rgba(255,255,255,0.06); }
        }

        @keyframes enteredNotTop {
            0% { transform: translateX(-40px) scale(0.98); opacity: 0.2; background: rgba(244, 63, 94, 0.28); }
            50% { transform: translateX(0) scale(1.02); opacity: 1; box-shadow: 0 0 28px rgba(244, 63, 94, 0.30); }
            100% { transform: translateX(0) scale(1); background: rgba(255,255,255,0.06); }
        }

        @keyframes fadeSwap {
            0% { opacity: 0.15; transform: translateY(16px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .tv-product {
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 10px;
            line-height: 1.15;
            letter-spacing: -0.02em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tv-meta.not-top {
            display: grid;
            grid-template-columns: 1fr 1fr 1.2fr 0.8fr 1fr 0.7fr;
            gap: 10px;
            align-items: start;
        }

        .tv-meta.top {
            display: grid;
            grid-template-columns: 1fr 1fr 1.15fr 0.9fr 0.6fr;
            gap: 10px;
            align-items: start;
        }

        .tv-meta-label {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .tv-meta-value {
            font-size: 22px;
            font-weight: 900;
            color: #fff;
            line-height: 1.08;
            word-break: normal;
            overflow-wrap: normal;
        }

        .tv-number {
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .tv-store {
            font-size: 18px;
            line-height: 1.05;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .tv-chip-red {
            color: #fda4af;
            font-weight: 900;
        }

        .tv-chip-green {
            color: #86efac;
            font-weight: 900;
        }

        .tv-marquee-wrap {
            margin-top: 14px;
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
        }

        .tv-marquee {
            display: flex;
            white-space: nowrap;
            padding: 12px 0;
            animation: marquee 25s linear infinite;
        }

        .tv-marquee span {
            margin: 0 36px;
            font-size: 18px;
            color: #e2e8f0;
        }

        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        @media (max-width: 1200px) {
            .tv-title {
                font-size: 34px;
            }

            .tv-columns {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="tv-wrap">
        <div class="tv-header">
            <div>
                <div class="tv-title">PriceHunterPro TV Dashboard</div>
                <div class="tv-subtitle">Live market board</div>
            </div>

            <div class="tv-header-right">
                <button id="fullscreenBtn" class="tv-fullscreen-btn" type="button">⛶ Full Screen</button>

                <div class="tv-status-box">
                    <div id="lastLoadTime" class="tv-subtitle">Last load: {{ now()->format('d.m.Y H:i:s') }}</div>
                </div>
            </div>
        </div>

        <div class="tv-columns">
            <div class="tv-col">
                <div class="tv-col-title-row">
                    <div class="tv-col-title" style="color:#fda4af;">NOT TOP</div>
                    <div id="notTopTotal" class="tv-col-count" style="color:#fda4af;">0</div>
                </div>
                <div class="tv-col-sub">Only products not #1 in Pazaruvaj with diff above 5</div>
                <div id="notTopList" class="tv-list"></div>
            </div>

            <div class="tv-col">
                <div class="tv-col-title-row">
                    <div class="tv-col-title" style="color:#86efac;">TOP</div>
                    <div id="topTotal" class="tv-col-count" style="color:#86efac;">0</div>
                </div>
                <div class="tv-col-sub">Only #1 products where next lowest offer after ours is above 5</div>
                <div id="topList" class="tv-list"></div>
            </div>
        </div>

        <div class="tv-marquee-wrap">
            <div class="tv-marquee">
                <span>✦ Live monitoring active</span>
                <span>✦ Refresh data every 30 minutes</span>
                <span>✦ Page rotation every 30 seconds</span>
                <span>✦ Not Top shows only products with diff above 5</span>
                <span>✦ Not Top also shows lowest store</span>
                <span>✦ Top shows only products where next offer after ours is above 5</span>
                <span>✦ Technopolis • Technomarket • Techmart • Tehnomix • Pazaruvaj</span>

                <span>✦ Live monitoring active</span>
                <span>✦ Refresh data every 30 minutes</span>
                <span>✦ Page rotation every 30 seconds</span>
                <span>✦ Not Top shows only products with diff above 5</span>
                <span>✦ Not Top also shows lowest store</span>
                <span>✦ Top shows only products where next offer after ours is above 5</span>
                <span>✦ Technopolis • Technomarket • Techmart • Tehnomix • Pazaruvaj</span>
            </div>
        </div>
    </div>

    <script>
        let previousSnapshot = {
            not_top: new Map(),
            top: new Map()
        };

        let currentPage = 1;
        let totalPages = 1;
        const scrollStates = {};

        function startAutoScroll(containerId, speed = 0.35) {
            const el = document.getElementById(containerId);
            if (!el) return;

            if (scrollStates[containerId]?.frame) {
                cancelAnimationFrame(scrollStates[containerId].frame);
            }

            scrollStates[containerId] = {
                paused: false,
                frame: null
            };

            const state = scrollStates[containerId];

            const step = () => {
                if (!state.paused) {
                    const maxScroll = el.scrollHeight - el.clientHeight;

                    if (maxScroll > 0) {
                        el.scrollTop += speed;

                        if (el.scrollTop >= maxScroll) {
                            el.scrollTop = 0;
                        }
                    }
                }

                state.frame = requestAnimationFrame(step);
            };

            el.onmouseenter = () => state.paused = true;
            el.onmouseleave = () => state.paused = false;

            step();
        }

        function renderList(containerId, items, isTop = false) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const snapshotKey = isTop ? 'top' : 'not_top';
            const otherKey = isTop ? 'not_top' : 'top';

            const oldMap = previousSnapshot[snapshotKey] || new Map();
            const otherOldMap = previousSnapshot[otherKey] || new Map();
            const newMap = new Map();

            el.innerHTML = '';

            items.forEach(p => {
                const signature = JSON.stringify({
                    our_price: p.our_price,
                    lowest_price: p.lowest_price,
                    lowest_store: p.lowest_store ?? null,
                    position: p.position,
                    diff_amount: p.diff_amount ?? null,
                    diff_percent: p.diff_percent ?? null,
                    offers_count: p.offers_count ?? null,
                    next_competitor_store: p.next_competitor_store ?? null,
                    next_competitor_price: p.next_competitor_price ?? null,
                    lead_euro: p.lead_euro ?? null,
                    lead_percent: p.lead_percent ?? null
                });

                newMap.set(String(p.id), signature);

                const card = document.createElement('div');
                card.className = 'tv-card fade-swap';

                const oldSignature = oldMap.get(String(p.id));
                const existedInOtherColumn = otherOldMap.has(String(p.id));

                if (oldSignature && oldSignature !== signature) {
                    card.classList.add('updated');
                }

                if (!oldSignature && !existedInOtherColumn) {
                    card.classList.add('updated');
                }

                if (!oldSignature && existedInOtherColumn) {
                    card.classList.add(isTop ? 'entered-top' : 'entered-not-top');
                }

                if (isTop) {
                    card.innerHTML = `
                        <div class="tv-product">${p.name}</div>
                        <div class="tv-meta top">
                            <div>
                                <span class="tv-meta-label">Our</span>
                                <span class="tv-meta-value tv-number">${p.our_price}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Next Price</span>
                                <span class="tv-meta-value tv-number">${p.next_competitor_price}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Competitor</span>
                                <span class="tv-meta-value tv-chip-green tv-store">${p.next_competitor_store}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Lead</span>
                                <span class="tv-meta-value tv-chip-green tv-number">${p.lead_euro}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">%</span>
                                <span class="tv-meta-value tv-chip-green tv-number">${p.lead_percent}</span>
                            </div>
                        </div>
                    `;
                } else {
                    card.innerHTML = `
                        <div class="tv-product">${p.name}</div>
                        <div class="tv-meta not-top">
                            <div>
                                <span class="tv-meta-label">Our</span>
                                <span class="tv-meta-value tv-number">${p.our_price}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Lowest</span>
                                <span class="tv-meta-value tv-number">${p.lowest_price}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Store</span>
                                <span class="tv-meta-value tv-chip-red tv-store">${p.lowest_store ?? '—'}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Pos</span>
                                <span class="tv-meta-value tv-chip-red tv-number">#${p.position}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">Diff</span>
                                <span class="tv-meta-value tv-chip-red tv-number">${p.diff_amount}</span>
                            </div>
                            <div>
                                <span class="tv-meta-label">%</span>
                                <span class="tv-meta-value tv-chip-red tv-number">${p.diff_percent}</span>
                            </div>
                        </div>
                    `;
                }

                el.appendChild(card);
            });

            previousSnapshot[snapshotKey] = newMap;

            el.scrollTop = 0;
            startAutoScroll(containerId, 0.35);
        }

        async function loadPage(page = 1) {
            try {
                const res = await fetch(`/tv-dashboard-data?page=${page}`, { cache: 'no-store' });
                const data = await res.json();

                totalPages = data.total_pages || 1;
                currentPage = data.page || 1;

                renderList('notTopList', data.not_top, false);
                renderList('topList', data.top, true);

                const lastLoad = document.getElementById('lastLoadTime');
                if (lastLoad) {
                    lastLoad.textContent = 'Last load: ' + data.updated_at;
                }

                const notTopTotal = document.getElementById('notTopTotal');
                if (notTopTotal) {
                    notTopTotal.textContent = data.not_top_total ?? 0;
                }

                const topTotal = document.getElementById('topTotal');
                if (topTotal) {
                    topTotal.textContent = data.top_total ?? 0;
                }
            } catch (e) {
                console.error('TV load error', e);
            }
        }

        async function refreshDataKeepPage() {
            await loadPage(currentPage);
        }

        async function nextPage() {
            let next = currentPage + 1;
            if (next > totalPages) {
                next = 1;
            }
            await loadPage(next);
        }

        function updateFullscreenButton() {
            const btn = document.getElementById('fullscreenBtn');
            if (!btn) return;

            btn.textContent = document.fullscreenElement ? '✕ Exit Full Screen' : '⛶ Full Screen';
        }

        async function toggleFullscreen() {
            try {
                if (!document.fullscreenElement) {
                    await document.documentElement.requestFullscreen();
                } else {
                    await document.exitFullscreen();
                }
            } catch (e) {
                console.error('Fullscreen error', e);
            }
            updateFullscreenButton();
        }

        document.addEventListener('fullscreenchange', updateFullscreenButton);

        document.addEventListener('DOMContentLoaded', async function () {
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', toggleFullscreen);
            }

            updateFullscreenButton();

            await loadPage(1);

            setInterval(async () => {
                await nextPage();
            }, 30000);

            setInterval(async () => {
                await refreshDataKeepPage();
            }, 1800000);
        });
    </script>
</body>
</html>