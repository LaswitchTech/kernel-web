# Theme Preview Page

## Overview

A theme preview page renders Bootstrap components using the active theme's styling, allowing developers and administrators to review visual appearance before applying a theme.

## Purpose

- Help developers review styling during theme development
- Help users/admins preview themes before applying them
- Serve as a visual regression / smoke-test page for themes
- Provide a consistent reference for component styling across themes

## Route Design

**Proposed route:** `GET /admin/themes/preview`

- Lives under the admin section since theme application is an admin action
- May eventually be available publicly for theme demos
- No authentication required if served publicly, but initially gated for safety

## Safety

- All data must be safe placeholders
- No real user names, emails, or sensitive content
- No authentication tokens or credentials displayed
- No forms that submit real data

## Bootstrap Components to Include

### Typography
- Headings (`h1`–`h6`)
- Lead text
- Blockquote
- List group
- Inline code and code blocks
- Tables (striped, hover)

### Buttons
- Contextual variants (primary, secondary, success, danger, warning, info, light, dark)
- Outline variants
- Sizes (sm, default, lg)
- Pill buttons
- Toggle buttons

### Alerts
- Contextual variants
- Dismissible alerts
- Alerts with icons

### Badges
- Contextual badges
- Pill badges

### Cards
- Text cards
- Image cards
- Horizontal cards
- Card grid layouts

### Forms
- Text inputs
- Textareas
- Select dropdowns
- Checkboxes and radio buttons
- Toggle switches
- Range inputs
- File input
- Input groups

### Navs
- Tabs
- Pills
- Vertical tabs
- Breadcrumb
- Pagination

### Modals
- Basic modal
- Centered modal
- Scrollable modal
- Modal with form

### Dropdowns
- Dropdown menus
- Dropdown dividers
- Button dropdowns

### Accordions
- Collapsible sections

### Progress
- Progress bars (basic, striped, animated)

### Toasts
- Dismissible toast notifications

## Theme Switching (Future)

**Query parameter:** `?theme={slug}`

- Swaps the active theme for preview only
- Does not persist or modify any server-side state
- Useful for comparing multiple themes side-by-side during development
- Theme slug resolved via the theme loader (not hardcoded)

```
/admin/themes/preview?theme=dark
/admin/themes/preview?theme=light
/admin/themes/preview?theme=my-custom-theme
```

## Layout

The preview page should use the `panel.php` layout:
- Sidebar: theme selector (future)
- Topbar: preview mode indicator
- Content: component sections organized by category

## Future Enhancements

- Side-by-side theme comparison
- Custom CSS override input
- Screenshot/export capability
- Color palette extraction
- Dark/light mode toggle within preview
