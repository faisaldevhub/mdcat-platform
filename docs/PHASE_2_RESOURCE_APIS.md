# Phase 2 — Resource REST APIs

> **Status:** Planning  
> **Depends on:** Phase 1 (API Foundation) — Complete ✅  
> **Target:** Next.js headless frontend

---

## Table of Contents

1. [Content APIs](#1-content-apis)
2. [Dashboard APIs](#2-dashboard-apis)
3. [Quiz APIs](#3-quiz-apis)
4. [Revision APIs](#4-revision-apis)
5. [Analytics APIs](#5-analytics-apis)
6. [Gamification APIs](#6-gamification-apis)
7. [Enrollment & Access APIs](#7-enrollment--access-apis)
8. [Profile APIs](#8-profile-apis)
9. [Notification APIs](#9-notification-apis)
10. [AJAX to REST Migration Map](#ajax-to-rest-migration-map)
11. [Next.js Screen → API Mapping](#nextjs-screen--api-mapping)
12. [Implementation Order](#implementation-order)
13. [Final Summary](#final-summary)

---

## 1. Content APIs

### Controller: `REST_Content_Controller`

Content is read-only for students. The admin CRUD operations (create/update/delete) remain in the existing WordPress admin interface.

---

#### 1.1 `GET /subjects`

| Property | Value |
|----------|-------|
| **Purpose** | List all subjects |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Subjects_Handler::get_subjects()` |
| **Tables** | `mdcat_subjects` |
| **Dependencies** | Access_Control_Service |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Subjects loaded.",
    "data": [
        {
            "id": 1,
            "name": "Biology",
            "slug": "biology",
            "description": "Complete Biology syllabus",
            "sort_order": 1,
            "chapter_count": 12,
            "collection_count": 45
        }
    ]
}
```

---

#### 1.2 `GET /subjects/{id}`

| Property | Value |
|----------|-------|
| **Purpose** | Get a single subject with its chapters |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Subjects_Handler::get_subject()`, `Chapters_Handler::get_chapters()` |
| **Tables** | `mdcat_subjects`, `mdcat_chapters` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Subject loaded.",
    "data": {
        "id": 1,
        "name": "Biology",
        "slug": "biology",
        "description": "Complete Biology syllabus",
        "chapters": [
            {
                "id": 1,
                "name": "Cell Biology",
                "slug": "cell-biology",
                "sort_order": 1,
                "collection_count": 5
            }
        ]
    }
}
```

---

#### 1.3 `GET /chapters`

| Property | Value |
|----------|-------|
| **Purpose** | List chapters, optionally filtered by subject |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Chapters_Handler::get_chapters()` |
| **Tables** | `mdcat_chapters`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `subject_id` | int (query) | ❌ | All subjects |

**Response:**
```json
{
    "success": true,
    "message": "Chapters loaded.",
    "data": [
        {
            "id": 1,
            "name": "Cell Biology",
            "subject_id": 1,
            "subject_name": "Biology",
            "sort_order": 1,
            "collection_count": 5
        }
    ]
}
```

---

#### 1.4 `GET /chapters/{id}`

| Property | Value |
|----------|-------|
| **Purpose** | Get a single chapter with its collections |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Chapters_Handler::get_chapter()`, `Collections_Handler::get_collections()` |
| **Tables** | `mdcat_chapters`, `mdcat_collections`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Chapter loaded.",
    "data": {
        "id": 1,
        "name": "Cell Biology",
        "subject_id": 1,
        "subject_name": "Biology",
        "collections": [
            {
                "id": 1,
                "title": "Cell Biology - Set 1",
                "type": "practice",
                "status": "published",
                "question_count": 20,
                "sort_order": 1
            }
        ]
    }
}
```

---

#### 1.5 `GET /collections`

| Property | Value |
|----------|-------|
| **Purpose** | List collections, optionally filtered by chapter |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Collections_Handler::get_collections()` |
| **Tables** | `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `chapter_id` | int (query) | ❌ | All chapters |
| `type` | string (query) | ❌ | All types |
| `status` | string (query) | ❌ | `published` |

**Response:**
```json
{
    "success": true,
    "message": "Collections loaded.",
    "data": [
        {
            "id": 1,
            "title": "Cell Biology - Set 1",
            "chapter_id": 1,
            "chapter_name": "Cell Biology",
            "subject_name": "Biology",
            "type": "practice",
            "status": "published",
            "question_count": 20,
            "sort_order": 1
        }
    ]
}
```

---

#### 1.6 `GET /collections/{id}`

| Property | Value |
|----------|-------|
| **Purpose** | Get a single collection with metadata (no questions) |
| **Auth** | Student JWT Required |
| **Permission** | `check_quiz_access` |
| **Service** | `Collections_Handler::get_collection()` |
| **Tables** | `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Collection loaded.",
    "data": {
        "id": 1,
        "title": "Cell Biology - Set 1",
        "chapter_id": 1,
        "chapter_name": "Cell Biology",
        "subject_id": 1,
        "subject_name": "Biology",
        "type": "practice",
        "status": "published",
        "question_count": 20
    }
}
```

---

## 2. Dashboard APIs

### Controller: `REST_Dashboard_Controller`

---

#### 2.1 `GET /dashboard`

| Property | Value |
|----------|-------|
| **Purpose** | Full aggregated dashboard for the student |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Dashboard_Service::get_dashboard_stats()`, `get_recent_activity()`, `get_performance_snapshot()`, `get_streak_data()`, `get_subject_progress()`, `get_chapter_progress()`, `get_overall_progress()`, `get_continue_learning()`, `get_engagement_data()`, `get_study_recommendations()`, `get_notification_summary()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_subjects`, `mdcat_chapters`, `mdcat_collections`, `mdcat_questions`, `mdcat_daily_activity`, `mdcat_xp_transactions`, `mdcat_user_rewards`, `mdcat_notifications` |
| **Dependencies** | Dashboard_Service, Progress_Service, Streak_Service, XP_Service, Badge_Service, Achievement_Service, Performance_Analytics, Recommendation_Service, Notification_Service |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Dashboard loaded.",
    "data": {
        "stats": {
            "total_quizzes": 45,
            "total_questions_attempted": 900,
            "average_score": 72.5,
            "total_time_spent": 1800
        },
        "recent_activity": [],
        "performance_snapshot": {},
        "streak": {
            "current_streak": 5,
            "longest_streak": 12,
            "is_active_today": true
        },
        "subject_progress": [],
        "chapter_progress": [],
        "overall_progress": {},
        "continue_learning": [],
        "engagement": {
            "xp": {},
            "badges": [],
            "achievements": []
        },
        "study_plan": {},
        "notification_summary": {
            "unread_count": 3,
            "recent": []
        }
    }
}
```

---

#### 2.2 `GET /dashboard/progress`

| Property | Value |
|----------|-------|
| **Purpose** | Detailed progress breakdown (lighter than full dashboard) |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Progress_Service::get_subject_completion()`, `get_chapter_completion()`, `get_overall_completion()` |
| **Tables** | `mdcat_attempts`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Progress loaded.",
    "data": {
        "overall": {
            "total_collections": 120,
            "completed_collections": 45,
            "completion_percentage": 37.5
        },
        "subjects": [],
        "chapters": []
    }
}
```

---

#### 2.3 `GET /dashboard/continue-learning`

| Property | Value |
|----------|-------|
| **Purpose** | Collections the student should attempt next |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Progress_Service::get_continue_learning()` |
| **Tables** | `mdcat_attempts`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Continue learning loaded.",
    "data": [
        {
            "collection_id": 15,
            "collection_title": "Genetics - Set 3",
            "chapter_name": "Genetics",
            "subject_name": "Biology",
            "question_count": 20,
            "reason": "not_attempted"
        }
    ]
}
```

---

#### 2.4 `GET /dashboard/study-plan`

| Property | Value |
|----------|-------|
| **Purpose** | AI-generated study recommendations |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Recommendation_Service::get_study_plan()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects`, `mdcat_daily_activity` |
| **Dependencies** | Performance_Analytics, Progress_Service, Streak_Service |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Study plan loaded.",
    "data": {
        "priority_topics": [],
        "weak_subjects": [],
        "continue_learning": [],
        "revision_recommendations": [],
        "daily_plan": []
    }
}
```

---

## 3. Quiz APIs

### Controller: `REST_Quiz_Controller`

---

#### 3.1 `POST /quiz/start`

| Property | Value |
|----------|-------|
| **Purpose** | Start a new quiz attempt for a collection |
| **Auth** | Student JWT Required |
| **Permission** | `check_quiz_access` (validates collection access) |
| **Service** | `Quiz_Engine::start_attempt()` |
| **Tables** | `mdcat_attempts`, `mdcat_collections`, `mdcat_questions` |
| **Dependencies** | Access_Control_Service |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `collection_id` | int (body) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Quiz started.",
    "data": {
        "attempt_id": 123,
        "total_questions": 20,
        "total_time": 30,
        "status": "in_progress",
        "started_at": "2026-06-24 12:00:00"
    }
}
```

---

#### 3.2 `GET /quiz/{attempt_id}/questions`

| Property | Value |
|----------|-------|
| **Purpose** | Get all questions for an active attempt |
| **Auth** | Student JWT Required |
| **Permission** | `check_attempt_owner` |
| **Service** | `Quiz_Engine::get_attempt_context()`, `Quiz_Engine::get_collection_questions()` |
| **Tables** | `mdcat_attempts`, `mdcat_questions`, `mdcat_collections` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `attempt_id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Questions loaded.",
    "data": {
        "attempt_id": 123,
        "collection_id": 15,
        "questions": [
            {
                "id": 501,
                "question_text": "Which organelle is responsible for ATP production?",
                "option_a": "Ribosome",
                "option_b": "Mitochondria",
                "option_c": "Golgi apparatus",
                "option_d": "Lysosome",
                "explanation": null
            }
        ]
    }
}
```

> **Note:** `correct_option` and `explanation` are NOT sent during active quiz. They are only visible in the review endpoint.

---

#### 3.3 `POST /quiz/{attempt_id}/answer`

| Property | Value |
|----------|-------|
| **Purpose** | Submit an answer for a question |
| **Auth** | Student JWT Required |
| **Permission** | `check_attempt_owner` |
| **Service** | `Quiz_Engine::validate_question_for_attempt()`, `Quiz_Engine::save_answer()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_questions` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `attempt_id` | int (URL) | ✅ |
| `question_id` | int (body) | ✅ |
| `selected_option` | string (body) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Answer saved.",
    "data": {
        "attempt_id": 123,
        "question_id": 501,
        "selected_option": "b",
        "is_correct": true,
        "answered_at": "2026-06-24 12:01:30"
    }
}
```

---

#### 3.4 `POST /quiz/{attempt_id}/complete`

| Property | Value |
|----------|-------|
| **Purpose** | Complete a quiz attempt and calculate score |
| **Auth** | Student JWT Required |
| **Permission** | `check_attempt_owner` |
| **Service** | `Quiz_Engine::complete_attempt()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_daily_activity`, `mdcat_xp_transactions`, `mdcat_user_rewards` |
| **Dependencies** | Streak_Service, XP_Service, Badge_Service, Achievement_Service (via `mdcat_quiz_completion_response` filter) |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `attempt_id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Quiz completed.",
    "data": {
        "attempt_id": 123,
        "collection_id": 15,
        "total_questions": 20,
        "answered_questions": 20,
        "correct_answers": 15,
        "wrong_answers": 5,
        "score": 75.0,
        "status": "completed",
        "time_taken": 1200,
        "completed_at": "2026-06-24 12:20:00"
    }
}
```

---

#### 3.5 `GET /quiz/{attempt_id}/result`

| Property | Value |
|----------|-------|
| **Purpose** | Get the result for a completed attempt |
| **Auth** | Student JWT Required |
| **Permission** | `check_attempt_owner` |
| **Service** | `Quiz_Engine::get_attempt_result()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `attempt_id` | int (URL) | ✅ |

**Response:** Same structure as 3.4.

---

#### 3.6 `GET /quiz/{attempt_id}/review`

| Property | Value |
|----------|-------|
| **Purpose** | Detailed question-by-question review with correct answers |
| **Auth** | Student JWT Required |
| **Permission** | `check_attempt_owner` |
| **Service** | `Review_Service::get_attempt_review()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_questions`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `attempt_id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Review loaded.",
    "data": {
        "attempt_id": 123,
        "collection_title": "Cell Biology - Set 1",
        "chapter_name": "Cell Biology",
        "subject_name": "Biology",
        "score": 75.0,
        "questions": [
            {
                "id": 501,
                "question_text": "Which organelle is responsible for ATP production?",
                "option_a": "Ribosome",
                "option_b": "Mitochondria",
                "option_c": "Golgi apparatus",
                "option_d": "Lysosome",
                "correct_option": "b",
                "selected_option": "b",
                "is_correct": true,
                "explanation": "Mitochondria is the powerhouse of the cell...",
                "is_bookmarked": false
            }
        ]
    }
}
```

---

#### 3.7 `GET /quiz/history`

| Property | Value |
|----------|-------|
| **Purpose** | Paginated list of the student's completed attempts |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Attempt_History::get_user_attempt_history()` |
| **Tables** | `mdcat_attempts`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `page` | int (query) | ❌ | 1 |
| `per_page` | int (query) | ❌ | 20 |

**Response:**
```json
{
    "success": true,
    "message": "History loaded.",
    "data": {
        "items": [
            {
                "attempt_id": 123,
                "collection_id": 15,
                "collection_title": "Cell Biology - Set 1",
                "chapter_name": "Cell Biology",
                "subject_name": "Biology",
                "score": 75.0,
                "total_questions": 20,
                "correct_answers": 15,
                "status": "completed",
                "completed_at": "2026-06-24 12:20:00"
            }
        ],
        "pagination": {
            "page": 1,
            "per_page": 20,
            "total": 45,
            "total_pages": 3
        }
    }
}
```

---

## 4. Revision APIs

### Controller: `REST_Revision_Controller`

---

#### 4.1 `GET /revision/bookmarks`

| Property | Value |
|----------|-------|
| **Purpose** | Get all bookmarked questions |
| **Auth** | Student JWT Required |
| **Permission** | `check_revision_access` |
| **Service** | `Revision_Service::get_bookmarked_questions()` |
| **Tables** | `mdcat_bookmarks`, `mdcat_questions`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Bookmarks loaded.",
    "data": [
        {
            "question_id": 501,
            "question_text": "Which organelle is responsible for ATP production?",
            "option_a": "Ribosome",
            "option_b": "Mitochondria",
            "option_c": "Golgi apparatus",
            "option_d": "Lysosome",
            "correct_option": "b",
            "explanation": "Mitochondria is the powerhouse...",
            "collection_title": "Cell Biology - Set 1",
            "chapter_name": "Cell Biology",
            "subject_name": "Biology",
            "bookmarked_at": "2026-06-24 12:05:00"
        }
    ]
}
```

---

#### 4.2 `POST /revision/bookmarks/toggle`

| Property | Value |
|----------|-------|
| **Purpose** | Toggle bookmark on/off for a question |
| **Auth** | Student JWT Required |
| **Permission** | `check_revision_access` |
| **Service** | `Revision_Service::toggle_bookmark()` |
| **Tables** | `mdcat_bookmarks` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `question_id` | int (body) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Bookmark toggled.",
    "data": {
        "question_id": 501,
        "is_bookmarked": true
    }
}
```

---

#### 4.3 `GET /revision/wrong-questions`

| Property | Value |
|----------|-------|
| **Purpose** | Get all questions the student answered incorrectly |
| **Auth** | Student JWT Required |
| **Permission** | `check_revision_access` |
| **Service** | `Revision_Service::get_wrong_questions()` |
| **Tables** | `mdcat_attempt_answers`, `mdcat_questions`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Wrong questions loaded.",
    "data": [
        {
            "question_id": 502,
            "question_text": "What is the function of ribosomes?",
            "option_a": "Energy production",
            "option_b": "Protein synthesis",
            "option_c": "Cell division",
            "option_d": "DNA replication",
            "correct_option": "b",
            "selected_option": "a",
            "explanation": "Ribosomes translate mRNA...",
            "collection_title": "Cell Biology - Set 1",
            "chapter_name": "Cell Biology",
            "subject_name": "Biology"
        }
    ]
}
```

---

## 5. Analytics APIs

### Controller: `REST_Analytics_Controller`

---

#### 5.1 `GET /analytics/performance`

| Property | Value |
|----------|-------|
| **Purpose** | Full performance analytics (subject + chapter breakdown) |
| **Auth** | Student JWT Required |
| **Permission** | `check_analytics_access` |
| **Service** | `Performance_Analytics::get_subject_performance()`, `get_chapter_performance()` |
| **Tables** | `mdcat_attempts`, `mdcat_attempt_answers`, `mdcat_questions`, `mdcat_collections`, `mdcat_chapters`, `mdcat_subjects` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Performance analytics loaded.",
    "data": {
        "subject_performance": [
            {
                "subject_id": 1,
                "subject_name": "Biology",
                "total_questions": 200,
                "correct_answers": 150,
                "accuracy": 75.0,
                "total_attempts": 10
            }
        ],
        "chapter_performance": [
            {
                "chapter_id": 1,
                "chapter_name": "Cell Biology",
                "subject_name": "Biology",
                "total_questions": 60,
                "correct_answers": 48,
                "accuracy": 80.0,
                "total_attempts": 3
            }
        ]
    }
}
```

---

## 6. Gamification APIs

### Controller: `REST_Gamification_Controller`

---

#### 6.1 `GET /gamification/streak`

| Property | Value |
|----------|-------|
| **Purpose** | Get current streak summary |
| **Auth** | Student JWT Required |
| **Permission** | `check_streak_access` |
| **Service** | `Streak_Service::get_streak_summary()` |
| **Tables** | `mdcat_daily_activity` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Streak loaded.",
    "data": {
        "current_streak": 5,
        "longest_streak": 12,
        "is_active_today": true,
        "last_active_date": "2026-06-24",
        "total_active_days": 45
    }
}
```

---

#### 6.2 `GET /gamification/xp`

| Property | Value |
|----------|-------|
| **Purpose** | XP summary with level and progress |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `XP_Service::get_xp_summary()`, `get_recent_transactions()` |
| **Tables** | `mdcat_xp_transactions` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `recent_limit` | int (query) | ❌ | 10 |

**Response:**
```json
{
    "success": true,
    "message": "XP summary loaded.",
    "data": {
        "total_xp": 2500,
        "level": 5,
        "level_name": "Scholar",
        "xp_for_current_level": 2000,
        "xp_for_next_level": 3000,
        "progress_percentage": 50.0,
        "recent_transactions": [
            {
                "xp_amount": 50,
                "source": "quiz_completion",
                "description": "Completed Cell Biology - Set 1",
                "created_at": "2026-06-24 12:20:00"
            }
        ]
    }
}
```

---

#### 6.3 `GET /gamification/badges`

| Property | Value |
|----------|-------|
| **Purpose** | All badges with earned/locked status |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Badge_Service::get_badges_with_status()` |
| **Tables** | `mdcat_user_rewards` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Badges loaded.",
    "data": [
        {
            "slug": "first_quiz",
            "name": "First Steps",
            "description": "Complete your first quiz",
            "icon": "🎯",
            "earned": true,
            "earned_at": "2026-06-20 10:00:00"
        },
        {
            "slug": "perfect_score",
            "name": "Perfectionist",
            "description": "Score 100% on any quiz",
            "icon": "💎",
            "earned": false,
            "earned_at": null
        }
    ]
}
```

---

#### 6.4 `GET /gamification/achievements`

| Property | Value |
|----------|-------|
| **Purpose** | Achievements earned by the student |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Achievement_Service::get_user_achievements()` |
| **Tables** | `mdcat_user_rewards` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Achievements loaded.",
    "data": [
        {
            "slug": "biology_master",
            "name": "Biology Master",
            "description": "Complete all Biology collections",
            "icon": "🧬",
            "earned_at": "2026-06-22 15:30:00"
        }
    ]
}
```

---

#### 6.5 `GET /gamification/leaderboard`

| Property | Value |
|----------|-------|
| **Purpose** | Ranked leaderboard with the current student's position |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Leaderboard_Service::get_leaderboard_data()` |
| **Tables** | `mdcat_xp_transactions`, `wp_users` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `type` | string (query) | ❌ | `weekly` |
| `limit` | int (query) | ❌ | 20 |

