// @ts-check
/**
 * capture-module-flows.mjs — flow-wise screenshot capture for the V2 module docs.
 *
 * Reads every docs/modules/NN-*.md, extracts the ```flow code block(s) embedded in
 * each, and DRIVES the real app with Playwright — logging in as the right role,
 * clicking through the flow step by step, and screenshotting each labelled screen.
 * Produces screenshots/flows/<module>/NN-step.png plus an index.html gallery and a
 * report.md. You update the flow blocks in the docs; this script captures them.
 *
 * Run:  npm run capture:flows
 *       node scripts/capture-module-flows.mjs --module=07 --headed
 *
 * Requires the dev server up (php8.4 artisan serve) and the V2 demo data seeded.
 * See scripts/FLOWS.md for the flow-block format and the full action reference.
 */

import { chromium } from 'playwright';
import { readdir, readFile, mkdir, rm, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const DOCS_DIR = path.join(ROOT, 'docs', 'modules');
const OUT_DIR = path.join(ROOT, 'screenshots', 'flows');

// ---- config ---------------------------------------------------------------

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const m = a.match(/^--([^=]+)(?:=(.*))?$/);
        return m ? [m[1], m[2] ?? true] : [a, true];
    }),
);

const BASE_URL = (args.base || process.env.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const ONLY_MODULE = args.module ? String(args.module) : null; // e.g. "07"
const HEADED = !!args.headed;
const SETTLE_MS = 450; // let Alpine/transitions paint before a shot

// Per-role login: GET login URL + demo creds (override via env).
// Confirmed consistent E2E trio: teacher bilal owns class "AS-A Physics";
// student 2026-001 (Ali Hassan) is enrolled in that class.
const ROLE_AUTH = {
    super_admin:  { login: '/v2/super-admin/login', email: env('SUPER_ADMIN_EMAIL', 'super@topicaled.com'),  password: env('SUPER_ADMIN_PASSWORD', 'password') },
    school_admin: { login: '/v2/school/login',      email: env('SCHOOL_ADMIN_EMAIL', 'admin@thesage.edu.pk'), password: env('SCHOOL_ADMIN_PASSWORD', 'password') },
    branch_admin: { login: '/v2/branch/login',      email: env('BRANCH_ADMIN_EMAIL', 'gulberg@thesage.edu.pk'), password: env('BRANCH_ADMIN_PASSWORD', 'password') },
    teacher:      { login: '/v2/teacher/login',     email: env('TEACHER_EMAIL', 'bilal@thesage.edu.pk'),     password: env('TEACHER_PASSWORD', 'password') },
    student:      { login: '/v2/student/login',     email: env('STUDENT_EMAIL', '2026-001@thesage.edu.pk'),  password: env('STUDENT_PASSWORD', 'password') },
};

const VIEWPORTS = {
    desktop: { width: 1440, height: 1200 },
    mobile: { width: 390, height: 844 },
};

function env(key, fallback) {
    return process.env[key] || fallback;
}

// ---- flow-block parsing ----------------------------------------------------

/** Pull every ```flow ... ``` block out of a markdown doc. */
function extractFlowBlocks(md) {
    const blocks = [];
    const re = /```flow\s*\n([\s\S]*?)```/g;
    let m;
    while ((m = re.exec(md)) !== null) blocks.push(m[1]);
    return blocks;
}

/** Forgiving JSON: strip // line-comments and trailing commas, then parse. */
function parseFlow(raw) {
    const cleaned = raw
        .replace(/(^|[^:])\/\/.*$/gm, '$1') // // comments (not inside http://)
        .replace(/,(\s*[}\]])/g, '$1'); // trailing commas
    return JSON.parse(cleaned);
}

