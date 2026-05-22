# MDCAT Platform Database Planning

## Subjects Table
wp_mdcat_subjects

- id
- name
- slug
- created_at

---

## Chapters Table
wp_mdcat_chapters

- id
- subject_id
- name
- slug
- created_at

Relationship:
A chapter belongs to one subject.

---

## Collections Table
wp_mdcat_collections

- id
- chapter_id
- title
- type
- slug
- created_at

Collection Types:
- exercise_mcqs
- past_papers
- practice_test
- book_lines

Relationship:
A collection belongs to one chapter.

---

## Questions Table (Future)

- id
- collection_id
- question
- explanation
- difficulty
- created_at

Relationship:
A question belongs to one collection.