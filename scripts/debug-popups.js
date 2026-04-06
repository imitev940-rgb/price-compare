const { chromium } = require('playwright');

async function debugStore(name, url) {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    console.log('\n=== ' + name + ' ===');
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);

    const closeButtons = await page.evaluate(() => {
        const results = [];
        document.querySelectorAll('button, a, div, span').forEach(el => {
            const text = (el.innerText || el.getAttribute('aria-label') || '').trim();
            const cls  = (el.className && typeof el.className === 'string') ? el.className : '';
            const id   = el.id || '';

            if (
                text.toLowerCase().includes('close') ||
                text.toLowerCase().includes('затвори') ||
                text.toLowerCase().includes('откажи') ||
                text.toLowerCase().includes('decline') ||
                cls.includes('close') ||
                cls.includes('dismiss') ||
                cls.includes('newsletter') ||
                id.includes('close') ||
                id.includes('newsletter')
            ) {
                results.push({
                    tag: el.tagName,
                    text: text.substring(0, 40),
                    id:   id.substring(0, 60),
                    class: cls.substring(0, 60)
                });
            }
        });
        return results.slice(0, 15);
    });

    console.log('Close buttons:', JSON.stringify(closeButtons, null, 2));
    await browser.close();
}

(async () => {
    await debugStore('Technopolis', 'https://www.technopolis.bg/bg/');
    await debugStore('Technomarket', 'https://www.technomarket.bg/');
    await debugStore('Tehnomix', 'https://www.tehnomix.bg/');
})();
