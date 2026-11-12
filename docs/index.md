---
name: Favorites

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: database
  icon: star
  tags: favorites favorite unfavorite toggle saved items eloquent polymorphic morph users posts products uuid ulid soft deletes events unique index
  description: "Let any Eloquent model favorite any other: favorite, unfavorite and toggle with results that say what changed, a unique index that keeps each pair to one row, and native relations for counts and eager loading."

seo:
  title: "{package.fullName} - Polymorphic Favorites for Eloquent Models"
  description: "{package.name} adds favorites between any two Laravel Eloquent models: favorite, unfavorite and toggle, database-enforced uniqueness, withCount and eager loading, UUID and ULID keys."
  keywords: laravel favorites, laravel favorite package, eloquent favorites, laravel polymorphic favorites, laravel favorite unfavorite toggle, laravel favoritable, laravel favoriter, laravel favorites count, laravel morph favorites
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package that lets any Eloquent model favorite any other, with a unique index per pair, explicit changed-state results, native relations, and cleanup when a model is deleted."
  keywords: laravel, eloquent, favorites, polymorphic relations, uuid, ulid, soft deletes, events
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
