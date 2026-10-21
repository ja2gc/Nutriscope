import { describe, expect, it } from 'vitest';
import { foodRecallAverage, foodRecallItemNutrients, foodRecallPayload, recipeRecallSnapshot, type FoodRecallPlan } from './foodRecall';

const item = {
  id: 'saved-line', food_item_id: 'rice-id', recipe_id: null, quantity: 0.25, unit: 'kg',
  nutrient_snapshot: { name: 'Rice', calories: 120, protein: 3, carbs: 25, fat: 1, water_g: 70,
    serving_size: 100, serving_unit: 'g', micronutrients: { sodium: 2 } },
};

describe('food recall nutrients', () => {
  it('scales every nutrient from the saved baseline using compatible units', () => {
    expect(foodRecallItemNutrients(item)).toMatchObject({ calories: 300, protein: 7.5, carbs: 62.5, fat: 2.5, water_g: 175, micronutrients: { sodium: 5 } });
    expect(item.nutrient_snapshot.calories).toBe(120);
    expect(foodRecallItemNutrients({ ...item, unit: 'cup' })).toBeNull();
  });

  it('requires an item in every selected day before returning a span average', () => {
    const plan: FoodRecallPlan = { days_count: 3, displayed_micros: ['sodium'], days: [{ items: [item] }, { items: [] }, { items: [item] }] };
    expect(foodRecallAverage(plan)).toBeNull();
    plan.days[1].items = [{ ...item, quantity: 0.1 }];
    expect(foodRecallAverage(plan)).toMatchObject({ calories: 240, protein: 6, micronutrients: { sodium: 4 } });
  });

  it('uses the saved prepared portion when a menu item is entered in measured units', () => {
    const menuItem = { ...item, recipe_id: 'recipe-id', food_item_id: null, quantity: 100, unit: 'g', nutrient_snapshot: {
      ...item.nutrient_snapshot, calories: 240, serving_size: 1, serving_unit: 'serving', prepared_portion_amount: 200, prepared_portion_unit: 'g',
    } };
    expect(foodRecallItemNutrients(menuItem)?.calories).toBe(120);
    expect(foodRecallItemNutrients({ ...menuItem, unit: 'kg' })?.calories).toBe(120000);
    expect(menuItem.quantity).toBe(100);
    expect(foodRecallItemNutrients({ ...menuItem, unit: 'cup' })).toBeNull();
  });

  it('recalculates recipe ingredient amounts without changing its frozen library baseline', () => {
    const baseline = { name: 'Rice dish', calories: 120, protein: 3, carbs: 25, fat: 1, serving_size: 1, serving_unit: 'serving', micronutrients: { sodium: 2 }, recipe_yield: 2,
      recipe_original_ingredients: [{ id: 1, food_item_id: 1, name: 'Rice', quantity: 200, unit: 'g', food_item: { id: 'rice-id', name: 'Rice', calories: '120', protein: '3', carbs: '25', fat: '1', serving_size: '100', serving_unit: 'g', micronutrients: { sodium: 2 } } }] };
    const result = recipeRecallSnapshot(baseline, [{ food_item_id: 'rice-id', quantity: 0.4, unit: 'kg' }]);
    expect(result).toMatchObject({ calories: 240, protein: 6, micronutrients: { sodium: 4 } });
    expect(baseline.recipe_original_ingredients[0].quantity).toBe(200);
    expect(baseline.calories).toBe(120);
  });

  it('sends source identities and portions without trusting client nutrient snapshots', () => {
    expect(foodRecallPayload({ days_count: 1, displayed_micros: [], days: [{ items: [item] }] })).toEqual({
      days_count: 1, displayed_micros: [], days: [{ items: [{ id: 'saved-line', food_item_id: 'rice-id', recipe_id: null, quantity: 0.25, unit: 'kg' }] }],
    });
  });
});
