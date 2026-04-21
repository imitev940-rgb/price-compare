#!/usr/bin/env node
/**
 * scrape-price.js
 *
 * Използване:
 *   node scrape-price.js <url>
 *
 * Примери:
 *   node scrape-price.js "https://techmart.bg/kafeavtomat-philips-ep3341-50"
 *   node scrape-price.js "https://www.technopolis.bg/bg/..."
 *
 * Изход:
 *   { "price": 299.99, "currency": "EUR", "in_stock": true, "title": "..." }
 *   { "price": null, "error": "..." }
 */

const { chromium } = require('playwright');

// ── Блокирани ресурси ────────────────────────────────────────────────────────
const BLOCKED_TYPES    = ['image', 'media', 'font', 'stylesheet'];
const BLOCKED_PATTERNS = [
    'google-analytics', 'googletagmanager', 'facebook',
    'hotjar', 'clarity', 'doubleclick', 'gtag',
];

// ── Store конфигурации ───────────────────────────────────────────────────────
const STORE_CONFIG = {
    'techmart.bg': {
        price: [
            '.price ins .woocommerce-Price-amount bdi',
            '.price .woocommerce-Price-amount bdi',
            '.woocommerce-Price-amount bdi',
            '.entry-summary .price .amount',
        ],
        stock: ['.stock.in-stock', '.in-stock'],
        outStock: ['.stock.out-of-stock', '.out-of-stock'],
        currency: 'EUR',
    },
    'technopolis.bg': {
        price: [
            '[class*="productPrice"]',
            '[class*="price-value"]',
            '[class*="finalPrice"]',
            '[data-price]',
            '.price-box .price',
            '[itemprop="price"]',
        ],
        stock: ['.in-stock', '[class*="inStock"]', '.available'],
        outStock: ['.out-of-stock', '[class*="outOfStock"]', 'text=Продуктът не е в наличност'],
        currency: 'EUR',
    },
    'technomarket.bg': {
        price: [],   // ← празно — използва JSON-LD
        stock: ['.in-stock', '.availability-in-stock'],
        outStock: ['.out-of-stock', '.not-available'],
        currency: 'BGN',
    },
    'tehnomix.bg': {
        price: [
            '[data-price-type="finalPrice"] .price',
            '.special-price .price',
            '.price-box .price',
            '[itemprop="price"]',
            '.product-info-price .price',
        ],
        stock: ['.stock.available', '.in-stock'],
        outStock: ['.stock.unavailable', '.out-of-stock'],
        currency: 'EUR',
    },
    'zora.bg': {
        price: [
            '[class*="price"] [class*="current"]',
            '[class*="product-price"]',
            '[class*="price-value"]',
            '[itemprop="price"]',
            '[class*="price"]',
        ],
        stock: [
            'button[class*="buy"]',
            'button[class*="cart"]',
            '.btn-buy',
            'button:has-text("Купи")',
        ],
        outStock: [
            '[class*="out-of-stock"]',
            '[class*="outOfStock"]',
            '[class*="unavailable"]',
            'button:has-text("Изчерпан")',
            'button:has-text("Няма")',
        ],
        currency: 'EUR',
    },
};

const BGN_TO_EUR = 1.95583;

// ── Помощни функции ──────────────────────────────────────────────────────────

function detectStore(url) {
    for (const key of Object.keys(STORE_CONFIG)) {
        if (url.includes(key)) return key;
    }
    return null;
}

function parsePrice(raw) {
    if (!raw) return null;

    let clean = raw
        .replace(/[^\d\s,\.]/g, '')
        .replace(/\s/g, '')
        .trim();

    if (!clean) return null;

    // "1.234,56" → "1234.56"
    if (clean.includes(',') && clean.includes('.')) {
        const lastComma = clean.lastIndexOf(',');
        const lastDot   = clean.lastIndexOf('.');
        if (lastComma > lastDot) {
            clean = clean.replace(/\./g, '').replace(',', '.');
        } else {
            clean = clean.replace(/,/g, '');
        }
    } else if (clean.includes(',')) {
        if (/,\d{2}$/.test(clean)) {
            clean = clean.replace(',', '.');
        } else {
            clean = clean.replace(/,/g, '');
        }
    } else if ((clean.match(/\./g) || []).length > 1) {
        const parts   = clean.split('.');
        const decimal = parts.pop();
        clean = parts.join('') + '.' + decimal;
    }

    const value = parseFloat(clean);
    return value > 0 && value < 999999 ? value : null;
}

function extractPriceFromJsonLd(html) {
    const matches = html.matchAll(
        /<script[^>]+type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/gi
    );

    for (const match of matches) {
        try {
            const data = JSON.parse(match[1]);
            const price = findPriceInObject(data);
            if (price) return price;
        } catch { /* ignore */ }
    }

    return null;
}

function findPriceInObject(obj) {
    if (!obj || typeof obj !== 'object') return null;

    if (obj.price !== undefined) {
        const currency = (obj.priceCurrency || '').toUpperCase();
        const value    = parsePrice(String(obj.price));

        if (value && value > 0) {
            if (currency === 'BGN') return Math.round(value / BGN_TO_EUR * 100) / 100;
            return Math.round(value * 100) / 100;
        }
    }

    for (const v of Object.values(obj)) {
        const found = findPriceInObject(v);
        if (found) return found;
    }

    return null;
}

