#!/usr/bin/env python3
"""Sapiqo Course Creator — turn one Markdown file into a full course folder.

Author a course as a single `.md` file (see template.md), then run:

    python3 creator/build_course.py path/to/my-course.md [--transcribe]

It generates `courses/<slug>/` (course.json, reader index.html, mirrored images,
local/Vimeo video embeds, badge) which the LMS auto-discovers. With --transcribe
it also generates English captions for local videos using OpenAI Whisper.

Self-contained: no third-party Python packages (dependency-free, like the rest
of the project). Markdown is converted by a small built-in renderer covering what
course content needs: headings, paragraphs, lists, blockquotes, rules, bold/
italic/code, links, and images.
"""

import argparse
import hashlib
import html as htmllib
import json
import os
import re
import shutil
import subprocess
import sys
import urllib.request

# --- Markdown -> HTML (minimal, dependency-free) -----------------------------

def safe_ref(value: str) -> str:
    value = value.strip()
    if re.search(r'[\x00-\x20\x7f]', value):
        return ''
    if re.match(r'^[a-zA-Z][a-zA-Z0-9+.\-]*:', value) and not re.match(r'^https?://', value, re.I):
        return ''
    return value


def _inline(text: str) -> str:
    text = htmllib.escape(text, quote=True)
    def reference(match, image=False):
        value = safe_ref(htmllib.unescape(match[2]))
        if not value:
            return match[1]
        value = htmllib.escape(value, quote=True)
        return (f'<img src="{value}" alt="{match[1]}">' if image
                else f'<a href="{value}">{match[1]}</a>')
    text = re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', lambda m: reference(m, True), text)
    text = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', reference, text)
    text = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', text)
    text = re.sub(r'(?<!\*)\*([^*]+)\*(?!\*)', r'<em>\1</em>', text)
    text = re.sub(r'`([^`]+)`', r'<code>\1</code>', text)
    return text


def md_to_html(md: str) -> str:
    lines = md.split('\n')
    out, i, n = [], 0, len(md.split('\n'))
    para: list[str] = []

    def flush_para():
        if para:
            out.append('<p>' + _inline(' '.join(para).strip()) + '</p>')
            para.clear()

    while i < n:
        line = lines[i]
        s = line.strip()
        if s == '':
            flush_para(); i += 1; continue
        if re.match(r'^(---|\*\*\*|___)$', s):
            flush_para(); out.append('<hr>'); i += 1; continue
        m = re.match(r'^(#{3,6})\s+(.*)$', s)   # ### and deeper are in-lesson headings
        if m:
            flush_para()
            lvl = len(m.group(1))
            out.append(f'<h{lvl}>' + _inline(m.group(2).strip()) + f'</h{lvl}>')
            i += 1; continue
        if re.match(r'^>\s?', s):
            flush_para(); buf = []
            while i < n and re.match(r'^>\s?', lines[i].strip()):
                buf.append(re.sub(r'^>\s?', '', lines[i].strip())); i += 1
            out.append('<blockquote>' + md_to_html('\n'.join(buf)) + '</blockquote>'); continue
        if re.match(r'^[-*]\s+', s):
            flush_para(); items = []
            while i < n and re.match(r'^[-*]\s+', lines[i].strip()):
                items.append(re.sub(r'^[-*]\s+', '', lines[i].strip())); i += 1
            out.append('<ul>' + ''.join('<li>' + _inline(x) + '</li>' for x in items) + '</ul>'); continue
        if re.match(r'^\d+\.\s+', s):
            flush_para(); items = []
            while i < n and re.match(r'^\d+\.\s+', lines[i].strip()):
                items.append(re.sub(r'^\d+\.\s+', '', lines[i].strip())); i += 1
            out.append('<ol>' + ''.join('<li>' + _inline(x) + '</li>' for x in items) + '</ol>'); continue
        # standalone image line -> figure
        im = re.match(r'^!\[([^\]]*)\]\(([^)]+)\)$', s)
        if im:
            flush_para()
            out.append(f'<figure class="course-image"><img src="{htmllib.escape(safe_ref(im.group(2)), quote=True)}" alt="{htmllib.escape(im.group(1))}"></figure>')
            i += 1; continue
        para.append(s); i += 1
    flush_para()
    return '\n'.join(out)


