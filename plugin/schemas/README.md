# Example components

Ten components that ship with the plugin to show what this approach can do.
Each one covers a different capability; together they are a course through the
format.

| Type | Shows |
|---|---|
| `hero` | The minimum: only `definition.json`, no custom code. |
| `faq` | A repeater with two subfields, output through the generic markup. |
| `feature-grid` | A `select` field used as an icon choice, translated only in the view. |
| `testimonials` | Quotation semantics and a horizontal flourish without JavaScript. |
| `pricing-table` | A repeater inside a repeater, plus one highlighted tier. |
| `stats` | A definition list instead of loose numbers. |
| `cta-band` | Theming through a `select` field and CSS variables. |
| `vinyl` | Two values nobody types: the track number from the row order, the total running time computed — and only when every duration is complete. |
| `movie` | `<dl>` for the facts, `<meter>` for the rating, `:has()` for the poster: one markup for both cases. |
| `series` | `<details>` per season instead of 200 lines, numbers from nested `<ol>`, pluralisation through `_n()`. |

## Three files, one directory

```
schemas/<type>/
    definition.json    required — fields, title, icon
    render.php         optional — markup
    component.css      optional — presentation
```

The directory name **is** the type: it appears in the field group key
(`group_sdcpb_component_<type>`), in the block name (`sdcpb/<type>`) and in the
path. Hence lowercase letters, digits and **hyphens** only — an underscore is a
valid component type but not a valid block name, and WordPress rejects
`sdcpb/feature_grid`. A hyphen works in all three places:
`feature-grid`, `group_sdcpb_component_feature-grid`, `sdcpb/feature-grid`.

`render.php` receives three local variables: `$type`, `$data` (raw values) and
`$component` (the entry from the page document). It prints its own markup,
including the wrapping element.

These files live inside the plugin and are replaced with every update. To change
one of them, copy it to `wp-content/sdc-components/<type>/` — there it takes
precedence over the plugin version, and there it is editable in wp-admin.

## The helpers in `render.php`

All of them print, and all of them escape. `sdcpb_value()` is the way past the
escaping to the raw value.

| Helper | Purpose |
|---|---|
| `sdcpb_text( 'field' )` | value as text |
| `sdcpb_html( 'field' )` | value as HTML (`wp_kses_post`) |
| `sdcpb_attr( 'field' )`, `sdcpb_url( 'field' )` | value inside an attribute |
| `sdcpb_has( 'field' )` | does the field hold a value? `'0'` counts as a value. |
| `sdcpb_link( 'field', 'class' )` | `<a>` from a link field |
| `sdcpb_image( 'field', 'large' )` | `<img>` including `alt`, `width`, `height` |
| `sdcpb_image_style( 'field' )` | `style="--sdc-image:url(…)"` for backgrounds |
| `sdcpb_each( 'list', function ( $row ) { … } )` | the rows of a repeater |
| `sdcpb_fields()` | the generic markup, when only the frame is meant to be custom |

Inside `sdcpb_each()` every helper reads the component, not the row. The row has
to come along as the last argument: `sdcpb_text( 'name', $row )`.

## When `definition.json` is enough

Without a `render.php` the plugin prints the fields generically: each field as a
labelled block, lists as rows. That is usable for FAQs, addresses and tables —
anywhere the order of the fields already is the statement.

Custom markup pays off as soon as one of these holds:

- The element carries a meaning that fields cannot express
  (`<blockquote>`, `<dl>`, `<figure>`).
- A field value is a key that has to be translated first (icon name, colour
  tone).
- The number of entries decides the layout (one tier or five).
- A rule spans several rows (exactly one highlighted tier).

## Presentation

`component.css` is loaded automatically as soon as the component appears on the
page — nothing to register. It is inlined into the `<head>`, not linked as a
separate file. To get separate files, set `sdcpb_inline_component_styles` to
`false`.

The examples work with CSS variables and `color-mix()` so they stay readable in
any theme and can be overridden with a single line:

```css
.sdc-feature-grid { --sdc-fg-min: 18rem; }
.sdc-cta { --sdc-cta-bg: #0f766e; --sdc-cta-fg: #ffffff; }
```
