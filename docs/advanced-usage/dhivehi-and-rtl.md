---
title: Dhivehi and RTL
sidebar_position: 6
---

# Strings

Every string the package shows is in `lang/en/strings.php` and `lang/dv/strings.php` (namespace `bulk-actions::strings`), and the permission descriptions are in `lang/dv.json`. The app's locale decides which is used, and a queued run uses the locale it was started in. React gets the strings translated from the server in `bulkActions.strings`.

Change a line by publishing the translations:

```bash
php artisan vendor:publish --tag=bulk-actions-translations
```

Your own labels, confirmation questions and success messages go through `__()` as usual:

```php
Action::make('publish')
    ->label(__('Publish'))
    ->successMessage(__(':count articles published.'));
```

```json
// lang/dv.json
{
    "Publish": "ޝާއިޢުކުރޭ",
    ":count articles published.": ":count ލިޔުން ޝާއިޢުކުރެވިއްޖެ."
}
```

Plural lines use explicit ranges (`{0}`, `{1}`, `[2,*]`), which work in every locale.

# Right to left

The Blade view and React components use logical utilities only (`ms-`, `pe-`, `text-start`), and javaabu/forms' table, select and modal are right to left aware. Keep to logical utilities in overridden views; the package's tests check its own.

# Thaana input

Action form fields are stencil fields: add `->thaana()` to text fields that take Dhivehi.

# For review

These translations were written without a native speaker's review. Please check them:

| Key | Dhivehi | English |
| --- | --- | --- |
| `confirm_text` | ޚިޔާރުކުރި ރެކޯޑުތަކުގައި ":action" ހިންގަން ބޭނުންތޯ؟ | Are you sure you want to :action the selected records? |
| `skipped` | :count ރެކޯޑު ދޫކޮށްލެވުނީ އެ ރެކޯޑުތައް ބަދަލުކުރުމުގެ ހުއްދަ ނެތީމައެވެ. | :count records were skipped because you aren't allowed to change them. |
| `queued` | :action ފަހަތުން :count ރެކޯޑުގައި ހިންގާނެ. | :action will run on :count records in the background. |
| `actions.restore.label` | އަނބުރާ ގެންނަ | Restore |
| `actions.force_delete.label` | ދާއިމީކޮށް ފޮހެލާ | Delete permanently |
| `ui.selected` | :count ޚިޔާރުކުރެވިފައި | :count selected |
| `ui.clear_selection` | ޚިޔާރުކުރުން ދުރުކުރޭ | Clear selection |
| `ui.progress` | :total އިން :processed ނިމިއްޖެ | :processed of :total done |
| `ui.cancel_run` | ހުއްޓާލާ | Stop |
| `statuses.processing` | ހިންގަނީ | Running |
| `Bulk actions` (dv.json) | އެއްފަހަރާ ކުރާ ކަންކަން | Bulk actions (as javaabu/forms' `bulk_action`) |
