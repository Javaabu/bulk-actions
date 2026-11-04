---
title: AI assistants
sidebar_position: 1.35
---

The docs are plain Markdown, written for people and AI coding assistants alike. The package ships them, so they always match the version you have installed.

| File | What |
| --- | --- |
| `vendor/javaabu/bulk-actions/llms.txt` | The rules for using the package, and every page |
| `vendor/javaabu/bulk-actions/docs/` | The pages |
| `vendor/javaabu/bulk-actions/resources/boost/` | The Laravel Boost skill and guideline |

# Laravel Boost

[Laravel Boost](https://github.com/laravel/boost) finds the skill and guideline in the Composer package by itself. Run this after installing or updating:

```bash
php artisan boost:update
```

The guideline gives the assistant the rules for writing bulk actions; the skill (`javaabu-bulk-actions`) has the full API, examples and common mistakes, and loads when it works on index pages and bulk actions.

# Other assistants

Point them at `vendor/javaabu/bulk-actions/llms.txt`.
