"use client";

import React, { useEffect, useState } from "react";
import { Mail, KeyRound, ShieldCheck, PencilLine, X } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { ImageUploadGallery, imagesFromSrcs, imageSrcs, readImages, type UploadImage } from "@/components/ui/ImageUploadGallery";
import { useAuth } from "@/contexts/AuthContext";
import {
  updateProfile,
  changePassword,
  updateRecoveryEmail,
  verifyRecoveryEmail,
  type User,
} from "@/services/authService";
import {
  changedPersonNameFields,
  personNameFormValues,
} from "@/lib/personName";
import { ProfilePhotoCropDialog } from "@/components/profile/ProfilePhotoCropDialog";
import { profilePhotoUpdate, type ProfilePhotoIntent } from "@/components/profile/profilePhotoUpdate";
import { personDisplayName } from "@/lib/personName";

type ProfilePageShellProps = {
  crumbs: [string, string?][];
  subtitle: string;
  fallbackRole: User["role"];
};

export function ProfilePageShell({ crumbs, subtitle, fallbackRole }: ProfilePageShellProps) {
  const { user, refreshUser } = useAuth();

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [contactNumber, setContactNumber] = useState("");
  const [editingProfile, setEditingProfile] = useState(false);
  const [profileImages, setProfileImages] = useState<UploadImage[]>([]);
  const [profilePhotoIntent, setProfilePhotoIntent] = useState<ProfilePhotoIntent>("unchanged");
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [profilePhotoError, setProfilePhotoError] = useState<string | null>(null);
  const [pendingProfilePhoto, setPendingProfilePhoto] = useState<UploadImage | null>(null);
  const [profileDone, setProfileDone] = useState(false);

  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [savingPassword, setSavingPassword] = useState(false);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordDone, setPasswordDone] = useState(false);

  const [recoveryEmail, setRecoveryEmail] = useState("");
  const [recoveryCode, setRecoveryCode] = useState("");
  const [savingRecoveryEmail, setSavingRecoveryEmail] = useState(false);
  const [verifyingRecoveryEmail, setVerifyingRecoveryEmail] = useState(false);
  const [recoveryMessage, setRecoveryMessage] = useState<string | null>(null);
  const [recoveryError, setRecoveryError] = useState<string | null>(null);

  useEffect(() => {
    if (user) {
      const nameValues = personNameFormValues(user);
      setFirstName(nameValues.firstName);
      setLastName(nameValues.lastName);
      setContactNumber(user.contact_number ?? "");
      setRecoveryEmail(user.recovery_email ?? "");
      setProfileImages(imagesFromSrcs(user.profile_photo ? [user.profile_photo] : [], "Profile photo"));
      setProfilePhotoIntent("unchanged");
    }
  }, [user]);

  async function handleProfileSubmit(e: React.FormEvent) {
    e.preventDefault();
    setProfileError(null);
    setProfileDone(false);
    setSavingProfile(true);
    try {
      if (!user) {
        throw new Error("User profile is unavailable.");
      }
      const nameFields = changedPersonNameFields(user, firstName, lastName);
      await updateProfile({
        contact_number: contactNumber.trim() || null,
        ...profilePhotoUpdate(profilePhotoIntent, imageSrcs(profileImages)[0] ?? null),
        ...(nameFields ?? {}),
      });
      await refreshUser();
      setProfileDone(true);
      setEditingProfile(false);
    } catch (err) {
      setProfileError(err instanceof Error ? err.message : "Failed to update profile.");
    } finally {
      setSavingProfile(false);
    }
  }

  function cancelProfileEdit() {
    if (user) {
      const nameValues = personNameFormValues(user);
      setFirstName(nameValues.firstName);
      setLastName(nameValues.lastName);
      setContactNumber(user.contact_number ?? "");
      setProfileImages(imagesFromSrcs(user.profile_photo ? [user.profile_photo] : [], "Profile photo"));
    }
    setProfilePhotoIntent("unchanged");
    setPendingProfilePhoto(null);
    setProfileError(null);
    setProfileDone(false);
    setEditingProfile(false);
  }

  async function handleProfilePhotoFiles(files: File[]) {
    setProfilePhotoError(null);
    const file = files.at(-1);
    if (!file) return;
    const allowedTypes = ["image/png", "image/jpeg", "image/webp"];
    if (!allowedTypes.includes(file.type)) {
      setProfilePhotoError("Use PNG, JPEG, or WebP only.");
      return;
    }
    if (file.size > 10000000) {
      setProfilePhotoError("Use an image under 10 MB.");
      return;
    }
    const [image] = await readImages([file]);
    setPendingProfilePhoto(image ?? null);
  }

  async function handlePasswordSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPasswordError(null);
    setPasswordDone(false);
    setSavingPassword(true);
    try {
      await changePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      await refreshUser();
      setCurrentPassword("");
      setPassword("");
      setPasswordConfirmation("");
      setPasswordDone(true);
    } catch (err) {
      setPasswordError(err instanceof Error ? err.message : "Failed to change password.");
    } finally {
      setSavingPassword(false);
    }
  }

  async function handleRecoveryEmailSubmit(e: React.FormEvent) {
    e.preventDefault();
    setRecoveryMessage(null);
    setRecoveryError(null);
    setSavingRecoveryEmail(true);
    try {
      const result = await updateRecoveryEmail(recoveryEmail);
      await refreshUser();
      setRecoveryCode("");
      setRecoveryMessage(result.message);
    } catch (err) {
      setRecoveryError(err instanceof Error ? err.message : "Failed to update recovery email.");
    } finally {
      setSavingRecoveryEmail(false);
    }
  }

  async function handleRecoveryEmailVerify(e: React.FormEvent) {
    e.preventDefault();
    setRecoveryMessage(null);
    setRecoveryError(null);
    setVerifyingRecoveryEmail(true);
    try {
      await verifyRecoveryEmail(recoveryCode);
      await refreshUser();
      setRecoveryCode("");
      setRecoveryMessage("Recovery email verified.");
    } catch (err) {
      setRecoveryError(err instanceof Error ? err.message : "Failed to verify recovery email.");
    } finally {
      setVerifyingRecoveryEmail(false);
    }
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={crumbs}
        title="My Profile"
        subtitle={subtitle}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <Card className="p-6">
          <div className="mb-5 flex items-center justify-between gap-3">
            <h3 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-warm-900">
              <Mail className="h-4 w-4 text-emerald-600" />
              Account Details
            </h3>
            {!editingProfile && (
              <Button type="button" variant="secondary" onClick={() => setEditingProfile(true)} className="w-auto">
                <PencilLine className="h-4 w-4" /> Edit Profile
              </Button>
            )}
          </div>
          {editingProfile ? <form onSubmit={handleProfileSubmit} className="space-y-4">
            <ImageUploadGallery
              images={profileImages}
              onImagesChange={(images) => {
                setProfileImages(images.slice(-1));
                setProfilePhotoIntent(images.length > 0 ? "replace" : "remove");
              }}
              onFilesSelected={handleProfilePhotoFiles}
              label="Profile Photo"
              emptyText="Profile photo preview appears here after upload."
              error={profilePhotoError}
              variant="avatar"
              uploadLabel={profileImages.length > 0 ? "Change profile picture" : "Upload profile picture"}
              removeLabel="Delete profile picture"
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input label="First Name" value={firstName} onChange={(e) => setFirstName(e.target.value)} className="min-h-11" />
              <Input label="Last Name" value={lastName} onChange={(e) => setLastName(e.target.value)} className="min-h-11" />
            </div>
            <div>
              <div className="text-sm font-semibold text-warm-600">Sign-in email</div>
              <div className="mt-1 text-sm text-warm-400">{user?.email}</div>
            </div>
            <Input label="Contact Number" value={contactNumber} onChange={(e) => setContactNumber(e.target.value)} />
            <div className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-warm-600 select-none tracking-wide">Role / Designation</span>
              <div className="rounded-lg border border-warm-200 bg-warm-50 px-3.5 py-2 text-base font-semibold text-warm-700">
                {user?.role ?? fallbackRole}
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-3 pt-1">
              <Button type="submit" loading={savingProfile} className="w-auto">Save Changes</Button>
              <Button type="button" variant="secondary" onClick={cancelProfileEdit} disabled={savingProfile} className="w-auto">
                <X className="h-4 w-4" /> Cancel
              </Button>
              {profileDone && <span className="text-sm font-semibold text-emerald-600">Saved.</span>}
            </div>
            {profileError && <p className="text-sm font-semibold text-red-600">{profileError}</p>}
          </form> : (
            <div className="space-y-5">
              <div className="flex items-center gap-4">
                <div className="h-16 w-16 shrink-0 overflow-hidden rounded-full border border-warm-200 bg-warm-50">
                  {user?.profile_photo ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={user.profile_photo} alt={personDisplayName(user)} className="h-full w-full object-cover" />
                  ) : null}
                </div>
                <div className="min-w-0">
                  <div className="truncate text-lg font-bold text-warm-900">{user ? personDisplayName(user) : "Profile"}</div>
                  <div className="truncate text-sm text-warm-400">{user?.email}</div>
                </div>
              </div>
              <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><dt className="text-xs font-bold uppercase tracking-wider text-warm-400">Contact Number</dt><dd className="mt-1 text-sm font-semibold text-warm-700">{user?.contact_number || "Not provided"}</dd></div>
                <div><dt className="text-xs font-bold uppercase tracking-wider text-warm-400">Role / Designation</dt><dd className="mt-1 text-sm font-semibold text-warm-700">{user?.role ?? fallbackRole}</dd></div>
              </dl>
              {profileDone && <span className="text-sm font-semibold text-emerald-600">Saved.</span>}
            </div>
          )}
        </Card>

        <Card className="p-6">
          <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2 mb-5">
            <ShieldCheck className="h-4 w-4 text-emerald-600" />
            Recovery Email
          </h3>
          <p className="-mt-3 mb-5 text-sm leading-relaxed text-warm-500">
            {user?.must_set_recovery_email
              ? "Add the recovery email required for first-login setup. No verification code is needed."
              : "Changing an existing recovery email requires a code before the old address is replaced."}
          </p>
          <form onSubmit={handleRecoveryEmailSubmit} className="space-y-4">
            <Input
              label="Recovery Email for Password Resets"
              type="email"
              value={recoveryEmail}
              onChange={(e) => setRecoveryEmail(e.target.value)}
              required
              autoComplete="email"
            />
            <p className="-mt-2 text-xs text-warm-400">
              {user?.must_set_recovery_email
                ? "Enter an address you control for future password recovery."
                : "A verification code will be sent to the new address to confirm ownership."}
            </p>
            <div className="flex items-center gap-3">
              <Button type="submit" loading={savingRecoveryEmail} className="w-auto">
                {user?.must_set_recovery_email ? "Save Recovery Email" : "Send Verification Code"}
              </Button>
              {user?.recovery_email_verified && user.recovery_email === recoveryEmail && (
                <span className="text-sm font-semibold text-emerald-600">Verified.</span>
              )}
            </div>
          </form>

          {!user?.must_set_recovery_email
            && (Boolean(user?.pending_recovery_email) || !user?.recovery_email_verified) && (
            <form onSubmit={handleRecoveryEmailVerify} className="mt-5 space-y-4 border-t border-warm-100 pt-5">
              <Input
                label="Verification Code"
                value={recoveryCode}
                onChange={(e) => setRecoveryCode(e.target.value)}
                required
                inputMode="numeric"
                maxLength={6}
              />
              <Button type="submit" loading={verifyingRecoveryEmail} className="w-auto">Verify Recovery Email</Button>
            </form>
          )}
          {recoveryMessage && <p className="mt-3 text-sm font-semibold text-emerald-600">{recoveryMessage}</p>}
          {recoveryError && <p className="mt-3 text-sm font-semibold text-red-600">{recoveryError}</p>}
        </Card>

        <Card className="p-6">
          <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2 mb-5">
            <KeyRound className="h-4 w-4 text-emerald-600" />
            Change Password
          </h3>
          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <Input label="Current Password" type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} required autoComplete="current-password" />
            <Input label="New Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
            <Input label="Confirm New Password" type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required minLength={8} autoComplete="new-password" />
            <div className="flex items-center gap-3 pt-1">
              <Button type="submit" loading={savingPassword} className="w-auto">Update Password</Button>
              {passwordDone && <span className="text-sm font-semibold text-emerald-600">Password updated.</span>}
            </div>
            {passwordError && <p className="text-sm font-semibold text-red-600">{passwordError}</p>}
          </form>
        </Card>
      </div>

      {pendingProfilePhoto && (
        <ProfilePhotoCropDialog
          image={pendingProfilePhoto.src}
          onCancel={() => setPendingProfilePhoto(null)}
          onApply={(croppedImage) => {
            setProfileImages([{ ...pendingProfilePhoto, src: croppedImage }]);
            setProfilePhotoIntent("replace");
            setPendingProfilePhoto(null);
          }}
        />
      )}
    </div>
  );
}
