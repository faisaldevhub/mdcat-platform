# Phase 2 — Smoke Test Plan

> **Generated from implementation source code.**
> Verified against: `api/controllers/*.php`, `api/class-api-loader.php`, `api/auth/*.php`

---

## Overview

| Item | Value |
|------|-------|
| Total Endpoints | 33 |
| Controllers | 8 |
| Namespace | `mdcat/v1` |
| Base URL | `/wp-json/mdcat/v1` |
| Auth Method | JWT Bearer Token (HS256) |
| Access Token TTL | 24 hours (86400s, filterable) |
| Refresh Token TTL | 30 days (2592000s, filterable) |

---

## Pre-Test Checklist

- [ ] WordPress installed on Hostinger
- [ ] MDCAT Platform plugin activated
- [ ] `AUTH_KEY` defined in `wp-config.php` (JWT signing secret)
- [ ] Permalinks set to anything other than "Plain" (REST API requirement)
- [ ] Test student account created (role: subscriber)
- [ ] At least 1 subject with chapters and collections exists
- [ ] At least 1 collection has questions loaded
- [ ] At least 1 completed quiz attempt exists for the test student
- [ ] At least 1 notification exists for the test student
- [ ] At least 1 bookmark exists for the test student
- [ ] cURL or Postman available for testing

---

## Auth Headers Reference

```
# Public endpoints (login, refresh):
Content-Type: application/json

# Authenticated endpoints:
Content-Type: application/json
Authorization: Bearer {access_token}
```

---

## Endpoint Test Matrix

---

### Module 1: Auth

#### 1.1 `POST /auth/login`

**Permission:** `check_public_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid email + password | 200 | `{ success: true, data: { user: {...}, access_token, refresh_token, expires_in } }` |
| 2 | Wrong password | 401 | `{ success: false, code: "invalid_credentials" }` |
| 3 | Non-existent email | 401 | `{ success: false, code: "invalid_credentials" }` |
| 4 | Missing email field | 400 | WordPress param validation error |
| 5 | Missing password field | 400 | WordPress param validation error |
| 6 | 6 failed attempts same email within 15 min | 429 | `{ success: false, code: "rate_limit_exceeded" }` |

#### 1.2 `POST /auth/refresh`

**Permission:** `check_public_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid refresh token | 200 | `{ success: true, data: { access_token, refresh_token, expires_in } }` |
| 2 | Expired refresh token | 401 | Error with expired token code |
| 3 | Access token used as refresh | 401 | Token type mismatch error |
| 4 | Missing refresh_token field | 400 | WordPress param validation error |
| 5 | Malformed JWT string | 401 | Invalid token error |

#### 1.3 `POST /auth/logout`

**Permission:** `check_student_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid access token | 200 | `{ success: true }` |
| 2 | No Authorization header | 401 | `{ success: false, code: "missing_token" }` |
| 3 | Already-logged-out token | 401 | Token blacklisted / invalid |

#### 1.4 `GET /auth/me`

**Permission:** `check_student_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid access token | 200 | `{ success: true, data: { id, display_name, email, role, avatar_url, registered_at } }` |
| 2 | No Authorization header | 401 | `{ success: false, code: "missing_token" }` |
| 3 | Expired access token | 401 | `{ success: false, code: "token_expired" }` |

---

### Module 2: Content

All 6 endpoints use permission: `check_dashboard_access`

#### 2.1 `GET /subjects`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid token | 200 | `{ success: true, data: { subjects: [...] } }` |
| 2 | No token | 401 | Missing token error |

#### 2.2 `GET /subjects/{id}`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid subject ID | 200 | `{ success: true, data: { id, name, description, ... } }` |
| 2 | Non-existent ID (999999) | 404 | Not found error |
| 3 | No token | 401 | Missing token error |

#### 2.3 `GET /chapters`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | No filter | 200 | All chapters returned |
| 2 | With `?subject_id=1` | 200 | Only chapters for subject 1 |
| 3 | No token | 401 | Missing token error |

#### 2.4 `GET /chapters/{id}`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid chapter ID | 200 | Single chapter object |
| 2 | Non-existent ID | 404 | Not found error |

#### 2.5 `GET /collections`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | No filter | 200 | All collections returned |
| 2 | With `?chapter_id=1` | 200 | Only collections for chapter 1 |
| 3 | No token | 401 | Missing token error |

