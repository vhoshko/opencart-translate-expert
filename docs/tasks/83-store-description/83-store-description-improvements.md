# 83-store-description: improvements

## Permission prompts
- No new prompt came up.

## Skipped or improvised steps
- Skipped the Explore agent. The Opus planner did the investigation with greps.
- Skipped the second review. The fixes only add facts that the code shows.

## Friction
- The plan said "no manual reset button for the character count". The review found the button on the module page. A "must not appear" list for a feature claim needs a grep of the JS that renders the control, not only a grep for the function callers.
- A list of files from `ls images | head` was cut. Count the files before you put a list in a document.
- The plan named a feature (Unishop) from a code pattern (`uni_set`). The code never names the product. Check that a product name appears in the code before it goes into a document.

## Proposed edits to the workflow skills
- None.
