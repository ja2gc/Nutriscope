import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { expect, test } from 'vitest';
import { SectionLoading } from './SectionLoading';

test('announces the pending task without trapping focus or implying completion', () => {
  const html = renderToStaticMarkup(<SectionLoading label="Loading supplier choices…" />);
  expect(html).toContain('role="status"');
  expect(html).toContain('Loading supplier choices…');
  expect(html).not.toContain('tabindex');
  expect(html).not.toContain('role="progressbar"');
});
