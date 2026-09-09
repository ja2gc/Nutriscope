import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";

import { AnnouncementAuthorAvatar } from "./AnnouncementAuthorAvatar";

describe("AnnouncementAuthorAvatar", () => {
  test("renders the author's real profile photo when available", () => {
    const markup = renderToStaticMarkup(
      <AnnouncementAuthorAvatar
        author={{ name: "Maria Santos", profile_photo: "/api/announcements/one/author-photo" }}
      />
    );

    expect(markup).toContain("src=\"/api/announcements/one/author-photo\"");
    expect(markup).toContain("alt=\"Maria Santos profile photo\"");
    expect(markup).toContain("object-cover");
    expect(markup).not.toContain("Announcement author");
  });

  test("falls back to initials when no profile photo exists", () => {
    const markup = renderToStaticMarkup(
      <AnnouncementAuthorAvatar author={{ name: "Maria Santos", profile_photo: null }} />
    );

    expect(markup).toContain("MS");
    expect(markup).not.toContain("<img");
  });
});
