const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const test = require('node:test');

const media = readFileSync(join(__dirname, '..', 'components', 'AnnouncementMedia.tsx'), 'utf8');
const feed = readFileSync(join(__dirname, '..', 'components', 'AnnouncementsScreen.tsx'), 'utf8');
const dashboard = readFileSync(join(__dirname, '..', 'app', '(tabs)', 'index.tsx'), 'utf8');
const procurement = readFileSync(join(__dirname, '..', 'app', '(tabs)', 'procurement.tsx'), 'utf8');

test('mobile announcements preserve image ratio in a fixed responsive blurred frame', () => {
  assert.match(media, /blurRadius=/);
  assert.match(media, /resizeMode="cover"/);
  assert.match(media, /resizeMode="contain"/);
  assert.match(media, /Math\.min\(360, Math\.max\(180,/);
  assert.match(feed, /<AnnouncementMedia/);
  assert.match(dashboard, /<AnnouncementMedia/);
  assert.match(procurement, /blurRadius=\{24\}[\s\S]*resizeMode="cover"[\s\S]*resizeMode="contain"/);
  assert.match(procurement, /Math\.min\(720, Math\.max\(280,/);
});

test('mobile announcement author avatars use scoped profile photo URLs', () => {
  assert.match(media, /authenticatedImageSource/);
  assert.match(media, /profile_photo/);
  assert.match(feed, /<AnnouncementAuthorAvatar/);
  assert.match(dashboard, /<AnnouncementAuthorAvatar/);
  assert.doesNotMatch(`${feed}\n${dashboard}`, /Announcement author/);
});
