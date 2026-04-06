#!/usr/bin/env node

const { chromium } = require('playwright');

const BLOCKED_TYPES    = ['image', 'media', 'font'];
const BLOCKED_PATTERNS = [
    'google-analytics', 'googletagmanager', 'facebook',
    'hotjar', 'clarity', 'doubleclick', 'gtag', 'analytics',
];

const STORE_CONFIG = {

    techmart: {
        baseUrl: 'https://techmart.bg/',
        cookieSelector: null,
        overlayEscapeKey: false,
        newsletterSelector: null,
        searchButtonSelector: null,
        directSearchUrl: null,
        useJsFill: false,
        jsFillSelector: null,
        searchSelectors: [
            '.searchInput',
            'input[placeholder="Търси..."]',
            'input[type="text"]',
        ],
        autocompleteSelectors: [
            '.autocomplete-suggestions a',
            '.search-autocomplete a',
            '[data-role="suggestions"] a',
            '.amsearch-item a',
            '.product-item a',
            'ul.dropdown-menu li a',
            '.tt-suggestion a',
            '.autocomplete a',
        ],
        waitAfterType: 1500,
        allowedPathPattern: null,
    },

    technopolis: {
        baseUrl: 'https://www.technopolis.bg/bg/',
        cookieSelector: '#CybotCookiebotDialogBodyButtonDecline',
        overlayEscapeKey: false,
        newsletterSelector: '.modal-close',
        searchButtonSelector: '.btn.js-toggle-search',
        directSearchUrl: null,
        useJsFill: false,
        jsFillSelector: null,
        searchSelectors: [
            'input[placeholder="търси ..."]',
            'input[placeholder*="търси"]',
        ],
        autocompleteSelectors: [
            '.search-results-dropdown a',
            '.autocomplete-item a',
            '[class*="search"] [class*="product"] a',
            '[class*="result"] a',
            '[class*="suggest"] a',
        ],
        waitAfterType: 1500,
        allowedPathPattern: /\/p\/\d+/i,
    },

    technomarket: {
        baseUrl: 'https://www.technomarket.bg/',
        cookieSelector: null,
        overlayEscapeKey: false,
        newsletterSelector: null,
        searchButtonSelector: null,
        directSearchUrl: 'https://www.technomarket.bg/search?query=',
        useJsFill: false,
        jsFillSelector: null,
        searchSelectors: [],
        autocompleteSelectors: [],
        waitAfterType: 3000,
        allowedPathPattern: null,
    },

    tehnomix: {
        baseUrl: 'https://www.tehnomix.bg/',
        cookieSelector: '.amgdprcookie-button.-allow.-save',
        overlayEscapeKey: false,
        newsletterSelector: '.cross.close',
        searchButtonSelector: null,
        directSearchUrl: 'https://www.tehnomix.bg/catalogsearch/result/?q=',
        useJsFill: false,
        jsFillSelector: null,
        searchSelectors: [
            'input[name="q"]',
            'input[placeholder*="Търси"]',
            'input[type="search"]',
            '#search',
        ],
        autocompleteSelectors: [
            '.amsearch-item a',
            '.autocomplete-suggestions a',
            '[data-role="suggestions"] a',
            '[class*="search"] [class*="item"] a',
            '[class*="suggest"] a',
        ],
        waitAfterType: 2000,
        allowedPathPattern: null,
    },

    zora: {
        baseUrl: 'https://zora.bg/',
        cookieSelector: null,
        overlayEscapeKey: false,
        newsletterSelector: null,
        searchButtonSelector: null,
        directSearchUrl: 'https://zora.bg/search?query=',
        useJsFill: false,
        jsFillSelector: null,
        searchSelectors: [
            'input[name="query"]',
            'input[type="search"]',
            'input[placeholder*="Търси"]',
        ],
        autocompleteSelectors: [
            '.search-results a',
            '[class*="autocomplete"] a',
            '[class*="suggest"] a',
            '[class*="search"] a',
        ],
        waitAfterType: 1500,
        allowedPathPattern: /\/product\//i,
    },
};

