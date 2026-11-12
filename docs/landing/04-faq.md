---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory, then `php artisan migrate`.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Does the favoriter have to be a user?

No. Any Eloquent model with the `CanFavorite` trait can favorite, and any model can be favorited. Nothing in the package reads the authenticated user.

## What happens when the same user favorites twice at the same moment?

One request creates the favorite and returns true; the other finds the unique index in its way and returns false. The table never holds two rows for the pair.

## Can I use it with UUID or ULID models?

Yes. Ids are stored as strings of up to 64 characters, so integer, UUID and ULID models can favorite each other in the same table.

## Can I use it next to Laravel Likes or Laravel Follows?

Yes. A favorite is its own row in its own `favorites` table, with its own traits, model and events, so the three packages can be installed together without sharing records.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), or ask in the [Pharaonic Discord](https://discord.gg/XQG9RhvEvf).