**Allowed types:** `all_time`, `weekly`, `monthly`

**Response:**
```json
{
    "success": true,
    "message": "Leaderboard loaded.",
    "data": {
        "type": "weekly",
        "rankings": [
            {
                "rank": 1,
                "user_id": 42,
                "display_name": "Ali Khan",
                "avatar_url": "https://...",
                "xp": 850,
                "level": 7
            }
        ],
        "current_user": {
            "rank": 5,
            "xp": 620,
            "level": 5
        }
    }
}
```

---

## 7. Enrollment & Access APIs

### Controller: `REST_Enrollment_Controller`

---

#### 7.1 `POST /enrollment/submit`

| Property | Value |
|----------|-------|
| **Purpose** | Submit enrollment request (public — no auth) |
| **Auth** | Public |
| **Permission** | `check_public_access` |
| **Service** | `Enrollment_Service::create_request()` |
| **Tables** | `mdcat_enrollment_requests` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `full_name` | string (body) | ✅ |
| `email` | string (body) | ✅ |
| `phone` | string (body) | ✅ |
| `city` | string (body) | ✅ |
| `payment_screenshot` | file (body) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Enrollment request submitted. You will receive an email once approved.",
    "data": {
        "request_id": 456,
        "status": "pending"
    }
}
```

---

#### 7.2 `GET /enrollment/status`

| Property | Value |
|----------|-------|
| **Purpose** | Check enrollment status for an email |
| **Auth** | Public |
| **Permission** | `check_public_access` |
| **Service** | `Enrollment_Service::get_request_by_email()` |
| **Tables** | `mdcat_enrollment_requests` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `email` | string (query) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Status loaded.",
    "data": {
        "status": "pending",
        "submitted_at": "2026-06-24 10:00:00"
    }
}
```

