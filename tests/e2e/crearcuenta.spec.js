import { test, expect } from '@playwright/test';

test('cuando un usuario visita la pagina para crear cuenta', async ({ page }) => {
  await page.goto('http://127.0.0.1/');
  await page.getByRole('link', { name: 'Crear cuenta' }).click();
  await page.getByRole('button', { name: 'Crear mi cuenta' }).click();
});