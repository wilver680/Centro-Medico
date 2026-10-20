import { test, expect } from '@playwright/test';

test(
  'Dado que un visitante esta en el inicio, cuando selecciona Iniciar sesion, entonces es dirigido a la pagina de acceso',
  async ({ page }) => {
    // Abrir la página de inicio.
    await page.goto('http://127.0.0.1/');

    // Hacer clic en el enlace de inicio de sesión.
    await page.getByRole('link', { name: 'Iniciar sesion' }).click();

    // Comprobar que se navegó a la ruta correcta.
    await expect(page).toHaveURL(/\/login$/);
  }
);


test(
  'Dado que un visitante abre el inicio, cuando la pagina carga, entonces ve el encabezado principal',
  async ({ page }) => {
    // Abrir la página de inicio.
    await page.goto('http://127.0.0.1/');

    // Comprobar que el mensaje principal sea visible.
    await expect(
      page.getByRole('heading', { name: /Atención médica integral/i })
    ).toBeVisible();
  }
);