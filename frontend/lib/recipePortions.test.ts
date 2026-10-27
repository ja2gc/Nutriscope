import { describe, expect, it } from 'vitest';
import { rescalePortion } from './recipePortions';

describe('manual recipe portion changes', () => {
  it('preserves edited ingredient quantities when changing 2x to 3x', () => {
    expect(rescalePortion([{ quantity: 150 }, { quantity: 400 }], 2, 3)).toEqual([{ quantity: 225 }, { quantity: 600 }]);
  });
  it('rejects an unknown baseline rather than treating it as one', () => {
    expect(() => rescalePortion([{ quantity: 100 }], 0, 2)).toThrow();
  });
});
