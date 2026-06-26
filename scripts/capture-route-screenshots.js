// scripts/capture-route-screenshots.js
//
// Visits every important route on the local Laravel server, takes desktop
// + mobile full-page screenshots, and writes a report.md + index.html gallery.
//
// Usage:
//   php artisan serve
//   npm run capture:screens
//
// Override credentials via env vars (see CREDS below).

import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);

// ────────────────────────── Config ──────────────────────────

const BASE_URL      = process.env.BASE_URL      || 'http://127.0.0.1:8000';
const AUTH_ENABLED  = (process.env.AUTH_ENABLED ?? 'true') === 'true';
const LOGIN_URL     = '/login';

// Per-role credentials. All four match the seeded demo accounts.
// Run `php artisan db:seed --class=DemoUsersSeeder` if these don't exist.
const CREDS = {
    student:    { email: process.env.STUDENT_EMAIL     || 'student@gt.test', password: process.env.STUDENT_PASSWORD     || 'password' },
    teacher:    { email: process.env.TEACHER_EMAIL     || 'teacher@gt.test', password: process.env.TEACHER_PASSWORD     || 'password' },
    admin:      { email: process.env.ADMIN_EMAIL       || 'school@gt.test',  password: process.env.ADMIN_PASSWORD       || 'password' },
    superadmin: { email: process.env.SUPER_ADMIN_EMAIL || 'super@gt.test',   password: process.env.SUPER_ADMIN_PASSWORD || 'password' },
};

const SAMPLE_QUESTION_ID = process.env.SAMPLE_QUESTION_ID || '1';
const SAMPLE_TEST_ID     = process.env.SAMPLE_TEST_ID     || '1';
const SAMPLE_RESULT_ID   = process.env.SAMPLE_RESULT_ID   || '1';

const VIEWPORTS = {
    desktop: { width: 1440, height: 1200 },
    mobile:  { width: 390,  height: 844  },
};

const OUT_DIR    = path.resolve(__dirname, '..', 'screenshots', 'routes');
const SHOTS_DIR  = path.join(OUT_DIR, 'shots');
const NAV_TIMEOUT_MS    = 25_000;
const POST_LOAD_DELAY   = 700;     // small wait for Livewire/Alpine paint

// ────────────────────────── Routes ──────────────────────────
//
// role:
//   null         → public, no auth
//   'guest'      → public; only captured when not logged in (login/register)
//   'student'    → log in as student
//   'teacher'    → log in as teacher
//   'admin'      → log in as school admin (covers /admin/* via isAdmin())
//   'superadmin' → log in as super admin (full platform access)

const ROUTES = [
    // ── Public ────────────────────────────────────────────────
    { name: 'public-home',         path: '/',         role: null },
    { name: 'public-pricing',      path: '/pricing',  role: null },
    { name: 'public-about',        path: '/about',    role: null },
    { name: 'public-contact',      path: '/contact',  role: null },

    // ── Auth (must NOT be logged in) ──────────────────────────
    { name: 'auth-login',           path: '/login',           role: 'guest' },
    { name: 'auth-register',        path: '/register',        role: 'guest' },
    { name: 'auth-forgot-password', path: '/forgot-password', role: 'guest' },

    // ── Admin (super admin can see everything) ────────────────
    { name: 'admin-dashboard',         path: '/admin',                                          role: 'superadmin' },
    { name: 'admin-analytics',         path: '/admin/analytics',                                role: 'superadmin' },
    { name: 'admin-questions',         path: '/admin/questions',                                role: 'superadmin' },
    { name: 'admin-question-review',   path: `/admin/questions/${SAMPLE_QUESTION_ID}/review`,   role: 'superadmin' },
    { name: 'admin-papers',            path: '/admin/papers',                                   role: 'superadmin' },
    { name: 'admin-papers-overview',   path: '/admin/papers-overview',                          role: 'superadmin' },
    { name: 'admin-topics',            path: '/admin/topics',                                   role: 'superadmin' },
    { name: 'admin-users',             path: '/admin/users',                                    role: 'superadmin' },
    { name: 'admin-imports',           path: '/admin/imports',                                  role: 'superadmin' },

    // ── Teacher ───────────────────────────────────────────────
    { name: 'teacher-dashboard',       path: '/teacher',                  role: 'teacher' },
    { name: 'teacher-test-generator',  path: '/teacher/test-generator',   role: 'teacher' },
    { name: 'teacher-question-picker', path: '/teacher/question-picker',  role: 'teacher' },
    { name: 'teacher-submissions',     path: '/teacher/submissions',      role: 'teacher' },
    { name: 'teacher-bank',            path: '/teacher/bank',             role: 'teacher' },
    { name: 'teacher-classes',         path: '/teacher/classes',          role: 'teacher' },

    // ── Student ───────────────────────────────────────────────
    { name: 'student-dashboard',       path: '/student',                                 role: 'student' },
    { name: 'student-tests',           path: '/student/tests',                           role: 'student' },
    { name: 'student-test-attempt',    path: `/student/tests/${SAMPLE_TEST_ID}`,         role: 'student' },
    { name: 'student-result',          path: `/student/results/${SAMPLE_RESULT_ID}`,     role: 'student' },
    { name: 'student-review',          path: '/student/review',                          role: 'student' },
    { name: 'student-practice',        path: '/student/practice',                        role: 'student' },
    { name: 'student-analytics',       path: '/student/analytics',                       role: 'student' },

    // ── Settings (any authed role) ────────────────────────────
    { name: 'settings-profile',        path: '/settings/profile',     role: 'student' },
    { name: 'settings-password',       path: '/settings/password',    role: 'student' },
    { name: 'settings-appearance',     path: '/settings/appearance',  role: 'student' },
];

