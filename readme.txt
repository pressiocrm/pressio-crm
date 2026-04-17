=== Pressio CRM ===
Contributors: pressiocrm
Tags: crm, contacts, pipeline, deals, contact form 7
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A pipeline-first CRM built into WordPress. Manage contacts, track deals, and close more sales — no monthly fees.

== Description ==

You started your business to do great work — not to wrestle with spreadsheets or pay $50 a month for a CRM you barely use.

Pressio CRM is a complete, pipeline-first CRM that runs inside your WordPress dashboard. Add contacts, track deals on a drag-and-drop kanban board, send emails, manage follow-up tasks, and see your sales at a glance — all without leaving your site or sending your customer data to someone else's server.

**The free version is not a demo.** There are no contact limits, no deal limits, no nag screens, and no time trials. It is a real CRM for real businesses.

= Features (all free, all included) =

* **Visual deal pipeline** — Drag-and-drop kanban board. Move deals through stages with a click. See total deal value per stage at a glance.
* **Full contact management** — Create, search, filter, tag, and organize contacts. Bulk actions, CSV import, and CSV export included.
* **HTML email sending** — Write and send emails directly from each contact record. Merge tags, a visual editor, email branding settings, and full email history per contact.
* **Task management** — Create tasks tied to contacts or deals. Set due dates and priorities. Overdue alerts keep you on top of follow-ups.
* **Activity timeline** — Every interaction is auto-logged: stage changes, emails sent, notes added, tasks completed. View the full story of any contact in one scroll.
* **Dashboard** — Total contacts, open deals, tasks due today, revenue stats. Pipeline funnel chart and recent activity feed give you a daily snapshot.
* **Contact Form 7 integration** — Website form submissions automatically create contacts in your CRM. Map any CF7 field to any contact field — no code required.
* **Color-coded tags** — Organize contacts your way. Assign multiple tags, filter by tag, and see tags at a glance in every list.
* **Onboarding wizard** — Set up your company name, pipeline stages, and first contact in under 2 minutes.
* **Modern interface** — Vue-powered single-page app inside WordPress. Fast, responsive, and clean — no page reloads.

= Why Pressio CRM instead of a SaaS CRM? =

1. **Your data stays on your server.** Customer names, emails, deal values, and notes never leave your WordPress database. No third-party access. Export everything anytime.
2. **No monthly fees.** The free version is a complete CRM. You do not need to pay to start using it or to keep using it.
3. **Pipeline-first design.** Most WordPress CRMs treat deals as an afterthought. Pressio CRM puts your sales pipeline front and center with a real kanban board.
4. **Works with your existing site.** Contact Form 7 submissions flow straight into your CRM. Your leads are captured where they arrive.
5. **Fast, modern interface.** No page reloads, no loading spinners on every click. The Vue-powered interface feels like a standalone web app, not a WordPress admin page from 2012.

= Who is Pressio CRM for? =

Pressio CRM is built for solo operators, freelancers, consultants, small agencies, and service businesses running WordPress. If you are tracking leads in a spreadsheet, juggling sticky notes for follow-ups, or paying for a SaaS CRM that is 90% features you never use — this plugin replaces all of that.

= Pressio CRM Pro =

Need more power as your business grows? Pressio CRM Pro adds:

* **Unlimited pipelines** — Track multiple sales processes, departments, or project types
* **Email sequences and automation** — Drip campaigns and trigger-based workflows
* **Custom fields UI** — Add any field you need to contacts and deals
* **Advanced reporting and analytics** — Revenue forecasting, conversion rates, stage duration
* **Priority support** — Direct access to the team that builds the plugin

The free version is not crippled. Pro is for teams and businesses that have outgrown a single pipeline.

== Installation ==

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for **Pressio CRM**.
3. Click **Install Now**, then **Activate**.
4. Go to **Pressio CRM** in your admin menu.
5. Complete the 3-step setup wizard (takes about 60 seconds).
6. Start adding contacts and deals.

= Manual installation =

1. Download the plugin zip file.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin and follow the setup wizard.

