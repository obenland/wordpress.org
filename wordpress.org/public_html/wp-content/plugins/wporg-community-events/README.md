# WordPress.org Community Events

This plugin is the platform layer for a WordPress.org-native events system. The public product is expected to be **Events**, with an initial launch target of replacing official local WordPress meetup groups that currently rely on Meetup.com.

The plugin should provide reusable community-event primitives. The companion `wporg-events` block theme provides the first public discovery, member, organizer, and event-detail interfaces against the plugin REST API.

## Product Direction

-   Launch target: official local WordPress meetup groups.
-   Broader model: local groups, online groups, topic groups, contributor events, workshops, trainings, and other open-source community events.
-   Deployment target: one dedicated WordPress.org subsite, likely `events.wordpress.org`.
-   Identity: WordPress.org users are required for joining, RSVPing, organizing, and commenting.
-   Profiles integration: Profiles.WordPress.org is the canonical identity surface.
-   Scope: functional parity for WordPress community needs, not a clone of Meetup.com's monetization or inbox model.

## Storage Principles

The initial architecture is custom-post-type and taxonomy first. Avoid custom database tables unless CPTs, taxonomies, post meta, and user meta have been proven insufficient for a specific workload.

WordPress post type keys have a 20-character limit, so internal keys use the `wporg_ce_` prefix while labels use the fuller product language.

## Public and Editorial Post Types

-   `wporg_ce_group`: community groups, local chapters, online groups, and topic communities.
-   `wporg_ce_event`: meetups, workshops, contributor events, trainings, and similar events.
-   `wporg_ce_venue`: reusable physical and online venues.

## Private Operational Post Types

-   `wporg_ce_member`: user-to-group membership records.
-   `wporg_ce_rsvp`: user-to-event RSVP and waitlist records.
-   `wporg_ce_suggest`: submitted group suggestions awaiting Community Team review.
-   `wporg_ce_import`: migration records for imported Meetup.com data or other sources.

Relationship post types are private posts. For user-owned relationships, `post_author` should be the WordPress.org user. For records attached to a group or event, `post_parent` should be the related group or event where possible. Meta fields store the explicit IDs as well, so the relationship remains understandable in exports and REST responses.

Memberships are user-centric relationship records, not opaque usermeta arrays. Usermeta can cache a user's group IDs later, but it should not be the canonical source of truth for group membership.

RSVPs use the same pattern: one private RSVP post per user/event, with the user as `post_author`, the event as `post_parent`, and registered meta for status, waitlist position, guests, visibility, and timestamps.

## Relationship Behavior

The first behavior layer lives in `includes/relationships.php`.

-   `join_group()` creates or updates one `wporg_ce_member` record per user/group.
-   `rsvp_to_event()` creates or updates one `wporg_ce_rsvp` record per user/event.
-   Event RSVP and waitlist counts are cached on event meta after RSVP changes.
-   Events with a positive `wporg_ce_capacity` automatically waitlist additional attending RSVPs after capacity is reached.

The first REST action layer lives in `includes/rest-api.php`, with route registration grouped into `WP_REST_Controller` subclasses under `includes/rest-api/`.

