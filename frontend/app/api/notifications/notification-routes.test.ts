import { beforeEach, describe, expect, it, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET as unreadCount } from "./unread-count/route";
import { DELETE as dismiss } from "./[id]/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));
const proxyMock = vi.mocked(proxy);

describe("notification proxy routes", () => {
  beforeEach(() => proxyMock.mockReset());

  it("forwards unread count", async () => {
    await unreadCount();
    expect(proxyMock).toHaveBeenCalledWith("/notifications/unread-count");
  });

  it("awaits dynamic params and forwards dismissal", async () => {
    await dismiss(new Request("http://localhost/api/notifications/notice-1", { method: "DELETE" }), { params: Promise.resolve({ id: "notice-1" }) });
    expect(proxyMock).toHaveBeenCalledWith("/notifications/notice-1", { method: "DELETE" });
  });
});
