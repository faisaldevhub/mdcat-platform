# Phase 2 Architecture Review

> **Review Date:** 2026-06-24  
> **Reviewed:** PHASE_2_RESOURCE_APIS.md  
> **Scope:** Critical architecture audit before implementation

---

## Issues Found

### Issue 1 — Content Handlers Are Admin-Only (CRITICAL)

> [!CAUTION]
> **Severity: HIGH — Will crash REST endpoints at runtime**

`Subjects_Handler`, `Chapters_Handler`, `Collections_Handler`, and `Questions_Handler` are **all loaded inside `is_admin()`** ([class-loader.php:15-41](file:///c:/Users/FaisalDev/Desktop/MDCAT/mdcat-platform/includes/class-loader.php#L15-L41)).

REST requests are not admin requests. Every Content API endpoint (`GET /subjects`, `GET /chapters`, etc.) will throw `Class not found` errors — identical to the `Student_Status_Service` bug we just fixed.

**Affected Phase 2 endpoints:** All 6 Content API endpoints + any endpoint that references these handler classes.

**Fix:** Extract the read-only handler classes (or their query methods) outside `is_admin()`. The `init()` methods on these handlers register `admin_post_` hooks for CRUD, so those should stay inside `is_admin()`, but the data-access methods (`get_subjects()`, `get_chapters()`, `get_collections()`) must be available globally.

The cleanest approach: load the handler PHP files unconditionally (they're pure data classes), but only call `::init()` inside `is_admin()`.

---

### Issue 2 — `GET /dashboard` Fires 11 Service Calls in One Request

> [!WARNING]
> **Severity: HIGH — Performance**

The full `GET /dashboard` endpoint replicates the existing AJAX handler's monolithic pattern, making 11 service calls that touch 10+ tables in a single HTTP request. This is acceptable in server-rendered WordPress (one AJAX call per page load), but in a Next.js SPA where the dashboard is the landing page, this is a performance bottleneck.

The plan already has sub-endpoints (`/dashboard/progress`, `/dashboard/continue-learning`, `/dashboard/study-plan`), but the full `GET /dashboard` endpoint duplicates all of them plus more.

**Fix:** Remove the monolithic `GET /dashboard`. The frontend should compose the dashboard from granular endpoints that it fetches in parallel:

- `GET /dashboard/stats` — stats only
- `GET /dashboard/progress` — progress only
- `GET /dashboard/continue-learning`
- `GET /dashboard/study-plan`
- `GET /gamification/streak`
- `GET /notifications` (with `per_page=5`)

This lets the frontend render incrementally (stats first, then analytics, etc.) and avoids blocking on the slowest query.

---

### Issue 3 — Bookmarks and Wrong Questions Need Pagination

> [!WARNING]
> **Severity: MEDIUM — Will degrade with scale**

`GET /revision/bookmarks` and `GET /revision/wrong-questions` return **all** results with no pagination. A student who has bookmarked 500 questions or answered 1000 questions wrong will receive a massive JSON payload.

The underlying service (`Revision_Service::get_question_dataset()`) does not have `LIMIT`/`OFFSET`, but this is a known data-growth risk.

**Fix:** Add `page` and `per_page` parameters. For Phase 2, accept unpaginated as acceptable for MVP since the existing AJAX does the same, but document it as a follow-up optimization.

---

### Issue 4 — `GET /enrollment/status` Leaks Information

> [!WARNING]
> **Severity: MEDIUM — Security**

The proposed `GET /enrollment/status?email=...` is public and allows anyone to check if an email has a pending enrollment. This enables:

1. Email enumeration (probe whether someone has applied)
2. Status disclosure (pending/approved/rejected)

The existing AJAX has no equivalent — enrollment status is only visible in the admin panel.

**Fix:** Remove this endpoint. The Next.js frontend does not need it — after form submission, show a static confirmation message ("Your request is being processed"). If status checking is needed in the future, gate it behind authentication or rate-limit it heavily.

---

### Issue 5 — `POST /enrollment/submit` File Upload Complexity

> [!IMPORTANT]
> **Severity: MEDIUM — Architecture risk**

File uploads via REST API from a Next.js frontend require `multipart/form-data` parsing, WordPress media handling functions (`wp_handle_upload`, etc.), and proper CORS for file POST requests. The existing AJAX handler has complex upload logic with file cleanup on failure.

This is the most complex single endpoint in Phase 2 and involves cross-origin file handling that didn't exist in the WordPress-rendered frontend.

**Fix:** Defer `POST /enrollment/submit` to Phase 2F (last). Build the simple authenticated endpoints first. When implementing, reuse the existing `handle_screenshot_upload()` method from `Enrollment_Ajax`.

---

### Issue 6 — Duplicate Data Between Endpoints

> [!NOTE]
> **Severity: LOW — Redundancy**

Several proposed endpoints return overlapping data:

| Data | Available from |
|------|---------------|
| Streak | `GET /dashboard` (proposed), `GET /dashboard/stats`, `GET /gamification/streak` |
| Subject progress | `GET /dashboard` (proposed), `GET /dashboard/progress` |
| XP/Badges/Achievements | `GET /dashboard` (proposed), individual gamification endpoints |
| Notification count | `GET /dashboard` (proposed), `GET /notifications` |

If `GET /dashboard` is removed per Issue 2, the duplication resolves itself.

**Fix:** Remove monolithic `GET /dashboard`. Frontend composes from granular endpoints.

---

### Issue 7 — `check_quiz_access` Requires `collection_id` — Misused on `GET /collections/{id}`

> [!NOTE]
> **Severity: MEDIUM — Permission callback mismatch**

The plan assigns `check_quiz_access` to `GET /collections/{id}`, but `check_quiz_access` expects `collection_id` in the request body (for `POST /quiz/start`). A `GET /collections/{id}` request sends the collection ID as a URL parameter `{id}`, not as `collection_id` in the body.

**Fix:** Use `check_dashboard_access` for `GET /collections/{id}` (viewing metadata is a dashboard-level operation). Reserve `check_quiz_access` exclusively for `POST /quiz/start`.

---

### Issue 8 — Missing `check_gamification_access` on Gamification Endpoints

> [!NOTE]
> **Severity: MEDIUM — Permission callback mistake**

The plan assigns `check_dashboard_access` to `GET /gamification/xp`, `GET /gamification/badges`, and `GET /gamification/achievements`. However, `Base_Controller` already provides `check_gamification_access` which checks `can_access_streak()` — the correct gamification gate.

Only `GET /gamification/streak` correctly uses `check_streak_access` (which doesn't exist — should be `check_gamification_access`).

**Fix:** All 5 gamification endpoints should use `check_gamification_access`.

---

### Issue 9 — `GET /dashboard/study-plan` Duplicate Query Problem

> [!NOTE]
> **Severity: MEDIUM — Performance**

When called standalone (not from the full dashboard), `Recommendation_Service::get_study_plan()` re-fetches progress, streak, and chapter performance data that the full dashboard already fetched. The existing AJAX handler solves this by passing a pre-fetched `$context` array.

The standalone `GET /dashboard/study-plan` endpoint can't benefit from this optimization because it doesn't have the dashboard context.

**Fix:** Accept this cost for standalone calls — the study plan endpoint will make ~6 additional queries. This is acceptable because standalone study plan requests are infrequent. Document the trade-off but don't over-optimize.

---

### Issue 10 — `PUT /profile` Missing Password Verification

> [!NOTE]
> **Severity: LOW — Covered at implementation time**

The plan lists `current_password` as optional and only required when changing password, but doesn't specify how verification works. The implementation must use `wp_check_password()` before calling `wp_set_password()`.

**Fix:** Document in the implementation contract: "Changing password requires `current_password` to be verified via `wp_check_password()` before `wp_set_password()` is called."

---

### Issue 11 — Route Naming Inconsistency

> [!NOTE]
> **Severity: LOW — Style**

| Inconsistency | Examples |
|---------------|---------|
| Plural vs singular | `/subjects` (plural) but `/quiz/start` (singular) |
| Action in route | `/quiz/start`, `/revision/bookmarks/toggle`, `/notifications/read-all` |
| Nested resource | `/quiz/{id}/answer` is good REST, but `/revision/bookmarks/toggle` is not |

**Fix:**

- `/quiz/start` → Keep as-is (action endpoint, not a CRUD resource)
- `/revision/bookmarks/toggle` → Keep as-is (matches existing AJAX toggle pattern)
- `/notifications/read-all` → Keep as-is (batch action)
- Accept these as reasonable pragmatic deviations from pure REST

---

### Issue 12 — No Rate Limiting on Public Enrollment

> [!IMPORTANT]
> **Severity: MEDIUM — Security**

The existing AJAX enrollment handler has IP-based rate limiting (3 per hour). The Phase 2 plan doesn't mention rate limiting for `POST /enrollment/submit`.

**Fix:** Implement the same IP-based rate limiting from the existing AJAX handler when building the REST endpoint. Reuse the same transient pattern.

---

### Issue 13 — `GET /access/check` Is Redundant

> [!NOTE]
> **Severity: LOW — Unnecessary endpoint**

`GET /access/check` returns booleans for each feature the student can access. But each feature endpoint already has its own permission callback (`check_dashboard_access`, `check_quiz_access`, etc.) that returns `403` if denied. The frontend will learn about access denial from the actual endpoint's error response.

**Fix:** Remove `GET /access/check`. The frontend doesn't need a separate "can I access this?" probe — it calls the real endpoint and handles 403 errors.

---

### Issue 14 — Missing `GET /dashboard/stats` Endpoint

> [!NOTE]
> **Severity: LOW — Follows from Issue 2 fix**

If the monolithic `GET /dashboard` is removed, the frontend needs a lightweight stats endpoint for the top-level dashboard cards (total quizzes, accuracy, bookmarks, weak topics).

**Fix:** Add `GET /dashboard/stats` using `Dashboard_Service::get_dashboard_stats()`.

---

### Issue 15 — Notifications Endpoint Missing `unread_count` in Header

> [!NOTE]
> **Severity: LOW — Frontend convenience**

The Next.js layout needs the unread notification count for the bell icon on every page. Calling `GET /notifications` just for a count is wasteful.

**Fix:** The existing `Notification_Service::get_dashboard_preview()` already returns `unread_count` and 5 recent items. Expose a lightweight `GET /notifications/unread-count` or include `unread_count` in the standard `GET /notifications` response header. For MVP, the frontend can call `GET /notifications?per_page=0` and read the `unread_count` from the response.

---

## Issues Summary

| # | Issue | Severity | Category | Action |
|---|-------|:--------:|----------|--------|
| 1 | Content handlers are admin-only | **HIGH** | Runtime crash | Fix before Phase 2A |
| 2 | Monolithic GET /dashboard | **HIGH** | Performance | Remove, use granular endpoints |
| 3 | Bookmarks/wrong-questions unbounded | **MEDIUM** | Scale | Accept for MVP, document follow-up |
| 4 | GET /enrollment/status leaks emails | **MEDIUM** | Security | Remove endpoint |
| 5 | Enrollment file upload complexity | **MEDIUM** | Architecture | Defer to Phase 2F |
| 6 | Duplicate data across endpoints | **LOW** | Redundancy | Resolves with Issue 2 fix |
| 7 | check_quiz_access misused on GET /collections/{id} | **MEDIUM** | Permission | Use check_dashboard_access |
| 8 | Wrong permission on gamification endpoints | **MEDIUM** | Permission | Use check_gamification_access |
| 9 | Study plan re-fetches data standalone | **MEDIUM** | Performance | Accept, document |
| 10 | PUT /profile password verification | **LOW** | Security | Document in contract |
| 11 | Route naming inconsistency | **LOW** | Style | Accept pragmatically |
| 12 | No rate limiting on enrollment | **MEDIUM** | Security | Implement when building |
| 13 | GET /access/check redundant | **LOW** | Redundancy | Remove endpoint |
| 14 | Missing GET /dashboard/stats | **LOW** | Completeness | Add lightweight endpoint |
| 15 | Notification unread count overhead | **LOW** | Performance | Include in GET /notifications response |

---

## Final Approved Endpoint List

After review, **31 endpoints** are approved (down from 34 proposed + 4 auth = 38 total).

**Removed:**
- ~~`GET /dashboard`~~ — replaced by granular sub-endpoints (Issue 2)
- ~~`GET /enrollment/status`~~ — email enumeration risk (Issue 4)
- ~~`GET /access/check`~~ — redundant with permission callbacks (Issue 13)

**Added:**
- `GET /dashboard/stats` — lightweight stats card (Issue 14)

### Auth (Phase 1 — Complete ✅)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 1 | `/auth/login` | POST | `check_public_access` | wp_authenticate + JWT_Handler |
| 2 | `/auth/refresh` | POST | `check_public_access` | JWT_Handler |
| 3 | `/auth/logout` | POST | `check_student_access` | (stateless) |
| 4 | `/auth/me` | GET | `check_student_access` | get_userdata |

### Content (Phase 2A)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 5 | `/subjects` | GET | `check_dashboard_access` | Subjects_Handler |
| 6 | `/subjects/{id}` | GET | `check_dashboard_access` | Subjects_Handler, Chapters_Handler |
| 7 | `/chapters` | GET | `check_dashboard_access` | Chapters_Handler |
| 8 | `/chapters/{id}` | GET | `check_dashboard_access` | Chapters_Handler, Collections_Handler |
| 9 | `/collections` | GET | `check_dashboard_access` | Collections_Handler |
| 10 | `/collections/{id}` | GET | `check_dashboard_access` | Collections_Handler |

### Dashboard (Phase 2B)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 11 | `/dashboard/stats` | GET | `check_dashboard_access` | Dashboard_Service |
| 12 | `/dashboard/progress` | GET | `check_dashboard_access` | Progress_Service |
| 13 | `/dashboard/continue-learning` | GET | `check_dashboard_access` | Progress_Service |
| 14 | `/dashboard/study-plan` | GET | `check_dashboard_access` | Recommendation_Service |

### Quiz (Phase 2C)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 15 | `/quiz/start` | POST | `check_quiz_access` | Quiz_Engine |
| 16 | `/quiz/{id}/questions` | GET | `check_attempt_owner` | Quiz_Engine |
| 17 | `/quiz/{id}/answer` | POST | `check_attempt_owner` | Quiz_Engine |
| 18 | `/quiz/{id}/complete` | POST | `check_attempt_owner` | Quiz_Engine |
| 19 | `/quiz/{id}/result` | GET | `check_attempt_owner` | Quiz_Engine |
| 20 | `/quiz/{id}/review` | GET | `check_attempt_owner` | Review_Service |
| 21 | `/quiz/history` | GET | `check_dashboard_access` | Attempt_History |

### Revision (Phase 2D)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 22 | `/revision/bookmarks` | GET | `check_revision_access` | Revision_Service |
| 23 | `/revision/bookmarks/toggle` | POST | `check_revision_access` | Revision_Service |
| 24 | `/revision/wrong-questions` | GET | `check_revision_access` | Revision_Service |

### Analytics (Phase 2D)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 25 | `/analytics/performance` | GET | `check_analytics_access` | Performance_Analytics |

### Gamification (Phase 2E)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 26 | `/gamification/streak` | GET | `check_gamification_access` | Streak_Service |
| 27 | `/gamification/xp` | GET | `check_gamification_access` | XP_Service |
| 28 | `/gamification/badges` | GET | `check_gamification_access` | Badge_Service |
| 29 | `/gamification/achievements` | GET | `check_gamification_access` | Achievement_Service |
| 30 | `/gamification/leaderboard` | GET | `check_gamification_access` | Leaderboard_Service |

### Enrollment (Phase 2F)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 31 | `/enrollment/submit` | POST | `check_public_access` | Enrollment_Service |

### Notifications (Phase 2F)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 32 | `/notifications` | GET | `check_dashboard_access` | Notification_Service |
| 33 | `/notifications/{id}/read` | POST | `check_dashboard_access` | Notification_Service |
| 34 | `/notifications/read-all` | POST | `check_dashboard_access` | Notification_Service |

### Profile (Phase 2F)

| # | Route | Method | Permission | Service |
|---|-------|--------|-----------|---------|
| 35 | `/profile` | GET | `check_student_access` | get_userdata + get_user_meta |
| 36 | `/profile` | PUT | `check_student_access` | wp_update_user |

---

## Recommended Phase 2A Scope

### Objective

Build the Content Controller — the simplest, lowest-risk slice that immediately unblocks the Next.js frontend for browsing subjects, chapters, and collections.

### Pre-requisite (Must Complete Before Phase 2A)

**Fix `is_admin()` loading for Content Handlers**

Move the `require_once` lines for handler files outside `is_admin()`, while keeping their `::init()` calls (which register `admin_post_` hooks) inside `is_admin()`:

```diff
  // These files contain pure data-access methods and table name constants.
  // They must be available in REST API context (non-admin).
+ require_once MDCAT_PLATFORM_PATH . 'modules/subjects/class-subjects-handler.php';
+ require_once MDCAT_PLATFORM_PATH . 'modules/chapters/class-chapters-handler.php';
+ require_once MDCAT_PLATFORM_PATH . 'modules/collections/class-collections-handler.php';

  if (is_admin()) {
-     require_once MDCAT_PLATFORM_PATH . 'modules/subjects/class-subjects-handler.php';
      require_once MDCAT_PLATFORM_PATH . 'modules/subjects/class-subjects.php';
-     require_once MDCAT_PLATFORM_PATH . 'modules/chapters/class-chapters-handler.php';
      require_once MDCAT_PLATFORM_PATH . 'modules/chapters/class-chapters.php';
-     require_once MDCAT_PLATFORM_PATH . 'modules/collections/class-collections-handler.php';
      require_once MDCAT_PLATFORM_PATH . 'modules/collections/class-collections.php';
```

### Phase 2A Deliverables

| # | Deliverable | Description |
|---|------------|-------------|
| 1 | Fix `class-loader.php` | Move handler `require_once` outside `is_admin()` |
| 2 | Create `api/controllers/class-rest-content-controller.php` | 6 read-only endpoints |
| 3 | Update `api/class-api-loader.php` | Load + register Content Controller routes |
| 4 | Test all 6 endpoints | Via curl/Postman against staging |

### Phase 2A Endpoints (6 total)

| Route | Service Method | Notes |
|-------|---------------|-------|
| `GET /subjects` | `Subjects_Handler::get_subjects()` | Filter to only active, add chapter count |
| `GET /subjects/{id}` | `Subjects_Handler::get_subject()` + chapters | Include nested chapters |
| `GET /chapters` | `Chapters_Handler::get_chapters()` | Support `?subject_id=` filter |
| `GET /chapters/{id}` | `Chapters_Handler::get_chapter()` + collections | Include nested collections |
| `GET /collections` | `Collections_Handler::get_collections()` | Support `?chapter_id=` filter, only active |
| `GET /collections/{id}` | `Collections_Handler::get_collection()` | Metadata only, no questions |

### Phase 2A Does NOT Include

- Dashboard, quiz, analytics, gamification, revision, enrollment, notification, or profile endpoints
- Any write operations
- Any new service classes
- Any database schema changes

### Estimated Effort

1 session — all 6 endpoints are thin wrappers around existing handler query methods.
