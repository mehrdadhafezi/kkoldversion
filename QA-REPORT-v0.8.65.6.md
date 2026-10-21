# QA Report — v0.8.65.6

## Scope
- Ported the receipt zoom visibility fix onto the supplied v0.8.65.5 package.
- Preserved the existing v0.8.65.5 finance workflow and robust zoom/pan/pinch implementation.

## Fix
- Expanded the viewer canvas by the absolute pan offset on both axes before calculating scroll bounds.
- This guarantees a negatively panned/scaled image does not place its top edge outside the scrollable coordinate space.
- Added a zoomed-stage top alignment class.

## Validation
- JavaScript syntax check: PASS.
- PHP lint: PASS for all PHP files.
- ZIP integrity: PASS.
