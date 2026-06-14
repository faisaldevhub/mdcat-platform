# MDCAT Platform — Current Sprint

# Active Sprint

Sprint Name:
Student Management System

Sprint Goal:
Build a centralized student management system that allows administrators to view, manage, monitor, and control student accounts, enrollment records, learning activity, and progress.

Status:
Planning

---

# Sprint Objectives

The platform administrator should be able to:

* view all students
* search and filter students
* view student profiles
* monitor learning activity
* monitor progress and performance
* manage student status
* review enrollment history

This sprint focuses ONLY on:
student management and administration.

---

# Current Tasks

## Student Directory

[ ] Student Listing
[ ] Search Students
[ ] Filter Students
[ ] Pagination

## Student Profiles

[ ] Student Overview
[ ] Enrollment Details
[ ] Registration Information
[ ] Last Activity Tracking

## Learning Analytics

[ ] Progress Overview
[ ] Subject Completion
[ ] Chapter Completion
[ ] Overall Progress
[ ] Accuracy Summary

## Activity Monitoring

[ ] Recent Attempts
[ ] Recent Scores
[ ] Login Activity
[ ] Learning History

## Student Management

[ ] Activate Student
[ ] Suspend Student
[ ] Student Status Management
[ ] Enrollment Review Access

## Admin Dashboard Integration

[ ] Student Detail Links
[ ] Student Quick Actions
[ ] Reporting Integration

---

# Sprint Rules

Rules:

* Reuse existing enrollment services
* Reuse analytics services
* Reuse progress services
* Reuse reporting services
* Maintain modular architecture
* Keep admin functionality isolated
* Avoid duplicate data storage
* Minimize database queries

---

# Technical Direction

Student management should derive from:

* WordPress users
* enrollment requests
* attempts
* analytics
* progress tracking
* streak tracking

Avoid duplicating information already available in existing systems.

---

# Architecture Requirements

Use:

* service layer
* AJAX handlers
* admin views
* reusable reporting services
* reusable progress services

Do NOT:

* duplicate analytics calculations
* duplicate enrollment data
* create unnecessary tables
* tightly couple modules

---

# Success Criteria

Sprint is complete when:

* admin can view all students
* admin can search and filter students
* admin can view student profiles
* admin can review student activity
* admin can monitor progress
* admin can suspend or activate students
* frontend remains responsive
* no security regressions exist

---

# Not Included In This Sprint

Do NOT build:

* payments
* AI systems
* badges
* XP systems
* notifications
* leaderboards

Only:
Student Management System.

---

# Sprint Completion Status

Implementation:
⏳ Not Started

Testing:
⏳ Not Started

Documentation:
⏳ Not Started

Deployment:
⏳ Not Started