#### 2.6 `GET /collections/{id}`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid collection ID | 200 | Single collection object |
| 2 | Non-existent ID | 404 | Not found error |

---

### Module 3: Dashboard

All 4 endpoints use permission: `check_dashboard_access`

#### 3.1 `GET /dashboard/stats`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid token (student with attempts) | 200 | `{ data: { total_attempts, total_questions, correct_answers, wrong_answers, overall_accuracy, bookmarked_count, weak_topic_count } }` |
| 2 | Valid token (new student, no data) | 200 | All values are 0 |
| 3 | No token | 401 | Missing token error |

#### 3.2 `GET /dashboard/progress`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid token | 200 | `{ data: { overall: {...}, subjects: [...], chapters: [...] } }` |
| 2 | No token | 401 | Missing token error |

#### 3.3 `GET /dashboard/continue-learning`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with uncompleted collections | 200 | `{ data: { collection_id, collection_title, chapter_id, ... } }` |
| 2 | Student who completed everything | 200 | `{ data: { curriculum_completed: true, collection_id: null, ... } }` |
| 3 | No token | 401 | Missing token error |

#### 3.4 `GET /dashboard/study-plan`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid token | 200 | `{ data: { priority_topics, weak_subjects, continue_learning, revision_recommendations, daily_plan } }` |
| 2 | No token | 401 | Missing token error |

---

### Module 4: Quiz

#### 4.1 `POST /quiz/start`

**Permission:** `check_quiz_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid `collection_id` | 200 | `{ data: { attempt_id, collection_id, total_questions, started_at } }` |
| 2 | Non-existent `collection_id` | 400/404 | Collection not found error |
| 3 | Missing `collection_id` | 400 | Validation error |
| 4 | No token | 401 | Missing token error |

#### 4.2 `GET /quiz/{id}/questions`

**Permission:** `check_attempt_owner`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Own attempt ID | 200 | `{ data: { attempt_id, collection_id, questions: [...] } }` |
| 2 | Another student's attempt ID | 403 | Ownership denied |
| 3 | Non-existent attempt ID | 403/404 | Not found / denied |
| 4 | No token | 401 | Missing token error |

#### 4.3 `POST /quiz/{id}/answer`

**Permission:** `check_attempt_owner`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid `question_id` + `selected_option` | 200 | `{ data: { question_id, selected_option, is_correct, correct_option } }` |
| 2 | Missing `question_id` | 400 | Validation error |
| 3 | Missing `selected_option` | 400 | Validation error |
| 4 | Question not in collection | 400 | Invalid question error |
| 5 | Another student's attempt | 403 | Ownership denied |

#### 4.4 `POST /quiz/{id}/complete`

**Permission:** `check_attempt_owner`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Own in-progress attempt | 200 | `{ data: { attempt_id, total_questions, correct_answers, wrong_answers, score_percentage, time_taken, completed_at, gamification: {...} } }` |
| 2 | Already-completed attempt | 400 | Already completed error |
| 3 | Another student's attempt | 403 | Ownership denied |

#### 4.5 `GET /quiz/{id}/result`

**Permission:** `check_attempt_owner`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Own completed attempt | 200 | `{ data: { attempt_id, total_questions, correct_answers, wrong_answers, score_percentage, time_taken, completed_at } }` |
| 2 | Own in-progress attempt | 400 | Not completed error |
| 3 | Another student's attempt | 403 | Ownership denied |

#### 4.6 `GET /quiz/{id}/review`

**Permission:** `check_attempt_owner`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Own completed attempt | 200 | `{ data: { attempt_id, questions: [{ question_id, question, options, correct_option, selected_option, is_correct, explanation, difficulty }] } }` |
| 2 | Another student's attempt | 403 | Ownership denied |

#### 4.7 `GET /quiz/history`

**Permission:** `check_dashboard_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Default (no params) | 200 | `{ data: { items: [...], page: 1, per_page: 20, total, total_pages } }` |
| 2 | `?page=2` | 200 | Page 2 of results |
| 3 | `?per_page=5` | 200 | 5 items per page |
| 4 | `?per_page=200` (exceeds max) | 200 | Capped to 100 per page |
| 5 | No token | 401 | Missing token error |

---

### Module 5: Analytics

