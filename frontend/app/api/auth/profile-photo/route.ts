import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET() {
  return privateBinaryProxy("/auth/profile-photo");
}
