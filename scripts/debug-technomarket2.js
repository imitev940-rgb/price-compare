const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.technomarket.bg/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Escape за overlay
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    // Намери search-box
    const searchBox = await page.$('#search-box, .search-box, [id*="search"]');
    console.log('Search box found:', searchBox ? 'YES' : 'NO');

    // Всички input-и след Escape
    const inputs = await page.evaluate(() =>
        Array.from(document.querySelectorAll('input')).map(e => ({
            type: e.type,
            name: e.name,
            id: e.id,
            placeholder: e.placeholder,
            class: e.className.substring(0, 60),
            visible: e.offsetParent !== null
        })).filter(e => e.visible)
    );
    console.log('Visible inputs:', JSON.stringify(inputs, null, 2));
    await browser.close();
})();
