import { FssShell } from "@/components/fss/FssShell";

export default function FssLayout({ children }: { children: React.ReactNode }) {
  return <FssShell>{children}</FssShell>;
}
