# JetX AI Hub — Notion to WordPress Plugin

**Version:** 3.0.0 · **Requires:** WordPress 5.8+ · **PHP:** 7.4+

Connect any Notion database to your WordPress site as a live, searchable, filterable directory — no code required. Configure everything from WP Admin: map your Notion columns, choose a layout, apply filters, and embed with a single shortcode.

---

## Features

- **Live Notion data** — pulls directly from the Notion API with automatic background refresh
- **4 layout modes** — Table, Gallery, List, Board (Kanban)
- **Schema mapping** — map your Notion column names to the plugin's fields entirely from WP Admin
- **Search + filters** — real-time client-side search and dropdown filters by Category, Status, Pricing, Traction
- **Notion badge colors** — select and multi-select fields render with their original Notion colors
- **Dark / Light theme** — switchable from the Display settings tab
- **No PHP editing required** — every setting, field name, and display option is configurable from WP Admin

---

## Requirements

| Requirement | Detail |
|---|---|
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| Notion account | Free plan works |
| Notion API integration | Created at [notion.so/my-integrations](https://www.notion.so/my-integrations) |

---

## Installation

### 1. Upload the plugin

1. In your WordPress dashboard go to **Plugins → Add New → Upload Plugin**
2. Choose `jetx-ai-hub-v3.zip` and click **Install Now**
3. Click **Activate Plugin**

You will now see **Settings → JetX AI Hub** in your WP Admin sidebar.

---

## Connecting to Notion

### Step 1 — Create a Notion Integration

1. Go to [https://www.notion.so/my-integrations](https://www.notion.so/my-integrations)
2. Click **+ New integration**
3. Give it a name (e.g. "WordPress Hub") and select your workspace
4. Under **Capabilities**, ensure **Read content** is checked
5. Click **Save** and copy your **Internal Integration Secret** — this is your API Token

### Step 2 — Share your Notion database with the integration

1. Open your Notion database
2. Click the **•••** menu (top-right) → **Connections**
3. Search for your integration name and click **Confirm**

> **Important:** The integration must be connected to the database or it will return a 403 error.

### Step 3 — Get your Database ID

Your database URL looks like this:

```
https://www.notion.so/your-workspace/DATABASE_ID?v=VIEW_ID
```

Copy the `DATABASE_ID` portion — it's the 32-character string before the `?v=`. Hyphens are optional.

### Step 4 — Enter credentials in WP Admin

1. Go to **Settings → JetX AI Hub → 🔌 Connection**
2. Paste your **API Token** and **Database ID**
3. Set your preferred **cache refresh interval** (default: 60 minutes)
4. Click **Save Settings**
5. Click **Refresh Now** to do an immediate pull from Notion

A green status card will confirm the connection. If you see an error, double-check that the integration is connected to your database (Step 2).

---

## Mapping Your Notion Schema

This is the most important step if your Notion database uses different column names than the defaults.

### Why this matters

The plugin has 18 internal field keys (e.g. `name`, `category`, `status`, `pricing`). Each key maps to an exact Notion property name. If your Notion database calls the status column "Stage" instead of "Status", you configure that here — no PHP editing required.

### How to set up your schema

1. Go to **Settings → JetX AI Hub → 🗂️ Schema**
2. For each field, enter the **exact property name** from your Notion database (case-sensitive — it must match the column header character-for-character)
3. Select the correct **Type** for each property (see the Type Reference at the bottom of the tab)
4. Check **Admin Only** for any fields you want hidden from the public-facing output
5. Click **Save Schema** — the cache is automatically flushed on save

#### Field Key Reference

| Internal Key | Purpose | Default Notion Name | Type |
|---|---|---|---|
| `name` | Tool / item title | `Name` | Title |
| `category` | Primary category | `Category` | Select |
| `sub_category` | Secondary category | `Sub-category` | Select |
| `status` | Status badge | `Status` | Select |
| `traction` | Traction level | `Traction` | Select |
| `pricing` | Pricing tier | `Pricing` | Select |
| `platform` | Platforms supported | `Platform` | Multi-select |
| `capability_tags` | Feature tags | `Capability Tags` | Multi-select |
| `era` | Era / generation | `Era` | Select |
| `publisher` | Publisher / company | `Publisher / Company` | Rich Text |
| `summary` | Short description | `Summary` | Rich Text |
| `why_it_matters` | Extended description | `Why It Matters` | Rich Text |
| `official_url` | Website link | `Official URL` | URL |
| `github_repo` | GitHub link | `GitHub Repo` | URL |
| `date_released` | Release date | `Date Released` | Date |
| `blog_status` | Blog workflow (admin) | `Blog Status` | Select |
| `jetx_relevance` | Relevance score (admin) | `JetX Relevance` | Select |
| `jetx_use_case` | Use cases (admin) | `JetX Use Case` | Multi-select |

> **Tip:** You don't need to use all 18 fields. If your database doesn't have a particular column, leave the default name in place and the field will simply return empty — it won't cause an error.

---

## Configuring Columns

Go to **Settings → JetX AI Hub → 📋 Columns** to:

- Toggle which columns appear in the public output
- Rename the column labels shown to visitors
- Drag rows to reorder columns

---

## Filters & Sort

Go to **Settings → JetX AI Hub → 🔍 Filters & Sort** to:

- Add **server-side filter rules** that are applied to the Notion API query (reduces data pulled from Notion)
- Set **sort order** — which field to sort by and ascending/descending direction

Saving this tab automatically flushes the cache so changes take effect immediately.

---

## Display Settings

Go to **Settings → JetX AI Hub → 🎨 Display** to:

| Setting | Options |
|---|---|
| Layout | Table, Gallery, List, Board |
| Theme | Dark, Light |
| Board group-by | Any select field (e.g. Category) |
| Show search bar | On / Off |
| Show filter dropdowns | On / Off |
| Row limit | 0 = show all |
| Use Notion badge colors | On / Off |
| Show summary text | On / Off |
| Show tags | On / Off |
| Show GitHub links | On / Off |
| Show footer attribution | On / Off |

---

## Embedding with the Shortcode

Place this shortcode anywhere on your site — page, post, or widget:

```
[jetx_ai_hub]
```

That's it. All layout and display settings are read from WP Admin.

---

## Cache & Performance

The plugin stores Notion data in a WordPress transient cache. By default it refreshes every 60 minutes via WP-Cron. You can:

- **Change the interval** — Connection tab → Cache Refresh Interval
- **Force a refresh** — Connection tab → Refresh Now
- **Clear the cache** — Connection tab → Flush Cache

A stale-while-revalidate pattern means visitors always see data immediately (from cache) while a background refresh runs — no loading delays.

---

## Troubleshooting

**"No data loaded" / empty table**
- Check that your API Token is correct and the integration is connected to the database
- Verify the Database ID is correct (32 characters, no extra characters)
- Click **Refresh Now** in the Connection tab and check for an error message

**Data shows but columns are blank**
- Go to the Schema tab and verify the Notion property names exactly match your database column headers (case-sensitive)
- Open Notion, hover over the column header to confirm the exact name

**Cache seems stale**
- Click **Flush Cache** in the Connection tab, then **Refresh Now**
- Check that WP-Cron is running (`wp-cron.php` must be accessible, or use a real cron job)

**403 error from Notion**
- The integration has not been connected to your database — see Step 2 of the Notion setup above

**Filter dropdowns show wrong values**
- The frontend filter dropdowns (Status, Pricing, Traction) are built dynamically from your live data — they update automatically after the next cache refresh

---

## Uninstalling

Deactivate and delete the plugin from **Plugins → Installed Plugins**. All WordPress options (`jetx_hub_*`) and cached transients are removed automatically on deletion.

---

## Built by JetX Media

**JetX Media Inc.** — Toronto's AI-first digital agency.
Web · SaaS · iOS/Android · AI Workflows · AI Agents

[www.jetxmedia.com](https://www.jetxmedia.com) · Toronto, ON

---

## Changelog

### 3.0.0
- Full multi-file plugin architecture (PHP, CSS, JS separated)
- New **Schema tab** — map Notion field names from WP Admin, no PHP editing
- 4 layout modes: Table, Gallery, List, Board
- Dark/Light theme with CSS custom properties
- Stale-while-revalidate cache with WP-Cron background refresh
- All filter dropdown options built dynamically from live data
- White-label ready — all branding configurable in `config.php`
