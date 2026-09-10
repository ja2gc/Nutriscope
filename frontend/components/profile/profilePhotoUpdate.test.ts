import { describe, expect, it } from "vitest";
import { profilePhotoUpdate } from "./profilePhotoUpdate";

describe("profilePhotoUpdate", () => {
  it("omits the photo when the existing image was not changed", () => {
    expect(profilePhotoUpdate("unchanged", "/api/auth/profile-photo")).toEqual({});
  });

  it("sends null only after explicit removal", () => {
    expect(profilePhotoUpdate("remove", null)).toEqual({ profile_photo: null });
  });

  it("sends a newly cropped data URI", () => {
    const photo = "data:image/jpeg;base64,ZmFrZQ==";
    expect(profilePhotoUpdate("replace", photo)).toEqual({ profile_photo: photo });
  });

  it("does not send an existing API URL as a replacement", () => {
    expect(profilePhotoUpdate("replace", "/api/auth/profile-photo")).toEqual({});
  });
});
