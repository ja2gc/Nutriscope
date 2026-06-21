# Nutriscope FSS Mobile

React Native app (Expo) for the Food Service Supervisor role.

## Prerequisites

- Node.js 18+
- [Expo Go](https://expo.dev/go) installed on your phone
- Phone and PC on the **same Wi-Fi network**
- Windows Firewall allowing inbound TCP on port 8000

## Setup

1. Copy `.env.example` to `.env` and set your PC's LAN IP:

```
EXPO_PUBLIC_API_URL=http://192.168.1.X:8000
```

Find your LAN IP with `ipconfig` — look for IPv4 Address under your Wi-Fi adapter.

2. Start the Laravel backend (from the repo root, not this folder):

```
php artisan serve --host=0.0.0.0 --port=8000
```

The `--host=0.0.0.0` flag makes it reachable from your phone.

3. Start the Expo dev server (from this folder):

```
npx expo start
```

4. Scan the QR code in the terminal with the **Expo Go** app on your phone.

## Firewall (Windows)

If your phone can't reach the backend, open port 8000:

```
netsh advfirewall firewall add rule name="Laravel Dev" dir=in action=allow protocol=TCP localport=8000
```