# --- Helpers -----------------------------------------------------------------

def slugify(t: str) -> str:
    s = re.sub(r'[^\w\s-]', '', t.lower())
    s = re.sub(r'[\s_]+', '-', s)
    return re.sub(r'-+', '-', s).strip('-') or 'item'


VIMEO_RE = re.compile(r'vimeo\.com/(?:video/)?(\d+)')
YOUTUBE_RE = re.compile(r'(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})')


def video_spec(arg):
    """Detect a hosted video provider from a URL. Returns a spec dict or None."""
    ym = YOUTUBE_RE.search(arg)
    if ym:
        return {'provider': 'youtube', 'id': ym.group(1), 'url': 'https://youtu.be/' + ym.group(1)}
    vm = VIMEO_RE.search(arg)
    if vm:
        return {'provider': 'vimeo', 'id': vm.group(1), 'url': 'https://vimeo.com/' + vm.group(1)}
    return None


def embed_html(url):
    """General @embed: video providers, Google Docs/Slides/Drive, direct video, else a link."""
    u = safe_ref(url)
    if not u:
        return ''
    spec = video_spec(u)
    if spec:
        return responsive_video(spec)
    if re.search(r'https?://(?:docs|drive)\.google\.com/\S+', u):
        src = re.sub(r'/(edit|view|pub)(\?[^"]*)?$', '/embed', u)
        return ('<div class="video-embed"><iframe src="' + htmllib.escape(src) +
                '" title="Embedded document" loading="lazy" frameborder="0" allowfullscreen></iframe></div>')
    if re.match(r'^https?://.*\.(mp4|webm|m4v)(\?\S*)?$', u, re.I):
        return ('<div class="video-embed"><video controls preload="metadata" playsinline>'
                '<source src="' + htmllib.escape(u) + '" /></video></div>')
    return '<p><a href="' + htmllib.escape(u) + '" target="_blank" rel="noopener">' + htmllib.escape(u) + '</a></p>'


def module_meta(title: str):
    low = title.lower()
    m = re.search(r'(?:module\s+)?(\d+)', title)
    if 'welcome' in low or low.startswith('start'):
        return '0', 'Start Here'
    if 'badge' in low or 'certificate' in low:
        return 'badge', 'Finish'
    if re.match(r'^\s*(module\s+)?\d+\b', low) and m:
        return m.group(1), 'Module ' + m.group(1)
    return slugify(title), title


def parse_quiz(lines):
    """Parse @quiz ... @endquiz: pass/shuffle/pick/attempts, Q/TF/SA question types,
    options, '= answer' (short) / '= True|False' (TF), and per-question feedback."""
    pass_pct, shuffle, pick, attempts = 70, False, 0, 0
    questions = []
    cur = None

    def flush():
        nonlocal cur
        if cur is None:
            return
        if cur.get('type') != 'short':
            nc = sum(1 for o in cur['options'] if o['correct'])
            cur['type'] = 'multiple' if nc > 1 else (cur.get('type') if cur.get('type') == 'multiple' else 'single')
        questions.append(cur)
        cur = None

    for raw in lines:
        t = raw.strip()
        if t == '':
            continue
        m = re.match(r'^pass:\s*(\d+)', t, re.I)
        if m:
            pass_pct = max(1, min(100, int(m.group(1)))); continue
        if re.match(r'^shuffle:\s*(true|yes|1)', t, re.I):
            shuffle = True; continue
        m = re.match(r'^pick:\s*(\d+)', t, re.I)
        if m:
            pick = max(0, int(m.group(1))); continue
        m = re.match(r'^attempts:\s*(\d+)', t, re.I)
        if m:
            attempts = max(0, int(m.group(1))); continue
        m = re.match(r'^feedback:\s*(.*)$', t, re.I)
        if m:
            if cur:
                cur['feedback'] = m.group(1).strip()
            continue
        qm = re.match(r'^Q[:.]\s*(.*)$', t, re.I)
        if qm:
            flush()
            text = qm.group(1)
            multiple = bool(re.search(r'\(multiple\)\s*$', text, re.I))
            text = re.sub(r'\(multiple\)\s*$', '', text, flags=re.I).strip()
            cur = {'text': text, 'type': 'multiple' if multiple else 'single', 'options': [], 'feedback': ''}
            continue
        tf = re.match(r'^TF[:.]\s*(.*)$', t, re.I)
        if tf:
            flush()
            cur = {'text': tf.group(1).strip(), 'type': 'single', 'feedback': '',
                   'options': [{'text': 'True', 'correct': False}, {'text': 'False', 'correct': False}]}
            continue
        sa = re.match(r'^SA[:.]\s*(.*)$', t, re.I)
        if sa:
            flush()
            cur = {'text': sa.group(1).strip(), 'type': 'short', 'answers': [], 'feedback': ''}
            continue
        eq = re.match(r'^=\s*(.*)$', t)
        if eq and cur:
            val = eq.group(1).strip()
            if cur.get('type') == 'short':
                if val:
                    cur['answers'].append(val)
            else:
                for o in cur['options']:
                    if o['text'].lower() == val.lower():
                        o['correct'] = True
            continue
        om = re.match(r'^[-*+]\s+(.*)$', t)
        if om and cur and cur.get('type') != 'short':
            opt = om.group(1).strip()
            correct = opt.startswith('*')
            if correct:
                opt = opt[1:].strip()
            if opt:
                cur['options'].append({'text': opt, 'correct': correct})
    flush()

    def valid(q):
        if not q['text']:
            return False
        if q.get('type') == 'short':
            return bool(q.get('answers'))
        return any(o['correct'] for o in q.get('options', []))

    questions = [q for q in questions if valid(q)]
    for i, q in enumerate(questions):
        q['qid'] = 'q%d' % i
    out = {'pass': pass_pct, 'questions': questions}
    if shuffle:
        out['shuffle'] = True
    if pick > 0:
        out['pick'] = pick
    if attempts > 0:
        out['attempts'] = attempts
    return out


