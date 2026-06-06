"use client";

interface TrendSparklineProps {
  values: number[];        // Chronological data points (oldest → newest)
  width?: number;
  height?: number;
  lowerIsBetter?: boolean; // true = declining is good (HbA1c, LDL); false = rising is good (albumin, weight)
}

export default function TrendSparkline({
  values,
  width = 72,
  height = 26,
  lowerIsBetter = false,
}: TrendSparklineProps) {
  if (!values || values.length < 2) return null;

  const pad = 3;
  const minVal = Math.min(...values);
  const maxVal = Math.max(...values);
  const range = maxVal - minVal || 1;

  const points = values.map((v, i) => {
    const x = pad + (i / (values.length - 1)) * (width - pad * 2);
    // SVG y-axis is inverted — higher value maps to lower y coordinate
    const y = pad + ((maxVal - v) / range) * (height - pad * 2);
    return { x, y };
  });

  const polyline = points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');

  const first = values[0];
  const last = values[values.length - 1];
  const improving = lowerIsBetter ? last < first : last > first;
  // flat line (no change) → neutral amber
  const isFlat = last === first;
  const stroke = isFlat ? '#f59e0b' : improving ? '#10b981' : '#f43f5e';

  const lastPoint = points[points.length - 1];

  return (
    <svg
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      className="overflow-visible shrink-0"
      aria-hidden="true"
    >
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
