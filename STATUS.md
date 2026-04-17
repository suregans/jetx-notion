# JetX AI Hub — Project Status

**Last updated:** 2026-04-17 (session conclude)
**Current version:** 4.0.0
**Deliverable:** `jetx-ai-hub-v4.zip` (29 files, 50.3 KB)

---

## Plugin Overview

WordPress plugin that pulls a Notion database into a live, searchable, filterable directory on the front end. Fully auto-configures from WP Admin — no PHP file editing required at any point.

**Shortcode:** `[jetx_ai_hub]`

---

## Current Status

| Area | Status | Notes |
|---|---|---|
| Activation bug fix | ✅ Complete | PHP 7.4 gate, defensive activation hook |
| Auto-schema detection | ✅ Complete | `GET /databases/{id}` → WP Admin toggle |
| Branding to WP Admin | ✅ Complete | No longer in config.php |
| Graph view (Sigma.js) | ✅ Complete | Cat → Sub-cat → Tool, toggle button |
| Dynamic layouts | ✅ Complete | All 4 layouts + graph use type-based rendering |
| Dynamic filter dropdowns | ✅ Complete | Controls built from first 4 active select fields |
| Staging install / live test | ⏳ Pending | Top priority — test full flow on WP+Notion |
| GitHub push | ⏳ Pending | Run push-to-github.ps1 locally (script ready) |

---

## WP Admin Tabs (v4.0)

| Tab | Slug | What it controls |
|---|---|---|
| 🔌 Connection | `connection` | API token, DB ID, cache interval, refresh/flush |
| 🔍 Fields | `fields` | Detect Notion columns; toggle on/off |
| ⚙️ Settings | `settings` | Branding, max pages, graph field config |
| 📋 Columns | `columns` | Visible columns, display labels, column order |
| 🔀 Filters | `filters` | Notion API filter rules + sort order |
| 🎨 Display | `display` | Layout, theme, UI toggles |

---

## Setup Flow (v4.0 — for any client database)

1. Install plugin → go to **🔌 Connection** → enter API token + Database ID → Save
2. Go to **🔍 Fields** → click **Detect Fields from Notion** → toggle which columns to show → Save
3. Go to **⚙️ Settings** → enter client branding name, URL, admin title → Save
4. Go to **⚙️ Settings** → select Category Field + Sub-category Field for graph → Save
5. Add `[jetx_ai_hub]` shortcode to any page
6. Click the **📋 Table / 🔗 Graph** toggle on the front end to switch views

No PHP editing at any step.

---

## wp_options Keys (v4.0)

| Key | Set by | Contains |
|---|---|---|
| `jetx_hub_token` | Connection | Notion API secret token |
| `jetx_hub_db_id` | Connection | Notion database ID |
| `jetx_hub_cache_minutes` | Connection | Auto-refresh interval |
| `jetx_hub_detected_schema` | Fields (auto) | Raw Notion property names + types |
| `jetx_hub_active_fields` | Fields | Admin toggles (key → bool) |
| `jetx_hub_settings` | Settings | Branding name/URL, admin title, menu label, max_pages |
| `jetx_hub_columns` | Columns | Visibility, labels, order |
| `jetx_hub_filters` | Filters | Notion API filter rules |
| `jetx_hub_sorts` | Filters | Notion API sort rules |
| `jetx_hub_display` | Display | Layout, theme, feature toggles, graph field config |

---

## File Structure (v4.0)

```
jetx-ai-hub-v3/
├── jetx-ai-hub.php          Bootstrap, PHP version gate, constants, lifecycle hooks
├── config.php               API constants, cache constants, branding fallback defaults + getters
├── uninstall.php            Clean option deletion on plugin removal
├── admin/
│   ├── admin.php            Menu (dynamic label), asset enqueue, page router
│   ├── save-handlers.php    All POST handlers (one per tab + detect action)
│   └── views/
│       ├── tab-connection.php
│       ├── tab-fields.php   ← NEW: Detect + toggle Notion columns
│       ├── tab-settings.php ← NEW: Branding, max pages, graph config
│       ├── tab-columns.php
│       ├── tab-filters.php
│       ├── tab-display.php
│       └── tab-schema.php   (legacy v3 tab — replaced by tab-fields.php)
├── includes/
│   ├── notion-schema.php    ← NEW: Auto-detect schema via Notion API
│   ├── property-defs.php    Dynamic from detected schema; hardcoded fallback
│   ├── api.php              Notion API layer (max_pages from wp_options)
│   ├── cache.php            Transient cache, cron, mutex
│   └── shortcode.php        [jetx_ai_hub], view toggle, dynamic helpers
├── public/views/
│   ├── controls.php         Dynamic filter dropdowns (data-findex)
│   ├── layout-table.php     Type-based cell rendering
│   ├── layout-gallery.php   Dynamic badge fields
│   ├── layout-list.php      Dynamic meta badges
│   ├── layout-board.php     Dynamic group-by
│   └── layout-graph.php     ← NEW: Sigma.js graph container + JSON data
└── assets/
    ├── css/admin.css
    ├── css/frontend.css
    ├── css/graph.css         ← NEW: Graph container, legend, toggle button
    ├── js/admin.js
    ├── js/frontend.js        Updated: view toggle, dynamic data-f* attrs
    └── js/graph.js           ← NEW: Sigma.js init, layout, interactivity
```

---

## Priorities

1. ⏳ Run `push-to-github.ps1` locally — commit + tag v4.0.0 → push to GitHub
2. ⏳ Install on staging site — test full flow: token → detect → toggle → shortcode → graph view
3. 💡 Future: "Test Connection" button that validates Notion property names against live DB
4. 💡 Future: ForceAtlas2 layout with graphology-layout-force for better graph aesthetics

---

## Open Decisions

| Decision | Status |
|---|---|
| Branding in config.php vs WP Admin | Resolved — moved to WP Admin (Settings tab) |
| Schema: manual vs auto-detect | Resolved — full auto-detect, no manual entry |
| Graph hierarchy | Resolved — Category → Sub-category → Tool |
| Version tag | 4.0.0 |
