/** @vitest-environment jsdom */
import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, expect, test, vi } from "vitest";
import { ProfilePageShell } from "./ProfilePageShell";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const auth = vi.hoisted(() => ({ user: {
  id: 1, first_name: "Review", last_name: "User", display_name: "Review User", name: "Review User",
  email: "review@example.invalid", role: "Admin", is_active: true,
  recovery_email: "recovery@example.invalid", recovery_email_verified: true,
  pending_recovery_email: null as string | null,
  profile_photo: null as string | null,
}, refreshUser: vi.fn() }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => auth }));
const recovery = vi.hoisted(() => ({ send: vi.fn(), verify: vi.fn(), cancel: vi.fn(), remove: vi.fn(), saveProfile: vi.fn() }));
vi.mock("@/services/authService", async importOriginal => ({
  ...await importOriginal<typeof import("@/services/authService")>(),
  updateRecoveryEmail: recovery.send,
  verifyRecoveryEmail: recovery.verify,
  cancelPendingRecoveryEmail: recovery.cancel,
  removeRecoveryEmail: recovery.remove,
  updateProfile: recovery.saveProfile,
}));

let container: HTMLDivElement;
let root: Root;
beforeEach(() => {
  auth.user.profile_photo = null;
  auth.user.recovery_email = "recovery@example.invalid";
  auth.user.recovery_email_verified = true;
  auth.user.pending_recovery_email = null;
  vi.clearAllMocks();
  recovery.send.mockImplementation(async (email: string) => {
    auth.user = { ...auth.user, pending_recovery_email: email };
    return { message: "Verification code sent.", user: auth.user };
  });
  recovery.cancel.mockImplementation(async () => {
    auth.user = { ...auth.user, pending_recovery_email: null };
    return { user: auth.user };
  });
  recovery.saveProfile.mockResolvedValue(auth.user);
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
});
afterEach(async () => {
  await act(async () => root.unmount());
  container.remove();
});

const button = (name: string) => [...document.querySelectorAll<HTMLButtonElement>("button")]
  .find(item => (item.getAttribute("aria-label") ?? item.textContent?.trim()) === name);

test("photo editing starts with the user's initial when no photo is saved", async () => {
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  await act(async () => button("Edit Profile")!.click());
  expect(container.querySelector('[aria-label="Default profile picture"]')?.textContent).toBe("R");
  await act(async () => button("Edit profile picture")!.click());
  expect(button("Choose profile picture")).toBeDefined();
  expect(container.textContent).not.toContain("preview appears here");
});

test("photo editing previews the saved photo, offers change, and restores it after cancel", async () => {
  auth.user.profile_photo = "/current-photo.png";
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  await act(async () => button("Edit Profile")!.click());
  expect(container.querySelector('img')?.getAttribute("src")).toBe("/current-photo.png");
  await act(async () => button("Edit profile picture")!.click());
  expect(button("Change profile picture")).toBeDefined();
  await act(async () => button("Delete profile picture")!.click());
  expect(container.querySelector('[aria-label="Default profile picture"]')?.textContent).toBe("R");
  await act(async () => button("Cancel")!.click());
  await act(async () => button("Edit Profile")!.click());
  expect(container.querySelector('img')?.getAttribute("src")).toBe("/current-photo.png");
});

test("the photo menu is available before editing account details", async () => {
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  expect(container.querySelector('input[id="first-name"]')).toBeNull();
  await act(async () => button("Edit profile picture")!.click());
  expect(button("Choose profile picture")).toBeDefined();
});

test.each(["Admin", "RND"] as const)("%s shows recovery actions first and keeps removal out of the replacement form", async role => {
  auth.user.role = role;
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole={role} />));
  expect(container.textContent).toContain("recovery@example.invalid");
  expect(button("Remove Recovery Email")).toBeUndefined();
  expect(container.textContent).not.toContain("Verified");
  await act(async () => button("Edit Recovery Email")!.click());
  expect(button("Remove Recovery Email")).toBeDefined();
  await act(async () => button("Change Recovery Email")!.click());
  expect(button("Remove Recovery Email")).toBeUndefined();
  expect(container.querySelector<HTMLInputElement>('input[type="email"]')?.value).toBe("");
  await act(async () => button("Cancel")!.click());
  expect(button("Remove Recovery Email")).toBeUndefined();
  expect(container.textContent).toContain("recovery@example.invalid");
  expect(recovery.remove).not.toHaveBeenCalled();
});

async function enterReplacement() {
  await act(async () => button("Edit Recovery Email")!.click());
  await act(async () => button("Change Recovery Email")!.click());
  const field = container.querySelector<HTMLInputElement>('input[type="email"]')!;
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(field, "new@example.invalid");
    field.dispatchEvent(new Event("input", { bubbles: true }));
  });
  await act(async () => field.form!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
}

test("sending succeeds before switching to the code field; cancel discards the pending replacement", async () => {
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  await enterReplacement();
  expect(recovery.send).toHaveBeenCalledWith("new@example.invalid");
  expect(container.querySelector('input[type="email"]')).toBeNull();
  expect(container.querySelector('input[maxlength="6"]')).not.toBeNull();
  expect(auth.user.recovery_email).toBe("recovery@example.invalid");
  await act(async () => button("Cancel")!.click());
  expect(recovery.cancel).toHaveBeenCalledOnce();
  expect(recovery.remove).not.toHaveBeenCalled();
  expect(container.querySelector('input[maxlength="6"]')).toBeNull();
  expect(container.textContent).toContain("recovery@example.invalid");
  expect(auth.user.pending_recovery_email).toBeNull();
});

test("failed code delivery keeps the address field and never opens the code field", async () => {
  recovery.send.mockRejectedValueOnce(new Error("Verification code could not be sent."));
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  await enterReplacement();
  expect(container.querySelector('input[type="email"]')).not.toBeNull();
  expect(container.querySelector('input[maxlength="6"]')).toBeNull();
  expect(container.textContent).toContain("Verification code could not be sent.");
  expect(auth.user.recovery_email).toBe("recovery@example.invalid");
});

test("profile save feedback clears after three seconds", async () => {
  await act(async () => root.render(<ProfilePageShell crumbs={[["Profile"]]} fallbackRole="Admin" />));
  await act(async () => button("Edit Profile")!.click());
  vi.useFakeTimers();
  try {
    await act(async () => container.querySelector('input[id="first-name"]')!.closest('form')!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
    expect(container.textContent).toContain("Saved.");
    await act(async () => vi.advanceTimersByTime(3000));
    expect(container.textContent).not.toContain("Saved.");
  } finally {
    vi.useRealTimers();
  }
});
