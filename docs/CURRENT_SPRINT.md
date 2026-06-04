# MDCAT Platform — Current Sprint

# Active Sprint

Sprint Name:
Admin Dashboard & Reporting System

Sprint Goal:
Build a centralized admin control center that provides platform-wide statistics, student insights, performance reports, and activity monitoring.

Status:
In Progress

---

# Sprint Objectives

The platform administrator should be able to understand:

* total platform usage
* student engagement
* performance trends
* subject strengths and weaknesses
* recent platform activity
* top-performing students

This sprint focuses ONLY on:
admin reporting and management visibility.

---

# Current Tasks

## Admin Overview Statistics

[ ] Total Students
[ ] Total Subjects
[ ] Total Chapters
[ ] Total Collections
[ ] Total Questions
[ ] Total Attempts
[ ] Average Accuracy
[ ] Active Streak Users

## Student Reporting

[ ] Student Statistics
[ ] Most Active Students
[ ] Student Performance Summary

## Performance Reporting

[ ] Subject Performance Report
[ ] Strongest Subjects
[ ] Weakest Subjects
[ ] Accuracy Metrics

## Activity Monitoring

[ ] Recent Activity Feed
[ ] Recent Quiz Attempts
[ ] Platform Usage Summary

## Dashboard UI

[ ] Admin Dashboard Layout
[ ] Statistics Cards
[ ] Reporting Tables
[ ] Responsive Design

---

# Sprint Rules

Rules:

* Do not modify unrelated modules
* Reuse existing services whenever possible
* Maintain modular architecture
* Keep reporting logic separated from student-facing logic
* Keep frontend controllers modular
* Avoid unnecessary database queries
* Prefer aggregated reporting over duplicate calculations

---

# Technical Direction

Reporting should derive from:

* users
* attempts
* analytics
* progress data
* streak data
* subjects
* chapters
* collections

Avoid duplicate data storage whenever possible.

---

# Architecture Requirements

Use:

* service layer
* modular AJAX handlers
* frontend rendering separation
* reusable reporting services

Do NOT:

* tightly couple reports to existing student dashboard modules
* duplicate analytics calculations
* create unnecessary database tables
* introduce heavy frontend computations

---

# Success Criteria

Sprint is complete when:

* admin can view platform statistics
* admin can view student activity
* admin can view subject performance reports
* admin can identify top performers
* reports are optimized and scalable
* frontend is responsive
* no security regressions exist

---

# Not Included In This Sprint

Do NOT build:

* payments
* AI systems
* notifications
* badges
* XP systems
* advanced charting
* institution management

Only:
Admin Dashboard & Reporting System.
