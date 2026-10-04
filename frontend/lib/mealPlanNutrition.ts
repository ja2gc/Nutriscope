type FoodFluidItem = {
  quantity: string | number;
  nutrient_snapshot: {
    serving_size: number;
    water_g?: number | null;
  } | null;
};

export function foodFluidBalance(items: FoodFluidItem[], targetMl: number) {
  const foodFluidMl = Math.round(items.reduce((total, item) => {
    const snapshot = item.nutrient_snapshot;
    if (!snapshot?.water_g) return total;
    const quantity = Number(item.quantity);
    const scale = snapshot.serving_size > 0 ? quantity / snapshot.serving_size : 1;
    return total + snapshot.water_g * scale;
  }, 0));

  return {
    requiredFluidMl: targetMl,
    foodFluidMl,
    remainingMl: Math.max(0, Math.round(targetMl - foodFluidMl)),
  };
}
