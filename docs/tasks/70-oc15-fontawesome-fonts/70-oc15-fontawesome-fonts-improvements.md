# Improvements: 70-oc15-fontawesome-fonts

## Permission prompts
- None hit.

## Skipped or improvised steps
- The plan was written inline, not by an Opus subagent. The session model and the chosen planning model are close, and the plan has 5 short steps.
- The user chose Opus for planning and review. The review is not independent.

## Friction
- The plan template asks for a Phase 0 question set of 3 questions. The issue text was clear, so only the model questions were needed.

## Proposed edits
- Add a rule to `project-workflows.md`: if a task adds a font or image to `sources/`, compare it with the npm package of the same version using `cmp`.
- Done in the same PR: the OC 2.0 to 3.0 zips exclude `oc15`.
