# Bulk Actions

[![Tests](https://github.com/Javaabu/bulk-actions/actions/workflows/tests.yml/badge.svg)](https://github.com/Javaabu/bulk-actions/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/Javaabu/bulk-actions/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/bulk-actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/bulk-actions.svg)](https://packagist.org/packages/javaabu/bulk-actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Fluent bulk actions for Laravel index pages. Each action is authorized through Laravel's Gate, can ask for input with a [stencil](https://github.com/Javaabu/stencil) form, and runs on the selected records or on everything matching the current filters, chunked and queued for large selections. The selection and action UI ships for Blade ([javaabu/forms](https://github.com/Javaabu/forms)) and Inertia React ([@javaabu/forms](https://github.com/Javaabu/react-forms)).

```php
class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected function actions(): array
    {
        return [
            DeleteAction::make()->can('delete_articles'),

            Action::make('change_status')
                ->label(__('Change status'))
                ->canEach('update')
                ->form([Select::make('status')->options(ArticleStatuses::class)->required()])
                ->each(fn (Article $article, array $data) => $article->update($data)),
        ];
    }
}
```

```php
Route::bulkActions('articles', ArticleBulkActions::class);
```

```blade
<x-forms::table model="articles">
    <x-slot:bulk-form :action="$bulk_actions->getUrl()">
        <x-bulk-actions::actions :actions="$bulk_actions" :paginator="$articles" />
    </x-slot:bulk-form>
    …
</x-forms::table>
```

```tsx
<BulkActionsTable bulkActions={bulkActions}>{rows}</BulkActionsTable>
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/docs/bulk-actions](https://docs.javaabu.com/docs/bulk-actions), and in [`docs/`](docs). AI assistants: start with [`llms.txt`](llms.txt); Laravel Boost picks up the guideline and the `javaabu-bulk-actions` skill from the package.

## Testing

```bash
composer test
pnpm install && pnpm test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