== Frequently Asked Questions ==

= Is the free version actually usable, or is it just a teaser for Pro? =

The free version is a fully functional CRM with no artificial limits. You get contacts, a deal pipeline, tasks, email sending, activity tracking, a dashboard, and Contact Form 7 integration. There is no cap on the number of contacts or deals you can create, no time-limited trial, and no persistent upgrade banners. Many businesses will never need Pro.

= Can I import contacts from a spreadsheet or another CRM? =

Yes. Go to **Settings > Data** and upload a CSV file. The importer lets you map CSV columns to contact fields, and you can choose how to handle duplicate email addresses. This works for migrating from Excel, Google Sheets, HubSpot, Mailchimp, or any tool that exports CSV.

= Does Pressio CRM work with Contact Form 7? =

Yes — this integration is included free. When a visitor submits a Contact Form 7 form on your site, Pressio CRM automatically creates or updates a contact record. You control which form fields map to which CRM fields directly from the CF7 form editor. No code, no extra plugins.

= Where is my customer data stored? =

Everything is stored in your own WordPress database on your own hosting. No data is transmitted to external servers or third-party services. You can export all contacts and deals to CSV at any time from **Settings > Data**.

= What is the difference between free and Pro? =

The free version gives you one pipeline, full contact management, tasks, email, activity tracking, and CF7 integration. Pro adds unlimited pipelines, email sequences, custom fields, advanced reporting, and priority support. Pricing is a flat site license — not per-user, not per-contact.

= Will Pressio CRM slow down my site? =

No. The plugin only loads its assets on its own admin pages. It does not add scripts or styles to your site's frontend. The CRM interface is a single-page Vue app, so after the initial load, navigation within the CRM is instant — no full page reloads.

== Screenshots ==

1. **Dashboard** — Stats cards showing total contacts, open deals, tasks due today, and revenue. Pipeline funnel chart and recent activity feed give you a daily business snapshot.
2. **Pipeline kanban board** — Drag-and-drop deals across stages. See deal values, contact names, and close dates on every card. Colored stage indicators show open, won, and lost.
3. **Contact profile** — Full contact detail view with activity timeline, linked deals, tasks, tags, notes, and complete email history in one place.
4. **Email composer** — Send branded HTML emails to contacts with merge tags, a visual editor, and per-contact email history so you never lose track of a conversation.
5. **Contact Form 7 integration** — Map any CF7 form field to any CRM contact field. New form submissions automatically create contacts — no code required.

== Source Code ==

The full source code for this plugin, including all uncompiled Vue 3 / JavaScript source files, is publicly available on GitHub:

https://github.com/pressiocrm/pressio-crm

The `src/` directory in the plugin contains all human-readable Vue components, Pinia stores, composables, and JavaScript source files that are compiled into the `build/` directory.

To rebuild the frontend assets from source:

1. Install Node dependencies: `npm install`
2. Production build: `npm run build`
3. Development build with HMR: `npm run dev`

Build tool: Vite. Entry point: `src/main.js`. Output: `build/index.js` + `build/index.css`.

Third-party libraries used in the build:
* Vue 3 (MIT) — https://github.com/vuejs/core
* Pinia (MIT) — https://github.com/vuejs/pinia
* Vue Router (MIT) — https://github.com/vuejs/router
* vue-draggable-plus (MIT) — https://github.com/Alfred-Skyblue/vue-draggable-plus
* @vueuse/core (MIT) — https://github.com/vueuse/vueuse
* @wordpress/i18n (GPL-2.0-or-later) — https://github.com/WordPress/gutenberg

== Changelog ==

= 1.0.0 =
* Initial release.
* Contact management with search, filter, tags, CSV import/export, and bulk actions.
* Pipeline kanban board with drag-and-drop deals and stage management.
* Task management linked to contacts and deals.
* Activity timeline with auto-logged events.
* Dashboard with stats, pipeline funnel, and activity feed.
* Email sending with merge tags, visual editor, and per-contact history.
* Contact Form 7 integration with field mapping.
* Email branding settings.
* Onboarding wizard.
