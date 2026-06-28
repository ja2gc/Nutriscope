import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";

import { Button } from "./Button";
import { Logo } from "./Logo";
import StatusBadge from "./StatusBadge";
import TabBar from "./TabBar";
import { Tabs } from "./Tabs";

describe("NutriScope shared UI theme components", () => {
  test("Logo keeps Nutri green and Scope orange in every variant", () => {
    const lightMarkup = renderToStaticMarkup(<Logo variant="light" />);
    const darkMarkup = renderToStaticMarkup(<Logo variant="dark" />);

    expect(lightMarkup).toContain(">Nutri<");
    expect(lightMarkup).toContain(">Scope<");
    expect(darkMarkup).toContain(">Nutri<");
    expect(darkMarkup).toContain(">Scope<");

    expect(lightMarkup).toContain("text-brand-green-600");
    expect(lightMarkup).toContain("text-brand-orange-600");
    expect(darkMarkup).toContain("text-brand-green-600");
    expect(darkMarkup).toContain("text-brand-orange-600");
    expect(darkMarkup).not.toContain("text-warm-100");
  });

  test("Button primary style is compact by default and opts into full width", () => {
    const compactMarkup = renderToStaticMarkup(<Button>Add Announcement</Button>);
    const fullMarkup = renderToStaticMarkup(<Button fullWidth>Sign In</Button>);

    expect(compactMarkup).toContain("bg-brand-green-600");
    expect(compactMarkup).toContain("hover:bg-brand-green-700");
    expect(compactMarkup).not.toContain("w-full");
    expect(fullMarkup).toContain("w-full");
  });

  test("Tabs use brand green for active state and focus ring", () => {
    const markup = renderToStaticMarkup(
      <Tabs
        items={[{ key: "all", label: "All" }, { key: "active", label: "Active" }]}
        value="active"
        onChange={() => undefined}
      />
    );

    expect(markup).toContain("border-brand-green-600");
    expect(markup).toContain("text-brand-green-700");
    expect(markup).toContain("focus-visible:ring-brand-green-500/30");
  });

  test("StatusBadge warning uses subtle brand orange", () => {
    const markup = renderToStaticMarkup(<StatusBadge status="warning" label="Warning" />);

    expect(markup).toContain("bg-brand-orange-50");
    expect(markup).toContain("text-brand-orange-700");
    expect(markup).toContain("border-brand-orange-100");
  });

  test("TabBar uses the same brand active classes as Tabs", () => {
    const markup = renderToStaticMarkup(
      <TabBar
        tabs={[{ key: "overview", label: "Overview" }, { key: "details", label: "Details" }]}
        activeTab="overview"
        onTabChange={() => undefined}
      />
    );

    expect(markup).toContain("text-brand-green-700");
    expect(markup).toContain("border-brand-green-600");
  });
});
