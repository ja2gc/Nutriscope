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

test('reserves content space with noninteractive, reduced-motion-safe skeletons', () => {
  const html = renderToStaticMarkup(<SectionLoading label="Loading inventory…" skeleton />);
  expect(html).toContain('aria-busy="true"');
  expect(html).toContain('role="status"');
  expect(html).toContain('Loading inventory…');
  expect(html).toContain('aria-hidden="true"');
  expect(html).toContain('motion-safe:animate-pulse');
  expect(html).not.toMatch(/<(button|input|a)\b/);
  expect(html).not.toContain('role="progressbar"');
});
