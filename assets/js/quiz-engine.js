(function () {
    'use strict';

    class MDCATQuizController {
        constructor(root) {
            this.root = root;
            this.collectionId = parseInt(root.dataset.collectionId || '0', 10);
            this.attemptId = 0;
            this.questions = [];
            this.currentIndex = 0;
            this.totalSeconds = 0;
            this.remainingSeconds = 0;
            this.timer = null;
            this.isBusy = false;

            this.elements = {
                startWrap: root.querySelector('.mdcat-quiz__start'),
                startButton: root.querySelector('.mdcat-quiz__start-button'),
                loading: root.querySelector('.mdcat-quiz__loading'),
                question: root.querySelector('.mdcat-quiz__question'),
                result: root.querySelector('.mdcat-quiz__result'),
                message: root.querySelector('.mdcat-quiz__message'),
                timer: root.querySelector('.mdcat-quiz__timer'),
                progress: root.querySelector('.mdcat-quiz__progress')
            };

            this.bindEvents();
            this.validateInitialState();
        }

        bindEvents() {
            if (!this.elements.startButton) {
                return;
            }

            this.elements.startButton.addEventListener('click', () => this.startQuiz());
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

        async startQuiz() {
            if (this.isBusy || !this.collectionId) {
                return;
            }

            this.setBusy(true);
            this.clearMessage();

            const response = await this.request('mdcat_start_quiz', {
                collection_id: this.collectionId
            });

            if (!response.success) {
                this.handleError(response);
                this.setBusy(false);
                return;
            }

            this.attemptId = parseInt(response.data.attempt_id, 10);
            this.totalSeconds = parseInt(response.data.total_time, 10) * 60;
            this.remainingSeconds = this.totalSeconds;

            await this.loadQuestions();
        }

        async loadQuestions() {
            const response = await this.request('mdcat_get_questions', {
                attempt_id: this.attemptId
            });

            this.setBusy(false);

            if (!response.success) {
                this.handleError(response);
                return;
            }

            this.questions = Array.isArray(response.data.questions) ? response.data.questions : [];

            if (!this.questions.length) {
                this.setMessage(this.t('request_failed'), 'error');
                return;
            }

            this.hide(this.elements.startWrap);
            this.show(this.elements.question);
            this.startTimer();
            this.renderQuestion();
        }

        renderQuestion() {
            const question = this.questions[this.currentIndex];

            if (!question) {
                this.completeQuiz();
                return;
            }

            this.updateProgress();
            this.clearMessage();
            this.elements.question.innerHTML = '';

            const title = document.createElement('h3');
            title.className = 'mdcat-quiz__question-text';
            title.textContent = question.question;

            const options = document.createElement('div');
            options.className = 'mdcat-quiz__options';

            Object.keys(question.options || {}).forEach((key) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'button mdcat-quiz__option';
                button.dataset.option = key;
                button.textContent = `${key.toUpperCase()}. ${question.options[key]}`;
                button.addEventListener('click', () => this.submitAnswer(question.id, key, button));
                options.appendChild(button);
            });

            this.elements.question.appendChild(title);
            this.elements.question.appendChild(options);
        }

        async submitAnswer(questionId, selectedOption, selectedButton) {
            if (this.isBusy) {
                return;
            }

            this.setBusy(true);
            this.disableOptions();

            const response = await this.request('mdcat_save_answer', {
                attempt_id: this.attemptId,
                question_id: questionId,
                selected_option: selectedOption,
                question_index: this.currentIndex
            });

            if (!response.success) {
                this.handleError(response);
                this.setBusy(false);
                this.enableOptions();
                return;
            }

            this.showFeedback(selectedButton, Boolean(response.data.is_correct));

            window.setTimeout(() => {
                this.setBusy(false);
                this.currentIndex += 1;

                if (this.currentIndex >= this.questions.length) {
                    this.completeQuiz();
                    return;
                }

                this.renderQuestion();
            }, 700);
        }

        async completeQuiz() {
            if (!this.attemptId) {
                return;
            }

            this.stopTimer();
            this.setBusy(true);

            const response = await this.request('mdcat_complete_quiz', {
                attempt_id: this.attemptId
            });

            this.setBusy(false);

            if (!response.success) {
                this.handleError(response);
                return;
            }

            await this.loadResult(response.data);
        }

        async loadResult(fallbackResult) {
            const response = await this.request('mdcat_get_result', {
                attempt_id: this.attemptId
            });

            if (!response.success) {
                this.renderResult(fallbackResult || {});
                return;
            }

            this.renderResult(response.data);
        }

        renderResult(result) {
            this.hide(this.elements.question);
            this.show(this.elements.result);
            this.elements.progress.textContent = '';
            this.elements.timer.textContent = '';

            const score = result.score || 0;
            const total = result.total_questions || 0;
            const correct = result.correct_answers || 0;
            const wrong = result.wrong_answers || 0;

            this.elements.result.innerHTML = `
                <h3>${this.escapeHtml(this.t('quiz_complete'))}</h3>
                <p>Score: ${this.escapeHtml(score)} / ${this.escapeHtml(total)}</p>
                <p>Correct: ${this.escapeHtml(correct)}</p>
                <p>Wrong: ${this.escapeHtml(wrong)}</p>
            `;
        }

        startTimer() {
            this.updateTimer();
            this.stopTimer();

            this.timer = window.setInterval(() => {
                this.remainingSeconds -= 1;
                this.updateTimer();

                if (this.remainingSeconds <= 0) {
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

        updateTimer() {
            const seconds = Math.max(0, this.remainingSeconds);
            const minutes = Math.floor(seconds / 60);
            const remaining = seconds % 60;
            this.elements.timer.textContent = `${minutes}:${String(remaining).padStart(2, '0')}`;
        }

        updateProgress() {
            this.elements.progress.textContent = `${this.currentIndex + 1} / ${this.questions.length}`;
        }

        showFeedback(button, isCorrect) {
            button.style.backgroundColor = isCorrect ? '#198754' : '#dc3545';
            button.style.borderColor = isCorrect ? '#198754' : '#dc3545';
            button.style.color = '#fff';
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
                return {
                    success: false,
                    data: {
                        message: this.t('request_failed')
                    }
                };
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

                return await response.json();
            } catch (error) {
                return {
                    success: false,
                    data: {
                        message: this.t('request_failed')
                    }
                };
            }
        }

        handleError(response) {
            const message = response && response.data && response.data.message
                ? response.data.message
                : this.t('request_failed');

            this.setMessage(message, 'error');
        }

        setBusy(isBusy) {
            this.isBusy = isBusy;

            if (this.elements.startButton) {
                this.elements.startButton.disabled = isBusy;
            }

            if (isBusy) {
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
            this.elements.message.textContent = message || '';
            this.elements.message.dataset.type = type || '';
            this.elements.message.style.color = type === 'error' ? '#dc3545' : '#198754';
        }

        clearMessage() {
            this.setMessage('', '');
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

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.mdcat-quiz').forEach((root) => {
            new MDCATQuizController(root);
        });
    });
}());
