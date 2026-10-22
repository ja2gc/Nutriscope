"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";

export default function GuidanceSuggestion({ label, text }: { label: string; text?: string }) {
  const [open, setOpen] = useState(false);
  const [copiedText, setCopiedText] = useState("");
  const [copyError, setCopyError] = useState("");
  if (!text) return null;

  async function copy() {
    setCopyError("");
    setCopiedText("");
    try {
      await navigator.clipboard.writeText(text!);
      setCopiedText(text!);
    } catch { setCopyError("Copy failed. Select and copy the note text."); }
  }

  return <Collapsible open={open} onOpenChange={setOpen} className="overflow-hidden rounded-lg border border-warm-200">
    <div className="flex flex-wrap items-center justify-between gap-2 px-2 py-1">
    <CollapsibleTrigger asChild>
      <Button type="button" variant="ghost" size="sm" aria-label={`${open ? "Hide" : "Show"} suggested ${label} note`}>
        {open ? "Hide suggestion" : "Show suggestion"}
      </Button>
    </CollapsibleTrigger>
      {open && <Button type="button" variant="secondary" size="sm" aria-label={`Copy suggested ${label} note`} onClick={() => void copy()}>
        {copiedText === text ? "Copied" : "Copy"}
      </Button>}
    </div>
    <CollapsibleContent className="space-y-2 border-t border-warm-200 px-3 py-3">
      <p className="select-text whitespace-pre-line text-sm leading-relaxed text-warm-700">{text}</p>
      {copyError && <p role="status" className="text-sm text-warm-600">{copyError}</p>}
    </CollapsibleContent>
  </Collapsible>;
}
