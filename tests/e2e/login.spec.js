import { test, expect } from '@playwright/test';

test('cuando un usuario visita la pagina de login', async ({ page }) => {
    await page.goto('http://127.0.0.1/');
    await page.getByRole('link', { name: 'Iniciar sesion' }).click();
    await page.getByRole('textbox', { name: 'Correo electronico' }).click();
    await page.getByRole('textbox', { name: 'Correo electronico' }).fill('usuarioprueba2@gmail.com');
    await page.getByRole('textbox', { name: 'Contrasena' }).click();
    await page.getByRole('textbox', { name: 'Contrasena' }).fill('123456789');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    // Si el acceso fue correcto, el sistema redirige al inicio.
    await expect(page).toHaveURL('http://127.0.0.1/');

});