-   `GET /wporg-community-events/v1/me/memberships`
-   `GET /wporg-community-events/v1/me/rsvps`
-   `GET /wporg-community-events/v1/me/group-suggestions`
-   `GET /wporg-community-events/v1/groups`
-   `GET /wporg-community-events/v1/groups/<group_id>`
-   `PATCH /wporg-community-events/v1/groups/<group_id>`
-   `GET /wporg-community-events/v1/groups/<group_id>/membership`
-   `POST /wporg-community-events/v1/groups/<group_id>/membership`
-   `DELETE /wporg-community-events/v1/groups/<group_id>/membership`
-   `GET /wporg-community-events/v1/groups/<group_id>/events`
-   `POST /wporg-community-events/v1/groups/<group_id>/events`
-   `GET /wporg-community-events/v1/groups/<group_id>/members`
-   `GET /wporg-community-events/v1/groups/<group_id>/organizers`
-   `POST /wporg-community-events/v1/groups/<group_id>/organizers`
-   `PATCH /wporg-community-events/v1/groups/<group_id>/organizers/<membership_id>`
-   `GET /wporg-community-events/v1/groups/<group_id>/calendar.ics`
-   `GET /wporg-community-events/v1/events`
-   `GET /wporg-community-events/v1/events/<event_id>`
-   `PATCH /wporg-community-events/v1/events/<event_id>`
-   `DELETE /wporg-community-events/v1/events/<event_id>`
-   `GET /wporg-community-events/v1/events/<event_id>/calendar.ics`
-   `POST /wporg-community-events/v1/events/<event_id>/copies`
-   `GET /wporg-community-events/v1/events/<event_id>/attendees`
-   `POST /wporg-community-events/v1/events/<event_id>/attendees`
-   `PATCH /wporg-community-events/v1/events/<event_id>/attendees/<rsvp_id>`
-   `POST /wporg-community-events/v1/events/<event_id>/cancellation`
-   `GET /wporg-community-events/v1/events/<event_id>/rsvp`
-   `POST /wporg-community-events/v1/events/<event_id>/rsvp`
-   `DELETE /wporg-community-events/v1/events/<event_id>/rsvp`
-   `GET /wporg-community-events/v1/group-suggestions`
-   `POST /wporg-community-events/v1/group-suggestions`
-   `GET /wporg-community-events/v1/group-suggestions/<suggestion_id>`
-   `PATCH /wporg-community-events/v1/group-suggestions/<suggestion_id>`

These endpoints support public group and event discovery and authenticated self-service actions for the current WordPress.org user. Collection endpoints return entity arrays at the top level and expose pagination totals through `X-WP-Total` and `X-WP-TotalPages` headers so they can later be registered with `@wordpress/core-data`. Public group discovery returns published groups, location metadata, cached group counts, and taxonomy terms, with filters for country, group type, topic, and language. Current-user membership and RSVP collections power member dashboards without using usermeta as the canonical relationship store. Public member and organizer discovery returns active visible profile records while omitting private memberships. Group joins always create member-level memberships; organizer and host roles are managed through separate organizer-team endpoints. Community Team moderators can assign organizer roles. Active group organizers can promote active members to hosts and demote hosts back to members. Active organizers/hosts can publish events directly and can assign one or more active group members as event hosts. Public event discovery returns published events across public groups, with optional group filtering, pagination, `timeframe` values of `upcoming`, `past`, and `all`, and taxonomy filters for country, event format, event type, language, and topic; the nested group event endpoint uses the same response shape for group pages. The companion theme exposes these taxonomy dimensions as event and group directory filters in addition to text search and event timeframe controls. Event and group `.ics` routes provide calendar export and subscription feeds for public approved events. Event create and update requests accept `host_user_ids` and taxonomy slug arrays for `countries`, `event_formats`, `event_types`, `languages`, and `topics`. Public attendee discovery returns visible attendee or waitlist RSVPs and omits private RSVP identities. Active organizers, group hosts, and assigned event hosts can add active group members to an event, update RSVP status, edit guest counts, and set attendance states such as checked in, no show, and not coming. RSVP waitlist status is assigned by the server based on event capacity.

Logged-in users can suggest new groups. Suggestions stay separate from published group objects until a Community Team reviewer approves them; approval creates the official group and copies location metadata and taxonomy terms. Declining a suggestion preserves the review record without creating a group.

REST responses include WordPress.org profile identity data for relevant users. Event responses include a compatibility `host` object, a full `hosts` list, embedded venue/location data, event-level online URLs, taxonomy terms, and RSVP/waitlist counts, while membership, RSVP, and attendee responses include the relevant public profile shape: user ID, profile slug, display name, `profiles.wordpress.org` URL, and avatar URL. RSVP and attendee responses also include attendance status, check-in timestamp, and attendance update metadata for organizer check-in workflows.

