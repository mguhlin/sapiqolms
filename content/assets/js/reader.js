/* ===========================================================================
   Sapiqo Course Reader
   Loads course.json, renders the sidebar + lesson view, tracks progress in
   localStorage. Progress schema is intentionally simple so a future Sapiqo
   layer can read/write the same keys per authenticated user.
   =========================================================================== */
(function () {
  "use strict";

  const els = {
    app: document.getElementById("app"),
    courseTitle: document.getElementById("courseTitle"),
    nav: document.getElementById("nav"),
    reader: document.getElementById("reader"),
    progressBar: document.getElementById("sideProgressBar"),
    progressPct: document.getElementById("sideProgressPct"),
    progressCount: document.getElementById("sideProgressCount"),
    navToggle: document.getElementById("navToggle"),
    backdrop: document.getElementById("backdrop"),
  };

  let COURSE = null;
  let FLAT = []; // flat ordered list of steps: {type, moduleKey, lesson, topic?, id, title}
  let PROGRESS_KEY = "sapiqo-course-progress";

  // --- Progress store (localStorage; Sapiqo-compatible shape) ---------------
  const Progress = {
    load() {
      try {
        return JSON.parse(localStorage.getItem(PROGRESS_KEY)) || {};
      } catch (_) {
        return {};
      }
    },
    _cache: null,
    get data() {
      if (!this._cache) this._cache = this.load();
      return this._cache;
    },
    isDone(id) {
      return !!this.data[id];
    },
    set(id, done) {
      if (done) this.data[id] = { completed_at: new Date().toISOString() };
      else delete this.data[id];
      localStorage.setItem(PROGRESS_KEY, JSON.stringify(this.data));
      LMS.sync(id, done);
    },
    // Update the local store only (used when the server already recorded the
    // step, e.g. an authoritative quiz pass — avoids a duplicate POST).
    setLocal(id, done) {
      if (done) this.data[id] = { completed_at: new Date().toISOString() };
      else delete this.data[id];
      localStorage.setItem(PROGRESS_KEY, JSON.stringify(this.data));
    },
  };

  // --- Optional Sapiqo sync -------------------------------------------------
  // When this course is served inside Sapiqo and the learner is signed in,
  // completion is mirrored to the server so it counts toward their badge and is
  // visible on any device. When served standalone, everything falls back to the
  // localStorage store above with no network calls.
  const LMS = {
    enabled: false,
    csrf: null,
    base: null,
    slug: null,
    async init(slug) {
      this.slug = slug;
      try {
        this.base = new URL("../../api/", location.href).href;
        const r = await fetch(this.base + "whoami", { credentials: "same-origin" });
        if (!r.ok) return false;
        const me = await r.json();
        if (!me.authenticated) return false;
        this.enabled = true;
        this.csrf = me.csrf;
        // Hydrate completed steps from the server (source of truth on load).
        const pr = await fetch(this.base + "progress?course=" + encodeURIComponent(slug), { credentials: "same-origin" });
        if (pr.ok) {
          const data = await pr.json();
          Progress._cache = {}; // the authenticated server is authoritative, including revoked progress
          (data.steps || []).forEach((sid) => {
            if (!Progress.data[sid]) Progress.data[sid] = { completed_at: null };
          });
          localStorage.setItem(PROGRESS_KEY, JSON.stringify(Progress.data));
        }
        return true;
      } catch (_) {
        return false;
      }
    },
    sync(stepId, done) {
      if (!this.enabled) return;
      fetch(this.base + "progress", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": this.csrf || "" },
        body: JSON.stringify({ course: this.slug, step_id: stepId, done: done }),
      })
        .then((r) => (r.ok ? r.json() : null))
        .then((res) => {
          if (res && res.badge && res.badge_url) LMS.celebrate(res.badge_url);
        })
        .catch(() => {});
    },
    // Remember the current lesson server-side so the dashboard can offer "resume".
    seen(stepId) {
      if (!this.enabled) return;
      fetch(this.base + "seen", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": this.csrf || "" },
        body: JSON.stringify({ course: this.slug, step_id: stepId }),
        keepalive: true,
      }).catch(() => {});
    },
    // Submit quiz answers for authoritative server-side grading.
    postQuiz(quizId, answers, asked) {
      if (!this.enabled) return Promise.resolve(null);
      return fetch(this.base + "quiz", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": this.csrf || "" },
        body: JSON.stringify({ course: this.slug, quiz_id: quizId, answers: answers, asked: asked || [] }),
      })
        .then((r) => (r.ok ? r.json() : null))
        .catch(() => null);
    },
    celebrate(badgeUrl) {
      if (document.getElementById("lms-badge-toast")) return;
      const toast = el("div", { id: "lms-badge-toast", class: "lms-toast", role: "status", "aria-live": "polite" }, [
        el("strong", {}, ["🎉 Course complete!"]),
        el("span", {}, ["You've earned your badge."]),
        el("a", { class: "btn btn-gold", href: badgeUrl }, ["Download badge"]),
      ]);
      document.body.appendChild(toast);
      setTimeout(() => toast.classList.add("show"), 30);
    },
  };

  // --- Utilities -------------------------------------------------------------
  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      for (const k in attrs) {
        if (k === "class") node.className = attrs[k];
        else if (k === "html") node.innerHTML = attrs[k];
        else if (k.startsWith("on") && typeof attrs[k] === "function")
          node.addEventListener(k.slice(2), attrs[k]);
        else if (attrs[k] !== null && attrs[k] !== undefined)
          node.setAttribute(k, attrs[k]);
      }
    }
    (children || []).forEach((c) => {
      if (c == null) return;
      node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    });
    return node;
  }

  const CHECK = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
  const LOCK = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

  // --- Flatten for prev/next -------------------------------------------------
  function buildFlat() {
    FLAT = [];
    COURSE.modules.forEach((m) => {
      m.lessons.forEach((l) => {
        FLAT.push({ type: "lesson", moduleKey: m.key, module: m, lesson: l, id: l.id, title: l.title });
      });
    });
  }

  function stepById(id) {
    return FLAT.find((s) => s.id === id);
  }

  // --- Completion counting ---------------------------------------------------
  // A lesson counts as one trackable unit; its topics each count too.
  function allUnitIds() {
    const ids = [];
    COURSE.modules.forEach((m) =>
      m.lessons.forEach((l) => {
        ids.push(l.id);
        l.topics.forEach((t) => ids.push(l.id + "::" + t.id));
        if (l.quiz && l.quiz.id) ids.push(l.quiz.id);
      })
    );
    return ids;
  }

  function moduleUnitIds(m) {
    const ids = [];
    m.lessons.forEach((l) => {
      ids.push(l.id);
      l.topics.forEach((t) => ids.push(l.id + "::" + t.id));
      if (l.quiz && l.quiz.id) ids.push(l.quiz.id);
    });
    return ids;
  }

  function countDone(ids) {
    return ids.filter((id) => Progress.isDone(id)).length;
  }

  // --- Sequential (locked-order) mode ---------------------------------------
  // When course.settings.sequential is on, each lesson stays locked until the
  // previous lesson (and its quiz, if any) is complete, so learners work through
  // the course in order and only reach the badge/certificate at the very end.
  let _curLesson = null, _nextStep = null, _nextBtn = null;

  function isSequential() {
    return !!(COURSE.settings && COURSE.settings.sequential);
  }

  // A lesson counts as complete (for gating the next) when its lesson unit is
  // done and, if it has a knowledge check, the quiz is passed. Bonus topics
  // don't gate progression.
  function lessonComplete(l) {
    if (!l) return true;
    if (!Progress.isDone(l.id)) return false;
    if (l.quiz && l.quiz.id && !Progress.isDone(l.quiz.id)) return false;
    return true;
  }

  // Index in FLAT of the first lesson that isn't complete — the furthest a
  // learner may go. It and everything before are open; everything after locks.
  function frontierIndex() {
    for (let i = 0; i < FLAT.length; i++) {
      if (!lessonComplete(FLAT[i].lesson)) return i;
    }
    return FLAT.length; // whole course complete — nothing locked
  }

  function lessonLockedById(id) {
    if (!isSequential()) return false;
    const idx = FLAT.findIndex((s) => s.id === id);
    return idx > frontierIndex();
  }

  // Refresh sidebar + the Next button's locked state in place, so completing a
  // lesson/quiz immediately unlocks the next one without a full re-render.
  function refreshLockState() {
    renderSidebar(currentActiveId());
    if (_nextBtn) {
      const locked = isSequential() && !!_nextStep && !lessonComplete(_curLesson);
      _nextBtn.disabled = !_nextStep || locked;
      _nextBtn.classList.toggle("pager__btn--locked", locked);
      _nextBtn.title = locked ? "Complete this lesson to continue" : "";
    }
  }

  // Brief "locked" notice when a learner tries to jump ahead in sequential mode.
  function flashLock(msg) {
    const old = document.getElementById("seq-lock-toast");
    if (old) old.remove();
    const t = el("div", { id: "seq-lock-toast", class: "lms-toast lms-toast--lock", role: "status", "aria-live": "polite" }, [
      el("strong", {}, ["🔒 Locked"]),
      el("span", {}, [msg]),
    ]);
    document.body.appendChild(t);
    setTimeout(() => { if (t && t.parentNode) t.remove(); }, 3200);
  }

  // --- Sidebar render --------------------------------------------------------
  function renderSidebar(activeId) {
    els.nav.innerHTML = "";
    COURSE.modules.forEach((m) => {
      if (!m.lessons || !m.lessons.length) return;
      const unitIds = moduleUnitIds(m);
      const done = countDone(unitIds);
      const complete = done === unitIds.length;
      const hasActive = m.lessons.some((l) => l.id === activeId);

      const list = el("ul", { class: "mod__list" });
      m.lessons.forEach((l) => {
        const locked = lessonLockedById(l.id);
        const link = el(
          "button",
          {
            class: "lesson-link" + (locked ? " lesson-link--locked" : ""),
            "data-done": Progress.isDone(l.id) ? "true" : "false",
            "aria-current": l.id === activeId ? "true" : "false",
            "aria-disabled": locked ? "true" : "false",
            disabled: locked ? "disabled" : null,
            title: locked ? "Complete the previous lesson to unlock" : null,
            onclick: locked ? null : () => selectLesson(l.id, true),
          },
          [
            el("span", { class: "lesson-link__check", html: Progress.isDone(l.id) ? CHECK : (locked ? LOCK : "") }),
            el("span", { class: "lesson-link__label" }, [l.title]),
            l.videos && l.videos.length
              ? el("span", { class: "lesson-link__badge", title: l.videos.length + " video(s)" }, ["▶ " + l.videos.length])
              : null,
          ]
        );
        list.appendChild(el("li", {}, [link]));
      });

      const mod = el("div", { class: "mod", "aria-expanded": hasActive || complete === false ? "true" : "true" }, [
        el(
          "button",
          {
            class: "mod__btn",
            "aria-expanded": "true",
            "aria-label": m.label + " — " + (complete ? "complete" : done + " of " + unitIds.length + " done") + ", toggle section",
            onclick: (e) => {
              const btn = e.currentTarget;
              const wrap = btn.parentElement;
              const open = wrap.getAttribute("aria-expanded") !== "false";
              wrap.setAttribute("aria-expanded", open ? "false" : "true");
              btn.setAttribute("aria-expanded", open ? "false" : "true");
            },
          },
          [
            el("span", { class: "mod__chevron", "aria-hidden": "true", html: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>' }),
            el("span", {}, [m.label]),
            el("span", { class: "mod__count", "aria-hidden": "true", "data-complete": complete ? "true" : "false" }, [
              complete ? "✓" : done + "/" + unitIds.length,
            ]),
          ]
        ),
        list,
      ]);
      els.nav.appendChild(mod);
    });

    updateProgressMeter();
  }

  function updateProgressMeter() {
    const ids = allUnitIds();
    const done = countDone(ids);
    const pct = ids.length ? Math.round((done / ids.length) * 100) : 0;
    els.progressBar.style.width = pct + "%";
    els.progressPct.textContent = pct + "%";
    els.progressCount.textContent = done + " / " + ids.length + " complete";
    // Expose progress to assistive tech.
    const track = els.progressBar.parentElement;
    if (track) {
      track.setAttribute("role", "progressbar");
      track.setAttribute("aria-valuemin", "0");
      track.setAttribute("aria-valuemax", "100");
      track.setAttribute("aria-valuenow", String(pct));
      track.setAttribute("aria-label", "Course progress: " + pct + "% complete");
    }
  }

  // --- Lesson view -----------------------------------------------------------
  function selectLesson(id, closeNav) {
    const step = stepById(id);
    if (!step) {
      renderHome();
      return;
    }
    // Sequential mode: don't let learners jump past the current lesson.
    if (lessonLockedById(id)) {
      const open = FLAT[frontierIndex()];
      flashLock("Finish the current lesson before moving ahead.");
      if (open && open.id !== id) { selectLesson(open.id, closeNav); return; }
      renderHome();
      return;
    }
    location.hash = "#/" + encodeURIComponent(id);
    const idx = FLAT.indexOf(step);
    const prev = FLAT[idx - 1];
    const next = FLAT[idx + 1];
    const l = step.lesson;
    const m = step.module;
    _curLesson = l;
    _nextStep = next || null;

    const view = el("div", { class: "reader__inner" }, [
      el("nav", { class: "crumbs", "aria-label": "Breadcrumb" }, [
        el("a", { href: "#", onclick: (e) => { e.preventDefault(); renderHome(); } }, [COURSE.title]),
        el("span", { "aria-hidden": "true" }, ["/"]),
        el("span", { "aria-current": "page" }, [m.label]),
      ]),
      el("header", { class: "lesson-head" }, [
        el("div", { class: "lesson-head__eyebrow" }, [m.title]),
        el("h1", {}, [l.title]),
        l.videos && l.videos.length
          ? el("div", { class: "video-count-chip" }, ["▶ " + l.videos.length + (l.videos.length === 1 ? " video" : " videos") + " in this lesson"])
          : null,
      ]),
      l.content && l.content.trim().length > 20
        ? el("article", { class: "content", html: l.content })
        : (l.topics && l.topics.length
            ? el("p", { class: "video-count-chip", style: "margin-top:22px;background:var(--surface-alt)" }, ["This lesson is a set of resources — open each item below."])
            : el("article", { class: "content" }, ["Content for this lesson is coming soon."])),
      renderTopics(l),
      renderQuiz(l),
      renderActions(l),
      renderPager(prev, next),
    ]);

    els.reader.innerHTML = "";
    els.reader.appendChild(view);
    els.reader.scrollTop = 0;
    window.scrollTo(0, 0);
    renderSidebar(id);
    if (closeNav) setNav(false);
    document.title = l.title + " · " + COURSE.title;
    LMS.seen(id);
  }

  function renderTopics(l) {
    if (!l.topics || !l.topics.length) return null;
    const wrap = el("section", { class: "topics" }, [
      el("h2", { class: "topics__title" }, ["Topics in this lesson"]),
    ]);
    l.topics.forEach((t) => {
      const unitId = l.id + "::" + t.id;
      const details = el("details", { class: "topic" }, [
        el("summary", { class: "topic__summary" }, [
          el("span", { class: "lesson-link__check", "data-done": Progress.isDone(unitId) ? "true" : "false", style: Progress.isDone(unitId) ? "background:var(--success);border-color:var(--success);color:#fff" : "", html: Progress.isDone(unitId) ? CHECK : "" }),
          el("span", {}, [t.title]),
          t.videos && t.videos.length ? el("span", { class: "lesson-link__badge", style: "color:var(--navy-700);border-color:var(--line)" }, ["▶ " + t.videos.length]) : null,
          el("span", { class: "topic__chevron", html: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>' }),
        ]),
        el("div", { class: "topic__body" }, [
          el("div", { class: "content", style: "border:0;box-shadow:none;padding:16px 0 0", html: t.content }),
          el("div", { class: "lesson-actions" }, [makeCompleteBtn(unitId, "topic")]),
        ]),
      ]);
      wrap.appendChild(details);
    });
    return wrap;
  }

  function makeCompleteBtn(unitId, kind) {
    function label() {
      return Progress.isDone(unitId)
        ? (kind === "topic" ? "Topic complete" : "Completed")
        : (kind === "topic" ? "Mark topic complete" : "Mark lesson complete");
    }
    const btn = el("button", {
      class: "btn " + (Progress.isDone(unitId) ? "btn-ghost" : "btn-gold"),
      onclick: () => {
        const now = !Progress.isDone(unitId);
        Progress.set(unitId, now);
        btn.className = "btn " + (now ? "btn-ghost" : "btn-gold");
        btn.querySelector(".mc-label").textContent = label();
        btn.querySelector(".mc-icon").innerHTML = now ? CHECK : "";
        refreshLockState();
      },
    }, [
      el("span", { class: "mc-icon lesson-link__check", style: "border-color:currentColor", html: Progress.isDone(unitId) ? CHECK : "" }),
      el("span", { class: "mc-label" }, [label()]),
    ]);
    return btn;
  }

  function renderActions(l) {
    return el("div", { class: "lesson-actions" }, [makeCompleteBtn(l.id, "lesson")]);
  }

  // --- Quiz / knowledge check ------------------------------------------------
  function shuffled(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
    return a;
  }
  function qidOf(q, i) { return q.qid || ("q" + i); }

  function renderQuiz(l) {
    if (!l.quiz || !l.quiz.questions || !l.quiz.questions.length) return null;
    const quiz = l.quiz;
    const passed = Progress.isDone(quiz.id);
    const section = el("section", { class: "quiz", "aria-labelledby": "quiz-h-" + quiz.id });
    section.appendChild(el("h2", { class: "quiz__title", id: "quiz-h-" + quiz.id }, [
      el("span", { class: "quiz__badge" + (passed ? " quiz__badge--pass" : ""), html: passed ? CHECK : "" }),
      "Knowledge check",
    ]));

    // Choose the question subset (question bank / pick N) for this attempt.
    let asked = quiz.questions.map((q, i) => ({ q: q, i: i }));
    if (quiz.pick && quiz.pick > 0 && quiz.pick < asked.length) asked = shuffled(asked).slice(0, quiz.pick);

    const attemptsNote = quiz.attempts ? " · " + quiz.attempts + " attempt" + (quiz.attempts === 1 ? "" : "s") + " allowed" : "";
    section.appendChild(el("p", { class: "quiz__meta" }, [
      "You need " + (quiz.pass || 70) + "% to pass" + (quiz.pick ? " · " + asked.length + " question" + (asked.length === 1 ? "" : "s") : "") + attemptsNote +
      (passed ? " — you've passed this check." : "."),
    ]));

    const form = el("form", { class: "quiz__form", "aria-describedby": "quiz-h-" + quiz.id });
    asked.forEach((entry, n) => {
      const q = entry.q, qid = qidOf(q, entry.i), type = q.type || "single";
      const fs = el("fieldset", { class: "quiz__q", "data-qid": qid }, [
        el("legend", {}, [(n + 1) + ". " + q.text + (type === "multiple" ? "  (choose all that apply)" : "")]),
      ]);
      if (type === "short") {
        fs.appendChild(el("input", { type: "text", class: "quiz__short", name: qid, autocomplete: "off",
          "aria-label": "Your answer", placeholder: "Type your answer" }));
      } else {
        const inputType = type === "multiple" ? "checkbox" : "radio";
        const opts = quiz.shuffle ? shuffled(q.options.map((o, oi) => ({ o: o, oi: oi }))) : q.options.map((o, oi) => ({ o: o, oi: oi }));
        opts.forEach((oe) => {
          const inputId = "opt-" + quiz.id + "-" + qid + "-" + oe.oi;
          fs.appendChild(el("label", { class: "quiz__opt", for: inputId }, [
            el("input", { type: inputType, name: qid, id: inputId, value: String(oe.oi) }),
            el("span", {}, [oe.o.text]),
          ]));
        });
      }
      fs.appendChild(el("div", { class: "quiz__fb", "data-qid": qid, style: "display:none" }));
      form.appendChild(fs);
    });

    const result = el("div", { class: "quiz__result", role: "status", "aria-live": "polite" });
    const submit = el("button", { class: "btn btn-gold", type: "submit" }, [passed ? "Retake quiz" : "Submit answers"]);
    form.appendChild(el("div", { class: "quiz__actions" }, [submit, result]));

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const answers = {}; const askedIds = [];
      let unanswered = false;
      asked.forEach((entry) => {
        const q = entry.q, qid = qidOf(q, entry.i), type = q.type || "single";
        askedIds.push(qid);
        if (type === "short") {
          const v = (form.querySelector('input[name="' + cssEsc(qid) + '"]') || {}).value || "";
          answers[qid] = v.trim();
          if (!answers[qid]) unanswered = true;
        } else {
          answers[qid] = Array.from(form.querySelectorAll('input[name="' + cssEsc(qid) + '"]:checked')).map((i) => Number(i.value));
          if (!answers[qid].length) unanswered = true;
        }
      });
      if (unanswered) { showQuizResult(result, null, "Please answer every question before submitting.", false); return; }
      submit.disabled = true;

      const finish = (graded) => {
        submit.disabled = false;
        if (graded.ok === false) { showQuizResult(result, null, graded.error || "Could not submit.", false); return; }
        submit.textContent = "Retake quiz";
        if (graded.detail) showQuizFeedback(form, graded.detail);
        let msg = "Score: " + graded.score + "/" + graded.total + " (" + graded.percent + "%).";
        if (typeof graded.attempts_limit !== "undefined")
          msg += " Attempt " + graded.attempts_used + " of " + graded.attempts_limit + ".";
        if (graded.passed) {
          Progress.setLocal(quiz.id, true);
          if (!LMS.enabled) Progress.set(quiz.id, true);
          showQuizResult(result, graded, "You passed! " + msg, true);
          refreshLockState();
          if (graded.badge_url) LMS.celebrate(graded.badge_url);
        } else {
          showQuizResult(result, graded, msg + " You need " + (quiz.pass || 70) + "% — review and try again.", false);
        }
      };

      if (LMS.enabled) {
        LMS.postQuiz(quiz.id, answers, askedIds).then((res) => {
          if (res && (typeof res.passed !== "undefined" || res.ok === false)) finish(res);
          else { submit.disabled = false; showQuizResult(result, null, "Could not submit the quiz. Please try again.", false); }
        });
      } else {
        finish(gradeLocally(quiz, answers, askedIds));
      }
    });

    section.appendChild(form);
    return section;
  }

  function cssEsc(s) { return String(s).replace(/["\\]/g, "\\$&"); }

  // Show per-question correctness + feedback after grading.
  function showQuizFeedback(form, detail) {
    Object.keys(detail).forEach((qid) => {
      const fb = form.querySelector('.quiz__fb[data-qid="' + cssEsc(qid) + '"]');
      const fs = form.querySelector('fieldset[data-qid="' + cssEsc(qid) + '"]');
      if (fs) fs.setAttribute("data-result", detail[qid].correct ? "correct" : "wrong");
      if (fb) {
        fb.style.display = "block";
        fb.className = "quiz__fb " + (detail[qid].correct ? "quiz__fb--ok" : "quiz__fb--no");
        fb.textContent = (detail[qid].correct ? "✓ Correct. " : "✗ Not quite. ") + (detail[qid].feedback || "");
      }
    });
  }

  // Client-side grading fallback (standalone / no LMS). Requires answer keys in
  // course.json, which are present only when not gated behind the LMS.
  function gradeLocally(quiz, answers, askedIds) {
    const byId = {}; quiz.questions.forEach((q, i) => { byId[qidOf(q, i)] = q; });
    const ids = (askedIds && askedIds.length) ? askedIds : Object.keys(byId);
    let score = 0; const detail = {};
    ids.forEach((qid) => {
      const q = byId[qid]; if (!q) return;
      let ok = false;
      if ((q.type || "single") === "short") {
        const norm = (s) => String(s).toLowerCase().trim().replace(/\s+/g, " ").replace(/[.!?,;:"'`]+$/, "");
        const given = norm(answers[qid] || "");
        ok = given !== "" && (q.answers || []).some((a) => norm(a) === given);
      } else {
        const correct = []; (q.options || []).forEach((o, oi) => { if (o.correct) correct.push(oi); });
        const sel = (answers[qid] || []).slice().sort((a, b) => a - b);
        const cor = correct.slice().sort((a, b) => a - b);
        ok = sel.length > 0 && sel.length === cor.length && sel.every((v, i) => v === cor[i]);
      }
      if (ok) score++;
      detail[qid] = { correct: ok, feedback: q.feedback || "" };
    });
    const total = ids.length;
    const percent = total ? Math.round((score / total) * 100) : 0;
    return { score: score, total: total, percent: percent, passed: percent >= (quiz.pass || 70), detail: detail };
  }

  function showQuizResult(node, graded, message, ok) {
    node.className = "quiz__result " + (ok ? "quiz__result--pass" : (graded ? "quiz__result--fail" : "quiz__result--note"));
    node.textContent = message;
  }

  function renderPager(prev, next) {
    const nextLocked = isSequential() && !!next && !lessonComplete(_curLesson);
    const nextBtn = el("button", {
      class: "pager__btn pager__btn--next" + (nextLocked ? " pager__btn--locked" : ""),
      disabled: next && !nextLocked ? null : "disabled",
      title: nextLocked ? "Complete this lesson to continue" : null,
      onclick: next && !nextLocked ? () => selectLesson(next.id, true) : null,
    }, [
      el("span", { class: "pager__dir" }, [nextLocked ? "🔒 Next" : "Next →"]),
      el("span", { class: "pager__title" }, [next ? next.title : ""]),
    ]);
    _nextBtn = nextBtn;
    return el("nav", { class: "pager", "aria-label": "Lesson navigation" }, [
      el("button", {
        class: "pager__btn",
        disabled: prev ? null : "disabled",
        onclick: prev ? () => selectLesson(prev.id, true) : null,
      }, [
        el("span", { class: "pager__dir" }, ["← Previous"]),
        el("span", { class: "pager__title" }, [prev ? prev.title : ""]),
      ]),
      nextBtn,
    ]);
  }

  function currentActiveId() {
    const h = decodeURIComponent((location.hash || "").replace(/^#\//, ""));
    return h || null;
  }

  // --- Course home -----------------------------------------------------------
  function renderHome() {
    location.hash = "";
    document.title = COURSE.title;
    const s = COURSE.stats;
    const firstLesson = FLAT[0];

    const cards = el("div", { class: "module-cards" });
    COURSE.modules.forEach((m) => {
      if (!m.lessons || !m.lessons.length) return;
      const unitIds = moduleUnitIds(m);
      const done = countDone(unitIds);
      const mLocked = lessonLockedById(m.lessons[0].id);
      cards.appendChild(
        el("button", { class: "module-card" + (mLocked ? " module-card--locked" : ""), onclick: () => selectLesson(m.lessons[0].id, true) }, [
          el("span", { class: "module-card__label" }, [m.label]),
          el("h3", {}, [m.title]),
          el("p", {}, [m.summary]),
          el("div", { class: "module-card__foot" }, [
            el("span", {}, [mLocked ? "🔒 Locked" : (m.lessons.length + " lesson" + (m.lessons.length === 1 ? "" : "s"))]),
            el("span", {}, [done + "/" + unitIds.length + " done"]),
          ]),
        ])
      );
    });

    const view = el("div", { class: "reader__inner" }, [
      el("section", { class: "course-home__hero" }, [
        el("span", { class: "tag-pill" }, [COURSE.provider || "Digital Certification"]),
        el("h1", {}, [COURSE.title]),
        el("p", {}, [COURSE.tagline || "Self-paced professional learning — learn at your own pace and earn your badge."]),
        el("div", { class: "course-home__stats" }, [
          stat(s.core_modules || s.modules, "Modules"),
          stat(s.lessons, "Lessons"),
          stat(s.videos, "Videos"),
          stat(s.topics, "Bonus resources"),
        ]),
        el("button", { class: "btn btn-gold", disabled: firstLesson ? null : true, onclick: () => firstLesson && selectLesson(firstLesson.id, true) }, [
          resumeLabel(),
        ]),
      ]),
      el("section", { class: "course-home__overview" }, [
        el("h2", {}, ["Course modules"]),
        cards,
      ]),
      COURSE.overview_html
        ? el("section", { class: "course-home__overview" }, [
            el("h2", {}, ["About this course"]),
            el("article", { class: "content", html: COURSE.overview_html }),
          ])
        : null,
    ]);

    if (LMS.enabled && COURSE.settings && COURSE.settings.assignment_count) {
      view.insertBefore(el("section", {class:"course-home__overview"}, [
        el("h2", {}, ["Assignments and completion"]),
        el("p", {}, [(COURSE.settings.required_assignments || 0) + " required assignment(s) need a passing instructor grade for course completion and a new certificate."]),
        el("a", {class:"btn btn-gold",href:new URL("../assignments/"+encodeURIComponent(COURSE.slug), LMS.base).href}, ["Open assignments and feedback"])
      ]), view.firstChild.nextSibling);
    }
    els.reader.innerHTML = "";
    els.reader.appendChild(view);
    window.scrollTo(0, 0);
    renderSidebar(null);
  }

  function resumeLabel() {
    const ids = allUnitIds();
    const done = countDone(ids);
    if (done === 0) return "Start the course →";
    if (done === ids.length) return "Review the course →";
    // Find first not-done lesson.
    const nextStep = FLAT.find((s) => !Progress.isDone(s.id));
    return nextStep ? "Resume where you left off →" : "Continue →";
  }

  function stat(n, label) {
    return el("div", { class: "stat" }, [el("b", {}, [String(n)]), el("span", {}, [label])]);
  }

  // --- Nav drawer (mobile) ---------------------------------------------------
  function setNav(open) {
    els.app.setAttribute("data-nav-open", open ? "true" : "false");
  }
  if (els.navToggle) els.navToggle.addEventListener("click", () => setNav(els.app.getAttribute("data-nav-open") !== "true"));
  if (els.backdrop) els.backdrop.addEventListener("click", () => setNav(false));

  // --- Boot ------------------------------------------------------------------
  // Public syllabus view (no lesson content). Shows module #, title, description
  // and lesson titles, with a prompt to sign in for the full course.
  function renderPreview() {
    const loginUrl = new URL("../../login", location.href).href;
    const registerUrl = new URL("../../register", location.href).href;
    const s = COURSE.stats || {};

    // Sidebar: modules + lesson titles (not interactive in preview).
    els.nav.innerHTML = "";
    COURSE.modules.forEach((m) => {
      const list = el("ul", { class: "mod__list" });
      (m.lessons || []).forEach((l) => {
        list.appendChild(el("li", {}, [el("span", { class: "lesson-link", style: "cursor:default" }, [
          el("span", { class: "lesson-link__label" }, [l.title]),
        ])]));
      });
      els.nav.appendChild(el("div", { class: "mod", "aria-expanded": "true" }, [
        el("div", { class: "mod__btn", style: "cursor:default" }, [el("span", {}, [m.label || m.title])]),
        list,
      ]));
    });
    els.progressCount.textContent = "Preview";
    els.progressPct.textContent = "";
    els.progressBar.style.width = "0%";

    const cards = el("div", { class: "module-cards" });
    COURSE.modules.forEach((m) => {
      cards.appendChild(el("div", { class: "module-card", style: "cursor:default" }, [
        el("span", { class: "module-card__label" }, [m.label || "Module"]),
        el("h3", {}, [m.title]),
        m.summary ? el("p", {}, [m.summary]) : null,
        el("div", { class: "module-card__foot" }, [
          el("span", {}, [(m.lessons ? m.lessons.length : 0) + " lesson" + ((m.lessons && m.lessons.length === 1) ? "" : "s")]),
        ]),
      ]));
    });

    const view = el("div", { class: "reader__inner" }, [
      el("section", { class: "course-home__hero" }, [
        el("span", { class: "tag-pill" }, [COURSE.provider || "Course"]),
        el("h1", {}, [COURSE.title]),
        el("p", {}, [COURSE.tagline || "Sign in to take this self-paced course and earn your badge."]),
        el("div", { class: "course-home__stats" }, [
          stat(s.core_modules || s.modules || (COURSE.modules || []).length, "Modules"),
          stat(s.lessons || 0, "Lessons"),
          stat(s.videos || 0, "Videos"),
        ]),
        el("div", { style: "display:flex;gap:12px;flex-wrap:wrap;margin-top:8px" }, [
          el("a", { class: "btn btn-gold", href: loginUrl }, ["Sign in to start →"]),
          el("a", { class: "btn btn-ghost", href: registerUrl }, ["Create an account"]),
        ]),
      ]),
      el("section", { class: "course-home__overview" }, [
        el("div", { class: "preview-note" }, [
          "This is a course outline. Sign in to access the lessons, videos, and to earn your certificate.",
        ]),
        el("h2", {}, ["What you'll learn"]),
        cards,
      ]),
    ]);
    els.reader.innerHTML = "";
    els.reader.appendChild(view);
    window.scrollTo(0, 0);
  }

  // Apply white-label branding to the shell (topbar/sidebar/title). Rebrands
  // even course pages whose static HTML was generated with an older brand.
  function applyBrand(brand, authed) {
    if (!brand) return;
    const name = brand.catalog_name || "Courses";
    // When running inside Sapiqo and signed in, the brand icon returns to the
    // learner Dashboard (not the public course catalog).
    if (authed) {
      const dash = new URL("../../dashboard", location.href).href;
      document.querySelectorAll("a.brand").forEach(function (a) { a.setAttribute("href", dash); });
    }
    document.querySelectorAll(".brand").forEach(function (a) {
      const mark = a.querySelector(".brand__mark");
      const label = a.querySelector("span:not(.brand__mark)");
      if (brand.logo) {
        const img = document.createElement("img");
        img.src = brand.logo; img.alt = name; img.style.height = "26px"; img.style.width = "auto";
        if (mark) mark.replaceWith(img); if (label) label.textContent = "";
      } else {
        if (mark) mark.textContent = brand.mark || name.charAt(0);
        if (label) label.textContent = name;
      }
    });
    if (document.title.indexOf("Sapiqo Courses") !== -1)
      document.title = document.title.replace(/Sapiqo Courses/g, name);
  }
  function hydrateBrand() {
    try {
      fetch(new URL("../../api/whoami", location.href).href, { credentials: "same-origin" })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d && d.brand) applyBrand(d.brand, d.authenticated); })
        .catch(function () {});
    } catch (_) {}
  }

  function boot(course) {
    hydrateBrand();
    COURSE = course;
    PROGRESS_KEY = "sapiqo-course-progress::" + course.slug;
    Progress._cache = null;
    els.courseTitle.textContent = course.title;
    document.title = course.title;

    // Public/preview course.json (not signed in): show a syllabus only — module
    // number, title, description, and lesson titles — never the lesson content.
    if (course.preview) { renderPreview(); return; }

    buildFlat();

    const active = currentActiveId();
    if (active && stepById(active)) selectLesson(active, false);
    else renderHome();

    // Enable LMS sync if this course is running inside Sapiqo. Re-render once
    // server progress is hydrated so checkmarks and bars reflect the account.
    LMS.init(course.slug).then((on) => {
      if (on) {
        const id = currentActiveId();
        if (id && stepById(id)) selectLesson(id, false);
        else renderHome();
      }
    });

    window.addEventListener("hashchange", () => {
      const id = currentActiveId();
      if (id && stepById(id)) {
        if (id !== (document.querySelector('.lesson-link[aria-current="true"]') || {}).dataset) selectLesson(id, false);
      } else {
        renderHome();
      }
    });
  }

  fetch("course.json", { cache: "no-cache" })
    .then((r) => {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.json();
    })
    .then(boot)
    .catch((err) => {
      els.reader.innerHTML =
        '<div class="reader__inner"><div class="content"><h1>Could not load the course</h1>' +
        '<p>The course data file did not load. If you are opening this file directly, run a local web server instead (browsers block <code>fetch</code> on <code>file://</code>):</p>' +
        '<pre>python3 -m http.server 8000</pre><p>Then visit <code>http://localhost:8000/chromebook-educator/</code>.</p>' +
        "<p style=\"color:var(--ink-soft)\">Details: " + String(err) + "</p></div></div>";
    });
})();