/** First "# ..." heading → module title. */
function docTitle(md, fallback) {
    const m = md.match(/^#\s+(.+)$/m);
    return m ? m[1].trim() : fallback;
}

// ---- the runner ------------------------------------------------------------

const manifest = []; // { module, moduleTitle, flowName, steps:[{shot, caption, file, ok, error}] }

async function main() {
    if (!existsSync(DOCS_DIR)) {
        console.error(`No docs/modules dir at ${DOCS_DIR}`);
        process.exit(1);
    }

    // discover module docs
    const files = (await readdir(DOCS_DIR))
        .filter((f) => /^\d{2}-.*\.md$/.test(f))
        .sort();

    const targets = [];
    for (const file of files) {
        const num = file.slice(0, 2);
        if (ONLY_MODULE && num !== ONLY_MODULE.padStart(2, '0')) continue;
        const md = await readFile(path.join(DOCS_DIR, file), 'utf8');
        const flows = extractFlowBlocks(md);
        if (!flows.length) continue;
        targets.push({ num, slug: file.replace(/\.md$/, ''), title: docTitle(md, file), flows });
    }

    if (!targets.length) {
        console.log(ONLY_MODULE
            ? `Module ${ONLY_MODULE} has no \`\`\`flow block yet. Add one (see scripts/FLOWS.md).`
            : 'No module docs contain a ```flow block yet. Add one (see scripts/FLOWS.md).');
        return;
    }

    await rm(OUT_DIR, { recursive: true, force: true });
    await mkdir(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: !HEADED });

    for (const t of targets) {
        for (let fi = 0; fi < t.flows.length; fi++) {
            let flow;
            try {
                flow = parseFlow(t.flows[fi]);
            } catch (e) {
                console.error(`  ✗ [${t.slug}] flow #${fi + 1} is not valid JSON: ${e.message}`);
                continue;
            }
            await runFlow(browser, t, fi, flow);
        }
    }

    await browser.close();
    await writeGallery();
    await writeReport();

    const shots = manifest.reduce((n, f) => n + f.steps.filter((s) => s.file).length, 0);
    const fails = manifest.reduce((n, f) => n + f.steps.filter((s) => s.error).length, 0);
    console.log(`\nDone — ${shots} screenshots across ${manifest.length} flow(s)${fails ? `, ${fails} step error(s)` : ''}.`);
    console.log(`Gallery: ${path.join(OUT_DIR, 'index.html')}`);
}

