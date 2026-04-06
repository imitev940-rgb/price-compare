const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.technomarket.bg/furni/gorenje-bps6737e04dbg-09236921', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Намери всички елементи с цена
    const prices = await page.evaluate(() => {
        const results = [];
        document.querySelectorAll('[class*="price"], [itemprop="price"], [data-price]').forEach(el => {
            const text = el.innerText || el.getAttribute('content') || el.getAttribute('data-price') || '';
            if (text && text.trim() && text.length < 50) {
                results.push({
                    tag: el.tagName,
                    class: (el.className || '').substring(0, 60),
                    itemprop: el.getAttribute('itemprop') || '',
                    text: text.trim().substring(0, 30)
                });
            }
        });
        return results.slice(0, 15);
    });

    console.log(JSON.stringify(prices, null, 2));
    await browser.close();
})();
