import { afterEach, expect, test, vi } from "vitest";
import { fetchAnnouncements, fetchAdminAnnouncements } from "./announcementService";

afterEach(() => vi.unstubAllGlobals());
test.each([fetchAnnouncements, fetchAdminAnnouncements])("requests compact feeds and supports the legacy editor representation", async (load) => {
  const fetch = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>(async () => new Response(JSON.stringify({ data: [] })));
  vi.stubGlobal("fetch", fetch);
  await load(1, 10);
  expect(fetch.mock.calls[0]?.[0]).toContain("attachment_format=urls");
  await load(1, 1, "fictional", false);
  expect(fetch.mock.calls[1]?.[0]).toContain("attachment_format=inline");
});
