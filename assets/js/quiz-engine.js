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
                isCompleted: false
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
            this.startTimer();
            this.persistState();
            this.renderQuestion();
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

            const title = document.createElement('h3');
            title.className = 'mdcat-quiz__question-text';
            title.textContent = question.question || '';

            const options = document.createElement('div');
            options.className = 'mdcat-quiz__options';

            Object.keys(question.options || {}).forEach((key) => {
                options.appendChild(this.createOptionButton(question.id, key, question.options[key]));
            });

            card.appendChild(title);
            card.appendChild(options);
            this.elements.question.appendChild(card);
            this.persistState();
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

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.mdcat-quiz').forEach((root) => {
            new MDCATQuizController(root);
        });

        document.querySelectorAll('.mdcat-attempt-history').forEach((root) => {
            new MDCATAttemptHistoryController(root);
        });
    });
}());