#### 5.1 `GET /analytics/performance`

**Permission:** `check_analytics_access`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with attempts | 200 | `{ data: { subject_performance: [...], chapter_performance: [...] } }` |
| 2 | New student (no attempts) | 200 | Both arrays empty |
| 3 | No token | 401 | Missing token error |

---

### Module 6: Revision

All 3 endpoints use permission: `check_revision_access`

#### 6.1 `GET /revision/bookmarks`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with bookmarks | 200 | `{ data: { questions: [...] } }` |
| 2 | Student with no bookmarks | 200 | `{ data: { questions: [] } }` |
| 3 | No token | 401 | Missing token error |

#### 6.2 `POST /revision/bookmarks/toggle`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Toggle on (not bookmarked) | 200 | `{ data: { question_id, is_bookmarked: true } }` |
| 2 | Toggle off (already bookmarked) | 200 | `{ data: { question_id, is_bookmarked: false } }` |
| 3 | Missing `question_id` | 400 | Validation error |
| 4 | No token | 401 | Missing token error |

#### 6.3 `GET /revision/wrong-questions`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with wrong answers | 200 | `{ data: { questions: [...] } }` — ordered by last_seen_at DESC, wrong_count DESC |
| 2 | Student with no wrong answers | 200 | `{ data: { questions: [] } }` |
| 3 | No token | 401 | Missing token error |

---

### Module 7: Gamification

All 5 endpoints use permission: `check_gamification_access`

#### 7.1 `GET /gamification/streak`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with activity | 200 | `{ data: { current_streak, longest_streak, total_active_days, last_active_date } }` |
| 2 | New student (no activity) | 200 | All 0, `last_active_date: null` |
| 3 | No token | 401 | Missing token error |

#### 7.2 `GET /gamification/xp`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with XP | 200 | `{ data: { total_xp, current_level, xp_in_level, xp_for_next_level, progress_percentage, is_max_level, recent_transactions: [...] } }` |
| 2 | New student (no XP) | 200 | `total_xp: 0`, `current_level: 1`, empty transactions |
| 3 | No token | 401 | Missing token error |

#### 7.3 `GET /gamification/badges`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Valid token | 200 | `{ data: { badges: [...] } }` — always 12 items (earned + locked) |
| 2 | Verify locked badge has `earned: false`, `earned_at: null` | 200 | Confirmed |
| 3 | No token | 401 | Missing token error |

#### 7.4 `GET /gamification/achievements`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with achievements | 200 | `{ data: { achievements: [...] } }` — only earned, ordered by earned_at DESC |
| 2 | New student | 200 | `{ data: { achievements: [] } }` |
| 3 | No token | 401 | Missing token error |

#### 7.5 `GET /gamification/leaderboard`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Default (no params) | 200 | `{ data: { type: "weekly", period_label, rankings: [...], current_user: {...} } }` |
| 2 | `?type=all_time` | 200 | `type: "all_time"`, `period_label: "All Time"` |
| 3 | `?type=monthly` | 200 | `type: "monthly"` |
| 4 | `?type=invalid_value` | 200 | Falls back to `type: "all_time"` |
| 5 | `?limit=5` | 200 | Max 5 students in rankings |
| 6 | `?limit=200` (exceeds max) | 200 | Capped to 100 (`MAX_LEADERBOARD_LIMIT`) |
| 7 | No token | 401 | Missing token error |
| 8 | Verify `current_user` rank is accurate even if outside top N | 200 | Rank uses independent subquery |

---

### Module 8: Notifications

All 3 endpoints use permission: `check_student_access`

#### 8.1 `GET /notifications`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Default (no page param) | 200 | `{ data: { notifications: [...], unread_count, page: 1, per_page: 15 } }` |
| 2 | `?page=2` | 200 | Page 2, same unread_count (global) |
| 3 | Page beyond data | 200 | `notifications: []` (empty), unread_count still correct |
| 4 | No token | 401 | Missing token error |

#### 8.2 `POST /notifications/{id}/read`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Own unread notification ID | 200 | `{ data: { unread_count } }` — count decreased |
| 2 | Own already-read notification | 200 | `{ data: { unread_count } }` — count unchanged |
| 3 | Another student's notification ID | 200 | Silent success (0 rows affected), own unread_count returned |
| 4 | ID = 0 | 400 | `{ code: "missing_id" }` |
| 5 | No token | 401 | Missing token error |