// ────────────────────────── Helpers ─────────────────────────

function ensureDir(dir) { fs.mkdirSync(dir, { recursive: true }); }

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

async function loginAs(context, role) {
    if (!AUTH_ENABLED) return { ok: true };
    const cred = CREDS[role];
    if (!cred) return { ok: false, error: `No credentials configured for role: ${role}` };

    const page = await context.newPage();
    try {
        await page.goto(BASE_URL + LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT_MS });
        await page.locator('input[type="email"], input[name="email"]').first().fill(cred.email);
        await page.locator('input[type="password"], input[name="password"]').first().fill(cred.password);

        // Livewire intercepts the submit click - wait until URL leaves /login.
        await Promise.all([
            page.waitForURL(u => !new URL(u).pathname.includes('/login'), { timeout: NAV_TIMEOUT_MS }).catch(() => {}),
            page.locator('button[type="submit"]').first().click(),
        ]);
        await page.waitForTimeout(400);

        const u = new URL(page.url());
        if (u.pathname.includes('/login')) {
            // Try to surface the validation error
            const err = await page.locator('p').filter({ hasText: /failed|incorrect|invalid|These credentials/i }).first().textContent().catch(() => null);
            return { ok: false, error: err?.trim() || 'still on /login after submit' };
        }
        return { ok: true };
    } catch (e) {
        return { ok: false, error: e.message };
    } finally {
        await page.close();
    }
}

async function captureOne(context, route, viewport) {
    const page = await context.newPage();
    await page.setViewportSize(viewport);
    const result = { name: route.name, path: route.path, role: route.role || 'public', viewport: route.viewport, status: null, error: null, success: false };
    try {
        const resp = await page.goto(BASE_URL + route.path, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT_MS });
        result.status = resp?.status() ?? null;
        await page.waitForLoadState('networkidle', { timeout: NAV_TIMEOUT_MS }).catch(() => {});
        await page.waitForTimeout(POST_LOAD_DELAY);
        const filename = `${route.name}-${route.viewport}.png`;
        await page.screenshot({ path: path.join(SHOTS_DIR, filename), fullPage: true });
        result.success = true;
        result.file = `shots/${filename}`;
    } catch (e) {
        result.error = e.message;
    } finally {
        await page.close();
    }
    return result;
}

function renderReportMd(results) {
    const ok   = results.filter(r => r.success).length;
    const fail = results.length - ok;
    let md = `# Route screenshot capture report\n\n`;
    md += `Base URL: \`${BASE_URL}\`\n\n`;
    md += `Captures: **${results.length}** total · ${ok} ok · ${fail} failed\n\n`;
    md += `| Route | Role | Viewport | HTTP | Result | Screenshot |\n`;
    md += `|---|---|---|---|---|---|\n`;
    for (const r of results) {
        const status = r.success ? '✅' : '❌';
        const file   = r.success ? `[png](${r.file})` : '-';
        const err    = r.error ? ' - ' + r.error.split('\n')[0] : '';
        md += `| \`${r.path}\` | ${r.role} | ${r.viewport} | ${r.status ?? '-'} | ${status}${err} | ${file} |\n`;
    }
    return md;
}

