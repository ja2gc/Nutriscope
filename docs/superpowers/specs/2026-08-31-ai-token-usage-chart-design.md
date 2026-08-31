# AI Token Usage Chart Design

## Goal

Replace the hand-built usage bars with a conventional, accessible daily column chart and improve cost estimates without adding persistent visual clutter.

## Data and estimation

- Continue treating Anthropic response usage as the source of truth for recorded input and output token counts.
- Aggregate input tokens, output tokens, and their total for every day or month.
- Estimate cost with separate configurable USD rates per one million input and output tokens:
  `input / 1,000,000 * input rate + output / 1,000,000 * output rate`.
- Convert the USD estimate using the dashboard's existing USD-to-PHP rate.
- Label cost as estimated. Token counts are not estimates.
- Keep the implementation model-simple: two current rate settings, not a historical pricing catalog.

## Chart

- Use the existing Recharts dependency and the dashboard's established colors, typography, spacing, and controls.
- Render one equal-width column per calendar day in month view and one per month in year view.
- Label every day from 1 through the last day of the month. On narrow screens, preserve labels with horizontal chart scrolling instead of skipping them.
- Start the y-axis at zero. Let the chart derive readable ticks from the highest total in the selected period.
- Render zero usage at the baseline without a decorative pseudo-bar. Do not render future periods as usage.
- Remove the usage, zero-usage, and not-occurred-yet legend because there is only one data series.

## Information hierarchy

- Keep the period summary token-only, matching the original display.
- Show the selected date, total tokens, input/output split, and estimated PHP cost in the hover/tap tooltip.
- Do not place cost labels above columns or repeat estimated cost around the chart.
- Retain the existing loading, error, period navigation, and filtering behavior.

## Verification

- Backend feature tests cover split-token aggregation, Manila boundaries, totals, and future dates.
- Frontend tests cover all daily labels, the absence of the old legend, token-only period summary, tooltip details, and the separate-rate cost calculation.
- Run focused backend and frontend tests, lint the changed frontend files, type-check, and inspect the rendered dashboard at desktop and narrow widths.
