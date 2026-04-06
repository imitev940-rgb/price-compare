const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.technomarket.bg/furni/gorenje-bps6737e04dbg-09236921', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    const jsonld = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[type="application/ld+json"]');
        return Array.from(scripts).map(s => s.innerText);
    });

    console.log(JSON.stringify(jsonld, null, 2));
    await browser.close();
})();