// ── Помощни функции ───────────────────────────────────────────────────────────

function normalizeId(str) {
    return (str || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
}

function scoreUrl(url, query) {
    const q = normalizeId(query);
    const u = normalizeId(url);
    let score = 0;

    if (q.length >= 4 && u.includes(q))                              score += 60;
    else if (q.length >= 6 && u.includes(q.slice(0, q.length - 1))) score += 40;

    const tokens = query.toUpperCase().replace(/[^A-Z0-9 ]/g, ' ').split(/\s+/).filter(t => t.length >= 3 || (t.length >= 2 && /^\d+$/.test(t)));
    for (const t of tokens) {
        if (u.includes(t)) score += 8;
    }

    return score;
}

function scoreTitle(title, query) {
    const q = normalizeId(query);
    const t = normalizeId(title);
    if (!q || !t) return 0;

    let score = 0;
    if (t.includes(q))                                               score += 60;
    else if (q.length >= 6 && t.includes(q.slice(0, -1)))           score += 40;

    const tokens = query.toUpperCase().replace(/[^A-Z0-9 ]/g, ' ').split(/\s+/).filter(x => x.length >= 3);
    for (const tok of tokens) {
        if (t.includes(tok)) score += 10;
    }

    return score;
}

/**
 * Проверява дали title-ът съответства на query-то.
 * Извлича модел номерата от query и проверява дали са в title-а.
 * Предотвратява BWD421PET вместо BWD421PRO и подобни грешки.
 */
function titleMatchesQuery(title, query) {
    if (!title || !query) return true;

    const titleNorm = normalizeId(title);
    const queryNorm = normalizeId(query);

    // Ако query-то е изцяло в title-а → ОК
    if (titleNorm.includes(queryNorm)) return true;

    // Split по spaces → после по тирета (суфикси като -26, -20 се пропускат)
    const parts = query.toUpperCase().split(/\s+/);

    for (const part of parts) {
        const subparts = part.split('-');

        for (const subpart of subparts) {
            const norm = subpart.replace(/[^A-Z0-9]/g, '');

            // Само токени с И букви И цифри (модел номера като ECAM29061B, CTPELE231)
            // Чисто цифрови суфикси (26, 20) се пропускат
            if (norm.length >= 3 && /[A-Z]/.test(norm) && /[0-9]/.test(norm)) {
                if (!titleNorm.includes(norm)) return false;
            }
        }
    }

    return true;
}

/**
 * Проверява дали URL-ът съответства на query-то.
 * Използва се когато title е празен (напр. Technopolis autocomplete).
 * Токени >= 5 символа от query трябва да са в URL-а.
 * Предотвратява CUe231 вместо CTPele231, CTPe231 вместо CTPele231.
 */
function urlMatchesQuery(url, query) {
    if (!url || !query) return true;
    const urlNorm = normalizeId(url);

    // Само токени >= 5 символа — "CTPele"=6 ✓, "231"=3 ✗ (пропуска се)
    const tokens = query.toUpperCase()
        .replace(/[^A-Z0-9 ]/g, ' ')
        .split(/\s+/)
        .filter(t => t.length >= 5);

    if (tokens.length === 0) return true;

    for (const token of tokens) {
        if (!urlNorm.includes(token)) return false;
    }

    return true;
}

function isBadUrl(url) {
    if (/\/(cart|checkout|account|wishlist|category|categories|brand|brands|blog|search|filter|compare|login|services|magazini|kontakti|loyalni|seasonal|zero-offers|promocii|profile)/i.test(url)) return true;
    // Reject WooCommerce combo/bundle URLs (e.g. /product-a-and-product-b)
    try { if (new URL(url).pathname.includes('-and-')) return true; } catch {}
    // Reject Zora bundle/package URLs
    try { if (new URL(url).pathname.includes('/product/paket-')) return true; } catch {}
    return false;
}

function cleanUrl(url) {
    try {
        const u = new URL(url);
        return u.origin + u.pathname;
    } catch {
        return url;
    }
}

// ── Основна логика ────────────────────────────────────────────────────────────

async function searchStore(storeName, query) {
    const config = STORE_CONFIG[storeName];
    if (!config) {
        return { url: null, error: 'Unknown store: ' + storeName };
    }

    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-extensions',
            '--blink-settings=imagesEnabled=false',
        ],
    });

    try {
        const context = await browser.newContext({
            locale:    'bg-BG',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            extraHTTPHeaders: { 'Accept-Language': 'bg-BG,bg;q=0.9,en;q=0.8' },
        });

        await context.route('**/*', (route) => {
            const req = route.request();
            if (BLOCKED_TYPES.includes(req.resourceType())) return route.abort();
            const url = req.url();
            if (BLOCKED_PATTERNS.some(p => url.includes(p))) return route.abort();
            return route.continue();
        });

        const page = await context.newPage();
        page.setDefaultTimeout(15000);
        page.setDefaultNavigationTimeout(20000);

        // ── Директен Search URL (Technomarket, Tehnomix) ─────────────────────
        if (config.directSearchUrl) {
            await page.goto(
                config.directSearchUrl + encodeURIComponent(query),
                { waitUntil: 'domcontentloaded' }
            );

            await page.waitForTimeout(config.waitAfterType);

            const currentUrl = page.url();

            // Ако директно редиректна към продуктова страница (Tehnomix)
            if (!currentUrl.includes('/catalogsearch/') && !currentUrl.includes('/search?')) {
                const urlScore = scoreUrl(currentUrl, query);
                if (urlScore >= 20) {
                    const title = await page.title().catch(() => '');

                    // Провери за изчерпана наличност
                    await page.waitForTimeout(1500);
                    const outOfStock = await page.evaluate(() => {
                        const text = document.body.innerText.toLowerCase();
                        return text.includes('изчерпан') ||
                               text.includes('изчерпана наличност') ||
                               text.includes('няма наличност') ||
                               text.includes('не е наличен') ||
                               text.includes('не е в наличност') ||
                               text.includes('out of stock');
                    }).catch(() => false);

                    if (outOfStock) {
                        return { url: null, error: 'Out of stock' };
                    }

                    return {
                        url:    cleanUrl(currentUrl),
                        title:  title,
                        score:  urlScore,
                        method: 'direct_redirect',
                    };
                }
            }

            const links = await page.$$eval('a[href]', anchors =>
                anchors.map(a => ({ href: a.href, text: (a.textContent || '').trim() }))
            );

            let bestUrl   = null;
            let bestScore = -1;
            let bestTitle = '';

            for (const { href, text } of links) {
                if (!href || isBadUrl(href)) continue;
                const url = cleanUrl(href);
                const s   = scoreUrl(url, query) + scoreTitle(text, query);

                if (text && !titleMatchesQuery(text, query)) continue;
                if (!text.trim() && !urlMatchesQuery(url, query)) continue;

                if (s > bestScore) {
                    bestScore = s;
                    bestUrl   = url;
                    bestTitle = text;
                }
            }

            if (bestUrl && bestScore >= 40) {
                return { url: bestUrl, title: bestTitle, score: bestScore, method: 'direct_search' };
            }

            return { url: null, error: 'No matching result found' };
        }

        // ── Стандартен подход ─────────────────────────────────────────────────

        await page.goto(config.baseUrl, { waitUntil: 'domcontentloaded' });

        // Затвори cookie banner
        if (config.cookieSelector) {
            try {
                await page.click(config.cookieSelector, { timeout: 3000 });
                await page.waitForTimeout(500);
            } catch { /* няма banner */ }
        }

        // Затвори overlay с Escape
        if (config.overlayEscapeKey) {
            try {
                await page.keyboard.press('Escape');
                await page.waitForTimeout(500);
            } catch { /* ignore */ }
        }

        // Затвори newsletter popup
        if (config.newsletterSelector) {
            try {
                await page.click(config.newsletterSelector, { timeout: 3000 });
                await page.waitForTimeout(500);
            } catch { /* няма newsletter */ }
        }

        // Кликни search бутон (Technopolis)
        if (config.searchButtonSelector) {
            try {
                await page.click(config.searchButtonSelector, { timeout: 3000 });
                await page.waitForTimeout(500);
            } catch { /* ignore */ }
        }

        // Намери search input
        let searchInput = null;
        for (const sel of config.searchSelectors) {
            try {
                searchInput = await page.waitForSelector(sel, { timeout: 4000, state: 'visible' });
                if (searchInput) break;
            } catch { /* опитай следващия */ }
        }

        if (!searchInput) {
            return { url: null, error: 'Search input not found' };
        }

        // Пиши заявката
        try {
            await searchInput.click({ clickCount: 3 });
        } catch {
            await searchInput.focus();
        }
        await searchInput.fill('');
        await searchInput.type(query, { delay: 80 });

        await page.waitForTimeout(config.waitAfterType);

        // ── Стратегия 1: Autocomplete ────────────────────────────────────────
        let bestUrl   = null;
        let bestScore = -1;
        let bestTitle = '';

        for (const sel of config.autocompleteSelectors) {
            const links = await page.$$(sel);
            if (!links.length) continue;

            for (const link of links.slice(0, 8)) {
                try {
                    const href  = await link.getAttribute('href');
                    const title = (await link.textContent() || '').trim();
                    if (!href) continue;

                    const abs = href.startsWith('http') ? href : new URL(href, config.baseUrl).href;
                    const url = cleanUrl(abs);

                    if (isBadUrl(url)) continue;
                    if (config.allowedPathPattern && !config.allowedPathPattern.test(url)) continue;

                    if (title && !titleMatchesQuery(title, query)) continue;
                    if (!title.trim() && !urlMatchesQuery(url, query)) continue;

                    const s = scoreUrl(url, query) + scoreTitle(title, query);
                    if (s > bestScore) {
                        bestScore = s;
                        bestUrl   = url;
                        bestTitle = title;
                    }
                } catch { /* ignore */ }
            }

            if (bestScore >= 40) break;
        }

        if (bestUrl && bestScore >= 15) {
            return { url: bestUrl, title: bestTitle, score: bestScore, method: 'autocomplete' };
        }

        // ── Стратегия 2: Search page ─────────────────────────────────────────
        await searchInput.press('Enter');

        try {
            await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 });
        } catch { /* продължи */ }

        await page.waitForTimeout(800);

        const links = await page.$$eval('a[href]', anchors =>
            anchors.map(a => ({ href: a.href, text: (a.textContent || '').trim() }))
        );

        bestUrl   = null;
        bestScore = -1;
        bestTitle = '';

        for (const { href, text } of links) {
            if (!href || isBadUrl(href)) continue;
            if (config.allowedPathPattern && !config.allowedPathPattern.test(href)) continue;

            if (text && !titleMatchesQuery(text, query)) continue;
            if (!text.trim() && !urlMatchesQuery(href, query)) continue;

            const url = cleanUrl(href);
            const s   = scoreUrl(url, query) + scoreTitle(text, query);

            if (s > bestScore) {
                bestScore = s;
                bestUrl   = url;
                bestTitle = text;
            }
        }

        if (bestUrl && bestScore >= 55) {
            return { url: bestUrl, title: bestTitle, score: bestScore, method: 'search_page' };
        }

        return { url: null, error: 'No matching result found' };

    } finally {
        await browser.close();
    }
}

// ── Entry point ───────────────────────────────────────────────────────────────

(async () => {
    const [, , store, ...queryParts] = process.argv;
    const query = (queryParts || []).join(' ').trim();

    if (!store || !query) {
        process.stdout.write(JSON.stringify({
            url: null,
            error: 'Usage: node search-competitor.js <store> <query>',
        }));
        process.exit(1);
    }

    try {
        const result = await searchStore(store.toLowerCase(), query);
        process.stdout.write(JSON.stringify(result));
        process.exit(result.url ? 0 : 1);
    } catch (err) {
        process.stdout.write(JSON.stringify({ url: null, error: err.message }));
        process.exit(1);
    }
})();