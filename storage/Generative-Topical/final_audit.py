import json, os, glob

allp = sorted(glob.glob('output/papers/*/'))
demo, swept, not_done = [], [], []
for p in allp:
    name = os.path.basename(os.path.normpath(p))
    log = os.path.join(p, 'claude_log.txt')
    is_demo = any(x in name for x in ['s20', 's21', 's22', 's23', 's24', 's25'])
    has_rev = os.path.exists(log) and '=== RE-REVIEWED ===' in open(log, encoding='utf-8', errors='replace').read()
    if is_demo:
        demo.append(name)
    elif has_rev:
        swept.append(name)
    else:
        not_done.append(name)

print('manual demo (s20-s25):', len(demo))
print('opus-swept:', len(swept))
print('NOT done (non-demo, no RE-REVIEWED marker):', not_done)

tot = miss = qbad = 0
for p in allp:
    d = json.load(open(os.path.join(p, 'questions.json'), encoding='utf-8'))
    if len(d['questions']) != 40:
        qbad += 1
        print('QCOUNT', p, len(d['questions']))
    refs = set()
    for q in d['questions']:
        for a in (q.get('question_images_between_text') or []) + (q.get('question_images_after_text') or []) + (q.get('assets') or []):
            if a.get('image_path'):
                refs.add(a['image_path'])
        for o in q.get('options') or []:
            for a in o.get('images') or []:
                if a.get('image_path'):
                    refs.add(a['image_path'])
        t = q.get('option_table')
        if t and t.get('image_path'):
            refs.add(t['image_path'])
    for x in refs:
        tot += 1
        if not os.path.exists(os.path.join('output', x.replace('/', os.sep))):
            miss += 1
            print('MISSING', p, x)

print(f'TOTAL CORPUS: {len(allp)} papers | {tot} image refs | {miss} missing | {qbad} wrong-count')
print('ALL DONE:', len(demo) + len(swept) == len(allp) and miss == 0 and qbad == 0)
