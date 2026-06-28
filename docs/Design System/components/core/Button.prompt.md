Primary interactive control — green primary for positive actions; secondary/ghost for neutral; danger for destructive; accent (orange) for emphasis.

```jsx
<Button variant="primary" onClick={save}>Save Plan</Button>
<Button variant="secondary" size="sm">From Template</Button>
<Button variant="ghost">Cancel</Button>
<Button variant="danger" loading>Delete</Button>
```

- `variant`: primary | secondary | ghost | danger | accent
- `size`: sm | md | lg
- `fullWidth`, `loading`, `leftIcon`
