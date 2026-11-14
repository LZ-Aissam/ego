// Sauvegarde des données de jeu de la production : InfinityFree refuse curl (anti-robots en JavaScript),
// un vrai Chrome sans interface passe le contrôle, puis appelle /systeme/export avec le code de déploiement.
import fs from 'node:fs';
import puppeteer from 'puppeteer';

const url = process.env.APP_URL ?? 'https://ego.freedev.app';
const token = process.env.DEPLOY_TOKEN;
const out = process.argv[2] ?? 'ego-export.json';
if (!token) {
    console.error('DEPLOY_TOKEN manquant');
    process.exit(1);
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
try {
    const page = await browser.newPage();
    await page.goto(`${url}/systeme/maj`, { waitUntil: 'networkidle2', timeout: 90000 });
    await page.waitForSelector('input[name=token]', { timeout: 60000 });
    const body = await page.evaluate(async (t) => {
        const response = await fetch('/systeme/export', { method: 'POST', body: new URLSearchParams({ token: t }) });
        return { status: response.status, text: await response.text() };
    }, token);
    if (body.status !== 200) throw new Error(`Export refusé : HTTP ${body.status}`);
    const data = JSON.parse(body.text);
    const counts = Object.fromEntries(Object.entries(data).map(([table, rows]) => [table, rows.length]));
    if (!counts.users || !counts.workouts) throw new Error(`Export vide : ${JSON.stringify(counts)}`);
    fs.writeFileSync(out, body.text);
    console.log('Sauvegarde :', JSON.stringify(counts));
} finally {
    await browser.close();
}
