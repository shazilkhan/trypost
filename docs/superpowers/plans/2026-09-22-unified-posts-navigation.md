# Unified Posts Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the calendar and status-specific sidebar links with one Posts destination, then provide Buffer-style `Lista | Calendário` navigation and query-string status tabs inside the Posts area.

**Architecture:** Keep `PostController@index` and `posts/Index.vue` responsible for the paginated list, and keep `PostController@calendar` and `posts/Calendar.vue` responsible for calendar data. A small shared `PostsHeaderActions.vue` component renders the cross-page mode switch and create action. List status state moves from the optional route segment to the `tab` query parameter, while constrained legacy routes redirect old bookmarks to the canonical URL.

**Tech Stack:** Laravel 13, Inertia.js 3, Vue 3 `<script setup>`, Wayfinder, Tailwind CSS 4, Pest 5 browser and feature tests.

**Spec:** `docs/superpowers/specs/2026-09-22-posts-navigation-design.md`

## Global Constraints

- Complete all work on `codex/copy-trypost-connect-design`; do not create another branch or worktree.
- Preserve the existing `/calendar?view=day|week|month` URL contract and date-position query parameters.
- The canonical list URLs are `/posts` and `/posts?tab=scheduled|draft|published`.
- Keep search, label filtering, sticky table headers, and Inertia infinite scroll working with the selected tab.
- Keep the existing calendar data query and responsive day/week/month rendering.
- Use Wayfinder helpers for frontend URLs and named Laravel routes for backend URLs.
- Add no dependencies and make no database or public API changes.
- Preserve unrelated working-tree changes, including the pre-existing `package-lock.json` modification.
- Any PHP change must pass `vendor/bin/pint --dirty --format agent`.

## Review Focus

- A `tab` value outside `scheduled`, `draft`, and `published` must render all posts rather than accidentally matching a post UUID or applying an invalid scope; Task 1 adds this case.
- Published filtering must continue including `partially_published` through the existing `published()` scope; Task 1 adds this case.
- Legacy status redirects must preserve array-valued `labels` and scalar `search` parameters without accepting a caller-supplied conflicting `tab`; Task 1 adds this case.
- Changing list tabs while search or labels are active must preserve those filters and reset the infinite-scroll collection; Task 3 adds source and browser coverage.
- The single Posts sidebar item must stay active on both `/posts` and `/calendar`, including query strings; Tasks 2 and 5 add component and browser coverage.

---

### Task 1: Canonicalize the Post List Filter Contract

**Files:**
- Modify: `routes/app.php:192-205`
- Modify: `app/Http/Controllers/App/PostController.php:39-86`
- Modify: `tests/Feature/PostControllerTest.php:35-155`

**Interfaces:**
- Consumes: `GET /posts` with optional `tab`, `search`, and `labels[]` query parameters.
- Produces: Inertia prop `currentTab: 'draft' | 'scheduled' | 'published' | null` and filtered `posts` data.
- Produces: constrained legacy route `app.posts.legacy` for `GET /posts/{status}` where status is `draft|scheduled|published`.

- [ ] **Step 1: Write failing feature tests for query-string tabs**

Add a dataset-driven test to `tests/Feature/PostControllerTest.php` using the existing factory states:

```php
test('posts index filters by the tab query parameter', function (string $tab, array $expectedStatuses) {
    Post::factory()->draft()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    Post::factory()->scheduled()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => PostStatus::PartiallyPublished,
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('app.posts.index', ['tab' => $tab]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('currentTab', $tab)
        ->where('posts.data', fn (array $posts) => collect($posts)
            ->pluck('status')
            ->sort()
            ->values()
            ->all() === collect($expectedStatuses)->sort()->values()->all())
    );
})->with([
    'draft' => ['draft', [PostStatus::Draft->value]],
    'scheduled' => ['scheduled', [PostStatus::Scheduled->value]],
    'published' => ['published', [PostStatus::Published->value, PostStatus::PartiallyPublished->value]],
]);
```

Add separate cases asserting an absent, blank, or unsupported `tab` returns all four posts and `currentTab === null`.

- [ ] **Step 2: Write failing tests for composed filters and legacy redirects**

Extend the existing label-filter tests so a scheduled tagged post is returned by `?tab=scheduled&search=launch&labels[]=<id>`, while a draft with the same label/content is excluded. Add redirect coverage:

