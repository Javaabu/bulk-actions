{{--
    The bulk actions bar. Put it in the bulk-form slot of <x-forms::table>:

    <x-slot:bulk-form :action="$bulkActions->getUrl()">
        <x-bulk-actions::actions :actions="$bulkActions" :paginator="$articles" />
    </x-slot:bulk-form>
--}}
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $framework = $framework ?? config('forms.framework', 'onu');
    $name = $bulkActions->getName();
    $actions = $bulkActions->getAvailableActions();
    $result = $bulkActions->getResult();
    $strings = $bulkActions->getStrings();
    $total = $bulkActions->getTotal();
    $page_count = $bulkActions->getPageCount();
    $old_action = old('action');
    $old_all = (bool) old('all');
    $dialog_actions = array_filter($actions, fn ($action) => $action->requiresConfirmationBeforeRunning() || $action->hasForm());
    $config = [
        'name' => $name,
        'total' => $total,
        'pageCount' => $page_count,
        'pollInterval' => (int) config('bulk-actions.poll_interval', 2000),
        'dialogs' => array_keys($dialog_actions),
        'strings' => $strings,
    ];
@endphp
<div class="bulk-actions flex flex-col gap-3" data-bulk-actions="{{ $name }}" data-bulk-actions-config="{{ json_encode($config) }}">
    @if($result)
        <x-forms::alert
            :type="$result->tone()"
            :framework="$framework"
            dismissible
            data-bulk-actions-result
            :data-run-url="$result->isQueued() ? $result->run_url : null"
        >
            <span data-bulk-actions-result-message>{{ $result->message }}</span>
            @if($result->isQueued())
                <span class="mt-2 flex items-center gap-2" data-bulk-actions-progress>
                    <progress class="h-2 w-full max-w-xs" max="100" @if($result->total > 0) value="{{ (int) floor($result->processed() / max($result->total, 1) * 100) }}" @endif aria-label="{{ $strings['queued'] }}"></progress>
                    <span class="text-sm" data-bulk-actions-progress-text>{{ __('bulk-actions::strings.ui.progress', ['processed' => $result->processed(), 'total' => $result->total]) }}</span>
                </span>
            @endif
        </x-forms::alert>
    @endif

    @if($actions)
        <div class="flex flex-wrap items-start gap-2">
            <div class="w-full md:w-64">
                <x-forms::select
                    name="action"
                    :framework="$framework"
                    :options="['' => ''] + collect($actions)->map(fn ($action) => $action->getLabel())->all()"
                    show-placeholder
                    :placeholder="$strings['choose_action']"
                    :label="$strings['actions']"
                    :show-label="false"
                    required
                    data-bulk-actions-select
                />
            </div>
            <div>
                <x-forms::button type="submit" color="warning" :framework="$framework" data-bulk-actions-apply>
                    {{ $strings['apply'] }}
                </x-forms::button>
            </div>
            <p class="self-center text-sm text-muted-foreground" data-bulk-actions-count aria-live="polite">{{ $strings['none_selected'] }}</p>
        </div>

        @error($name)
            <p class="text-sm text-destructive" role="alert">{{ $message }}</p>
        @enderror

        @if($total !== null)
            <div class="flex flex-wrap items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm" data-bulk-actions-all-matching @unless($old_all) hidden @endunless>
                <span data-bulk-actions-page-selected @if($old_all) hidden @endif>{{ __('bulk-actions::strings.ui.all_on_page_selected', ['count' => $page_count ?? 0]) }}</span>
                <span data-bulk-actions-all-selected @unless($old_all) hidden @endunless>{{ __('bulk-actions::strings.ui.all_selected', ['total' => number_format($total)]) }}</span>
                <button type="button" class="font-medium underline underline-offset-4" data-bulk-actions-select-all @if($old_all) hidden @endif>
                    {{ __('bulk-actions::strings.ui.select_all_matching', ['total' => number_format($total)]) }}
                </button>
                <button type="button" class="font-medium underline underline-offset-4" data-bulk-actions-clear @unless($old_all) hidden @endunless>
                    {{ $strings['clear_selection'] }}
                </button>
            </div>
        @endif

        <input type="hidden" name="all" value="{{ $old_all ? '1' : '0' }}" data-bulk-actions-all>
        <fieldset class="contents" data-bulk-actions-filters @unless($old_all) disabled @endunless>
            @foreach(\Javaabu\BulkActions\Support\QueryFields::flatten($bulkActions->getFilterParameters(), 'filters') as $field)
                <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
            @endforeach
        </fieldset>

        @foreach($dialog_actions as $action_name => $action)
            @php($form = $action->getForm())
            @php($reopen = $old_action === $action_name && $errors->any())
            <x-forms::modal
                :id="\Illuminate\Support\Str::slug($name . '-' . $action_name) . '-dialog'"
                :title="$action->getConfirmationHeading()"
                :framework="$framework"
                size="md"
                data-bulk-actions-dialog="{{ $action_name }}"
                :data-bulk-actions-reopen="$reopen ? '1' : null"
            >
                <fieldset class="flex flex-col gap-4" data-bulk-actions-fields @unless($reopen) disabled @endunless>
                    @if($action->requiresConfirmationBeforeRunning())
                        <p>{{ $action->getConfirmationText() }}</p>
                    @endif

                    @if($form)
                        {!! $form->toHtml() !!}
                    @endif
                </fieldset>

                <x-slot:footer>
                    <x-forms::button type="button" color="secondary" :framework="$framework" data-dismiss="modal">
                        {{ $strings['cancel'] }}
                    </x-forms::button>
                    <x-forms::button type="submit" :color="$action->getColor()" :framework="$framework" data-bulk-actions-confirm>
                        {{ $action->getConfirmButtonLabel() }}
                    </x-forms::button>
                </x-slot:footer>
            </x-forms::modal>
        @endforeach
    @endif

    <input type="hidden" name="redirect" value="{{ request()->fullUrl() }}">
</div>
