# Live Android Emulator Testing

- This Windows workstation has Android Studio, Android SDK at `%LOCALAPPDATA%\Android\Sdk`, and the `Medium_Phone_API_37.0` AVD. `adb` and `emulator` are on the user `PATH`.
- Start it with `emulator -avd Medium_Phone_API_37.0`; wait for `adb devices -l` to show `device` before testing.
- From `mobile/`, run `npm run android` to launch Expo on the emulator.
- For the local Laravel API, use `http://10.0.2.2:8000` from the emulator. Use the PC's LAN IP for a physical phone.
