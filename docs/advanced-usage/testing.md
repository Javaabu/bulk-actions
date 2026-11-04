---
title: Testing
sidebar_position: 5
---

Test bulk actions through their route, as a user would.

```php
#[Test]
public function it_publishes_the_selected_articles(): void
{
    $editor = User::factory()->create();
    $editor->givePermissionTo('publish_articles', 'edit_articles');

    $articles = Article::factory()->count(2)->create();

    $this->actingAs($editor)
        ->post(route('articles.bulk'), [
            'action' => 'publish',
            'articles' => $articles->pluck('id')->all(),
        ])
        ->assertRedirect()
        ->assertSessionHas('bulk-actions.results.articles', fn (array $result) => $result['succeeded'] === 2);

    $this->assertDatabaseHas('articles', ['id' => $articles[0]->id, 'status' => 'published']);
}
```

`postJson()` returns the result as JSON, which is easier to assert on:

```php
$this->actingAs($author)
    ->postJson(route('articles.bulk'), ['action' => 'delete', 'articles' => [$mine->id, $theirs->id]])
    ->assertOk()
    ->assertJson(['succeeded' => 1, 'skipped' => 1]);
```

| To test | Assert |
| --- | --- |
| `can()` fails | `->assertForbidden()` |
| `canEach()` skips | `'skipped' => n` |
| No action or selection | `->assertJsonValidationErrors(['action'])` / `(['articles'])` |
| The action form | `->assertJsonValidationErrors(['status'])` |
| "All matching" | send `'all' => 1, 'filters' => ['search' => 'މާލެ']` |
| Queued | `Queue::fake()`, `->assertStatus(202)`, `Queue::assertPushed(RunBulkAction::class)` |
| An event | `Event::fake([BulkActionCompleted::class])` |

Without a route, call the bulk actions directly:

```php
request()->merge(['action' => 'publish', 'articles' => [$article->id]]);

$result = ArticleBulkActions::make()->user($editor)->execute();

$this->assertEquals(1, $result->succeeded);
```

To check which actions a user sees:

```php
$this->actingAs($author);

$this->assertArrayNotHasKey('publish', ArticleBulkActions::make()->getAvailableActions());
```

Use Thaana in tests of user-facing behaviour: titles, form input and messages.