---

#### 7.3 `GET /access/check`

| Property | Value |
|----------|-------|
| **Purpose** | Verify what features the student can access |
| **Auth** | Student JWT Required |
| **Permission** | `check_student_access` |
| **Service** | `Access_Control_Service::can_access_dashboard()`, `can_access_quiz()`, `can_access_analytics()`, `can_access_revision()`, `can_access_streak()` |
| **Tables** | None (uses user_meta + filters) |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Access checked.",
    "data": {
        "dashboard": true,
        "quiz": true,
        "analytics": true,
        "revision": true,
        "streak": true,
        "is_suspended": false
    }
}
```

---

## 8. Profile APIs

### Controller: `REST_Profile_Controller`

---

#### 8.1 `GET /profile`

| Property | Value |
|----------|-------|
| **Purpose** | Get full student profile (extends /auth/me) |
| **Auth** | Student JWT Required |
| **Permission** | `check_student_access` |
| **Service** | WordPress `get_userdata()`, `get_user_meta()` |
| **Tables** | `wp_users`, `wp_usermeta` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "Profile loaded.",
    "data": {
        "id": 42,
        "display_name": "Ali Khan",
        "email": "ali@example.com",
        "role": "subscriber",
        "avatar_url": "https://...",
        "registered_at": "2026-01-15 10:00:00",
        "city": "Lahore",
        "phone": "+923001234567"
    }
}
```

