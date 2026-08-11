---
name: Elevated Commerce
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#474555'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#787586'
  outline-variant: '#c8c4d7'
  surface-tint: '#5942de'
  primary: '#4427ca'
  on-primary: '#ffffff'
  primary-container: '#5d47e2'
  on-primary-container: '#e1dbff'
  inverse-primary: '#c7bfff'
  secondary: '#5c5e69'
  on-secondary: '#ffffff'
  secondary-container: '#e1e1ef'
  on-secondary-container: '#62646f'
  tertiary: '#4a4a54'
  on-tertiary: '#ffffff'
  tertiary-container: '#62626d'
  on-tertiary-container: '#e0deeb'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e4dfff'
  primary-fixed-dim: '#c7bfff'
  on-primary-fixed: '#170065'
  on-primary-fixed-variant: '#4021c6'
  secondary-fixed: '#e1e1ef'
  secondary-fixed-dim: '#c5c5d2'
  on-secondary-fixed: '#191b24'
  on-secondary-fixed-variant: '#454651'
  tertiary-fixed: '#e3e1ee'
  tertiary-fixed-dim: '#c6c5d2'
  on-tertiary-fixed: '#1a1b24'
  on-tertiary-fixed-variant: '#464650'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '800'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Hanken Grotesk
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  container-max: 1280px
  gutter: 24px
  margin-desktop: 64px
  margin-mobile: 20px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
  section-gap: 80px
---

## Brand & Style

The design system is engineered for a premium, high-conversion e-commerce experience. It balances high-energy brand accents with a disciplined, minimalist structure. The aesthetic is **Corporate / Modern** with a focus on editorial clarity and functional elegance.

The target audience values efficiency and quality. The UI evokes a sense of reliability and technological sophistication through generous whitespace (breathing room), precise alignment, and a cohesive color story. By utilizing a "Dark-to-Light" hierarchy—where high-level navigation and hero sections use deep neutrals while the shopping grid remains pristine white—the system directs focus toward product imagery and calls to action.

## Colors

The palette is anchored by a vibrant **Electric Violet** primary color, used strategically for interactive elements and brand identifiers. 

- **Primary (#5D47E2):** High-action areas, primary buttons, and active states.
- **Deep Neutral (#0F111A):** Used for headers, footers, and primary headings to provide a grounded, premium feel.
- **Soft Accent (#F4F2FF):** A tinted neutral used for subtle backgrounds, secondary buttons, or chip surfaces to maintain brand continuity without visual fatigue.
- **Surface Tones:** Pure white (#FFFFFF) is the standard for product grids to ensure color accuracy of product photography, while light grays are used for borders and disabled states.

## Typography

This design system utilizes **Hanken Grotesk** across all roles to achieve a sharp, contemporary look that bridges the gap between tech-forward and human-centric design.

The hierarchy is strictly enforced:
- **Display styles** are reserved for hero sections and major marketing beats, using heavy weights and tight tracking.
- **Headlines** use a bold weight to anchor content sections and product titles.
- **Labels** employ uppercase styling and increased letter spacing for category tags and metadata, providing clear "micro-copy" distinction.
- **Body** copy is optimized for legibility with a comfortable line-height for product descriptions and specifications.

## Layout & Spacing

The system uses a **12-column fluid grid** for desktop and a **4-column fluid grid** for mobile. 

- **The 8px Rhythm:** All spacing (padding, margins, gaps) must be a multiple of 4px, with a preference for 8px increments to maintain a consistent vertical cadence.
- **Sectioning:** Large vertical gaps (80px+) are used to clearly separate different shopping intents (e.g., "Featured Products" vs "Categories").
- **Product Grids:** On desktop, use a 4 or 5 column layout with 24px gutters. On mobile, transition to a 2-column layout to maximize image size while maintaining browsing speed.
- **Safe Zones:** Content is contained within a 1280px max-width wrapper to ensure optimal readability on ultra-wide monitors.

## Elevation & Depth

Visual hierarchy is established primarily through **Tonal Layers** and crisp, **Low-Contrast Outlines**.

- **Surfaces:** Use high-contrast backgrounds (Deep Neutral vs White) to define global regions like the navigation and footer.
- **Cards:** Product cards use a thin 1px border (#E2E8F0) rather than heavy shadows. A very soft, diffused shadow (0px 4px 20px rgba(0,0,0,0.04)) is only applied on hover states to indicate interactivity.
- **Floating Elements:** Modals and dropdown menus use a more pronounced elevation with a 15% opacity primary-tinted shadow to give the appearance of sitting closer to the user.

## Shapes

The shape language is **Rounded**, reflecting a modern and accessible brand personality.

- **Buttons & Inputs:** Use the standard 0.5rem (8px) radius.
- **Product Containers:** Large surfaces and card containers use 1rem (16px) to soften the overall grid.
- **Interactive Chips:** Small tags and category pills use a full "pill" radius (999px) to distinguish them from functional buttons.
- **Icons:** Use a 2px stroke weight with slightly rounded terminals to match the Hanken Grotesk typeface.

## Components

### Buttons
- **Primary:** Solid Electric Violet background with White text. No border.
- **Secondary:** Soft Violet (#F4F2FF) background with Electric Violet text.
- **Ghost:** Transparent background with Electric Violet text; used for tertiary actions like "View More."

### Input Fields
- **Default State:** White background, 1px light gray border, 8px radius.
- **Focus State:** 2px Electric Violet border with a subtle outer glow of the same color at 10% opacity.

### Product Cards
- **Structure:** Top-aligned image area with a 1:1 aspect ratio. Metadata (Category, Title, Price) is left-aligned below.
- **Interactive:** The "Add to Cart" button is always visible or appears on hover depending on device capability.

### Chips & Tags
- **Status Tags:** (e.g., "New", "Sale") utilize the pill shape with a semi-transparent background of the primary color and bold labels.

### Lists & Navigation
- **Navigation:** Deep Neutral background for the top bar with white links. Hover states utilize a subtle underline or a primary color shift.
- **Footer:** Structured with clear vertical columns and "Label-MD" headers for high discoverability.