def responsive_video(vsrc: dict) -> str:
    if vsrc['provider'] == 'vimeo':
        return ('<div class="video-embed"><iframe src="https://player.vimeo.com/video/'
                + vsrc['id'] + '?dnt=1" title="Course video" loading="lazy" frameborder="0" '
                'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen '
                'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>')
    if vsrc['provider'] == 'youtube':
        return ('<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/'
                + vsrc['id'] + '" title="Course video" loading="lazy" frameborder="0" '
                'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>')
    track = (f'<track kind="captions" srclang="en" label="English" src="{vsrc["captions"]}" default />'
             if vsrc.get('captions') else '')
    return ('<div class="video-embed"><video controls preload="metadata" playsinline title="Course video">'
            f'<source src="{vsrc["file"]}" type="video/mp4" />{track}'
            'Your browser cannot play this video.</video></div>')


# --- Front matter + structure parsing ----------------------------------------

def parse_front_matter(text: str):
    meta, body = {}, text
    m = re.match(r'^---\s*\n(.*?)\n---\s*\n?(.*)$', text, re.DOTALL)
    if m:
        for line in m.group(1).split('\n'):
            if ':' in line:
                k, v = line.split(':', 1)
                meta[k.strip().lower()] = v.strip()
        body = m.group(2)
    return meta, body


