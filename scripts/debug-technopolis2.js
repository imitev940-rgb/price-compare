const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('https://www.technopolis.bg/bg/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    // Намери cookie бутони
    const buttons = await page.evaluate(() => 
        Array.from(document.querySelectorAll('button')).map(e => ({
            text: e.innerText.substring(0, 50),
            id: e.id,
            class: e.className.substring(0, 50)
        }))
    );
    console.log('BUTTONS:', JSON.stringify(buttons, null, 2));
    await browser.close();
})();
