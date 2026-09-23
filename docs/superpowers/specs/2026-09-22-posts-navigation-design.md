# Unified Posts Navigation Design

## Intent

Consolidate post discovery behind one sidebar destination while preserving the existing list and calendar implementations. The result should feel like Buffer's Publish area: one Posts entry in the sidebar, a shared `Lista | Calendário` mode switch in the page header, and visual status tabs within list mode.

Success means users no longer need separate sidebar entries for scheduled, published, draft, and calendar views; every state remains directly linkable through a stable URL; and the existing full-height table, infinite scroll, search, label filters, and calendar behavior continue to work.

## Scope

This change covers:

- The Posts and Calendar entries in the authenticated sidebar.
- The header and navigation shared by the post list and calendar pages.
- Status filtering for the post list.
- Compatibility redirects for the old path-based status URLs.
- Automated coverage for routes, filters, navigation, and layout behavior.

This change does not redesign post rows, calendar cells, post creation/editing, publishing behavior, or database models.

## URL Contract

### List mode

The canonical list route remains `GET /posts`.

| State | URL |
| --- | --- |
| All posts | `/posts` |
| Scheduled | `/posts?tab=scheduled` |
| Drafts | `/posts?tab=draft` |
| Published | `/posts?tab=published` |

The absence of `tab` means all posts. An unsupported `tab` value behaves like the all-posts view and exposes no active status filter to the frontend.

Search and label filters remain query-string parameters and compose with `tab`, for example `/posts?tab=scheduled&search=launch&labels[]=<id>`. Infinite-scroll requests must preserve the complete query string.

### Calendar mode

The existing calendar route and query-string contract remain unchanged:

| State | URL |
| --- | --- |
| Default calendar | `/calendar` |
| Day | `/calendar?view=day` |
| Week | `/calendar?view=week` |
| Month | `/calendar?view=month` |

Existing date-position parameters (`day`, `week`, and `month`) remain unchanged. The calendar continues to default to week view when `view` is absent.

### Compatibility

The old status paths remain reachable only as redirects:

- `/posts/scheduled` redirects to `/posts?tab=scheduled`.
- `/posts/draft` redirects to `/posts?tab=draft`.
- `/posts/published` redirects to `/posts?tab=published`.

Compatibility redirects preserve applicable query parameters such as `search` and `labels`. `/calendar` is already canonical and must not redirect.

## Navigation and Layout

### Sidebar

The authenticated sidebar shows one Posts destination pointing to `/posts`.

The following standalone sidebar destinations are removed:

- Calendar
- Scheduled posts
- Published posts
- Draft posts

The Posts item is active for both `/posts` and `/calendar`, including their query-string variants, so the sidebar continues to identify the current product area.

### Shared page header

The post list and calendar use the same top-level Posts header treatment. It contains:

- The page title, `Posts`.
- A visual `Lista | Calendário` segmented switch.
- The existing `Novo post` action when the current user can create posts.

The switch performs real Inertia navigation rather than swapping locally mounted panels:

- `Lista` navigates to `/posts`.
- `Calendário` navigates to `/calendar?view=week`.

The active mode is derived from the rendered page, not from client-only state. The shared treatment should be implemented as a focused reusable post-navigation component rather than duplicated markup.

### List mode

List mode displays visual navigation tabs below the shared header:

- Todos
- Agendados
- Rascunhos
- Publicados

These controls are links, not local tab panels. Selecting one updates the canonical `tab` query parameter. Search and selected labels are retained when switching between list tabs so users can compare the same filtered subset across statuses.

Search, label filters, the full-height table, sticky table header, and infinite-scroll pagination keep their current behavior. Search and label controls remain list-only.

### Calendar mode

Calendar mode does not show the list status tabs, search, or label filters. Beneath the shared Posts header, it retains the existing calendar controls and calendar body:

- Previous and next period.
- Today.
- Date selection.
- Day, week, and month views.

Changing the calendar view continues to update the `view` query parameter. Existing responsive behavior, including the mobile day presentation, remains intact.

## Backend Design

`PostController@index` continues to render `posts/Index`. It reads `tab` from the query string, normalizes it against the supported list tabs, and applies the matching existing Eloquent scope. The normalized value is returned to the page as the active tab.

`PostController@calendar` continues to render `posts/Calendar` and keeps its current query and date-range calculations. No calendar data is loaded while rendering list mode, and no paginated list data is loaded while rendering calendar mode.

The canonical `/posts` route must be declared independently from the compatibility status route. The compatibility route accepts only `draft`, `scheduled`, or `published` and redirects before the dynamic post-detail routes can match it.

No database or API changes are required.

## Frontend Design

The shared post-navigation component owns only cross-page navigation and the create action. It receives the active mode explicitly and does not own server data or query execution.

`posts/Index.vue` owns list-specific status tabs and filter composition. Its filter requests always target `/posts`, sending `tab`, `search`, and `labels` as query parameters. Switching status tabs resets the paginated `posts` prop while preserving the current search and label selection.

`posts/Calendar.vue` owns calendar-specific period and view navigation. It adopts the shared Posts header but otherwise keeps the existing calendar calculations and rendering.

Generated Wayfinder route helpers remain the source of frontend URLs.

## Error and Edge Behavior

- An unsupported list `tab` does not reach a dynamic post route and renders the all-posts list.
- Blank `tab`, search, or label values are omitted or normalized consistently with the current filtering behavior.
- Users without a current workspace continue to be redirected to workspace creation from both modes.
- Users without post-creation permission do not see the `Novo post` action.
- Realtime post events continue reloading only the list data and retain the active query filters.
- Switching between Lista and Calendário intentionally returns to each mode's default state; remembering the last list tab or calendar period is out of scope.

## Testing Strategy

Feature coverage will verify:

- `/posts` renders all posts with no active status filter.
- Each supported `tab` filters through the correct existing post scope.
- Unsupported and blank tabs fall back to all posts.
- `tab`, search, and labels compose correctly.
- Each legacy status path redirects to the equivalent canonical query URL and preserves relevant filters.
- Calendar day, week, month, date navigation, workspace redirects, and authorization remain unchanged.

Browser/component coverage will verify:

- The sidebar exposes one Posts destination and no standalone calendar/status destinations.
- Posts remains active in both list and calendar modes.
- The shared `Lista | Calendário` switch points to the canonical routes and reflects the active mode.
- List status tabs emit query-string URLs and preserve search/label filters.
- List mode keeps the full-height infinite-scroll table.
- Calendar mode retains responsive behavior without list-only controls.

The targeted post controller, sidebar, localization, mobile overflow, and design tests must pass. The production frontend build must also succeed.
