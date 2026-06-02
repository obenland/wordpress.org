/**
 * Interactivity API store for WordPress.org Events.
 *
 * @package WordPressdotorg\Events_Theme
 */

import {
	getConfig,
	getContext,
	store,
	withSyncEvent,
} from '@wordpress/interactivity';

/**
 * Localized UI strings provided by `wp_interactivity_config()`.
 *
 * @typedef {Object.<string, string>} MessageMap
 */

/**
 * Store configuration rendered by PHP for all interactive event views.
 *
 * `restUrl` points at the Community Events namespace without a trailing slash.
 * Mutating REST requests must include `nonce`; unauthenticated actions redirect
 * to `loginUrl` before touching local context.
 *
 * @typedef {Object} EventsConfig
 * @property {boolean}    isLoggedIn Whether the current viewer can make signed requests.
 * @property {string}     loginUrl   URL used when an action requires authentication.
 * @property {MessageMap} messages   Localized labels, status text, and error fallbacks.
 * @property {string}     nonce      REST nonce for signed same-origin requests.
 * @property {string}     restUrl    Base URL for the Events REST namespace.
 */

/**
 * Per-element context rendered by block PHP through `data-wp-context`.
 *
 * The Interactivity API keeps this object local to the current card, row, form,
 * or panel. Actions below mutate it for optimistic labels, busy flags, and
 * server-confirmed IDs without requiring a page refresh.
 *
 * @typedef {Object} EventsContext
 */

const ACTIVE_RSVP_STATUSES = [ 'attending', 'waitlisted' ];

/**
 * Checks whether a Unix timestamp is still upcoming.
 *
 * Event and directory blocks render timestamps in seconds so filtering can run
 * without reparsing localized date strings in the browser.
 *
 * @param {number} timestamp Event start timestamp in seconds.
 *
 * @return {boolean} True when the timestamp is now or in the future.
 */
const isUpcoming = ( timestamp ) =>
	timestamp >= Math.floor( Date.now() / 1000 );

/**
 * Checks whether an RSVP status should count as an active event RSVP.
 *
 * Waitlisted attendees still have an active relationship with the event, so
 * they should see cancellation and option-management controls.
 *
 * @param {string} status RSVP status returned by the Events REST API.
 *
 * @return {boolean} True when the status reserves or waitlists a spot.
 */
const isActiveRsvpStatus = ( status ) =>
	ACTIVE_RSVP_STATUSES.includes( status );

/**
 * Checks whether a selected taxonomy term matches the terms in context.
 *
 * Empty selected values intentionally match everything so unselected filters do
 * not hide cards. Context slugs come from `data-wp-context` on each item.
 * PHP normalizes each item's searchable taxonomy data to slug arrays before
 * rendering the block.
 *
 * @param {string}   selectedSlug Selected term slug from shared filter state.
 * @param {string[]} contextSlugs Term slugs assigned to the current item.
 *
 * @return {boolean} True when the item should pass the selected term filter.
 */
const matchesTermFilter = ( selectedSlug, contextSlugs ) => {
	const selected = String( selectedSlug || '' ).trim();

	if ( ! selected ) {
		return true;
	}

	if ( ! Array.isArray( contextSlugs ) ) {
		return false;
	}

	return contextSlugs.includes( selected );
};

/**
 * Gets the short feedback message shown after an RSVP request completes.
 *
 * This maps API relationship states to sentences rather than labels. Labels are
 * handled by `getRsvpStatusLabel()` for badges and static status text.
 *
 * @param {string}                 status   RSVP status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Message for the resulting RSVP state.
 */
const getRsvpStatusMessage = ( status, messages ) => {
	if ( 'waitlisted' === status ) {
		return messages.waitlisted;
	}

	if ( 'not_attending' === status ) {
		return messages.rsvpCanceled;
	}

	return messages.attending;
};

/**
 * Gets the display label for an RSVP status.
 *
 * Unknown non-empty statuses intentionally collapse to a generic localized
 * fallback so unexpected backend states do not expose raw API values in the UI.
 *
 * @param {string}                 status   RSVP status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable RSVP status label.
 */
const getRsvpStatusLabel = ( status, messages ) => {
	if ( 'attending' === status ) {
		return messages.rsvpStatusAttending;
	}

	if ( 'not_attending' === status ) {
		return messages.rsvpStatusNotAttending;
	}

	if ( 'waitlisted' === status ) {
		return messages.rsvpStatusWaitlisted;
	}

	return status ? messages.unknownStatus : '';
};

/**
 * Gets the display label for an attendee check-in status.
 *
 * Event organizers use this label after managing attendance from the event
 * detail page. The raw API value remains in context for subsequent requests.
 *
 * @param {string}                 status   Attendance status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable attendance status label.
 */
const getAttendanceStatusLabel = ( status, messages ) => {
	if ( 'checked_in' === status ) {
		return messages.attendanceStatusCheckedIn;
	}

	if ( 'no_show' === status ) {
		return messages.attendanceStatusNoShow;
	}

	if ( 'not_checked_in' === status ) {
		return messages.attendanceStatusNotCheckedIn;
	}

	if ( 'not_coming' === status ) {
		return messages.attendanceStatusNotComing;
	}

	return status ? messages.unknownStatus : '';
};

/**
 * Gets the display label for a group membership status.
 *
 * Membership routes return relationship state, not post status. This helper is
 * shared by join/leave and notification-preference actions.
 *
 * @param {string}                 status   Membership status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable membership status label.
 */
const getMembershipStatusLabel = ( status, messages ) => {
	if ( 'active' === status ) {
		return messages.membershipActive;
	}

	if ( 'left' === status ) {
		return messages.membershipLeft;
	}

	if ( 'pending' === status ) {
		return messages.membershipPending;
	}

	return status ? messages.unknownStatus : '';
};

/**
 * Gets the display label for a group membership role.
 *
 * Organizer-management responses and current-user membership responses use the
 * same role values, so this keeps row labels and membership summaries aligned.
 *
 * @param {string}                 role     Membership role returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable membership role label.
 */
const getMembershipRoleLabel = ( role, messages ) => {
	if ( 'host' === role ) {
		return messages.membershipRoleHost;
	}

	if ( 'member' === role ) {
		return messages.membershipRoleMember;
	}

	if ( 'organizer' === role ) {
		return messages.membershipRoleOrganizer;
	}

	return role ? messages.unknownStatus : '';
};

/**
 * Gets the display label for an event approval state.
 *
 * Event creation and moderation currently expose approved/canceled states in
 * the front-end UI. Other states fall back to the generic unknown label.
 *
 * @param {string}                 status   Event approval status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable event approval status label.
 */
const getEventApprovalStatusLabel = ( status, messages ) => {
	if ( 'approved' === status ) {
		return messages.eventStatusApproved;
	}

	if ( 'canceled' === status ) {
		return messages.eventStatusCanceled;
	}

	return status ? messages.unknownStatus : '';
};

/**
 * Gets the display label for a group suggestion review status.
 *
 * Review statuses are used both before and after a Community Team action. The
 * returned label updates the row immediately after the REST response confirms
 * the saved status.
 *
 * @param {string}                 status   Review status returned by the API.
 * @param {Record<string, string>} messages Localized message map from config.
 *
 * @return {string} Human-readable review status label.
 */
