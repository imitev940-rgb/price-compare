const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.tehnomix.bg/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Затвори cookie
    try {
        await page.click('.amgdprcookie-button.-allow.-save', { timeout: 3000 });
        await page.waitForTimeout(500);
    } catch {}

    // Затвори newsletter
    try {
        await page.click('.cross.close', { timeout: 3000 });
        await page.waitForTimeout(500);
    } catch {}

    // Намери input
    const input = await page.$('input[name="q"]');
    if (!input) { console.log('INPUT NOT FOUND'); await browser.close(); return; }

    await input.click();
    await input.type('Gorenje D2HNE7E', { delay: 80 });
    await page.waitForTimeout(2000);

    // Снимка на всички линкове в autocomplete
    const allLinks = await page.$$eval('a', els =>
        els.map(e => ({
            href: e.href,
            text: (e.textContent || '').trim().substring(0, 50),
            visible: e.offsetParent !== null
        })).filter(e => e.visible && e.href.includes('tehnomix'))
    );

    console.log('Visible links:', JSON.stringify(allLinks.slice(0, 15), null, 2));

    // Screenshot за да видим какво е на екрана
    await page.screenshot({ path: '/tmp/tehnomix-debug.png' });
    console.log('Screenshot saved to /tmp/tehnomix-debug.png');

    await browser.close();
})();
