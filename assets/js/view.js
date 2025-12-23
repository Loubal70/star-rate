import { store, getContext } from '@wordpress/interactivity';

store('star-rate', {
	state: {
		get formattedAverage() {
			const state = store('star-rate').state;
			if (state.count === 0) {
				return '—';
			}
			return Math.round(state.average) + '/5';
		},

		get formattedCount() {
			const state = store('star-rate').state;
			const i18n = state.i18n || {};

			if (state.count === 0) {
				return i18n.noVotes || 'No votes yet';
			}
			if (state.count === 1) {
				return i18n.oneVote || '1 vote';
			}

			const template = i18n.votes || '%d votes';
			return template.replace('%d', state.count);
		},
	},

	actions: {
		async vote(event) {
			const context = getContext();
			const state = store('star-rate').state;
			const i18n = state.i18n || {};

			if (state.hasVoted || state.isLoading) {
				return;
			}

			const rating = context.rating;
			state.isLoading = true;

			try {
				const response = await fetch(state.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': state.nonce,
					},
					credentials: 'same-origin',
					body: JSON.stringify({
						post_id: state.postId,
						rating: rating,
						nonce: state.nonce,
					}),
				});

				const data = await response.json();

				if (response.ok && data.success) {
					state.average = data.data.average;
					state.count = data.data.count;
					state.hasVoted = true;
				} else {
					console.error('Star Rate: Vote failed -', data.message);
					alert(data.message || i18n.errorVote || 'Failed to record vote.');
				}
			} catch (error) {
				console.error('Star Rate: Network error -', error);
				alert(i18n.errorNetwork || 'Network error. Please try again.');
			} finally {
				state.isLoading = false;
			}
		},

		hoverStar() {
			const context = getContext();
			const state = store('star-rate').state;

			if (!state.hasVoted) {
				context.hoverRating = context.rating;
			}
		},

		resetHover() {
			const context = getContext();
			const state = store('star-rate').state;

			if (!state.hasVoted) {
				context.hoverRating = 0;
			}
		},
	},

	callbacks: {
		isStarFilled() {
			const context = getContext();
			const state = store('star-rate').state;

			if (state.hasVoted) {
				return context.rating <= Math.round(state.average);
			}

			if (context.hoverRating > 0) {
				return context.rating <= context.hoverRating;
			}

			return context.rating <= Math.round(state.average);
		},
	},
});
