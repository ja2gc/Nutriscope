"use client";

import React, { useEffect, useState } from "react";
import { UserCog, Mail, KeyRound, ShieldCheck } from "lucide-react";
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

type ProfilePageShellProps = {
  crumbs: [string, string?][];
  subtitle: string;
  fallbackRole: User["role"];
};

export function ProfilePageShell({ crumbs, subtitle, fallbackRole }: ProfilePageShellProps) {
  const { user, refreshUser } = useAuth();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [contactNumber, setContactNumber] = useState("");
  const [profileImages, setProfileImages] = useState<UploadImage[]>([]);
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [profilePhotoError, setProfilePhotoError] = useState<string | null>(null);
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
      setName(user.name);
      setEmail(user.email);
      setContactNumber(user.contact_number ?? "");
      setRecoveryEmail(user.recovery_email ?? "");
      setProfileImages(imagesFromSrcs(user.profile_photo ? [user.profile_photo] : [], "Profile photo"));
    }
  }, [user]);

  async function handleProfileSubmit(e: React.FormEvent) {
    e.preventDefault();
    setProfileError(null);
    setProfileDone(false);
    setSavingProfile(true);
    try {
      await updateProfile({
        name,
        email,
        contact_number: contactNumber.trim() || null,
        profile_photo: imageSrcs(profileImages)[0] ?? null,
      });
      await refreshUser();
      setProfileDone(true);
    } catch (err) {
      setProfileError(err instanceof Error ? err.message : "Failed to update profile.");
    } finally {
      setSavingProfile(false);
    }
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
    if (file.size > 220000) {
      setProfilePhotoError("Use an image under 220 KB.");
      return;
    }
    const [image] = await readImages([file]);
    setProfileImages(image ? [image] : []);
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
      await updateRecoveryEmail(recoveryEmail);
      await refreshUser();
      setRecoveryCode("");
      setRecoveryMessage("Verification code sent.");
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
        icon={<UserCog className="h-5 w-5 text-emerald-600" />}
        subtitle={subtitle}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <Card className="p-6">
          <h3 className="text-xs font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2 mb-5">
            <Mail className="h-4 w-4 text-emerald-600" />
            Account Details
          </h3>
          <form onSubmit={handleProfileSubmit} className="space-y-4">
            <ImageUploadGallery
              images={profileImages}
              onImagesChange={(images) => setProfileImages(images.slice(-1))}
              onFilesSelected={handleProfilePhotoFiles}
              label="Profile Photo"
              emptyText="Profile photo preview appears here after upload."
              error={profilePhotoError}
              variant="avatar"
              uploadLabel={profileImages.length > 0 ? "Change profile picture" : "Upload profile picture"}
              removeLabel="Delete profile picture"
            />
            <Input label="Full Name" value={name} onChange={(e) => setName(e.target.value)} required />
            <Input label="Sign-in Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            <p className="-mt-2 text-[10px] text-warm-400">
              Use this email when signing in. Password reset links are sent to your verified recovery email below.
            </p>
            <Input label="Contact Number" value={contactNumber} onChange={(e) => setContactNumber(e.target.value)} />
            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-semibold text-warm-600 select-none tracking-wide">Role / Designation</span>
              <div className="rounded-lg border border-warm-200 bg-warm-50 px-3.5 py-2 text-sm font-semibold text-warm-700">
                {user?.role ?? fallbackRole}
              </div>
            </div>
            <div className="flex items-center gap-3 pt-1">
              <Button type="submit" loading={savingProfile} className="w-auto">Save Changes</Button>
              {profileDone && <span className="text-xs font-semibold text-emerald-600">Saved.</span>}
            </div>
            {profileError && <p className="text-xs font-semibold text-red-600">{profileError}</p>}
          </form>
        </Card>

        <Card className="p-6">
          <h3 className="text-xs font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2 mb-5">
            <ShieldCheck className="h-4 w-4 text-emerald-600" />
            Recovery Email
          </h3>
          <p className="-mt-3 mb-5 text-xs leading-relaxed text-warm-500">
            Used only for password reset links and account recovery. It must be verified before it can receive reset emails.
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
            <p className="-mt-2 text-[10px] text-warm-400">
              Enter an email you have access to. A verification code will be sent to confirm ownership.
            </p>
            <div className="flex items-center gap-3">
              <Button type="submit" loading={savingRecoveryEmail} className="w-auto">Send Verification Code</Button>
              {user?.recovery_email_verified && user.recovery_email === recoveryEmail && (
                <span className="text-xs font-semibold text-emerald-600">Verified.</span>
              )}
            </div>
          </form>

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
          {recoveryMessage && <p className="mt-3 text-xs font-semibold text-emerald-600">{recoveryMessage}</p>}
          {recoveryError && <p className="mt-3 text-xs font-semibold text-red-600">{recoveryError}</p>}
        </Card>

        <Card className="p-6">
          <h3 className="text-xs font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2 mb-5">
            <KeyRound className="h-4 w-4 text-emerald-600" />
            Change Password
          </h3>
          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <Input label="Current Password" type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} required autoComplete="current-password" />
            <Input label="New Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
            <Input label="Confirm New Password" type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required minLength={8} autoComplete="new-password" />
            <div className="flex items-center gap-3 pt-1">
              <Button type="submit" loading={savingPassword} className="w-auto">Update Password</Button>
              {passwordDone && <span className="text-xs font-semibold text-emerald-600">Password updated.</span>}
            </div>
            {passwordError && <p className="text-xs font-semibold text-red-600">{passwordError}</p>}
          </form>
        </Card>
      </div>
    </div>
  );
}