const getGroupSuggestionReviewStatusLabel = ( status, messages ) => {
	if ( 'approved' === status ) {
		return messages.groupSuggestionApproved;
	}

	if ( 'declined' === status ) {
		return messages.groupSuggestionDeclined;
	}

	if ( 'needs_info' === status ) {
		return messages.groupSuggestionNeedsInfo;
	}

	if ( 'pending' === status ) {
		return messages.groupSuggestionPending;
	}

	return status ? messages.unknownStatus : '';
};

/**
 * Reads a trimmed string value from form data.
 *
 * FormData returns `File`, string, or null values. These forms only submit text
 * controls, so all values are normalized to strings before REST body creation.
 *
 * @param {FormData} formData Submitted form data.
 * @param {string}   name     Form field name.
 *
 * @return {string} Trimmed field value, or an empty string.
 */
const getFormString = ( formData, name ) =>
	String( formData.get( name ) || '' ).trim();

/**
 * Reads positive integer values from a repeated form field.
 *
 * Host and organizer controls can submit repeated user IDs. Invalid and empty
 * values are ignored so optional controls do not send `NaN` to the REST API.
 *
 * @param {FormData} formData Submitted form data.
 * @param {string}   name     Repeated form field name.
 *
 * @return {number[]} Positive integer values in submitted order.
 */
const getFormIntegers = ( formData, name ) =>
	formData
		.getAll( name )
		.map( ( value ) => Number.parseInt( String( value ), 10 ) )
		.filter( ( value ) => Number.isInteger( value ) && value > 0 );

/**
 * Creates a stable REST-safe RSVP question ID from a label.
 *
 * PHP sanitizes question IDs with `sanitize_key()`. This mirrors that shape so
 * newly created questions get predictable IDs before the server normalizes the
 * event meta.
 *
 * @param {string} label Question label submitted by the organizer.
 * @param {number} index Zero-based question row index.
 *
 * @return {string} Sanitized question ID.
 */
const getRsvpQuestionId = ( label, index ) => {
	const normalized = String( label || '' )
		.toLowerCase()
		.normalize( 'NFKD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( /[^a-z0-9_-]+/g, '-' )
		.replace( /^[-_]+|[-_]+$/g, '' )
		.slice( 0, 80 );

	return normalized || `question_${ index + 1 }`;
};

/**
 * Collects organizer-defined RSVP questions from an event form.
 *
 * Question fields use indexed names so unchecked `required` boxes do not shift
 * row positions in `FormData`. Empty rows are ignored, and duplicate generated
 * IDs are suffixed before the REST API receives them.
 *
 * @param {FormData} formData Submitted event form data.
 *
 * @return {Object[]} RSVP question definitions.
 */
const getFormRsvpQuestions = ( formData ) => {
	const rows = new Map();
	const supportedTypes = [ 'text', 'textarea', 'select' ];

	for ( const [ name, value ] of formData.entries() ) {
		const match = String( name ).match(
			/^rsvp_questions\[(\d+)\]\[(id|label|type|required|choices)\]$/
		);

		if ( ! match ) {
			continue;
		}

		const index = Number.parseInt( match[ 1 ], 10 );
		const key = match[ 2 ];
		const row = rows.get( index ) || {};

		if ( 'required' === key ) {
			row.required = true;
		} else {
			row[ key ] = String( value || '' ).trim();
		}

		rows.set( index, row );
	}

	const usedIds = new Set();
	const questions = [];

	for ( const [ index, row ] of [ ...rows.entries() ].sort(
		( [ first ], [ second ] ) => first - second
	) ) {
		const label = String( row.label || '' ).trim();

		if ( ! label ) {
			continue;
		}

		const baseId = getRsvpQuestionId( row.id || label, index );
		let id = baseId;
		let suffix = 2;

		while ( usedIds.has( id ) ) {
			id = `${ baseId }_${ suffix }`;
			suffix += 1;
		}

		usedIds.add( id );

		const type = supportedTypes.includes( row.type ) ? row.type : 'text';
		const choices = String( row.choices || '' )
			.split( /\r?\n/ )
			.map( ( choice ) => choice.trim() )
			.filter( Boolean );

		questions.push( {
			choices: 'select' === type ? choices : [],
			id,
			label,
			required: Boolean( row.required ),
			type,
		} );
	}

	return questions;
};

/**
 * Collects RSVP answers from the detailed event RSVP form.
 *
 * Answer inputs are keyed by question ID, matching the REST API's answer object
 * schema. Empty answers are omitted so clearing an optional field removes the
 * stored value on save.
 *
 * @param {FormData} formData Submitted RSVP form data.
 *
 * @return {Object} Answers keyed by RSVP question ID.
 */
const getFormRsvpAnswers = ( formData ) => {
	const answers = {};

	for ( const [ name, value ] of formData.entries() ) {
		const match = String( name ).match( /^answers\[([^\]]+)\]$/ );

		if ( ! match ) {
			continue;
		}

		const answer = String( value || '' ).trim();

		if ( answer ) {
			answers[ match[ 1 ] ] = answer;
		}
	}

	return answers;
};

/**
 * Normalizes a submitted or contextual guest count.
 *
 * Guest counts are non-negative integers in the REST API. Blank inputs and
 * malformed values are treated as zero instead of blocking the request.
 *
 * @param {*} value Guest count value from form data or context.
 *
 * @return {number} Non-negative integer guest count.
 */
const getGuestCount = ( value ) => {
	const guestCount = Number.parseInt( String( value || 0 ), 10 );

	return Number.isNaN( guestCount ) ? 0 : Math.max( 0, guestCount );
};

/**
 * Normalizes a relationship visibility value.
 *
 * The front-end only exposes public/private visibility. Any unexpected value
 * becomes public, matching the default membership and RSVP behavior.
 *
 * @param {string} visibility Visibility value from form data or context.
 *
 * @return {string} Supported relationship visibility.
 */
const getRelationshipVisibility = ( visibility ) =>
	'private' === visibility ? 'private' : 'public';

/**
 * Builds the POST body for creating or updating the current user's RSVP.
 *
 * Both the compact RSVP button and the detailed options form use this shape.
 * When `formData` is omitted, the action falls back to values stored in the
 * current event context.
 *
 * @param {Object}        context  Interactivity context for the current event.
 * @param {FormData|null} formData Optional RSVP options form data.
 *
 * @return {Object} RSVP request body.
 */
const getRsvpRequestBody = ( context, formData = null ) => ( {
	...( formData ? { answers: getFormRsvpAnswers( formData ) } : {} ),
	guest_count: getGuestCount(
		formData ? formData.get( 'guest_count' ) : context.rsvpGuestCount
	),
	status: 'attending',
	visibility: getRelationshipVisibility(
		formData
			? getFormString( formData, 'visibility' )
			: context.rsvpVisibility
	),
} );

/**
 * Registers the Events Interactivity API store.
 *
 * Shared `state` powers directory filters and selected dashboard tabs. Local
 * `context` powers row/card/panel behavior such as REST IDs, current labels,
 * pending flags, and response messages. PHP owns the initial render; this store
 * only mutates the pieces that can change during front-end interactions.
 */