---

#### 8.2 `PUT /profile`

| Property | Value |
|----------|-------|
| **Purpose** | Update display name and password |
| **Auth** | Student JWT Required |
| **Permission** | `check_student_access` |
| **Service** | WordPress `wp_update_user()`, `update_user_meta()` |
| **Tables** | `wp_users`, `wp_usermeta` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `display_name` | string (body) | ❌ |
| `current_password` | string (body) | ❌ (required if changing password) |
| `new_password` | string (body) | ❌ |

**Response:**
```json
{
    "success": true,
    "message": "Profile updated.",
    "data": {
        "id": 42,
        "display_name": "Ali Khan Updated",
        "email": "ali@example.com"
    }
}
```

---

## 9. Notification APIs

### Controller: `REST_Notification_Controller`

---

#### 9.1 `GET /notifications`

| Property | Value |
|----------|-------|
| **Purpose** | Paginated list of notifications |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Notification_Service::get_notifications()` |
| **Tables** | `mdcat_notifications` |

**Parameters:**

| Param | Type | Required | Default |
|-------|------|:--------:|---------|
| `page` | int (query) | ❌ | 1 |
| `per_page` | int (query) | ❌ | 20 |

**Response:**
```json
{
    "success": true,
    "message": "Notifications loaded.",
    "data": {
        "items": [
            {
                "id": 100,
                "type": "badge_earned",
                "title": "New Badge Earned!",
                "message": "You earned the First Steps badge.",
                "is_read": false,
                "created_at": "2026-06-24 12:00:00"
            }
        ],
        "unread_count": 3,
        "pagination": {
            "page": 1,
            "per_page": 20,
            "total": 15,
            "total_pages": 1
        }
    }
}
```

---

#### 9.2 `POST /notifications/{id}/read`

| Property | Value |
|----------|-------|
| **Purpose** | Mark a single notification as read |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Notification_Service::mark_as_read()` |
| **Tables** | `mdcat_notifications` |

