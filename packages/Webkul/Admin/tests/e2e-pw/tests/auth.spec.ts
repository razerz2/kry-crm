import { test, expect } from "../setup";

const adminCredentials = {
    email: "admin@example.com",
    password: "admin123",
};

test("should be able to login", async ({ page }) => {
    /**
     * Login as admin.
     */
    await page.goto("admin/login");
    await page.getByPlaceholder("Email Address").click();
    await page.getByPlaceholder("Email Address").fill(adminCredentials.email);
    await page.getByPlaceholder("Password").click();
    await page.getByPlaceholder("Password").fill(adminCredentials.password);
    await page.getByRole("button", { name: "Sign In" }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.getByPlaceholder("Mega Search").first()).toBeVisible();
});

test("should be able to logout", async ({ adminPage }) => {
    await expect(adminPage).toHaveURL(/\/admin\/dashboard/);

    await adminPage.locator("header button.rounded-full").first().click();
    await adminPage.getByRole("link", { name: "Sign Out" }).click();

    await expect(adminPage).toHaveURL(/\/admin\/login/);
    await expect(adminPage.locator('input[name="password"]')).toBeVisible();
});
