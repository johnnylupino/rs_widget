# block_progresswidget

A Moodle dashboard block that displays a modern animated doughnut chart
showing the logged-in user's overall course-completion progress.

**States tracked:** Completed · In progress · Not started

## Requirements

| Item | Minimum |
|---|---|
| Moodle | 4.0 (build 2022041900) |
| PHP | 7.4 |
| Completion tracking | Must be enabled site-wide |

## Installation

### Option A — ZIP upload (recommended)

1. Go to **Site administration → Plugins → Install plugins**
2. Upload `block_progresswidget.zip`
3. Follow the on-screen upgrade steps

### Option B — Manual

```bash
# Unzip into your Moodle blocks directory
unzip block_progresswidget.zip -d /var/www/moodle/blocks/

# Run the Moodle upgrade CLI
php /var/www/moodle/admin/cli/upgrade.php --non-interactive
```

Then visit **Site administration → Notifications** if the CLI isn't available.

### Post-install

1. Enable completion tracking:
   **Site administration → Advanced features → Enable completion tracking ✔**
2. Enable completion on individual courses:
   **Course settings → Completion tracking → Enable**
3. Add the block to the Dashboard:
   **Dashboard → Customise this page → Add a block → Progress widget**

## How it works

### Data — Moodle completion API

No raw SQL. All data comes from official PHP APIs:

| API call | Purpose |
|---|---|
| `enrol_get_my_courses()` | Active enrolled courses for current user |
| `completion_info::is_enabled()` | Check if tracking is on per course |
| `completion_info::is_course_complete()` | Whole-course pass/fail |
| `completion_info::get_activities()` | Trackable activities list |
| `completion_info::get_data()` | Per-activity completion state |

### Caching (MUC)

Progress is cached in `block_progresswidget / userprogress`
(application-level, 5-minute TTL, keyed as `progress_{userid}`).

Cache is **immediately invalidated** via event observers when:
- A course completes (`core\event\course_completed`)
- An activity state changes (`core\event\course_module_completion_updated`)
- An enrolment is created or removed (`user_enrolment_created / _deleted`)

### Front end

- **No Chart.js dependency** — the doughnut is a pure SVG built from
  stacked `<circle>` elements using `stroke-dasharray` / `stroke-dashoffset`,
  the same technique used by Recharts and Tremor.
- **AMD module** (`amd/src/chart.js`) injects the SVG into `.pw-chart-target`
  and triggers CSS-transition animations + a count-up percentage label.
- **Mustache template** (`templates/main.mustache`) renders the header,
  legend rows, divider, and per-course list.
- **Scoped CSS** (`styles.css`) uses CSS custom properties under
  `.block-progresswidget` — no theme pollution, dark-mode aware.

## File structure

```
block_progresswidget/
├── block_progresswidget.php        Main block class
├── version.php                     Plugin metadata (v1.1.0)
├── styles.css                      Scoped CSS — modern design tokens
├── README.md
│
├── amd/
│   └── src/
│       └── chart.js                AMD SVG doughnut renderer (no Chart.js)
│
├── classes/
│   ├── observer.php                Event observer — cache invalidation
│   └── privacy/
│       └── provider.php            GDPR null_provider
│
├── db/
│   ├── caches.php                  MUC cache definition
│   └── events.php                  Event observer registration
│
├── lang/
│   └── en/
│       └── block_progresswidget.php  English strings
│
└── templates/
    └── main.mustache               Block HTML — BEM class names
```

## Changelog

### 1.1.0
- Replaced Chart.js with a lightweight pure-SVG doughnut (stroke-dasharray arcs)
- Added count-up percentage animation in the doughnut hole
- Redesigned card layout: header pill, legend with micro bars, per-course list
- Dark-mode support via CSS custom properties
- Added `prefers-reduced-motion` support

### 1.0.0
- Initial release with Chart.js doughnut + MUC caching + event observers

## Known limitations

- Courses with completion tracking **disabled** show a `—` badge and a flat
  progress bar. Enable tracking in each course's settings to get real data.
- `get_activities()` loops in PHP — for users enrolled in 50+ courses,
  increase the MUC TTL in `db/caches.php` (default 300 s).
- AMD requires Moodle's `core/chartjs-lazy` to be absent from the dependency
  list — this plugin no longer needs it, but if your theme auto-loads it
  there is no conflict.

## Licence

GNU GPL v3 or later — https://www.gnu.org/copyleft/gpl.html
