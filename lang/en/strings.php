<?php

return [

    'confirm_text' => 'Are you sure you want to :action the selected records?',

    'done' => '{0} No records were changed.|{1} :action: 1 record done.|[2,*] :action: :count records done.',
    'skipped' => '{1} 1 record was skipped because you aren\'t allowed to change it.|[2,*] :count records were skipped because you aren\'t allowed to change them.',
    'failed' => '{1} 1 record failed.|[2,*] :count records failed.',
    'cancelled' => 'The action was cancelled.',
    'queued' => '{1} :action will run on 1 record in the background.|[2,*] :action will run on :count records in the background.',
    'run_failed' => 'The action failed. Please try again.',
    'unauthorized' => 'You aren\'t allowed to run this action.',

    'validation' => [
        'action' => 'Choose an action.',
        'selection' => 'Select at least one record.',
    ],

    'actions' => [
        'delete' => [
            'label' => 'Delete',
            'confirm' => 'Are you sure you want to delete the selected records?',
            'done' => '{0} No records were deleted.|{1} 1 record deleted.|[2,*] :count records deleted.',
        ],
        'restore' => [
            'label' => 'Restore',
            'done' => '{0} No records were restored.|{1} 1 record restored.|[2,*] :count records restored.',
        ],
        'force_delete' => [
            'label' => 'Delete permanently',
            'confirm' => 'Are you sure you want to permanently delete the selected records? This can\'t be undone.',
            'done' => '{0} No records were deleted.|{1} 1 record permanently deleted.|[2,*] :count records permanently deleted.',
        ],
    ],

    'statuses' => [
        'pending' => 'Waiting',
        'processing' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'ui' => [
        'actions' => 'Bulk actions',
        'apply' => 'Apply',
        'choose_action' => 'Choose an action',
        'selected' => ':count selected',
        'none_selected' => 'Select records to apply an action',
        'select_all_matching' => 'Select all :total',
        'all_selected' => 'All :total records are selected.',
        'all_on_page_selected' => 'All :count records on this page are selected.',
        'clear_selection' => 'Clear selection',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'running' => 'Running…',
        'queued' => 'Running in the background',
        'progress' => ':processed of :total done',
        'cancel_run' => 'Stop',
        'dismiss' => 'Dismiss',
    ],

];
