# Available Shortcodes

All shortcodes are registered in `frontend/class-frontend.php` inside the `MDCAT_Platform_Frontend::init()` method.

Unless noted otherwise, every shortcode requires a logged-in user and will display a login prompt for guests.

---

## mdcat_dashboard

**Purpose:**
Renders the full student dashboard including overall progress, continue-learning widget, subject and chapter progress, stats grid, study streak, performance snapshot, quick actions, and recent activity. Data is populated via AJAX by the JavaScript controller.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_dashboard]
```

---

## mdcat_streak

**Purpose:**
Renders a standalone study streak widget showing current streak, longest streak, last active date, and total active days. Can be placed on any page independently from the dashboard. Data is fetched via AJAX.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_streak]
```

---

## mdcat_quiz

**Purpose:**
Renders the interactive quiz engine container with a timer, progress bar, start button, question area, and results view. Requires a `collection_id` attribute to specify which question collection to load. Access is controlled at the quiz level via `require_quiz_access()` rather than a simple login check.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_quiz collection_id="42"]
```

---

## mdcat_attempt_history

**Purpose:**
Renders a paginated table of the student's completed quiz attempts displaying subject, chapter, collection, score, correct count, wrong count, and date. Accepts an optional `per_page` attribute to control pagination size.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_attempt_history]
[mdcat_attempt_history per_page="10"]
```

---

## mdcat_performance

**Purpose:**
Renders performance analytics with two tables: subject-level performance (accuracy, correct, wrong, total) and chapter-level performance (accuracy and performance label). Data is fetched via AJAX.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_performance]
```

---

## mdcat_bookmarks

**Purpose:**
Renders a revision list of questions the student has bookmarked for later review. Uses the shared revision list container internally.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_bookmarks]
```

---

## mdcat_wrong_questions

**Purpose:**
Renders a revision list of questions the student answered incorrectly, enabling targeted re-study. Uses the shared revision list container internally.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_wrong_questions]
```

---

## mdcat_subject_progress

**Purpose:**
Renders a standalone subject progress view showing completion percentages and progress bars for each subject. Data is fetched independently via AJAX.

**File:**
`frontend/class-frontend.php`

**Example:**
```
[mdcat_subject_progress]
```