const { state } = store( 'wporg/events', {
	state: {
		/**
		 * Determines whether a dashboard panel should be hidden.
		 *
		 * Dashboard panels render all views for SEO and progressive enhancement.
		 * This getter hides inactive panels after the Interactivity API starts.
		 *
		 * @return {boolean} True when this panel is not the selected dashboard view.
		 */
		get isDashboardViewHidden() {
			const context = getContext();

			return state.dashboardView !== context.dashboardView;
		},

		/**
		 * Determines whether an event directory item fails the active filters.
		 *
		 * Event cards carry pre-normalized search text, taxonomy slugs, and a
		 * start timestamp in context. Shared state supplies the active controls.
		 *
		 * @return {boolean} True when timeframe, search, or taxonomy filters hide the item.
		 */
		get isEventHidden() {
			const context = getContext();
			const query = ( state.eventQuery || '' ).toLowerCase().trim();
			const timestamp = Number( context.startTimestamp || 0 );
			const matchesTimeframe =
				'all' === state.eventTimeframe ||
				( 'upcoming' === state.eventTimeframe &&
					isUpcoming( timestamp ) ) ||
				( 'past' === state.eventTimeframe &&
					! isUpcoming( timestamp ) );
			const matchesQuery =
				! query || String( context.searchText || '' ).includes( query );
			const matchesTaxonomies =
				matchesTermFilter(
					state.eventCountry,
					context.eventCountries
				) &&
				matchesTermFilter( state.eventFormat, context.eventFormats ) &&
				matchesTermFilter(
					state.eventLanguage,
					context.eventLanguages
				) &&
				matchesTermFilter( state.eventTopic, context.eventTopics ) &&
				matchesTermFilter( state.eventType, context.eventTypes );

			return ! matchesTimeframe || ! matchesQuery || ! matchesTaxonomies;
		},

		/**
		 * Determines whether a group directory item fails the active filters.
		 *
		 * Group cards render with a lowercased `searchText` string and taxonomy
		 * slug arrays so filtering does not need DOM reads.
		 *
		 * @return {boolean} True when search or taxonomy filters hide the item.
		 */
		get isGroupHidden() {
			const context = getContext();
			const query = ( state.groupQuery || '' ).toLowerCase().trim();
			const matchesQuery =
				! query || String( context.searchText || '' ).includes( query );
			const matchesTaxonomies =
				matchesTermFilter(
					state.groupCountry,
					context.groupCountries
				) &&
				matchesTermFilter(
					state.groupLanguage,
					context.groupLanguages
				) &&
				matchesTermFilter( state.groupTopic, context.groupTopics ) &&
				matchesTermFilter( state.groupType, context.groupTypes );

			return ! matchesQuery || ! matchesTaxonomies;
		},

		/**
		 * Determines whether the group suggestion form is collapsed.
		 *
		 * The form remains in the DOM so the collapsed state is reversible without
		 * a rerender and still degrades to visible content without JavaScript.
		 *
		 * @return {boolean} True when the suggestion form should be hidden.
		 */
		get isGroupSuggestionFormHidden() {
			const context = getContext();

			return ! Boolean( context.groupSuggestionOpen );
		},

		/**
		 * Determines whether a created-group notice should be hidden.
		 *
		 * The review action writes `groupSuggestionCreatedGroupId` after an
		 * approval response. The notice can then appear without reloading.
		 *
		 * @return {boolean} True when the suggestion has not created a group.
		 */
		get isCreatedGroupNoticeHidden() {
			const context = getContext();

			return ! Number( context.groupSuggestionCreatedGroupId || 0 );
		},

		/**
		 * Determines whether a venue directory item fails the active search query.
		 *
		 * Venue filtering is intentionally search-only for now because the venue
		 * directory is a support surface for event creation, not primary browsing.
		 *
		 * @return {boolean} True when the venue should be hidden.
		 */
		get isVenueHidden() {
			const context = getContext();
			const query = ( state.venueQuery || '' ).toLowerCase().trim();

			return Boolean(
				query && ! String( context.searchText || '' ).includes( query )
			);
		},

		/**
		 * Determines whether a dashboard tab controls the selected view.
		 *
		 * Used for ARIA and visual selected state on the dashboard tab controls.
		 *
		 * @return {boolean} True when this control is selected.
		 */
		get isSelectedDashboardView() {
			const context = getContext();

			return state.dashboardView === context.dashboardView;
		},

		/**
		 * Determines whether the event creation form should be hidden.
		 *
		 * The server decides whether the viewer can create events for the group
		 * and renders that boolean into context.
		 *
		 * @return {boolean} True when the current user cannot create group events.
		 */
		get isEventFormHidden() {
			const context = getContext();

			return ! Boolean( context.canCreateEvents );
		},

		/**
		 * Determines whether the event creation prompt should be hidden.
		 *
		 * @return {boolean} True when the full creation form is available.
		 */
		get isEventCreatePromptHidden() {
			const context = getContext();

			return Boolean( context.canCreateEvents );
		},

		/**
		 * Determines whether the RSVP button should reject interaction.
		 *
		 * Busy state prevents duplicate requests. Canceled events keep their RSVP
		 * controls visible but inert so users can still see prior state.
		 *
		 * @return {boolean} True while a request is pending or the event is canceled.
		 */
		get isRsvpButtonDisabled() {
			const context = getContext();

			return Boolean( context.rsvpBusy || context.isEventCanceled );
		},

		/**
		 * Determines whether the RSVP options form should be hidden.
		 *
		 * Canceled events keep the RSVP summary visible but hide controls that
		 * would no longer save useful changes.
		 *
		 * @return {boolean} True when the event can no longer accept RSVP changes.
		 */
		get isRsvpOptionsHidden() {
			const context = getContext();

			return Boolean( context.isEventCanceled );
		},

		/**
		 * Determines whether the RSVP cancellation button should be hidden.
		 *
		 * @return {boolean} True until the viewer has an active RSVP.
		 */
		get isRsvpCancelButtonHidden() {
			const context = getContext();

			return (
				! Boolean( context.isEventRsvped ) ||
				Boolean( context.isEventCanceled )
			);
		},

		/**
		 * Determines whether the post-event feedback form should be hidden.
		 *
		 * The backend is authoritative for eligibility. Context only decides
		 * whether the form is shown before and after a successful submission.
		 *
		 * @return {boolean} True when the viewer cannot submit feedback here.
		 */
		get isFeedbackFormHidden() {
			const context = getContext();

			return (
				! Boolean( context.isFeedbackFormAvailable ) ||
				Boolean( context.feedbackSubmitted )
			);
		},

		/**
		 * Determines whether the feedback submit button is disabled.
		 *
		 * @return {boolean} True while feedback is being submitted.
		 */
		get isFeedbackButtonDisabled() {
			const context = getContext();

			return Boolean( context.feedbackBusy );
		},

		/**
		 * Determines whether the copied event link should be hidden.
		 *
		 * The copy action writes `eventCopyUrl` after the REST request returns the
		 * normal event entity response.
		 *
		 * @return {boolean} True until a copied event URL is available.
		 */
		get isEventCopyLinkHidden() {
			const context = getContext();

			return ! Boolean( context.eventCopyUrl );
		},

		/**
		 * Determines whether a timeframe filter control is selected.
		 *
		 * @return {boolean} True when the control matches the active timeframe.
		 */
		get isSelectedEventTimeframe() {
			const context = getContext();

			return state.eventTimeframe === context.targetTimeframe;
		},

		/**
		 * Determines whether a managed organizer row should be hidden.
		 *
		 * Member lists can reuse organizer row markup. Plain members are hidden
		 * from organizer-management controls unless promoted.
		 *
		 * @return {boolean} True when the row represents a plain group member.
		 */
		get isManagedOrganizerHidden() {
			const context = getContext();

			return 'member' === context.groupOrganizerRole;
		},

		/**
		 * Reads whether the current membership wants new-event notifications.
		 *
		 * @return {boolean} True when the preference is enabled.
		 */
		get isNewEventsNotificationChecked() {
			const context = getContext();

			return Boolean(
				context.membershipNotificationPreferences?.new_events
			);
		},

		/**
		 * Reads whether the current membership wants event-update notifications.
		 *
		 * @return {boolean} True when the preference is enabled.
		 */
		get isEventUpdatesNotificationChecked() {
			const context = getContext();

			return Boolean(
				context.membershipNotificationPreferences?.event_updates
			);
		},

		/**
		 * Reads whether the current membership wants cancellation notifications.
		 *
		 * @return {boolean} True when the preference is enabled.
		 */
		get isEventCancellationsNotificationChecked() {
			const context = getContext();

			return Boolean(
				context.membershipNotificationPreferences?.event_cancellations
			);
		},

		/**
		 * Determines whether membership notification controls should be hidden.
		 *
		 * Notification preferences are stored on active memberships. Viewers who
		 * have left or have not joined should not see preference controls.
		 *
		 * @return {boolean} True when the viewer is not an active group member.
		 */
		get isMembershipNotificationFormHidden() {
			const context = getContext();

			return ! Boolean( context.isGroupMember );
		},
	},
	actions: {
		/**
		 * Selects the dashboard view associated with the current control.
		 *
		 * @return {void}
		 */
		setDashboardView() {
			const context = getContext();

			state.dashboardView = context.dashboardView;
		},

		/**
		 * Selects the event timeframe associated with the current control.
		 *
		 * @return {void}
		 */
		setEventTimeframe() {
			const context = getContext();

			state.eventTimeframe = context.targetTimeframe;
		},

		/**
		 * Updates the shared event directory search query.
		 *
		 * @param {Event} event Search input event.
		 *
		 * @return {void}
		 */
		updateEventQuery( event ) {
			state.eventQuery = event.target.value;
		},

		/**
		 * Updates one of the shared event taxonomy filters.
		 *
		 * The input `name` must match the corresponding store key.
		 *
		 * @param {Event} event Filter control change event.
		 *
		 * @return {void}
		 */
		updateEventFilter( event ) {
			state[ event.target.name ] = event.target.value;
		},

		/**
		 * Updates the shared group directory search query.
		 *
		 * @param {Event} event Search input event.
		 *
		 * @return {void}
		 */
		updateGroupQuery( event ) {
			state.groupQuery = event.target.value;
		},

		/**
		 * Updates one of the shared group taxonomy filters.
		 *
		 * The input `name` must match the corresponding store key.
		 *
		 * @param {Event} event Filter control change event.
		 *
		 * @return {void}
		 */
		updateGroupFilter( event ) {
			state[ event.target.name ] = event.target.value;
		},

		/**
		 * Opens or closes the group suggestion form in the current context.
		 *
		 * The toggle lives in context so multiple independently rendered group
		 * directory blocks would not accidentally open or close each other.
		 *
		 * @return {void}
		 */
		toggleGroupSuggestionForm() {
			const context = getContext();

			context.groupSuggestionOpen = ! Boolean(
				context.groupSuggestionOpen
			);
		},

		/**
		 * Updates the shared venue directory search query.
		 *
		 * @param {Event} event Search input event.
		 *
		 * @return {void}
		 */
		updateVenueQuery( event ) {
			state.venueQuery = event.target.value;
		},

		/**
		 * Updates one local notification preference before it is saved.
		 *
		 * The checkbox state is staged in context and sent by
		 * `saveMembershipNotifications()`. No REST request is made per checkbox.
		 *
		 * @param {Event} event Preference checkbox change event.
		 *
		 * @return {void}
		 */
		updateMembershipNotificationPreference( event ) {
			const context = getContext();
			const name = event.target.name;

			context.membershipNotificationPreferences = {
				...( context.membershipNotificationPreferences || {} ),
				[ name ]: event.target.checked,
			};
		},

		/**
		 * Updates the current membership visibility before saving.
		 *
		 * Visibility controls whether the viewer appears in public group member
		 * lists. The value is persisted by `saveMembershipNotifications()`.
		 *
		 * @param {Event} event Visibility select change event.
		 *
		 * @return {void}
		 */
		updateMembershipVisibility( event ) {
			const context = getContext();

			context.membershipVisibility = getRelationshipVisibility(
				event.target.value
			);
		},

		/**
		 * Updates the RSVP guest count in local context before saving.
		 *
		 * This mirrors the same normalization used for the eventual REST body so
		 * the context is always safe to submit from the compact RSVP action.
		 *
		 * @param {Event} event Guest count input event.
		 *
		 * @return {void}
		 */
		updateRsvpGuestCount( event ) {
			const context = getContext();
			const guestCount = Number.parseInt(
				String( event.target.value || 0 ),
				10
			);

			context.rsvpGuestCount = Number.isNaN( guestCount )
				? 0
				: Math.max( 0, guestCount );
		},

		/**
		 * Updates the RSVP attendee-list visibility in local context before saving.
		 *
		 * The value is not persisted until `saveRsvp()` or `rsvp()` sends the
		 * relationship request.
		 *
		 * @param {Event} event Visibility select change event.
		 *
		 * @return {void}
		 */
		updateRsvpVisibility( event ) {
			const context = getContext();

			context.rsvpVisibility = event.target.value || 'public';
		},

		/**
		 * Submits a proposed new group to the Community Team review queue.
		 *
		 * `withSyncEvent` keeps the submitted form event available while the
		 * generator yields to REST requests. On success the form resets and the
		 * server-rendered review queue remains unchanged until a moderator reloads
		 * or visits the page.
		 *
		 * @param {SubmitEvent} event Suggestion form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitGroupSuggestion: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const title = getFormString( formData, 'title' );
			const locationLabel = getFormString( formData, 'location_label' );

			if ( ! title ) {
				context.groupSuggestionMessage = messages.groupSuggestionTitle;
				return;
			}

			if ( ! locationLabel ) {
				context.groupSuggestionMessage =
					messages.groupSuggestionLocation;
				return;
			}

			const body = {
				city: getFormString( formData, 'city' ),
				description: getFormString( formData, 'description' ),
				excerpt: getFormString( formData, 'excerpt' ),
				location_label: locationLabel,
				region: getFormString( formData, 'region' ),
				timezone: getFormString( formData, 'timezone' ),
				title,
				website_url: getFormString( formData, 'website_url' ),
			};

			context.groupSuggestionBusy = true;
			context.groupSuggestionButton = messages.suggestGroupSubmitting;
			context.groupSuggestionMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + '/group-suggestions',
					{
						body: JSON.stringify( body ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupSuggestion
					);
				}

				context.groupSuggestionSubmitted = true;
				context.groupSuggestionMessage = messages.groupSuggestionSent;
				form.reset();
			} catch ( error ) {
				context.groupSuggestionMessage =
					error.message || messages.unableGroupSuggestion;
			} finally {
				context.groupSuggestionBusy = false;
				context.groupSuggestionButton = messages.suggestGroup;
			}
		} ),

		/**
		 * Saves a Community Team review decision for a group suggestion.
		 *
		 * `withSyncEvent` keeps the submitted form event available while the
		 * generator yields to REST requests. The REST response is authoritative:
		 * it can include a newly created group ID when an approval creates a real
		 * group from the suggestion.
		 *
		 * @param {SubmitEvent} event Review form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitGroupSuggestionReview: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();
			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const suggestionId =
				Number.parseInt(
					String(
						context.groupSuggestionId ||
							formData.get( 'suggestion_id' ) ||
							0
					),
					10
				) || 0;
			const reviewStatus = getFormString( formData, 'review_status' );
			const duplicateGroupId =
				Number.parseInt(
					String( formData.get( 'duplicate_group_id' ) || 0 ),
					10
				) || 0;

			if ( ! suggestionId || ! reviewStatus ) {
				context.groupSuggestionReviewMessage =
					messages.groupSuggestionReviewStatus;
				return;
			}

			const body = {
				duplicate_group_id: Math.max( 0, duplicateGroupId ),
				review_note: getFormString( formData, 'review_note' ),
				review_status: reviewStatus,
			};

			context.groupSuggestionReviewBusy = true;
			context.groupSuggestionReviewButton =
				messages.groupSuggestionReviewSaving;
			context.groupSuggestionReviewMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + `/group-suggestions/${ suggestionId }`,
					{
						body: JSON.stringify( body ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'PATCH',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupSuggestionReview
					);
				}

				context.groupSuggestionCreatedGroupId =
					Number( data.created_group_id || 0 ) || 0;
				context.groupSuggestionReviewStatus =
					data.review_status || reviewStatus;
				context.groupSuggestionReviewStatusLabel =
					getGroupSuggestionReviewStatusLabel(
						context.groupSuggestionReviewStatus,
						messages
					);
				context.groupSuggestionReviewMessage =
					messages.groupSuggestionReviewSaved;
			} catch ( error ) {
				context.groupSuggestionReviewMessage =
					error.message || messages.unableGroupSuggestionReview;
			} finally {
				context.groupSuggestionReviewBusy = false;
				context.groupSuggestionReviewButton =
					messages.groupSuggestionReviewSave;
			}
		} ),

		/**
		 * Adds an existing active group member to the organizer team.
		 *
		 * The API enforces group permissions and relationship validity. The UI
		 * only stages a success/error message because the current organizer list
		 * still comes from the server render.
		 *
		 * @param {SubmitEvent} event Organizer add form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		addGroupOrganizer: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const userId = Number.parseInt(
				getFormString( formData, 'user_id' ),
				10
			);
			const role = getFormString( formData, 'role' ) || 'host';

			if ( Number.isNaN( userId ) || userId <= 0 ) {
				return;
			}

			context.groupOrganizerAddBusy = true;
			context.groupOrganizerAddButton = messages.groupOrganizerAdding;
			context.groupOrganizerAddMessage = '';

			try {
				const response = yield fetch(
					config.restUrl +
						'/groups/' +
						context.groupId +
						'/organizers',
					{
						body: JSON.stringify( {
							role,
							user_id: userId,
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupOrganizerUpdate
					);
				}

				context.groupOrganizerAddMessage = messages.groupOrganizerAdded;
				form.reset();
			} catch ( error ) {
				context.groupOrganizerAddMessage =
					error.message || messages.unableGroupOrganizerUpdate;
			} finally {
				context.groupOrganizerAddBusy = false;
				context.groupOrganizerAddButton = messages.addToTeam;
			}
		} ),

		/**
		 * Updates an organizer or host role for the current group row.
		 *
		 * After a successful PATCH, the row context is updated with the saved role
		 * and localized label so the list reflects the backend response.
		 *
		 * @param {SubmitEvent} event Organizer role form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		updateGroupOrganizer: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.groupId || ! context.groupOrganizerMembershipId ) {
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const role = getFormString( formData, 'role' ) || 'host';

			context.groupOrganizerUpdateBusy = true;
			context.groupOrganizerUpdateButton =
				messages.groupOrganizerUpdating;
			context.groupOrganizerUpdateMessage = '';

			try {
				const response = yield fetch(
					config.restUrl +
						'/groups/' +
						context.groupId +
						'/organizers/' +
						context.groupOrganizerMembershipId,
					{
						body: JSON.stringify( {
							role,
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'PATCH',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupOrganizerUpdate
					);
				}

				context.groupOrganizerRole = data.role || role;
				context.groupOrganizerRoleLabel = getMembershipRoleLabel(
					context.groupOrganizerRole,
					messages
				);
				context.groupOrganizerUpdateMessage =
					messages.groupOrganizerUpdated;
			} catch ( error ) {
				context.groupOrganizerUpdateMessage =
					error.message || messages.unableGroupOrganizerUpdate;
			} finally {
				context.groupOrganizerUpdateBusy = false;
				context.groupOrganizerUpdateButton =
					messages.updateOrganizerRole;
			}
		} ),

		/**
		 * Updates editable public profile fields for the current group.
		 *
		 * The route returns the normal group entity response so future UI layers
		 * can reuse the same endpoint for a core-data-backed group editor.
		 *
		 * @param {SubmitEvent} event Group profile form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitGroupProfile: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.groupId ) {
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );

			context.groupProfileBusy = true;
			context.groupProfileButton = messages.groupProfileSaving;
			context.groupProfileMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + '/groups/' + context.groupId,
					{
						body: JSON.stringify( {
							website_url: getFormString(
								formData,
								'website_url'
							),
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'PATCH',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupProfileUpdate
					);
				}

				context.groupWebsiteUrl = data.website_url || '';
				context.groupProfileMessage = messages.groupProfileSaved;
			} catch ( error ) {
				context.groupProfileMessage =
					error.message || messages.unableGroupProfileUpdate;
			} finally {
				context.groupProfileBusy = false;
				context.groupProfileButton = messages.saveGroupProfile;
			}
		} ),

		/**
		 * Creates a group event from the front-end management form.
		 *
		 * When the form includes a venue title, the venue is created first and
		 * its returned ID is attached to the event creation request. The route
		 * expects UTC timestamps, so browser `Date` values are serialized before
		 * submission while the browser timezone is stored separately.
		 *
		 * @param {SubmitEvent} event Event creation form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitGroupEvent: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const startDate = new Date( getFormString( formData, 'start' ) );

			if ( Number.isNaN( startDate.getTime() ) ) {
				context.eventFormMessage = messages.eventStartRequired;
				return;
			}

			const end = getFormString( formData, 'end' );
			let endDate = null;

			if ( end ) {
				endDate = new Date( end );

				if ( Number.isNaN( endDate.getTime() ) ) {
					context.eventFormMessage = messages.eventEndInvalid;
					return;
				}

				if ( endDate <= startDate ) {
					context.eventFormMessage = messages.eventEndBeforeStart;
					return;
				}
			}

			const capacity = Number.parseInt(
				getFormString( formData, 'capacity' ),
				10
			);
			const venueId = Number.parseInt(
				getFormString( formData, 'venue_id' ),
				10
			);
			const hostUserIds = getFormIntegers( formData, 'host_user_ids' );
			const eventFormat = getFormString( formData, 'event_format' );
			const eventType = getFormString( formData, 'event_type' );
			const venueTitle = getFormString( formData, 'venue_title' );
			const body = {
				capacity: Number.isNaN( capacity )
					? 0
					: Math.max( 0, capacity ),
				description: getFormString( formData, 'description' ),
				event_formats: eventFormat ? [ eventFormat ] : [],
				event_types: eventType ? [ eventType ] : [],
				excerpt: getFormString( formData, 'excerpt' ),
				rsvp_questions: getFormRsvpQuestions( formData ),
				rsvp_policy: 'open',
				start_utc: startDate.toISOString().replace( '.000Z', 'Z' ),
				timezone:
					Intl.DateTimeFormat().resolvedOptions().timeZone || '',
				title: getFormString( formData, 'title' ),
				venue_id: Number.isNaN( venueId ) ? 0 : Math.max( 0, venueId ),
			};
			const onlineUrl = getFormString( formData, 'online_url' );

			if ( hostUserIds.length ) {
				body.host_user_ids = hostUserIds;
			}

			if ( endDate ) {
				body.end_utc = endDate.toISOString().replace( '.000Z', 'Z' );
			}

			if ( onlineUrl ) {
				body.online_url = onlineUrl;
			}

			context.eventFormBusy = true;
			context.eventFormButton = messages.eventCreating;
			context.eventFormMessage = '';

			try {
				if ( venueTitle ) {
					const venueCountry = getFormString(
						formData,
						'venue_country'
					);

					context.eventFormButton = messages.venueCreating;

					const venueResponse = yield fetch(
						config.restUrl + '/venues',
						{
							body: JSON.stringify( {
								accessibility_notes: getFormString(
									formData,
									'venue_accessibility_notes'
								),
								address: getFormString(
									formData,
									'venue_address'
								),
								city: getFormString( formData, 'venue_city' ),
								countries: venueCountry ? [ venueCountry ] : [],
								group_id: context.groupId,
								postal_code: getFormString(
									formData,
									'venue_postal_code'
								),
								region: getFormString(
									formData,
									'venue_region'
								),
								title: venueTitle,
							} ),
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': config.nonce,
							},
							method: 'POST',
						}
					);
					const venueData = yield venueResponse.json();

					if ( ! venueResponse.ok ) {
						throw new Error(
							venueData.message || messages.unableVenueCreate
						);
					}

					body.venue_id = venueData.id || 0;
					context.eventFormButton = messages.eventCreating;
				}

				const response = yield fetch(
					config.restUrl + '/groups/' + context.groupId + '/events',
					{
						body: JSON.stringify( body ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableEventCreate
					);
				}

				context.eventFormMessage = messages.eventCreated;
				form.reset();
			} catch ( error ) {
				context.eventFormMessage =
					error.message || messages.unableEventCreate;
			} finally {
				context.eventFormBusy = false;
				context.eventFormButton = messages.createEvent;
			}
		} ),

		/**
		 * Saves edits to an existing group event.
		 *
		 * This mirrors `submitGroupEvent()` but always targets the event route and
		 * keeps the event status label in sync with the REST response.
		 *
		 * @param {SubmitEvent} event Event management form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitEventUpdate: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const startDate = new Date( getFormString( formData, 'start' ) );

			if ( Number.isNaN( startDate.getTime() ) ) {
				context.eventManageMessage = messages.eventStartRequired;
				return;
			}

			const end = getFormString( formData, 'end' );
			let endDate = null;

			if ( end ) {
				endDate = new Date( end );

				if ( Number.isNaN( endDate.getTime() ) ) {
					context.eventManageMessage = messages.eventEndInvalid;
					return;
				}

				if ( endDate <= startDate ) {
					context.eventManageMessage = messages.eventEndBeforeStart;
					return;
				}
			}

			const capacity = Number.parseInt(
				getFormString( formData, 'capacity' ),
				10
			);
			const venueId = Number.parseInt(
				getFormString( formData, 'venue_id' ),
				10
			);
			const hostUserIds = getFormIntegers( formData, 'host_user_ids' );
			const eventFormat = getFormString( formData, 'event_format' );
			const eventType = getFormString( formData, 'event_type' );
			const body = {
				capacity: Number.isNaN( capacity )
					? 0
					: Math.max( 0, capacity ),
				description: getFormString( formData, 'description' ),
				end_utc: endDate
					? endDate.toISOString().replace( '.000Z', 'Z' )
					: '',
				event_formats: eventFormat ? [ eventFormat ] : [],
				event_types: eventType ? [ eventType ] : [],
				excerpt: getFormString( formData, 'excerpt' ),
				online_url: getFormString( formData, 'online_url' ),
				rsvp_questions: getFormRsvpQuestions( formData ),
				rsvp_policy: getFormString( formData, 'rsvp_policy' ) || 'open',
				start_utc: startDate.toISOString().replace( '.000Z', 'Z' ),
				timezone:
					Intl.DateTimeFormat().resolvedOptions().timeZone || '',
				title: getFormString( formData, 'title' ),
				venue_id: Number.isNaN( venueId ) ? 0 : Math.max( 0, venueId ),
			};

			if ( hostUserIds.length ) {
				body.host_user_ids = hostUserIds;
			}

			context.eventManageBusy = true;
			context.eventManageButton = messages.eventUpdating;
			context.eventManageMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId,
					{
						body: JSON.stringify( body ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'PATCH',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableEventUpdate
					);
				}

				context.eventStatusLabel = getEventApprovalStatusLabel(
					data.approval_status,
					messages
				);
				context.eventManageMessage = messages.eventUpdated;
			} catch ( error ) {
				context.eventManageMessage =
					error.message || messages.unableEventUpdate;
			} finally {
				context.eventManageBusy = false;
				context.eventManageButton = messages.saveEvent;
			}
		} ),

		/**
		 * Creates a new event by copying the current event's reusable details.
		 *
		 * Organizers use this for recurring or similar meetups. The backend copies
		 * hosts, venue, taxonomy terms, capacity, RSVP policy, and content while
		 * this action supplies the new date and optional title override.
		 *
		 * @param {SubmitEvent} event Event copy form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		copyEvent: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const startDate = new Date( getFormString( formData, 'start' ) );

			if ( Number.isNaN( startDate.getTime() ) ) {
				context.eventCopyMessage = messages.eventStartRequired;
				return;
			}

			const end = getFormString( formData, 'end' );
			let endDate = null;

			if ( end ) {
				endDate = new Date( end );

				if ( Number.isNaN( endDate.getTime() ) ) {
					context.eventCopyMessage = messages.eventEndInvalid;
					return;
				}

				if ( endDate <= startDate ) {
					context.eventCopyMessage = messages.eventEndBeforeStart;
					return;
				}
			}

			const body = {
				end_utc: endDate
					? endDate.toISOString().replace( '.000Z', 'Z' )
					: '',
				start_utc: startDate.toISOString().replace( '.000Z', 'Z' ),
				title: getFormString( formData, 'title' ),
			};

			context.eventCopyBusy = true;
			context.eventCopyButton = messages.eventCopying;
			context.eventCopyMessage = '';
			context.eventCopyUrl = '';

			try {
				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId + '/copies',
					{
						body: JSON.stringify( body ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error( data.message || messages.unableEventCopy );
				}

				context.eventCopyMessage = messages.eventCopied;
				context.eventCopyUrl = data.link || '';
			} catch ( error ) {
				context.eventCopyMessage =
					error.message || messages.unableEventCopy;
			} finally {
				context.eventCopyBusy = false;
				context.eventCopyButton = messages.eventCopy;
			}
		} ),

		/**
		 * Cancels the current event and updates the visible status controls.
		 *
		 * Cancellation is modeled as a POST to the cancellation subresource so the
		 * request can carry an optional reason while keeping DELETE semantics
		 * available on the canonical event route.
		 *
		 * @param {Event} event Cancel button click event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		cancelEvent: function* ( event ) {
			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( context.eventCanceled ) {
				return;
			}

			const messages = config.messages || {};
			const panel = event.target.closest( '.wporg-events-panel' );
			const reasonInput = panel
				? panel.querySelector( '[name="cancellation_reason"]' )
				: null;
			const reason = reasonInput ? reasonInput.value.trim() : '';

			context.eventCancelBusy = true;
			context.eventCancelButton = messages.eventCanceling;
			context.eventCancelMessage = '';

			try {
				const response = yield fetch(
					config.restUrl +
						'/events/' +
						context.eventId +
						'/cancellation',
					{
						body: JSON.stringify( {
							reason,
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableEventCancel
					);
				}

				context.eventCanceled = 'canceled' === data.approval_status;
				context.eventStatusLabel = getEventApprovalStatusLabel(
					data.approval_status,
					messages
				);
				context.eventCancelButton = messages.eventCanceledButton;
				context.eventCancelMessage = messages.eventCanceledPublic;
			} catch ( error ) {
				context.eventCancelButton = messages.eventCancel;
				context.eventCancelMessage =
					error.message || messages.unableEventCancel;
			} finally {
				context.eventCancelBusy = false;
			}
		},

		/**
		 * Updates an attendee's RSVP and attendance status as an event manager.
		 *
		 * The target button supplies the requested statuses through data
		 * attributes, while the row context supplies the RSVP and event IDs. The
		 * row is updated from the response so check-in controls can be used
		 * repeatedly without a page refresh.
		 *
		 * @param {Event} event Attendance action click event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		updateAttendeeAttendance: withSyncEvent( function* ( event ) {
			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.eventId || ! context.rsvpId ) {
				return;
			}

			const messages = config.messages || {};
			const target = event.currentTarget;
			const attendanceStatus =
				target.dataset.attendanceStatus ||
				context.attendanceStatus ||
				'not_checked_in';
			const rsvpStatus =
				target.dataset.rsvpStatus || context.rsvpStatus || 'attending';
			const guestCount = Number.parseInt(
				String( context.guestCount || 0 ),
				10
			);

			context.attendeeManageBusy = true;
			context.attendeeManageMessage = messages.attendeeUpdating;

			try {
				const response = yield fetch(
					config.restUrl +
						'/events/' +
						context.eventId +
						'/attendees/' +
						context.rsvpId,
					{
						body: JSON.stringify( {
							attendance_status: attendanceStatus,
							guest_count: Number.isNaN( guestCount )
								? 0
								: Math.max( 0, guestCount ),
							status: rsvpStatus,
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'PATCH',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableAttendeeUpdate
					);
				}

				context.attendanceStatus =
					data.attendance_status || attendanceStatus;
				context.attendanceStatusLabel = getAttendanceStatusLabel(
					context.attendanceStatus,
					messages
				);
				context.guestCount = data.guest_count || 0;
				context.rsvpStatus = data.status || rsvpStatus;
				context.attendeeManageMessage =
					context.attendanceStatusLabel || '';
			} catch ( error ) {
				context.attendeeManageMessage =
					error.message || messages.unableAttendeeUpdate;
			} finally {
				context.attendeeManageBusy = false;
			}
		} ),

		/**
		 * Adds an active group member to the current event as a manager.
		 *
		 * The backend owns attendee eligibility and capacity handling. The UI
		 * posts the selected member, optional guest count, visibility, RSVP
		 * status, and RSVP-question answers, then removes that member from the
		 * candidate select to prevent duplicate immediate submissions.
		 *
		 * @param {SubmitEvent} event Attendee add form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		addEventAttendee: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.eventId ) {
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const userId = Number.parseInt(
				getFormString( formData, 'user_id' ),
				10
			);

			if ( Number.isNaN( userId ) || userId <= 0 ) {
				context.eventAttendeeAddMessage = messages.attendeeUserRequired;
				return;
			}

			context.eventAttendeeAddBusy = true;
			context.eventAttendeeAddButton = messages.attendeeAdding;
			context.eventAttendeeAddMessage = '';

			try {
				const response = yield fetch(
					config.restUrl +
						'/events/' +
						context.eventId +
						'/attendees',
					{
						body: JSON.stringify( {
							answers: getFormRsvpAnswers( formData ),
							guest_count: getGuestCount(
								formData.get( 'guest_count' )
							),
							status:
								getFormString( formData, 'status' ) ||
								'attending',
							user_id: userId,
							visibility: getRelationshipVisibility(
								getFormString( formData, 'visibility' )
							),
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableAttendeeAdd
					);
				}

				const selectedOption = form.querySelector(
					'[name="user_id"] option:checked'
				);

				if ( selectedOption && selectedOption.value ) {
					selectedOption.remove();
				}

				form.reset();
				context.eventAttendeeAddMessage = messages.attendeeAdded;
			} catch ( error ) {
				context.eventAttendeeAddMessage =
					error.message || messages.unableAttendeeAdd;
			} finally {
				context.eventAttendeeAddBusy = false;
				context.eventAttendeeAddButton = messages.addAttendee;
			}
		} ),

		/**
		 * Sends a manager-authored email to event attendees.
		 *
		 * The REST action sends immediately and returns counts. The UI keeps the
		 * attendee list server-rendered and only updates the status message.
		 *
		 * @param {SubmitEvent} event Attendee message form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		sendEventAttendeeMessage: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.eventId ) {
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const subject = getFormString( formData, 'subject' );
			const message = getFormString( formData, 'message' );

			if ( ! subject ) {
				context.eventMessageStatus =
					messages.eventMessageSubjectRequired;
				return;
			}

			if ( ! message ) {
				context.eventMessageStatus = messages.eventMessageBodyRequired;
				return;
			}

			context.eventMessageBusy = true;
			context.eventMessageButton = messages.eventMessageSending;
			context.eventMessageStatus = '';

			try {
				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId + '/messages',
					{
						body: JSON.stringify( {
							message,
							status:
								getFormString( formData, 'status' ) || 'all',
							subject,
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableEventMessage
					);
				}

				context.eventMessageStatus = messages.eventMessageSent;
				form.reset();
			} catch ( error ) {
				context.eventMessageStatus =
					error.message || messages.unableEventMessage;
			} finally {
				context.eventMessageBusy = false;
				context.eventMessageButton = messages.sendMessage;
			}
		} ),

		/**
		 * Submits post-event feedback from an attendee.
		 *
		 * The list of existing feedback remains server-rendered. After a
		 * successful POST, the form hides and the status message confirms receipt.
		 *
		 * @param {SubmitEvent} event Feedback form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		submitEventFeedback: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			if ( ! context.eventId ) {
				return;
			}

			const messages = config.messages || {};
			const form = event.target;
			const formData = new FormData( form );
			const rating = Number.parseInt(
				getFormString( formData, 'rating' ),
				10
			);

			if ( Number.isNaN( rating ) || rating < 1 || rating > 5 ) {
				context.feedbackMessage = messages.eventFeedbackRatingRequired;
				return;
			}

			context.feedbackBusy = true;
			context.feedbackButton = messages.eventFeedbackSaving;
			context.feedbackMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId + '/feedback',
					{
						body: JSON.stringify( {
							rating,
							review: getFormString( formData, 'review' ),
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableEventFeedback
					);
				}

				context.feedbackSubmitted = true;
				context.isFeedbackFormAvailable = false;
				context.feedbackMessage = messages.eventFeedbackSaved;
				form.reset();
			} catch ( error ) {
				context.feedbackMessage =
					error.message || messages.unableEventFeedback;
			} finally {
				context.feedbackBusy = false;
				context.feedbackButton = messages.shareFeedback;
			}
		} ),

		/**
		 * Joins or leaves the current group for the logged-in user.
		 *
		 * The same button sends POST or DELETE depending on current context. The
		 * response updates membership state and notification preferences for other
		 * controls in the same group detail view.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		toggleGroupMembership: function* () {
			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const isLeaving = Boolean( context.isGroupMember );

			context.membershipBusy = true;
			context.membershipButton = isLeaving
				? messages.leavingGroup
				: messages.joiningGroup;
			context.membershipMessage = '';

			try {
				const request = {
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce,
					},
					method: isLeaving ? 'DELETE' : 'POST',
				};

				if ( ! isLeaving ) {
					request.body = JSON.stringify( {
						visibility: 'public',
					} );
				}

				const response = yield fetch(
					config.restUrl +
						'/groups/' +
						context.groupId +
						'/membership',
					request
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupMembership
					);
				}

				context.membershipId = data.id || 0;
				context.membershipRole = data.role || '';
				context.membershipRoleLabel = getMembershipRoleLabel(
					data.role,
					messages
				);
				context.membershipNotificationPreferences =
					data.notification_preferences ||
					context.membershipNotificationPreferences ||
					{};
				context.membershipStatus = data.status || '';
				context.membershipStatusLabel = getMembershipStatusLabel(
					data.status,
					messages
				);
				context.membershipVisibility =
					data.visibility || context.membershipVisibility || 'public';
				context.isGroupMember = 'active' === data.status;
				context.membershipButton = context.isGroupMember
					? messages.leaveGroup
					: messages.joinGroup;
				context.membershipMessage = context.isGroupMember
					? messages.joinedGroup
					: messages.leftGroup;
			} catch ( error ) {
				context.membershipButton = isLeaving
					? messages.leaveGroup
					: messages.joinGroup;
				context.membershipMessage =
					error.message || messages.unableGroupMembership;
			} finally {
				context.membershipBusy = false;
			}
		},

		/**
		 * Saves the current group membership notification preferences.
		 *
		 * Preferences are stored with the membership relationship, so this action
		 * uses the same current-user membership endpoint as join/leave.
		 *
		 * @param {SubmitEvent} event Notification preference form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		saveMembershipNotifications: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};

			context.membershipNotificationBusy = true;
			context.membershipNotificationButton =
				messages.membershipNotificationsSaving;
			context.membershipNotificationMessage = '';

			try {
				const response = yield fetch(
					config.restUrl +
						'/groups/' +
						context.groupId +
						'/membership',
					{
						body: JSON.stringify( {
							notification_preferences:
								context.membershipNotificationPreferences || {},
							visibility:
								context.membershipVisibility || 'public',
						} ),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message || messages.unableGroupNotifications
					);
				}

				context.membershipNotificationPreferences =
					data.notification_preferences ||
					context.membershipNotificationPreferences ||
					{};
				context.membershipVisibility =
					data.visibility || context.membershipVisibility || 'public';
				context.membershipNotificationMessage =
					messages.membershipNotificationsSaved;
			} catch ( error ) {
				context.membershipNotificationMessage =
					error.message || messages.unableGroupNotifications;
			} finally {
				context.membershipNotificationBusy = false;
				context.membershipNotificationButton =
					messages.saveNotifications;
			}
		} ),

		/**
		 * Creates or updates the logged-in user's RSVP with submitted options.
		 *
		 * This is the explicit options form path. It persists guest count and
		 * visibility along with the active RSVP status.
		 *
		 * @param {SubmitEvent} event RSVP options form submit event.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		saveRsvp: withSyncEvent( function* ( event ) {
			event.preventDefault();

			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const formData = new FormData( event.target );

			if ( context.isEventCanceled ) {
				context.rsvpSaveButton = messages.eventCanceledButton;
				context.rsvpMessage = messages.eventCanceledPublic;
				return;
			}

			context.rsvpBusy = true;
			context.rsvpSaveButton = messages.savingRsvp;
			context.rsvpMessage = '';

			try {
				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId + '/rsvp',
					{
						body: JSON.stringify(
							getRsvpRequestBody( context, formData )
						),
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': config.nonce,
						},
						method: 'POST',
					}
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error( data.message || messages.unableRsvp );
				}

				context.rsvpGuestCount = data.guest_count || 0;
				context.rsvpId = data.id || 0;
				context.rsvpStatus = data.status || '';
				context.rsvpStatusLabel = getRsvpStatusLabel(
					data.status,
					messages
				);
				context.rsvpVisibility = getRelationshipVisibility(
					data.visibility
				);
				context.isEventRsvped = isActiveRsvpStatus( data.status );
				context.rsvpButton = context.isEventRsvped
					? messages.cancelRsvp
					: context.rsvpJoinLabel ||
					  messages.rsvp ||
					  messages.attendEvent;
				context.rsvpSaveButton = messages.saveRsvp;
				context.rsvpMessage =
					getRsvpStatusMessage( data.status, messages ) ||
					messages.rsvpSaved;
			} catch ( error ) {
				context.rsvpSaveButton = messages.saveRsvp;
				context.rsvpMessage = error.message || messages.unableRsvp;
			} finally {
				context.rsvpBusy = false;
			}
		} ),

		/**
		 * Creates or cancels the logged-in user's RSVP for the current event.
		 *
		 * This is the compact RSVP button path. It reuses context-staged options
		 * when creating an RSVP and sends DELETE when the user is already active.
		 *
		 * @return {Generator} Interactivity API async action generator.
		 */
		rsvp: function* () {
			const context = getContext();
			const config = getConfig();

			if ( ! config.isLoggedIn ) {
				window.location.href = config.loginUrl;
				return;
			}

			const messages = config.messages || {};
			const isCanceling = Boolean( context.isEventRsvped );
			const joinLabel =
				context.rsvpJoinLabel || messages.rsvp || messages.attendEvent;

			if ( context.isEventCanceled ) {
				context.rsvpButton = messages.eventCanceledButton;
				context.rsvpMessage = messages.eventCanceledPublic;
				return;
			}

			context.rsvpBusy = true;
			context.rsvpButton = isCanceling
				? messages.cancelingRsvp
				: messages.savingRsvp;
			context.rsvpMessage = '';

			try {
				const request = {
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce,
					},
					method: isCanceling ? 'DELETE' : 'POST',
				};

				if ( ! isCanceling ) {
					request.body = JSON.stringify(
						getRsvpRequestBody( context )
					);
				}

				const response = yield fetch(
					config.restUrl + '/events/' + context.eventId + '/rsvp',
					request
				);
				const data = yield response.json();

				if ( ! response.ok ) {
					throw new Error(
						data.message ||
							( isCanceling
								? messages.unableCancelRsvp
								: messages.unableRsvp )
					);
				}

				context.rsvpId = data.id || 0;
				context.rsvpGuestCount = data.guest_count || 0;
				context.rsvpStatus = data.status || '';
				context.rsvpStatusLabel = getRsvpStatusLabel(
					data.status,
					messages
				);
				context.rsvpVisibility = getRelationshipVisibility(
					data.visibility
				);
				context.isEventRsvped = isActiveRsvpStatus( data.status );
				context.rsvpButton = context.isEventRsvped
					? messages.cancelRsvp
					: joinLabel;
				context.rsvpSaveButton = context.isEventRsvped
					? messages.saveRsvp
					: joinLabel;
				context.rsvpMessage = getRsvpStatusMessage(
					data.status,
					messages
				);
			} catch ( error ) {
				context.rsvpButton = isCanceling
					? messages.cancelRsvp
					: joinLabel;
				context.rsvpMessage =
					error.message ||
					( isCanceling
						? messages.unableCancelRsvp
						: messages.unableRsvp );
			} finally {
				context.rsvpBusy = false;
			}
		},
	},
} );
