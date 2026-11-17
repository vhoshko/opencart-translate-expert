# Improvements: 71-oc15-header-char-count

## Permission prompts

None.

## Skipped or improvised steps

- The task was small. The flow skipped the Explore agent and the sanity check. Two greps found the code.
- The plan came from the session model, not Opus. A planning subagent costs more than a 20-line plan.
- The re-review gate was skipped. The fixes changed only CSS and JS tests. Auto-approve was on.

## Friction

- The phase 0 model question lists Opus as the review default. The user picked Opus for both planning and review. The skill says to use a different review model in this case. Show this rule in the question text.

## Proposed edits

- In the phase 0 question, mark the review default as "a model that differs from the planning model".
- A CSS change for one OpenCart version needs a check of the page colors. The reviewer found a contrast problem that tests cannot find. Add a line to the reviewer brief for UI work: "Check colors against the real admin CSS of each OC version."