```php
test('legacy post status paths redirect to the canonical tab query and preserve filters', function (string $status) {
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.posts.legacy', [
        'status' => $status,
        'search' => 'launch',
        'labels' => [$label->id],
        'tab' => 'draft',
    ]));

    $response->assertRedirect(route('app.posts.index', [
        'tab' => $status,
        'search' => 'launch',
        'labels' => [$label->id],
    ]));
})->with(['scheduled', 'draft', 'published']);
```

- [ ] **Step 3: Run the focused tests and verify they fail**

Run:

```bash
php artisan test --compact tests/Feature/PostControllerTest.php --filter='posts index filters|legacy post status|unsupported tab|blank tab'
```

Expected: failures because `index()` still reads the route segment, `currentTab` does not exist, and `app.posts.legacy` is undefined.

- [ ] **Step 4: Split canonical and legacy routes**

Replace the optional status route in `routes/app.php` with these routes before the dynamic post-detail routes:

```php
Route::get('posts', [PostController::class, 'index'])->name('app.posts.index');
Route::get('posts/{status}', [PostController::class, 'legacyIndex'])
    ->name('app.posts.legacy')
    ->where('status', 'draft|scheduled|published');
```

Keep `posts/create` and all post mutation/detail routes intact. The status constraint prevents the compatibility route from consuming `create`, UUIDs, or other post paths.

- [ ] **Step 5: Normalize the tab and add the compatibility redirect**

Change the controller signature to `index(Request $request): Response|RedirectResponse`. Normalize the query before applying the existing scopes:

```php
$currentTab = match ($request->string('tab')->toString()) {
    PostStatus::Draft->value => PostStatus::Draft,
    PostStatus::Scheduled->value => PostStatus::Scheduled,
    PostStatus::Published->value => PostStatus::Published,
    default => null,
};

if ($currentTab !== null) {
    $query = match ($currentTab) {
        PostStatus::Draft => $query->draft(),
        PostStatus::Scheduled => $query->scheduled(),
        PostStatus::Published => $query->published(),
        default => $query,
    };
}
```

Return `'currentTab' => $currentTab?->value` and include the normalized tab in `filters` so partial reloads return one complete filter contract.

Add:

```php
public function legacyIndex(Request $request, string $status): RedirectResponse
{
    return redirect()->route('app.posts.index', [
        'tab' => $status,
        ...$request->except('tab'),
    ]);
}
```

- [ ] **Step 6: Run Task 1 tests and format PHP**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/PostControllerTest.php
```

Expected: all post controller tests pass.

- [ ] **Step 7: Commit Task 1**

```bash
git add routes/app.php app/Http/Controllers/App/PostController.php tests/Feature/PostControllerTest.php
git commit -m "refactor: filter post lists through query tabs"
```

---

### Task 2: Build Shared Post-Area Navigation and Simplify the Sidebar

**Files:**
- Create: `resources/js/components/posts/PostsHeaderActions.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/NavMain.vue`
- Modify: `lang/*/posts.php`
- Modify: `tests/Feature/UiComponentDesignTest.php`
- Modify: `tests/Feature/LocalizationParityTest.php` only if its existing parity assertion exposes a real key mismatch
- Modify: `tests/Browser/SidebarLanguageSwitchTest.php`
- Modify: `tests/Browser/LoginLocaleAppliedTest.php`

**Interfaces:**
- Consumes: prop `activeMode: 'list' | 'calendar'`.
- Produces: `data-testid="posts-view-list"`, `data-testid="posts-view-calendar"`, and `data-testid="new-post-link"` navigation targets.
- Produces: one sidebar item whose `isActive` boolean covers both post-list and calendar paths.

- [ ] **Step 1: Write failing component-contract tests**

Add assertions to `tests/Feature/UiComponentDesignTest.php` that the shared component contains both canonical route helpers and both test IDs, and that `AppSidebar.vue` no longer contains path-specific `postsIndex.url('scheduled')`, `postsIndex.url('published')`, or `postsIndex.url('draft')` entries.

Also assert `NavMain.vue` respects the existing `NavItem.isActive` field:

```php
expect(componentSource('NavMain.vue'))
    ->toContain('item.isActive ?? urlIsActive');
```

- [ ] **Step 2: Run the component test and verify it fails**

Run:

```bash
php artisan test --compact tests/Feature/UiComponentDesignTest.php
```

Expected: failure because the shared component does not exist and the sidebar still defines four post-status entries.

- [ ] **Step 3: Add the translated List label without key drift**

Add `view.list` under the root of every `lang/*/posts.php`. Use these exact translations:

| Locale | Value |
| --- | --- |
| ar | `القائمة` |
| de | `Liste` |
| el | `Λίστα` |
| en | `List` |
| es | `Lista` |
| fr | `Liste` |
| it | `Elenco` |
| ja | `リスト` |
| ko | `목록` |
| nl | `Lijst` |
| pl | `Lista` |
| pt-BR | `Lista` |
| ru | `Список` |
| tr | `Liste` |
| uk | `Список` |
| zh | `列表` |

Use the existing `calendar.title` key for the Calendar label and existing `posts.new_post` key for the create action.

- [ ] **Step 4: Create `PostsHeaderActions.vue`**

Implement a focused component with a segmented `Lista | Calendário` control and the permission-aware create button:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconCalendar, IconList, IconPlus } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { calendar } from '@/routes/app';
import { create as createPost, index as postsIndex } from '@/routes/app/posts';

defineProps<{ activeMode: 'list' | 'calendar' }>();

const { canCreatePost } = useWorkspaceRole();
</script>
```

