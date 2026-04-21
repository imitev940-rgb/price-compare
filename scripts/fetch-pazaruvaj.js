#!/usr/bin/env node
/**
 * fetch-pazaruvaj.js
 * Връща HTML от Pazaruvaj през Playwright (заобикаля 403 блокировката)
 * Използване: node fetch-pazaruvaj.js <url>
 */

const { chromium } = require('playwright');

(async () => {
    const url = process.argv[2];
    if (!url) {
        console.error(JSON.stringify({ error: 'No URL provided' }));
        process.exit(1);
    }

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'],
    });

    try {
        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            locale: 'bg-BG',
            extraHTTPHeaders: {
                'Accept-Language': 'bg-BG,bg;q=0.9,en;q=0.8',
            },
        });

        const page = await context.newPage();

        // Blockирай ненужни ресурси
        await page.route('**/*', (route) => {
            const type = route.request().resourceType();
            if (['image', 'media', 'font', 'stylesheet'].includes(type)) {
                return route.abort();
            }
            return route.continue();
        });

        const response = await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });

        const status = response?.status() ?? 0;
        const html = await page.content();

        console.log(JSON.stringify({ status, html }));
    } catch (e) {
        console.log(JSON.stringify({ status: 0, html: '', error: e.message }));
    } finally {
        await browser.close();
    }
})();
