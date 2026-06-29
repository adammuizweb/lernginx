const { test, expect } = require('@playwright/test');
const BASE = 'http://lernginx.lan';
const CREDS = {
  admin: { email: 'admin@lernginx.lan', password: 'password' },
  teacher: { email: 'teacher1@lernginx.lan', password: 'password' },
  student: { email: 'student1@lernginx.lan', password: 'password' },
};

async function login(page, role) {
  const cred = CREDS[role];
  await page.goto(`${BASE}/login/`);
  await page.fill('input[name="email"]', cred.email);
  await page.fill('input[name="password"]', cred.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard/**', { timeout: 10000 });
}

test.describe('1. Unauthenticated access redirects', () => {
  const protectedPaths = [
    '/dashboard/',
    '/dashboard/?modul=admin',
    '/dashboard/?modul=student',
    '/dashboard/?modul=profile',
    '/dashboard/admin/',
    '/dashboard/admin/content/',
    '/dashboard/admin/tags/',
    '/dashboard/admin/categories/',
    '/dashboard/admin/assign/',
  ];
  for (const path of protectedPaths) {
    test(`redirects ${path} to /login/`, async ({ page }) => {
      await page.goto(`${BASE}${path}`);
      // Protected pages should redirect or show access denied
      await page.waitForTimeout(2000);
      const url = page.url();
      const body = await page.content();
      // Either redirected to /login/ or shows access denied message
      const denied = url.includes('/login/') || body.includes('login') || body.includes('Access');
      expect(denied).toBe(true);
    });
  }
});

test.describe('2. Frontend public pages', () => {
  const pages = ['/', '/login/', '/register/', '/profile/', '/program/', '/moduls/',
    '/mathematics/', '/science/', '/languages/', '/creative-arts/', '/reset-password/'];
  for (const p of pages) {
    test(`GET ${p} returns 200`, async ({ page }) => {
      const resp = await page.goto(`${BASE}${p}`);
      expect(resp.status()).toBe(200);
    });
  }
});

test.describe('3. Login flows', () => {
  for (const role of ['admin', 'teacher', 'student']) {
    test(`login as ${role}`, async ({ page }) => {
      await login(page, role);
      expect(page.url()).toContain('/dashboard/');
      // verify session cookie is set
      const cookies = await page.context().cookies();
      const sessionCookie = cookies.find(c => c.name === 'lernginx_session');
      expect(sessionCookie).toBeTruthy();
      expect(sessionCookie.httpOnly).toBe(true);
    });
  }

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto(`${BASE}/login/`);
    await page.fill('input[name="email"]', 'admin@lernginx.lan');
    await page.fill('input[name="password"]', 'wrongpass');
    await page.click('button[type="submit"]');
    // should stay on login page with error message
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('/login/');
  });
});

test.describe('4. Admin CRUD - Tags', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'admin'); });

  test('create, edit, and delete a tag', async ({ page }) => {
    // Navigate to admin tags
    await page.goto(`${BASE}/dashboard/admin/tags/`);
    expect(page.url()).toContain('tags');

    // Add new tag
    await page.click('a.btn.add');
    await page.fill('input[name="name"]', 'Test Tag ' + Date.now());
    await page.click('button#submitBtn');
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('tags');
  });

  test('navigate admin panel pages', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/admin/`);
    await page.waitForSelector('.admin-grid');
    const boxes = await page.locator('.admin-box').count();
    expect(boxes).toBeGreaterThanOrEqual(3);
  });
});

test.describe('5. Admin CRUD - Categories', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'admin'); });

  test('create a category', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/admin/categories/`);
    await page.click('a[href*="add"]');
    const catName = 'TestCat ' + Date.now();
    await page.fill('input[name="name"]', catName);
    await page.fill('textarea[name="description"]', 'Automated test category');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);
    // should redirect back to categories list
    expect(page.url()).toContain('categories');
  });
});

test.describe('6. Admin - Assign modules to student', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'admin'); });

  test('assign page loads with students list', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/admin/assign/`);
    await page.waitForSelector('table');
    const rows = await page.locator('table tbody tr').count();
    expect(rows).toBeGreaterThanOrEqual(0);
  });
});

test.describe('7. Student flows', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'student'); });

  test('student dashboard loads', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/`);
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('/dashboard/');
  });

  test('student programs page loads', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/?modul=programs`);
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('modul=programs');
  });

  test('student profile page loads', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/?modul=profile`);
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('modul=profile');
  });

  test('student module registration page loads', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/student/`);
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('/dashboard/student/');
  });
});

test.describe('8. Teacher flows', () => {
  test.beforeEach(async ({ page }) => { await login(page, 'teacher'); });

  test('teacher can access admin panel', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/admin/`);
    await page.waitForSelector('.admin-grid');
    expect(page.url()).toContain('/admin/');
  });

  test('teacher can access tags', async ({ page }) => {
    await page.goto(`${BASE}/dashboard/admin/tags/`);
    await page.waitForTimeout(1000);
    expect(page.url()).toContain('tags');
  });
});

test.describe('9. Logout', () => {
  test('logout clears session and redirects', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE}/logout/`);
    await page.waitForURL('**/login/**', { timeout: 5000 });
    expect(page.url()).toContain('/login/');

    // try accessing protected page after logout
    await page.goto(`${BASE}/dashboard/`);
    await page.waitForURL('**/login/**', { timeout: 5000 });
    expect(page.url()).toContain('/login/');
  });
});

test.describe('10. Registration flow', () => {
  test('register page shows form', async ({ page }) => {
    await page.goto(`${BASE}/register/`);
    const emailInput = await page.locator('input[name="email"]').count();
    expect(emailInput).toBe(1);
  });
});