def build(md_path: str, transcribe: bool):
    src_dir = os.path.dirname(os.path.abspath(md_path))
    creator_dir = os.path.dirname(os.path.abspath(__file__))          # courses/sapiqo/creator
    # Courses are written to the sibling courses/content/ (override w/ SAPIQO_COURSES).
    courses_root = os.environ.get('SAPIQO_COURSES') or os.path.join(
        os.path.dirname(os.path.dirname(creator_dir)), 'content')     # courses/content

    text = open(md_path, encoding='utf-8').read()
    meta, body = parse_front_matter(text)
    title = meta.get('title') or 'Untitled Course'
    slug = meta.get('slug') or slugify(title)
    out_dir = os.path.join(courses_root, slug)
    media_dir = os.path.join(out_dir, 'media')
    video_dir = os.path.join(media_dir, 'videos')
    os.makedirs(video_dir, exist_ok=True)

    # Split into modules (# ), lessons (## ), topics (### handled inside body).
    modules, cur_mod, cur_lesson = [], None, None
    body_buf: list[str] = []
    cur_videos: list[dict] = []
    cur_video_html: list[str] = []
    resources: list[str] = []
    img_urls: dict[str, str] = {}

    def mirror_image(src: str) -> str:
        # http(s) -> download into media/ ; local path -> copy into media/
        if src in img_urls:
            return img_urls[src]
        if re.match(r'^https?://', src):
            base = re.sub(r'[?#].*$', '', src).rsplit('/', 1)[-1] or 'image'
            name = hashlib.sha1(src.encode()).hexdigest()[:8] + '-' + re.sub(r'[^A-Za-z0-9._-]', '_', base)
            dest = os.path.join(media_dir, name)
            try:
                req = urllib.request.Request(src, headers={'User-Agent': 'Mozilla/5.0 Sapiqo'})
                with urllib.request.urlopen(req, timeout=30) as r:
                    open(dest, 'wb').write(r.read())
            except Exception as e:  # noqa: BLE001
                print(f'  ! image download failed {src}: {e}'); img_urls[src] = src; return src
        else:
            p = src if os.path.isabs(src) else os.path.join(src_dir, src)
            if not os.path.isfile(p):
                print(f'  ! image not found: {src}'); img_urls[src] = src; return src
            name = os.path.basename(p)
            shutil.copy(p, os.path.join(media_dir, name))
        rel = 'media/' + name
        img_urls[src] = rel
        return rel

    def add_local_video(ref: str) -> dict:
        p = ref if os.path.isabs(ref) else os.path.join(src_dir, ref)
        vid = {'provider': 'local'}
        if not os.path.isfile(p):
            print(f'  ! video not found: {ref} (kept as reference)')
            vid['file'] = 'media/videos/' + os.path.basename(ref)
            return vid
        name = os.path.basename(p)
        shutil.copy(p, os.path.join(video_dir, name))
        vid['file'] = 'media/videos/' + name
        return vid

    def flush_body():
        """Convert accumulated markdown + directives into HTML for the current step."""
        nonlocal body_buf, cur_videos, cur_video_html, resources
        text_block = '\n'.join(body_buf)
        # rewrite inline markdown image srcs through the mirror
        def _imgsub(m):
            return f'![{m.group(1)}]({mirror_image(m.group(2))})'
        text_block = re.sub(r'!\[([^\]]*)\]\(([^)]+)\)', _imgsub, text_block)
        html = md_to_html(text_block)
        # swap protected video placeholders back to their embed HTML
        for i, vh in enumerate(cur_video_html):
            html = html.replace(f'<p>@@VIDEO{i}@@</p>', vh).replace(f'@@VIDEO{i}@@', vh)
        if resources:
            html += '\n<h3>Resources</h3><ul class="resources">' + ''.join(resources) + '</ul>'
        videos_now = cur_videos
        body_buf, cur_videos, cur_video_html, resources = [], [], [], []
        return html, videos_now

    def close_lesson():
        nonlocal cur_lesson
        if cur_lesson is not None:
            content, vids = flush_body()
            cur_lesson['content'] = content
            cur_lesson['videos'] = vids
            cur_mod['lessons'].append(cur_lesson)
            cur_lesson = None

    lines = body.split('\n')
    idx = 0
    in_quiz = False; quiz_lines = []
    while idx < len(lines):
        line = lines[idx]; s = line.strip()
        if in_quiz:
            if re.match(r'^@endquiz\b', s, re.I):
                if cur_lesson is not None:
                    cur_lesson['quiz'] = parse_quiz(quiz_lines)
                in_quiz = False; quiz_lines = []
            else:
                quiz_lines.append(line)
            idx += 1; continue
        if re.match(r'^@quiz\b', s, re.I):
            in_quiz = True; quiz_lines = []; idx += 1; continue
        h1 = re.match(r'^#\s+(.*)$', s)
        h2 = re.match(r'^##\s+(.*)$', s)
        # directive lines
        d = re.match(r'^@(video|resource|image|embed)\s+(.*)$', s)
        if h1:
            close_lesson()
            key, label = module_meta(h1.group(1).strip())
            cur_mod = {'key': key, 'label': label, 'title': h1.group(1).strip(), 'lessons': []}
            modules.append(cur_mod); idx += 1; continue
        if h2:
            close_lesson()
            if cur_mod is None:
                key, label = '0', 'Start Here'
                cur_mod = {'key': key, 'label': label, 'title': 'Welcome', 'lessons': []}
                modules.append(cur_mod)
            cur_lesson = {'title': h2.group(1).strip(), 'topics': []}
            idx += 1; continue
        if d:
            kind, arg = d.group(1), d.group(2).strip()
            if kind == 'video':
                spec = video_spec(arg)
                if arg.startswith('local:'):
                    vsrc = add_local_video(arg[len('local:'):].strip())
                elif spec:
                    vsrc = spec
                else:
                    vsrc = add_local_video(arg)
                cur_videos.append(vsrc)
                body_buf.append(f'\n@@VIDEO{len(cur_video_html)}@@\n')  # protected placeholder
                cur_video_html.append(responsive_video(vsrc))
            elif kind == 'embed':
                body_buf.append(f'\n@@VIDEO{len(cur_video_html)}@@\n')
                cur_video_html.append(embed_html(arg))
            elif kind == 'resource':
                lm = re.match(r'\[([^\]]+)\]\(([^)]+)\)\s*(.*)$', arg)
                if lm:
                    extra = ' — ' + htmllib.escape(lm.group(3)) if lm.group(3) else ''
                    resources.append(f'<li><a href="{htmllib.escape(safe_ref(lm.group(2)), quote=True)}"><strong>{htmllib.escape(lm.group(1))}</strong></a>{extra}</li>')
            elif kind == 'image':
                im = re.match(r'(\S+)(?:\s+"([^"]*)")?', arg)
                if im:
                    body_buf.append(f'![{im.group(2) or ""}]({im.group(1)})')
            idx += 1; continue
        # raw HTML passthrough for video placeholders already inserted; other lines are markdown
        body_buf.append(line); idx += 1
    close_lesson()

    # Assemble course.json
    total_videos = 0
    total_quizzes = 0
    out_modules = []
    for mi, mod in enumerate(modules):
        lessons = []
        for li, les in enumerate(mod['lessons']):
            total_videos += len(les.get('videos', []))
            lesson = {
                'id': f'lesson-{mi}-{li}',
                'title': les['title'],
                'slug': slugify(les['title']),
                'content': les.get('content', ''),
                'videos': les.get('videos', []),
                'topics': les.get('topics', []),
            }
            quiz = les.get('quiz')
            if quiz and quiz.get('questions'):
                quiz['id'] = f'quiz-{mi}-{li}'
                lesson['quiz'] = quiz
                total_quizzes += 1
            lessons.append(lesson)
        out_modules.append({
            'id': f'module-{mod["key"]}', 'key': mod['key'], 'label': mod['label'],
            'title': mod['title'], 'summary': '', 'lessons': lessons,
        })

    lesson_count = sum(len(m['lessons']) for m in out_modules)
    course = {
        'slug': slug, 'title': title, 'provider': os.environ.get('SAPIQO_ORG_NAME', ''),
        'tagline': meta.get('tagline', ''),
        'tags': [t.strip() for t in meta.get('tags', '').split(',') if t.strip()],
        'overview_html': md_to_html(meta.get('about', '')) if meta.get('about') else '',
        'stats': {
            'modules': len(out_modules),
            'core_modules': sum(1 for m in out_modules if m['label'].startswith('Module')),
            'lessons': lesson_count, 'topics': 0, 'videos': total_videos,
            'quizzes': total_quizzes,
        },
        'modules': out_modules,
    }

    # Reader shell + badge
    _write_shell(out_dir, title, meta.get('tagline', ''))
    _copy_badge(meta.get('badge', ''), src_dir, out_dir)

    if transcribe:
        _transcribe_local(out_dir, course)

    json.dump(course, open(os.path.join(out_dir, 'course.json'), 'w', encoding='utf-8'),
              ensure_ascii=False, indent=2)
    # keep the source for re-builds
    shutil.copy(md_path, os.path.join(out_dir, 'source.md'))

    print(f'Wrote {out_dir}/course.json')
    print(f'  modules: {len(out_modules)}  lessons: {lesson_count}  videos: {total_videos}')
    for m in out_modules:
        print(f'    [{m["label"]}] {m["title"]} — {len(m["lessons"])} lessons')
    print('Drop-in ready — it will appear in the LMS on the next course scan.')


