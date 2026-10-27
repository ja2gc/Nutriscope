export function rescalePortion<T extends { quantity: number }>(items: T[], previous: number, next: number): T[] {
  if (!Number.isFinite(previous) || previous <= 0 || !Number.isFinite(next) || next <= 0) {
    throw new Error('Enter a positive portion and a valid recipe baseline.');
  }
  return items.map(item => ({ ...item, quantity: Number((item.quantity * next / previous).toFixed(6)) }));
}
