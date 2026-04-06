const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    // Директно към search резултати
    await page.goto('https://www.technomarket.bg/search?query=Tefal+CY851130', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    console.log('URL:', page.url());

    const links = await page.$$eval('a[href]', anchors =>
        anchors.map(a => ({ href: a.href, text: (a.textContent || '').trim().substring(0, 60) }))
            .filter(a => a.href.includes('technomarket.bg/') && a.href.length > 35)
            .slice(0, 15)
    );

    console.log('Links:', JSON.stringify(links, null, 2));
    await browser.close();
})();