#### 8.3 `POST /notifications/read-all`

| # | Test Case | Expected Status | Expected Result |
|:-:|-----------|:---------------:|-----------------|
| 1 | Student with unread notifications | 200 | `{ data: { unread_count: 0 } }` |
| 2 | Student with no unread | 200 | `{ data: { unread_count: 0 } }` |
| 3 | No token | 401 | Missing token error |

---

## End-to-End User Journey Tests

### Journey 1: Login Flow

```
1. POST /auth/login       → Get access_token + refresh_token
2. GET  /auth/me           → Verify user profile with access_token
3. POST /auth/refresh      → Get new token pair using refresh_token
4. GET  /auth/me           → Verify new access_token works
5. POST /auth/logout       → Invalidate tokens
6. GET  /auth/me           → Confirm 401 (token blacklisted)
```

**Pass criteria:** Steps 1–5 return 200. Step 6 returns 401.

### Journey 2: Content Discovery

```
1. GET /subjects           → List all subjects
2. GET /subjects/{id}      → Pick first subject, get details
3. GET /chapters?subject_id={id} → List chapters for that subject
4. GET /chapters/{id}      → Pick first chapter, get details
5. GET /collections?chapter_id={id} → List collections for that chapter
6. GET /collections/{id}   → Pick first collection, get details
```

**Pass criteria:** All return 200. Filtering narrows results correctly.

### Journey 3: Quiz Lifecycle

```
1. POST /quiz/start        → { collection_id } → Get attempt_id
2. GET  /quiz/{id}/questions → Load questions (verify sort_order ASC, id ASC)
3. POST /quiz/{id}/answer   → Submit answer for first question
4. POST /quiz/{id}/answer   → Submit answer for remaining questions
5. POST /quiz/{id}/complete → Complete quiz → Verify gamification in response
6. GET  /quiz/{id}/result   → Get result summary
7. GET  /quiz/{id}/review   → Get full review with correct answers
8. GET  /quiz/history       → Verify new attempt appears in history
```

**Pass criteria:** All return 200. Step 5 includes `gamification` object. Step 8 shows the new attempt.

### Journey 4: Dashboard

```
1. GET /dashboard/stats             → Verify stats reflect completed quiz
2. GET /dashboard/progress          → Verify overall + subject + chapter completion
3. GET /dashboard/continue-learning → Verify next collection recommendation
4. GET /dashboard/study-plan        → Verify all 5 recommendation sections present
```

**Pass criteria:** All return 200. Stats reflect quiz data from Journey 3.

### Journey 5: Revision

```
1. POST /revision/bookmarks/toggle  → Bookmark a question → is_bookmarked: true
2. GET  /revision/bookmarks         → Verify question appears
3. POST /revision/bookmarks/toggle  → Un-bookmark same question → is_bookmarked: false
4. GET  /revision/bookmarks         → Verify question removed
5. GET  /revision/wrong-questions   → Verify wrong answers from Journey 3 appear
```

**Pass criteria:** Toggle is idempotent. Wrong questions reflect actual incorrect answers.

### Journey 6: Gamification

```
1. GET /gamification/streak       → Verify streak reflects today's quiz activity
2. GET /gamification/xp           → Verify XP earned from quiz
3. GET /gamification/badges       → Verify 12 badges returned (check earned status)
4. GET /gamification/achievements → Verify earned achievements
5. GET /gamification/leaderboard  → Verify current user appears in rankings or current_user
```

**Pass criteria:** All return 200. Streak and XP reflect quiz activity.

### Journey 7: Notifications

```
1. GET  /notifications            → List notifications (verify unread_count)
2. POST /notifications/{id}/read  → Mark first notification as read
3. GET  /notifications            → Verify unread_count decreased by 1
4. POST /notifications/read-all   → Mark all as read
5. GET  /notifications            → Verify unread_count is 0
```

**Pass criteria:** unread_count decrements correctly. read-all zeroes it.

---

## Security Tests

### Authentication

