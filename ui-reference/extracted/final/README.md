# Maharani Weddings Academy — Production Handoff v2.0
### For Antigravity / LearnDash integration

---

## What this package is

The **shippable** version of the Academy front-end. Audit blockers from v1.0 are all resolved:

- ✅ **Mobile nav works** (hamburger + drawer with focus management, Escape-to-close)
- ✅ **WCAG AA pass** — global `:focus-visible`, darker `--gray-400`, no functional emoji
- ✅ **Reset-password success + expired states wired up** (URL-driven, no commented HTML)
- ✅ **Zero invented copy** — every fake string is now `{{TEMPLATE_VAR}}`
- ✅ **No hardcoded production URLs**
- ✅ **iOS input zoom prevented** (inputs ≥ 16px)
- ✅ **Empty + error states designed and present in markup**
- ✅ **`prefers-reduced-motion` honored everywhere**
- ✅ **Print styles for the certificate**

**Open `index.html`** to land on a state explorer — every page with every variant linked one click away.

---

## Files

| File | Purpose |
|------|---------|
| `index.html` | State explorer + audit checklist + template-var reference. Start here. |
| `styles.css` | Shared design system v2.0 |
| `academy.js` | Shared front-end behavior — drawer + password toggles |
| `login.html` | Sign In — has error-alert markup region |
| `reset-password.html` | Reset — `?reset=` query param drives `set` / `success` / `expired` |
| `dashboard.html` | Post-login home — `?state=empty` previews the new-user state |
| `course.html` | Course content — 4 lesson-row variants documented in source |
| `lesson.html` | Lesson player — drawer contains lesson TOC on mobile |
| `certificate.html` | Print-ready A4 landscape certificate |
| `favicon.svg` | Stand-in favicon — replace with real brand mark |

---

## How states are exposed

Each page renders all of its states in markup; the URL decides which one is visible. This lets QA — and Antigravity — see every state without faking server data.

| URL | Renders |
|-----|---------|
| `login.html` | Default form |
| `reset-password.html` | "Set new password" (state 1) |
| `reset-password.html?reset=success` | "Password updated" (state 2) |
| `reset-password.html?reset=expired` | "Link expired" (state 3, with a re-request form) |
| `dashboard.html` | Returning learner |
| `dashboard.html?state=empty` | New-user empty state |
| `lesson.html` | Default · click **Mark as complete** to reveal the XP banner |

Antigravity should **remove the QA-only routers** (`?state=empty` toggle in dashboard.html, the URLSearchParams reader in reset-password.html) once server-side state takes over, OR leave them in — they're harmless when the server doesn't pass the param.

---

## Template variable conventions

Format is `{{VAR_NAME}}` (Mustache-style — works in PHP, Blade, Twig, Latte, you-name-it).

Optional default values use a pipe: `{{ERROR_MESSAGE|Default copy here}}`.

The full mapping table is in `index.html` under "Template variable map" — that's the canonical reference. Highlights:

```
{{USER_DISPLAY_NAME}}   ← $current_user->display_name
{{USER_INITIAL}}        ← first letter of display name, uppercase
{{XP_CURRENT}}          ← user meta `mw_xp_total`
{{COURSE_PROGRESS_PCT}} ← learndash_course_progress($user_id, $course_id)['percentage']
{{COURSE_RING_OFFSET}}  ← round(364.4 * (1 - pct / 100), 1)
{{REDIRECT_TO}}         ← actual production dashboard route (NOT /dashboard)
{{GOOGLE_OAUTH_URL}}    ← OAuth start URL from your Google login plugin
```

---

## Accessibility notes

- `:focus-visible` is opted out of `outline` and given a 3px pink-tinted box-shadow — visible on white surfaces. On dark surfaces (hero buttons, lesson topbar), it switches to a brighter pink ring.
- `prefers-reduced-motion: reduce` shortens every transition to 0.001ms and disables all `:hover transform` lifts. No motion-sickness risk.
- Skip-to-main link is the first focusable element on every logged-in page.
- Drawer manages `aria-hidden`, `aria-controls`, returns focus to the trigger on close, and closes on Escape + backdrop click.
- All decorative SVGs carry `aria-hidden="true"`. All interactive SVG-only buttons carry `aria-label`.
- Password toggle updates both `aria-label` ("Show password" ↔ "Hide password") **and** `aria-pressed`.
- Progress bars/rings have `role="progressbar"` with `aria-valuenow`/`min`/`max`.

---

## What you still need to provide

These are the legitimate brand decisions I won't fake:

1. **Level names** (`{{LEVEL_NAME}}` for levels 1, 2, 3, 4, 5+). v1 invented "Rising Planner" etc. — gone.
2. **Real badge artwork.** SVGs in the badge grid are functional placeholders (book, flame, lock).
3. **Login hero copy.** v1's "Master the art of South Asian Weddings" tagline + "2,400+ Professionals" stats were invented. The hero now has `{{HERO_HEADLINE_LINE_1}}` etc. waiting for your team's wording.
4. **Course cover imagery.** Continue-card image area uses a striped placeholder marked "COURSE COVER" — replace with real images.
5. **Real password rules.** Currently length / upper / number / special. If your security policy differs, adjust the regex + UI in `reset-password.html`.

---

## LearnDash integration notes (unchanged from v1 except for URLs)

### Login (`login.html`)
- Form `action="{{LOGIN_ACTION_URL}}"` → typically `/wp-login.php`
- Field names: `log`, `pwd`, `rememberme`, `redirect_to`
- Use LoginPress or a custom auth template to replace the default WP login UI with this design.
- Google OAuth: hook the `{{GOOGLE_OAUTH_URL}}` into Nextend Social Login / WP Google Login / your plugin of choice. The link must originate the OAuth flow server-side.

### Reset password (`reset-password.html`)
- State 1 (set): `pass1`, `pass2`, `rp_key`, `rp_login` — standard WP fields. Action → `/wp-login.php?action=resetpass`.
- State 2 (success): WP redirects to this page with `?reset=success` after success.
- State 3 (expired): redirect with `?reset=expired` when `rp_key` is missing/expired. Form posts to `/wp-login.php?action=lostpassword`.

### Dashboard / Course / Lesson
Same hooks as v1 (XP via user meta, `learndash_course_progress()`, `ld_get_course_lessons_list()`, `learndash_process_mark_complete()`).

The lesson page's **Mark complete** button currently mutates UI client-side only — wire its click handler to call `learndash_process_mark_complete()` and, on success, run the same UI changes (`.done` class on the button + `.show` on the XP banner). No `scrollIntoView` (removed — was jarring).

---

## Browser support

Same matrix as v1 (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+, Mobile Safari/Chrome). Adds:

- `:focus-visible` — Safari 15.4+. Older Safari falls back to the system `:focus` ring (still accessible).
- `aspect-ratio` (used in video + certificate) — Safari 15+.

---

*Production design by Claude (Anthropic) for Crissius · Handoff v2.0*
