const MANILA_TIME = new Intl.DateTimeFormat("en-US", {
  timeZone: "Asia/Manila",
  year: "numeric",
  month: "short",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
  hour12: true,
});

export function formatAuditTimestamp(value: string) {
  const date = new Date(value);
  const parts = Object.fromEntries(
    MANILA_TIME.formatToParts(date)
      .filter((part) => part.type !== "literal")
      .map((part) => [part.type, part.value]),
  );
  const iso = date.toISOString();
  const dateLabel = `${parts.month} ${parts.day}, ${parts.year}`;
  const timeLabel = `${parts.hour}:${parts.minute}:${parts.second} ${parts.dayPeriod} PHT`;

  return {
    iso,
    dateLabel,
    timeLabel,
    title: `${iso} · ${dateLabel} ${timeLabel.replace(" PHT", "")} Asia/Manila`,
  };
}

export function AuditTimestamp({ value, layout = "inline" }: { value: string; layout?: "inline" | "stacked" }) {
  const timestamp = formatAuditTimestamp(value);
  return (
    <time
      dateTime={timestamp.iso}
      title={timestamp.title}
      aria-label={`${timestamp.dateLabel} ${timestamp.timeLabel}, Asia/Manila`}
    >
      {layout === "stacked" ? (
        <>
          <span className="block text-sm font-semibold text-warm-800">{timestamp.dateLabel}</span>
          <span className="mt-0.5 block text-xs tabular-nums text-warm-500">{timestamp.timeLabel}</span>
        </>
      ) : (
        `${timestamp.dateLabel} · ${timestamp.timeLabel}`
      )}
    </time>
  );
}
