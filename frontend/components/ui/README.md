# UI Components

This directory contains reusable UI components built with shadcn/ui.

## Available Components

- Button
- Input
- Card
- Dialog
- Dropdown Menu
- Form
- Table
- Tabs
- Toast
- Tooltip
- And more...

## Usage

Each component is self-contained and can be imported directly:

```tsx
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
```

## Customization

Components can be customized using the `className` prop or by modifying the component's source code.

## Theme

The components use the following theme configuration:

```ts
const theme = {
  colors: {
    primary: {
      DEFAULT: "hsl(var(--primary))",
      foreground: "hsl(var(--primary-foreground))",
    },
    secondary: {
      DEFAULT: "hsl(var(--secondary))",
      foreground: "hsl(var(--secondary-foreground))",
    },
    // ... more colors
  },
  // ... more theme settings
}
``` 