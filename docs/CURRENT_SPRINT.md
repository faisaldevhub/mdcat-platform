# MDCAT Platform — Current Sprint

# Active Sprint

Sprint Name:
Student Enrollment & Approval System

Sprint Goal:
Build a complete student enrollment workflow where students submit enrollment requests, upload payment proof, and receive platform access after admin approval.

Status:
Planning

---

# Sprint Objectives

The platform should be able to:

* collect student enrollment requests
* accept payment screenshot uploads
* allow admin review and approval
* generate WordPress student accounts
* email login credentials automatically
* integrate with existing access control

This sprint focuses ONLY on:
student enrollment, approval workflow, and access provisioning.

---

# Current Tasks

## Enrollment Requests

[ ] Enrollment Request Form
[ ] Payment Screenshot Upload
[ ] Form Validation
[ ] Duplicate Email Handling

## Approval Workflow

[ ] Pending Requests
[ ] Approve Request
[ ] Reject Request
[ ] Rejection Reason Support

## User Provisioning

[ ] Create WordPress User
[ ] Use Email As Username
[ ] Generate Secure Password
[ ] Assign Student Role

## Email Automation

[ ] Approval Email
[ ] Credentials Email
[ ] Rejection Email

## Access Control Integration

[ ] Access Middleware Integration
[ ] Guest Redirect Flow
[ ] Login Enforcement
[ ] Enrollment Status Handling

## Admin Management

[ ] Enrollment Requests Dashboard
[ ] Screenshot Viewer
[ ] Enrollment Filters
[ ] Enrollment Status Tracking

## Frontend Experience

[ ] Enrollment Page
[ ] Enrollment Shortcode
[ ] Success Messages
[ ] Status Messages

---

# Sprint Rules

Rules:

* Reuse existing Access Control module
* Maintain modular architecture
* Do not modify quiz engine logic
* Keep enrollment logic isolated
* Use WordPress user management APIs
* Follow existing service-layer patterns
* Minimize database complexity

---

# Technical Direction

The enrollment system should integrate with:

* Access Control Module
* WordPress Users
* Email System
* Dashboard
* Authentication System

Avoid duplicating user data already stored in WordPress.

---

# Architecture Requirements

Use:

* service layer
* AJAX handlers
* shortcode rendering
* WordPress user APIs
* email services

Do NOT:

* build payment gateways
* build subscription systems
* duplicate WordPress user functionality
* tightly couple enrollment to quiz logic

---

# Success Criteria

Sprint is complete when:

* students can submit enrollment requests
* screenshots upload successfully
* admins can approve or reject requests
* WordPress users are created automatically
* credentials are emailed automatically
* approved students can access quizzes
* rejected students can re-apply
* no security regressions exist

---

# Not Included In This Sprint

Do NOT build:

* subscriptions
* JazzCash APIs
* EasyPaisa APIs
* recurring billing
* AI systems
* badges
* notifications

Only:
Student Enrollment & Approval System.

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
