/**
 * TPoll Frontend JavaScript
 *
 * The poll HTML is identical for every visitor (page cache friendly).
 * The visitor state (already voted, fresh nonce, results) is loaded here.
 *
 * @package TPoll
 */

(function() {
    'use strict';

    // Public API for add-ons. Set before the polls are initialised.
    window.tpoll = {
        submitVote: submitVote,
        initChoicePoll: initChoicePoll,
        markVoted: markVoted,
        updateResults: updateResults,
        showMessage: showMessage
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPolls);
    } else {
        initPolls();
    }

    /**
     * Initialize all polls on page
     */
    function initPolls() {
        const polls = document.querySelectorAll('.tpoll-poll');
        if (!polls.length || typeof tpollData === 'undefined') {
            return;
        }

        polls.forEach(function(poll) {
            switch (poll.dataset.pollType) {
                case 'rating':
                    initRatingPoll(poll);
                    break;
                case 'voting':
                    initVotingPoll(poll);
                    break;
                case 'reactions':
                    initReactionsPoll(poll);
                    break;
                case 'single':
                case 'multiple':
                    initChoicePoll(poll);
                    break;
                default:
                    // Add-on types: answer lists work like choice polls unless the add-on
                    // marks its markup with data-tpoll-custom and handles voting itself.
                    if (poll.dataset.choice === '1' && !poll.querySelector('[data-tpoll-custom]')) {
                        initChoicePoll(poll);
                    }
            }

            // Add-ons initialise their own types on this event.
            poll.dispatchEvent(new CustomEvent('tpoll:init', { bubbles: true, detail: { poll: poll } }));
        });

        loadState(polls);
    }

    /**
     * Load visitor state for all polls in one request.
     */
    function loadState(polls) {
        const ids = [];
        polls.forEach(function(poll) {
            ids.push(stateKey(poll));
        });

        const url = tpollData.ajaxUrl + '?action=tpoll_state&poll_ids=' + encodeURIComponent(ids.join(','));

        fetch(url, { credentials: 'same-origin' })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (!response || !response.success || !response.data) {
                    return;
                }
                polls.forEach(function(poll) {
                    const state = response.data[stateKey(poll)];
                    if (state) {
                        applyState(poll, state);
                    }
                });
            })
            .catch(function() {
                // Without state the form still works with the nonce from the page.
            });
    }

    /**
     * "id" or "id:post" for polls that are counted per post.
     */
    function stateKey(poll) {
        return poll.dataset.context ? poll.dataset.pollId + ':' + poll.dataset.context : poll.dataset.pollId;
    }

    /**
     * Initialize reactions: a click (or Enter/Space) on a reaction votes.
     */
    function initReactionsPoll(poll) {
        poll.querySelectorAll('.tpoll-reactions .tpoll-reaction[data-answer]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                btn.setAttribute('aria-pressed', 'true');
                submitVote(poll, [btn.dataset.answer], 1).then(function(ok) {
                    if (!ok) {
                        btn.removeAttribute('aria-pressed');
                    }
                });
            });
        });
    }

    /**
     * Write the counts into the reaction buttons.
     */
    function updateReactionCounts(poll, results) {
        if (!results || !results.answers) return;
        results.answers.forEach(function(answer) {
            const count = poll.querySelector('.tpoll-reaction[data-answer="' + answer.id + '"] .tpoll-count');
            if (count) {
                count.textContent = answer.votes;
            }
        });
    }

    /**
     * Apply the visitor state to one poll.
     */
    function applyState(poll, state) {
        if (state.nonce) {
            poll.dataset.nonce = state.nonce;
        }

        if (state.hasVoted && !state.canRevote) {
            markVoted(poll, null);
        }

        if (state.html) {
            if (poll.dataset.pollType === 'reactions' && !(state.hasVoted && !state.canRevote)) {
                updateReactionCounts(poll, state.results);
            } else {
                updateResults(poll, state.html);
            }
        }

        if (state.results && poll.dataset.pollType === 'voting') {
            updateVotingCounts(poll, state.results);
        }
    }

    /**
     * Initialize choice poll (single/multiple)
     */
    function initChoicePoll(poll) {
        const form = poll.querySelector('.tpoll-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const selectedInputs = form.querySelectorAll('input[name="tpoll_answer"]:checked, input[name="tpoll_answers[]"]:checked');

            if (selectedInputs.length === 0) {
                showMessage(form, tpollData.strings.selectAnswer, 'error');
                return;
            }

            const answers = [];
            selectedInputs.forEach(function(input) {
                answers.push(input.value);
            });

            submitVote(poll, answers, 1);
        });

        // Handle max choices for multiple
        const answersWrap = poll.querySelector('.tpoll-multiple');
        if (answersWrap) {
            const maxChoices = parseInt(answersWrap.dataset.maxChoices, 10) || 0;

            if (maxChoices > 0) {
                const checkboxes = answersWrap.querySelectorAll('input[type="checkbox"]');

                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('change', function() {
                        const checked = answersWrap.querySelectorAll('input[type="checkbox"]:checked');
                        const full = checked.length >= maxChoices;

                        checkboxes.forEach(function(cb) {
                            cb.disabled = full && !cb.checked;
                        });
                    });
                });
            }
        }
    }

    /**
     * Initialize rating poll
     */
    function initRatingPoll(poll) {
        const ratingWrap = poll.querySelector('.tpoll-rating');
        if (!ratingWrap) return;

        const stars = ratingWrap.querySelectorAll('.tpoll-star');
        const answerInput = ratingWrap.querySelector('input[name="tpoll_answer"]');
        const textSpan = ratingWrap.querySelector('.tpoll-rating-text');

        let currentRating = 0;

        stars.forEach(function(star, index) {
            const preview = function() { highlightStars(stars, index + 1); };
            const reset = function() { highlightStars(stars, currentRating); };

            star.addEventListener('mouseenter', preview);
            star.addEventListener('focus', preview);
            star.addEventListener('mouseleave', reset);
            star.addEventListener('blur', reset);

            star.addEventListener('click', function() {
                currentRating = parseInt(star.dataset.value, 10);
                highlightStars(stars, currentRating);

                if (textSpan) {
                    textSpan.textContent = tpollData.strings.yourRating.replace('%s', currentRating);
                }

                submitVote(poll, [answerInput.value], currentRating);
            });
        });
    }

    /**
     * Initialize voting poll (thumbs)
     */
    function initVotingPoll(poll) {
        const votingWrap = poll.querySelector('.tpoll-voting');
        if (!votingWrap) return;

        const answerInput = votingWrap.querySelector('input[name="tpoll_answer"]');

        votingWrap.querySelectorAll('.tpoll-vote-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                submitVote(poll, [answerInput.value], parseInt(btn.dataset.value, 10));
            });
        });
    }

    /**
     * Enable or disable all vote controls of a poll.
     */
    function setBusy(poll, busy) {
        poll.querySelectorAll('.tpoll-submit, .tpoll-star, .tpoll-vote-btn, .tpoll-reaction[data-answer]').forEach(function(el) {
            el.disabled = busy;
            el.classList.toggle('tpoll-loading', busy && el.classList.contains('tpoll-submit'));
        });
    }

    /**
     * Submit vote via AJAX.
     *
     * @return {Promise<boolean>} Resolves true when the vote was stored.
     */
    function submitVote(poll, answers, value) {
        const form = poll.querySelector('.tpoll-form');
        const nonce = poll.dataset.nonce || (form ? form.dataset.nonce : '');

        setBusy(poll, true);

        const data = new FormData();
        data.append('action', 'tpoll_vote');
        data.append('nonce', nonce);
        data.append('poll_id', poll.dataset.pollId);
        data.append('value', value);
        data.append('context', poll.dataset.context || '0');

        const hp = form ? form.querySelector('input[name="tpoll_hp"]') : null;
        data.append('tpoll_hp', hp ? hp.value : '');

        answers.forEach(function(answer) {
            data.append('answers[]', answer);
        });

        // Extra fields of add-ons (e.g. newsletter sign-up) are sent with the vote.
        if (form) {
            form.querySelectorAll('[name^="tpoll_extra["]').forEach(function(field) {
                if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                    return;
                }
                data.append(field.name, field.value);
            });
        }

        return fetch(tpollData.ajaxUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            const payload = response && response.data ? response.data : {};

            if (response && response.success) {
                markVoted(poll, payload.message || tpollData.strings.voted);
                poll.dispatchEvent(new CustomEvent('tpoll:voted', { bubbles: true, detail: { poll: poll, data: payload } }));

                if (payload.html) {
                    updateResults(poll, payload.html);
                }
                if (payload.results && poll.dataset.pollType === 'voting') {
                    updateVotingCounts(poll, payload.results);
                }
                return true;
            }
            showMessage(form || poll, payload.message || tpollData.strings.error, 'error');
            setBusy(poll, false);
            poll.dispatchEvent(new CustomEvent('tpoll:votefailed', { bubbles: true, detail: { poll: poll, data: payload } }));
            return false;
        })
        .catch(function() {
            showMessage(form || poll, tpollData.strings.error, 'error');
            setBusy(poll, false);
            poll.dispatchEvent(new CustomEvent('tpoll:votefailed', { bubbles: true, detail: { poll: poll, data: {} } }));
            return false;
        });
    }

    /**
     * Hide the form and show the "voted" note.
     *
     * @param {Element}     poll    Poll element.
     * @param {string|null} message Text to show, null keeps the configured text.
     */
    function markVoted(poll, message) {
        const form = poll.querySelector('.tpoll-form');
        if (form) {
            form.hidden = true;
        }

        const voted = poll.querySelector('.tpoll-voted');
        if (voted) {
            if (message) {
                voted.textContent = message;
            }
            voted.classList.remove('tpoll-hidden');
            voted.setAttribute('role', 'status');
        }
    }

    /**
     * Highlight stars up to given index
     */
    function highlightStars(stars, count) {
        stars.forEach(function(star, index) {
            star.classList.toggle('tpoll-star-active', index < count);
        });
    }

    /**
     * Show message
     */
    function showMessage(container, message, type) {
        let messageEl = container.querySelector('.tpoll-message');

        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.setAttribute('role', 'status');
            container.appendChild(messageEl);
        }

        messageEl.textContent = message;
        messageEl.className = 'tpoll-message tpoll-message-' + type;
    }

    /**
     * Replace the results (never adds a second block).
     */
    function updateResults(poll, html) {
        const container = poll.querySelector('.tpoll-results-container');
        if (!container) return;

        container.innerHTML = html;
        container.hidden = false;
    }

    /**
     * Update thumbs counts
     */
    function updateVotingCounts(poll, results) {
        if (!results || typeof results.up === 'undefined') return;

        const up = poll.querySelector('.tpoll-count-up');
        const down = poll.querySelector('.tpoll-count-down');

        if (up) {
            up.textContent = results.up;
        }
        if (down) {
            down.textContent = results.down;
        }
    }

})();