**Parameters:**

| Param | Type | Required |
|-------|------|:--------:|
| `id` | int (URL) | ✅ |

**Response:**
```json
{
    "success": true,
    "message": "Notification marked as read.",
    "data": null
}
```

---

#### 9.3 `POST /notifications/read-all`

| Property | Value |
|----------|-------|
| **Purpose** | Mark all notifications as read |
| **Auth** | Student JWT Required |
| **Permission** | `check_dashboard_access` |
| **Service** | `Notification_Service::mark_all_as_read()` |
| **Tables** | `mdcat_notifications` |

**Parameters:** None

**Response:**
```json
{
    "success": true,
    "message": "All notifications marked as read.",
    "data": null
}
```

---

## AJAX to REST Migration Map

| # | Existing AJAX Action | Existing Service | Proposed REST Endpoint | Notes |
|---|---------------------|-----------------|----------------------|-------|
| 1 | `mdcat_get_student_dashboard` | Dashboard_Service (aggregated) | `GET /dashboard` | Split into sub-endpoints for lighter fetches |
| 2 | `mdcat_get_subject_progress` | Progress_Service | `GET /dashboard/progress` | Part of progress sub-endpoint |
| 3 | `mdcat_get_chapter_progress` | Progress_Service | `GET /dashboard/progress` | Combined with subject progress |
| 4 | `mdcat_get_overall_progress` | Progress_Service | `GET /dashboard/progress` | Combined |
| 5 | `mdcat_get_continue_learning` | Progress_Service | `GET /dashboard/continue-learning` | Standalone endpoint |
| 6 | `mdcat_get_study_plan` | Recommendation_Service | `GET /dashboard/study-plan` | Full study plan |
| 7 | `mdcat_get_daily_plan` | Recommendation_Service | `GET /dashboard/study-plan` | Subset of study plan |
| 8 | `mdcat_start_quiz` | Quiz_Engine | `POST /quiz/start` | Direct 1:1 migration |
| 9 | `mdcat_get_questions` | Quiz_Engine | `GET /quiz/{attempt_id}/questions` | RESTful URL pattern |
| 10 | `mdcat_save_answer` | Quiz_Engine | `POST /quiz/{attempt_id}/answer` | RESTful URL pattern |
| 11 | `mdcat_complete_quiz` | Quiz_Engine | `POST /quiz/{attempt_id}/complete` | Triggers gamification hooks |
| 12 | `mdcat_get_result` | Quiz_Engine | `GET /quiz/{attempt_id}/result` | RESTful URL pattern |
| 13 | `mdcat_get_attempt_history` | Attempt_History | `GET /quiz/history` | Paginated |
| 14 | `mdcat_get_attempt_review` | Review_Service | `GET /quiz/{attempt_id}/review` | Grouped under quiz controller |
| 15 | `mdcat_toggle_bookmark` | Revision_Service | `POST /revision/bookmarks/toggle` | Toggle pattern preserved |
| 16 | `mdcat_get_bookmarks` | Revision_Service | `GET /revision/bookmarks` | Direct migration |
| 17 | `mdcat_get_wrong_questions` | Revision_Service | `GET /revision/wrong-questions` | Direct migration |
| 18 | `mdcat_get_performance_analytics` | Performance_Analytics | `GET /analytics/performance` | Direct migration |
| 19 | `mdcat_get_streak_summary` | Streak_Service | `GET /gamification/streak` | Direct migration |
| 20 | `mdcat_get_xp_summary` | XP_Service | `GET /gamification/xp` | Direct migration |
| 21 | `mdcat_get_user_badges` | Badge_Service | `GET /gamification/badges` | Direct migration |
| 22 | `mdcat_get_user_achievements` | Achievement_Service | `GET /gamification/achievements` | Direct migration |
| 23 | `mdcat_get_leaderboard` | Leaderboard_Service | `GET /gamification/leaderboard` | Direct migration |
| 24 | `mdcat_get_notifications` | Notification_Service | `GET /notifications` | Paginated |
| 25 | `mdcat_mark_notification_read` | Notification_Service | `POST /notifications/{id}/read` | RESTful URL pattern |
| 26 | `mdcat_mark_all_notifications_read` | Notification_Service | `POST /notifications/read-all` | Direct migration |
| 27 | `mdcat_enrollment_submit` | Enrollment_Service | `POST /enrollment/submit` | Public endpoint with file upload |
| — | No AJAX equivalent | — | `GET /subjects` | New: content browsing |
| — | No AJAX equivalent | — | `GET /chapters` | New: content browsing |
| — | No AJAX equivalent | — | `GET /collections` | New: content browsing |
| — | No AJAX equivalent | — | `GET /enrollment/status` | New: status check |
| — | No AJAX equivalent | — | `GET /access/check` | New: access verification |
| — | No AJAX equivalent | — | `GET /profile` | New: extended profile |
| — | No AJAX equivalent | — | `PUT /profile` | New: profile update |

