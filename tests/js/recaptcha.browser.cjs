const puppeteer = require('puppeteer');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
    const browser = await puppeteer.launch({executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox']});
    try {
        const page = await browser.newPage();
        await page.setContent('<meta name="recaptcha-site-key" content="test-public"><meta name="csrf-header" content="X-CSRF-TOKEN"><meta name="csrf-token" content="test-csrf"><form method="post"><input name="email" value="kept@example.test" required><button name="intent" value="save">Save</button></form><div id="uploadProgress" hidden><span id="uploadProgressLabel"></span><progress id="uploadProgressBar"></progress></div>');
        await page.evaluate(() => {
            window.actions = []; window.sent = []; window.rejectCaptcha = false;
            window.grecaptcha = {ready: fn => fn(), execute: async (key, {action}) => {
                window.actions.push(action);
                if (window.rejectCaptcha) throw new Error('Unavailable');
                return 'fresh-' + window.actions.length;
            }};
            document.addEventListener('submit', event => {
                event.preventDefault(); window.sent.push(Object.fromEntries(new FormData(event.target, event.submitter)));
            });
        });
        await page.addScriptTag({path: path.resolve(__dirname, '../../public/assets/js/recaptcha.js')});
        await page.click('button');
        await page.waitForFunction(() => window.sent.length === 1);
        assert.deepEqual(await page.evaluate(() => window.sent[0]), {email:'kept@example.test',intent:'save','g-recaptcha-response':'fresh-1'});
        await page.click('button');
        await page.waitForFunction(() => window.sent.length === 2);
        assert.equal(await page.evaluate(() => window.sent[1]['g-recaptcha-response']), 'fresh-2');
        await page.evaluate(() => { window.rejectCaptcha = true; });
        await page.click('button');
        await page.waitForFunction(() => document.querySelector('[data-recaptcha-message]').textContent.includes('failed'));
        assert.equal(await page.evaluate(() => window.sent.length), 2);
        assert.equal(await page.$eval('[name="email"]', el => el.value), 'kept@example.test');
        await page.evaluate(() => {
            window.rejectCaptcha = false;
            document.body.dataset.uploadUrl = '/api/tools/gang-sheet/inspect';
            window.XMLHttpRequest = class {
                upload = {}; open() {} setRequestHeader() {}
                send(data) { window.uploadToken = data.get('g-recaptcha-response'); this.status = 200; this.response = {ok:true,image:{width:100}}; this.onload(); }
            };
        });
        await page.addScriptTag({path: path.resolve(__dirname, '../../public/assets/js/upload-progress.js')});
        assert.equal(await page.evaluate(async () => (await dtfProgress.upload(new File(['test'],'art.png'))).width),100);
        assert.equal(await page.evaluate(() => window.actions.at(-1)), 'artwork_upload');
        assert.equal(await page.evaluate(() => window.uploadToken), 'fresh-4');
        console.log('PASS: fresh tokens on each submission, submitter preserved, failure retry/input preservation, and artwork upload token.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
