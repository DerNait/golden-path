import { chromium } from 'playwright';
import fs from 'node:fs/promises';

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const email = process.env.E2E_EMAIL || 'owner@example.com';
const password = process.env.E2E_PASSWORD || 'password';
const output = process.env.OUTPUT_DIR || './qa';
await fs.mkdir(output, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
const page = await context.newPage();
const errors = [];
let authenticated = false;

page.on('pageerror', (error) => errors.push(`page: ${error.message}`));
page.on('console', (message) => {
  const expectedRestore = !authenticated && message.type() === 'error' && message.text().includes('401');
  if (message.type() === 'error' && !expectedRestore) errors.push(`console: ${message.text()}`);
});
page.on('response', (response) => {
  const expectedRestore = !authenticated
    && response.status() === 401
    && new URL(response.url()).pathname === '/api/me';
  if (response.status() >= 400 && !expectedRestore) {
    errors.push(`http ${response.status()}: ${response.url()}`);
  }
});

await page.goto(`${baseUrl}/login`);
await page.getByLabel('Correo').fill(email);
await page.getByLabel('Contrasena').fill(password);
await page.getByRole('button', { name: 'Iniciar sesion' }).click();
await page.waitForURL(`${baseUrl}/`);
await page.getByRole('heading', { name: /Hola,/ }).waitFor();
authenticated = true;

await page.goto(`${baseUrl}/workout`);
await page.getByRole('button', { name: 'Iniciar' }).first().click();
await page.getByRole('button', { name: 'Comenzar' }).click();
await page.getByRole('heading', { name: 'Press inclinado con mancuernas' }).waitFor();
await page.getByTitle('Ver detalles de Press inclinado con mancuernas').click();
const detailDialog = page.getByRole('dialog', { name: 'Press inclinado con mancuernas' });
await detailDialog.waitFor();
await detailDialog.getByText('Sin video de referencia').waitFor();
let detailOverflow = await page.evaluate(
  () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
);
if (detailOverflow) errors.push('layout overflow on active exercise detail modal');
await detailDialog.getByTitle('Cerrar').click();

const newRow = page.locator('.sets-table tbody > tr:not(.set-details)').last();
const inputs = newRow.locator('input[type=number]');
await inputs.nth(0).fill('20');
await inputs.nth(1).fill('8');
await inputs.nth(2).fill('2');
await newRow.getByTitle('Guardar serie').click();
await page.locator('.app-toast').waitFor();
await page.getByText(/Descanso/).waitFor();

let overflow = await page.evaluate(
  () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
);
if (overflow) errors.push('layout overflow on active workout');
await page.screenshot({ path: `${output}/mobile-active-workout.png`, fullPage: true });

await page.getByLabel('Rapido').check();
for (let index = 0; index < 10; index += 1) {
  const next = page.getByRole('button', { name: /Siguiente/ });
  if (!await next.isVisible().catch(() => false)) break;
  await next.click();
}

await page.getByRole('button', { name: /Finalizar/ }).click();
await page.getByRole('button', { name: 'Completar' }).click();
await page.waitForURL(/\/history\/\d+$/);
await page.getByRole('heading', { name: 'Upper A' }).waitFor();

overflow = await page.evaluate(
  () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
);
if (overflow) errors.push('layout overflow on workout summary');
await page.screenshot({ path: `${output}/mobile-workout-summary.png`, fullPage: true });

console.log(JSON.stringify({ url: page.url(), errors }, null, 2));
await context.close();
await browser.close();
if (errors.length) process.exit(1);