function extractPriceFromMeta(html) {
    const patterns = [
        /property="product:price:amount"\s+content="([\d\.,]+)"/i,
        /itemprop="price"\s+content="([\d\.,]+)"/i,
        /"price"\s*:\s*"([\d\.,]+)"/i,
        /"price"\s*:\s*([\d\.]+)/i,
    ];

    for (const pattern of patterns) {
        const match = html.match(pattern);
        if (match) {
            const value = parsePrice(match[1]);
            if (value && value > 0) return value;
        }
    }

    return null;
}

// ── Основна функция ──────────────────────────────────────────────────────────

async function scrapePrice(url) {
    const storeKey = detectStore(url);
    const config   = storeKey ? STORE_CONFIG[storeKey] : null;

    // Primary proxy от .env + fallback pool
    const PRIMARY_PROXY = process.env.ZORA_PROXY_HOST && process.env.ZORA_PROXY_PORT
        ? { host: process.env.ZORA_PROXY_HOST, port: process.env.ZORA_PROXY_PORT }
        : null;

    const FALLBACK_POOL = [
        { host: '96.62.194.176', port: 6378 },
        { host: '147.79.22.210', port: 7926 },
        { host: '96.62.180.188', port: 7898 },
        { host: '82.21.39.112', port: 7873 },
        { host: '82.23.61.53', port: 7805 },
    ];

    const PROXIES = PRIMARY_PROXY
        ? [PRIMARY_PROXY, ...FALLBACK_POOL.filter(p => p.host !== PRIMARY_PROXY.host)]
        : FALLBACK_POOL;

    const randomProxy = PROXIES[0];
    const proxyServer = `http://${randomProxy.host}:${randomProxy.port}`;
    process.env.ZORA_PROXY_USER = process.env.ZORA_PROXY_USER || 'tumdzdvc';
    process.env.ZORA_PROXY_PASS = process.env.ZORA_PROXY_PASS || '9zbgvzfgy3yp';

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
        proxy: proxyServer ? {
            server: proxyServer,
            username: process.env.ZORA_PROXY_USER,
            password: process.env.ZORA_PROXY_PASS,
        } : undefined,
    });

    try {
        const context = await browser.newContext({
            locale:    'bg-BG',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        });

        // Блокирай ненужни ресурси
        await context.route('**/*', (route) => {
            const type = route.request().resourceType();
            const reqUrl = route.request().url();

            if (BLOCKED_TYPES.includes(type)) return route.abort();
            if (BLOCKED_PATTERNS.some(p => reqUrl.includes(p))) return route.abort();

            return route.continue();
        });

        const page = await context.newPage();
        page.setDefaultTimeout(15000);

        const gotoTimeout = storeKey === 'technomarket.bg' ? 60000 : 40000;
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: gotoTimeout });
        await page.waitForTimeout(1000);   // изчакай JS да зареди цените

        let price    = null;
        let title    = null;
        let inStock  = null;

        // ── 1. CSS селектори (специфични за магазина) ────────────────────────
        if (config?.price) {
            for (const sel of config.price) {
                try {
                    const el = await page.$(sel);
                    if (!el) continue;

                    const text = await el.getAttribute('content')
                        || await el.getAttribute('data-price')
                        || await el.innerText();

                    if (!text) continue;

                    let value = parsePrice(text);
                    if (!value) continue;

                    // Конвертирай BGN → EUR
                    if (config.currency === 'BGN') {
                        value = Math.round(value / BGN_TO_EUR * 100) / 100;
                    }

                    if (value > 0) {
                        price = value;
                        break;
                    }
                } catch { /* ignore */ }
            }
        }

        // ── 2. JSON-LD от HTML ───────────────────────────────────────────────
        if (!price) {
            const html = await page.content();
            price = extractPriceFromJsonLd(html) || extractPriceFromMeta(html);
        }

        // ── 3. Title ─────────────────────────────────────────────────────────
        try {
            title = await page.title();
            if (!title) {
                title = await page.$eval('h1', el => el.innerText.trim()).catch(() => null);
            }
        } catch { /* ignore */ }

        // ── 4. Stock статус ──────────────────────────────────────────────────
        if (config?.stock) {
            for (const sel of config.stock) {
                if (await page.$(sel)) { inStock = true; break; }
            }
        }

        if (inStock === null && config?.outStock) {
            for (const sel of config.outStock) {
                if (await page.$(sel)) { inStock = false; break; }
            }
        }

        if (price) {
            return {
                price:    Math.round(price * 100) / 100,
                currency: 'EUR',
                in_stock: inStock,
                title:    title ? title.substring(0, 255) : null,
            };
        }

        return { price: null, error: 'Price not found', title };

    } finally {
        await browser.close();
    }
}

// ── Entry point ──────────────────────────────────────────────────────────────

(async () => {
    const url = process.argv[2];

    if (!url) {
        process.stdout.write(JSON.stringify({ price: null, error: 'Usage: node scrape-price.js <url>' }));
        process.exit(1);
    }

    const isThechnomarket = url.includes('technomarket.bg');
    const maxRetries = isThechnomarket ? 2 : 1;
    let lastErr = null;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            const result = await scrapePrice(url);
            process.stdout.write(JSON.stringify(result));
            process.exit(result.price ? 0 : 1);
        } catch (err) {
            lastErr = err;
            if (attempt < maxRetries) {
                await new Promise(r => setTimeout(r, 5000));
            }
        }
    }

    process.stdout.write(JSON.stringify({ price: null, error: lastErr?.message }));
    process.exit(1);
})();