function renderIndexHtml(results) {
    const grouped = {};
    for (const r of results) {
        grouped[r.name] ??= { name: r.name, path: r.path, role: r.role };
        grouped[r.name][r.viewport] = r;
    }
    const cards = Object.values(grouped).map(g => {
        const desktop = g.desktop;
        const mobile  = g.mobile;
        const fail = (s) => `<small style="color:#DC2626">${escapeHtml(s.error || 'failed')}</small>`;
        const cell = (s) => s?.success
            ? `<a href="${s.file}" target="_blank"><img src="${s.file}" loading="lazy"/></a>`
            : `<div class="empty">${s ? fail(s) : 'no capture'}</div>`;
        return `
        <article class="card">
            <header>
                <h2>${escapeHtml(g.name)}</h2>
                <p><code>${escapeHtml(g.path)}</code> · <span class="role role-${escapeHtml(g.role)}">${escapeHtml(g.role)}</span></p>
            </header>
            <div class="grid">
                <div>
                    <p>Desktop ${desktop?.success ? '✅ ' + (desktop.status ?? '') : '❌'}</p>
                    ${cell(desktop)}
                </div>
                <div>
                    <p>Mobile ${mobile?.success ? '✅ ' + (mobile.status ?? '') : '❌'}</p>
                    ${cell(mobile)}
                </div>
            </div>
        </article>`;
    }).join('\n');

    const ok = results.filter(r => r.success).length;
    const fail = results.length - ok;

    return `<!DOCTYPE html>
<html><head><meta charset="utf-8"/><title>Route screenshots</title>
<style>
    :root { --emerald:#0B3D2E; --gold:#D4A437; --ivory:#FAF7EF; --border:#E5E7EB; --slate:#475569; --paper:#fff; }
    body { font: 14px/1.5 -apple-system, BlinkMacSystemFont, sans-serif; background: var(--ivory); color:#111827; margin:0; padding:32px; }
    h1 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 36px; color: var(--emerald); margin: 0 0 6px; letter-spacing: -0.01em; }
    .summary { color: var(--slate); margin-bottom: 32px; }
    .legend { display: flex; gap: 8px; font-size: 11px; margin-bottom: 24px; flex-wrap: wrap; }
    .legend span { padding: 4px 10px; border-radius: 999px; background: var(--paper); border: 1px solid var(--border); }
    .card { background: var(--paper); border: 1px solid var(--border); border-radius: 12px; padding: 18px 18px 22px; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(15,23,42,0.04); }
    .card header h2 { font-size: 16px; margin: 0 0 4px; color: var(--emerald); font-weight: 600; }
    .card header p { margin: 0; font-size: 12px; color: var(--slate); }
    .card code { background: #F8FAFC; padding: 2px 6px; border-radius: 4px; font-size: 12px; color: var(--emerald); }
    .role { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-left: 6px; }
    .role-public { background: #ECF4EE; color: var(--emerald); }
    .role-guest  { background: #F1F5F9; color: var(--slate); }
    .role-student{ background: #FBF5E2; color: #A8801F; }
    .role-teacher{ background: #FBF5E2; color: #A8801F; }
    .role-admin, .role-superadmin { background: var(--emerald); color: var(--ivory); }
    .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-top: 12px; }
    .grid > div { display: flex; flex-direction: column; gap: 8px; }
    .grid p { margin: 0; font-size: 11px; color: var(--slate); font-weight: 500; }
    .grid img { width: 100%; height: auto; border: 1px solid var(--border); border-radius: 8px; display: block; }
    .empty { background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 32px; text-align: center; color: #94A3B8; font-size: 12px; }
</style></head><body>
    <h1>Route screenshots</h1>
    <p class="summary">${ok} ok · ${fail} failed · ${results.length} captures · base <code>${escapeHtml(BASE_URL)}</code></p>
    <div class="legend">
        <span>desktop 1440 × 1200</span>
        <span>mobile 390 × 844</span>
        <span>fullPage: true</span>
    </div>
    ${cards}
</body></html>`;
}

// ────────────────────────── Main ────────────────────────────

async function main() {
    ensureDir(OUT_DIR);
    ensureDir(SHOTS_DIR);

    console.log(`Base URL : ${BASE_URL}`);
    console.log(`Output   : ${OUT_DIR}\n`);

    const browser = await chromium.launch();
    const results = [];

    // Group routes by role so we only log in once per role.
    const groups = new Map();
    for (const r of ROUTES) {
        const key = r.role || 'public';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(r);
    }

    for (const [roleKey, routes] of groups) {
        const role = (roleKey === 'public' || roleKey === 'guest') ? null : roleKey;
        console.log(`── ${roleKey} (${routes.length} routes) ──`);

        const context = await browser.newContext({ ignoreHTTPSErrors: true });
        let auth = { ok: true };
        if (role) {
            auth = await loginAs(context, role);
            console.log(`auth: ${auth.ok ? 'ok' : 'FAILED - ' + auth.error}`);
        }

        for (const route of routes) {
            for (const [vpName, viewport] of Object.entries(VIEWPORTS)) {
                const r = { ...route, viewport: vpName };
                if (role && !auth.ok) {
                    results.push({ name: r.name, path: r.path, role: r.role || 'public', viewport: vpName, status: null, success: false, error: `Auth failed: ${auth.error}` });
                    console.log(`  [skip] ${r.path} (${vpName}) auth-failed`);
                    continue;
                }
                const res = await captureOne(context, r, viewport);
                results.push(res);
                const tag = res.success ? 'ok  ' : 'fail';
                const extra = res.error ? ' - ' + res.error.slice(0, 80) : '';
                console.log(`  [${tag}] ${r.path} (${vpName})${res.status ? ' ' + res.status : ''}${extra}`);
            }
        }

        await context.close();
    }

    await browser.close();

    fs.writeFileSync(path.join(OUT_DIR, 'report.md'),  renderReportMd(results));
    fs.writeFileSync(path.join(OUT_DIR, 'index.html'), renderIndexHtml(results));

    const ok   = results.filter(r => r.success).length;
    const fail = results.length - ok;
    console.log(`\nDone. ${ok} ok · ${fail} failed.`);
    console.log(`Report : ${path.join(OUT_DIR, 'report.md')}`);
    console.log(`Gallery: ${path.join(OUT_DIR, 'index.html')}`);
    process.exit(fail > 0 ? 1 : 0);
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
