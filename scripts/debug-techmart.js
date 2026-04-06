const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://techmart.bg/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    const inputs = await page.evaluate(() => 
        Array.from(document.querySelectorAll('input')).map(e => ({
            type: e.type,
            name: e.name,
            id: e.id,
            placeholder: e.placeholder,
            class: e.className.substring(0, 50)
        }))
    );
    console.log(JSON.stringify(inputs, null, 2));
    await browser.close();
})();