---

## Next.js Screen → API Mapping

### Public Screens (No Auth)

| Screen | APIs Required |
|--------|-------------|
| **Landing Page** | None (static content) |
| **Pricing** | None (static content) |
| **Enrollment Form** | `POST /enrollment/submit` |
| **Enrollment Status** | `GET /enrollment/status` |
| **Login** | `POST /auth/login` |

### Authenticated Screens

| Screen | APIs Required |
|--------|-------------|
| **Student Dashboard** | `GET /dashboard`, `GET /gamification/streak` |
| **Subjects List** | `GET /subjects`, `GET /dashboard/progress` |
| **Subject Detail** | `GET /subjects/{id}`, `GET /dashboard/progress` |
| **Chapter Detail** | `GET /chapters/{id}`, `GET /dashboard/progress` |
| **Collection Detail** | `GET /collections/{id}` |
| **Quiz Screen** | `POST /quiz/start`, `GET /quiz/{id}/questions`, `POST /quiz/{id}/answer` |
| **Quiz Complete** | `POST /quiz/{id}/complete` |
| **Results Screen** | `GET /quiz/{id}/result` |
| **Review Screen** | `GET /quiz/{id}/review`, `POST /revision/bookmarks/toggle` |
| **Attempt History** | `GET /quiz/history` |
| **Analytics Screen** | `GET /analytics/performance` |
| **Continue Learning** | `GET /dashboard/continue-learning` |
| **Study Planner** | `GET /dashboard/study-plan` |
| **Bookmarks** | `GET /revision/bookmarks` |
| **Wrong Questions** | `GET /revision/wrong-questions` |
| **Leaderboard** | `GET /gamification/leaderboard` |
| **Badges & Achievements** | `GET /gamification/badges`, `GET /gamification/achievements` |
| **XP & Level** | `GET /gamification/xp` |
| **Notifications** | `GET /notifications`, `POST /notifications/{id}/read`, `POST /notifications/read-all` |
| **Profile** | `GET /profile`, `GET /auth/me` |
| **Edit Profile** | `PUT /profile` |