Render both links inside one rounded border group. Apply the existing amber selected treatment when the corresponding mode is active, keep visible focus styles, and attach the three test IDs from the interface block. `Lista` points to `postsIndex.url()`. `Calendário` points to `calendar.url({ query: { view: 'week' } })`.

- [ ] **Step 5: Collapse sidebar post navigation to one item**

In `AppSidebar.vue`:

- Remove Calendar from `mainNavItems`.
- Replace the four `postsNavItems` entries with one item titled `sidebar.groups.posts`, linking to `postsIndex.url()`, using `IconFileText`.
- Remove the redundant Posts group label so the sidebar renders one row labeled Posts rather than a Posts heading plus an All child.
- Import `useActiveUrl` and set that item's `isActive` to `urlIsActive(postsIndex.url()) || urlIsActive(calendar.url())`.
- Remove unused status/calendar icon imports.

In `NavMain.vue`, change `:is-active` to prefer `item.isActive` before falling back to URL matching. This activates the single Posts item on either page without teaching the generic navigation component about Posts.

- [ ] **Step 6: Update language-switch browser expectations**

In `SidebarLanguageSwitchTest.php` and `LoginLocaleAppliedTest.php`, replace assertions for `sidebar.posts.all` with `sidebar.groups.posts`, because the visible sidebar entry is now Posts rather than All. Keep their existing locale-change behavior intact.

- [ ] **Step 7: Run Task 2 tests**

Run:

```bash
php artisan test --compact tests/Feature/UiComponentDesignTest.php tests/Feature/LocalizationParityTest.php tests/Browser/SidebarLanguageSwitchTest.php tests/Browser/LoginLocaleAppliedTest.php
```

Expected: component, translation parity, and language-switch tests pass.

- [ ] **Step 8: Commit Task 2**

```bash
git add resources/js/components/posts/PostsHeaderActions.vue resources/js/components/AppSidebar.vue resources/js/components/NavMain.vue lang tests/Feature/UiComponentDesignTest.php tests/Browser/SidebarLanguageSwitchTest.php tests/Browser/LoginLocaleAppliedTest.php
git commit -m "feat: unify posts navigation in the sidebar"
```

---

### Task 3: Convert the Post List to Visual Query Tabs

**Files:**
- Modify: `resources/js/pages/posts/Index.vue`
- Modify: `tests/Feature/UiComponentDesignTest.php`
- Test: `tests/Feature/PostControllerTest.php`

**Interfaces:**
- Consumes: `currentTab` and `filters.tab/search/labels` from Task 1.
- Consumes: `PostsHeaderActions active-mode="list"` from Task 2.
- Produces: `posts-tab-all`, `posts-tab-scheduled`, `posts-tab-draft`, and `posts-tab-published` test IDs.

- [ ] **Step 1: Add failing list-layout assertions**

Extend the post-list design test to require:

```php
expect(resourceSource('pages/posts/Index.vue'))
    ->toContain('<PostsHeaderActions active-mode="list"')
    ->toContain('data-testid="posts-tabs"')
    ->toContain('data-testid="posts-tab-scheduled"')
    ->toContain("tab: props.currentTab || undefined")
    ->not->toContain('postsIndex.url(props.currentStatus)');
```

- [ ] **Step 2: Run the focused tests and verify they fail**

Run:

```bash
php artisan test --compact tests/Feature/UiComponentDesignTest.php tests/Feature/PostControllerTest.php
```

