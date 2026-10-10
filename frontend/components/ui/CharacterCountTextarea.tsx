"use client";

import { useId, useLayoutEffect, useRef } from "react";

type CharacterCountTextareaProps = {
  value: string;
  onChange: (value: string) => void;
  maxLength: number;
  rows?: number;
  disabled?: boolean;
  autoGrow?: boolean;
  className?: string;
  "aria-label"?: string;
};

export function CharacterCountTextarea({
  value,
  onChange,
  maxLength,
  rows = 3,
  disabled = false,
  autoGrow = false,
  className = "",
  "aria-label": ariaLabel,
}: CharacterCountTextareaProps) {
  const countId = useId();
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useLayoutEffect(() => {
    if (!autoGrow || !textareaRef.current) return;
    textareaRef.current.style.height = "auto";
    textareaRef.current.style.height = `${textareaRef.current.scrollHeight}px`;
  }, [autoGrow, rows, value]);

  return (
    <div className="relative">
      <textarea
        ref={textareaRef}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        maxLength={maxLength}
        rows={rows}
        disabled={disabled}
        aria-label={ariaLabel}
        aria-describedby={countId}
        className={`${className} pb-7`}
      />
      <span
        id={countId}
        className="pointer-events-none absolute bottom-2.5 right-3 text-xs font-normal leading-none text-warm-400"
      >
        {value.length}/{maxLength}
      </span>
    </div>
  );
}
