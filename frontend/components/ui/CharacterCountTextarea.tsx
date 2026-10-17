"use client";

import { useId, useLayoutEffect, useRef } from "react";

type CharacterCountTextareaProps = {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  maxLength: number;
  rows?: number;
  disabled?: boolean;
  autoGrow?: boolean;
  className?: string;
  "aria-label"?: string;
  onKeyDown?: React.KeyboardEventHandler<HTMLTextAreaElement>;
  onBlur?: React.FocusEventHandler<HTMLTextAreaElement>;
};

export function CharacterCountTextarea({
  id,
  value,
  onChange,
  maxLength,
  rows = 3,
  disabled = false,
  autoGrow = false,
  className = "",
  "aria-label": ariaLabel,
  onKeyDown,
  onBlur,
}: CharacterCountTextareaProps) {
  const countId = useId();
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const characterCount = Array.from(value).length;

  useLayoutEffect(() => {
    if (!autoGrow || !textareaRef.current) return;
    const textarea = textareaRef.current;
    const resize = () => {
      textarea.style.height = "auto";
      const borders = textarea.offsetHeight - textarea.clientHeight;
      textarea.style.height = `${textarea.scrollHeight + borders}px`;
    };
    resize();
    if (typeof ResizeObserver === "undefined") return;
    let width = textarea.clientWidth;
    const observer = new ResizeObserver(() => {
      if (textarea.clientWidth === width) return;
      width = textarea.clientWidth;
      resize();
    });
    observer.observe(textarea);
    return () => observer.disconnect();
  }, [autoGrow, rows, value]);

  return (
    <div className="relative">
      <textarea
        id={id}
        ref={textareaRef}
        value={value}
        onChange={(event) => {
          const next = Array.from(event.target.value);
          if (characterCount > maxLength && next.length >= characterCount) return;
          onChange(next.length > maxLength && characterCount <= maxLength
            ? next.slice(0, maxLength).join("")
            : event.target.value);
        }}
        onKeyDown={onKeyDown}
        onBlur={onBlur}
        rows={rows}
        disabled={disabled}
        aria-label={ariaLabel}
        aria-describedby={countId}
        aria-invalid={characterCount > maxLength || undefined}
        className={`${className} pb-7`}
      />
      <span
        id={countId}
        className="pointer-events-none absolute bottom-2.5 right-3 text-xs font-normal leading-none text-warm-400"
      >
        {characterCount}/{maxLength}
      </span>
    </div>
  );
}