Expected: the design assertion fails because the page still builds path-segment URLs and has no visual status rail.

- [ ] **Step 3: Replace route-status state with query-tab state**

In the `Props` interface, replace `currentStatus` with:

```ts
currentTab: 'draft' | 'scheduled' | 'published' | null;
filters: {
    tab: 'draft' | 'scheduled' | 'published' | null;
    search: string;
    labels: string[];
};
```

Always request the canonical list route:

```ts
router.get(
    postsIndex.url(),
    {
        tab: props.currentTab || undefined,
        search: searchQuery.value || undefined,
        labels: selectedLabelIds.value.length ? selectedLabelIds.value : undefined,
    },
    {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['posts', 'filters', 'currentTab'],
        reset: ['posts'],
    },
);
```

Keep realtime reloads scoped to `posts` so they retain the current page URL and filters.

- [ ] **Step 4: Add link-based visual status tabs**

Define a typed tab array for all/scheduled/draft/published. Build each link with `postsIndex.url({ query: { tab, search, labels } })`, omitting blank values. Use existing plural copy from `sidebar.posts.all`, `sidebar.posts.scheduled`, `sidebar.posts.drafts`, and `sidebar.posts.posted`.

Render the tab rail in a border-bottom list toolbar before the empty state/table. Use link semantics and an amber active underline/background treatment; do not use `TabsContent` because navigation is server-backed. Add the four test IDs and `data-testid="posts-tabs"`.

- [ ] **Step 5: Recompose the list header and toolbar**

Use a stable `Posts` page title:

```vue
<template #header>
    <HeaderTitle :title="$t('posts.title')" :total="posts.total" />
</template>

<template #header-actions>
    <PostsHeaderActions active-mode="list" />
</template>
```

Move search and label filters from `header-actions` into the right side of the list tab toolbar. Keep their current test IDs and responsive widths. The New Post link now comes only from `PostsHeaderActions`.

- [ ] **Step 6: Format and run Task 3 tests**

Run:

```bash
npx prettier --write resources/js/pages/posts/Index.vue resources/js/components/posts/PostsHeaderActions.vue
php artisan test --compact tests/Feature/PostControllerTest.php tests/Feature/UiComponentDesignTest.php
```

Expected: list filtering and source-contract tests pass.

- [ ] **Step 7: Commit Task 3**

```bash
git add resources/js/pages/posts/Index.vue resources/js/components/posts/PostsHeaderActions.vue tests/Feature/UiComponentDesignTest.php
git commit -m "feat: add query-backed post status tabs"
```

---

### Task 4: Integrate the Existing Calendar into the Shared Posts Header

**Files:**
- Modify: `resources/js/pages/posts/Calendar.vue`
- Modify: `tests/Feature/UiComponentDesignTest.php`
- Test: `tests/Feature/PostControllerTest.php`
- Test: `tests/Browser/MobileOverflowTest.php`

**Interfaces:**
- Consumes: `PostsHeaderActions active-mode="calendar"` from Task 2.
- Preserves: named route `app.calendar` and all existing `view`, `day`, `week`, and `month` query parameters.

- [ ] **Step 1: Add failing calendar-layout assertions**

Add a source-contract test requiring `Calendar.vue` to contain the shared header action component while still containing all three existing view triggers and `calendar.url({ query: { view } })`. Assert the old duplicate header-level `createPost.url()` links are absent while date-cell create links remain.

- [ ] **Step 2: Run calendar and mobile tests and verify the new assertion fails**

Run:

```bash
php artisan test --compact tests/Feature/PostControllerTest.php tests/Feature/UiComponentDesignTest.php tests/Browser/MobileOverflowTest.php
```

Expected: only the new shared-header assertion fails.

- [ ] **Step 3: Add the shared Posts header to Calendar**

Inside `<AppLayout full-width>`, add:

```vue
<template #header>
    <HeaderTitle :title="$t('posts.title')" />
</template>

<template #header-actions>
    <PostsHeaderActions active-mode="calendar" />
</template>
```

Import `HeaderTitle` and `PostsHeaderActions`. Keep `<Head :title="$t('calendar.title')" />` so the browser title remains specific.

- [ ] **Step 4: Remove only duplicated page-level creation controls**

Remove the mobile full-width New Post row and the desktop New Post button from the calendar control headers. Keep `canCreatePost`, `createPostUrl()`, and date-cell creation links because they allow creating a post on a specific calendar date.

