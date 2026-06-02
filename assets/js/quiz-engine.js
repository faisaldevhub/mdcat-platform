(function () {
    'use strict';

    const STORAGE_PREFIX = 'mdcat_quiz_state_';
    const LOW_TIME_THRESHOLD = 60;
    const FEEDBACK_DELAY = 700;
    const REQUEST_TIMEOUT = 20000;

    class MDCATQuizController {
        constructor(root) {
            this.root = root;
            this.collectionId = this.parseInt(root.dataset.collectionId);
            this.storageKey = `${STORAGE_PREFIX}${this.collectionId || 'unknown'}`;
            this.state = this.getDefaultState();
            this.timer = null;
            this.activeRequest = null;

            this.elements = this.getElements();
            this.bindEvents();
            this.validateInitialState();
            this.renderInitialState();
        }

        getDefaultState() {
            return {
                attemptId: 0,
                questions: [],
                currentIndex: 0,
                totalSeconds: 0,
                remainingSeconds: 0,
                isBusy: false,
                isSubmitting: false,
                isCompleting: false,
                isCompleted: false,
                bookmarks: {}
            };
        }

        getElements() {
            return {
                startWrap: this.root.querySelector('.mdcat-quiz__start'),
                startButton: this.root.querySelector('.mdcat-quiz__start-button'),
                loading: this.root.querySelector('.mdcat-quiz__loading'),
                question: this.root.querySelector('.mdcat-quiz__question'),
                result: this.root.querySelector('.mdcat-quiz__result'),
                message: this.root.querySelector('.mdcat-quiz__message'),
                timer: this.root.querySelector('.mdcat-quiz__timer'),
                progress: this.root.querySelector('.mdcat-quiz__progress')
            };
        }

        bindEvents() {
            if (this.elements.startButton) {
                this.elements.startButton.addEventListener('click', () => this.startQuiz());
            }
        }

        validateInitialState() {
            if (!window.MDCATQuiz || !MDCATQuiz.is_logged_in) {
                this.setMessage(this.t('login_required'), 'error');
                this.disableStart();
                return;
            }

            if (!this.collectionId) {
                this.setMessage(this.t('missing_collection'), 'error');
                this.disableStart();
            }
        }

        renderInitialState() {
            this.updateTimerDisplay();
            this.updateProgress();
            this.root.classList.add('mdcat-quiz--ready');
        }

        async startQuiz() {
            if (this.state.isBusy || this.state.isCompleted || !this.collectionId) {
                return;
            }

            this.setBusy(true, this.t('loading'));
            this.clearMessage();

            const response = await this.request('mdcat_start_quiz', {
                collection_id: this.collectionId
            });

            if (!this.isValidResponse(response)) {
                this.failRequest(response);
                return;
            }

            this.state.attemptId = this.parseInt(response.data.attempt_id);
            this.state.totalSeconds = this.parseInt(response.data.total_time) * 60;
            this.state.remainingSeconds = this.state.totalSeconds;
            this.persistState();

            await this.loadQuestions();
        }

        async loadQuestions() {
            const response = await this.request('mdcat_get_questions', {
                attempt_id: this.state.attemptId
            });

            if (!this.isValidResponse(response) || !Array.isArray(response.data.questions)) {
                this.failRequest(response);
                return;
            }

            this.state.questions = response.data.questions;
            this.state.currentIndex = 0;

            if (!this.state.questions.length) {
                this.failRequest({
                    data: {
                        message: this.t('request_failed')
                    }
                });
                return;
            }

            this.setBusy(false);
            this.hide(this.elements.startWrap);
            this.hide(this.elements.result);
            this.show(this.elements.question);
            await this.loadBookmarkStates();
            this.startTimer();
            this.persistState();
            this.renderQuestion();
        }

        async loadBookmarkStates() {
            const response = await this.request('mdcat_get_bookmarks', {});

            if (!this.isValidResponse(response) || !response.data || !Array.isArray(response.data.questions)) {
                return;
            }

            const availableQuestionIds = this.state.questions.reduce((ids, question) => {
                ids[this.parseInt(question.id)] = true;
                return ids;
            }, {});

            response.data.questions.forEach((question) => {
                const questionId = this.parseInt(question.question_id);

                if (availableQuestionIds[questionId]) {
                    this.state.bookmarks[questionId] = true;
                }
            });
        }

        renderQuestion() {
            const question = this.getCurrentQuestion();

            if (!question) {
                this.completeQuiz();
                return;
            }

            this.clearMessage();
            this.updateProgress();
            this.elements.question.innerHTML = '';

            const card = document.createElement('div');
            card.className = 'mdcat-quiz__question-card';

            const actions = document.createElement('div');
            actions.className = 'mdcat-quiz__question-actions';
            actions.appendChild(this.createBookmarkButton(question.id));

            const title = document.createElement('h3');
            title.className = 'mdcat-quiz__question-text';
            title.textContent = question.question || '';

            const options = document.createElement('div');
            options.className = 'mdcat-quiz__options';

            Object.keys(question.options || {}).forEach((key) => {
                options.appendChild(this.createOptionButton(question.id, key, question.options[key]));
            });

            card.appendChild(actions);
            card.appendChild(title);
            card.appendChild(options);
            this.elements.question.appendChild(card);
            this.persistState();
        }

        createBookmarkButton(questionId) {
            const button = document.createElement('button');
            const isBookmarked = Boolean(this.state.bookmarks[questionId]);

            button.type = 'button';
            button.className = `mdcat-quiz__bookmark${isBookmarked ? ' is-active' : ''}`;
            button.dataset.questionId = questionId;
            button.textContent = isBookmarked ? this.t('bookmarked') : this.t('bookmark');
            button.addEventListener('click', () => this.toggleBookmark(questionId, button));

            return button;
        }

        async toggleBookmark(questionId, button) {
            if (!questionId || button.disabled) {
                return;
            }

            const previousState = Boolean(this.state.bookmarks[questionId]);
            const optimisticState = !previousState;

            button.disabled = true;
            this.setBookmarkButtonState(button, optimisticState);
            this.state.bookmarks[questionId] = optimisticState;

            const response = await this.request('mdcat_toggle_bookmark', {
                question_id: questionId
            });

            if (!this.isValidResponse(response)) {
                this.state.bookmarks[questionId] = previousState;
                this.setBookmarkButtonState(button, previousState);
                button.disabled = false;
                this.handleError(response);
                return;
            }

            this.state.bookmarks[questionId] = Boolean(response.data.is_bookmarked);
            this.setBookmarkButtonState(button, this.state.bookmarks[questionId]);
            button.disabled = false;
        }

        setBookmarkButtonState(button, isBookmarked) {
            button.classList.toggle('is-active', isBookmarked);
            button.textContent = isBookmarked ? this.t('bookmarked') : this.t('bookmark');
        }

        createOptionButton(questionId, optionKey, optionText) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mdcat-quiz__option';
            button.dataset.option = optionKey;
            button.textContent = `${optionKey.toUpperCase()}. ${optionText || ''}`;
            button.addEventListener('click', () => this.submitAnswer(questionId, optionKey, button));

            return button;
        }

        async submitAnswer(questionId, selectedOption, selectedButton) {
            if (this.state.isBusy || this.state.isSubmitting || this.state.isCompleting || this.state.isCompleted) {
                return;
            }

            this.state.isSubmitting = true;
            this.setBusy(true, '');
            this.disableOptions();

            const response = await this.request('mdcat_save_answer', {
                attempt_id: this.state.attemptId,
                question_id: questionId,
                selected_option: selectedOption,
                question_index: this.state.currentIndex
            });

            if (!this.isValidResponse(response)) {
                this.state.isSubmitting = false;
                this.setBusy(false);
                this.enableOptions();
                this.failRequest(response);
                return;
            }

            this.renderFeedback(selectedButton, Boolean(response.data.is_correct));

            window.setTimeout(() => {
                this.state.isSubmitting = false;
                this.setBusy(false);
                this.state.currentIndex += 1;
                this.persistState();

                if (this.state.currentIndex >= this.state.questions.length) {
                    this.completeQuiz();
                    return;
                }

                this.renderQuestion();
            }, FEEDBACK_DELAY);
        }

        async completeQuiz() {
            if (!this.state.attemptId || this.state.isCompleting || this.state.isCompleted) {
                return;
            }

            this.state.isCompleting = true;
            this.stopTimer();
            this.setBusy(true, this.t('loading'));

            const response = await this.request('mdcat_complete_quiz', {
                attempt_id: this.state.attemptId
            });

            this.state.isCompleting = false;
            this.setBusy(false);

            if (!this.isValidResponse(response)) {
                this.failRequest(response);
                return;
            }

            this.state.isCompleted = true;
            this.persistState();
            await this.loadResult(response.data);
        }

        async loadResult(fallbackResult) {
            const response = await this.request('mdcat_get_result', {
                attempt_id: this.state.attemptId
            });

            if (!this.isValidResponse(response)) {
                this.renderResult(fallbackResult || {});
                return;
            }

            this.renderResult(response.data);
        }

        renderResult(result) {
            this.stopTimer();
            this.hide(this.elements.question);
            this.show(this.elements.result);
            this.elements.progress.textContent = '';
            this.elements.timer.textContent = '';
            this.root.classList.add('mdcat-quiz--completed');

            const score = this.parseNumber(result.score);
            const total = this.parseInt(result.total_questions);
            const correct = this.parseInt(result.correct_answers);
            const wrong = this.parseInt(result.wrong_answers);

            this.elements.result.innerHTML = `
                <div class="mdcat-quiz__result-card">
                    <h3>${this.escapeHtml(this.t('quiz_complete'))}</h3>
                    <div class="mdcat-quiz__score">${this.escapeHtml(score)} / ${this.escapeHtml(total)}</div>
                    <div class="mdcat-quiz__summary">
                        <span>${this.escapeHtml(this.t('correct'))}: ${this.escapeHtml(correct)}</span>
                        <span>${this.escapeHtml(this.t('wrong'))}: ${this.escapeHtml(wrong)}</span>
                    </div>
                    <button type="button" class="mdcat-quiz__review-button">${this.escapeHtml(this.t('review_answers'))}</button>
                </div>
            `;

            const reviewButton = this.elements.result.querySelector('.mdcat-quiz__review-button');

            if (reviewButton) {
                reviewButton.addEventListener('click', () => this.fetchAttemptReview());
            }

            this.clearPersistedState();
        }

        async fetchAttemptReview() {
            if (!this.state.attemptId || this.state.isBusy) {
                return;
            }

            this.setBusy(true, this.t('loading'));

            const response = await this.request('mdcat_get_attempt_review', {
                attempt_id: this.state.attemptId
            });

            this.setBusy(false);

            if (!this.isValidResponse(response)) {
                this.failRequest(response);
                return;
            }

            this.renderReviewScreen(response.data);
        }

        renderReviewScreen(review) {
            const questions = Array.isArray(review.questions) ? review.questions : [];
            const score = review.score || {};
            const collection = review.collection || {};

            this.hide(this.elements.question);
            this.show(this.elements.result);

            const wrapper = document.createElement('div');
            wrapper.className = 'mdcat-review';

            const heading = document.createElement('div');
            heading.className = 'mdcat-review__header';
            heading.innerHTML = `
                <h3>${this.escapeHtml(this.t('review_title'))}</h3>
                <p>${this.escapeHtml(collection.subject_title || '')} ${collection.chapter_title ? ' / ' + this.escapeHtml(collection.chapter_title) : ''} ${collection.collection_title ? ' / ' + this.escapeHtml(collection.collection_title) : ''}</p>
                <div class="mdcat-review__score">${this.escapeHtml(score.score || 0)} / ${this.escapeHtml(score.total_questions || 0)}</div>
            `;

            wrapper.appendChild(heading);

            questions.forEach((question, index) => {
                wrapper.appendChild(this.createReviewQuestion(question, index));
            });

            this.elements.result.innerHTML = '';
            this.elements.result.appendChild(wrapper);
        }

        createReviewQuestion(question, index) {
            const card = document.createElement('article');
            card.className = `mdcat-review__question ${question.is_correct ? 'is-correct' : 'is-wrong'}`;

            const title = document.createElement('h4');
            title.className = 'mdcat-review__question-title';
            title.textContent = `${index + 1}. ${question.question || ''}`;
            card.appendChild(title);

            const options = document.createElement('div');
            options.className = 'mdcat-review__options';

            Object.keys(question.options || {}).forEach((key) => {
                options.appendChild(this.createReviewOption(question, key));
            });

            card.appendChild(options);

            const meta = document.createElement('div');
            meta.className = 'mdcat-review__meta';
            meta.innerHTML = `
                <span>${this.escapeHtml(this.t('your_answer'))}: ${this.escapeHtml((question.selected_option || '').toUpperCase() || '-')}</span>
                <span>${this.escapeHtml(this.t('correct_answer'))}: ${this.escapeHtml((question.correct_option || '').toUpperCase())}</span>
            `;
            card.appendChild(meta);

            const explanation = document.createElement('div');
            explanation.className = 'mdcat-review__explanation';
            explanation.innerHTML = `<strong>${this.escapeHtml(this.t('explanation'))}:</strong> ${this.escapeHtml(question.explanation || '')}`;
            card.appendChild(explanation);

            return card;
        }

        createReviewOption(question, key) {
            const option = document.createElement('div');
            const selected = question.selected_option === key;
            const correct = question.correct_option === key;
            const classes = ['mdcat-review__option'];

            if (selected && question.is_correct) {
                classes.push('is-selected-correct');
            } else if (selected) {
                classes.push('is-selected-wrong');
            }

            if (correct) {
                classes.push('is-correct-answer');
            }

            option.className = classes.join(' ');
            option.textContent = `${key.toUpperCase()}. ${(question.options && question.options[key]) || ''}`;

            return option;
        }

        startTimer() {
            this.stopTimer();
            this.updateTimerDisplay();

            this.timer = window.setInterval(() => {
                if (this.state.isCompleting || this.state.isCompleted) {
                    this.stopTimer();
                    return;
                }

                this.state.remainingSeconds = Math.max(0, this.state.remainingSeconds - 1);
                this.updateTimerDisplay();
                this.persistState();

                if (this.state.remainingSeconds <= 0) {
                    this.completeQuiz();
                }
            }, 1000);
        }

        stopTimer() {
            if (this.timer) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        }

        updateTimerDisplay() {
            if (!this.elements.timer) {
                return;
            }

            const seconds = Math.max(0, this.state.remainingSeconds);
            const minutes = Math.floor(seconds / 60);
            const remaining = seconds % 60;

            this.elements.timer.textContent = seconds ? `${minutes}:${String(remaining).padStart(2, '0')}` : '';
            this.elements.timer.classList.toggle('mdcat-quiz__timer--warning', seconds > 0 && seconds <= LOW_TIME_THRESHOLD);
        }

        updateProgress() {
            if (!this.elements.progress || !this.state.questions.length) {
                if (this.elements.progress) {
                    this.elements.progress.textContent = '';
                }
                return;
            }

            this.elements.progress.textContent = this.format(
                this.t('question_of'),
                this.state.currentIndex + 1,
                this.state.questions.length
            );
        }

        renderFeedback(button, isCorrect) {
            button.classList.add(isCorrect ? 'is-correct' : 'is-wrong');
            this.setMessage(isCorrect ? this.t('correct') : this.t('wrong'), isCorrect ? 'success' : 'error');
        }

        disableOptions() {
            this.root.querySelectorAll('.mdcat-quiz__option').forEach((button) => {
                button.disabled = true;
            });
        }

        enableOptions() {
            this.root.querySelectorAll('.mdcat-quiz__option').forEach((button) => {
                button.disabled = false;
            });
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const controller = window.AbortController ? new AbortController() : null;
            const timeout = controller ? window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT) : null;
            const formData = this.buildFormData(action, payload);

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                    signal: controller ? controller.signal : undefined
                });

                if (timeout) {
                    window.clearTimeout(timeout);
                }

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                const data = await response.json();
                return data && typeof data === 'object' ? data : this.errorResponse(this.t('request_failed'));
            } catch (error) {
                if (timeout) {
                    window.clearTimeout(timeout);
                }

                return this.errorResponse(this.t('request_failed'));
            }
        }

        buildFormData(action, payload) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            return formData;
        }

        isValidResponse(response) {
            return Boolean(response && response.success && response.data);
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        failRequest(response) {
            this.setBusy(false);
            this.handleError(response);
        }

        handleError(response) {
            const message = response && response.data && response.data.message
                ? response.data.message
                : this.t('request_failed');

            this.setMessage(message, 'error');
            this.root.classList.add('mdcat-quiz--error');
        }

        setBusy(isBusy, loadingText) {
            this.state.isBusy = isBusy;
            this.root.classList.toggle('mdcat-quiz--busy', isBusy);

            if (this.elements.startButton) {
                this.elements.startButton.disabled = isBusy;
            }

            if (this.elements.loading && loadingText !== undefined) {
                this.elements.loading.textContent = loadingText || '';
            }

            if (isBusy && loadingText) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        disableStart() {
            if (this.elements.startButton) {
                this.elements.startButton.disabled = true;
            }
        }

        setMessage(message, type) {
            if (!this.elements.message) {
                return;
            }

            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.elements.message.hidden = !message;
        }

        clearMessage() {
            this.setMessage('', '');
            this.root.classList.remove('mdcat-quiz--error');
        }

        getCurrentQuestion() {
            return this.state.questions[this.state.currentIndex] || null;
        }

        persistState() {
            try {
                window.localStorage.setItem(
                    this.storageKey,
                    JSON.stringify({
                        attemptId: this.state.attemptId,
                        collectionId: this.collectionId,
                        currentIndex: this.state.currentIndex,
                        remainingSeconds: this.state.remainingSeconds,
                        totalSeconds: this.state.totalSeconds,
                        updatedAt: Date.now()
                    })
                );
            } catch (error) {
                // Storage can fail in private browsing or restricted contexts.
            }
        }

        clearPersistedState() {
            try {
                window.localStorage.removeItem(this.storageKey);
            } catch (error) {
                // Storage cleanup is best-effort only.
            }
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        parseInt(value) {
            const parsed = Number.parseInt(value, 10);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        parseNumber(value) {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        format(template, ...values) {
            return String(template).replace(/%(\d+)\$d/g, (match, index) => {
                const value = values[Number.parseInt(index, 10) - 1];
                return value !== undefined ? value : match;
            });
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }
    }

    class MDCATAttemptHistoryController {
        constructor(root) {
            this.root = root;
            this.page = 1;
            this.perPage = this.parseInt(root.dataset.perPage) || 20;
            this.elements = {
                loading: root.querySelector('.mdcat-attempt-history__loading'),
                message: root.querySelector('.mdcat-attempt-history__message'),
                tableWrap: root.querySelector('.mdcat-attempt-history__table-wrap'),
                tableBody: root.querySelector('tbody')
            };

            this.fetchAttemptHistory();
        }

        async fetchAttemptHistory() {
            this.setLoading(true);
            this.hideMessage();

            const response = await this.request('mdcat_get_attempt_history', {
                page: this.page,
                per_page: this.perPage
            });

            this.setLoading(false);

            if (!response || !response.success || !response.data) {
                this.showMessage(this.getErrorMessage(response), 'error');
                return;
            }

            this.renderAttemptHistory(response.data.items || []);
        }

        renderAttemptHistory(items) {
            if (!Array.isArray(items) || !items.length) {
                this.hide(this.elements.tableWrap);
                this.showMessage(this.t('history_empty'), 'empty');
                return;
            }

            this.elements.tableBody.innerHTML = '';

            items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${this.escapeHtml(item.subject_title || '')}</td>
                    <td>${this.escapeHtml(item.chapter_title || '')}</td>
                    <td>${this.escapeHtml(item.collection_title || '')}</td>
                    <td>${this.escapeHtml(item.score || 0)} / ${this.escapeHtml(item.total_questions || 0)}</td>
                    <td>${this.escapeHtml(item.correct_answers || 0)}</td>
                    <td>${this.escapeHtml(item.wrong_answers || 0)}</td>
                    <td>${this.escapeHtml(item.completed_at || '')}</td>
                `;
                this.elements.tableBody.appendChild(row);
            });

            this.show(this.elements.tableWrap);
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                return await response.json();
            } catch (error) {
                return this.errorResponse(this.t('request_failed'));
            }
        }

        setLoading(isLoading) {
            if (isLoading) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        showMessage(message, type) {
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.show(this.elements.message);
        }

        hideMessage() {
            this.elements.message.textContent = '';
            this.hide(this.elements.message);
        }

        getErrorMessage(response) {
            return response && response.data && response.data.message ? response.data.message : this.t('request_failed');
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        parseInt(value) {
            const parsed = Number.parseInt(value, 10);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }
    }

    class MDCATRevisionListController {
        constructor(root) {
            this.root = root;
            this.type = root.dataset.revisionType || 'bookmarks';
            this.elements = {
                loading: root.querySelector('.mdcat-revision-list__loading'),
                message: root.querySelector('.mdcat-revision-list__message'),
                items: root.querySelector('.mdcat-revision-list__items')
            };

            if (this.type === 'wrong') {
                this.fetchWrongQuestions();
            } else {
                this.fetchBookmarks();
            }
        }

        async fetchBookmarks() {
            const response = await this.fetchRevisionDataset('mdcat_get_bookmarks');
            this.renderRevisionQuestions(response, 'bookmarks_empty');
        }

        async fetchWrongQuestions() {
            const response = await this.fetchRevisionDataset('mdcat_get_wrong_questions');
            this.renderRevisionQuestions(response, 'wrong_empty');
        }

        async fetchRevisionDataset(action) {
            this.setLoading(true);
            this.hideMessage();

            const response = await this.request(action, {});

            this.setLoading(false);

            return response;
        }

        renderRevisionQuestions(response, emptyKey) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.questions)) {
                this.showMessage(this.getErrorMessage(response), 'error');
                return;
            }

            const questions = response.data.questions;

            if (!questions.length) {
                this.hide(this.elements.items);
                this.showMessage(this.t(emptyKey), 'empty');
                return;
            }

            this.elements.items.innerHTML = '';

            questions.forEach((question) => {
                this.elements.items.appendChild(this.createRevisionCard(question));
            });

            this.show(this.elements.items);
        }

        createRevisionCard(question) {
            const card = document.createElement('article');
            card.className = 'mdcat-revision-card';

            const title = document.createElement('h3');
            title.className = 'mdcat-revision-card__title';
            title.textContent = question.question || '';
            card.appendChild(title);

            const meta = document.createElement('div');
            meta.className = 'mdcat-revision-card__meta';
            meta.textContent = [question.subject_title, question.chapter_title, question.collection_title].filter(Boolean).join(' / ');
            card.appendChild(meta);

            const options = document.createElement('div');
            options.className = 'mdcat-revision-card__options';

            Object.keys(question.options || {}).forEach((key) => {
                const option = document.createElement('div');
                option.className = `mdcat-revision-card__option${question.correct_option === key ? ' is-correct-answer' : ''}`;
                option.textContent = `${key.toUpperCase()}. ${question.options[key] || ''}`;
                options.appendChild(option);
            });

            card.appendChild(options);

            const explanation = document.createElement('div');
            explanation.className = 'mdcat-revision-card__explanation';
            explanation.innerHTML = `<strong>${this.escapeHtml(this.t('explanation'))}:</strong> ${this.escapeHtml(question.explanation || '')}`;
            card.appendChild(explanation);

            if (question.wrong_count) {
                const wrongCount = document.createElement('div');
                wrongCount.className = 'mdcat-revision-card__wrong-count';
                wrongCount.textContent = `${this.t('wrong')}: ${question.wrong_count}`;
                card.appendChild(wrongCount);
            }

            return card;
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                return await response.json();
            } catch (error) {
                return this.errorResponse(this.t('request_failed'));
            }
        }

        setLoading(isLoading) {
            if (isLoading) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        showMessage(message, type) {
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.show(this.elements.message);
        }

        hideMessage() {
            this.elements.message.textContent = '';
            this.hide(this.elements.message);
        }

        getErrorMessage(response) {
            return response && response.data && response.data.message ? response.data.message : this.t('request_failed');
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }
    }

    class MDCATPerformanceController {
        constructor(root) {
            this.root = root;
            this.elements = {
                loading: root.querySelector('.mdcat-performance__loading'),
                message: root.querySelector('.mdcat-performance__message'),
                content: root.querySelector('.mdcat-performance__content'),
                subjectBody: root.querySelector('.mdcat-performance__subject-table tbody'),
                chapterBody: root.querySelector('.mdcat-performance__chapter-table tbody')
            };

            this.fetchPerformanceAnalytics();
        }

        async fetchPerformanceAnalytics() {
            this.setLoading(true);
            this.hideMessage();

            const response = await this.request('mdcat_get_performance_analytics', {});

            this.setLoading(false);

            if (!response || !response.success || !response.data) {
                this.showMessage(this.getErrorMessage(response), 'error');
                return;
            }

            this.renderPerformanceAnalytics(response.data);
        }

        renderPerformanceAnalytics(data) {
            const subjects = Array.isArray(data.subject_performance) ? data.subject_performance : [];
            const chapters = Array.isArray(data.chapter_performance) ? data.chapter_performance : [];

            if (!subjects.length && !chapters.length) {
                this.hide(this.elements.content);
                this.showMessage(this.t('analytics_empty'), 'empty');
                return;
            }

            this.renderSubjectPerformance(subjects);
            this.renderChapterPerformance(chapters);
            this.show(this.elements.content);
        }

        renderSubjectPerformance(items) {
            this.elements.subjectBody.innerHTML = '';

            items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${this.escapeHtml(item.subject_title || '')}</td>
                    <td>${this.escapeHtml(item.accuracy_percentage || 0)}%</td>
                    <td>${this.escapeHtml(item.correct_answers || 0)}</td>
                    <td>${this.escapeHtml(item.wrong_answers || 0)}</td>
                    <td>${this.escapeHtml(item.total_questions || 0)}</td>
                `;
                this.elements.subjectBody.appendChild(row);
            });
        }

        renderChapterPerformance(items) {
            this.elements.chapterBody.innerHTML = '';

            items.forEach((item) => {
                const label = item.performance_label || 'Weak';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${this.escapeHtml(item.subject_title || '')}</td>
                    <td>${this.escapeHtml(item.chapter_title || '')}</td>
                    <td>${this.escapeHtml(item.accuracy_percentage || 0)}%</td>
                    <td><span class="mdcat-performance__label mdcat-performance__label--${this.escapeAttribute(label.toLowerCase())}">${this.escapeHtml(label)}</span></td>
                `;
                this.elements.chapterBody.appendChild(row);
            });
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                return await response.json();
            } catch (error) {
                return this.errorResponse(this.t('request_failed'));
            }
        }

        setLoading(isLoading) {
            if (isLoading) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        showMessage(message, type) {
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.show(this.elements.message);
        }

        hideMessage() {
            this.elements.message.textContent = '';
            this.hide(this.elements.message);
        }

        getErrorMessage(response) {
            return response && response.data && response.data.message ? response.data.message : this.t('request_failed');
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }

        escapeAttribute(value) {
            return String(value).replace(/[^a-z0-9_-]/gi, '');
        }
    }

    class MDCATDashboardController {
        constructor(root) {
            this.root = root;
            this.elements = {
                loading: root.querySelector('.mdcat-dashboard__loading'),
                message: root.querySelector('.mdcat-dashboard__message'),
                content: root.querySelector('.mdcat-dashboard__content'),
                statsGrid: root.querySelector('.mdcat-dashboard__stats-grid'),
                streakSection: root.querySelector('.mdcat-dashboard__streak'),
                snapshot: root.querySelector('.mdcat-dashboard__snapshot'),
                actionsGrid: root.querySelector('.mdcat-dashboard__actions-grid'),
                activity: root.querySelector('.mdcat-dashboard__activity')
            };

            this.fetchStudentDashboard();
        }

        async fetchStudentDashboard() {
            this.setLoading(true);
            this.hideMessage();

            const response = await this.request('mdcat_get_student_dashboard', {});

            this.setLoading(false);

            if (!response || !response.success || !response.data) {
                this.showMessage(this.getErrorMessage(response), 'error');
                return;
            }

            this.renderStudentDashboard(response.data);
        }

        renderStudentDashboard(data) {
            const stats = data.stats || {};
            const recentActivity = Array.isArray(data.recent_activity) ? data.recent_activity : [];
            const snapshot = data.performance_snapshot || {};
            const streak = data.streak || {};

            if (!stats.total_attempts && !recentActivity.length) {
                this.hide(this.elements.content);
                this.showMessage(this.t('dashboard_empty'), 'empty');
                return;
            }

            this.renderStatsCards(stats);
            this.renderStreakSection(streak);
            this.renderPerformanceSnapshot(snapshot);
            this.renderQuickActions();
            this.renderRecentActivity(recentActivity);
            this.show(this.elements.content);
        }

        renderStatsCards(stats) {
            const cards = [
                {
                    label: this.t('dashboard_total_attempts'),
                    value: this.escapeHtml(stats.total_attempts || 0),
                    icon: '📝',
                    modifier: 'attempts'
                },
                {
                    label: this.t('dashboard_accuracy'),
                    value: this.escapeHtml(stats.overall_accuracy || 0) + '%',
                    icon: '🎯',
                    modifier: 'accuracy'
                },
                {
                    label: this.t('dashboard_correct'),
                    value: this.escapeHtml(stats.total_correct_answers || 0),
                    icon: '✅',
                    modifier: 'correct'
                },
                {
                    label: this.t('dashboard_wrong'),
                    value: this.escapeHtml(stats.total_wrong_answers || 0),
                    icon: '❌',
                    modifier: 'wrong'
                },
                {
                    label: this.t('dashboard_bookmarks'),
                    value: this.escapeHtml(stats.bookmarked_questions_count || 0),
                    icon: '🔖',
                    modifier: 'bookmarks'
                }
            ];

            this.elements.statsGrid.innerHTML = '';

            cards.forEach((card) => {
                const element = document.createElement('div');
                element.className = `mdcat-dashboard__stat-card mdcat-dashboard__stat-card--${this.escapeAttribute(card.modifier)}`;
                element.innerHTML = `
                    <div class="mdcat-dashboard__stat-icon">${card.icon}</div>
                    <div class="mdcat-dashboard__stat-value">${card.value}</div>
                    <div class="mdcat-dashboard__stat-label">${this.escapeHtml(card.label)}</div>
                `;
                this.elements.statsGrid.appendChild(element);
            });
        }

        renderStreakSection(streak) {
            if (!this.elements.streakSection) {
                return;
            }

            this.elements.streakSection.innerHTML = '';

            const cards = [
                {
                    label: this.t('streak_current'),
                    value: this.escapeHtml(streak.current_streak || 0),
                    suffix: this.t('streak_days'),
                    icon: '🔥',
                    modifier: 'current'
                },
                {
                    label: this.t('streak_longest'),
                    value: this.escapeHtml(streak.longest_streak || 0),
                    suffix: this.t('streak_days'),
                    icon: '🏆',
                    modifier: 'longest'
                },
                {
                    label: this.t('streak_total_days'),
                    value: this.escapeHtml(streak.total_active_days || 0),
                    suffix: this.t('streak_days'),
                    icon: '📅',
                    modifier: 'total'
                },
                {
                    label: this.t('streak_last_active'),
                    value: this.formatLastActive(streak.last_active_date),
                    suffix: '',
                    icon: '⏰',
                    modifier: 'last-active'
                }
            ];

            const grid = document.createElement('div');
            grid.className = 'mdcat-streak__cards-grid';

            cards.forEach((card) => {
                const element = document.createElement('div');
                element.className = `mdcat-streak__card mdcat-streak__card--${this.escapeAttribute(card.modifier)}`;
                element.innerHTML = `
                    <div class="mdcat-streak__card-icon">${card.icon}</div>
                    <div class="mdcat-streak__card-value">${card.value}</div>
                    ${card.suffix ? `<div class="mdcat-streak__card-suffix">${this.escapeHtml(card.suffix)}</div>` : ''}
                    <div class="mdcat-streak__card-label">${this.escapeHtml(card.label)}</div>
                `;
                grid.appendChild(element);
            });

            this.elements.streakSection.appendChild(grid);
        }

        formatLastActive(dateString) {
            if (!dateString) {
                return this.t('streak_never');
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const active = new Date(dateString + 'T00:00:00');
            active.setHours(0, 0, 0, 0);

            const diffMs = today.getTime() - active.getTime();
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                return this.t('streak_today');
            }

            if (diffDays === 1) {
                return this.t('streak_yesterday');
            }

            return this.formatDate(dateString);
        }

        renderPerformanceSnapshot(snapshot) {
            const strong = Array.isArray(snapshot.strong_subjects) ? snapshot.strong_subjects : [];
            const weak = Array.isArray(snapshot.weak_subjects) ? snapshot.weak_subjects : [];

            this.elements.snapshot.innerHTML = '';

            const wrapper = document.createElement('div');
            wrapper.className = 'mdcat-dashboard__snapshot-grid';

            wrapper.appendChild(this.createSubjectList(this.t('dashboard_strong'), strong, 'strong', this.t('dashboard_no_strong')));
            wrapper.appendChild(this.createSubjectList(this.t('dashboard_weak'), weak, 'weak', this.t('dashboard_no_weak')));

            this.elements.snapshot.appendChild(wrapper);
        }

        createSubjectList(title, subjects, modifier, emptyText) {
            const container = document.createElement('div');
            container.className = `mdcat-dashboard__subject-list mdcat-dashboard__subject-list--${this.escapeAttribute(modifier)}`;

            const heading = document.createElement('h3');
            heading.className = 'mdcat-dashboard__subject-heading';
            heading.textContent = title;
            container.appendChild(heading);

            if (!subjects.length) {
                const empty = document.createElement('p');
                empty.className = 'mdcat-dashboard__subject-empty';
                empty.textContent = emptyText;
                container.appendChild(empty);
                return container;
            }

            const list = document.createElement('ul');
            list.className = 'mdcat-dashboard__subject-items';

            subjects.forEach((subject) => {
                const item = document.createElement('li');
                item.className = 'mdcat-dashboard__subject-item';
                item.innerHTML = `
                    <span class="mdcat-dashboard__subject-name">${this.escapeHtml(subject.subject_title || '')}</span>
                    <span class="mdcat-dashboard__subject-accuracy">${this.escapeHtml(subject.accuracy_percentage || 0)}%</span>
                `;
                list.appendChild(item);
            });

            container.appendChild(list);
            return container;
        }

        renderQuickActions() {
            const actions = [
                {
                    label: this.t('dashboard_continue'),
                    icon: '▶️',
                    modifier: 'continue',
                    href: '#'
                },
                {
                    label: this.t('dashboard_my_bookmarks'),
                    icon: '🔖',
                    modifier: 'bookmarks',
                    href: '#'
                },
                {
                    label: this.t('dashboard_wrong_questions'),
                    icon: '❌',
                    modifier: 'wrong',
                    href: '#'
                },
                {
                    label: this.t('dashboard_attempt_history'),
                    icon: '📋',
                    modifier: 'history',
                    href: '#'
                },
                {
                    label: this.t('dashboard_analytics'),
                    icon: '📊',
                    modifier: 'analytics',
                    href: '#'
                }
            ];

            this.elements.actionsGrid.innerHTML = '';

            actions.forEach((action) => {
                const card = document.createElement('a');
                card.className = `mdcat-dashboard__action-card mdcat-dashboard__action-card--${this.escapeAttribute(action.modifier)}`;
                card.href = action.href;
                card.dataset.action = action.modifier;
                card.innerHTML = `
                    <span class="mdcat-dashboard__action-icon">${action.icon}</span>
                    <span class="mdcat-dashboard__action-label">${this.escapeHtml(action.label)}</span>
                `;
                this.elements.actionsGrid.appendChild(card);
            });
        }

        renderRecentActivity(items) {
            this.elements.activity.innerHTML = '';

            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'mdcat-dashboard__activity-empty';
                empty.textContent = this.t('dashboard_no_activity');
                this.elements.activity.appendChild(empty);
                return;
            }

            const tableWrap = document.createElement('div');
            tableWrap.className = 'mdcat-dashboard__activity-table-wrap';

            const table = document.createElement('table');
            table.className = 'mdcat-dashboard__activity-table';

            const thead = document.createElement('thead');
            thead.innerHTML = `
                <tr>
                    <th scope="col">${this.escapeHtml(this.t('dashboard_subject'))}</th>
                    <th scope="col">${this.escapeHtml(this.t('dashboard_chapter'))}</th>
                    <th scope="col">${this.escapeHtml(this.t('dashboard_quiz'))}</th>
                    <th scope="col">${this.escapeHtml(this.t('dashboard_score'))}</th>
                    <th scope="col">${this.escapeHtml(this.t('dashboard_date'))}</th>
                </tr>
            `;
            table.appendChild(thead);

            const tbody = document.createElement('tbody');

            items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${this.escapeHtml(item.subject_title || '')}</td>
                    <td>${this.escapeHtml(item.chapter_title || '')}</td>
                    <td>${this.escapeHtml(item.collection_title || '')}</td>
                    <td><strong>${this.escapeHtml(item.score || 0)}</strong> / ${this.escapeHtml(item.total_questions || 0)}</td>
                    <td>${this.escapeHtml(this.formatDate(item.completed_at))}</td>
                `;
                tbody.appendChild(row);
            });

            table.appendChild(tbody);
            tableWrap.appendChild(table);
            this.elements.activity.appendChild(tableWrap);
        }

        formatDate(dateString) {
            if (!dateString) {
                return '-';
            }

            try {
                const date = new Date(dateString);

                if (Number.isNaN(date.getTime())) {
                    return dateString;
                }

                return date.toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } catch (error) {
                return dateString;
            }
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                return await response.json();
            } catch (error) {
                return this.errorResponse(this.t('request_failed'));
            }
        }

        setLoading(isLoading) {
            if (isLoading) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        showMessage(message, type) {
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.show(this.elements.message);
        }

        hideMessage() {
            this.elements.message.textContent = '';
            this.hide(this.elements.message);
        }

        getErrorMessage(response) {
            return response && response.data && response.data.message ? response.data.message : this.t('request_failed');
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }

        escapeAttribute(value) {
            return String(value).replace(/[^a-z0-9_-]/gi, '');
        }
    }

    class MDCATStreakController {
        constructor(root) {
            this.root = root;
            this.elements = {
                loading: root.querySelector('.mdcat-streak__loading'),
                message: root.querySelector('.mdcat-streak__message'),
                content: root.querySelector('.mdcat-streak__content'),
                cardsGrid: root.querySelector('.mdcat-streak__cards-grid')
            };

            this.fetchStreakSummary();
        }

        async fetchStreakSummary() {
            this.setLoading(true);
            this.hideMessage();

            const response = await this.request('mdcat_get_streak_summary', {});

            this.setLoading(false);

            if (!response || !response.success || !response.data) {
                this.showMessage(this.getErrorMessage(response), 'error');
                return;
            }

            this.renderStreakWidget(response.data);
        }

        renderStreakWidget(data) {
            const cards = [
                {
                    label: this.t('streak_current'),
                    value: this.escapeHtml(data.current_streak || 0),
                    suffix: this.t('streak_days'),
                    icon: '🔥',
                    modifier: 'current'
                },
                {
                    label: this.t('streak_longest'),
                    value: this.escapeHtml(data.longest_streak || 0),
                    suffix: this.t('streak_days'),
                    icon: '🏆',
                    modifier: 'longest'
                },
                {
                    label: this.t('streak_total_days'),
                    value: this.escapeHtml(data.total_active_days || 0),
                    suffix: this.t('streak_days'),
                    icon: '📅',
                    modifier: 'total'
                },
                {
                    label: this.t('streak_last_active'),
                    value: this.formatLastActive(data.last_active_date),
                    suffix: '',
                    icon: '⏰',
                    modifier: 'last-active'
                }
            ];

            this.elements.cardsGrid.innerHTML = '';

            cards.forEach((card) => {
                const element = document.createElement('div');
                element.className = `mdcat-streak__card mdcat-streak__card--${this.escapeAttribute(card.modifier)}`;
                element.innerHTML = `
                    <div class="mdcat-streak__card-icon">${card.icon}</div>
                    <div class="mdcat-streak__card-value">${card.value}</div>
                    ${card.suffix ? `<div class="mdcat-streak__card-suffix">${this.escapeHtml(card.suffix)}</div>` : ''}
                    <div class="mdcat-streak__card-label">${this.escapeHtml(card.label)}</div>
                `;
                this.elements.cardsGrid.appendChild(element);
            });

            this.show(this.elements.content);
        }

        formatLastActive(dateString) {
            if (!dateString) {
                return this.t('streak_never');
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const active = new Date(dateString + 'T00:00:00');
            active.setHours(0, 0, 0, 0);

            const diffMs = today.getTime() - active.getTime();
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                return this.t('streak_today');
            }

            if (diffDays === 1) {
                return this.t('streak_yesterday');
            }

            try {
                const date = new Date(dateString);

                if (Number.isNaN(date.getTime())) {
                    return dateString;
                }

                return date.toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } catch (error) {
                return dateString;
            }
        }

        async request(action, payload) {
            if (!window.MDCATQuiz || !MDCATQuiz.ajax_url) {
                return this.errorResponse(this.t('request_failed'));
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', MDCATQuiz.nonce || '');

            Object.keys(payload || {}).forEach((key) => {
                formData.append(key, payload[key]);
            });

            try {
                const response = await window.fetch(MDCATQuiz.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    return this.errorResponse(this.t('request_failed'));
                }

                return await response.json();
            } catch (error) {
                return this.errorResponse(this.t('request_failed'));
            }
        }

        setLoading(isLoading) {
            if (isLoading) {
                this.show(this.elements.loading);
            } else {
                this.hide(this.elements.loading);
            }
        }

        showMessage(message, type) {
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.show(this.elements.message);
        }

        hideMessage() {
            this.elements.message.textContent = '';
            this.hide(this.elements.message);
        }

        getErrorMessage(response) {
            return response && response.data && response.data.message ? response.data.message : this.t('request_failed');
        }

        errorResponse(message) {
            return {
                success: false,
                data: {
                    message: message || this.t('request_failed')
                }
            };
        }

        show(element) {
            if (element) {
                element.hidden = false;
            }
        }

        hide(element) {
            if (element) {
                element.hidden = true;
            }
        }

        t(key) {
            return window.MDCATQuiz && MDCATQuiz.i18n && MDCATQuiz.i18n[key] ? MDCATQuiz.i18n[key] : key;
        }

        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }

        escapeAttribute(value) {
            return String(value).replace(/[^a-z0-9_-]/gi, '');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.mdcat-dashboard').forEach((root) => {
            new MDCATDashboardController(root);
        });

        document.querySelectorAll('.mdcat-streak').forEach((root) => {
            new MDCATStreakController(root);
        });

        document.querySelectorAll('.mdcat-quiz').forEach((root) => {
            new MDCATQuizController(root);
        });

        document.querySelectorAll('.mdcat-attempt-history').forEach((root) => {
            new MDCATAttemptHistoryController(root);
        });

        document.querySelectorAll('.mdcat-performance').forEach((root) => {
            new MDCATPerformanceController(root);
        });

        document.querySelectorAll('.mdcat-revision-list').forEach((root) => {
            new MDCATRevisionListController(root);
        });
    });
}());
