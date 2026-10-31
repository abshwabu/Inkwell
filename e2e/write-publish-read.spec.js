import { test, expect } from '@playwright/test';

test.describe('Write → Publish → Read as a Guest', () => {
  test('author registers, writes story, publishes it, and guest reads it', async ({ browser, page }) => {
    const timestamp = Date.now();
    const username = `author_${timestamp}`;
    const email = `author_${timestamp}@example.com`;
    const password = 'Password123!';
    const storyTitle = `Playwright Verified Story ${timestamp}`;

    // 1. Register as a new author
    await page.goto('/register');
    await page.fill('input#name', 'Playwright Author');
    await page.fill('input#username', username);
    await page.fill('input#email', email);
    await page.fill('input#password', password);
    await page.fill('input#password_confirmation', password);
    await page.getByRole('button', { name: 'Register' }).click();

    // Wait for redirect to onboarding or dashboard
    await page.waitForURL(/\/(onboarding|dashboard)/);

    // 2. Navigate to Write page
    await page.goto('/write');
    await expect(page).toHaveURL(/\/write/);

    // 3. Enter Story Title
    const titleInput = page.locator('input[placeholder="Title..."]');
    await titleInput.fill(storyTitle);

    // 4. Enter Story Content into Rich Editor (>100 words to satisfy minimum word count)
    const editor = page.locator('.tiptap.ProseMirror');
    await editor.click();
    const words = Array(120).fill('innovation').join(' ');

    // Type or insert text into ProseMirror and trigger input event
    await page.evaluate((text) => {
      const pm = document.querySelector('.tiptap.ProseMirror');
      if (pm) {
        pm.innerHTML = `<p>${text}</p>`;
        pm.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }, words);

    // Brief sync pause
    await page.waitForTimeout(800);

    // 5. Click Publish button
    const publishButton = page.getByRole('button', { name: 'Publish' });
    await expect(publishButton).toBeEnabled({ timeout: 10000 });
    await publishButton.click();

    // Wait for redirect to post page or dashboard
    await page.waitForURL(/\/(posts|dashboard|[a-zA-Z0-9_\-]+)/, { timeout: 15000 });

    // 6. Now open a brand new clean Guest browser context (no cookies or session)
    const guestContext = await browser.newContext();
    const guestPage = await guestContext.newPage();

    // 7. Guest searches for the story
    await guestPage.goto(`/search?q=${encodeURIComponent(storyTitle)}`);
    await expect(guestPage.locator('body')).toContainText(storyTitle);

    // 8. Guest clicks directly to the story
    const storyLink = guestPage.locator(`a:has-text("${storyTitle}")`).first();
    await storyLink.click();

    // 9. Verify guest can read the published post title, author name, and content
    await expect(guestPage.locator('h1')).toContainText(storyTitle);
    await expect(guestPage.locator('body')).toContainText('Playwright Author');
    await expect(guestPage.locator('body')).toContainText('innovation');

    await guestContext.close();
  });
});