### Common Layout APIs (Every Authenticated Page)

| Component | APIs |
|-----------|------|
| **Header** | `GET /auth/me` (cached), `GET /gamification/streak` (cached) |
| **Notification Bell** | `GET /notifications` (unread_count only) |
| **Token Refresh** | `POST /auth/refresh` (automatic) |

---

## Implementation Order

### Phase 2A — Content APIs

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Content Controller: subjects, chapters, collections | Low | Low |
| 6 endpoints (read-only CRUD) | | |
| Uses existing Handler classes directly | | |

**Dependencies:** None  
**Estimated effort:** 1 session  
**Recommended sequence:** Build controller → register routes → test all 6 endpoints

---

### Phase 2B — Dashboard APIs

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Dashboard Controller: full dashboard, progress, continue-learning, study-plan | Medium | Medium |
| 4 endpoints | | |
| Aggregates multiple services | | |

**Dependencies:** Content APIs (for collection references)  
**Estimated effort:** 1–2 sessions  
**Key risk:** Dashboard_Service loads data from 8+ services. Must ensure all services are loaded outside `is_admin()`.  
**Recommended sequence:** Audit service loading → Build controller → Test full dashboard → Test sub-endpoints

---

### Phase 2C — Quiz APIs

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Quiz Controller: start, questions, answer, complete, result, review, history | High | High |
| 7 endpoints (read + write) | | |
| Complex state machine (in_progress → completed) | | |

**Dependencies:** Content APIs, Dashboard APIs (for gamification hooks)  
**Estimated effort:** 2–3 sessions  
**Key risk:** Quiz_Engine has side effects (XP, streaks, badges on completion). The `mdcat_quiz_completion_response` filter triggers gamification processing.  
**Recommended sequence:** Start + questions → answer → complete → result → review → history

---

### Phase 2D — Analytics & Revision APIs

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Analytics Controller: performance | Low | Low |
| Revision Controller: bookmarks, wrong-questions, toggle | Low | Low |
| 4 endpoints | | |