| # | Test | Method | Route | Expected |
|:-:|------|--------|-------|----------|
| 1 | No Authorization header | GET | `/auth/me` | 401 `missing_token` |
| 2 | `Authorization: Bearer invalid.jwt.here` | GET | `/auth/me` | 401 `invalid_token` |
| 3 | Expired access token | GET | `/auth/me` | 401 `token_expired` |
| 4 | Refresh token used as access token | GET | `/auth/me` | 401 (type mismatch) |
| 5 | Access token used as refresh token | POST | `/auth/refresh` | 401 (type mismatch) |
| 6 | Token after logout (blacklisted) | GET | `/auth/me` | 401 |

### Suspension

| # | Test | Expected |
|:-:|------|----------|
| 1 | Suspend test student in WP admin | — |
| 2 | Any authenticated endpoint | 403 `account_suspended` |
| 3 | Re-activate student | — |
| 4 | Same endpoint | 200 (access restored) |

### Attempt Ownership

| # | Test | Expected |
|:-:|------|----------|
| 1 | Create attempt with Student A | 200 |
| 2 | Access attempt with Student B's token | 403 |
| 3 | Access attempt with Student A's token | 200 |

### Rate Limiting

| # | Test | Expected |
|:-:|------|----------|
| 1 | 5 failed login attempts (same email) | 6th attempt → 429 |
| 2 | Wait 15 minutes or clear transient | Login succeeds again |
| 3 | 20 total attempts (same IP) | 21st attempt → 429 |

### CORS

| # | Test | Expected |
|:-:|------|----------|
| 1 | OPTIONS preflight request | 200 with CORS headers |
| 2 | Request from allowed origin | Response includes `Access-Control-Allow-Origin` |
| 3 | Request from disallowed origin | No CORS header or blocked |

---

## Pass/Fail Checklist

### Auth Module
- [ ] Login with valid credentials returns tokens
- [ ] Login with wrong password returns 401
- [ ] Login rate limiting works (6th attempt blocked)
- [ ] Refresh with valid refresh token returns new pair
- [ ] Refresh with access token is rejected
- [ ] Logout invalidates token
- [ ] Me returns user profile
- [ ] Me with expired token returns 401

### Content Module
- [ ] Subjects list returns all subjects
- [ ] Subject detail returns single subject
- [ ] Chapters list supports subject_id filter
- [ ] Chapter detail returns single chapter
- [ ] Collections list supports chapter_id filter
- [ ] Collection detail returns single collection
- [ ] Non-existent ID returns 404

### Dashboard Module
- [ ] Stats returns aggregate quiz data
- [ ] Progress returns overall + subjects + chapters
- [ ] Continue-learning returns next collection or curriculum_completed
- [ ] Study-plan returns all 5 recommendation sections

### Quiz Module
- [ ] Start creates new attempt
- [ ] Questions returned in sort_order ASC, id ASC
- [ ] Answer saves and returns is_correct + correct_option
- [ ] Complete calculates score and returns gamification
- [ ] Result returns score for completed attempt
- [ ] Review returns all questions with correct answers
- [ ] History is paginated (default 20, max 100)
- [ ] Attempt ownership enforced (another student gets 403)

### Analytics Module
- [ ] Performance returns subject + chapter analytics
- [ ] New student gets empty arrays

### Revision Module
- [ ] Bookmarks list returns bookmarked questions
- [ ] Toggle on returns is_bookmarked: true
- [ ] Toggle off returns is_bookmarked: false
- [ ] Wrong questions returns incorrect answers
- [ ] Wrong questions ordered by last_seen_at DESC

### Gamification Module
- [ ] Streak returns current + longest + total + last_active_date
- [ ] XP returns level progress + recent transactions
- [ ] Badges returns all 12 (earned + locked)
- [ ] Achievements returns only earned
- [ ] Leaderboard default type is "weekly"
- [ ] Leaderboard limit capped at 100
- [ ] Leaderboard current_user rank is independent
- [ ] Invalid leaderboard type falls back to "all_time"

### Notifications Module
- [ ] GET returns paginated feed (15 per page)
- [ ] unread_count is global across all pages
- [ ] Mark single read decrements unread_count
- [ ] Mark all read sets unread_count to 0
- [ ] Another student's notification silently unaffected

### Security
- [ ] Missing JWT → 401
- [ ] Invalid JWT → 401
- [ ] Expired JWT → 401
- [ ] Suspended student → 403
- [ ] Attempt ownership → 403 for non-owner
- [ ] Login rate limiting → 429
- [ ] CORS preflight → 200 with headers
