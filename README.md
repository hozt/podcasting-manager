# Podcasting Manager

A WordPress plugin for managing podcast episodes with a full admin interface and WPGraphQL support. Built for headless and decoupled WordPress sites.

By [Jeffrey Haug](https://hozt.com)

## What It Does

Podcasting Manager adds a `podcast` custom post type to WordPress and provides everything needed to manage a podcast from the admin — episode metadata, global podcast settings, and a GraphQL API for frontend consumption.

## Features

- **Custom post type** — A dedicated `podcast` post type with title, content, featured image, excerpt, and custom episode fields.
- **Episode metadata** — Per-episode fields managed via a meta box on the edit screen: episode number, MP3 URL, episode date (with datepicker), duration (seconds), file size (bytes), and transcript.
- **Global podcast settings** — A settings page under Settings > Podcast Settings for podcast-level metadata: name, description, image, hosts, owner, license, explicit rating, series, categories, keywords, donation link, trailer URL, and update frequency.
- **Platform directory links** — Store and expose links to Apple Podcasts, Spotify, Amazon Music, iHeart, and Podcasting Index.
- **WPGraphQL integration** — Exposes all podcast data through the WPGraphQL schema. Query global settings via `podcastSettings` and per-episode fields (`episodeNumber`, `mp3File`, `episodeDate`, `episodeLength`, `fileSize`, `transcript`) on any podcast node.

## Requirements

- WordPress
- [WPGraphQL](https://www.wpgraphql.com/) (required for GraphQL support)

## GraphQL Usage

Query global podcast settings and episodes in a single request:

```graphql
{
  podcastSettings {
    name
    description
    image
    hosts
    podcastSpotifyLink
    podcastAppleLink
  }
  podcasts {
    nodes {
      title
      episodeNumber
      mp3File
      episodeDate
      episodeLength
      fileSize
      transcript
    }
  }
}
```

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate the plugin in the WordPress admin under Plugins.
3. Configure global settings under Settings > Podcast Settings.
4. Add episodes via the Podcasts post type in the admin sidebar.

## Build

```sh
git archive --format=zip --output=podcasting-manager.zip main
```