Active organizers, group hosts, and assigned event hosts can update approved event details or cancel an approved event without deleting its public history. Canceling sets `wporg_ce_approval_status` to `canceled`, closes RSVPs, and records cancellation metadata on the event. Event posts support native WordPress comments for public discussion. New events open comments by default, and event comments require an authenticated WordPress.org user; anonymous comment submissions are rejected server-side.

Active organizers and group hosts can copy an existing event to a new date. The copy preserves reusable event details such as description, venue, hosts, taxonomy terms, capacity, RSVP policy, and online URL while creating a new event with fresh RSVP and attendance state.

The block editor and wp-admin stay aligned with the same registered meta model. Event editors get an Event details meta box for group, schedule, venue, online URL, capacity, and RSVP policy fields, so editorial users do not need to edit raw meta keys while the longer-term TypeScript editor UI evolves.

## Taxonomies

-   `wporg_ce_group_type`: local, online, topic.
-   `wporg_ce_event_type`: meetup, workshop, contributor day, do_action, training.
-   `wporg_ce_event_format`: in-person, online, hybrid.
-   `wporg_ce_topic`: subject matter and community interests.
-   `wporg_ce_language`: languages used by groups and events.
-   `wporg_ce_country`: country browsing and reporting.

## Governance Model

Group creation and official status are controlled by the Community Team. Event creation is distributed:

-   Trusted hosts and organizers can publish events directly.
-   Logged-in users can suggest new groups for Community Team review.
-   Every event should have at least one accountable WordPress.org user as host.

This keeps event ownership close to each group while preserving Community Team oversight for official community spaces.

## Migration Model

Import public and operational infrastructure first:

-   group identity
-   organizers that can be mapped to WordPress.org users
-   venues
-   upcoming events
-   public historical events
-   aggregate member and attendance counts
-   source IDs and source URLs

Do not silently migrate Meetup.com users as active members or attendees. People should reconnect through WordPress.org login before future participation is recorded as WordPress.org activity.

Source imports are reconciled through private `wporg_ce_import` posts. Migration scripts should call `upsert_import_record()` after creating or updating a local object, then use `get_import_record_id()` or `get_import_target_id()` to make repeated imports idempotent. Import records store the source system, source object ID, source URL, target type, target ID, import status, and imported time in registered post meta.

## Intentional V1 Non-Goals

-   paid organizer subscriptions
-   Meetup+ equivalent
-   automatic member identity migration
-   first-class announcements
-   private direct messages
-   member dues
-   mandatory paid event fees

These may become extension points later, but they should not shape the first implementation.

## Notifications

Group membership records store per-group email preferences for new events, event updates, and event cancellations. Event lifecycle emails are sent to active group members who have the matching preference enabled.

Attendee reminders are sent by a recurring WordPress cron hook to users with active attending RSVPs for approved events starting in the next 24 hours. Reminder state is stored on the event post so an event is reminded once for its current start time and can be reminded again if organizers reschedule it.

## Tests

Run the local environment from the repository root:

```sh
npm --prefix environments run community-events:env -- start
```

Activate the companion block theme and seed local event data:

```sh
npm --prefix environments run community-events:site:setup
```

Install the PHPUnit dependencies into the wp-env test container:

```sh
npm --prefix environments run community-events:test:setup
```

Run the plugin tests:

```sh
npm --prefix environments run community-events:test
```

New PHPUnit files should use WPCS class filenames like `tests/class-example-test.php` and classes like `Example_Test`. The test bootstrap maps those filenames for PHPUnit so each test file does not need a local `class_alias()`.

The npm test script runs each discovered `tests/class-*-test.php` file independently. That keeps the suite compatible with WPCS filenames and avoids editing `phpunit.xml.dist` for each new file.
