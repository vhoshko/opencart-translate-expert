# 81-collapse-settings-cards: improvements

## Permission prompts
- No new prompt came up.

## Skipped or improvised steps
- Skipped the Explore agent. Four greps found all card markup, so the plan records the skip.
- Skipped the second review. The first review found only 4 minor findings. The 2 fixes changed one PHP condition and one test.

## Friction
- A script that edited both templates added a double negation (`!!empty`). The review found it. After a scripted edit, read the resulting diff line by line.
- The static test first checked only the name of the error variable. It could not find an inverted condition. Test the full condition text.

## Proposed edits to the workflow skills
- None.

## Open follow-ups (not in scope)
- A number field in a collapsed card can block the save with no visible message. The same problem exists when the user saves from another tab.
