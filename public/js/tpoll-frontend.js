/**
 * TPoll Frontend JavaScript
 *
 * @package TPoll
 */

(function() {
    'use strict';

    // Wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        initPolls();
    });

    /**
     * Initialize all polls on page
     */
    function initPolls() {
        const polls = document.querySelectorAll('.tpoll-poll');

        polls.forEach(function(poll) {
            const pollId = poll.dataset.pollId;
            const pollType = poll.dataset.pollType;

            // Initialize based on type
            switch (pollType) {
                case 'rating':
                    initRatingPoll(poll, pollId);
                    break;
                case 'voting':
                    initVotingPoll(poll, pollId);
                    break;
                default:
                    initChoicePoll(poll, pollId);
            }
        });
    }

    /**
     * Initialize choice poll (single/multiple)
     */
    function initChoicePoll(poll, pollId) {
        const form = poll.querySelector('.tpoll-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const answers = [];

            // Get selected answers
            const selectedInputs = form.querySelectorAll('input[name="tpoll_answer"]:checked, input[name="tpoll_answers[]"]:checked');

            if (selectedInputs.length === 0) {
                showMessage(form, tpollData.strings.selectAnswer, 'error');
                return;
            }

            selectedInputs.forEach(function(input) {
                answers.push(input.value);
            });

            // Submit vote
            submitVote(poll, pollId, answers, 1, form);
        });

        // Handle max choices for multiple
        const answersWrap = poll.querySelector('.tpoll-multiple');
        if (answersWrap) {
            const maxChoices = parseInt(answersWrap.dataset.maxChoices) || 0;

            if (maxChoices > 0) {
                const checkboxes = answersWrap.querySelectorAll('input[type="checkbox"]');

                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('change', function() {
                        const checked = answersWrap.querySelectorAll('input[type="checkbox"]:checked');

                        if (checked.length >= maxChoices) {
                            checkboxes.forEach(function(cb) {
                                if (!cb.checked) {
                                    cb.disabled = true;
                                }
                            });
                        } else {
                            checkboxes.forEach(function(cb) {
                                cb.disabled = false;
                            });
                        }
                    });
                });
            }
        }
    }

    /**
     * Initialize rating poll
     */
    function initRatingPoll(poll, pollId) {
        const ratingWrap = poll.querySelector('.tpoll-rating');
        if (!ratingWrap) return;

        const stars = ratingWrap.querySelectorAll('.tpoll-star');
        const valueInput = ratingWrap.querySelector('input[name="tpoll_value"]');
        const answerInput = ratingWrap.querySelector('input[name="tpoll_answer"]');
        const textSpan = ratingWrap.querySelector('.tpoll-rating-text');

        let currentRating = 0;

        // Hover effect
        stars.forEach(function(star, index) {
            star.addEventListener('mouseenter', function() {
                highlightStars(stars, index + 1);
            });

            star.addEventListener('mouseleave', function() {
                highlightStars(stars, currentRating);
            });

            star.addEventListener('click', function() {
                currentRating = parseInt(star.dataset.value);
                valueInput.value = currentRating;
                highlightStars(stars, currentRating);

                if (textSpan) {
                    textSpan.textContent = tpollData.strings.yourRating.replace('%s', currentRating);
                }

                // Auto-submit rating
                submitVote(poll, pollId, [answerInput.value], currentRating);
            });
        });
    }

    /**
     * Initialize voting poll (thumbs)
     */
    function initVotingPoll(poll, pollId) {
        const votingWrap = poll.querySelector('.tpoll-voting');
        if (!votingWrap) return;

        const upBtn = votingWrap.querySelector('.tpoll-vote-up');
        const downBtn = votingWrap.querySelector('.tpoll-vote-down');
        const answerInput = votingWrap.querySelector('input[name="tpoll_answer"]');

        if (upBtn) {
            upBtn.addEventListener('click', function() {
                submitVote(poll, pollId, [answerInput.value], 1);
            });
        }

        if (downBtn) {
            downBtn.addEventListener('click', function() {
                submitVote(poll, pollId, [answerInput.value], -1);
            });
        }
    }

    /**
     * Submit vote via AJAX
     */
    function submitVote(poll, pollId, answers, value, form) {
        const formElement = form || poll.querySelector('.tpoll-form');
        const nonce = formElement ? formElement.dataset.nonce : tpollData.nonce;

        // Disable form
        if (formElement) {
            const submitBtn = formElement.querySelector('.tpoll-submit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('tpoll-loading');
            }
        }

        // Prepare data
        const data = new FormData();
        data.append('action', 'tpoll_vote');
        data.append('nonce', nonce);
        data.append('poll_id', pollId);
        data.append('value', value);

        answers.forEach(function(answer) {
            data.append('answers[]', answer);
        });

        // Send request
        fetch(tpollData.ajaxUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            if (response.success) {
                // Show success message
                showMessage(formElement || poll, response.data.message, 'success');

                // Update results
                const showResults = poll.dataset.showResults;
                if (showResults !== 'never' && response.data.html) {
                    updateResults(poll, response.data.html);
                }

                // Hide form
                if (formElement) {
                    formElement.style.display = 'none';
                }

                // Update voting counts
                if (poll.dataset.pollType === 'voting') {
                    updateVotingCounts(poll, response.data.results);
                }
            } else {
                showMessage(formElement || poll, response.data.message, 'error');

                // Re-enable form
                if (formElement) {
                    const submitBtn = formElement.querySelector('.tpoll-submit');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('tpoll-loading');
                    }
                }
            }
        })
        .catch(function(error) {
            console.error('TPoll Error:', error);
            showMessage(formElement || poll, tpollData.strings.error, 'error');

            // Re-enable form
            if (formElement) {
                const submitBtn = formElement.querySelector('.tpoll-submit');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('tpoll-loading');
                }
            }
        });
    }

    /**
     * Highlight stars up to given index
     */
    function highlightStars(stars, count) {
        stars.forEach(function(star, index) {
            if (index < count) {
                star.classList.add('tpoll-star-active');
            } else {
                star.classList.remove('tpoll-star-active');
            }
        });
    }

    /**
     * Show message
     */
    function showMessage(container, message, type) {
        let messageEl = container.querySelector('.tpoll-message');

        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'tpoll-message';
            container.appendChild(messageEl);
        }

        messageEl.textContent = message;
        messageEl.className = 'tpoll-message tpoll-message-' + type;
        messageEl.style.display = 'block';

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                messageEl.style.display = 'none';
            }, 5000);
        }
    }

    /**
     * Update results display
     */
    function updateResults(poll, html) {
        let resultsContainer = poll.querySelector('.tpoll-results-container');

        if (!resultsContainer) {
            resultsContainer = poll.querySelector('.tpoll-results');
        }

        if (resultsContainer) {
            resultsContainer.innerHTML = html;
            resultsContainer.style.display = 'block';

            // Animate bars
            setTimeout(function() {
                const bars = resultsContainer.querySelectorAll('.tpoll-result-bar');
                bars.forEach(function(bar) {
                    bar.classList.add('tpoll-animated');
                });
            }, 100);
        }
    }

    /**
     * Update voting counts
     */
    function updateVotingCounts(poll, results) {
        if (!results || !results.answers || !results.answers[0]) return;

        const upBtn = poll.querySelector('.tpoll-vote-up');
        const downBtn = poll.querySelector('.tpoll-vote-down');

        if (upBtn) {
            const countEl = upBtn.querySelector('.tpoll-count');
            if (countEl) {
                countEl.textContent = results.total_votes;
            }
            upBtn.disabled = true;
        }

        if (downBtn) {
            downBtn.disabled = true;
        }
    }

})();
