# Story 01 — Upload Recognition Moment

## Situation

After a long shoot, the photographer uploads a large batch of images (e.g. 200–1000 files named DSC_XXXX.jpg).

The photographer is mentally fatigued and prone to mistakes.

---

## System Behavior

The system:

1. Detects likely session context:
   - from calendar (if available)
   - or from metadata/time clustering

2. Associates upload with a session (confidence-based)

3. Responds immediately with context-aware guidance:

Example:

"Looks like the Alma Mater Footwear Lifestyle shoot went well.  
You finished 15 minutes early.  
Do you want to:
- Cull images
- Start delivery
- Create invoice"

---

## User Outcome

- No manual project naming
- No searching for shoot details
- Reduced decision fatigue
- Immediate forward momentum

---

## System Requirements

- Candidate matching (already built ✅)
- Confidence scoring (already built ✅)
- Session context attachment (already built ✅)
- New: UploadRecognition event
- New: Suggestion engine (lightweight)

---

## Success Criteria

- Upload → suggestion appears in < 2 seconds
- Correct session identified ≥ 90% when calendar exists
- User can act without navigating elsewhere