def _write_shell(out_dir: str, title: str, desc: str):
    # Brand for the static shell; overridable via env. When served inside the LMS
    # the reader re-brands live from settings, so this only affects standalone use.
    brand = os.environ.get('SAPIQO_CATALOG_NAME', 'Courses')
    mark = htmllib.escape(brand[:2])
    brand = htmllib.escape(brand)
    html = f'''<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{htmllib.escape(title)} · {brand}</title>
  <meta name="description" content="{htmllib.escape(desc)}" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/theme.css" />
  <link rel="stylesheet" href="../assets/css/reader.css" />
</head>
<body>
  <a class="skip-link" href="#reader">Skip to lesson content</a>
  <header class="topbar">
    <a class="brand" href="../index.html"><span class="brand__mark">{mark}</span><span>{brand}</span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle course navigation">Contents</button>
  </header>
  <div class="app" id="app" data-nav-open="false">
    <aside class="sidebar" aria-label="Course navigation">
      <div class="sidebar__head">
        <a class="brand" href="../index.html"><span class="brand__mark">{mark}</span><span>{brand}</span></a>
        <h1 class="sidebar__course-title" id="courseTitle">Loading…</h1>
        <div class="sidebar__progress-meta"><span id="sideProgressCount">0 / 0</span><strong id="sideProgressPct">0%</strong></div>
        <div class="progress progress--on-navy"><div class="progress__bar" id="sideProgressBar"></div></div>
      </div>
      <nav class="sidebar__nav" id="nav" aria-label="Modules and lessons"></nav>
    </aside>
    <main class="reader" id="reader" tabindex="-1"></main>
    <div class="backdrop" id="backdrop"></div>
  </div>
  <script src="../assets/js/reader.js"></script>
</body>
</html>
'''
    open(os.path.join(out_dir, 'index.html'), 'w', encoding='utf-8').write(html)


