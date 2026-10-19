// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it } from 'vitest';
import NumericInput from './NumericInput';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('increments a calculated portion by one tenth without snapping away its precision', async () => {
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<NumericInput label="Planned portion" value="1.01" onChange={() => {}} unit="serving" step={0.1} />));
  try {
    const input = container.querySelector('input')!;
    expect(input.value).toBe('1.01');
    input.stepUp(); expect(input.value).toBe('1.11');
    input.stepDown(); expect(input.value).toBe('1.01');
    await act(async () => root.render(<NumericInput label="Planned portion" value="0.3" onChange={() => {}} unit="serving" step={0.1} />));
    input.stepDown(3); expect(input.value).toBe('0');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
