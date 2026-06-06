# MDCAT Platform — Current Sprint

# Active Sprint

Sprint Name:
Payments & Subscription System

Sprint Goal:
Build a complete monetization system that supports free and premium access, subscriptions, enrollment management, and payment integrations.

Status:
Planning

---

# Sprint Objectives

The platform should be able to:

* sell premium access
* restrict protected content
* manage subscriptions
* support monthly plans
* support lifetime plans
* prepare for local payment gateways
* integrate with the existing access control system

This sprint focuses ONLY on:
payments, subscriptions, and monetization.

---

# Current Tasks

## Subscription Architecture

[ ] Subscription Plans
[ ] Monthly Plans
[ ] Lifetime Plans
[ ] Plan Management

## Access Management

[ ] Premium Access Rules
[ ] Collection Protection
[ ] Quiz Protection
[ ] Subscription Validation
[ ] Access Middleware Integration

## Enrollment System

[ ] Student Enrollment Logic
[ ] Active Subscription Tracking
[ ] Subscription Expiry Handling
[ ] Enrollment Status Management

## Payment Foundation

[ ] Payment Architecture
[ ] Transaction Records
[ ] Payment Verification Flow
[ ] Payment Status Management

## Payment Gateway Preparation

[ ] JazzCash Integration Design
[ ] EasyPaisa Integration Design
[ ] Gateway Abstraction Layer

## Admin Management

[ ] Subscription Management
[ ] Payment Monitoring
[ ] Enrollment Reports
[ ] Revenue Tracking Foundation

## Frontend Experience

[ ] Pricing Page Architecture
[ ] Upgrade Flow
[ ] Access Restriction Messages
[ ] Subscription Status Display

---

# Sprint Rules

Rules:

* Reuse the existing Access Control module
* Maintain modular architecture
* Keep payment logic separated from content logic
* Do not modify quiz engine behavior
* Keep payment gateways abstracted
* Design for future gateway expansion
* Prefer extensibility over quick hacks

---

# Technical Direction

The payment system should integrate with:

* Access Control Module
* Student Accounts
* Collections
* Quizzes
* Dashboard
* Enrollment Logic

Avoid duplicate user-access tracking whenever possible.

---

# Architecture Requirements

Use:

* service layer
* modular AJAX handlers
* payment abstraction layer
* reusable access middleware
* subscription validation services

Do NOT:

* tightly couple payment gateways to business logic
* hardcode JazzCash or EasyPaisa into core modules
* duplicate access control functionality
* create unnecessary complexity

---

# Success Criteria

Sprint is complete when:

* free and premium access works
* protected content is enforced
* subscriptions can be managed
* enrollment status is tracked
* payment architecture is extensible
* access control remains centralized
* no security regressions exist

---

# Not Included In This Sprint

Do NOT build:

* AI systems
* badges
* XP systems
* notifications
* advanced analytics
* mobile applications

Only:
Payments & Subscription System.

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
