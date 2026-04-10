const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = 'http://127.0.0.1:8000';
const loginRoute = '/admin/login';

const routes = [
    '/admin',
    '/admin/docs/project-docs',
    '/admin/api-request-logs',
    '/admin/auth-event-logs',
    '/admin/project-users',
    '/admin/project-users/create',
    '/admin/projects',
    '/admin/projects/create',
];

(async () => {
    const dir = path.join(__dirname, 'screenshots');

    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    const browser = await chromium.launch({ headless: true });

    const context = await browser.newContext({
        viewport: { width: 1280, height: 1024 },
    });

    const page = await context.newPage();

    console.log('Navigating to login page...');
    await page.goto(`${baseUrl}${loginRoute}`, {
        waitUntil: 'domcontentloaded',
        timeout: 15000,
    });

    const loginScreenshot = path.join(dir, '1_admin_login.png');

    await page.screenshot({
        path: loginScreenshot,
        fullPage: true,
    });

    console.log(`Saved screenshot to ${loginScreenshot}`);

    console.log('Logging in...');
    try {
        await page.fill('input[type="email"], input[name="email"], #email', 'test@example.com');
        await page.fill('input[type="password"], input[name="password"], #password', 'password');
        await page.click('button[type="submit"]');

        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(2000);
    } catch (e) {
        console.log('Login fields not found or login step skipped. Continuing...');
    }

    let counter = 2;

    for (const route of routes) {
        const url = `${baseUrl}${route}`;
        console.log(`Visiting: ${url}`);

        try {
            await page.goto(url, {
                waitUntil: 'domcontentloaded',
                timeout: 15000,
            });

            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForTimeout(1000);

            const safeName = route
                .replace(/\//g, '_')
                .replace(/^_+/, '')
                .replace(/[^a-zA-Z0-9_-]/g, '');

            const filename = path.join(dir, `${counter}_${safeName || 'home'}.png`);

            await page.screenshot({
                path: filename,
                fullPage: true,
            });

            console.log(`Saved screenshot to ${filename}`);
            counter++;
        } catch (e) {
            console.log(`Failed to screenshot ${url}: ${e.message}`);
        }
    }

    await browser.close();
    console.log('Finished capturing dashboard pages.');
})();
