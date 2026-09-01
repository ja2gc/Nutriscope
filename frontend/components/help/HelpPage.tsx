"use client";

import { useMemo, useState } from "react";
import { CircleHelp, ShieldCheck } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import SearchInput from "@/components/ui/SearchInput";
import {
  filterHelpItems,
  getPopularHelpItems,
  groupHelpItems,
  type WebHelpRole,
} from "@/lib/helpContent";
import { HelpQuestionList } from "./HelpQuestionList";

function slug(value: string) {
  return value.toLocaleLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
}

export function HelpPage({ role }: { role: WebHelpRole }) {
  const [query, setQuery] = useState("");
  const normalizedQuery = query.trim();
  const items = useMemo(() => filterHelpItems(role, query), [query, role]);
  const groups = useMemo(() => groupHelpItems(items), [items]);
  const popular = useMemo(() => getPopularHelpItems(role), [role]);
  const homeHref = role === "Admin" ? "/admin/dashboard" : "/dashboard";

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <PageHeader
        crumbs={[[role, homeHref], ["Help"]]}
        title="Help"
        icon={<CircleHelp className="h-5 w-5 text-brand-green-700" />}
      />

      <section
        aria-labelledby="help-search-heading"
        className="rounded-2xl border border-warm-200 bg-white p-5 shadow-sm sm:p-6"
      >
        <div className="max-w-3xl">
          <h2 id="help-search-heading" className="text-lg font-extrabold text-warm-900">
            What do you need help with?
          </h2>
          <label htmlFor="help-search" className="mt-4 block text-sm font-semibold text-warm-700">
            Search Help
          </label>
          <SearchInput id="help-search" className="mt-1.5" label="Search Help" value={query} onChange={setQuery} />
          <p aria-live="polite" className="mt-2 text-sm font-medium text-warm-500">
            {normalizedQuery
              ? `${items.length} ${items.length === 1 ? "answer" : "answers"} found`
              : `${items.length} answers available`}
          </p>
        </div>
      </section>

      {!normalizedQuery && popular.length > 0 && (
        <section aria-labelledby="popular-help-heading" className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
          <div className="border-b border-warm-100 bg-warm-50 px-4 py-3.5 sm:px-5">
            <h2 id="popular-help-heading" className="text-base font-extrabold text-warm-900">
              Popular questions
            </h2>
          </div>
          <HelpQuestionList items={popular} instanceId="popular" />
        </section>
      )}

      {groups.length > 0 ? (
        <div className="space-y-5">
          {groups.map((group) => (
            <section
              key={group.category}
              aria-labelledby={`help-group-${slug(group.category)}`}
              className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm"
            >
              <div className="border-b border-warm-100 bg-warm-50 px-4 py-3.5 sm:px-5">
                <h2 id={`help-group-${slug(group.category)}`} className="text-base font-extrabold text-warm-900">
                  {group.category}
                </h2>
                <p className="mt-0.5 text-sm text-warm-500">
                  {group.items.length} {group.items.length === 1 ? "answer" : "answers"}
                </p>
              </div>
              <HelpQuestionList
                key={`${group.category}-${normalizedQuery}`}
                items={group.items}
                instanceId={`group-${slug(group.category)}`}
              />
            </section>
          ))}
        </div>
      ) : (
        <section className="rounded-2xl border border-dashed border-warm-300 bg-white px-6 py-12 text-center">
          <CircleHelp aria-hidden="true" className="mx-auto h-9 w-9 text-warm-400" />
          <h2 className="mt-3 text-lg font-extrabold text-warm-900">No matching answers</h2>
          <p className="mx-auto mt-2 max-w-xl text-base leading-7 text-warm-500">
            Try fewer or broader words. You can also clear the search to browse every topic.
          </p>
          <button
            type="button"
            onClick={() => setQuery("")}
            className="mt-5 min-h-11 cursor-pointer rounded-lg bg-forest-900 px-4 py-2 text-sm font-bold text-white hover:bg-forest-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/40"
          >
            Clear search
          </button>
        </section>
      )}

      <aside className="flex gap-3 rounded-2xl border border-brand-green-200 bg-brand-green-50 p-5 text-brand-green-900">
        <ShieldCheck aria-hidden="true" className="mt-0.5 h-5 w-5 shrink-0 text-brand-green-700" />
        <div>
          <h2 className="text-base font-extrabold">Still need help?</h2>
          <p className="mt-1 text-sm leading-6">
            Contact your administrator with the screen, time, action, and safe error wording. Never send a password, recovery code, or patient information in a support message.
          </p>
        </div>
      </aside>
    </div>
  );
}
