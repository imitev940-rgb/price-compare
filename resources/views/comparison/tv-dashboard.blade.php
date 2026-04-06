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

        .tv-fullscreen-btn:active { transform: scale(0.98); }

        #exitFullscreenBtn { display: none; }

        .tv-columns {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            overflow: hidden;
        }

        .tv-col {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 24px;
            padding: 16px;
            display: flex;
            flex-direction: column;
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

        .tv-col-title { font-size: 24px; font-weight: 900; line-height: 1; }
        .tv-col-count { font-size: 20px; font-weight: 900; line-height: 1; }
        .tv-col-sub   { font-size: 14px; color: #cbd5e1; margin-bottom: 12px; }

        /* Page dots */
        .tv-page-dots {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }
        .tv-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: background 0.3s, transform 0.3s;
        }
        .tv-dot.active {
            background: rgba(255,255,255,0.85);
            transform: scale(1.3);
        }

        .tv-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow: hidden;
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .tv-list.fade-out {
            opacity: 0;
            transform: translateY(-16px);
        }

        .tv-list.fade-in {
            opacity: 0;
            transform: translateY(16px);
        }

        .tv-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 12px 16px;
            min-height: 0;
            display: flex;
            max-height: 20vh;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
            transition: border-color 0.45s ease, box-shadow 0.45s ease;
        }

        .tv-card-empty {
            border-radius: 18px;
            min-height: 0;
        }

        .tv-card.updated {
            animation: tvPulse 2.4s ease;
            border-color: rgba(250, 204, 21, 0.95);
            box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.35), 0 0 34px rgba(250, 204, 21, 0.22);
        }

        .tv-card.entered-top    { animation: enteredTop    2.6s ease; }
        .tv-card.entered-not-top{ animation: enteredNotTop 2.6s ease; }

        @keyframes tvPulse {
            0%   { transform: scale(1);    box-shadow: 0 0 0 0 rgba(250,204,21,0); }
            25%  { transform: scale(1.02); box-shadow: 0 0 0 1px rgba(250,204,21,0.45), 0 0 38px rgba(250,204,21,0.30); }
            100% { transform: scale(1);    box-shadow: 0 0 0 1px rgba(250,204,21,0.15); }
        }

        @keyframes enteredTop {
            0%   { transform: translateX(40px) scale(0.98); opacity: 0.2; background: rgba(34,197,94,0.28); }
            50%  { transform: translateX(0) scale(1.02); opacity: 1; box-shadow: 0 0 28px rgba(34,197,94,0.30); }
            100% { transform: translateX(0) scale(1); background: rgba(255,255,255,0.06); }
        }

        @keyframes enteredNotTop {
            0%   { transform: translateX(-40px) scale(0.98); opacity: 0.2; background: rgba(244,63,94,0.28); }
            50%  { transform: translateX(0) scale(1.02); opacity: 1; box-shadow: 0 0 28px rgba(244,63,94,0.30); }
            100% { transform: translateX(0) scale(1); background: rgba(255,255,255,0.06); }
        }

        .tv-product {
            font-size: clamp(14px, 1.6vw, 22px);
            font-weight: 900;
            margin-bottom: 6px;
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
            font-size: clamp(13px, 1.4vw, 20px);
            font-weight: 900;
            color: #fff;
            line-height: 1.08;
        }

        .tv-number { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .tv-store  { font-size: clamp(11px, 1.1vw, 16px); line-height: 1.05; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
        .tv-chip-red   { color: #fda4af; font-weight: 900; }
        .tv-chip-green { color: #86efac; font-weight: 900; }

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

        .tv-marquee span { margin: 0 36px; font-size: 18px; color: #e2e8f0; }

        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        @media (max-width: 1200px) {
            .tv-title   { font-size: 34px; }
            .tv-columns { grid-template-columns: 1fr; }
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
            <button id="enterFullscreenBtn" class="tv-fullscreen-btn" type="button">⛶ Full Screen</button>
            <button id="exitFullscreenBtn"  class="tv-fullscreen-btn" type="button">✕ Exit Full Screen</button>
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
            <div class="tv-col-sub">Products not #1 in Pazaruvaj with diff above 5€</div>
            <div id="notTopDots" class="tv-page-dots"></div>
            <div id="notTopList" class="tv-list"></div>
        </div>

        <div class="tv-col">
            <div class="tv-col-title-row">
                <div class="tv-col-title" style="color:#86efac;">TOP</div>
                <div id="topTotal" class="tv-col-count" style="color:#86efac;">0</div>
            </div>
            <div class="tv-col-sub">Products #1 where next offer is above 5€ away</div>
            <div id="topDots" class="tv-page-dots"></div>
            <div id="topList" class="tv-list"></div>
        </div>
    </div>

    <div class="tv-marquee-wrap">
        <div class="tv-marquee">
            <span>✦ Live monitoring active</span>
            <span>✦ Refresh data every 30 minutes</span>
            <span>✦ Page rotation every 30 seconds</span>
            <span>✦ Not Top shows only products with diff above 5€</span>
            <span>✦ Not Top also shows lowest store</span>
            <span>✦ Top shows only products where next offer after ours is above 5€</span>
            <span>✦ Technopolis • Technomarket • Techmart • Tehnomix • Zora • Pazaruvaj</span>
            <span>✦ Live monitoring active</span>
            <span>✦ Refresh data every 30 minutes</span>
            <span>✦ Page rotation every 30 seconds</span>
            <span>✦ Not Top shows only products with diff above 5€</span>
            <span>✦ Not Top also shows lowest store</span>
            <span>✦ Top shows only products where next offer after ours is above 5€</span>
            <span>✦ Technopolis • Technomarket • Techmart • Tehnomix • Zora • Pazaruvaj</span>
        </div>
    </div>
</div>

<script>
    const ITEMS_PER_PAGE = 5;
    const PAGE_INTERVAL  = 30000;

    let allNotTop  = [];
    let allTop     = [];
    let notTopPage = 0;
    let topPage    = 0;
    let previousSnapshot = { not_top: new Map(), top: new Map() };

    function buildCard(p, isTop, oldMap) {
        const sig   = JSON.stringify({ our_price: p.our_price, position: p.position ?? null, diff_amount: p.diff_amount ?? null, lead_euro: p.lead_euro ?? null });
        const wasHere = oldMap.has(String(p.id));
        const changed = wasHere && oldMap.get(String(p.id)) !== sig;
        let cls = 'tv-card';
        if (changed) cls += ' updated';
        else if (!wasHere) cls += isTop ? ' entered-top' : ' entered-not-top';

        if (isTop) {
            return `<div class="${cls}">
                <div class="tv-product">${p.name}</div>
                <div class="tv-meta top">
                    <div><span class="tv-meta-label">Our</span><span class="tv-meta-value tv-number">${p.our_price} €</span></div>
                    <div><span class="tv-meta-label">Next Price</span><span class="tv-meta-value tv-number">${p.next_competitor_price} €</span></div>
                    <div><span class="tv-meta-label">Competitor</span><span class="tv-meta-value tv-chip-green tv-store">${p.next_competitor_store}</span></div>
                    <div><span class="tv-meta-label">Lead €</span><span class="tv-meta-value tv-chip-green tv-number">+${p.lead_euro}</span></div>
                    <div><span class="tv-meta-label">Lead %</span><span class="tv-meta-value tv-chip-green tv-number">${p.lead_percent}%</span></div>
                </div>
            </div>`;
        } else {
            return `<div class="${cls}">
                <div class="tv-product">${p.name}</div>
                <div class="tv-meta not-top">
                    <div><span class="tv-meta-label">Our</span><span class="tv-meta-value tv-number">${p.our_price} €</span></div>
                    <div><span class="tv-meta-label">Lowest</span><span class="tv-meta-value tv-number">${p.lowest_price} €</span></div>
                    <div><span class="tv-meta-label">Store</span><span class="tv-meta-value tv-chip-red tv-store">${p.lowest_store ?? '—'}</span></div>
                    <div><span class="tv-meta-label">Pos</span><span class="tv-meta-value tv-chip-red tv-number">#${p.position}</span></div>
                    <div><span class="tv-meta-label">Diff €</span><span class="tv-meta-value tv-chip-red tv-number">${p.diff_amount} €</span></div>
                    <div><span class="tv-meta-label">Diff %</span><span class="tv-meta-value tv-chip-red tv-number">${p.diff_percent}%</span></div>
                </div>
            </div>`;
        }
    }

    function renderPage(listId, dotsId, items, pageIndex, isTop, oldMap) {
        const listEl = document.getElementById(listId);
        const dotsEl = document.getElementById(dotsId);
        if (!listEl) return;

        const total = Math.max(1, Math.ceil(items.length / ITEMS_PER_PAGE));
        const slice = items.slice(pageIndex * ITEMS_PER_PAGE, (pageIndex + 1) * ITEMS_PER_PAGE);

        if (dotsEl) {
            dotsEl.innerHTML = Array.from({ length: total }, (_, i) =>
                `<div class="tv-dot${i === pageIndex ? ' active' : ''}"></div>`
            ).join('');
        }

        listEl.classList.add('fade-out');

        setTimeout(() => {
            const cards = slice.map(p => buildCard(p, isTop, oldMap));
            while (cards.length < ITEMS_PER_PAGE) cards.push('<div class="tv-card-empty" style="height:calc((100vh - 260px)/5 - 8px); flex-shrink:0;"></div>');
            listEl.innerHTML = cards.join('');

            listEl.classList.remove('fade-out');
            listEl.classList.add('fade-in');
            requestAnimationFrame(() => requestAnimationFrame(() => listEl.classList.remove('fade-in')));
        }, 350);
    }

    function advancePages() {
        const ntPages = Math.max(1, Math.ceil(allNotTop.length / ITEMS_PER_PAGE));
        const tPages  = Math.max(1, Math.ceil(allTop.length / ITEMS_PER_PAGE));
        notTopPage = (notTopPage + 1) % ntPages;
        topPage    = (topPage    + 1) % tPages;
        renderPage('notTopList', 'notTopDots', allNotTop, notTopPage, false, previousSnapshot.not_top);
        renderPage('topList',    'topDots',    allTop,    topPage,    true,  previousSnapshot.top);
    }

    async function loadData() {
        try {
            const res  = await fetch('/tv-dashboard-data?page=1', { cache: 'no-store' });
            const data = await res.json();

            const newNTMap = new Map();
            const newTMap  = new Map();

            allNotTop = data.not_top ?? [];
            allTop    = data.top    ?? [];

            allNotTop.forEach(p => newNTMap.set(String(p.id), JSON.stringify(p)));
            allTop.forEach(p    => newTMap.set(String(p.id),  JSON.stringify(p)));

            previousSnapshot.not_top = newNTMap;
            previousSnapshot.top     = newTMap;

            notTopPage = 0;
            topPage    = 0;

            renderPage('notTopList', 'notTopDots', allNotTop, 0, false, newNTMap);
            renderPage('topList',    'topDots',    allTop,    0, true,  newTMap);

            document.getElementById('notTopTotal').textContent = data.not_top_total ?? allNotTop.length;
            document.getElementById('topTotal').textContent    = data.top_total    ?? allTop.length;

            const lastLoad = document.getElementById('lastLoadTime');
            if (lastLoad) lastLoad.textContent = 'Last load: ' + (data.updated_at ?? new Date().toLocaleString());

        } catch (e) {
            console.error('TV load error', e);
        }
    }

    function updateFullscreenButtons() {
        const inFS = !!document.fullscreenElement;
        document.getElementById('enterFullscreenBtn').style.display = inFS ? 'none' : '';
        document.getElementById('exitFullscreenBtn').style.display  = inFS ? 'inline-block' : 'none';
    }

    async function enterFullscreen() {
        try { await document.documentElement.requestFullscreen(); } catch(e) {}
        updateFullscreenButtons();
    }

    async function exitFullscreen() {
        try { await document.exitFullscreen(); } catch(e) {}
        updateFullscreenButtons();
    }

    document.addEventListener('fullscreenchange', updateFullscreenButtons);

    document.addEventListener('DOMContentLoaded', async () => {
        document.getElementById('enterFullscreenBtn').addEventListener('click', enterFullscreen);
        document.getElementById('exitFullscreenBtn').addEventListener('click', exitFullscreen);
        updateFullscreenButtons();
        await loadData();
        setInterval(advancePages, PAGE_INTERVAL);
        setInterval(loadData, 1800000);
    });
</script>
</body>
</html>