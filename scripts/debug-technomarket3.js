const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.technomarket.bg/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Escape overlay
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // JS fill
    await page.evaluate(({ sel, val }) => {
        const input = document.querySelector(sel);
        if (!input) return;
        const overlay = document.getElementById('search-box');
        if (overlay) overlay.style.pointerEvents = 'none';
        input.value = val;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.focus();
    }, { sel: '#search', val: 'Tefal CY851130' });

    await page.waitForTimeout(1500);
    await page.keyboard.press('Enter');

    try {
        await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch {}

    await page.waitForTimeout(1000);

    // Вземи всички линкове с техния score
    const links = await page.$$eval('a[href]', anchors =>
        anchors.map(a => ({ href: a.href, text: (a.textContent || '').trim().substring(0, 60) }))
            .filter(a => a.href.includes('technomarket.bg') && !a.href.includes('/search'))
            .slice(0, 20)
    );

    console.log('Current URL:', page.url());
    console.log('Links found:', JSON.stringify(links, null, 2));

    await browser.close();
})();
