# MDCAT Platform — Architecture

# Project Architecture

The platform follows a modular WordPress plugin architecture.

Core principles:

* modular systems
* service-layer architecture
* AJAX separation
* frontend controller separation
* centralized access control
* event-driven architecture
* scalable database structure

---

# Main Architecture Pattern

Each feature/module follows:

module
→ services
→ ajax
→ frontend
→ rendering
→ CSS/UI

Example:

analytics/
├── services/
├── ajax/
└── class-analytics.php

---

# Current Modules

## Core Learning Modules

* subjects
* chapters
* collections
* questions
* quiz
* attempts
* reviews

## Intelligence Modules

* analytics
* revision
* dashboard
* gamification

## Security & Platform Modules

* access
* auth
* enrollment (planned)
* payments (planned)

---

# Database Tables

## Core Content

* wp_mdcat_subjects
* wp_mdcat_chapters
* wp_mdcat_collections
* wp_mdcat_questions

## Quiz Systems

* wp_mdcat_attempts
* wp_mdcat_attempt_answers

## Revision Systems

* wp_mdcat_bookmarks

## Gamification Systems

* wp_mdcat_daily_activity

---

# Frontend Architecture

Frontend uses:

* modular JavaScript controllers
* AJAX-based rendering
* reusable UI rendering methods
* responsive CSS architecture

Main frontend file:

* assets/js/quiz-engine.js

Main CSS file:

* assets/css/quiz-engine.css

---

# Backend Architecture

Backend follows:

* service-layer pattern
* AJAX controllers
* centralized middleware
* reusable business logic

Rules:

* no heavy business logic inside AJAX handlers
* no direct frontend trust
* reusable service methods
* security checks server-side

---

# Security Architecture

Security systems include:

* centralized access control
* middleware validation
* nonce verification
* logged-in user validation
* protected AJAX endpoints
* protected shortcodes

---

# Event-Driven Systems

Current event hooks:

* mdcat_quiz_completed

Used for:

* streak tracking
* future XP systems
* achievements
* notifications
* analytics expansion

---

# Development Standards

Rules:

* Do not modify unrelated modules
* Keep modules isolated
* Reuse existing services
* Avoid duplicate business logic
* Maintain frontend controller separation
* Maintain scalable naming conventions

---

# Future Systems Planned

## Learning Intelligence

* progress tracking
* adaptive learning
* AI recommendations

## Monetization

* enrollments
* subscriptions
* premium access
* payment gateways

## Gamification

* XP
* badges
* achievements
* leaderboards

## AI Systems

* AI tutor
* AI study planner
* adaptive revision
* predictive scoring
