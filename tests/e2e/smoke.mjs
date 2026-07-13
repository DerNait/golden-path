import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const output = process.env.OUTPUT_DIR || './qa';
const email = process.env.E2E_EMAIL || 'owner@example.com';
const password = process.env.E2E_PASSWORD || 'password';
await fs.mkdir(output, { recursive: true });

const browser = await chromium.launch({ headless: true });
const results = [];

async function verify(name, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const errors = [];
  let authenticated = false;

  page.on('console', (message) => {
    const expectedRestore = !authenticated && message.type() === 'error' && message.text().includes('401');
    if (message.type() === 'error' && !expectedRestore) errors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', (error) => errors.push(`page: ${error.message}`));
  page.on('response', (response) => {
    const expectedRestore = !authenticated
      && response.status() === 401
      && new URL(response.url()).pathname === '/api/me';
    if (response.status() >= 400 && !expectedRestore) {
      errors.push(`http ${response.status()}: ${response.url()}`);
    }
  });

  await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(output, `${name}-login.png`), fullPage: true });
  await page.getByLabel('Correo').fill(email);
  await page.getByLabel('Contrasena').fill(password);
  await page.getByRole('button', { name: 'Iniciar sesion' }).click();
  await page.waitForURL(`${baseUrl}/`);
  await page.getByRole('heading', { name: /Hola,/ }).waitFor();
  authenticated = true;

  const routes = [
    ['dashboard', '/', /Hola,/],
    ['routine', '/routine', /Upper \/ Lower/],
    ['workout', '/workout', /Elige una sesion/],
    ['progress', '/progress', /^Progreso$/],
    ['history', '/history', /^Historial$/],
    ['profile', '/profile', /^Mi perfil$/],
  ];

  for (const [slug, route, heading] of routes) {
    await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
    try {
      await page.getByRole('heading', { name: heading }).first().waitFor({ timeout: 12000 });
    } catch {
      errors.push(`missing heading ${heading} on ${route}; url=${page.url()}`);
    }
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    if (overflow) errors.push(`layout overflow on ${route}`);
    await page.screenshot({ path: path.join(output, `${name}-${slug}.png`), fullPage: true });

    if (slug === 'routine') {
      await page.getByTitle('Ver Press inclinado con mancuernas').first().click();
      await page.getByRole('dialog', { name: 'Press inclinado con mancuernas' }).waitFor();
      await page.getByText('Sin video de referencia').waitFor();
      const modalOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      );
      if (modalOverflow) errors.push('layout overflow on exercise detail modal');
      await page.screenshot({ path: path.join(output, `${name}-exercise-detail.png`), fullPage: true });
      await page.getByTitle('Cerrar').click();
    }
  }

  results.push({ name, errors });
  await context.close();
}

await verify('mobile', { width: 390, height: 844 });
await verify('desktop', { width: 1440, height: 1000 });
await browser.close();

console.log(JSON.stringify(results, null, 2));
if (results.some((result) => result.errors.length)) process.exit(1);
