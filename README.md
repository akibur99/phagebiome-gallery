# Phagebiome Gallery

Admin-managed gallery with category filters, responsive grid and accessible lightbox. Display anywhere with:

```
[phagebiome_gallery]
```

## Features

- Gallery items in WordPress admin: Media Library image picker with preview, alt-text override, caption, categories, automatic/manual display order, draft support
- Dedicated `gallery-category` taxonomy with custom filter ordering
- Category filter buttons generated from published items (plus configurable “All”)
- Responsive grid (3 / 2 / 1 columns by default, configurable) with reference-matched cards, counters and captions
- Accessible lightbox: prev/next, position (“2 of 8”), captions, arrow keys, Escape, focus trap + restore, scroll lock, touch-friendly, single-image handling
- Works without JavaScript: filters are links, images open the file directly
- One-click reference import (9 stock placeholder images downloaded into the Media Library) and reference-only Trash removal
- Multiple independent galleries per page; compatible with the Shortcode block and Elementor Shortcode widget

## Install

1. Upload `phagebiome-gallery.zip` via Plugins → Add New → Upload, Activate.
2. Gallery → Import → Import Reference Gallery (optional; needs outbound HTTPS to the image host).
3. Add `[phagebiome_gallery]` to any page.

## Managing the gallery

- Gallery → All Gallery Items: thumbnail, categories, auto/manual order, date.
- Add New Gallery Item: enter a title (“Enter image title”), Select Image, optional alt text (“Describe what is visible in the image”), optional caption, categories, Display Order (“Automatic” = assigned on first publish; enter a number to override; Reset to automatic restores it).
- An item cannot be published without a valid JPEG, PNG, GIF or WebP image; incomplete items stay drafts.
- Gallery → Categories: add/edit/delete (deleting never deletes items); Display order controls custom filter order.
- Gallery → Settings: order direction, filter order, captions on/off, “All” label, empty message, grid columns.

## Reference import notes

The 9 reference images are third-party stock photographs (placeholders), flagged “Stock placeholder” in admin — replace them with the lab’s originals. If a download fails, the item is created as a draft and reported; re-running import skips existing references without duplicating or overwriting edits. Removal trashes only imported items and preserves Media Library files and categories.

## Test checklist

- Fresh install from ZIP, activate, no errors.
- Create categories + items with images; check frontend grid, filters, counts.
- Filter, open lightbox: arrows, Esc, counter, focus return, single-image prev/next hidden.
- Disable JavaScript: filters and image links still work.
- Narrow to 390 px and embed in a narrow column: 1-column grid, wrapped buttons, no sideways scroll.
- Two shortcodes on one page filter independently; one lightbox at a time.
- Manual order override + Reset to automatic; new publish gets next number.
- Import twice: second run skips everything; remove: only imported items trashed, media kept.
- Console shows zero errors.
