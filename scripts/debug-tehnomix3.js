const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    // Директно към search
    await page.goto('https://www.tehnomix.bg/catalogsearch/result/?q=Gorenje+D2HNE7E', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    const links = await page.$$eval('a', els =>
        els.map(e => ({
            href: e.href,
            text: (e.textContent || '').trim().substring(0, 60),
            visible: e.offsetParent !== null
        })).filter(e => e.visible && e.href.includes('tehnomix.bg/') && e.href.length > 30)
    );

    console.log('URL:', page.url());
    console.log('Links:', JSON.stringify(links.slice(0, 15), null, 2));
    await browser.close();
})();
