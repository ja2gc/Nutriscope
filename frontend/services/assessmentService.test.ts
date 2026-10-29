import { afterEach, describe, expect, test, vi } from "vitest";

import { fetchAssessment, saveAssessment } from "./assessmentService";

describe("saveAssessment", () => {
  afterEach(() => vi.unstubAllGlobals());

  test("preserves the HTTP status so missing records differ from connection failures", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response("{}", { status: 404 })));
    await expect(fetchAssessment("fictional")).rejects.toMatchObject({ status: 404 });
  });

  test("sends calculated IBW percentage at the API's two-decimal precision", async () => {
    const fetchMock = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>(async () => new Response(JSON.stringify({ data: {} }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }));
    vi.stubGlobal("fetch", fetchMock);

    await saveAssessment("synthetic-ncp", { ibw_percentage: 134.932545, bmi_classification: 'Obese Class I', bmi_reference_date: '2026-10-25' }, false);

    const body = JSON.parse(fetchMock.mock.calls[0][1]?.body as string);
    expect(body.ibw_percentage).toBe(134.93);
    expect(body).not.toHaveProperty('bmi_classification');
    expect(body).not.toHaveProperty('bmi_reference_date');
  });

  test('saves recall sources and portions with the assessment without posting nutrient snapshots', async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ data: {} }), { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    await saveAssessment('cycle', { food_recall: { days_count: 1, displayed_micros: [], days: [{ items: [{
      id: 'line', food_item_id: 'food', recipe_id: null, quantity: 100, unit: 'g',
      nutrient_snapshot: { name: 'Saved food', calories: 120, protein: 3, carbs: 25, fat: 1, serving_size: 100, serving_unit: 'g', micronutrients: {} },
    }] }] } }, true);
    const body = JSON.parse((fetchMock.mock.calls[0] as unknown as [unknown, RequestInit])[1].body as string);
    expect(body.food_recall.days[0].items[0]).toEqual({ id: 'line', food_item_id: 'food', recipe_id: null, quantity: 100, unit: 'g' });
  });
});
