# Next Session Prompt — Student AI Tutor V1

> Paste this into a **fresh session** once `docs/ai-context/` is complete and verified.
> That session builds the feature; this session only produced the documentation it reads.

Read `docs/ai-context/README.md` first, then:

- `ai/01-existing-ai-usage.md`
- `ai/03-ai-context-contracts.md`
- `ai/04-ai-safety-rules.md`
- `ai/05-ai-roadmap.md`
- `modules/12-mistake-bank.md`
- `modules/13-learning-hub-worked-solutions-assets.md`
- `modules/15-notes-module.md`
- `modules/10-analytics-and-statistics.md`

## Goal

Build **Student AI Tutor V1**.

## Scope

- Ask AI from a worked solution.
- Ask AI from a mistake.
- Use current question/mistake context only.
- Include question stem, diagram reference, options, correct answer, selected answer,
  worked solution, option explanations, topic/subtopic, mistake count, mastery status.
- Save AI answer to Notes.
- Log AI interactions, model, tokens/cost if available, user role, source context type.

## Do not build

- full syllabus embeddings
- school-wide AI reports
- AI exam generator
- notes AI
- voice tutor
- autonomous lesson generation

## Safety

- AI must stay grounded in provided context.
- If context is insufficient, say so.
- Do not invent marks, topics, results, or syllabus facts.
- Do not expose other students' data.
- Do not modify official records without confirmation.
