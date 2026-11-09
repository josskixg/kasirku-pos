---
version: alpha
name: KasirKu POS (Supabase Dark + Light Theme)
description: Dark-mode-native developer & retail POS operator console inspired by Supabase (#171717 canvas, #3ecf8e emerald accent, Source Code Pro telemetry, and full Light Mode parity).
colors:
  primary: "#3ECF8E"
  primary-strong: "#006239"
  secondary: "#898989"
  surface-canvas: "#171717"
  surface-panel: "#0F0F0F"
  surface-elevated: "#1F1F1F"
  border-subtle: "#2E2E2E"
  border-strong: "#363636"
  text-primary: "#FAFAFA"
  text-muted: "#B4B4B4"
  light-canvas: "#F8FAFC"
  light-panel: "#FFFFFF"
  light-text: "#0F172A"
  status-warning: "#F59E0B"
  status-danger: "#EF4444"
typography:
  display-hero:
    fontFamily: Inter
    fontSize: 2.25rem
    fontWeight: 600
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  heading-card:
    fontFamily: Inter
    fontSize: 1.125rem
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "-0.01em"
  body-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.5
  mono-label:
    fontFamily: Source Code Pro
    fontSize: 0.75rem
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "0.08em"
rounded:
  sm: 6px
  md: 8px
  lg: 12px
  pill: 9999px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
components:
  button-primary:
    backgroundColor: "{colors.primary-strong}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.pill}"
    padding: 10px
    typography: "{typography.body-md}"
  button-secondary:
    backgroundColor: "{colors.surface-panel}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.sm}"
    padding: 8px
  card-dark:
    backgroundColor: "{colors.surface-canvas}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: 16px
  card-panel:
    backgroundColor: "{colors.surface-panel}"
    textColor: "{colors.text-muted}"
    rounded: "{rounded.lg}"
    padding: 16px
  card-light:
    backgroundColor: "{colors.light-panel}"
    textColor: "{colors.light-text}"
    rounded: "{rounded.md}"
    padding: 16px
  badge-warning:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.status-warning}"
    rounded: "{rounded.pill}"
    padding: 4px
  badge-danger:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.status-danger}"
    rounded: "{rounded.pill}"
    padding: 4px
  badge-brand:
    backgroundColor: "{colors.surface-panel}"
    textColor: "{colors.primary}"
    rounded: "{rounded.pill}"
    padding: 4px
  sub-bar:
    backgroundColor: "{colors.border-subtle}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.sm}"
    padding: 8px
  divider-strong:
    backgroundColor: "{colors.border-strong}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.sm}"
    padding: 4px
  light-shell:
    backgroundColor: "{colors.light-canvas}"
    textColor: "{colors.light-text}"
    rounded: "{rounded.md}"
    padding: 16px
  muted-pill:
    backgroundColor: "{colors.surface-panel}"
    textColor: "{colors.secondary}"
    rounded: "{rounded.pill}"
    padding: 4px
---

## Overview

KasirKu POS adopts the **Supabase** developer-first dark design language paired with a complete daylight-ready Light Theme for sunlit retail counters and outdoor food stalls. Built on an **Operate & Monitor** surface composition, it eliminates marketing fluff in favor of high-speed SKU registration, real-time HPP (COGS) margin telemetry, and raw 58mm ESC/POS byte stream inspection.

## Colors

- **Primary Emerald (`#3ECF8E` / `#006239`)**: Signals active POS checkout triggers, Bluetooth thermal connection status, and net profit telemetry.
- **Dark Canvas (`#171717` & `#0F0F0F`)**: Zero-glare dark surfaces separated by crisp 1px borders (`#2E2E2E` and `#363636`) instead of heavy drop shadows.
- **Light Mode Surface (`#F8FAFC` & `#FFFFFF`)**: High-contrast daylight mode toggled via persistent `localStorage` state for outdoor UMKM booths.

## Typography

Uses **Inter** for dense UI controls and **Source Code Pro** (`tabular-nums`) for currency alignment, SKU codes, EMVCo QRIS payloads, and ESC/POS hexadecimal byte dumps.

## Layout

Multi-pane responsive **Operate & Monitor** console:
- **Top Telemetry Strip**: Store identity, IndexedDB offline sync queue indicator, real-time Gross Revenue, HPP Cost, Net Profit margin, and Dark/Light theme switcher.
- **Left Catalog & Barcode Workspace**: Instant search (`/`), category filter pills, and tactile SKU cards with HPP margin badges.
- **Right Register & ESC/POS 58mm Inspector**: Live order bill, discount/PPN 11% toggles, 58mm thermal paper preview, and raw hex byte dump.
- **Mobile (<768px)**: Sticky bottom checkout bar + slide-over drawer for single-hand smartphone operation.

## Elevation & Depth

Depth is achieved via luminance stepping (`#0F0F0F` -> `#171717` -> `#1F1F1F`) and `1px solid #2E2E2E` hairlines, avoiding muddy blurs.

## Shapes

Pill-shaped primary CTAs (`9999px`) contrast with `6px` and `8px` technical containers.

## Components

- `button-primary`: High-emphasis checkout and Web Bluetooth 58mm thermal print trigger.
- `card-dark` / `card-light`: SKU item tiles with keyboard shortcut badges and stock indicators.
- `badge-brand`: Monospace telemetry pills for ESC/POS status and QRIS CRC16 verification.

## Do's and Don'ts

- **Do** enforce `font-variant-numeric: tabular-nums` on every IDR price, HPP calculation, and hex counter.
- **Don't** use marketing hero banners inside the cashier workspace; keep the top fold 100% operational.