**Dependencies:** Quiz APIs (needs attempt data to exist)  
**Estimated effort:** 1 session  
**Recommended sequence:** Analytics → Revision (bookmark toggle needs testing with review screen)

---

### Phase 2E — Gamification & Social APIs

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Gamification Controller: streak, xp, badges, achievements, leaderboard | Medium | Low |
| 5 endpoints | | |

**Dependencies:** Quiz APIs (gamification data is generated by quiz completion)  
**Estimated effort:** 1 session  
**Recommended sequence:** Streak → XP → Badges → Achievements → Leaderboard

---

### Phase 2F — Enrollment, Notifications & Profile

| Task | Complexity | Risk |
|------|:----------:|:----:|
| Enrollment Controller: submit, status | Medium | Medium |
| Notification Controller: list, mark-read, mark-all-read | Low | Low |
| Profile Controller: get, update | Low | Low |
| Access Check endpoint | Low | Low |
| 8 endpoints | | |

**Dependencies:** Auth (for profile)  
**Estimated effort:** 1–2 sessions  
**Key risk:** Enrollment submit requires file upload handling via REST API (multipart/form-data).  
**Recommended sequence:** Notifications → Profile → Enrollment → Access Check

---

## Final Summary

### Total Endpoint Count

| Category | Endpoints |
|----------|:---------:|
| Auth (Phase 1 — Complete) | 4 |
| Content | 6 |
| Dashboard | 4 |
| Quiz | 7 |
| Revision | 3 |
| Analytics | 1 |
| Gamification | 5 |
| Enrollment & Access | 3 |
| Profile | 2 |
| Notifications | 3 |
| **Total** | **38** |

### Controller Structure

```
api/controllers/
├── class-rest-auth-controller.php         ✅ Complete
├── class-rest-content-controller.php      Phase 2A
├── class-rest-dashboard-controller.php    Phase 2B
├── class-rest-quiz-controller.php         Phase 2C
├── class-rest-revision-controller.php     Phase 2D
├── class-rest-analytics-controller.php    Phase 2D
├── class-rest-gamification-controller.php Phase 2E
├── class-rest-enrollment-controller.php   Phase 2F
├── class-rest-notification-controller.php Phase 2F
├── class-rest-profile-controller.php      Phase 2F
└── class-rest-base-controller.php         ✅ Complete
```

### Route Namespace Structure

```
mdcat/v1/
├── auth/login           POST    ✅
├── auth/refresh         POST    ✅
├── auth/logout          POST    ✅
├── auth/me              GET     ✅
├── subjects             GET
├── subjects/{id}        GET
├── chapters             GET
├── chapters/{id}        GET
├── collections          GET
├── collections/{id}     GET
├── dashboard            GET
├── dashboard/progress   GET
├── dashboard/continue-learning  GET
├── dashboard/study-plan GET
├── quiz/start           POST
├── quiz/{id}/questions  GET
├── quiz/{id}/answer     POST
├── quiz/{id}/complete   POST
├── quiz/{id}/result     GET
├── quiz/{id}/review     GET
├── quiz/history         GET
├── revision/bookmarks   GET
├── revision/bookmarks/toggle  POST
├── revision/wrong-questions   GET
├── analytics/performance GET
├── gamification/streak  GET
├── gamification/xp      GET
├── gamification/badges  GET
├── gamification/achievements  GET
├── gamification/leaderboard   GET
├── enrollment/submit    POST
├── enrollment/status    GET
├── access/check         GET
├── profile              GET
├── profile              PUT
├── notifications        GET
├── notifications/{id}/read    POST
└── notifications/read-all     POST
```

### Recommended Implementation Roadmap

```
Phase 2A (Content)         → 6 endpoints   → Week 1
Phase 2B (Dashboard)       → 4 endpoints   → Week 1-2
Phase 2C (Quiz)            → 7 endpoints   → Week 2-3
Phase 2D (Analytics+Rev)   → 4 endpoints   → Week 3
Phase 2E (Gamification)    → 5 endpoints   → Week 3-4
Phase 2F (Enroll+Notif+Profile) → 8 endpoints → Week 4
```

### Testing Strategy

| Level | Approach |
|-------|---------|
| **Static Analysis** | PHP lint on every file, method signature grep, dependency chain validation (same as Phase 1 smoke test) |
| **Unit Testing** | Test each controller method with mock request objects. Validate response shape, status codes, and error handling. |
| **Integration Testing** | Curl/Postman scripts against staging WordPress. Test full flows: login → browse subjects → start quiz → submit answers → complete → view result → view bookmarks → check leaderboard. |
| **Auth Testing** | Verify every authenticated endpoint returns 401 without token, 403 for suspended users, 200 for valid tokens. |
| **Contract Testing** | Compare actual JSON responses against this document's response examples for shape compliance. |
| **CORS Testing** | Verify all new endpoints inherit CORS from the scoped handler. Test from localhost:3000 and unauthorized origins. |