Remove the old mobile `pl-12` compensation now that the standard AppHeader owns the mobile sidebar trigger. Keep the current previous/next, Today, DatePicker, period title, and Day/Week/Month controls immediately below the shared header.

- [ ] **Step 5: Run Task 4 tests**

Run:

```bash
npx prettier --write resources/js/pages/posts/Calendar.vue
php artisan test --compact tests/Feature/PostControllerTest.php tests/Feature/UiComponentDesignTest.php tests/Browser/MobileOverflowTest.php
```

Expected: existing calendar behavior, mobile overflow coverage, and shared-header assertions pass.

- [ ] **Step 6: Commit Task 4**

```bash
git add resources/js/pages/posts/Calendar.vue tests/Feature/UiComponentDesignTest.php
git commit -m "feat: integrate calendar into posts navigation"
```

---

### Task 5: Verify the Integrated Buffer-Style Navigation

**Files:**
- Create: `tests/Browser/PostsNavigationTest.php`
- Modify: `tests/Browser/ConnectDesignTest.php` only if existing shell assertions need to share setup
- Modify: `tests/Browser/MobileOverflowTest.php` only when a new named case is needed

**Interfaces:**
- Consumes: test IDs and route contracts from Tasks 1-4.
- Produces: end-to-end proof that sidebar, list tabs, and mode switching agree on canonical URLs.

- [ ] **Step 1: Generate the browser test through Artisan**

Run:

```bash
php artisan make:test --pest PostsNavigationTest --no-interaction
```

Move the generated file to `tests/Browser/PostsNavigationTest.php` only if the project's Artisan command creates it under `tests/Feature`; use `apply_patch` for the move/content edit.

- [ ] **Step 2: Write the integrated browser test**

Create an authenticated user/current workspace using the established browser-test setup, then verify:

- `/posts` shows the shared header, both mode links, all four status tabs, search, and one visible Posts sidebar link.
- Starting from a URL with `search` and `labels[]`, clicking Scheduled leaves the pathname at `/posts`, sets `tab=scheduled`, and preserves both existing filters.
- Clicking Calendar navigates to `/calendar?view=week`, keeps the Posts sidebar row active, shows calendar view controls, and hides list status tabs/search.
- Clicking List returns to `/posts`.
- No JavaScript errors occur.

Use a small browser script for URL and active-state assertions so query ordering is irrelevant:

```php
$state = $page->script(<<<'JS'
    (() => {
        const url = new URL(window.location.href);
        const postsLink = document.querySelector('[data-testid="nav-/posts"]');

        return {
            pathname: url.pathname,
            tab: url.searchParams.get('tab'),
            view: url.searchParams.get('view'),
            search: url.searchParams.get('search'),
            labels: url.searchParams.getAll('labels[]'),
            sidebarActive: postsLink?.closest('[data-active="true"]') !== null,
        };
    })();
JS);
```

If the sidebar button exposes its active state through a different existing attribute, assert that actual attribute rather than adding a Posts-only DOM contract.

- [ ] **Step 3: Run the focused browser and feature suite**

Run:

```bash
php artisan test --compact tests/Browser/PostsNavigationTest.php tests/Browser/SidebarMenuTest.php tests/Browser/SidebarLanguageSwitchTest.php tests/Browser/LoginLocaleAppliedTest.php tests/Browser/MobileOverflowTest.php tests/Feature/PostControllerTest.php tests/Feature/UiComponentDesignTest.php tests/Feature/LocalizationParityTest.php
```

Expected: all targeted navigation, localization, controller, and responsive tests pass.

- [ ] **Step 4: Run frontend static checks and production build**

Run:

```bash
npx eslint resources/js/components/AppSidebar.vue resources/js/components/NavMain.vue resources/js/components/posts/PostsHeaderActions.vue resources/js/pages/posts/Index.vue resources/js/pages/posts/Calendar.vue
npm run build
```

Expected: ESLint reports no errors and Vite completes successfully. Existing dependency annotation warnings may remain non-fatal.

- [ ] **Step 5: Run final formatting and diff checks**

Run:

```bash
vendor/bin/pint --dirty --format agent
git diff --check
git status --short
```

Confirm `package-lock.json` remains untouched by this feature and that no unrelated user changes are staged.

- [ ] **Step 6: Commit Task 5**

```bash
git add tests/Browser/PostsNavigationTest.php tests/Browser/ConnectDesignTest.php tests/Browser/MobileOverflowTest.php
git commit -m "test: cover unified posts navigation"
```
