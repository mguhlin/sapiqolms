# Courses

Drop each course in its own subfolder here (one folder per course, containing a
`course.json` and an `index.html` reader shell plus a `media/` folder). Sapiqo
auto-discovers any subfolder that contains a `course.json`.

Build a course from a single Markdown file with the creator:

    python3 ../sapiqo/creator/build_course.py my-course.md

`assets/` holds the shared course reader (CSS/JS/fonts) — leave it in place.
