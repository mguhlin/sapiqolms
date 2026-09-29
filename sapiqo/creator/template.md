---
title: Your Course Title
slug: your-course-slug          # folder name + URL; lowercase-with-dashes
tagline: One sentence shown on the splash page and course home.
tags: Topic, Audience           # optional, comma-separated
badge: badge.png                # optional; put the image next to this .md file
about: |                        # optional longer intro shown on the course home
  A short paragraph about the course.
---

# Module 1: First Module Title
<!-- "# " starts a MODULE. Use "Welcome" for the intro module and a title with
     "Certificate"/"Badge" for the closing module — those get special labels. -->

## 1.1 First Lesson Title
<!-- "## " starts a LESSON inside the current module. -->

Write normal Markdown here: **bold**, *italic*, `code`, and
[links](https://example.org). Blank lines separate paragraphs.

- Bulleted lists
- work fine

1. So do
2. numbered lists

### A sub-heading inside the lesson
Use ### and #### for headings *within* a lesson.

> Blockquotes are supported too.

![Alt text for the image](https://example.org/picture.png)
<!-- Images (URL or a local path relative to this file) are copied into the course
     so it stays self-contained/offline. -->

@video https://vimeo.com/123456789
<!-- @video takes a Vimeo URL, a YouTube URL, OR a local file:  @video local:my-clip.mp4
     (the file, relative to this .md, is copied in; add --transcribe for captions). -->

@embed https://docs.google.com/presentation/d/EXAMPLE/edit
<!-- @embed handles Vimeo/YouTube, Google Slides/Docs/Drive, or a direct .mp4/.webm URL. -->

@resource [Resource title](https://example.org/guide) Optional note after the link
<!-- @resource lines are grouped into a "Resources" list at the end of the lesson. -->

@quiz
<!-- A knowledge check. Learners must pass it (default 70%) for it to count
     toward course completion / the badge. Grading is done server-side in the LMS.
     Options: pass:N, shuffle:true, pick:N (ask a random subset), attempts:N. -->
pass: 70
shuffle: true
Q: What does "# " start?
- a lesson
- *a module
- a topic
feedback: "# " begins a module; "## " begins a lesson.
Q: Which of these are directives? (multiple)
- *@video
- *@resource
- @heading
TF: "## " starts a lesson.
= true
SA: Which directive embeds a YouTube video?
= @video
= video
@endquiz

## 1.2 Second Lesson Title
More content…

# Module 2: Second Module Title

## 2.1 A Lesson
…

# Get Your Certificate
## Finish and claim your badge
When you complete every step, your certificate and badge appear on your dashboard.
