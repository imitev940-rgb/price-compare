const { chromium } = require('playwright');

async function debugStore(name, url) {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    console.log('\n=== ' + name + ' ===');
    
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);
    
    // Намери всички overlay/modal елементи
    const overlays = await page.evaluate(() => {
        const results = [];
        // Cookie banners
        document.querySelectorAll('[id*="cookie"], [id*="Cookie"], [id*="gdpr"], [id*="GDPR"], [class*="cookie"], [class*="modal"], [class*="overlay"], [class*="popup"]').forEach(el => {
            if (el.offsetParent !== null) { // видим елемент
                results.push({
                    tag: el.tagName,
                    id: el.id,
                    class: el.className.substring(0, 60),
                    visible: true
                });
            }
        });
        return results.slice(0, 10);
    });
    
    console.log('Overlays:', JSON.stringify(overlays, null, 2));
    await browser.close();
}

(async () => {
    await debugStore('Technomarket', 'https://www.technomarket.bg/');
    await debugStore('Tehnomix', 'https://www.tehnomix.bg/');
})();
