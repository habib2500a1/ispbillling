/**
 * AI Operations Copilot — UI enhancements (advisory layer only).
 */
(function () {
    'use strict';

    function scrollMessages() {
        var box = document.querySelector('[data-ai-messages]');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    function initTheme() {
        var root = document.querySelector('.ai-copilot-module') || document.body;
        var stored = localStorage.getItem('ai-copilot-theme');
        if (stored === 'light') {
            root.classList.add('ai-theme-light');
        }
        document.querySelectorAll('[data-ai-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.classList.toggle('ai-theme-light');
                localStorage.setItem('ai-copilot-theme', root.classList.contains('ai-theme-light') ? 'light' : 'dark');
            });
        });
    }

    function initVoicePlaceholder() {
        document.querySelectorAll('[data-ai-voice]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.querySelector('[data-ai-input]');
                if (!input) {
                    return;
                }
                if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                    input.placeholder = 'Voice input not supported in this browser — type your question.';
                    input.focus();
                    return;
                }
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                var rec = new SpeechRecognition();
                rec.lang = 'en-US';
                rec.interimResults = false;
                rec.maxAlternatives = 1;
                btn.classList.add('ai-voice-btn--active');
                rec.onresult = function (event) {
                    input.value = event.results[0][0].transcript;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    btn.classList.remove('ai-voice-btn--active');
                };
                rec.onerror = rec.onend = function () {
                    btn.classList.remove('ai-voice-btn--active');
                };
                rec.start();
            });
        });
    }

    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw-ai.js', { scope: '/admin/ai-copilot' }).catch(function () {});
        }
    }

    function init() {
        initTheme();
        initVoicePlaceholder();
        scrollMessages();
        registerServiceWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', function () {
        initTheme();
        initVoicePlaceholder();
        scrollMessages();
    });

    document.addEventListener('livewire:initialized', function () {
        if (typeof Livewire !== 'undefined') {
            Livewire.hook('morph.updated', function () {
                scrollMessages();
            });
        }
    });
})();
