// ── Safe formula evaluator ───────────────────────────────────────────────────
// Evaluates a config-supplied math expression against a { var: value } map so a
// single Calculator component can serve every "plug numbers into a formula"
// question purely from JSON. NO eval / new Function (CSP-safe): a hand-written
// tokenizer + recursive-descent parser.
//
// Supports:  + - * / % ^   parentheses   unary ±   numbers (incl. 1.5e-3)
//            variables (from `vars`)   constants (pi, e)
//            functions: sqrt abs exp ln log/log10 sin cos tan round min max
//
//   evalFormula('mass / mr', { mass: 8, mr: 40 })            → 0.2
//   evalFormula('power * time', { power: 60, time: 300 })    → 18000
//   evalFormula('sqrt(a^2 + b^2)', { a: 3, b: 4 })           → 5

const FUNCS = {
    sqrt: Math.sqrt, abs: Math.abs, exp: Math.exp,
    ln: Math.log, log: Math.log10, log10: Math.log10,
    sin: Math.sin, cos: Math.cos, tan: Math.tan, round: Math.round,
    min: Math.min, max: Math.max,
};
const CONSTS = { pi: Math.PI, e: Math.E };

function tokenize(s) {
    const out = [];
    let i = 0;
    const isDigit = (c) => c >= '0' && c <= '9';
    const isAlpha = (c) => /[A-Za-z_]/.test(c);
    while (i < s.length) {
        const c = s[i];
        if (c === ' ' || c === '\t' || c === '\n') { i++; continue; }
        if ('()+-*/^%,'.includes(c)) { out.push({ t: c }); i++; continue; }
        if (isDigit(c) || (c === '.' && isDigit(s[i + 1]))) {
            let j = i + 1;
            while (j < s.length && /[0-9.]/.test(s[j])) j++;
            if (s[j] === 'e' || s[j] === 'E') {
                j++;
                if (s[j] === '+' || s[j] === '-') j++;
                while (j < s.length && isDigit(s[j])) j++;
            }
            out.push({ t: 'num', v: parseFloat(s.slice(i, j)) });
            i = j; continue;
        }
        if (isAlpha(c)) {
            let j = i + 1;
            while (j < s.length && /[A-Za-z0-9_]/.test(s[j])) j++;
            out.push({ t: 'id', v: s.slice(i, j) });
            i = j; continue;
        }
        throw new Error('Unexpected character: ' + c);
    }
    return out;
}

export function evalFormula(src, vars = {}) {
    if (src == null || src === '') return NaN;
    const toks = tokenize(String(src));
    let p = 0;
    const peek = () => toks[p];
    const next = () => toks[p++];
    const expect = (ch) => { const t = next(); if (!t || t.t !== ch) throw new Error('expected ' + ch); };

    function parseExpr() {
        let v = parseTerm();
        while (peek() && (peek().t === '+' || peek().t === '-')) {
            const op = next().t;
            const r = parseTerm();
            v = op === '+' ? v + r : v - r;
        }
        return v;
    }
    function parseTerm() {
        let v = parseFactor();
        while (peek() && (peek().t === '*' || peek().t === '/' || peek().t === '%')) {
            const op = next().t;
            const r = parseFactor();
            v = op === '*' ? v * r : op === '/' ? v / r : v % r;
        }
        return v;
    }
    function parseFactor() {              // '^' is right-associative
        const b = parseBase();
        if (peek() && peek().t === '^') { next(); return Math.pow(b, parseFactor()); }
        return b;
    }
    function parseBase() {
        const t = peek();
        if (!t) throw new Error('unexpected end of expression');
        if (t.t === '-') { next(); return -parseBase(); }
        if (t.t === '+') { next(); return parseBase(); }
        if (t.t === '(') { next(); const v = parseExpr(); expect(')'); return v; }
        if (t.t === 'num') { next(); return t.v; }
        if (t.t === 'id') {
            next();
            if (peek() && peek().t === '(') {         // function call
                next();
                const args = [parseExpr()];
                while (peek() && peek().t === ',') { next(); args.push(parseExpr()); }
                expect(')');
                const fn = FUNCS[t.v];
                if (!fn) throw new Error('unknown function: ' + t.v);
                return fn(...args);
            }
            if (t.v in CONSTS) return CONSTS[t.v];
            if (t.v in vars) return Number(vars[t.v]);
            throw new Error('unknown variable: ' + t.v);
        }
        throw new Error('unexpected token: ' + t.t);
    }

    const result = parseExpr();
    if (p !== toks.length) throw new Error('trailing tokens in expression');
    return result;
}
