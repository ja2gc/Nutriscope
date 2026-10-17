"use client";

interface TrendSparklineProps {
  values: number[];        // Chronological data points (oldest → newest)
  width?: number;
  height?: number;
  lowerIsBetter?: boolean; // true = declining is good (HbA1c, LDL); false = rising is good (albumin, weight)
  target?: number | null;
  reference?: { min?: number | null; max?: number | null } | null;
}

export default function TrendSparkline({
  values,
  width = 72,
  height = 26,
  target,
  reference,
}: TrendSparklineProps) {
  if (!values || values.length < 2) return null;

  const pad = 3;
  const guides = [target, reference?.min, reference?.max].filter((value): value is number => value != null && Number.isFinite(value));
  const minVal = Math.min(...values, ...guides);
  const maxVal = Math.max(...values, ...guides);
  const range = maxVal - minVal || 1;

  const points = values.map((v, i) => {
    const x = pad + (i / (values.length - 1)) * (width - pad * 2);
    // SVG y-axis is inverted — higher value maps to lower y coordinate
    const y = pad + ((maxVal - v) / range) * (height - pad * 2);
    return { x, y };
  });

  const polyline = points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');

  const stroke = '#2563eb';

  const lastPoint = points[points.length - 1];

  return (
    <svg
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      className="overflow-visible shrink-0"
      role="img"
      aria-label={`Measurement trend${target != null ? `; Target: ${Number(target.toFixed(2))}` : ""}${reference?.min != null ? `; Reference minimum: ${Number(reference.min.toFixed(2))}` : ""}${reference?.max != null ? `; Reference maximum: ${Number(reference.max.toFixed(2))}` : ""}`}
    >
      {guides.map((guide, index) => {
        const y = pad + ((maxVal - guide) / range) * (height - pad * 2);
        return <line key={index} x1={pad} x2={width - pad} y1={y} y2={y} stroke={guide === target ? "#059669" : "#d97706"} strokeDasharray="3 2" />;
      })}
      <polyline
        points={polyline}
        fill="none"
        stroke={stroke}
        strokeWidth={1.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      {/* Endpoint dot */}
      <circle
        cx={lastPoint.x.toFixed(1)}
        cy={lastPoint.y.toFixed(1)}
        r={2.5}
        fill={stroke}
      />
    </svg>
  );
}
