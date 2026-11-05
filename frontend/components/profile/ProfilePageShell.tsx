"use client";

import React, { useEffect, useState } from "react";
import { PencilLine } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { InfoHint } from "@/components/ui/InfoHint";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { ImageUploadGallery, imagesFromSrcs, imageSrcs, readImages, type UploadImage } from "@/components/ui/ImageUploadGallery";
import { useAuth } from "@/contexts/AuthContext";
import {
  updateProfile,
  changePassword,
  updateRecoveryEmail,
  verifyRecoveryEmail,
  removeRecoveryEmail,
  cancelPendingRecoveryEmail,
  type User,
} from "@/services/authService";
import {
  changedPersonNameFields,
  personNameFormValues,
} from "@/lib/personName";
import { ProfilePhotoCropDialog } from "@/components/profile/ProfilePhotoCropDialog";
import { profilePhotoUpdate, type ProfilePhotoIntent } from "@/components/profile/profilePhotoUpdate";
import { personDisplayName } from "@/lib/personName";
import { validateUploadFile } from "@/lib/uploadValidation";

type ProfilePageShellProps = {
  crumbs: [string, string?][];
  fallbackRole: User["role"];
};

export function ProfilePageShell({ crumbs, fallbackRole }: ProfilePageShellProps) {
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
  const [editingPassword, setEditingPassword] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordDone, setPasswordDone] = useState(false);

  const [recoveryEmail, setRecoveryEmail] = useState("");
  const [recoveryCode, setRecoveryCode] = useState("");
  const [recoveryStep, setRecoveryStep] = useState<"view" | "actions" | "email" | "code">("view");
  const [cancellingRecoveryEmail, setCancellingRecoveryEmail] = useState(false);
  const [savingRecoveryEmail, setSavingRecoveryEmail] = useState(false);
  const [verifyingRecoveryEmail, setVerifyingRecoveryEmail] = useState(false);
  const [removingRecoveryEmail, setRemovingRecoveryEmail] = useState(false);
  const [recoveryMessage, setRecoveryMessage] = useState<string | null>(null);
  const [recoveryError, setRecoveryError] = useState<string | null>(null);
  const hasRecoveryEmail = Boolean(user?.recovery_email_verified && user.recovery_email);
  const recoveryBusy = savingRecoveryEmail || verifyingRecoveryEmail || removingRecoveryEmail || cancellingRecoveryEmail;

  useEffect(() => {
    if (!profileDone && !passwordDone && !recoveryMessage) return;
    const timer = window.setTimeout(() => {
      setProfileDone(false);
      setPasswordDone(false);
      setRecoveryMessage(null);
    }, 3000);
    return () => window.clearTimeout(timer);
  }, [profileDone, passwordDone, recoveryMessage]);

  useEffect(() => {
    if (user) {
      const nameValues = personNameFormValues(user);
      setFirstName(nameValues.firstName);
      setLastName(nameValues.lastName);
      setContactNumber(user.contact_number ?? "");
      setRecoveryEmail(
        user.pending_recovery_email ?? (user.recovery_email_verified ? "" : user.recovery_email ?? ""),
      );
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
    const validation = validateUploadFile(file, {
      maxBytes: 2 * 1024 * 1024,
      allowedTypes: ["image/png", "image/jpeg", "image/webp"],
    });
    if (!validation.valid) {
      setProfilePhotoError(validation.error);
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
      setEditingPassword(false);
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
      setRecoveryStep("code");
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
      setRecoveryEmail("");
      setRecoveryStep("view");
      setRecoveryMessage("Recovery email updated.");
    } catch (err) {
      setRecoveryError(err instanceof Error ? err.message : "Failed to verify recovery email.");
    } finally {
      setVerifyingRecoveryEmail(false);
    }
  }

  async function handleRecoveryEmailRemove() {
    if (!window.confirm("Remove your recovery email? Password reset will be unavailable until a new address is verified.")) return;
    setRecoveryMessage(null);
    setRecoveryError(null);
    setRemovingRecoveryEmail(true);
    try {
      const result = await removeRecoveryEmail();
      setRecoveryEmail("");
      setRecoveryCode("");
      await refreshUser();
      setRecoveryMessage(result.message);
      setRecoveryStep("view");
    } catch (err) {
      setRecoveryError(err instanceof Error ? err.message : "Failed to remove recovery email.");
    } finally {
      setRemovingRecoveryEmail(false);
    }
  }

  function startRecoveryEmailChange() {
    setRecoveryEmail("");
    setRecoveryCode("");
    setRecoveryMessage(null);
    setRecoveryError(null);
    setRecoveryStep("email");
  }

  async function cancelRecoveryEmailEdit() {
    setRecoveryError(null);
    setCancellingRecoveryEmail(true);
    try {
      if (recoveryStep === "code" || user?.pending_recovery_email || (!user?.recovery_email_verified && user?.recovery_email)) {
        await cancelPendingRecoveryEmail();
        await refreshUser();
      }
      setRecoveryEmail("");
      setRecoveryCode("");
      setRecoveryMessage(null);
      setRecoveryStep("view");
    } catch (err) {
      setRecoveryError(err instanceof Error ? err.message : "Failed to cancel recovery email change.");
    } finally {
      setCancellingRecoveryEmail(false);
    }
  }

  function cancelPasswordEdit() {
    setCurrentPassword("");
    setPassword("");
    setPasswordConfirmation("");
    setPasswordError(null);
    setEditingPassword(false);
  }

  return (
    <div className="mx-auto w-full max-w-5xl space-y-6 font-sans">
      <PageHeader
        crumbs={crumbs}
        title="My Profile"
      />

      <Card className="divide-y divide-warm-200">
        <section aria-labelledby="profile-account-heading" className="min-w-0 space-y-6 p-4 sm:p-8">
          <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 sm:gap-5">
            <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-center gap-3 sm:gap-4">
              <ImageUploadGallery
                images={profileImages}
                onImagesChange={(images) => {
                  setProfileImages(images.slice(-1));
                  setProfilePhotoIntent(images.length > 0 ? "replace" : "remove");
                  setEditingProfile(true);
                  setProfileDone(false);
                }}
                onFilesSelected={handleProfilePhotoFiles}
                label="Profile Photo"
                avatarFallback={<span role="img" aria-label="Default profile picture">{user ? personDisplayName(user).trim().charAt(0).toUpperCase() : ""}</span>}
                error={profilePhotoError}
                maxBytes={2 * 1024 * 1024}
                allowedTypes={["image/png", "image/jpeg", "image/webp"]}
                maxFiles={1}
                disabled={savingProfile}
                variant="avatar"
                uploadLabel={profileImages.length > 0 ? "Change profile picture" : "Choose profile picture"}
                removeLabel="Delete profile picture"
              />
              <div className="min-w-0">
                <div className="break-words text-xl font-bold text-warm-900">{user ? personDisplayName(user) : "Profile"}</div>
                <div className="mt-1 text-sm text-warm-500">{user?.role ?? fallbackRole}</div>
              </div>
            </div>
            {!editingProfile && <Button type="button" variant="secondary" aria-label="Edit Profile" onClick={() => { setProfileDone(false); setEditingProfile(true); }} className="min-h-11 w-auto px-3 sm:px-4">
              <PencilLine className="hidden h-4 w-4 sm:block" aria-hidden="true" /><span className="sm:hidden">Edit</span><span className="hidden sm:inline">Edit Profile</span>
            </Button>}
          </div>
          <h2 id="profile-account-heading" className="text-base font-bold text-warm-900">Account details</h2>
          {editingProfile ? <form onSubmit={handleProfileSubmit} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input label="First Name" value={firstName} onChange={(e) => setFirstName(e.target.value)} className="min-h-11" />
              <Input label="Last Name" value={lastName} onChange={(e) => setLastName(e.target.value)} className="min-h-11" />
            </div>
            <div>
              <div className="text-sm font-semibold text-warm-600">Sign-in email</div>
              <div className="mt-1 break-all text-sm text-warm-500">{user?.email}</div>
            </div>
            <Input label="Contact Number" value={contactNumber} onChange={(e) => setContactNumber(e.target.value)} />
            <div className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-warm-600 select-none tracking-wide">Role / Designation</span>
              <div className="rounded-lg border border-warm-200 bg-warm-50 px-3.5 py-2 text-base font-semibold text-warm-700">
                {user?.role ?? fallbackRole}
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 pt-2 sm:gap-3">
              <Button type="button" size="sm" variant="secondary" onClick={cancelProfileEdit} disabled={savingProfile} className="min-h-11 w-auto">
                Cancel
              </Button>
              <Button type="submit" size="sm" loading={savingProfile} className="min-h-11 w-auto">Save Changes</Button>
            </div>
            {profileError && <p className="text-sm font-semibold text-red-600">{profileError}</p>}
          </form> : (
            <div className="space-y-6">
              <div>
                <dl className="grid min-w-0 grid-cols-1 gap-5 sm:grid-cols-2">
                  <div className="min-w-0"><dt className="text-sm text-warm-500">Sign-in email</dt><dd className="mt-1 break-all text-sm font-semibold text-warm-900">{user?.email}</dd></div>
                  <div><dt className="text-sm text-warm-500">Contact number</dt><dd className="mt-1 break-words text-sm font-semibold text-warm-900">{user?.contact_number || "Not provided"}</dd></div>
                </dl>
              </div>
              {profileDone && <span className="text-sm font-semibold text-emerald-600">Saved.</span>}
            </div>
          )}
        </section>

        <section aria-labelledby="profile-recovery-heading" className="min-w-0 space-y-5 p-4 sm:p-8">
          <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 sm:gap-x-5">
            <div className="grid min-h-11 min-w-0 grid-cols-[minmax(0,auto)_2.75rem] items-center justify-start">
              <h2 id="profile-recovery-heading" className="text-base font-bold text-warm-900">Recovery email</h2>
              <InfoHint label="About recovery email" title="Recovery email" className="min-h-11 min-w-11">
                A six-digit verification code is required before a new recovery email becomes active. An existing verified address stays active until its replacement is verified.
              </InfoHint>
            </div>
            {(recoveryStep === "view" || recoveryStep === "actions") && (
              hasRecoveryEmail ? <Popover open={recoveryStep === "actions"} onOpenChange={open => { setRecoveryMessage(null); setRecoveryError(null); setRecoveryStep(open ? "actions" : "view"); }}>
                <PopoverTrigger asChild>
                  <Button type="button" variant="secondary" aria-label="Edit Recovery Email" className="col-start-2 row-start-1 min-h-11 w-auto px-3 sm:px-4">Edit</Button>
                </PopoverTrigger>
                <PopoverContent align="end" onCloseAutoFocus={event => event.preventDefault()} className="w-[min(16rem,calc(100vw-2rem))] p-1">
                  <Button type="button" variant="ghost" aria-label="Change Recovery Email" onClick={startRecoveryEmailChange} className="min-h-11 w-full justify-start">Change</Button>
                  <Button type="button" variant="ghost" aria-label="Remove Recovery Email" disabled={recoveryBusy} onClick={() => void handleRecoveryEmailRemove()} className="min-h-11 w-full justify-start text-red-700">Remove</Button>
                  <Button type="button" variant="ghost" disabled={recoveryBusy} onClick={() => void cancelRecoveryEmailEdit()} className="min-h-11 w-full justify-start">Cancel</Button>
                </PopoverContent>
              </Popover> : <Button type="button" variant="secondary" aria-label="Add Recovery Email" onClick={startRecoveryEmailChange} className="col-start-2 row-start-1 min-h-11 w-auto px-3 sm:px-4">Add</Button>
            )}
            {(recoveryStep === "view" || recoveryStep === "actions") && <div className="col-span-2 min-w-0" aria-label="Current Recovery Email">
              <p className="break-all text-sm font-semibold text-warm-700">{hasRecoveryEmail ? user?.recovery_email : "Not set"}</p>
            </div>}
          </div>
          {recoveryStep === "email" && <form onSubmit={handleRecoveryEmailSubmit} className="space-y-4">
            <Input label={hasRecoveryEmail ? "New Recovery Email" : "Recovery Email"} type="email" value={recoveryEmail}
              onChange={event => setRecoveryEmail(event.target.value)} required autoComplete="email" autoFocus disabled={recoveryBusy} />
            <div className="flex items-center justify-end gap-2 sm:gap-3">
              <Button type="button" size="sm" variant="secondary" disabled={recoveryBusy} onClick={() => void cancelRecoveryEmailEdit()} className="min-h-11 w-auto">Cancel</Button>
              <Button type="submit" size="sm" aria-label="Send Verification Code" loading={savingRecoveryEmail} disabled={recoveryBusy} className="min-h-11 w-auto">
                <span className="sm:hidden">Send code</span><span className="hidden sm:inline">Send Verification Code</span>
              </Button>
            </div>
          </form>}
          {recoveryStep === "code" && <form onSubmit={handleRecoveryEmailVerify} className="space-y-4">
            <p className="break-words text-sm text-warm-500">Enter the code sent to <span className="break-all font-semibold">{recoveryEmail}</span>.</p>
            <Input label="Verification Code" value={recoveryCode} onChange={event => setRecoveryCode(event.target.value)}
              required inputMode="numeric" maxLength={6} autoComplete="one-time-code" autoFocus disabled={recoveryBusy} />
            <div className="flex items-center justify-end gap-2 sm:gap-3">
              <Button type="button" size="sm" variant="secondary" disabled={recoveryBusy} onClick={() => void cancelRecoveryEmailEdit()} className="min-h-11 w-auto">Cancel</Button>
              <Button type="submit" size="sm" aria-label="Verify Recovery Email" loading={verifyingRecoveryEmail} disabled={recoveryBusy} className="min-h-11 w-auto">
                <span className="sm:hidden">Verify</span><span className="hidden sm:inline">Verify Recovery Email</span>
              </Button>
            </div>
          </form>}
          {recoveryMessage && <p role="status" className="text-sm font-semibold text-emerald-600">{recoveryMessage}</p>}
          {recoveryError && <p role="alert" className="text-sm font-semibold text-red-600">{recoveryError}</p>}
        </section>

        <section aria-labelledby="profile-password-heading" className="min-w-0 space-y-5 p-4 sm:p-8">
          <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 sm:gap-5">
            <h2 id="profile-password-heading" className="text-base font-bold text-warm-900">Password</h2>
            {!editingPassword && <Button type="button" variant="secondary" aria-label="Change Password" onClick={() => { setPasswordDone(false); setEditingPassword(true); }} className="min-h-11 w-auto px-3 sm:px-4">
              <span className="sm:hidden">Change</span><span className="hidden sm:inline">Change Password</span>
            </Button>}
          </div>
          {editingPassword && <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <Input label="Current Password" type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} required autoComplete="current-password" />
            <Input label="New Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
            <Input label="Confirm New Password" type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required minLength={8} autoComplete="new-password" />
            <div className="flex items-center justify-end gap-2 pt-1 sm:gap-3">
              <Button type="button" size="sm" variant="secondary" onClick={cancelPasswordEdit} className="min-h-11 w-auto">Cancel</Button>
              <Button type="submit" size="sm" aria-label="Update Password" loading={savingPassword} className="min-h-11 w-auto"><span className="sm:hidden">Save</span><span className="hidden sm:inline">Update Password</span></Button>
            </div>
            {passwordError && <p className="text-sm font-semibold text-red-600">{passwordError}</p>}
          </form>}
          {passwordDone && <p className="text-sm font-semibold text-emerald-600">Password updated.</p>}
        </section>
      </Card>

      {pendingProfilePhoto && (
        <ProfilePhotoCropDialog
          image={pendingProfilePhoto.src}
          onCancel={() => setPendingProfilePhoto(null)}
          onApply={(croppedImage) => {
            setProfileImages([{ ...pendingProfilePhoto, src: croppedImage }]);
            setProfilePhotoIntent("replace");
            setEditingProfile(true);
            setProfileDone(false);
            setPendingProfilePhoto(null);
          }}
        />
      )}
    </div>
  );
}