def _copy_badge(badge: str, src_dir: str, out_dir: str):
    if not badge:
        return
    p = badge if os.path.isabs(badge) else os.path.join(src_dir, badge)
    if os.path.isfile(p):
        shutil.copy(p, os.path.join(out_dir, 'badge.png'))
        print('  badge.png set from ' + badge)
    else:
        print(f'  ! badge not found: {badge} (course will use the generic medallion)')


def _transcribe_local(out_dir: str, course: dict):
    whisper = os.environ.get('WHISPER_BIN', 'whisper')
    if not os.path.isfile(whisper):
        print('  ! --transcribe: whisper not found, skipping captions'); return
    vdir = os.path.join(out_dir, 'media', 'videos')
    for f in sorted(os.listdir(vdir)):
        if not f.endswith('.mp4'):
            continue
        base = f[:-4]
        if os.path.isfile(os.path.join(vdir, base + '.vtt')):
            continue
        print('  transcribing ' + f)
        subprocess.run([whisper, os.path.join(vdir, f), '--model', os.environ.get('WHISPER_MODEL', 'turbo'),
                        '--language', 'English', '--output_format', 'all', '--output_dir', vdir,
                        '--word_timestamps', 'True', '--max_line_width', '42', '--max_line_count', '2',
                        '--verbose', 'False'], check=False)
    for ext in ('txt', 'tsv', 'json'):
        for f in os.listdir(vdir):
            if f.endswith('.' + ext):
                os.remove(os.path.join(vdir, f))
    # attach caption tracks to local videos: record on the video + inject a
    # <track> into the already-rendered embed (right after its <source>).
    for m in course['modules']:
        for l in m['lessons']:
            for v in l['videos']:
                if v.get('provider') != 'local':
                    continue
                vtt = v['file'].rsplit('.', 1)[0] + '.vtt'
                if not os.path.isfile(os.path.join(out_dir, vtt)):
                    continue
                v['captions'] = vtt
                src = f'<source src="{v["file"]}" type="video/mp4" />'
                track = f'<track kind="captions" srclang="en" label="English" src="{vtt}" default />'
                if src in l['content'] and track not in l['content']:
                    l['content'] = l['content'].replace(src, src + track, 1)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('markdown')
    ap.add_argument('--transcribe', action='store_true', help='generate captions for local videos (needs whisper)')
    args = ap.parse_args()
    if not os.path.isfile(args.markdown):
        sys.exit('Markdown file not found: ' + args.markdown)
    build(args.markdown, args.transcribe)


if __name__ == '__main__':
    main()
