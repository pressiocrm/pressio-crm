# Pressio CRM

A pipeline-first CRM built into WordPress. Track deals on a drag-and-drop kanban board, manage contacts, send emails, and log activity — no monthly fees, no data leaving your server.

## Features

- **Deal pipeline** — Drag-and-drop kanban board with customizable stages and per-stage revenue totals
- **Contact management** — Search, filter, tag, bulk-edit, CSV import/export
- **Email sending** — HTML emails with merge tags, logged to the contact timeline
- **Task management** — Due dates, priorities, overdue alerts
- **Activity timeline** — Auto-logs stage changes, emails, notes, tasks, and form submissions
- **Dashboard** — Stats cards, pipeline funnel chart, recent activity feed
- **Contact Form 7 integration** — Map CF7 fields to CRM fields; submissions auto-create contacts
- **Modern interface** — Vue-powered SPA inside WordPress admin; no full page reloads

## Installation

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/pressio-crm/) or clone this repo into `wp-content/plugins/pressio-crm`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Follow the onboarding wizard to set up your pipeline stages and add your first contact

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Development

```bash
# Install JS dependencies
npm install

# Development build with watch
npm run dev

# Production build
npm run build
```

## License

GPL v2 or later — see [LICENSE](LICENSE).
