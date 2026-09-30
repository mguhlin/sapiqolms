# Compatibility and validation matrix

© 2026 Miguel Guhlin · CC BY-SA 4.0

For 1.13.0, “tested” means the specific checks below, not complete certification.
The [Verify workflow](../.github/workflows/verify.yml) gates Pages deployment.

| Area | Evidence / scope | Status |
| --- | --- | --- |
| Linux/PHP/SQLite | Security and HTTP suite, full recovery, eight-worker contention, upgrade/rollback | Tested locally |
| MariaDB / PDO MySQL | Disposable server; schema 22, reset/quiz/MFA contention, assignments, rubric/score completion, dump/restore | Tested locally |
| PHP 8.1, 8.3, 8.4 | Regression matrix in GitHub Actions | Release requires green CI |
| Docker/Apache | Image build, first administrator login, readiness page in GitHub Actions | Release requires green CI |
| Chromium | Desktop/mobile introduction; author/learner assignment lifecycle; certificates; SCORM parent isolation; MFA/revocation | Tested locally; repeated in CI |
| SMTP | Local protocol fixture, message headers and dot-stuffing | Protocol tested; real TLS/provider delivery unverified |
| Native course JSON | Protected reader, progress, quizzes, authoring, assignment integration | Tested fixtures; actual imported course review still required |
| SCORM 1.2 / 2004 | Basic API shim and completion bridge, opaque-origin fixture, score/completion recorded | Basic fixture tested; full standard conformance not claimed |
| Common Cartridge / LearnDash / OneRoster | Existing implementations and containment/error regressions | Representative live LMS round trips still required |
| Google / Microsoft / Clever / ClassLink / Rhythm | Local identity controls and explicit linking; configured endpoints | Real provider credentials and login/logout tests required |
| LTI 1.3 / grade passback | Local JWT and provisioning tests; MFA launch target preserved | Actual LMS launch/passback and certification unverified |
| nginx / Windows / IIS / web-server offload | Existing deployment instructions | Environment tests required |
| Safari / Firefox / screen readers | No provider/browser credentials needed, but manual assistive-technology review required | Pending |
| Organizational pilots / production workload | Needs participants, representative courses, host and workload | Pending |

SCORM restrictions, legacy API-key behavior and recovery details are in
[Operations](OPERATIONS.md). Tests use synthetic accounts and disposable data;
no existing organizational installation was upgraded or used as a test fixture.
