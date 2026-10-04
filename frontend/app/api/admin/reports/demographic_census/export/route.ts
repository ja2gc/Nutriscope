import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET(request: Request) {
  return privateBinaryProxy(`/admin/reports/demographic_census/export${new URL(request.url).search}`);
}