async function runFlow(browser, target, flowIndex, flow) {
    const flowName = flow.name || `Flow ${flowIndex + 1}`;
    const flowDir = path.join(OUT_DIR, target.slug, slugify(flowName));
    await mkdir(flowDir, { recursive: true });

    const record = { module: target.num, slug: target.slug, moduleTitle: target.title, flowName, steps: [] };
    manifest.push(record);
    console.log(`\n▶ [${target.slug}] ${flowName}`);

    const vars = { run: String(Date.now()).slice(-6) };
    let context = null;
    let page = null;
    let viewport = 'desktop';
    let shotSeq = 0;

    const newContext = async () => {
        if (context) await context.close();
        context = await browser.newContext({ viewport: VIEWPORTS[viewport], deviceScaleFactor: 1 });
        page = await context.newPage();
        page.on('dialog', (d) => d.accept().catch(() => {})); // accept the submit confirm()
    };
    await newContext();

    for (const step of flow.steps) {
        try {
            await runStep(step);
        } catch (e) {
            const label = describe(step);
            console.log(`  ✗ ${label} — ${e.message.split('\n')[0]}`);
            // capture the failure state so you can see what went wrong
            const file = await snap(`ERROR-${++shotSeq}-${slugify(label)}`);
            record.steps.push({ shot: `error:${label}`, caption: `ERROR: ${e.message.split('\n')[0]}`, file, error: e.message.split('\n')[0] });
        }
    }

    if (context) await context.close();

    // -- step executor --------------------------------------------------------
    async function runStep(step) {
        if (step.viewport && step.viewport !== viewport) {
            viewport = step.viewport;
            await page.setViewportSize(VIEWPORTS[viewport]);
        }
        if (step.as) await login(step.as);
        if (step.goto) await go(tpl(step.goto));
        if (step.fill) await fill(step.fill);
        if (step.select) await select(step.select);
        if (step.check) await clickEl(step.check, true);
        if (step.msselect) await msselect(step.msselect);
        if (step.answerAll) await answerAll(step.answerAll);
        if (step.capture) await capture(step);
        if (step.clickSelector) { await page.locator(tpl(step.clickSelector)).first().click(); await settle(); }
        if (step.click) { await clickByText(tpl(step.click)); await settle(); }
        if (step.waitFor !== undefined) await waitFor(step.waitFor);
        if (step.shot) {
            const file = await snap(`${String(++shotSeq).padStart(2, '0')}-${slugify(step.shot)}`);
            record.steps.push({ shot: step.shot, caption: step.caption || step.shot, file, ok: true });
            console.log(`  ✓ ${step.shot}`);
        }
    }

    async function login(role) {
        const auth = ROLE_AUTH[role];
        if (!auth) throw new Error(`Unknown role "${role}" (valid: ${Object.keys(ROLE_AUTH).join(', ')})`);
        await newContext(); // a fresh, isolated session per actor switch
        await page.goto(BASE_URL + auth.login, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="email"]', auth.email);
        await page.fill('input[name="password"]', auth.password);
        await page.click('button[type="submit"]');
        await page.waitForLoadState('networkidle').catch(() => {});
        if (new URL(page.url()).pathname.endsWith('/login')) {
            const err = await page.locator('p, .alert, [role=alert]').filter({ hasText: /incorrect|invalid|failed|credentials|match/i }).first().textContent().catch(() => null);
            throw new Error(`login as ${role} failed${err ? `: ${err.trim()}` : ''}`);
        }
        await settle();
    }

    async function go(target) {
        const url = target.startsWith('http') ? target : BASE_URL + (target.startsWith('/') ? target : '/' + target);
        await page.goto(url, { waitUntil: 'networkidle' }).catch(() => page.goto(url, { waitUntil: 'domcontentloaded' }));
        await settle();
    }

    async function fill(spec) {
        const loc = resolveInput(spec);
        await loc.fill(String(tpl(spec.value ?? '')));
    }

    async function select(spec) {
        const loc = resolveInput(spec);
        if (spec.label != null) await loc.selectOption({ label: tpl(spec.label) });
        else if (spec.index != null) await loc.selectOption({ index: Number(spec.index) });
        else await loc.selectOption(tpl(spec.value));
        await settle();
    }

    /** Custom Alpine multi-select (.ms-control + search + .ms-opt). */
    async function msselect(spec) {
        await page.locator('.ms-control').first().click();
        const search = page.locator('[x-ref="msSearch"], .ms-drop input, .ms-control input').first();
        if (await search.count()) await search.fill(tpl(spec.text || ''));
        await page.locator('.ms-opt', { hasText: tpl(spec.text) }).first().click();
        await page.keyboard.press('Escape').catch(() => {});
        await settle();
    }

    /** Select an answer for every question on the take page. value: "first". */
    async function answerAll(which) {
        const names = await page.$$eval('input[type="radio"][name^="answers["]', (els) => [...new Set(els.map((e) => e.getAttribute('name')))]);
        for (const name of names) {
            const group = page.locator(`input[name="${name}"]`);
            const radio = which === 'last' ? group.last() : group.first();
            await radio.check({ force: true }).catch(async () => {
                // hidden radio → click its wrapping label instead
                await radio.locator('xpath=ancestor::label[1]').click({ force: true }).catch(() => {});
            });
        }
        await settle();
    }

    async function capture(step) {
        const re = new RegExp(step.fromUrl);
        const m = page.url().match(re);
        if (!m || !m[1]) throw new Error(`capture "${step.capture}" — pattern /${step.fromUrl}/ did not match ${page.url()}`);
        vars[step.capture] = m[1];
        console.log(`  · captured ${step.capture}=${m[1]}`);
    }

    async function clickByText(text) {
        // explicit selector?
        if (/^[.#\[]/.test(text) || text.includes('>>')) {
            await page.locator(text).first().click();
            return;
        }
        const byRole = page.getByRole('button', { name: text }).or(page.getByRole('link', { name: text }));
        if (await byRole.count()) { await byRole.first().click(); return; }
        await page.getByText(text, { exact: false }).first().click();
    }

    async function clickEl(spec, check = false) {
        const loc = resolveInput(spec);
        if (check) await loc.check({ force: true });
        else await loc.click();
        await settle();
    }

    function resolveInput(spec) {
        if (typeof spec === 'string') return page.locator(spec).first();
        if (spec.selector) return page.locator(tpl(spec.selector)).first();
        if (spec.name) return page.locator(`[name="${spec.name}"]`).first();
        throw new Error(`input spec needs "name" or "selector": ${JSON.stringify(spec)}`);
    }

    async function waitFor(w) {
        if (w === 'networkidle') return page.waitForLoadState('networkidle').catch(() => {});
        if (typeof w === 'number') return page.waitForTimeout(w);
        return page.waitForSelector(tpl(String(w)), { timeout: 15000 });
    }

    async function snap(name) {
        const file = path.join(flowDir, `${name}.png`);
        await page.screenshot({ path: file, fullPage: true }).catch(async () => {
            await page.screenshot({ path: file }); // fullPage can fail on huge pages
        });
        return path.relative(OUT_DIR, file).replace(/\\/g, '/');
    }

    async function settle() {
        await page.waitForTimeout(SETTLE_MS);
    }

    function tpl(v) {
        if (typeof v !== 'string') return v;
        return v.replace(/\{\{(\w+)\}\}/g, (_, k) => (vars[k] != null ? vars[k] : `{{${k}}}`));
    }
}

// ---- helpers ---------------------------------------------------------------

function describe(step) {
    for (const k of ['as', 'goto', 'click', 'clickSelector', 'fill', 'select', 'capture', 'answerAll', 'shot']) {
        if (step[k] !== undefined) return `${k}:${typeof step[k] === 'object' ? JSON.stringify(step[k]) : step[k]}`;
    }
    return 'step';
}

function slugify(s) {
    return String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'x';
}

async function writeReport() {
    let md = `# Module flow captures\n\nGenerated against ${BASE_URL}. ${manifest.length} flow(s).\n\n`;
    for (const f of manifest) {
        md += `## ${f.module} — ${f.moduleTitle}\n\n**Flow:** ${f.flowName}\n\n`;
        md += `| # | Screen | Caption | Status |\n|---|---|---|---|\n`;
        f.steps.forEach((s, i) => {
            md += `| ${i + 1} | ${s.shot} | ${s.caption} | ${s.error ? '❌ ' + s.error : '✅'} |\n`;
        });
        md += '\n';
    }
    await writeFile(path.join(OUT_DIR, 'report.md'), md, 'utf8');
}

async function writeGallery() {
    const css = `
        :root{--bg:#0b1f33;--card:#11293f;--line:#1f3a52;--ink:#eaf1f8;--soft:#9db3c6;--ok:#5fa052;--bad:#c64c44}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,Segoe UI,sans-serif}
        header{padding:22px 28px;border-bottom:1px solid var(--line)}h1{margin:0;font-size:20px}
        .sub{color:var(--soft);font-size:12.5px;margin-top:4px}
        .mod{padding:10px 28px;margin-top:18px;border-top:1px solid var(--line);font-size:16px;font-weight:600}
        .flow{color:var(--soft);font-size:13px;padding:0 28px 4px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;padding:12px 28px 28px}
        .shot{background:var(--card);border:1px solid var(--line);border-radius:10px;overflow:hidden}
        .shot.err{border-color:var(--bad)}
        .shot img{width:100%;display:block;border-bottom:1px solid var(--line);cursor:zoom-in}
        .cap{padding:9px 12px;font-size:12.5px}.cap .n{color:var(--soft);margin-right:6px}
        .err .cap{color:var(--bad)}
        a{color:inherit;text-decoration:none}`;
    let html = `<!doctype html><html><head><meta charset="utf-8"><title>Module flow captures</title><style>${css}</style></head><body>`;
    html += `<header><h1>Module flow captures</h1><div class="sub">${BASE_URL} · ${new Date().toISOString()}</div></header>`;
    for (const f of manifest) {
        html += `<div class="mod">${f.module} — ${esc(f.moduleTitle)}</div><div class="flow">▶ ${esc(f.flowName)}</div><div class="grid">`;
        f.steps.forEach((s, i) => {
            html += `<div class="shot ${s.error ? 'err' : ''}">`;
            if (s.file) html += `<a href="${s.file}" target="_blank"><img src="${s.file}" loading="lazy"></a>`;
            html += `<div class="cap"><span class="n">${i + 1}</span>${esc(s.caption)}</div></div>`;
        });
        html += `</div>`;
    }
    html += `</body></html>`;
    await writeFile(path.join(OUT_DIR, 'index.html'), html, 'utf8');
}

function esc(s) {
    return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
